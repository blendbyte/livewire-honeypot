<?php

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// Default (ValidationExceptionResponder) — existing behaviour preserved
// ---------------------------------------------------------------------------

test('default responder shows validation error on honeypot fill', function () {
    $fieldName = config('livewire-honeypot.field_name', 'hp_website');

    $component = Livewire::test(ResponderTestComponent::class);
    $component->set($fieldName, 'spam');
    $this->travel(10)->seconds();
    $component->call('submit');

    $component->assertHasErrors($fieldName);
});

// ---------------------------------------------------------------------------
// AbortResponder
// ---------------------------------------------------------------------------

test('AbortResponder returns 403 when submitted too quickly via trait', function () {
    app()->bind(SpamResponder::class, fn () => new AbortResponder());

    // Trigger the time-trap: hp_started_at just set, so elapsed < minimum_fill_seconds
    $component = Livewire::test(ResponderTestComponent::class);
    $component->call('submit');

    $component->assertStatus(403);
});

// ---------------------------------------------------------------------------
// RedirectResponder
// ---------------------------------------------------------------------------

test('RedirectResponder returns a redirect response when submitted too quickly via trait', function () {
    app()->bind(SpamResponder::class, fn () => new RedirectResponder());

    $component = Livewire::test(ResponderTestComponent::class);
    $component->call('submit');

    $component->assertStatus(200)->assertRedirect(url('/'));
});

// ---------------------------------------------------------------------------
// Test component
// ---------------------------------------------------------------------------

class ResponderTestComponent extends Component
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
