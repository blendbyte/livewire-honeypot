<?php

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(TestComponent::class);
    $component->set($fieldName, 'https://spam.com');
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors([$fieldName => 'size']);
});

test('spam detection error message is correct', function () {
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(TestComponent::class);
    $component->set($fieldName, 'spam');
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors($fieldName);
    expect($component->errors()->first($fieldName))->toBe('Spam detected.');
});

test('it uses configured field_name for validation', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    $component = Livewire::test(CustomFieldComponent::class);
    $component->set('my_trap', 'https://spam.com');
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors('my_trap');
});

// ---------------------------------------------------------------------------
// validateHoneypot() — time-trap
// ---------------------------------------------------------------------------

test('it fails when submitted too quickly', function () {
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    $component->assertHasErrors($fieldName);
});

test('time-trap error message is correct', function () {
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(TestComponent::class);
    $component->call('submit');

    expect($component->errors()->first($fieldName))->toBe('Form submitted too quickly.');
});

test('it uses configured field_name for time-trap error', function () {
    config(['livewire-honeypot.field_name' => 'my_trap']);

    $component = Livewire::test(CustomFieldComponent::class);
    $component->call('submit');

    $component->assertHasErrors('my_trap');
});

test('it respects a custom minimum seconds parameter', function () {
    $component = Livewire::test(TestComponent::class);

    // Two seconds have elapsed, which would fail the default 5s check
    $this->travel(2)->seconds();

    // But passes when we override minimum to 1 second
    config(['livewire-honeypot.minimum_fill_seconds' => 99]); // ensure config is NOT used
    $component->call('submitWithMinimum', 1);

    $component->assertHasNoErrors();
});

test('it still fails when custom minimum seconds is not met', function () {
    $component = Livewire::test(TestComponent::class);

    $this->travel(2)->seconds();

    $component->call('submitWithMinimum', 10); // require 10s but only 2s elapsed

    $component->assertHasErrors();
});

// ---------------------------------------------------------------------------
// validateHoneypot() — token
// ---------------------------------------------------------------------------

test('it rejects client changes to the form token', function (string $token) {
    Livewire::test(TestComponent::class)->set('hp_token', $token);
})->with(['', 'short', str_repeat('x', 24)])
    ->throws(CannotUpdateLockedPropertyException::class, 'hp_token');

test('it rejects a client backdating the start time to bypass the time trap', function () {
    $this->freezeTime();

    Livewire::test(TestComponent::class)
        ->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
})->throws(CannotUpdateLockedPropertyException::class, 'hp_started_at');

test('it enforces the time trap across submissions and server-side resets', function () {
    $this->freezeTime();
    $component = Livewire::test(TestComponent::class);
    $originalToken = $component->hp_token;

    $this->travel(4)->seconds();
    $component->call('submit')->assertHasErrors('hp_website');
    expect($component->hp_token)->toBe($originalToken);

    $this->travel(1)->seconds();
    $component->call('submit')->assertHasNoErrors();
    expect($component->hp_started_at)->toBe(now()->getTimestamp());
    expect($component->hp_token)->not->toBe($originalToken);

    $component->call('submit')->assertHasErrors('hp_website');
    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors();
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
// Field name randomization
// ---------------------------------------------------------------------------

test('hp_field_name defaults to the configured field_name when randomization is disabled', function () {
    config(['livewire-honeypot.randomize_field_name' => false]);

    $component = Livewire::test(TestComponent::class);

    expect($component->hp_field_name)->toBe('hp_website');
});

test('hp_field_name is set on mount', function () {
    $component = Livewire::test(TestComponent::class);

    expect($component->hp_field_name)->toBeString()->not->toBeEmpty();
});

test('hp_field_name is randomized when randomize_field_name config is enabled', function () {
    config(['livewire-honeypot.randomize_field_name' => true]);

    $a = Livewire::test(TestComponent::class)->hp_field_name;
    $b = Livewire::test(TestComponent::class)->hp_field_name;

    // Both should start with 'hp_' and be 9 chars (hp_ + 6 random chars)
    expect($a)->toStartWith('hp_')->toHaveLength(9);
    expect($b)->toStartWith('hp_')->toHaveLength(9);
    // Statistically near-impossible for two random names to match
    expect($a)->not->toBe($b);
});

test('hp_field_name is refreshed on resetHoneypot when randomization is enabled', function () {
    config([
        'livewire-honeypot.randomize_field_name' => true,
        'livewire-honeypot.minimum_fill_seconds' => 0,
    ]);

    $component = Livewire::test(TestComponent::class);
    $original = $component->hp_field_name;

    $component->call('submit');

    expect($component->hp_field_name)->not->toBe($original);
});

test('validation still passes when randomization is enabled', function () {
    config([
        'livewire-honeypot.randomize_field_name' => true,
        'livewire-honeypot.minimum_fill_seconds' => 0,
    ]);

    $component = Livewire::test(TestComponent::class);
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

    public function submitWithMinimum(int $minimumSeconds): void
    {
        $this->validateHoneypot($minimumSeconds);
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<div><x-honeypot /></div>';
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
        return '<div><x-honeypot /></div>';
    }
}
