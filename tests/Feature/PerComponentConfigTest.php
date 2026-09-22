<?php

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// minimum_fill_seconds override
// ---------------------------------------------------------------------------

test('component-level minimum_fill_seconds overrides global config', function () {
    // Global config is 5s; component sets 1s — a 2s-old timestamp should pass
    $component = Livewire::test(FastFormComponent::class);
    $this->travel(2)->seconds();
    $component->call('submit');

    $component->assertHasNoErrors();
});

test('component-level minimum_fill_seconds is enforced over global config', function () {
    // Global config is 5s; component sets 15s — a 10s-old timestamp should fail
    $component = Livewire::test(SlowFormComponent::class);
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors();
});

// ---------------------------------------------------------------------------
// token_length override
// ---------------------------------------------------------------------------

test('component-level token_length is used on mount', function () {
    $component = Livewire::test(LongTokenComponent::class);

    expect($component->hp_token)->toHaveLength(48);
});

test('component-level token_length is used after reset', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(LongTokenComponent::class);
    $component->call('submit');

    expect($component->hp_token)->toHaveLength(48);
});

// ---------------------------------------------------------------------------
// token_min_length override
// ---------------------------------------------------------------------------

test('component-level token_min_length accepts shorter token', function () {
    config([
        'livewire-honeypot.minimum_fill_seconds' => 0,
        'livewire-honeypot.token_min_length'      => 20,
    ]);

    // Component overrides token_min_length to 5, so a 6-char token passes
    $component = Livewire::test(ShortTokenMinComponent::class);
    expect($component->hp_token)->toHaveLength(6);
    $component->call('submit');

    $component->assertHasNoErrors();
});

// ---------------------------------------------------------------------------
// randomize_field_name override
// ---------------------------------------------------------------------------

test('component-level randomize_field_name generates random hp_field_name', function () {
    config(['livewire-honeypot.randomize_field_name' => false]);

    $a = Livewire::test(RandomizeFieldComponent::class)->hp_field_name;
    $b = Livewire::test(RandomizeFieldComponent::class)->hp_field_name;

    expect($a)->toStartWith('hp_')->toHaveLength(9);
    expect($b)->toStartWith('hp_')->toHaveLength(9);
    expect($a)->not->toBe($b);
});

// ---------------------------------------------------------------------------
// honeypotConfig() returns empty array by default
// ---------------------------------------------------------------------------

test('honeypotConfig returns empty array by default', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    // DefaultConfigComponent uses no overrides; global config applies
    $component = Livewire::test(DefaultConfigComponent::class);
    $component->call('submit');

    $component->assertHasNoErrors();
});

test('falsy component overrides take precedence while null uses current global config', function () {
    config([
        'livewire-honeypot.minimum_fill_seconds' => 30,
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.randomize_field_name' => true,
        'livewire-honeypot.token_length' => 32,
    ]);

    $component = Livewire::test(FalsyConfigComponent::class);
    expect($component->hp_token)->toHaveLength(32);
    $component->assertSet('hp_field_name', 'hp_website')->assertSet('hp_js', '');
    $component->call('submit')->assertHasNoErrors();

    config(['livewire-honeypot.token_length' => 40]);
    $component->call('submit')->assertHasNoErrors();
    expect($component->hp_token)->toHaveLength(40);
    expect(config('livewire-honeypot.minimum_fill_seconds'))->toBe(30);
});

// ---------------------------------------------------------------------------
// Test components
// ---------------------------------------------------------------------------

class DefaultConfigComponent extends Component
{
    use HasHoneypot;

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string { return '<div></div>'; }
}

class FalsyConfigComponent extends DefaultConfigComponent
{
    protected function honeypotConfig(): array
    {
        return [
            'minimum_fill_seconds' => 0,
            'require_js_verification' => false,
            'randomize_field_name' => false,
            'token_length' => null,
        ];
    }
}

class FastFormComponent extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return ['minimum_fill_seconds' => 1];
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string { return '<div></div>'; }
}

class SlowFormComponent extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return ['minimum_fill_seconds' => 15];
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string { return '<div></div>'; }
}

class LongTokenComponent extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return ['token_length' => 48, 'minimum_fill_seconds' => 0];
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string { return '<div></div>'; }
}

class ShortTokenMinComponent extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return ['token_min_length' => 5, 'token_length' => 6];
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string { return '<div></div>'; }
}

class RandomizeFieldComponent extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return ['randomize_field_name' => true];
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string { return '<div></div>'; }
}
