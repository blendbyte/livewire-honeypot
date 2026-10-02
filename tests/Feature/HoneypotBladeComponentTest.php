<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Facades\Vite;

// ---------------------------------------------------------------------------
// Blade component: <x-honeypot />
// ---------------------------------------------------------------------------

test('it renders without error', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toBeString()->not->toBeEmpty();
});

test('it renders the bait text input', function () {
    config(['livewire-honeypot.randomize_field_name' => false]);
    $html = renderInHoneypotComponent('<x-honeypot />');

    expect($html)->toContain('name="hp_website"')
        ->toContain('type="text"');
});

test('it does not render a start time input', function () {
    expect(renderInHoneypotComponent('<x-honeypot />'))->not->toContain('name="hp_started_at"');
    expect(Blade::render('<x-honeypot />'))->not->toContain('name="hp_started_at"');
});

test('it does not render a token input in a Livewire component', function () {
    $html = renderInHoneypotComponent('<x-honeypot />');

    expect($html)->not->toContain('name="hp_token"');
});

test('it binds hp_website with a deferred wire:model by default', function () {
    $html = renderInHoneypotComponent('<x-honeypot />');

    expect($html)->toContain('wire:model="hp_website"');
});

test('it sets tabindex -1 on the text input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('tabindex="-1"');
});

test('it sets aria-hidden on the wrapper div', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('aria-hidden="true"');
});

test('it hides the wrapper with screen-reader-only CSS under an inconspicuous class', function () {
    $class = app(HoneypotService::class)->wrapperClass();
    $html = Blade::render('<x-honeypot />');

    expect($class)->toMatch('/^f[0-9a-f]{8}$/');
    expect($html)->toContain('<div class="'.$class.'" aria-hidden="true">')
        ->toContain('.'.$class.' {')
        ->toContain('clip-path: inset(50%)')
        ->not->toContain('hp-field')
        ->not->toContain('-10000px')
        ->not->toContain('nonce=');
});

test('the wrapper class is stable per app key', function () {
    $service = app(HoneypotService::class);
    $class = $service->wrapperClass();

    expect($service->wrapperClass())->toBe($class);

    config(['app.key' => 'another-application-key']);
    expect($service->wrapperClass())->not->toBe($class);
});

test('it uses the Vite CSP nonce on its hiding stylesheet', function () {
    Vite::useCspNonce('vite-nonce');

    $html = Blade::render('<x-honeypot />');

    $class = app(HoneypotService::class)->wrapperClass();

    expect($html)->toMatch('/<style\s+nonce="vite-nonce"\s*>/')
        ->toContain('class="'.$class.'"')
        ->toContain('.'.$class.' {');
});

test('an explicit CSP nonce takes precedence over the Vite nonce', function () {
    Vite::useCspNonce('vite-nonce');

    $html = Blade::render('<x-honeypot :nonce="$nonce" />', ['nonce' => 'explicit-nonce']);

    expect($html)->toMatch('/<style\s+nonce="explicit-nonce"\s*>/')
        ->not->toContain('vite-nonce');
});

test('CSP nonces are escaped as attribute values', function () {
    $html = Blade::render('<x-honeypot :nonce="$nonce" />', ['nonce' => '"><script>alert(1)</script>']);

    expect($html)->toContain('nonce="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->not->toContain('<script>');
});

test('it renders the honeypot_label translation in the label span', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('Leave this field empty');
});

test('it sets autocomplete off on the text input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('autocomplete="off"');
});

test('it accepts a custom field-name prop for the name attribute', function () {
    $html = renderInHoneypotComponent('<x-honeypot :field-name="$name" />', ['name' => 'hp_custom123']);

    expect($html)->toContain('name="hp_custom123"');
});

test('wire:model still targets the static field_name when a custom field-name prop is passed', function () {
    $html = renderInHoneypotComponent('<x-honeypot :field-name="$name" />', ['name' => 'hp_random99']);

    expect($html)->toContain('wire:model="hp_website"');
});

test('name attribute falls back to config field_name when randomization is disabled', function () {
    config(['livewire-honeypot.randomize_field_name' => false]);
    $html = renderInHoneypotComponent('<x-honeypot />');

    expect($html)->toContain('name="hp_website"');
});
