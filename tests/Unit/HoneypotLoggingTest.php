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
        'hp_token'       => app(HoneypotService::class)->token(now()->subSeconds(10)->getTimestamp()),
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
        'hp_token'       => app(HoneypotService::class)->token(now()->subSeconds(10)->getTimestamp()),
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
        'hp_token'       => app(HoneypotService::class)->token(now()->getTimestamp()),
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
        'hp_token'       => app(HoneypotService::class)->token(now()->subSeconds(10)->getTimestamp()),
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
        'hp_token'       => app(HoneypotService::class)->token(now()->subSeconds(10)->getTimestamp()),
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
        'hp_token'       => app(HoneypotService::class)->token(now()->subSeconds(10)->getTimestamp()),
    ];

    $this->service->validate($data);

    expect($handler->getRecords())->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Filled value (logging.include_value)
// ---------------------------------------------------------------------------

/**
 * Submit $baitValue in the bait field with a valid token and return the log handler.
 */
function logBaitSubmission(mixed $baitValue, bool $includeValue, int $secondsAgo = 10): TestHandler
{
    $handler = setupTestLogChannel();
    config([
        'livewire-honeypot.logging.enabled'       => true,
        'livewire-honeypot.logging.include_value' => $includeValue,
    ]);

    (new HoneypotServiceProvider(app()))->boot();

    $startedAt = now()->subSeconds($secondsAgo)->getTimestamp();
    $data = [
        config('livewire-honeypot.field_name') => $baitValue,
        'hp_started_at' => $startedAt,
        'hp_token'      => app(HoneypotService::class)->token($startedAt),
    ];

    try { (new HoneypotService())->validate($data); } catch (ValidationException) {}

    return $handler;
}

test('it does not log the filled value by default', function () {
    $handler = logBaitSubmission('jane@example.com', includeValue: false);

    expect($handler->hasWarningThatPasses(
        fn ($record) => ($record->context['reason'] ?? null) === 'honeypot_filled'
            && ! array_key_exists('filled_value', $record->context)
    ))->toBeTrue();
});

test('it logs the filled value when include_value is enabled', function () {
    $handler = logBaitSubmission("https://spam.example\nfake log line", includeValue: true);

    expect($handler->hasWarningThatPasses(
        fn ($record) => $record->message === 'Honeypot triggered'
            && ($record->context['filled_value'] ?? null) === "https://spam.example\nfake log line"
    ))->toBeTrue();
});

test('it truncates long filled values in the log', function () {
    $handler = logBaitSubmission(str_repeat('a', 10_000), includeValue: true);

    expect($handler->hasWarningThatPasses(
        fn ($record) => ($record->context['filled_value'] ?? null) === str_repeat('a', 200) . '...'
    ))->toBeTrue();
});

test('it encodes non-string filled values for the log', function () {
    $handler = logBaitSubmission(['url' => 'https://spam.example'], includeValue: true);

    expect($handler->hasWarningThatPasses(
        fn ($record) => ($record->context['reason'] ?? null) === 'honeypot_filled'
            && ($record->context['filled_value'] ?? null) === '{"url":"https://spam.example"}'
    ))->toBeTrue();
});

test('it omits the filled value for reasons other than a filled bait field', function () {
    $handler = logBaitSubmission('', includeValue: true, secondsAgo: 0);

    expect($handler->hasWarningThatPasses(
        fn ($record) => ($record->context['reason'] ?? null) === 'submitted_too_quickly'
            && ! array_key_exists('filled_value', $record->context)
    ))->toBeTrue();
});
