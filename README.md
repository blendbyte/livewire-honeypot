<img alt="livewire-honeypot-banner" src="https://github.com/user-attachments/assets/bfa67e85-1864-4cb5-8df5-cf6880850f2f" />

# livewire-honeypot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blendbyte/livewire-honeypot.svg?style=flat-square)](https://packagist.org/packages/blendbyte/livewire-honeypot)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/blendbyte/livewire-honeypot/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-787cb5?style=flat-square)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20?style=flat-square)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-fb70a9?style=flat-square)](https://livewire.laravel.com)

Spam protection for Livewire and plain Laravel forms, without CAPTCHAs or external requests. Requires PHP 8.5, Laravel 13, and Livewire 4.

- **Hidden bait field** with a generated name per form, which browsers and password managers leave alone
- **Minimum fill time and form expiry**, tracked on the server so bots cannot fake them
- **Plain forms too:** `<x-honeypot />` renders a signed token for forms posting to a controller
- **Silent rejection** that answers bots with a fake success
- Optional **JavaScript check**, **events and logging**, **CSP nonces**, and **12 languages**

## Install

```bash
composer require blendbyte/livewire-honeypot
```

The service provider is registered automatically.

## Protect a Livewire form

Add the trait to your component and validate the honeypot before processing the submission:

```php
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;

class ContactForm extends Component
{
    use HasHoneypot;

    public string $email = '';

    public function submit(): void
    {
        $this->validateHoneypot();
        $this->validate(['email' => 'required|email']);

        // Process the submission here.

        $this->reset('email');
        $this->resetHoneypot();
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

In `resources/views/livewire/contact-form.blade.php`:

```blade
<form wire:submit="submit">
    <x-honeypot />

    <label for="email">Email</label>
    <input id="email" type="email" wire:model="email">
    @error('email') <p>{{ $message }}</p> @enderror

    <button type="submit">Send</button>
</form>
```

By default, the hidden bait must stay empty, submissions must wait **5 seconds**, and forms expire after **1 hour**. Honeypot errors appear beside the component; style `.hp-error` to match your form.

Keep the trait on the Livewire component, including when using a Livewire `Form` object. Call `resetHoneypot()` after a successful submission to refresh the form's protection.

## Protect a plain form

Outside a Livewire component, the same Blade component renders a signed token for forms posting to a controller:

```blade
<form method="POST" action="/contact">
    @csrf
    <x-honeypot />

    {{-- Your regular fields and submit button. --}}
</form>
```

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Http\Request;

public function store(Request $request, HoneypotService $honeypot)
{
    $honeypot->validate($request->all());

    // Validate and process the rest of the form.
}
```

A rejected submission redirects back, and the error appears beside the component. Every render gets a fresh token, so do not cache pages containing the form. See [plain HTML forms](docs/plain-forms.md) for JavaScript verification and for rendering the fields yourself.

## Silent rejection

A validation error tells a bot it was caught, and it can wait and resubmit. To answer bots with a fake success instead, check `isHoneypotCaught()` and return early:

```php
public function submit(): void
{
    if ($this->isHoneypotCaught()) {
        $this->success = true; // looks like success, nothing is saved or sent
        return;
    }

    $this->validate(['email' => 'required|email']);

    // Process the submission here.

    $this->success = true;
    $this->reset('email');
    $this->resetHoneypot();
}
```

`isHoneypotCaught()` never calls the configured responder or adds errors. Once a form's token is caught, every later submission from that form is caught too, even after waiting or clearing the bait field. Do not call `resetHoneypot()` for a caught submission: a fresh token would let the bot start over. For custom bindings, use `isHoneypotCaughtForModel('contact.trap')`; plain forms can use `HoneypotService::isCaught()`.

Real users can be caught too, for example by autofilling and submitting faster than the minimum time, and they will see the same fake success. Stick with `validateHoneypot()` when a visible error is safer than losing a message silently. Expired forms are not treated as spam: they still show a validation error asking the visitor to reload the page and send again.

## Configuration

Most applications can use the defaults. To change them, set these in `.env`:

| Variable | Default | What it does |
| --- | --- | --- |
| `HONEYPOT_MINIMUM_FILL_SECONDS` | `5` | Seconds a visitor must spend on the form before submitting. `0` disables the check. |
| `HONEYPOT_MAXIMUM_FILL_SECONDS` | `3600` | Older forms ask the visitor to reload. Must be greater than the minimum; `0` disables expiry. |
| `HONEYPOT_RANDOMIZE_FIELD_NAME` | `true` | Renders the bait with a generated HTML name such as `referral_3f9a`. |
| `HONEYPOT_FIELD_NAME` | `hp_website` | The Livewire property bound to the bait, and the key errors are reported under. |
| `HONEYPOT_JS_VERIFICATION` | `false` | Also requires a marker that JavaScript fills on page load. |
| `HONEYPOT_LOGGING` | `false` | Logs every detection. |
| `HONEYPOT_LOG_CHANNEL` | default channel | The log channel for detections. |
| `HONEYPOT_LOG_LEVEL` | `warning` | The log level for detections. |
| `HONEYPOT_LOG_VALUE` | `false` | Also logs the bait value, shortened to 200 characters. It can contain a visitor's autofilled personal data. |
| `HONEYPOT_CAUGHT_CACHE_STORE` | default store | The cache store for tokens caught by silent rejection. |
| `HONEYPOT_TOKEN_LENGTH` | `24` | The length of the random part of a form token. |
| `HONEYPOT_TOKEN_MIN_LENGTH` | `10` | The shortest random part accepted on submit. |

The spam responder is chosen in the [configuration file](config/livewire-honeypot.php), which you can publish:

```bash
php artisan vendor:publish --tag=livewire-honeypot-config
```

Override settings for one component with `honeypotConfig()`:

```php
protected function honeypotConfig(): array
{
    return ['minimum_fill_seconds' => 10];
}
```

For a single action, use `$this->validateHoneypot(minimumSeconds: 2)`.

### Custom bait bindings

Declare an empty bait property, such as `public array $contact = ['trap' => ''];`, and use the same path in the view and validation calls:

```blade
<x-honeypot wire:model="contact.trap" />
```

```php
$this->validateHoneypotForModel('contact.trap');
// Validate and process the rest of the form.
$this->resetHoneypotForModel('contact.trap');
```

This also works with `form.trap` on a Livewire form object. If you change `field_name` globally or through `honeypotConfig()`, declare a matching public property on the component. The default `<x-honeypot />` binding follows that setting automatically.

### Generated HTML names

The bait's HTML name is generated from the form's token, so it changes with every form, does not look like a honeypot, and avoids names that browsers and password managers autofill. Only the HTML name changes: the Livewire binding still targets `field_name` or your custom `wire:model` path. Password-manager ignore hints are included as well, but autofill behavior varies by browser and extension.

To keep a fixed name, set `HONEYPOT_RANDOMIZE_FIELD_NAME=false` or pass one with `<x-honeypot field-name="..." />`.

## Testing

Bypass honeypot checks in tests that focus on the rest of your form:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

beforeEach(fn () => HoneypotService::fake());
```

Fake mode ends with each test's application, so it never leaks into other tests. Call `HoneypotService::resetFake()` to turn it off within a test.

To test the waiting period itself, mount the component and advance time before submitting:

```php
$component = Livewire::test(ContactForm::class);
$this->travel(5)->seconds();
$component->call('submit');
```

The timestamp and token are locked properties, so use time travel instead of setting them through Livewire. To select the bait input in browser tests, use its `wire:model` attribute rather than its generated name.

See [testing](docs/testing.md) for silent rejection tests and the package's own PHP and browser suites.

## More options

- [Plain HTML forms](docs/plain-forms.md): JavaScript verification and rendering the fields yourself.
- [Advanced options](docs/advanced.md): CSP, responders, silent rejection, events and logs, JS verification, views, and translations.
- [Upgrading](docs/upgrading.md): what changed since 2.1.0, and earlier integration changes.

Honeypots catch simple automation, not every bot. Keep normal validation, CSRF protection, and rate limiting on your forms.

Forked from [ArvidDeJong/livewire-honeypot](https://github.com/ArvidDeJong/livewire-honeypot). Licensed under [MIT](LICENSE).

## Maintained by Blendbyte

<br>

<p align="center">
  <a href="https://www.blendbyte.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://www.blendbyte.com/logo_horizontal_light.png">
      <img src="https://www.blendbyte.com/logo_horizontal.png" alt="Blendbyte" width="360">
    </picture>
  </a>
</p>

<p align="center">
  <strong><a href="https://www.blendbyte.com">Blendbyte</a></strong> builds cloud infrastructure, web apps, and developer tools.<br>
  We've been shipping software to production for 20+ years.
</p>

<p align="center">
  This package runs in our own stack, which is why we keep it maintained.<br>
  Issues and PRs get read. Good ones get merged.
</p>

<br>

<p align="center">
  <a href="https://www.blendbyte.com">blendbyte.com</a> · <a href="mailto:hello@blendbyte.com">hello@blendbyte.com</a>
</p>
