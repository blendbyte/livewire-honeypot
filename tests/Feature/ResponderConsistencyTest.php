<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeTime();
    Event::fake([HoneypotDetected::class]);
    ConsistentResponderComponent::$processed = false;
});

dataset('configured responders', [
    'abort' => [AbortResponder::class, 403],
    'redirect' => [RedirectResponder::class, 302],
    'custom subclass' => [CustomValidationResponder::class, 409],
]);

dataset('honeypot rejection paths', [
    'filled bait' => ['filled', 'honeypot_filled'],
    'missing bait' => ['missing', 'honeypot_filled'],
    'invalid token' => ['token', 'invalid_form_data'],
    'missing metadata' => ['metadata', 'invalid_form_data'],
    'expired form' => ['expired', 'invalid_form_data'],
    'future timestamp' => ['future', 'invalid_form_data'],
    'too quick' => ['quick', 'submitted_too_quickly'],
    'missing JS marker' => ['js', 'js_verification_failed'],
]);

test('Livewire uses the configured responder for every rejection', function (string $responder, int $status, string $scenario, string $reason) {
    config([
        'livewire-honeypot.spam_responder' => $responder,
        'livewire-honeypot.require_js_verification' => $scenario === 'js',
    ]);
    $component = Livewire::test(ConsistentResponderComponent::class);
    $this->travel($scenario === 'expired' ? 3601 : ($scenario === 'quick' ? 0 : 5))->seconds();

    if ($scenario === 'filled') {
        $component->set('contact.trap', 'spam');
    } elseif ($scenario === 'missing') {
        $component->set('contact', []);
    }

    $component->call('submit', $scenario)->assertStatus($responder === RedirectResponder::class ? 200 : $status);
    if ($responder === RedirectResponder::class) {
        $component->assertRedirect(url('/'));
    }
    expect(ConsistentResponderComponent::$processed)->toBeFalse();
    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->reason === $reason && $event->fieldName === 'contact.trap'
        && $event->component === ConsistentResponderComponent::class
    );

    if ($responder === CustomValidationResponder::class) {
        $component->assertJsonPath('field', 'contact.trap');
        if ($reason === 'invalid_form_data') {
            $component->assertJsonPath('message', __('livewire-honeypot::validation.invalid_form_data'));
        }
    }
})->with('configured responders')->with('honeypot rejection paths');

test('plain forms use the configured responder for every rejection', function (string $responder, int $status, string $scenario, string $reason) {
    config([
        'livewire-honeypot.field_name' => 'trap',
        'livewire-honeypot.spam_responder' => $responder,
        'livewire-honeypot.require_js_verification' => $scenario === 'js',
    ]);
    $processed = false;
    Route::middleware('web')->post('/responder-contact', function (Request $request, HoneypotService $service) use (&$processed) {
        $service->validate($request->all());
        $processed = true;

        return response()->noContent();
    });
    $service = app(HoneypotService::class);
    $data = $service->generate();
    $this->travel($scenario === 'expired' ? 3601 : ($scenario === 'quick' ? 0 : 5))->seconds();

    switch ($scenario) {
        case 'filled': $data['trap'] = 'spam'; break;
        case 'missing': unset($data['trap']); break;
        case 'token': $data['hp_token'] = 'invalid'; break;
        case 'metadata': unset($data['hp_token']); break;
        case 'future': $data['hp_token'] = $service->token(now()->addMinute()->getTimestamp()); break;
    }

    $response = $this->from('/contact')->postJson('/responder-contact', $data)->assertStatus($status);
    if ($responder === RedirectResponder::class) {
        $response->assertRedirect('/contact');
    }
    expect($processed)->toBeFalse();
    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) =>
        $event->reason === $reason && $event->fieldName === 'trap' && $event->component === null
    );

    if ($responder === CustomValidationResponder::class) {
        $response->assertJsonPath('field', 'trap');
        if ($reason === 'invalid_form_data') {
            $response->assertJsonPath('message', __('livewire-honeypot::validation.invalid_form_data'));
        }
    }
})->with('configured responders')->with('honeypot rejection paths');

class ConsistentResponderComponent extends Component
{
    use HasHoneypot;

    public array $contact = ['trap' => ''];
    public static bool $processed = false;

    public function submit(string $scenario): void
    {
        // Change locked metadata on the server to exercise invalid snapshots.
        if ($scenario === 'token') {
            $this->hp_token = 'short';
        } elseif ($scenario === 'metadata') {
            $this->hp_token = '';
            $this->hp_started_at = 0;
        } elseif ($scenario === 'future') {
            $this->hp_started_at = now()->addMinute()->getTimestamp();
        }

        $this->validateHoneypotForModel('contact.trap');
        self::$processed = true;
    }

    public function render(): string
    {
        return '<div><x-honeypot wire:model="contact.trap" /></div>';
    }
}

class CustomValidationResponder extends ValidationExceptionResponder
{
    public function respond(string $fieldName, string $message): never
    {
        throw new HttpResponseException(response()->json(['field' => $fieldName, 'message' => $message], 409));
    }
}
