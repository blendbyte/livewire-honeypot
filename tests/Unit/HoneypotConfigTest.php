<?php

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\HoneypotConfig;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Facades\Blade;

test('missing package settings retain the service and view defaults', function () {
    config(['livewire-honeypot' => []]);
    $this->freezeTime();
    $service = new HoneypotService();
    $data = $service->generate();

    expect($data)->toHaveKey('hp_website', '');
    expect(explode('.', $data['hp_token'])[0])->toHaveLength(24);
    expect(app(SpamResponder::class))->toBeInstanceOf(ValidationExceptionResponder::class);
    expect(Blade::render('<x-honeypot />'))
        ->toContain('wire:model.lazy="hp_website"')
        ->not->toContain('name="hp_js"');

    $this->travel(5)->seconds();
    $service->validate($data);
});

test('the same service and view pick up runtime configuration changes', function () {
    $service = new HoneypotService();
    $first = $service->generate();
    expect($first)->toHaveKey('hp_website');
    expect(Blade::render('<x-honeypot />'))->toContain('name="hp_website"');

    config([
        'livewire-honeypot.field_name' => 'trap',
        'livewire-honeypot.token_length' => 32,
        'livewire-honeypot.minimum_fill_seconds' => 0,
        'livewire-honeypot.require_js_verification' => true,
    ]);
    $data = $service->generate();

    expect($data)->toHaveKey('trap', '')->not->toHaveKey('hp_website');
    expect(explode('.', $data['hp_token'])[0])->toHaveLength(32);
    expect(Blade::render('<x-honeypot />'))
        ->toContain('wire:model.lazy="trap"')
        ->toContain('name="hp_js"');

    $service->validate([...$data, 'hp_js' => '1']);
});

test('missing nested settings fall back without replacing explicit null or falsy values', function () {
    config(['livewire-honeypot.logging' => ['enabled' => true]]);

    expect(HoneypotConfig::get('logging.level'))->toBe('warning');
    expect(HoneypotConfig::get('logging.channel'))->toBeNull();

    config([
        'livewire-honeypot.logging.level' => null,
        'livewire-honeypot.require_js_verification' => false,
        'livewire-honeypot.minimum_fill_seconds' => 0,
    ]);

    expect(HoneypotConfig::get('logging.level'))->toBeNull();
    expect(HoneypotConfig::get('require_js_verification'))->toBeFalse();
    expect(HoneypotConfig::get('minimum_fill_seconds'))->toBe(0);
});
