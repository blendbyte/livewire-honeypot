<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = new HoneypotService();
});

// ---------------------------------------------------------------------------
// generate()
// ---------------------------------------------------------------------------

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

test('it respects token_length config when generating', function () {
    config(['livewire-honeypot.token_length' => 32]);

    $fields = $this->service->generate();

    expect($fields['hp_token'])->toHaveLength(32);
});

test('it sets hp_started_at to the current unix timestamp', function () {
    $before = now()->getTimestamp();
    $fields = $this->service->generate();
    $after = now()->getTimestamp();

    expect($fields['hp_started_at'])->toBeGreaterThanOrEqual($before)
        ->toBeLessThanOrEqual($after);
});

test('it uses configured field_name in generate', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    $fields = $this->service->generate();

    expect($fields)->toHaveKey('my_trap');
    expect($fields)->not->toHaveKey('hp_website');
    expect($fields['my_trap'])->toBe('');
});

// ---------------------------------------------------------------------------
// validate() — happy paths
// ---------------------------------------------------------------------------

test('it validates valid honeypot data', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);

    expect(true)->toBeTrue();
});

test('it respects custom minimum seconds parameter', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->subSeconds(2)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data, 1);

    expect(true)->toBeTrue();
});

test('it passes when minimum_fill_seconds is zero via config', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);

    expect(true)->toBeTrue();
});

test('it passes when minimum_fill_seconds is zero via parameter', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data, 0);

    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// validate() — honeypot bait field
// ---------------------------------------------------------------------------

test('it fails when honeypot field is filled', function () {
    $data = [
        'hp_website'    => 'https://spam.com',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when hp_website key is missing entirely', function () {
    $data = [
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it uses configured field_name in validate', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    $data = [
        'my_trap'       => 'spam',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

// ---------------------------------------------------------------------------
// validate() — time-trap
// ---------------------------------------------------------------------------

test('it fails when submitted too quickly', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when hp_started_at is in the future', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->addMinutes(5)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when hp_started_at is missing', function () {
    $data = [
        'hp_website' => '',
        'hp_token'   => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when hp_started_at is not an integer', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => 'not-a-timestamp',
        'hp_token'      => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

// ---------------------------------------------------------------------------
// validate() — token
// ---------------------------------------------------------------------------

test('it fails when token is too short', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => 'short',
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when hp_token is an empty string', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => '',
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it fails when hp_token key is missing entirely', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('it respects custom token_min_length config', function () {
    config(['livewire-honeypot.token_min_length' => 15]);

    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 12),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

// ---------------------------------------------------------------------------
// validate() — error messages and translations
// ---------------------------------------------------------------------------

test('it throws spam_detected error on filled honeypot field', function () {
    $data = [
        'hp_website'    => 'spam',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Spam detected.');
});

test('it throws submitted_too_quickly error on time-trap', function () {
    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Form submitted too quickly.');
});

test('it translates spam_detected to Dutch', function () {
    app()->setLocale('nl');

    $data = [
        'hp_website'    => 'spam',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Spam gedetecteerd.');
});

test('it translates submitted_too_quickly to Dutch', function () {
    app()->setLocale('nl');

    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Formulier te snel verzonden.');
});

test('it translates spam_detected to German', function () {
    app()->setLocale('de');

    $data = [
        'hp_website'    => 'spam',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Spam erkannt.');
});

test('it translates submitted_too_quickly to German', function () {
    app()->setLocale('de');

    $data = [
        'hp_website'    => '',
        'hp_started_at' => now()->getTimestamp(),
        'hp_token'      => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class, 'Form zu schnell abgeschickt.');
});
