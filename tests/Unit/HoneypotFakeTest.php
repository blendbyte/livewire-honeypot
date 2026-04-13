<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = new HoneypotService();
    $this->fieldName = config('livewire-honeypot.field_name', 'hp_website');
});

// ---------------------------------------------------------------------------
// HoneypotService::fake() / isFake() / resetFake()
// ---------------------------------------------------------------------------

test('isFake returns false by default', function () {
    expect(HoneypotService::isFake())->toBeFalse();
});

test('fake() sets isFake to true', function () {
    HoneypotService::fake();

    expect(HoneypotService::isFake())->toBeTrue();
});

test('resetFake() restores isFake to false', function () {
    HoneypotService::fake();
    HoneypotService::resetFake();

    expect(HoneypotService::isFake())->toBeFalse();
});

test('validate() is bypassed when fake mode is active', function () {
    HoneypotService::fake();

    // This data would normally fail (honeypot filled, no token, zero timestamp)
    $this->service->validate([
        $this->fieldName => 'filled by bot',
        'hp_started_at'  => 0,
        'hp_token'       => '',
    ]);

    expect(true)->toBeTrue(); // reached here = no exception thrown
});

test('validate() still throws when fake mode is not active', function () {
    $data = [
        $this->fieldName => 'filled by bot',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('validate() resumes normal behaviour after resetFake()', function () {
    HoneypotService::fake();
    HoneypotService::resetFake();

    $data = [
        $this->fieldName => 'filled by bot',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    $this->service->validate($data);
})->throws(ValidationException::class);

test('fake mode does not persist between test instances (tearDown resets it)', function () {
    // This test relies on TestCase::tearDown() calling resetFake()
    // If a previous test called fake() without resetting, this would catch it
    expect(HoneypotService::isFake())->toBeFalse();
});
