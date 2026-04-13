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

test('it renders the hp_started_at hidden input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('name="hp_started_at"');
});

test('it renders the hp_token hidden input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('name="hp_token"');
});

test('it binds hp_website with wire:model.lazy by default', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('wire:model.lazy=hp_website');
});

test('it binds hp_started_at with wire:model by default', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('wire:model=hp_started_at');
});

test('it binds hp_token with wire:model by default', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('wire:model=hp_token');
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
        ->toContain('position: absolute');
});

test('it renders the honeypot_label translation in the label span', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('Website (leave empty)');
});

test('it sets autocomplete off on the text input', function () {
    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('autocomplete="off"');
});
