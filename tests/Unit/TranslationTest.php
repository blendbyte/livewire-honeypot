<?php

// ---------------------------------------------------------------------------
// English (en) — default locale
// ---------------------------------------------------------------------------

test('en: spam_detected', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detected.');
});

test('en: submitted_too_quickly', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Form submitted too quickly.');
});

test('en: honeypot_label', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (leave empty)');
});

// ---------------------------------------------------------------------------
// Dutch (nl)
// ---------------------------------------------------------------------------

test('nl: spam_detected', function () {
    app()->setLocale('nl');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam gedetecteerd.');
});

test('nl: submitted_too_quickly', function () {
    app()->setLocale('nl');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Formulier te snel verzonden.');
});

test('nl: honeypot_label', function () {
    app()->setLocale('nl');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (laat leeg)');
});

// ---------------------------------------------------------------------------
// German (de)
// ---------------------------------------------------------------------------

test('de: spam_detected', function () {
    app()->setLocale('de');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam erkannt.');
});

test('de: submitted_too_quickly', function () {
    app()->setLocale('de');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Form zu schnell abgeschickt.');
});

test('de: honeypot_label', function () {
    app()->setLocale('de');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (frei lassen)');
});

// ---------------------------------------------------------------------------
// Fallback: unknown locale falls back to English
// ---------------------------------------------------------------------------

test('unknown locale falls back to English for spam_detected', function () {
    app()->setLocale('xx');
    app()->setFallbackLocale('en');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detected.');
});
