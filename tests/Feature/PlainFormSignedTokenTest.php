<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->freezeTime();
    Route::middleware('web')->post('/signed-contact', function (Request $request, HoneypotService $honeypot) {
        $honeypot->validate($request->all());

        return response()->json(['accepted' => true, 'bait_is_null' => $request->input('hp_website') === null]);
    });
});

test('plain forms validate through the normal middleware with a signed token', function () {
    $data = app(HoneypotService::class)->generate();
    unset($data['hp_started_at']);
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', $data)
        ->assertOk()
        ->assertJson(['accepted' => true, 'bait_is_null' => true]);
});

test('plain forms cannot bypass the time trap by posting an older timestamp', function () {
    $data = app(HoneypotService::class)->generate();
    $data['hp_started_at'] = now()->subSeconds(10)->getTimestamp();

    $this->postJson('/signed-contact', $data)->assertUnprocessable()->assertJsonValidationErrors('hp_website');
});

test('plain forms reject invented tokens', function () {
    $this->postJson('/signed-contact', [
        'hp_website' => '',
        'hp_started_at' => now()->subSeconds(10)->getTimestamp(),
        'hp_token' => str_repeat('a', 24),
    ])->assertUnprocessable()->assertJsonValidationErrors('hp_token');
});

test('plain forms still reject missing or filled bait with a valid token', function (bool $missing) {
    $data = app(HoneypotService::class)->generate();
    if ($missing) {
        unset($data['hp_website']);
    } else {
        $data['hp_website'] = 'spam';
    }
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', $data)->assertUnprocessable()->assertJsonValidationErrors('hp_website');
})->with([true, false]);

test('plain forms reject malformed JS markers through the normal validation path', function (mixed $marker) {
    config(['livewire-honeypot.require_js_verification' => true]);
    Event::fake([HoneypotDetected::class]);
    $data = app(HoneypotService::class)->generate();
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', [...$data, 'hp_js' => $marker])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hp_website');

    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'js_verification_failed');
})->with([
    'empty array' => [[]],
    'filled array' => [['1']],
    'nested array' => [['marker' => ['1']]],
    'integer' => [1],
    'boolean' => [true],
    'null' => [null],
    'whitespace' => ['   '],
]);

test('plain forms accept a nonempty string JS marker', function () {
    config(['livewire-honeypot.require_js_verification' => true]);
    $data = app(HoneypotService::class)->generate();
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', [...$data, 'hp_js' => '1'])->assertOk();
});

test('malformed JS markers still use the configured spam responder', function () {
    config([
        'livewire-honeypot.require_js_verification' => true,
        'livewire-honeypot.spam_responder' => AbortResponder::class,
    ]);
    $data = app(HoneypotService::class)->generate();
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', [...$data, 'hp_js' => ['1']])->assertForbidden();
});

/**
 * A plain form that renders the derived bait name instead of field_name.
 */
function derivedBaitForm(string $value = ''): array
{
    $service = app(HoneypotService::class);
    $token = $service->generate()['hp_token'];

    return ['hp_token' => $token, $service->baitName($token) => $value];
}

test('plain forms accept an empty bait under its derived name through the middleware', function () {
    $data = derivedBaitForm();
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', $data)->assertOk();
});

test('plain forms reject a filled bait under its derived name', function () {
    Event::fake([HoneypotDetected::class]);
    $data = derivedBaitForm('https://spam.example');
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', $data)->assertUnprocessable()->assertJsonValidationErrors('hp_website');
    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'honeypot_filled'
        && $event->fieldName === 'hp_website'
        && $event->filledValue === 'https://spam.example');
});

test('a filled field_name still counts when the derived bait is empty', function () {
    $data = [...derivedBaitForm(), 'hp_website' => 'spam'];
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', $data)->assertUnprocessable()->assertJsonValidationErrors('hp_website');
});

test('a bait name derived from another token is not accepted', function () {
    $data = derivedBaitForm();
    $other = derivedBaitForm();
    $this->travel(5)->seconds();

    $this->postJson('/signed-contact', ['hp_token' => $data['hp_token'], ...array_diff_key($other, ['hp_token' => true])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hp_website');
});
