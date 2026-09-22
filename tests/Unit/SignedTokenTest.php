<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->freezeTime();
    $this->service = app(HoneypotService::class);
});

test('generated tokens have distinct nonces and authenticate their timestamps', function () {
    $first = $this->service->generate();
    $second = $this->service->generate();

    expect($first['hp_token'])->not->toBe($second['hp_token']);
    expect($this->service->startedAtFromToken($first['hp_token']))->toBe(now()->getTimestamp());

    [$nonce, $timestamp, $signature] = explode('.', $first['hp_token']);
    expect($signature)->toBe(hash_hmac('sha256', "livewire-honeypot|{$nonce}.{$timestamp}", config('app.key')));
});

test('backdating the posted timestamp cannot bypass the minimum fill time', function () {
    $data = $this->service->generate();
    $data['hp_started_at'] = now()->subMinutes(10)->getTimestamp();

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Form submitted too quickly.');
});

test('changing any signed token part is rejected', function (int $part) {
    $data = $this->service->generate();
    $parts = explode('.', $data['hp_token']);
    $parts[$part] = match ($part) {
        0 => ($parts[0][0] === 'a' ? 'b' : 'a') . substr($parts[0], 1),
        1 => (string) (now()->getTimestamp() - 10),
        2 => ($parts[2][0] === 'a' ? 'b' : 'a') . substr($parts[2], 1),
    };
    $data['hp_token'] = implode('.', $parts);
    $this->travel(5)->seconds();
    Event::fake([HoneypotDetected::class]);

    try {
        $this->service->validate($data);
        $this->fail('A tampered token was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('hp_token');
    }

    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'invalid_form_data');
})->with([0, 1, 2]);

test('unsigned and malformed tokens are rejected', function (mixed $token) {
    expect($this->service->startedAtFromToken($token))->toBeNull();

    expect(fn () => $this->service->validate([
        'hp_website' => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token' => $token,
    ]))->toThrow(ValidationException::class);
})->with([
    'old unsigned token' => [str_repeat('a', 24)],
    'missing token' => [null],
    'empty token' => [''],
    'array token' => [['value']],
    'integer token' => [123],
    'boolean token' => [true],
    'extra segment' => ['nonce.123.signature.extra'],
    'wrong signature' => [str_repeat('a', 24) . '.123.' . str_repeat('0', 64)],
]);

test('invalid timestamp encodings are rejected even with a valid signature', function (string $timestamp) {
    $payload = str_repeat('a', 24) . '.' . $timestamp;
    $token = $payload . '.' . hash_hmac('sha256', 'livewire-honeypot|' . $payload, config('app.key'));

    expect($this->service->startedAtFromToken($token))->toBeNull();
})->with(['0', '-1', '1e9', '1.5', str_repeat('9', 40), 'not-a-time']);

test('the signed timestamp enforces the existing time boundaries', function (int $age, bool $accepted) {
    $data = $this->service->generate();
    $this->travel($age)->seconds();
    // Posting a fresh timestamp must not extend an expired token's life.
    $data['hp_started_at'] = now()->subSeconds(5)->getTimestamp();

    if ($accepted) {
        $this->service->validate($data);
        expect($this->service->startedAtFromToken($data['hp_token']))->toBe(now()->getTimestamp() - $age);
    } else {
        expect(fn () => $this->service->validate($data))->toThrow(ValidationException::class);
    }
})->with([[4, false], [5, true], [3600, true], [3601, false]]);

test('previous app keys keep open forms valid until the key is removed', function (string $oldKey) {
    config(['app.key' => $oldKey]);
    $data = $this->service->generate();
    $this->travel(5)->seconds();

    config(['app.key' => 'new-application-key', 'app.previous_keys' => [$oldKey]]);
    $this->service->validate($data);
    $newToken = $this->service->token();

    config(['app.previous_keys' => []]);
    expect(fn () => $this->service->validate($data))->toThrow(ValidationException::class);
    expect($this->service->startedAtFromToken($newToken))->toBe(now()->getTimestamp());

    config(['app.key' => $oldKey]);
    expect($this->service->startedAtFromToken($newToken))->toBeNull();
})->with(['old-application-key', 'base64:' . base64_encode(str_repeat('k', 32))]);

test('empty previous keys cannot authenticate forged tokens', function () {
    config(['app.previous_keys' => ['', null, false, 0]]);
    $payload = str_repeat('a', 24) . '.' . now()->subSeconds(10)->getTimestamp();
    $forged = $payload . '.' . hash_hmac('sha256', 'livewire-honeypot|' . $payload, '');

    expect($this->service->startedAtFromToken($forged))->toBeNull();
});

test('signing and verification fail clearly without a current app key', function (string $operation) {
    config(['app.key' => null, 'app.previous_keys' => ['old-key']]);

    match ($operation) {
        'generate' => $this->service->generate(),
        'token' => $this->service->token(),
        'verify' => $this->service->startedAtFromToken('invalid'),
        'validate' => $this->service->validate([]),
    };
})->with(['generate', 'token', 'verify', 'validate'])->throws(MissingAppKeyException::class);

test('fake mode still bypasses validation without a signing key', function () {
    config(['app.key' => null]);
    HoneypotService::fake();
    $this->service->validate([]);
})->throwsNoExceptions();
