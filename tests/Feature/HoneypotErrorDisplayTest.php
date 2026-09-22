<?php

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(fn () => $this->freezeTime());

function renderHoneypotWithErrors(array $messages, string $template = '<x-honeypot />'): string
{
    view()->share('errors', (new ViewErrorBag())->put('default', new MessageBag($messages)));

    return Blade::render($template);
}

test('honeypot errors are visible outside the hidden wrapper', function () {
    $html = renderHoneypotWithErrors(['hp_website' => 'Form submitted too quickly.']);
    $document = new DOMDocument();
    $document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//p[@class="hp-error" and @role="alert"]')->length)->toBe(1);
    expect($xpath->query('//p[@role="alert"]/ancestor::*[@aria-hidden="true" or @class="hp-field"]')->length)->toBe(0);
    expect($html)->toContain('Form submitted too quickly.');
});

test('error messages are escaped', function () {
    $html = renderHoneypotWithErrors(['hp_website' => '<script>alert(1)</script>']);

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>');
});

test('an unrelated validation error is not shown by the honeypot component', function () {
    $html = renderHoneypotWithErrors(['email' => 'An email address is required.']);

    expect($html)->not->toContain('role="alert"')->not->toContain('An email address is required.');
});

test('the component also renders without an available error bag', function () {
    app('view')->share('errors', null);

    expect(Blade::render('<x-honeypot />'))->not->toContain('role="alert"');
});

test('the configured field name determines the default error key', function () {
    config(['livewire-honeypot.field_name' => 'trap']);
    $html = renderHoneypotWithErrors(['trap' => 'Spam detected.']);

    expect($html)->toContain('<p class="hp-error" role="alert">Spam detected.</p>');
});

test('the error key follows custom bindings rather than randomized HTML names', function (string $directive) {
    $html = renderHoneypotWithErrors(
        ['contact.trap' => 'Spam detected.'],
        '<x-honeypot ' . $directive . '="contact.trap" field-name="hp_random" />',
    );

    expect($html)->toContain('<p class="hp-error" role="alert">Spam detected.</p>')
        ->toContain('name="hp_random"');
})->with(['wire:model', 'wire:model.blur', 'wire:model.live.debounce.500ms']);

test('an explicit error key changes only which error is displayed', function () {
    $html = renderHoneypotWithErrors(
        ['contact.trap' => 'Default error', 'form_spam' => 'Custom error'],
        '<x-honeypot wire:model="contact.trap" error-key="form_spam" />',
    );

    expect($html)->toContain('<p class="hp-error" role="alert">Custom error</p>')
        ->toContain('wire:model="contact.trap"')
        ->not->toContain('Default error');
});

test('metadata validation errors are displayed when there is no bait error', function (string $key) {
    $html = renderHoneypotWithErrors([$key => 'Invalid form data.']);

    expect($html)->toContain('<p class="hp-error" role="alert">Invalid form data.</p>');
})->with(['hp_started_at', 'hp_token']);

test('only the first relevant error is displayed', function () {
    $html = renderHoneypotWithErrors([
        'hp_website' => ['Spam detected.', 'Another bait error'],
        'hp_started_at' => 'Invalid form data.',
    ]);

    expect(substr_count($html, 'role="alert"'))->toBe(1);
    expect($html)->toContain('Spam detected.')->not->toContain('Another bait error')->not->toContain('Invalid form data.');
});

test('the time-trap error appears on submission and clears after a successful retry', function () {
    $component = Livewire::test(ErrorDisplayComponent::class);
    $component->assertDontSeeHtml('role="alert"');

    $component->call('submit')
        ->assertHasErrors('hp_website')
        ->assertSeeHtml('<p class="hp-error" role="alert">Form submitted too quickly.</p>');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors()->assertDontSeeHtml('role="alert"');
});

test('an expired Livewire form shows its metadata validation error', function () {
    $component = Livewire::test(ErrorDisplayComponent::class);
    $this->travel(3601)->seconds();

    $component->call('submit')
        ->assertHasErrors('hp_started_at')
        ->assertSeeHtml('<p class="hp-error" role="alert">Invalid form data.</p>');
});

test('custom bindings display both bait and JS verification errors', function () {
    config(['livewire-honeypot.require_js_verification' => true]);
    $component = Livewire::test(CustomErrorDisplayComponent::class);
    $this->travel(5)->seconds();

    $component->set('contact.trap', 'spam')->call('submit')
        ->assertSeeHtml('<p class="hp-error" role="alert">Spam detected.</p>');

    $component->set('contact.trap', '')->call('submit')
        ->assertSeeHtml('<p class="hp-error" role="alert">JavaScript verification failed.</p>');

    $component->set('hp_js', 'browser-value')->call('submit')
        ->assertHasNoErrors()->assertDontSeeHtml('role="alert"');
});

class ErrorDisplayComponent extends Component
{
    use HasHoneypot;

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->resetHoneypot();
    }

    public function render(): string
    {
        return '<form wire:submit="submit"><x-honeypot /></form>';
    }
}

class CustomErrorDisplayComponent extends Component
{
    use HasHoneypot;

    public array $contact = ['trap' => ''];

    public function submit(): void
    {
        $this->validateHoneypotForModel('contact.trap');
        $this->resetHoneypotForModel('contact.trap');
    }

    public function render(): string
    {
        return '<form wire:submit="submit"><x-honeypot wire:model.blur="contact.trap" /></form>';
    }
}
