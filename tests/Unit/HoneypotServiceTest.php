<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = new HoneypotService();
});

test('it generates honeypot fields', function () {
    $fields = $this->service->generate();

    expect($fields)->toBeArray()
        ->toHaveKey('hp_website')
        ->toHaveKey('hp_started_at')
        ->toHaveKey('hp_token');
    
    expect($fields['hp_website'])->toBe('');
    expect($fields['hp_started_at'])->toBeInt();
    expect($fields['hp_token'])->toBeString()->toHaveLength(24);
});

test('it validates valid honeypot data', function () {
    $data = [
        'hp_website' => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token' => str_repeat('a', 24),
    ];

    $this->service->validate($data);
    
    expect(true)->toBeTrue(); // No exception thrown
});

test('it fails when honeypot field is filled', function () {
    $data = [
        'hp_website' => 'https://spam.com',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token' => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when submitted too quickly', function () {
    $data = [
        'hp_website' => '',
        'hp_started_at' => now()->getTimestamp(), // Just now
        'hp_token' => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when token is too short', function () {
    $data = [
        'hp_website' => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token' => 'short',
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it respects custom minimum seconds', function () {
    $data = [
        'hp_website' => '',
        'hp_started_at' => now()->subSeconds(2)->getTimestamp(),
        'hp_token' => str_repeat('a', 24),
    ];

    // Should pass with custom minimum of 1 second
    $this->service->validate($data, 1);
    
    expect(true)->toBeTrue();
});

test('it uses config values', function () {
    config(['livewire-honeypot.token_length' => 32]);

    $fields = $this->service->generate();

    expect($fields['hp_token'])->toHaveLength(32);
});

test('it translates error messages', function () {
    app()->setLocale('nl');

    $data = [
        'hp_website' => 'spam',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token' => str_repeat('a', 24),
    ];

    try {
        $this->service->validate($data);
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('Spam');
    }
});
