<?php

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// HasHoneypot trait — hp_js property
// ---------------------------------------------------------------------------

test('hp_js property is initialized to empty string on mount', function () {
    $component = Livewire::test(JsVerificationComponent::class);

    expect($component->hp_js)->toBe('');
});

test('hp_js is reset to empty string after resetHoneypot', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(JsVerificationComponent::class);
    $component->set('hp_js', 'MTY5MDAwMDAwMA==');
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());

    // Simulate a successful submit that calls resetHoneypot
    config(['livewire-honeypot.require_js_verification' => true]);
    $component->call('submitWithJs');

    expect($component->hp_js)->toBe('');
});

// ---------------------------------------------------------------------------
// validateHoneypot() — JS verification disabled (default)
// ---------------------------------------------------------------------------

test('validation passes with empty hp_js when js verification is disabled', function () {
    config([
        'livewire-honeypot.require_js_verification' => false,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $component = Livewire::test(JsVerificationComponent::class);
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submitWithJs');

    $component->assertHasNoErrors();
});

// ---------------------------------------------------------------------------
// validateHoneypot() — JS verification enabled
// ---------------------------------------------------------------------------

test('validation fails when js verification is enabled and hp_js is empty', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $component = Livewire::test(JsVerificationComponent::class);
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    // hp_js is default '' — JS did not run
    $component->call('submitWithJs');

    $component->assertHasErrors();
});

test('validation passes when js verification is enabled and hp_js is populated', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $component = Livewire::test(JsVerificationComponent::class);
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->set('hp_js', base64_encode((string) time()));
    $component->call('submitWithJs');

    $component->assertHasNoErrors();
});

test('js verification error uses the configured field_name', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $component = Livewire::test(JsVerificationComponent::class);
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submitWithJs');

    $component->assertHasErrors($fieldName);
});

test('js verification error message is correct', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $component = Livewire::test(JsVerificationComponent::class);
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submitWithJs');

    expect($component->errors()->first($fieldName))->toBe('JavaScript verification failed.');
});

// ---------------------------------------------------------------------------
// JS verification + per-component config override
// ---------------------------------------------------------------------------

test('per-component config can enable js verification', function () {
    config([
        'livewire-honeypot.require_js_verification' => false, // global disabled
        'livewire-honeypot.minimum_fill_seconds'    => 0,
    ]);

    $component = Livewire::test(JsVerificationOverrideComponent::class);
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    // hp_js empty — but component-level config enables it
    $component->call('submitWithJs');

    $component->assertHasErrors();
});

// ---------------------------------------------------------------------------
// Blade component renders hp_js field when enabled
// ---------------------------------------------------------------------------

test('blade component renders hp_js input when js verification is enabled', function () {
    config(['livewire-honeypot.require_js_verification' => true]);

    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('name="hp_js"');
});

test('blade component does not render hp_js input when js verification is disabled', function () {
    config(['livewire-honeypot.require_js_verification' => false]);

    $html = Blade::render('<x-honeypot />');

    expect($html)->not->toContain('name="hp_js"');
});

test('blade component hp_js field has x-data and x-init Alpine directives', function () {
    config(['livewire-honeypot.require_js_verification' => true]);

    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('x-data')
        ->toContain('x-init');
});

test('blade component hp_js field sets value via btoa', function () {
    config(['livewire-honeypot.require_js_verification' => true]);

    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('btoa(');
});

test('blade component hp_js field dispatches input event', function () {
    config(['livewire-honeypot.require_js_verification' => true]);

    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain("dispatchEvent(new Event('input'");
});

test('blade component hp_js field has wire:model binding', function () {
    config(['livewire-honeypot.require_js_verification' => true]);

    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('wire:model=hp_js');
});

// ---------------------------------------------------------------------------
// Test components
// ---------------------------------------------------------------------------

class JsVerificationComponent extends Component
{
    use HasHoneypot;

    public function submitWithJs(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<div>Test</div>';
    }
}

class JsVerificationOverrideComponent extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return ['require_js_verification' => true];
    }

    public function submitWithJs(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<div>Test</div>';
    }
}
