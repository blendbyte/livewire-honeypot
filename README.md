# livewire-honeypot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blendbyte/livewire-honeypot.svg?style=flat-square)](https://packagist.org/packages/blendbyte/livewire-honeypot)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-787cb5?style=flat-square)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20?style=flat-square)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-fb70a9?style=flat-square)](https://livewire.laravel.com)

Lightweight **honeypot + time-trap** protection for **Livewire 4** (Laravel 13). Blocks simple bots without CAPTCHAs — privacy-friendly, zero external requests, and invisible to real users.

Forked from [darvis/livewire-honeypot](https://github.com/darvis/livewire-honeypot).

---

## Features

- **Honeypot bait field** — hidden input that bots fill in, legitimate users never see (`present|size:0`)
- **Time-trap** — enforces a configurable minimum time between page load and submission
- **Token validation** — cryptographically random token verified on each submission
- **Livewire Trait** — drop-in protection for any Livewire component
- **Controller / API Service** — use outside of Livewire for standard form controllers
- **Blade component** — `<x-honeypot />` renders all hidden fields in one line
- **Multilingual** — English, Dutch, and German translations included
- **Fully configurable** — all settings available via config file or environment variables
- **Zero extra dependencies** — only requires Livewire 4 / Laravel 13

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.5` |
| Laravel | `^13.0` |
| Livewire | `^4.0` |

---

## Installation

```bash
composer require blendbyte/livewire-honeypot
```

The service provider is auto-discovered. No manual registration required.

---

## Usage

### Livewire Components (Trait)

Add the `HasHoneypot` trait to your Livewire component and call `validateHoneypot()` inside your submit action. After a successful submission, call `resetHoneypot()` to regenerate the token and timestamp for subsequent submissions.

```php
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;

class ContactForm extends Component
{
    use HasHoneypot;

    public string $name = '';
    public string $email = '';
    public string $message = '';

    public function submit(): void
    {
        $this->validateHoneypot();

        $this->validate([
            'name'    => 'required|string|min:2',
            'email'   => 'required|email',
            'message' => 'required|string|min:10',
        ]);

        // process form ...

        $this->reset(['name', 'email', 'message']);
        $this->resetHoneypot();
    }
}
```

Add the Blade component anywhere inside your form:

```blade
<form wire:submit="submit">
    <x-honeypot />

    <input type="text" wire:model="name" />
    <input type="email" wire:model="email" />
    <textarea wire:model="message"></textarea>

    <button type="submit">Send</button>
</form>
```

#### Custom field name

If you configure a custom `field_name` in your config (or via `HONEYPOT_FIELD_NAME`), you must declare a matching public property on your component:

```php
// config: HONEYPOT_FIELD_NAME=hp_url

class ContactForm extends Component
{
    use HasHoneypot;

    public string $hp_url = ''; // must match the configured field_name
}
```

The trait's `mount` method will throw a `LogicException` with a clear message if the property is missing.

---

### Controllers / APIs (Service)

Inject or resolve `HoneypotService` to validate honeypot data submitted with a standard HTML form.

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

public function store(Request $request, HoneypotService $honeypot): RedirectResponse
{
    $honeypot->validate($request->only(
        config('livewire-honeypot.field_name', 'hp_website'),
        'hp_started_at',
        'hp_token',
    ));

    // process form ...

    return redirect()->back()->with('success', 'Sent!');
}
```

To generate the initial honeypot data server-side and pass it to a Blade view:

```php
$hp = app(HoneypotService::class)->generate();
// Returns: ['hp_website' => '', 'hp_started_at' => 1234567890, 'hp_token' => 'abc...']
return view('contact', compact('hp'));
```

Then in your Blade template, use the values as hidden inputs:

```blade
<form method="POST" action="/contact">
    @csrf
    <input type="text"   name="hp_website"    value="{{ $hp['hp_website'] }}"    style="display:none" tabindex="-1" autocomplete="off">
    <input type="hidden" name="hp_started_at" value="{{ $hp['hp_started_at'] }}">
    <input type="hidden" name="hp_token"      value="{{ $hp['hp_token'] }}">

    {{-- your regular fields --}}
</form>
```

#### Overriding the minimum time per-request

Both `validateHoneypot()` (trait) and `HoneypotService::validate()` accept an optional `$minimumSeconds` argument to override the global config for a specific action:

```php
// Trait
$this->validateHoneypot(minimumSeconds: 10);

// Service
$honeypot->validate($data, minimumSeconds: 10);
```

---

## Blade Component

The `<x-honeypot />` component renders three hidden fields and scoped CSS that moves them offscreen:

| Field | Purpose |
|---|---|
| `hp_website` (configurable) | Bait field — must remain empty |
| `hp_started_at` | Unix timestamp of page load |
| `hp_token` | Random token to verify form origin |

The component uses `aria-hidden="true"` and `tabindex="-1"` so it is invisible to screen readers and keyboard navigation.

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=livewire-honeypot-config
```

This creates `config/livewire-honeypot.php`:

```php
return [
    // Minimum seconds between page load and submission (0 = disabled)
    'minimum_fill_seconds' => env('HONEYPOT_MINIMUM_FILL_SECONDS', 5),

    // Name of the honeypot bait field
    'field_name' => env('HONEYPOT_FIELD_NAME', 'hp_website'),

    // Minimum accepted length of the token on validation
    'token_min_length' => env('HONEYPOT_TOKEN_MIN_LENGTH', 10),

    // Length of the token generated on page load
    'token_length' => env('HONEYPOT_TOKEN_LENGTH', 24),
];
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `HONEYPOT_MINIMUM_FILL_SECONDS` | `5` | Seconds required before a submission is accepted |
| `HONEYPOT_FIELD_NAME` | `hp_website` | Name of the bait input field |
| `HONEYPOT_TOKEN_MIN_LENGTH` | `10` | Minimum token length accepted during validation |
| `HONEYPOT_TOKEN_LENGTH` | `24` | Length of the generated token |

> **Note:** `token_length` must be greater than or equal to `token_min_length`. The service provider throws an `InvalidArgumentException` on boot if this constraint is violated.

---

## Translations

English, Dutch (`nl`), and German (`de`) translations are included. Publish them to customize the error messages:

```bash
php artisan vendor:publish --tag=livewire-honeypot-translations
```

This creates files under `lang/vendor/livewire-honeypot/{locale}/validation.php`.

### Available translation keys

| Key | Default (English) | Description |
|---|---|---|
| `spam_detected` | `Spam detected.` | Shown when the bait field is filled |
| `submitted_too_quickly` | `Form submitted too quickly.` | Shown when the time-trap triggers |
| `honeypot_label` | `Website (leave empty)` | Accessible label on the hidden field |
| `invalid_form_data` | `Invalid form data.` | Shown when `hp_started_at` is out of range |

---

## Publishing Views

To customize the `<x-honeypot />` Blade component:

```bash
php artisan vendor:publish --tag=livewire-honeypot-views
```

This copies the component to `resources/views/vendor/livewire-honeypot/components/honeypot.blade.php`.

---

## Recommended Additions

Honeypot protection works best as one layer of a defence-in-depth strategy. Consider pairing it with:

**Rate limiting** on your form route:

```php
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:10,1');
```

**CSRF protection** — always use `@csrf` in non-Livewire forms (Livewire handles this automatically).

---

## How It Works

1. On page load, the trait / service generates a Unix timestamp (`hp_started_at`) and a cryptographically random token (`hp_token`). These are stored as hidden Livewire properties or passed to the view.
2. The Blade component renders these values as hidden inputs alongside the bait field, which is visually positioned offscreen via CSS.
3. On submission, `validateHoneypot()` / `HoneypotService::validate()` checks:
   - The bait field is **empty** (bots usually fill every visible and hidden input).
   - `hp_started_at` falls within the last hour (guards against replayed or stale forms).
   - `hp_token` meets the minimum length (guards against manually crafted requests).
   - Enough time has elapsed since page load (time-trap).
4. Any failure throws a `ValidationException`, which Livewire or Laravel surfaces as a standard validation error.

---

## License

MIT — see [LICENSE](LICENSE).
