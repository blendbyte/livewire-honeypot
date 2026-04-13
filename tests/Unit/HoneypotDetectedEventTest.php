<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service   = new HoneypotService();
    $this->fieldName = config('livewire-honeypot.field_name', 'hp_website');
});

// ---------------------------------------------------------------------------
// HoneypotDetected event — structure
// ---------------------------------------------------------------------------

test('HoneypotDetected has correct public properties', function () {
    $event = new HoneypotDetected(
        fieldName: 'hp_website',
        reason: 'honeypot_filled',
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0',
        component: 'App\\Livewire\\ContactForm',
    );

    expect($event->fieldName)->toBe('hp_website');
    expect($event->reason)->toBe('honeypot_filled');
    expect($event->ipAddress)->toBe('127.0.0.1');
    expect($event->userAgent)->toBe('Mozilla/5.0');
    expect($event->component)->toBe('App\\Livewire\\ContactForm');
});

test('HoneypotDetected component defaults to null', function () {
    $event = new HoneypotDetected(
        fieldName: 'hp_website',
        reason: 'submitted_too_quickly',
        ipAddress: null,
        userAgent: null,
    );

    expect($event->component)->toBeNull();
});

// ---------------------------------------------------------------------------
// HoneypotService dispatches event on detection
// ---------------------------------------------------------------------------

test('it dispatches HoneypotDetected when honeypot field is filled', function () {
    Event::fake();

    $data = [
        $this->fieldName => 'spam content',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try {
        $this->service->validate($data);
    } catch (ValidationException) {}

    Event::assertDispatched(HoneypotDetected::class, function (HoneypotDetected $event) {
        return $event->reason === 'honeypot_filled'
            && $event->fieldName === $this->fieldName;
    });
});

test('it dispatches HoneypotDetected when submitted too quickly', function () {
    Event::fake();

    $data = [
        $this->fieldName => '',
        'hp_started_at'  => now()->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try {
        $this->service->validate($data);
    } catch (ValidationException) {}

    Event::assertDispatched(HoneypotDetected::class, function (HoneypotDetected $event) {
        return $event->reason === 'submitted_too_quickly';
    });
});

test('it dispatches HoneypotDetected when form data is invalid', function () {
    Event::fake();

    $data = [
        $this->fieldName => '',
        'hp_started_at'  => 0,
        'hp_token'       => str_repeat('a', 24),
    ];

    try {
        $this->service->validate($data);
    } catch (ValidationException) {}

    Event::assertDispatched(HoneypotDetected::class, function (HoneypotDetected $event) {
        return $event->reason === 'invalid_form_data';
    });
});

test('it does not dispatch HoneypotDetected on a valid submission', function () {
    Event::fake();

    $data = [
        $this->fieldName => '',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    $this->service->validate($data);

    Event::assertNotDispatched(HoneypotDetected::class);
});

test('it still throws ValidationException after dispatching the event', function () {
    Event::fake();

    $data = [
        $this->fieldName => 'spam',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    expect(fn () => $this->service->validate($data))
        ->toThrow(ValidationException::class);

    Event::assertDispatched(HoneypotDetected::class);
});
