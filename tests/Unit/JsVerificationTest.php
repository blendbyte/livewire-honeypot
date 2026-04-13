<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;

// ---------------------------------------------------------------------------
// HoneypotService::validate() — JS verification
// ---------------------------------------------------------------------------

test('js verification passes when hp_js is populated', function () {
    config(['livewire-honeypot.require_js_verification' => true]);

    $service = new HoneypotService();

    $data = [
        config('livewire-honeypot.field_name', 'hp_website') => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('x', 24),
        'hp_js'         => base64_encode((string) time()),
    ];

    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    expect(fn () => $service->validate($data))->not->toThrow(\Throwable::class);
});

test('js verification fails when hp_js is empty', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $service = new HoneypotService();
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $data = [
        $fieldName      => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('x', 24),
        'hp_js'         => '',
    ];

    expect(fn () => $service->validate($data))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('js verification fails when hp_js is missing from data', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $service = new HoneypotService();
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $data = [
        $fieldName      => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('x', 24),
    ];

    expect(fn () => $service->validate($data))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('js verification fails when hp_js is whitespace only', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $service = new HoneypotService();
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $data = [
        $fieldName      => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('x', 24),
        'hp_js'         => '   ',
    ];

    expect(fn () => $service->validate($data))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('js verification is skipped when disabled', function () {
    config([
        'livewire-honeypot.require_js_verification' => false,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $service = new HoneypotService();
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $data = [
        $fieldName      => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token'      => str_repeat('x', 24),
        'hp_js'         => '', // empty — but check is disabled
    ];

    expect(fn () => $service->validate($data))->not->toThrow(\Throwable::class);
});

test('js verification is disabled by default', function () {
    // Default config has require_js_verification = false
    expect(config('livewire-honeypot.require_js_verification'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// HoneypotDetected event — js_verification_failed reason
// ---------------------------------------------------------------------------

test('js verification failure dispatches HoneypotDetected event with correct reason', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    \Illuminate\Support\Facades\Event::fake();

    $service = new HoneypotService();
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    try {
        $service->validate([
            $fieldName      => '',
            'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
            'hp_token'      => str_repeat('x', 24),
            'hp_js'         => '',
        ]);
    } catch (\Throwable) {
        // expected
    }

    \Illuminate\Support\Facades\Event::assertDispatched(
        \Blendbyte\LivewireHoneypot\Events\HoneypotDetected::class,
        fn ($event) => $event->reason === 'js_verification_failed'
    );
});

// ---------------------------------------------------------------------------
// Translation
// ---------------------------------------------------------------------------

test('js_verification_failed translation key exists in English', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.js_verification_failed'))
        ->toBe('JavaScript verification failed.');
});
