<img alt="livewire-honeypot-banner" src="https://github.com/user-attachments/assets/bfa67e85-1864-4cb5-8df5-cf6880850f2f" />

# livewire-honeypot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blendbyte/livewire-honeypot.svg?style=flat-square)](https://packagist.org/packages/blendbyte/livewire-honeypot)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/blendbyte/livewire-honeypot/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-787cb5?style=flat-square)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20?style=flat-square)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-fb70a9?style=flat-square)](https://livewire.laravel.com)

Honeypot and minimum-fill-time protection for Livewire forms, without CAPTCHAs or external requests. Requires PHP 8.5, Laravel 13, and Livewire 4.

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

## Configuration

Most applications can use the defaults. To change the waiting time or enable detection logs:

```env
HONEYPOT_MINIMUM_FILL_SECONDS=3
HONEYPOT_LOGGING=true
```

Set the minimum to `0` to disable the waiting period. For all options, publish the [configuration file](config/livewire-honeypot.php):

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

This also works with `form.trap` on a Livewire form object. If you change the global `field_name`, declare a matching public property on the component.

### Randomized HTML names

Set `HONEYPOT_RANDOMIZE_FIELD_NAME=true` and pass the generated name to the view:

```blade
<x-honeypot :field-name="$hp_field_name" />
```

This changes the HTML name while keeping the Livewire binding intact. Password-manager ignore hints are included, but autofill behavior varies by browser and extension.

## Testing

Bypass honeypot checks in tests that focus on the rest of your form:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

beforeEach(fn () => HoneypotService::fake());
afterEach(fn () => HoneypotService::resetFake());
```

To test the waiting period itself, mount the component and advance time before submitting:

```php
$component = Livewire::test(ContactForm::class);
$this->travel(5)->seconds();
$component->call('submit');
```

The timestamp and token are locked properties, so use time travel instead of setting them through Livewire.

For the package's own PHP and browser checks, see [running the test suites](docs/testing.md).

## More options

- [Plain HTML forms](docs/plain-forms.md): signed tokens and controller validation.
- [Advanced options](docs/advanced.md): CSP, responders, events, translations, and JS verification.
- [Upgrading existing integrations](docs/upgrading.md): published views, custom bindings, and signed tokens.

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
