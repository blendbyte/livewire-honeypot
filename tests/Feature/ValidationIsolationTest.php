<?php

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Wireable;

beforeEach(function () {
    config(['livewire-honeypot.minimum_fill_seconds' => 0]);
    Event::fake([HoneypotDetected::class]);
});

test('application validation hooks run only on application validation', function (string $responder) {
    config(['livewire-honeypot.spam_responder' => $responder]);

    Livewire::test(IsolatedValidationComponent::class)->call('submit')
        ->assertStatus(200)->assertHasErrors('email')->assertHasNoErrors('hp_website')
        ->assertSet('hookRules', ['email'])->assertSet('processed', false);

    Event::assertNotDispatched(HoneypotDetected::class);
})->with([ValidationExceptionResponder::class, AbortResponder::class, RedirectResponder::class]);

test('honeypot validation does not clear unrelated errors or consume validation hooks', function () {
    Livewire::test(IsolatedValidationComponent::class)->call('checkHoneypot')
        ->assertHasErrors('email')->assertHasNoErrors('hp_website')
        ->assertSet('hookRules', []);

    Event::assertNotDispatched(HoneypotDetected::class);
});

test('application preparation cannot erase a filled honeypot', function () {
    Livewire::test(IsolatedValidationComponent::class)
        ->set('hp_website', 'spam')->call('submit')
        ->assertHasErrors(['hp_website' => 'size'])->assertSet('hookRules', [])
        ->assertSet('processed', false);

    Event::assertDispatchedTimes(HoneypotDetected::class, 1);
});

test('isolated validation still unwraps custom Livewire property types', function () {
    $component = Livewire::test(WireableBaitValidationComponent::class);
    $component->call('submit')->assertHasNoErrors()->assertSet('submissions', 1);
    $component->set('contact.trap', 'spam')->call('submit')
        ->assertHasErrors(['contact.trap' => 'size'])->assertSet('submissions', 1);
});

class IsolatedValidationComponent extends Component
{
    use HasHoneypot;

    public string $email = 'blocked@example.com';
    public array $hookRules = [];
    public bool $processed = false;

    public function boot(): void
    {
        $this->withValidator(function ($validator) {
            $this->hookRules = array_keys($validator->getRules());
            $validator->after(fn ($validator) => $validator->errors()->add('email', 'This address is blocked.'));
        });
    }

    protected function prepareForValidation($data)
    {
        // Application preparation must not alter the honeypot's own checks.
        $data['hp_website'] = '';

        return $data;
    }

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->validate(['email' => 'required|email']);
        $this->processed = true;
    }

    public function checkHoneypot(): void
    {
        $this->addError('email', 'Keep this application error.');
        $this->addError('hp_website', 'Clear this old honeypot error.');
        $this->validateHoneypot();
    }

    public function render(): string
    {
        return '<div><x-honeypot /></div>';
    }
}

class WireableBaitData implements Wireable
{
    public function __construct(public string $trap = '') {}

    public function toLivewire(): array { return ['trap' => $this->trap]; }

    public static function fromLivewire($value): static { return new static($value['trap']); }
}

class WireableBaitValidationComponent extends Component
{
    use HasHoneypot;

    public WireableBaitData $contact;
    public int $submissions = 0;

    public function mount(): void { $this->contact = new WireableBaitData(); }

    public function submit(): void
    {
        $this->validateHoneypotForModel('contact.trap');
        $this->submissions++;
    }

    public function render(): string { return '<div><x-honeypot wire:model="contact.trap" /></div>'; }
}
