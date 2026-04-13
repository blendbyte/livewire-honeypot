<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// HasHoneypot trait dispatches HoneypotDetected event
// ---------------------------------------------------------------------------

test('HasHoneypot dispatches HoneypotDetected when honeypot field is filled', function () {
    Event::fake();

    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(EventTestComponent::class);
    $component->set($fieldName, 'spam');
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submit');

    Event::assertDispatched(HoneypotDetected::class, function (HoneypotDetected $event) {
        return $event->reason === 'honeypot_filled'
            && $event->component === EventTestComponent::class;
    });
});

test('HasHoneypot dispatches HoneypotDetected when submitted too quickly', function () {
    Event::fake();

    $component = Livewire::test(EventTestComponent::class);
    $component->call('submit');

    Event::assertDispatched(HoneypotDetected::class, function (HoneypotDetected $event) {
        return $event->reason === 'submitted_too_quickly'
            && $event->component === EventTestComponent::class;
    });
});

test('HasHoneypot event includes component class name', function () {
    Event::fake();

    $fieldName = config('livewire-honeypot.field_name', 'hp_website');
    $component = Livewire::test(EventTestComponent::class);
    $component->set($fieldName, 'bot');
    $component->set('hp_started_at', now()->subSeconds(10)->getTimestamp());
    $component->call('submit');

    Event::assertDispatched(HoneypotDetected::class, function (HoneypotDetected $event) {
        return $event->component === EventTestComponent::class;
    });
});

test('HasHoneypot does not dispatch event on valid submission', function () {
    Event::fake();

    config(['livewire-honeypot.minimum_fill_seconds' => 0]);

    $component = Livewire::test(EventTestComponent::class);
    $component->call('submit');

    Event::assertNotDispatched(HoneypotDetected::class);
});

// ---------------------------------------------------------------------------
// Test component
// ---------------------------------------------------------------------------

class EventTestComponent extends Component
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
