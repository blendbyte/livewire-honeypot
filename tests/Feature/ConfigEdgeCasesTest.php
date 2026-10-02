<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Event;
use Illuminate\View\ViewException;
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
    $component->assertSeeHtml('wire:model="trap"')
        ->assertDontSeeHtml('wire:model="global_trap"');
    $component->assertSeeHtml('name="'.$component->hp_field_name.'"');

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

test('a null JS marker in the submit request follows the configured rejection path', function (string $responder, string $componentClass) {
    config(['livewire-honeypot.spam_responder' => $responder]);
    EdgeCaseHoneypotComponent::$settings['require_js_verification'] = true;
    $component = Livewire::test($componentClass);
    $component->update([['method' => 'submit', 'params' => []]], ['hp_js' => null]);

    if ($responder === AbortResponder::class) {
        $component->assertStatus(403);
    } elseif ($responder === RedirectResponder::class) {
        $component->assertStatus(200)->assertRedirect(url('/'));
    } else {
        $component->assertStatus(200)->assertHasErrors($componentClass === ExplicitEdgeCaseHoneypotComponent::class ? 'contact.trap' : 'trap')
            ->assertSee('JavaScript verification failed.');
    }
    expect(EdgeCaseHoneypotComponent::$processed)->toBeFalse();
    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'js_verification_failed');
})->with([ValidationExceptionResponder::class, AbortResponder::class, RedirectResponder::class])
    ->with([[EdgeCaseHoneypotComponent::class], [ExplicitEdgeCaseHoneypotComponent::class]]);

test('a null JS marker does not block submissions when verification is disabled', function () {
    Livewire::test(EdgeCaseHoneypotComponent::class)
        ->update([['method' => 'submit', 'params' => []]], ['hp_js' => null])
        ->assertHasNoErrors()->assertSet('hp_js', '');
    expect(EdgeCaseHoneypotComponent::$processed)->toBeTrue();
    Event::assertNotDispatched(HoneypotDetected::class);
});

test('invalid effective token lengths fail during component initialization', function (int $length) {
    EdgeCaseHoneypotComponent::$settings += ['token_length' => $length];
    // Livewire wraps initialization exceptions in a view exception.
    expect(fn () => Livewire::test(EdgeCaseHoneypotComponent::class))
        ->toThrow(ViewException::class, 'token_length');
})->with(['zero' => [0], 'negative' => [-1]]);

test('valid effective token lengths work on mount and reset', function (int $length) {
    // The deprecated token_min_length is ignored, even when it is larger than the length.
    EdgeCaseHoneypotComponent::$settings += ['token_length' => $length, 'token_min_length' => 32];
    $component = Livewire::test(EdgeCaseHoneypotComponent::class);
    expect($component->hp_token)->toHaveLength($length);
    $component->call('submit')->assertHasNoErrors();
    expect($component->hp_token)->toHaveLength($length);
})->with([1, 6, 10]);

test('invalid token settings on reset fail before changing honeypot state', function () {
    $component = Livewire::test(EdgeCaseHoneypotComponent::class)->instance();
    $token = $component->hp_token;
    $component->trap = 'preserve';
    EdgeCaseHoneypotComponent::$settings['token_length'] = 0;

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

    protected function honeypotConfig(): array
    {
        return self::$settings;
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        self::$processed = true;
        $this->resetHoneypot();
    }

    public function clearHoneypot(): void
    {
        $this->resetHoneypot();
    }

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
