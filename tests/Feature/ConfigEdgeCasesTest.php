<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);
    EdgeCaseHoneypotComponent::$settings = ['field_name' => 'trap'];
    EdgeCaseHoneypotComponent::$processed = false;
    Event::fake([HoneypotDetected::class]);
});

test('the default view uses the component field override for binding and errors', function (bool $randomized) {
    EdgeCaseHoneypotComponent::$settings['randomize_field_name'] = $randomized;
    config(['livewire-honeypot.field_name' => 'global_trap']);
    $component = Livewire::test(EdgeCaseHoneypotComponent::class);
    $component->assertSeeHtml('wire:model.lazy="trap"')
        ->assertDontSeeHtml('wire:model.lazy="global_trap"');
    $component->assertSeeHtml('name="' . $component->hp_field_name . '"');

    $component->set('trap', 'spam')->call('submit')
        ->assertHasErrors(['trap' => 'size'])
        ->assertSeeHtml('<p class="hp-error" role="alert">Spam detected.</p>');
    expect(EdgeCaseHoneypotComponent::$processed)->toBeFalse();
    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->fieldName === 'trap');

    $component->set('trap', '')->call('submit')->assertHasNoErrors();
    expect(EdgeCaseHoneypotComponent::$processed)->toBeTrue();
})->with([false, true]);

test('explicit model and HTML name still override the component defaults', function () {
    Livewire::test(ExplicitEdgeCaseHoneypotComponent::class)
        ->assertSeeHtml('wire:model.blur="contact.trap"')
        ->assertSeeHtml('name="custom_html_name"')
        ->set('contact.trap', 'spam')->call('submit')
        ->assertHasErrors('contact.trap')
        ->assertSeeHtml('<p class="hp-error" role="alert">Spam detected.</p>');
});

test('invalid effective token lengths fail during component initialization', function (array $settings) {
    EdgeCaseHoneypotComponent::$settings += $settings;
    // Livewire wraps initialization exceptions in a view exception.
    expect(fn () => Livewire::test(EdgeCaseHoneypotComponent::class))
        ->toThrow(\Illuminate\View\ViewException::class, 'token_length');
})->with([
    'length below global minimum' => [['token_length' => 5]],
    'minimum above global length' => [['token_min_length' => 32]],
    'both overridden' => [['token_length' => 5, 'token_min_length' => 6]],
    'zero lengths' => [['token_length' => 0, 'token_min_length' => 0]],
    'negative lengths' => [['token_length' => -1, 'token_min_length' => -1]],
]);

test('valid effective token lengths work on mount and reset', function (int $length, int $minimum) {
    EdgeCaseHoneypotComponent::$settings += ['token_length' => $length, 'token_min_length' => $minimum];
    $component = Livewire::test(EdgeCaseHoneypotComponent::class);
    expect($component->hp_token)->toHaveLength($length);
    $component->call('submit')->assertHasNoErrors();
    expect($component->hp_token)->toHaveLength($length);
})->with([[1, 1], [6, 5], [10, 10]]);

test('invalid token settings on reset fail before changing honeypot state', function () {
    $component = Livewire::test(EdgeCaseHoneypotComponent::class)->instance();
    $token = $component->hp_token;
    $component->trap = 'preserve';
    EdgeCaseHoneypotComponent::$settings['token_length'] = 5;

    expect(fn () => $component->clearHoneypot())->toThrow(InvalidArgumentException::class, 'token_length');
    expect($component->hp_token)->toBe($token);
    expect($component->trap)->toBe('preserve');
});

class EdgeCaseHoneypotComponent extends Component
{
    use HasHoneypot;

    public static array $settings = [];
    public static bool $processed = false;
    public string $trap = '';
    public array $contact = ['trap' => ''];

    protected function honeypotConfig(): array { return self::$settings; }

    public function submit(): void
    {
        $this->validateHoneypot();
        self::$processed = true;
        $this->resetHoneypot();
    }

    public function clearHoneypot(): void { $this->resetHoneypot(); }

    public function render(): string
    {
        return self::$settings['randomize_field_name'] ?? false
            ? '<div><x-honeypot :field-name="$hp_field_name" /></div>'
            : '<div><x-honeypot /></div>';
    }
}

class ExplicitEdgeCaseHoneypotComponent extends EdgeCaseHoneypotComponent
{
    public function submit(): void
    {
        $this->validateHoneypotForModel('contact.trap');
        self::$processed = true;
        $this->resetHoneypotForModel('contact.trap');
    }

    public function render(): string
    {
        return '<div><x-honeypot wire:model.blur="contact.trap" field-name="custom_html_name" /></div>';
    }
}
