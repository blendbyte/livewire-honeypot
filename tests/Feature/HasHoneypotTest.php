<?php

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// Initialization
// ---------------------------------------------------------------------------

test('it initializes honeypot fields on mount', function () {
    $component = Livewire::test(TestComponent::class);

    expect($component->hp_website)->toBe('');
    expect($component->hp_started_at)->toBeInt()->toBeGreaterThan(0);
    expect($component->hp_token)->toBeString()->toHaveLength(24);
});

test('it sets hp_started_at to the current timestamp on mount', function () {
    $before = now()->getTimestamp();
    $component = Livewire::test(TestComponent::class);
    $after = now()->getTimestamp();

    expect($component->hp_started_at)
        ->toBeGreaterThanOrEqual($before)
        ->toBeLessThanOrEqual($after);
});

test('it respects token_length config on mount', function () {
    config(['livewire-honeypot.token_length' => 32]);

    $component = Livewire::test(TestComponent::class);

    expect($component->hp_token)->toHaveLength(32);
});

// ---------------------------------------------------------------------------
// validateHoneypot() — happy paths
// ---------------------------------------------------------------------------

test('it validates a valid honeypot submission', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    $component->assertHasNoErrors();
});

test('it passes when minimum_fill_seconds config is zero', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    $component->assertHasNoErrors();
});

// ---------------------------------------------------------------------------
// validateHoneypot() — honeypot bait field
// ---------------------------------------------------------------------------

test('it fails when honeypot field is filled', function () {
    $component = Livewire::test(TestComponent::class);
    $component->set('hp_website', 'https://spam.com');
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submit');

    $component->assertHasErrors(['hp_website' => 'size']);
});

test('spam detection error message is correct', function () {
    $component = Livewire::test(TestComponent::class);
    $component->set('hp_website', 'spam');
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submit');

    $component->assertHasErrors('hp_website');
    expect($component->errors()->first('hp_website'))->toBe('Spam detected.');
});

test('it uses configured field_name for validation', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    $component = Livewire::test(CustomFieldComponent::class);
    $component->set('my_trap', 'https://spam.com');
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submit');

    $component->assertHasErrors('my_trap');
});

// ---------------------------------------------------------------------------
// validateHoneypot() — time-trap
// ---------------------------------------------------------------------------

test('it fails when submitted too quickly', function () {
    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    $component->assertHasErrors('hp_website');
});

test('time-trap error message is correct', function () {
    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    expect($component->errors()->first('hp_website'))->toBe('Form submitted too quickly.');
});

test('it uses configured field_name for time-trap error', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    $component = Livewire::test(CustomFieldComponent::class);
    $component->call('submit');

    $component->assertHasErrors('my_trap');
});

// ---------------------------------------------------------------------------
// validateHoneypot() — token
// ---------------------------------------------------------------------------

test('it fails when hp_token is empty', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(TestComponent::class);
    $component->set('hp_token', '');
    $component->call('submit');

    $component->assertHasErrors('hp_token');
});

test('it fails when hp_token is shorter than token_min_length', function () {
    config([
        'livewire-honeypot.minimum_fill_seconds' => 0,
        'livewire-honeypot.token_min_length'      => 15,
    ]);

    $component = Livewire::test(TestComponent::class);
    $component->set('hp_token', str_repeat('x', 10));
    $component->call('submit');

    $component->assertHasErrors('hp_token');
});

// ---------------------------------------------------------------------------
// resetHoneypot()
// ---------------------------------------------------------------------------

test('it resets honeypot after submission', function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(TestComponent::class);
    $originalToken = $component->hp_token;

    $component->call('submit');

    expect($component->hp_token)->not->toBe($originalToken);
    expect($component->hp_website)->toBe('');
    expect($component->hp_started_at)->toBeGreaterThan(0);
});

test('it generates a new token of the correct length on reset', function () {
    config([
        'livewire-honeypot.minimum_fill_seconds' => 0,
        'livewire-honeypot.token_length'          => 48,
    ]);

    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    expect($component->hp_token)->toHaveLength(48);
});

// ---------------------------------------------------------------------------
// mountHasHoneypot() — custom field_name guard
// ---------------------------------------------------------------------------

test('it throws LogicException when custom field_name property is not declared', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    Livewire::test(TestComponent::class);
    // Livewire wraps mount exceptions in ViewException; the original LogicException message is preserved
})->throws(\Illuminate\View\ViewException::class, 'my_trap');

test('it works with a custom field_name when property is declared', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(CustomFieldComponent::class);

    $component->call('submit');
    $component->assertHasNoErrors();
});

// ---------------------------------------------------------------------------
// Test components
// ---------------------------------------------------------------------------
class TestComponent extends Component
{
    use HasHoneypot;

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<div>Test</div>';
    }
}

// Test component with a custom honeypot field name
class CustomFieldComponent extends Component
{
    use HasHoneypot;

    public string $my_trap = '';

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<div>Test</div>';
    }
}
