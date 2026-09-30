<?php

use Blendbyte\LivewireHoneypot\CaughtTokens;
use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Form;
use Livewire\Livewire;

beforeEach(function () {
    config(['cache.default' => 'array']);
});

// ---------------------------------------------------------------------------
// Livewire trait
// ---------------------------------------------------------------------------

test('a clean submission is not caught and the real work runs', function () {
    Event::fake([HoneypotDetected::class]);

    $component = Livewire::test(SilentRejectionComponent::class);
    $this->travel(5)->seconds();
    $component->call('submit')
        ->assertHasNoErrors()
        ->assertSet('success', true)
        ->assertSet('submissions', 1);

    Event::assertNotDispatched(HoneypotDetected::class);
});

test('a caught submission looks successful without errors or real work', function (Closure $arrange, string $reason) {
    Event::fake([HoneypotDetected::class]);

    $component = Livewire::test(SilentRejectionComponent::class);
    $scenario = $arrange($component);
    $component->call('submit', $scenario)
        ->assertHasNoErrors()
        ->assertStatus(200)
        ->assertSet('success', true)
        ->assertSet('submissions', 0);

    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->reason === $reason
        && $event->fieldName === 'hp_website'
        && $event->component === SilentRejectionComponent::class
    );
})->with([
    'bait filled' => [function ($component) {
        $component->set('hp_website', 'spam');
        test()->travel(10)->seconds();
    }, 'honeypot_filled'],
    'too quick' => [fn () => null, 'submitted_too_quickly'],
    'future start time' => [function () {
        test()->travel(10)->seconds();

        return 'future';
    }, 'invalid_form_data'],
    'short token' => [function () {
        test()->travel(10)->seconds();

        return 'token';
    }, 'invalid_form_data'],
    'JS marker missing' => [function () {
        config(['livewire-honeypot.require_js_verification' => true]);
        test()->travel(10)->seconds();
    }, 'js_verification_failed'],
]);

test('a caught token stays caught after waiting and resubmitting a clean form', function () {
    Event::fake([HoneypotDetected::class]);

    $component = Livewire::test(SilentRejectionComponent::class);
    $component->call('submit')->assertSet('success', true)->assertSet('submissions', 0);

    $this->travel(5)->seconds();
    $component->set('success', false)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('success', true)
        ->assertSet('submissions', 0);

    $reasons = Event::dispatched(HoneypotDetected::class)->map(fn ($args) => $args[0]->reason)->all();
    expect($reasons)->toBe(['submitted_too_quickly', 'previously_detected']);
    expect(Cache::has(CaughtTokens::key($component->get('hp_token'))))->toBeTrue();
});

test('a caught token does not affect another form instance', function () {
    $caught = Livewire::test(SilentRejectionComponent::class);
    $caught->call('submit')->assertSet('submissions', 0);

    $fresh = Livewire::test(SilentRejectionComponent::class);
    $this->travel(5)->seconds();

    expect($fresh->get('hp_token'))->not->toBe($caught->get('hp_token'));
    $fresh->call('submit')->assertSet('submissions', 1);
    $caught->call('submit')->assertSet('submissions', 0);
});

test('an expired form shows the normal error instead of a fake success', function (string $responder) {
    Event::fake([HoneypotDetected::class]);
    app()->bind(SpamResponder::class, fn () => new $responder());

    $component = Livewire::test(SilentRejectionComponent::class);
    $this->travel(61)->minutes();
    $component->call('submit')
        ->assertStatus(200)
        ->assertHasErrors(['hp_started_at' => 'min'])
        ->assertSet('success', false)
        ->assertSet('submissions', 0);

    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'invalid_form_data');
    expect(Cache::has(CaughtTokens::key($component->get('hp_token'))))->toBeFalse();

    // Reloading the page gives the visitor a working form.
    $reloaded = Livewire::test(SilentRejectionComponent::class);
    $this->travel(5)->seconds();
    $reloaded->call('submit')->assertHasNoErrors()->assertSet('submissions', 1);
})->with([AbortResponder::class, ThrowingSilentResponder::class]);

test('an expired form with other problems is still caught silently', function () {
    $component = Livewire::test(SilentRejectionComponent::class)->set('hp_website', 'spam');
    $this->travel(61)->minutes();

    $component->call('submit')->assertHasNoErrors()->assertSet('success', true)->assertSet('submissions', 0);
});

test('a token caught before its form expired stays caught afterwards', function () {
    Event::fake([HoneypotDetected::class]);

    $component = Livewire::test(SilentRejectionComponent::class)->set('hp_website', 'spam');
    $this->travel(59)->minutes();
    $component->call('submit')->assertSet('submissions', 0);

    $this->travel(2)->minutes();
    $component->set('hp_website', '')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('success', true)
        ->assertSet('submissions', 0);

    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'previously_detected');
});

test('a clean check clears earlier honeypot errors', function () {
    $component = Livewire::test(SilentRejectionComponent::class);
    $this->travel(5)->seconds();

    $component->call('addHoneypotError')
        ->assertHasErrors('hp_website')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submissions', 1);
});

test('configured responders are never called by the silent API', function (string $responder) {
    app()->bind(SpamResponder::class, fn () => new $responder());

    $component = Livewire::test(SilentRejectionComponent::class);
    $component->set('hp_website', 'spam')
        ->call('submit')
        ->assertStatus(200)
        ->assertNoRedirect()
        ->assertHasNoErrors()
        ->assertSet('success', true)
        ->assertSet('submissions', 0);
})->with([AbortResponder::class, RedirectResponder::class, ThrowingSilentResponder::class]);

test('fake mode reports submissions as clean', function () {
    Event::fake([HoneypotDetected::class]);
    HoneypotService::fake();

    Livewire::test(SilentRejectionComponent::class)
        ->set('hp_website', 'spam')
        ->call('submit')
        ->assertSet('submissions', 1);

    Event::assertNotDispatched(HoneypotDetected::class);
});

test('custom bait bindings can be checked silently', function () {
    Event::fake([HoneypotDetected::class]);

    $clean = Livewire::test(SilentRejectionCustomBaitComponent::class);
    $this->travel(5)->seconds();
    $clean->call('submit')->assertSet('submissions', 1);

    $caught = Livewire::test(SilentRejectionCustomBaitComponent::class)->set('contact.trap', 'spam');
    $this->travel(5)->seconds();
    $caught->call('submit')
        ->assertHasNoErrors()
        ->assertSet('success', true)
        ->assertSet('submissions', 0);

    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->reason === 'honeypot_filled' && $event->fieldName === 'contact.trap'
    );
});

test('caught tokens are stored in the configured cache store', function () {
    config([
        'cache.stores.honeypot-caught' => ['driver' => 'array'],
        'livewire-honeypot.caught_cache_store' => 'honeypot-caught',
    ]);

    $component = Livewire::test(SilentRejectionComponent::class);
    $component->call('submit')->assertSet('submissions', 0);
    $key = CaughtTokens::key($component->get('hp_token'));

    expect(Cache::store('honeypot-caught')->has($key))->toBeTrue();
    expect(Cache::store('array')->has($key))->toBeFalse();
});

test('an unavailable cache store is reported and forms keep working', function () {
    Exceptions::fake();
    config(['livewire-honeypot.caught_cache_store' => 'missing-store']);

    $caught = Livewire::test(SilentRejectionComponent::class);
    $caught->call('submit')->assertStatus(200)->assertSet('submissions', 0);

    // One report per submission: the failed lookup skips remembering the token.
    Exceptions::assertReportedCount(1);

    $clean = Livewire::test(SilentRejectionComponent::class);
    $this->travel(5)->seconds();
    $clean->call('submit')->assertStatus(200)->assertSet('submissions', 1);

    Exceptions::assertReported(InvalidArgumentException::class);
});

test('missing or short tokens are never remembered', function () {
    Event::fake([HoneypotDetected::class]);

    $component = Livewire::test(SilentRejectionComponent::class);
    $this->travel(5)->seconds();
    $component->call('submit', 'metadata')->assertSet('submissions', 0);
    $component->call('submit', 'metadata')->assertSet('submissions', 0);

    $reasons = Event::dispatched(HoneypotDetected::class)->map(fn ($args) => $args[0]->reason)->unique()->all();
    expect($reasons)->toBe(['invalid_form_data']);
    expect(Cache::has(CaughtTokens::key('')))->toBeFalse();
});

test('only tokens with enough random characters are remembered', function (int $length, bool $remembered) {
    config(['livewire-honeypot.token_length' => $length, 'livewire-honeypot.token_min_length' => $length]);

    $component = Livewire::test(SilentRejectionComponent::class);
    $component->call('submit')->assertSet('success', true)->assertSet('submissions', 0);

    expect(Cache::has(CaughtTokens::key($component->get('hp_token'))))->toBe($remembered);
})->with([
    'too short' => [CaughtTokens::MIN_REMEMBERED_LENGTH - 1, false],
    'minimum' => [CaughtTokens::MIN_REMEMBERED_LENGTH, true],
]);

test('the silent API still reports trait misuse on a form object', function () {
    Event::fake([HoneypotDetected::class]);

    $component = Livewire::test(SilentRejectionMisusedFormComponent::class);

    expect(fn () => $component->call('submit'))->toThrow(
        LogicException::class,
        'Use the HasHoneypot trait on the Livewire component, not on a form object.',
    );
    Event::assertNotDispatched(HoneypotDetected::class);
});

// ---------------------------------------------------------------------------
// Plain forms
// ---------------------------------------------------------------------------

describe('plain forms', function () {
    beforeEach(function () {
        $this->freezeTime();
        Route::middleware('web')->post('/silent-contact', function (Request $request, HoneypotService $honeypot) {
            return response()->json(['caught' => $honeypot->isCaught($request->all())]);
        });
    });

    test('a clean submission is not caught', function () {
        $data = app(HoneypotService::class)->generate();
        $this->travel(5)->seconds();

        $this->postJson('/silent-contact', $data)->assertOk()->assertJson(['caught' => false]);
    });

    test('a caught signed token stays caught after waiting', function () {
        Event::fake([HoneypotDetected::class]);
        $data = app(HoneypotService::class)->generate();

        $this->postJson('/silent-contact', $data)->assertOk()->assertJson(['caught' => true]);
        $this->travel(5)->seconds();
        $this->postJson('/silent-contact', $data)->assertOk()->assertJson(['caught' => true]);

        $reasons = Event::dispatched(HoneypotDetected::class)->map(fn ($args) => $args[0]->reason)->all();
        expect($reasons)->toBe(['submitted_too_quickly', 'previously_detected']);
    });

    test('invented tokens are caught but not remembered', function () {
        Event::fake([HoneypotDetected::class]);
        $data = ['hp_website' => '', 'hp_token' => str_repeat('a', 24)];

        $this->postJson('/silent-contact', $data)->assertJson(['caught' => true]);
        $this->postJson('/silent-contact', $data)->assertJson(['caught' => true]);

        $reasons = Event::dispatched(HoneypotDetected::class)->map(fn ($args) => $args[0]->reason)->unique()->all();
        expect($reasons)->toBe(['invalid_form_data']);
        expect(Cache::has(CaughtTokens::key($data['hp_token'])))->toBeFalse();
    });

    test('an expired form throws the normal validation error', function () {
        config(['livewire-honeypot.spam_responder' => AbortResponder::class]);
        $data = app(HoneypotService::class)->generate();
        $this->travel(61)->minutes();

        $this->postJson('/silent-contact', $data)->assertUnprocessable()->assertJsonValidationErrors('hp_started_at');
        expect(Cache::has(CaughtTokens::key($data['hp_token'])))->toBeFalse();
    });

    test('only signed tokens with enough random characters are remembered', function (int $length, bool $remembered) {
        config(['livewire-honeypot.token_length' => $length, 'livewire-honeypot.token_min_length' => $length]);
        $data = app(HoneypotService::class)->generate();

        $this->postJson('/silent-contact', $data)->assertJson(['caught' => true]);
        expect(Cache::has(CaughtTokens::key($data['hp_token'])))->toBe($remembered);
    })->with([
        'too short' => [CaughtTokens::MIN_REMEMBERED_LENGTH - 1, false],
        'minimum' => [CaughtTokens::MIN_REMEMBERED_LENGTH, true],
    ]);

    test('the configured responder is never called', function () {
        config(['livewire-honeypot.spam_responder' => AbortResponder::class]);
        $data = [...app(HoneypotService::class)->generate(), 'hp_website' => 'spam'];
        $this->travel(5)->seconds();

        $this->postJson('/silent-contact', $data)->assertOk()->assertJson(['caught' => true]);
    });

    test('fake mode reports submissions as clean', function () {
        HoneypotService::fake();

        $this->postJson('/silent-contact', ['hp_website' => 'spam'])->assertOk()->assertJson(['caught' => false]);
    });
});

// ---------------------------------------------------------------------------
// Test components
// ---------------------------------------------------------------------------

class SilentRejectionComponent extends Component
{
    use HasHoneypot;

    public bool $success = false;
    public int $submissions = 0;

    public function submit(?string $scenario = null): void
    {
        // Change locked metadata on the server to exercise invalid snapshots.
        if ($scenario === 'future') {
            $this->hp_started_at = now()->addMinute()->getTimestamp();
        } elseif ($scenario === 'token') {
            $this->hp_token = 'short';
        } elseif ($scenario === 'metadata') {
            $this->hp_token = '';
            $this->hp_started_at = 0;
        }

        if ($this->isHoneypotCaught()) {
            $this->success = true;

            return;
        }

        $this->submissions++;
        $this->success = true;
        $this->resetHoneypot();
    }

    public function addHoneypotError(): void
    {
        $this->addError('hp_website', 'Spam detected.');
    }

    public function render(): string
    {
        return '<div><x-honeypot /></div>';
    }
}

class SilentRejectionCustomBaitComponent extends Component
{
    use HasHoneypot;

    public array $contact = ['trap' => ''];
    public bool $success = false;
    public int $submissions = 0;

    public function submit(): void
    {
        if ($this->isHoneypotCaughtForModel('contact.trap')) {
            $this->success = true;

            return;
        }

        $this->submissions++;
        $this->success = true;
        $this->resetHoneypotForModel('contact.trap');
    }

    public function render(): string
    {
        return '<div><x-honeypot wire:model="contact.trap" /></div>';
    }
}

class SilentRejectionMisusedForm extends Form
{
    use HasHoneypot;

    public function submit(): bool
    {
        return $this->isHoneypotCaught();
    }
}

class SilentRejectionMisusedFormComponent extends Component
{
    public SilentRejectionMisusedForm $form;

    public function submit(): void
    {
        $this->form->submit();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class ThrowingSilentResponder implements SpamResponder
{
    public function respond(string $fieldName, string $message): never
    {
        throw new RuntimeException('The silent API must not call the responder.');
    }
}
