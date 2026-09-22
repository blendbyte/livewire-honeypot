<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Form;
use Livewire\Livewire;

test('an uninitialized honeypot on a form object reports trait misuse', function (bool $customModel) {
    Event::fake([HoneypotDetected::class]);
    $component = Livewire::test(MisusedHoneypotFormComponent::class);
    $component->assertSet('form.hp_started_at', 0);

    expect(fn () => $component->call('submit', $customModel))->toThrow(
        LogicException::class,
        'Use the HasHoneypot trait on the Livewire component, not on a form object.',
    );

    Event::assertNotDispatched(HoneypotDetected::class);
})->with([false, true]);

test('fake mode continues to bypass form object validation', function (bool $customModel) {
    HoneypotService::fake();

    Livewire::test(MisusedHoneypotFormComponent::class)
        ->call('submit', $customModel)
        ->assertHasNoErrors()
        ->assertSet('submitted', true);
})->with([false, true]);

test('missing metadata on a component still produces validation errors', function (bool $customModel) {
    Event::fake([HoneypotDetected::class]);

    Livewire::test(MissingMetadataHoneypotComponent::class)
        ->call('submit', $customModel)
        ->assertHasErrors(['hp_started_at' => 'min', 'hp_token' => 'required'])
        ->assertSet('submitted', false);

    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'invalid_form_data');
})->with([false, true]);

class MisusedHoneypotForm extends Form
{
    use HasHoneypot;

    public string $trap = '';

    public function submit(bool $customModel): void
    {
        if ($customModel) {
            $this->validateHoneypotForModel('trap');
        } else {
            $this->validateHoneypot();
        }
    }
}

class MisusedHoneypotFormComponent extends Component
{
    public MisusedHoneypotForm $form;
    public bool $submitted = false;

    public function submit(bool $customModel): void
    {
        $this->form->submit($customModel);
        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class MissingMetadataHoneypotComponent extends Component
{
    use HasHoneypot;

    public string $trap = '';
    public bool $submitted = false;

    public function submit(bool $customModel): void
    {
        // Simulate missing metadata from a page opened before the trait was added.
        $this->hp_started_at = 0;
        $this->hp_token = '';

        if ($customModel) {
            $this->validateHoneypotForModel('trap');
        } else {
            $this->validateHoneypot();
        }

        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div><x-honeypot /></div>';
    }
}
