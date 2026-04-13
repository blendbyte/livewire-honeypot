<?php

use Blendbyte\LivewireHoneypot\HoneypotServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

test('it merges the package config', function () {
    // The service provider merges defaults; TestCase sets explicit values,
    // so verify that all expected keys are present and have correct types.
    expect(config('livewire-honeypot.minimum_fill_seconds'))->toBeInt();
    expect(config('livewire-honeypot.field_name'))->toBeString();
    expect(config('livewire-honeypot.token_min_length'))->toBeInt();
    expect(config('livewire-honeypot.token_length'))->toBeInt();
});

test('it registers all four config keys', function () {
    $config = config('livewire-honeypot');

    expect($config)->toBeArray()
        ->toHaveKey('minimum_fill_seconds')
        ->toHaveKey('field_name')
        ->toHaveKey('token_min_length')
        ->toHaveKey('token_length');
});

// ---------------------------------------------------------------------------
// Views
// ---------------------------------------------------------------------------

test('it registers the views namespace', function () {
    expect(View::exists('livewire-honeypot::components.honeypot'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Translations
// ---------------------------------------------------------------------------

test('it registers the translations namespace', function () {
    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detected.');
    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Form submitted too quickly.');
    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (leave empty)');
});

// ---------------------------------------------------------------------------
// Blade component
// ---------------------------------------------------------------------------

test('it registers the x-honeypot blade component', function () {
    $aliases = Blade::getClassComponentAliases();

    expect($aliases)->toHaveKey('honeypot');
});

// ---------------------------------------------------------------------------
// Publish tags
// ---------------------------------------------------------------------------

test('it registers the livewire-honeypot-views publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(HoneypotServiceProvider::class, 'livewire-honeypot-views');

    expect($paths)->not->toBeEmpty();
});

test('it registers the livewire-honeypot-translations publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(HoneypotServiceProvider::class, 'livewire-honeypot-translations');

    expect($paths)->not->toBeEmpty();
});

test('it registers the livewire-honeypot-config publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(HoneypotServiceProvider::class, 'livewire-honeypot-config');

    expect($paths)->not->toBeEmpty();
});
