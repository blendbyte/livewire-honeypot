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

Add the component inside the form:

```blade
<form wire:submit="submit">
    <x-honeypot />

    <label for="email">Email</label>
    <input id="email" type="email" wire:model="email">
    @error('email') <p>{{ $message }}</p> @enderror

    <button type="submit">Send</button>
</form>
```

Keep the trait on the component, also when you use a Livewire `Form` object, and call `resetHoneypot()` after a successful submission. Honeypot errors appear beside the component; style `.hp-error` to match your form.

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

A rejected submission redirects back with the error shown beside the component. Do not cache pages containing the form, because every render needs a fresh token.

## Configuration

The defaults suit most forms: the bait must stay empty, submissions must wait **5 seconds**, and forms expire after **1 hour**. To change the waiting time or log detections:

```env
HONEYPOT_MINIMUM_FILL_SECONDS=3
HONEYPOT_LOGGING=true
```

See [configuration](docs/configuration.md) for every option, per-component settings, and custom bait bindings.

## Testing

Bypass the honeypot in tests that focus on the rest of your form:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

beforeEach(fn () => HoneypotService::fake());
```

## Documentation

- [Configuration](docs/configuration.md): all settings, per-component overrides, custom bindings, and generated names.
- [Plain HTML forms](docs/plain-forms.md): JavaScript verification and rendering the fields yourself.
- [Advanced options](docs/advanced.md): silent rejection, responders, events and logs, CSP, JS verification, views, and translations.
- [Testing](docs/testing.md): testing your forms and running the package's own suites.
- [Upgrading](docs/upgrading.md): what changed since 2.1.0.

Honeypots catch simple automation, not every bot. Keep normal validation, CSRF protection, and rate limiting on your forms.

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
