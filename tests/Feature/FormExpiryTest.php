<?php

use Blendbyte\LivewireHoneypot\CaughtTokens;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\HoneypotServiceProvider;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    config(['cache.default' => 'array']);
    ExpiryComponent::$settings = [];
});

// ---------------------------------------------------------------------------
// Configured expiry
// ---------------------------------------------------------------------------

test('the default expiry stays at one hour', function () {
    expect(config('livewire-honeypot.maximum_fill_seconds'))->toBe(3600);
});

test('Livewire forms follow the configured maximum', function (int $age, bool $expired) {
    config(['livewire-honeypot.maximum_fill_seconds' => 600]);
    $component = Livewire::test(ExpiryComponent::class);
    $this->travel($age)->seconds();

    $component->call('submit');

    $expired
        ? $component->assertHasErrors(['hp_started_at' => 'min'])->assertSet('submissions', 0)
        : $component->assertHasNoErrors()->assertSet('submissions', 1);
})->with([[600, false], [601, true]]);

test('plain forms follow the configured maximum', function (int $age, bool $expired) {
    config(['livewire-honeypot.maximum_fill_seconds' => 600]);
    $service = app(HoneypotService::class);
    $data = $service->generate();
    $this->travel($age)->seconds();

    $expired
        ? expect(fn () => $service->validate($data))->toThrow(ValidationException::class, 'This form has expired.')
        : expect(fn () => $service->validate($data))->not->toThrow(ValidationException::class);
})->with([[600, false], [601, true]]);

test('a maximum of 0 disables expiry', function () {
    config(['livewire-honeypot.maximum_fill_seconds' => 0]);
    $service = app(HoneypotService::class);
    $data = $service->generate();
    $component = Livewire::test(ExpiryComponent::class);
    $this->travel(30)->days();

    $service->validate($data);
    $component->call('submit')->assertHasNoErrors()->assertSet('submissions', 1);
});

test('a future start time is still rejected with expiry disabled', function () {
    config(['livewire-honeypot.maximum_fill_seconds' => 0]);
    $service = app(HoneypotService::class);
    $data = ['hp_website' => '', 'hp_token' => $service->token(now()->addMinute()->getTimestamp())];

    expect(fn () => $service->validate($data))->toThrow(ValidationException::class, 'Invalid form data.');
});

test('components can override the maximum', function () {
    config(['livewire-honeypot.maximum_fill_seconds' => 600]);
    ExpiryComponent::$settings = ['maximum_fill_seconds' => 7200];
    $component = Livewire::test(ExpiryComponent::class);
    $this->travel(2)->hours();

    $component->call('submit')->assertHasNoErrors()->assertSet('submissions', 1);
});

// ---------------------------------------------------------------------------
// Event reasons
// ---------------------------------------------------------------------------

test('an expired Livewire form is reported as form_expired', function () {
    Event::fake([HoneypotDetected::class]);
    $component = Livewire::test(ExpiryComponent::class);
    $this->travel(3601)->seconds();

    $component->call('submit')->assertHasErrors(['hp_started_at' => 'min']);

    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'form_expired');
});

test('an expired plain form is reported as form_expired and keeps its start-time error key', function () {
    Event::fake([HoneypotDetected::class]);
    $service = app(HoneypotService::class);
    $data = $service->generate();
    $this->travel(3601)->seconds();

    try {
        $service->validate($data);
        $this->fail('The expired form was accepted.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['hp_started_at']);
    }

    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'form_expired');
});

test('a future start time is still reported as invalid_form_data', function () {
    Event::fake([HoneypotDetected::class]);
    $service = app(HoneypotService::class);
    $data = ['hp_website' => '', 'hp_token' => $service->token(now()->addMinute()->getTimestamp())];

    expect(fn () => $service->validate($data))->toThrow(ValidationException::class);

    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'invalid_form_data');
});

test('an expired form with a filled bait is reported as honeypot_filled', function () {
    Event::fake([HoneypotDetected::class]);
    $service = app(HoneypotService::class);
    $data = [...$service->generate(), 'hp_website' => 'spam'];
    $this->travel(3601)->seconds();

    expect(fn () => $service->validate($data))->toThrow(ValidationException::class, 'Spam detected.');

    Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'honeypot_filled');
});

// ---------------------------------------------------------------------------
// Invalid settings
// ---------------------------------------------------------------------------

test('invalid global maximums fail at boot', function (int $minimum, int $maximum) {
    config([
        'livewire-honeypot.minimum_fill_seconds' => $minimum,
        'livewire-honeypot.maximum_fill_seconds' => $maximum,
    ]);

    expect(fn () => (new HoneypotServiceProvider(app()))->boot())
        ->toThrow(InvalidArgumentException::class, 'maximum_fill_seconds');
})->with([[5, -1], [5, 5], [600, 300]]);

test('valid global maximums boot', function (int $minimum, int $maximum) {
    config([
        'livewire-honeypot.minimum_fill_seconds' => $minimum,
        'livewire-honeypot.maximum_fill_seconds' => $maximum,
    ]);

    expect(fn () => (new HoneypotServiceProvider(app()))->boot())->not->toThrow(InvalidArgumentException::class);
})->with([[5, 0], [5, 6], [0, 1]]);

test('an invalid component maximum fails on mount', function () {
    ExpiryComponent::$settings = ['minimum_fill_seconds' => 60, 'maximum_fill_seconds' => 30];

    // Livewire wraps mount exceptions in a ViewException that keeps the original message.
    expect(fn () => Livewire::test(ExpiryComponent::class))->toThrow(Exception::class, 'maximum_fill_seconds (30)');
});

// ---------------------------------------------------------------------------
// Remembered caught tokens
// ---------------------------------------------------------------------------

test('a caught plain token is remembered until its form expires', function () {
    config(['livewire-honeypot.maximum_fill_seconds' => 600]);
    $service = app(HoneypotService::class);
    $data = $service->generate();
    $this->travel(5)->seconds();

    expect($service->isCaught([...$data, 'hp_website' => 'spam']))->toBeTrue();

    $this->travel(590)->seconds();
    expect($service->isCaught($data))->toBeTrue();

    // Once the token is forgotten, its form has expired and gets the normal error.
    $this->travel(10)->seconds();
    expect(fn () => $service->isCaught($data))->toThrow(ValidationException::class, 'This form has expired.');
});

test('without expiry a caught token is remembered for one day', function () {
    config(['livewire-honeypot.maximum_fill_seconds' => 0]);
    $service = app(HoneypotService::class);
    $data = $service->generate();
    $this->travel(5)->seconds();

    expect($service->isCaught([...$data, 'hp_website' => 'spam']))->toBeTrue();

    $this->travel(86399)->seconds();
    expect($service->isCaught($data))->toBeTrue();

    $this->travel(2)->seconds();
    expect($service->isCaught($data))->toBeFalse();
});

test('a caught Livewire token follows the component maximum', function () {
    ExpiryComponent::$settings = ['maximum_fill_seconds' => 7200];
    $component = Livewire::test(ExpiryComponent::class);
    $this->travel(5)->seconds();
    $component->set('hp_website', 'spam')->call('check')->assertSet('caught', true);

    $this->travel(3700)->seconds();
    $component->set('hp_website', '')->call('check')->assertSet('caught', true);
    expect(Cache::has(CaughtTokens::key($component->hp_token)))->toBeTrue();
});

class ExpiryComponent extends Component
{
    use HasHoneypot;

    public static array $settings = [];

    public int $submissions = 0;

    public bool $caught = false;

    protected function honeypotConfig(): array
    {
        return self::$settings;
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->submissions++;
        $this->resetHoneypot();
    }

    public function check(): void
    {
        $this->caught = $this->isHoneypotCaught();
    }

    public function render(): string
    {
        return '<div><x-honeypot /></div>';
    }
}
