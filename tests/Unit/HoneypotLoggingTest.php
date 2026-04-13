<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\HoneypotServiceProvider;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

/**
 * Set up a TestHandler-backed log channel named 'test' and return the handler
 * so assertions can be made against recorded log entries.
 */
function setupTestLogChannel(): TestHandler
{
    $handler = new TestHandler();
    $logger  = new Logger('test', [$handler]);

    app('log')->extend('test-channel', fn () => $logger);

    config([
        'logging.channels.test-channel' => [
            'driver' => 'test-channel',
        ],
        'logging.default'               => 'test-channel',
        'livewire-honeypot.logging.channel' => 'test-channel',
    ]);

    // Swap the resolved log driver so the default channel resolves to ours
    app('log')->setDefaultDriver('test-channel');

    return $handler;
}

beforeEach(function () {
    $this->service   = new HoneypotService();
    $this->fieldName = config('livewire-honeypot.field_name', 'hp_website');
});

// ---------------------------------------------------------------------------
// Logging disabled (default)
// ---------------------------------------------------------------------------

test('it does not log when logging is disabled', function () {
    $handler = setupTestLogChannel();
    config(['livewire-honeypot.logging.enabled' => false]);

    $data = [
        $this->fieldName => 'spam',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try { $this->service->validate($data); } catch (ValidationException) {}

    expect($handler->getRecords())->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Logging enabled
// ---------------------------------------------------------------------------

test('it logs at warning level when honeypot field is filled', function () {
    $handler = setupTestLogChannel();
    config(['livewire-honeypot.logging.enabled' => true]);

    // Re-boot the service provider so the listener is registered with updated config
    (new HoneypotServiceProvider(app()))->boot();

    $data = [
        $this->fieldName => 'spam content',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try { $this->service->validate($data); } catch (ValidationException) {}

    expect($handler->hasWarningThatPasses(
        fn ($record) => $record->message === 'Honeypot triggered'
            && ($record->context['reason'] ?? null) === 'honeypot_filled'
    ))->toBeTrue();
});

test('it logs at warning level when submitted too quickly', function () {
    $handler = setupTestLogChannel();
    config(['livewire-honeypot.logging.enabled' => true]);

    (new HoneypotServiceProvider(app()))->boot();

    $data = [
        $this->fieldName => '',
        'hp_started_at'  => now()->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try { $this->service->validate($data); } catch (ValidationException) {}

    expect($handler->hasWarningThatPasses(
        fn ($record) => $record->message === 'Honeypot triggered'
            && ($record->context['reason'] ?? null) === 'submitted_too_quickly'
    ))->toBeTrue();
});

test('it logs at configured level', function () {
    $handler = setupTestLogChannel();
    config([
        'livewire-honeypot.logging.enabled' => true,
        'livewire-honeypot.logging.level'   => 'error',
    ]);

    (new HoneypotServiceProvider(app()))->boot();

    $data = [
        $this->fieldName => 'spam',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try { $this->service->validate($data); } catch (ValidationException) {}

    expect($handler->hasErrorThatPasses(
        fn ($record) => $record->message === 'Honeypot triggered'
    ))->toBeTrue();
});

test('it logs the correct context fields', function () {
    $handler = setupTestLogChannel();
    config(['livewire-honeypot.logging.enabled' => true]);

    (new HoneypotServiceProvider(app()))->boot();

    $data = [
        $this->fieldName => 'spam',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    try { $this->service->validate($data); } catch (ValidationException) {}

    expect($handler->hasWarningThatPasses(function ($record) {
        return $record->message === 'Honeypot triggered'
            && array_key_exists('reason', $record->context)
            && array_key_exists('field_name', $record->context)
            && array_key_exists('ip', $record->context)
            && array_key_exists('user_agent', $record->context)
            && array_key_exists('component', $record->context);
    }))->toBeTrue();
});

test('it does not log on a valid submission', function () {
    $handler = setupTestLogChannel();
    config(['livewire-honeypot.logging.enabled' => true]);

    (new HoneypotServiceProvider(app()))->boot();

    $data = [
        $this->fieldName => '',
        'hp_started_at'  => now()->subSeconds(10)->getTimestamp(),
        'hp_token'       => str_repeat('a', 24),
    ];

    $this->service->validate($data);

    expect($handler->getRecords())->toBeEmpty();
});
