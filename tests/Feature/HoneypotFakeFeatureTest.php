<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// HasHoneypot trait: fake mode
// ---------------------------------------------------------------------------

test('validateHoneypot() is bypassed when fake mode is active', function () {
    HoneypotService::fake();

    $component = Livewire::test(FakeTestComponent::class);

    // A filled bait and expired start time would normally fail validation
    $component->set('hp_website', 'bot was here');
    $this->travel(2)->hours();

    $component->call('submit');

    $component->assertHasNoErrors();
});

test('validateHoneypot() fires normally when fake mode is not active', function () {
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $component = Livewire::test(FakeTestComponent::class);
    $component->set($fieldName, 'bot was here');
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors($fieldName);
});

test('validateHoneypot() resumes normal behaviour after resetFake()', function () {
    HoneypotService::fake();
    HoneypotService::resetFake();

    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(FakeTestComponent::class);
    $component->set($fieldName, 'bot was here');
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors($fieldName);
});

test('fake mode allows testing the happy path without manipulating honeypot fields', function () {
    HoneypotService::fake();

    $component = Livewire::test(FakeTestComponent::class);
    $component->call('submit');

    $component->assertSet('submitted', true);
    $component->assertHasNoErrors();
});

// ---------------------------------------------------------------------------
// Test component
// ---------------------------------------------------------------------------

class FakeTestComponent extends Component
{
    use HasHoneypot;

    public bool $submitted = false;

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->submitted = true;
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<div>Test</div>';
    }
}
