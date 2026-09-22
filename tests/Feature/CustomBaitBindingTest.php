<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Form;
use Livewire\Livewire;

beforeEach(fn () => $this->freezeTime());

test('the Blade component preserves custom binding modifiers', function (string $directive) {
    $html = Blade::render('<x-honeypot ' . $directive . '="contact.trap" />');

    expect($html)->toContain($directive . '="contact.trap"')
        ->not->toContain('wire:model.lazy="hp_website"');
})->with(['wire:model', 'wire:model.blur', 'wire:model.live.debounce.500ms']);

test('a randomized HTML name does not replace a custom model binding', function () {
    $html = Blade::render('<x-honeypot field-name="hp_random" wire:model="contact.trap" />');

    expect($html)->toContain('name="hp_random"')
        ->toContain('wire:model="contact.trap"');
});

test('binding values are escaped as HTML attributes', function () {
    $html = Blade::render('<x-honeypot :wire:model="$model" />', [
        'model' => 'trap" onfocus="alert(1)',
    ]);

    expect($html)->toContain('wire:model="trap&quot; onfocus=&quot;alert(1)"')
        ->not->toContain(' onfocus="alert(1)');
});

test('a custom bait binding keeps the JS verification input bound independently', function () {
    config(['livewire-honeypot.require_js_verification' => true]);
    $html = Blade::render('<x-honeypot wire:model.blur="contact.trap" />');

    expect($html)->toContain('wire:model.blur="contact.trap"')
        ->toContain('wire:model="hp_js"');
});

test('a filled custom bait is rejected while the default property is empty', function (string $class, string $model) {
    Event::fake([HoneypotDetected::class]);
    $component = Livewire::test($class);
    $component->assertSeeHtml('wire:model="' . $model . '"');
    $this->travel(5)->seconds();

    $component->set($model, 'spam')->call('submit')
        ->assertHasErrors([$model => 'size'])
        ->assertSet('submitted', false)
        ->assertSet('hp_website', '');

    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->fieldName === $model && $event->reason === 'honeypot_filled'
    );
})->with([
    [CustomBaitComponent::class, 'trap'],
    [NestedBaitComponent::class, 'contact.trap'],
    [FormObjectBaitComponent::class, 'form.trap'],
]);

test('an empty custom bait can submit after the fill time', function (string $class) {
    $component = Livewire::test($class);
    $this->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
})->with([CustomBaitComponent::class, NestedBaitComponent::class, FormObjectBaitComponent::class]);

test('a missing nested bait is rejected', function () {
    $component = Livewire::test(NestedBaitComponent::class);
    $this->travel(5)->seconds();

    $component->set('contact', [])->call('submit')
        ->assertHasErrors(['contact.trap' => 'present'])
        ->assertSet('submitted', false);
});

test('time-trap errors and events use the custom property path', function () {
    Event::fake([HoneypotDetected::class]);

    Livewire::test(NestedBaitComponent::class)->call('submit')
        ->assertHasErrors('contact.trap')->assertSet('submitted', false);

    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->fieldName === 'contact.trap' && $event->reason === 'submitted_too_quickly'
    );
});

test('a custom model can be combined with a positional minimum fill time', function () {
    $component = Livewire::test(NestedBaitComponent::class);
    $this->travel(2)->seconds();

    $component->call('submit', 1)->assertHasNoErrors()->assertSet('submitted', true);
});

test('JS verification still runs with a custom bait binding', function () {
    config(['livewire-honeypot.require_js_verification' => true]);
    Event::fake([HoneypotDetected::class]);
    $component = Livewire::test(NestedBaitComponent::class);
    $this->travel(5)->seconds();

    $component->call('submit')->assertHasErrors('contact.trap')->assertSet('submitted', false);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->fieldName === 'contact.trap' && $event->reason === 'js_verification_failed'
    );

    $component->set('hp_js', 'browser-value')->call('submit')
        ->assertHasNoErrors()->assertSet('submitted', true);
});

test('reset clears a custom bait without resetting sibling fields', function (string $class, string $model) {
    $component = Livewire::test($class);
    $token = $component->hp_token;
    $component->set($model, 'spam');
    $this->travel(5)->seconds();

    $component->call('clearHoneypot')->assertSet($model, '');
    expect($component->hp_token)->not->toBe($token);
    expect($component->hp_started_at)->toBe(now()->getTimestamp());
    $component->assertSet('contact.name', 'Alice')->assertSet('form.name', '');

    $component->call('submit')->assertHasErrors($model);
    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors();
})->with([
    [CustomBaitComponent::class, 'trap'],
    [NestedBaitComponent::class, 'contact.trap'],
    [FormObjectBaitComponent::class, 'form.trap'],
]);

test('per-component field_name still supplies the default validation target', function () {
    $component = Livewire::test(ConfiguredBaitComponent::class);
    $this->travel(5)->seconds();

    $component->set('trap', 'spam')->call('submit')->assertHasErrors('trap');
    $component->call('clearHoneypot')->assertSet('trap', '');
    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors();
});

class BaitContactForm extends Form
{
    public string $trap = '';
    public string $name = '';

    protected function rules(): array
    {
        return ['name' => 'required'];
    }
}

class CustomBaitComponent extends Component
{
    use HasHoneypot;

    public string $trap = '';
    public array $contact = ['trap' => '', 'name' => 'Alice'];
    public BaitContactForm $form;
    public bool $submitted = false;

    protected function baitModel(): string { return 'trap'; }

    public function submit(?int $minimumSeconds = null): void
    {
        $this->validateHoneypotForModel($this->baitModel(), $minimumSeconds);
        $this->submitted = true;
        $this->resetHoneypotForModel($this->baitModel());
    }

    public function clearHoneypot(): void
    {
        $this->resetHoneypotForModel($this->baitModel());
    }

    public function render(): string
    {
        return '<div><x-honeypot wire:model="' . $this->baitModel() . '" /></div>';
    }
}

class NestedBaitComponent extends CustomBaitComponent
{
    protected function baitModel(): string { return 'contact.trap'; }
}

class FormObjectBaitComponent extends CustomBaitComponent
{
    protected function baitModel(): string { return 'form.trap'; }
}

class ConfiguredBaitComponent extends CustomBaitComponent
{
    protected function honeypotConfig(): array { return ['field_name' => 'trap']; }

    public function submit(?int $minimumSeconds = null): void
    {
        $this->validateHoneypot($minimumSeconds);
        $this->submitted = true;
        $this->resetHoneypot();
    }
}
