<?php

// ---------------------------------------------------------------------------
// Blade component: <x-honeypot />
// ---------------------------------------------------------------------------

test('it renders without error', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toBeString()->not->toBeEmpty();
});

test('it renders the hp_website text input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('name="hp_website"')
        ->toContain('type="text"');
});

test('it does not render a start time input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->not->toContain('name="hp_started_at"');
});

test('it does not render a token input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->not->toContain('name="hp_token"');
});

test('it binds hp_website with wire:model.lazy by default', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('wire:model.lazy="hp_website"');
});

test('it sets tabindex -1 on the text input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('tabindex="-1"');
});

test('it sets aria-hidden on the wrapper div', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('aria-hidden="true"');
});

test('it injects offscreen CSS styles', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('.hp-field')
        ->toContain('position: absolute')
        ->not->toContain('nonce=');
});

test('it uses the Vite CSP nonce on its hiding stylesheet', function () {
    \Illuminate\Support\Facades\Vite::useCspNonce('vite-nonce');

    $html = Blade::render('<x-honeypot />');

    expect($html)->toMatch('/<style\s+nonce="vite-nonce"\s*>/')
        ->toContain('class="hp-field"')
        ->toContain('.hp-field');
});

test('an explicit CSP nonce takes precedence over the Vite nonce', function () {
    \Illuminate\Support\Facades\Vite::useCspNonce('vite-nonce');

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
    $html = Blade::render('<x-honeypot :field-name="$name" />', ['name' => 'hp_custom123']);

    expect($html)->toContain('name="hp_custom123"');
});

test('wire:model.lazy still targets the static field_name when a custom field-name prop is passed', function () {
    $html = Blade::render('<x-honeypot :field-name="$name" />', ['name' => 'hp_random99']);

    expect($html)->toContain('wire:model.lazy="hp_website"');
});

test('name attribute falls back to config field_name when no prop is passed', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('name="hp_website"');
});
