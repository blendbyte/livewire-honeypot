<?php

use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeTime();

    Route::middleware('web')->get('/plain-contact', fn () => Blade::render(
        '<form method="POST" action="/plain-contact">@csrf<x-honeypot /></form>'
    ));
    Route::middleware('web')->post('/plain-contact', function (Request $request, HoneypotService $honeypot) {
        $honeypot->validate($request->all());

        return 'accepted';
    });
});

/**
 * Read the rendered bait name and token from <x-honeypot /> markup.
 *
 * @return array{bait: string, token: string}
 */
function plainHoneypotFields(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    return [
        'bait' => $xpath->query('//input[@type="text"]')->item(0)->getAttribute('name'),
        'token' => $xpath->query('//input[@name="hp_token"]')->item(0)->getAttribute('value'),
    ];
}

test('outside Livewire the component renders a signed token and a derived bait name', function () {
    $html = Blade::render('<x-honeypot />');
    $fields = plainHoneypotFields($html);
    $service = app(HoneypotService::class);

    expect($service->startedAtFromToken($fields['token']))->toBe(now()->getTimestamp());
    expect($fields['bait'])->toBe($service->baitName($fields['token']));
    expect($html)->not->toContain('wire:')
        ->not->toContain('x-data')
        ->toContain('value=""');
});

test('every render gets a fresh token', function () {
    $first = plainHoneypotFields(Blade::render('<x-honeypot />'));
    $second = plainHoneypotFields(Blade::render('<x-honeypot />'));

    expect($first['token'])->not->toBe($second['token']);
});

test('plain forms render field_name when randomization is disabled', function () {
    config(['livewire-honeypot.randomize_field_name' => false, 'livewire-honeypot.field_name' => 'trap']);

    expect(plainHoneypotFields(Blade::render('<x-honeypot />'))['bait'])->toBe('trap');
});

test('Livewire bindings are not rendered in a plain form', function () {
    $html = Blade::render('<x-honeypot wire:model.blur="contact.trap" />');

    expect($html)->not->toContain('wire:model')
        ->toContain('name="hp_token"');
});

test('a Livewire component without the trait renders a plain form', function () {
    Livewire::test(PlainFormHostComponent::class)
        ->assertSeeHtml('name="hp_token"')
        ->assertDontSeeHtml('wire:model');
});

test('a plain form submits through the middleware after the fill time', function () {
    $fields = plainHoneypotFields($this->get('/plain-contact')->getContent());
    $this->travel(5)->seconds();

    $this->post('/plain-contact', ['hp_token' => $fields['token'], $fields['bait'] => ''])
        ->assertOk()
        ->assertSee('accepted');
});

test('a filled plain form is rejected and the error is shown when the form is rendered again', function () {
    $fields = plainHoneypotFields($this->get('/plain-contact')->getContent());
    $this->travel(5)->seconds();

    $this->from('/plain-contact')
        ->post('/plain-contact', ['hp_token' => $fields['token'], $fields['bait'] => 'spam'])
        ->assertRedirect('/plain-contact')
        ->assertSessionHasErrors('hp_website');

    $html = $this->get('/plain-contact')->getContent();
    expect($html)->toContain('<p class="hp-error" role="alert">Spam detected.</p>');
    // The form is rendered with a fresh token, not the rejected one.
    expect(plainHoneypotFields($html)['token'])->not->toBe($fields['token']);
});

test('a plain form submitted too quickly is rejected', function () {
    $fields = plainHoneypotFields($this->get('/plain-contact')->getContent());

    $this->post('/plain-contact', ['hp_token' => $fields['token'], $fields['bait'] => ''])
        ->assertSessionHasErrors(['hp_website' => 'Form submitted too quickly.']);
});

test('plain JS verification renders an empty marker and a nonced script that fills it', function () {
    config(['livewire-honeypot.require_js_verification' => true]);
    Vite::useCspNonce('plain-nonce');

    $html = Blade::render('<x-honeypot />');

    expect($html)->toContain('<input type="hidden" name="hp_js" value="" />')
        ->toMatch('/<script\s+nonce="plain-nonce"\s*>document\.currentScript\.previousElementSibling\.value = \'1\';<\/script>/')
        ->not->toContain('x-init');
});

test('plain JS verification requires the marker on submit', function () {
    config(['livewire-honeypot.require_js_verification' => true]);
    $fields = plainHoneypotFields($this->get('/plain-contact')->getContent());
    $this->travel(5)->seconds();
    $data = ['hp_token' => $fields['token'], $fields['bait'] => ''];

    $this->post('/plain-contact', [...$data, 'hp_js' => ''])
        ->assertSessionHasErrors(['hp_website' => 'JavaScript verification failed.']);
    $this->post('/plain-contact', [...$data, 'hp_js' => '1'])->assertOk();
});

class PlainFormHostComponent extends Component
{
    public function render(): string
    {
        return '<div><form method="POST" action="/newsletter"><x-honeypot /></form></div>';
    }
}
