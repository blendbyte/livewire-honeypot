<img width="2560" height="840" alt="livewire-honeypot-banner" src="https://github.com/user-attachments/assets/bfa67e85-1864-4cb5-8df5-cf6880850f2f" />

# livewire-honeypot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blendbyte/livewire-honeypot.svg?style=flat-square)](https://packagist.org/packages/blendbyte/livewire-honeypot)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/blendbyte/livewire-honeypot/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-787cb5?style=flat-square)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20?style=flat-square)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-fb70a9?style=flat-square)](https://livewire.laravel.com)

Lightweight **honeypot + time-trap** protection for **Livewire 4** (Laravel 13). Blocks simple bots without CAPTCHAs — privacy-friendly, zero external requests, and invisible to real users.

Forked from [darvis/livewire-honeypot](https://github.com/darvis/livewire-honeypot).

## Features

- **Honeypot bait field** — hidden input that bots fill in, legitimate users never see (`present|size:0`)
- **Time-trap** — enforces a configurable minimum time between page load and submission
- **Token validation** — cryptographically random token verified on each submission
- **Randomized field name** — optionally render the bait field with a random HTML `name` to defeat name-aware bots
- **JS fill verification** — opt-in hidden field populated only by Alpine.js; blocks headless bots that skip JavaScript
- **Livewire Trait** — drop-in protection for any Livewire component
- **Per-component config** — override any honeypot setting per component via `honeypotConfig()`
- **Controller / API Service** — use outside of Livewire for standard form controllers
- **Blade component** — `<x-honeypot />` renders all hidden fields in one line
- **Configurable spam responder** — choose how spam is handled: validation error, 403 abort, or silent redirect; or provide your own
- **`HoneypotDetected` event** — fired on every spam detection, carrying IP, user-agent, reason, and component
- **Structured logging** — optional log entry on every detection, routed to any Laravel logging channel
- **Testing support** — `HoneypotService::fake()` bypasses validation in tests
- **Multilingual** — 12 translations included
- **Zero extra dependencies** — only requires Livewire 4 / Laravel 13

## Requirements

| Dependency | Version |
|------------|---------|
| PHP        | `^8.5`  |
| Laravel    | `^13.0` |
| Livewire   | `^4.0`  |

## Installation

```bash
composer require blendbyte/livewire-honeypot
```

The service provider is auto-discovered. No manual registration required.

## Quick Start

Add the `HasHoneypot` trait to your Livewire component and call `validateHoneypot()` in your submit action:

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

That's it — bots get blocked, real users never notice.

## How It Works

1. On page load, the trait/service generates a Unix timestamp (`hp_started_at`) and a cryptographically random token (`hp_token`). These are stored as hidden Livewire properties or passed to the view.
2. The Blade component renders these values as hidden inputs alongside the bait field, which is visually positioned offscreen via CSS. If `randomize_field_name` is enabled, the bait field's HTML `name` is randomised each page load.
3. On submission, `validateHoneypot()` / `HoneypotService::validate()` checks:
   - The bait field is **empty** (bots usually fill every visible and hidden input).
   - `hp_started_at` falls within the last hour (guards against replayed or stale forms).
   - `hp_token` meets the minimum length (guards against manually crafted requests).
   - If `require_js_verification` is enabled, `hp_js` must be **non-empty** — Alpine.js populates this field on page load; bots without JavaScript execution leave it empty.
   - Enough time has elapsed since page load (time-trap).
4. Any failure fires a `HoneypotDetected` event (and optionally writes a log entry), then delegates to the configured `SpamResponder`.

## Usage

### Custom field name

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

### Per-component configuration

Override `honeypotConfig()` to customise any honeypot setting for a specific component without touching the global config:

```php
class RegistrationForm extends Component
{
    use HasHoneypot;

    protected function honeypotConfig(): array
    {
        return [
            'minimum_fill_seconds' => 10,
            'token_length'         => 32,
            'randomize_field_name' => true,
        ];
    }
}
```

Supported keys: `minimum_fill_seconds`, `field_name`, `token_length`, `token_min_length`, `randomize_field_name`, `require_js_verification`.

Component-level values take precedence over the global config; any key not present falls back to the global config.

### Randomized field name

When `randomize_field_name` is enabled (globally or per-component), the bait field is rendered in HTML with a random `name` attribute (e.g. `hp_a3f7c2`) instead of the configured `field_name`. This defeats bots that skip inputs by recognising known honeypot names.

Pass `$hp_field_name` (automatically kept in sync by the trait) to the Blade component:

```blade
<x-honeypot :field-name="$hp_field_name" />
```

The `wire:model` binding is unaffected — only the rendered HTML `name` attribute is randomised.

### JavaScript fill verification

When `require_js_verification` is enabled, the Blade component renders an additional hidden field (`hp_js`) that is populated client-side by Alpine.js via an `x-init` directive. Bots and headless scrapers that submit forms without executing JavaScript will leave this field empty, and the submission will be rejected.

Enable it globally:

```env
HONEYPOT_JS_VERIFICATION=true
```

Or per-component via `honeypotConfig()`:

```php
protected function honeypotConfig(): array
{
    return ['require_js_verification' => true];
}
```

No changes to your template are needed — `<x-honeypot />` automatically renders the `hp_js` field when the option is enabled.

> **Note:** This check requires Alpine.js. Livewire 4 bundles Alpine.js automatically, so no extra setup is needed for Livewire components. For controller/API usage, ensure Alpine.js is loaded on the page.

### Overriding the minimum time per-action

`validateHoneypot()` accepts an optional `$minimumSeconds` argument to override the config for a specific submit action:

```php
public function submitQuickPoll(): void
{
    $this->validateHoneypot(minimumSeconds: 2);
    // ...
}
```

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

`HoneypotService::validate()` also accepts an optional `$minimumSeconds` argument:

```php
$honeypot->validate($data, minimumSeconds: 10);
```

## Blade Component

The `<x-honeypot />` component renders hidden fields and scoped CSS that moves them offscreen:

| Field          | Purpose                             | Always rendered                            |
|----------------|-------------------------------------|--------------------------------------------|
| `hp_website` (configurable)  | Bait field — must remain empty      | Yes                                        |
| `hp_started_at`| Unix timestamp of page load         | Yes                                        |
| `hp_token`     | Random token to verify form origin  | Yes                                        |
| `hp_js`        | Populated by Alpine.js on page load | Only when `require_js_verification = true`  |

The component uses `aria-hidden="true"` and `tabindex="-1"` so it is invisible to screen readers and keyboard navigation.

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

    // Randomise the HTML name attribute of the bait field on each page load
    'randomize_field_name' => env('HONEYPOT_RANDOMIZE_FIELD_NAME', false),

    // Structured logging when spam is detected
    'logging' => [
        'enabled' => env('HONEYPOT_LOGGING', false),
        'channel' => env('HONEYPOT_LOG_CHANNEL', null), // null = default channel
        'level'   => env('HONEYPOT_LOG_LEVEL', 'warning'),
    ],

    // How to respond when spam is detected
    // Must implement Blendbyte\LivewireHoneypot\Contracts\SpamResponder
    'spam_responder' => \Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder::class,

    // Require a hidden field populated by Alpine.js (opt-in JS verification)
    'require_js_verification' => env('HONEYPOT_JS_VERIFICATION', false),
];
```

### Environment variables

| Variable                        | Default      | Description                                                     |
|---------------------------------|--------------|-----------------------------------------------------------------|
| `HONEYPOT_MINIMUM_FILL_SECONDS` | `5`          | Seconds required before a submission is accepted                |
| `HONEYPOT_FIELD_NAME`           | `hp_website` | Name of the bait input field                                    |
| `HONEYPOT_TOKEN_MIN_LENGTH`     | `10`         | Minimum token length accepted during validation                 |
| `HONEYPOT_TOKEN_LENGTH`         | `24`         | Length of the generated token                                   |
| `HONEYPOT_RANDOMIZE_FIELD_NAME` | `false`      | Randomise the HTML `name` of the bait field each page load      |
| `HONEYPOT_LOGGING`              | `false`      | Enable structured log entries on spam detection                 |
| `HONEYPOT_LOG_CHANNEL`          | *(default)*  | Laravel logging channel to write to (`null` = app default)      |
| `HONEYPOT_LOG_LEVEL`            | `warning`    | PSR-3 log level (`debug`, `info`, `warning`, `error`, …)        |
| `HONEYPOT_JS_VERIFICATION`      | `false`      | Require the `hp_js` field to be populated by Alpine.js          |

> **Note:** `token_length` must be greater than or equal to `token_min_length`. The service provider throws an `InvalidArgumentException` on boot if this constraint is violated.

## Spam Responders

When spam is detected you can control what happens via the `spam_responder` config key. Three responders are built in:

| Class                            | Behaviour                                                                       |
|----------------------------------|---------------------------------------------------------------------------------|
| `ValidationExceptionResponder` (default) | Throws a `ValidationException` — Livewire surfaces it as a field-level error |
| `AbortResponder`                 | Calls `abort(403)` — hard rejection with a 403 Forbidden response              |
| `RedirectResponder`              | Silently redirects the user back — the form appears to do nothing              |

Change the responder globally in `config/livewire-honeypot.php`:

```php
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;

'spam_responder' => AbortResponder::class,
```

### Custom responder

Implement the `SpamResponder` contract to define your own behaviour:

```php
use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;

class SilentIgnoreResponder implements SpamResponder
{
    public function respond(string $fieldName, string $message): never
    {
        logger()->info('Honeypot triggered silently', ['field' => $fieldName]);
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['status' => 'ok'])
        );
    }
}
```

Register it in `config/livewire-honeypot.php`:

```php
'spam_responder' => \App\Http\Honeypot\SilentIgnoreResponder::class,
```

Or bind it in a service provider for maximum flexibility:

```php
$this->app->bind(
    \Blendbyte\LivewireHoneypot\Contracts\SpamResponder::class,
    fn () => new SilentIgnoreResponder(/* deps */),
);
```

## Events

Every spam detection fires a `HoneypotDetected` event regardless of which responder is configured. Listen to it for custom alerting, rate-limiting, or analytics:

```php
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;

Event::listen(HoneypotDetected::class, function (HoneypotDetected $event) {
    // $event->reason      — "honeypot_filled" | "submitted_too_quickly" | "invalid_form_data" | "js_verification_failed"
    // $event->fieldName   — the bait field name (e.g. "hp_website")
    // $event->ipAddress   — IP address of the request
    // $event->userAgent   — user-agent string of the request
    // $event->component   — FQCN of the Livewire component, or null for controller usage

    logger()->critical('Bot attempt detected', [
        'ip'     => $event->ipAddress,
        'reason' => $event->reason,
    ]);
});
```

## Logging

Enable structured log entries so every spam detection is written to your Laravel log automatically — no manual event listener needed:

```env
HONEYPOT_LOGGING=true
HONEYPOT_LOG_CHANNEL=slack
HONEYPOT_LOG_LEVEL=warning
```

Each log entry includes `reason`, `field_name`, `ip`, `user_agent`, and `component`:

```
[warning] Honeypot triggered {"reason":"honeypot_filled","field_name":"hp_website","ip":"1.2.3.4","user_agent":"curl/8.0","component":null}
```

## Testing

Use `HoneypotService::fake()` to bypass all honeypot validation in tests:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

beforeEach(fn () => HoneypotService::fake());
afterEach(fn () => HoneypotService::resetFake());

it('submits the contact form', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'Alice')
        ->set('email', 'alice@example.com')
        ->set('message', 'Hello there!')
        ->call('submit')
        ->assertHasNoErrors();
});
```

When fake mode is active, `validateHoneypot()` (trait) and `HoneypotService::validate()` (service) both return immediately without checking any fields.

## Translations

12 translations are included out of the box: English, Dutch, German, Spanish, French, Portuguese, Italian, Russian, Polish, Japanese, Chinese Simplified, and Chinese Traditional.

Publish them to customize error messages:

```bash
php artisan vendor:publish --tag=livewire-honeypot-translations
```

### Available translation keys

| Key                      | Default (English)              | Description                              |
|--------------------------|-------------------------------|------------------------------------------|
| `spam_detected`          | `Spam detected.`             | Shown when the bait field is filled      |
| `submitted_too_quickly`  | `Form submitted too quickly.` | Shown when the time-trap triggers        |
| `honeypot_label`         | `Website (leave empty)`      | Accessible label on the hidden field     |
| `invalid_form_data`      | `Invalid form data.`         | Shown when `hp_started_at` is out of range |
| `js_verification_failed` | `JavaScript verification failed.` | Shown when `hp_js` is empty         |

## Publishing Views

To customize the `<x-honeypot />` Blade component:

```bash
php artisan vendor:publish --tag=livewire-honeypot-views
```

This copies the component to `resources/views/vendor/livewire-honeypot/components/honeypot.blade.php`.

## Recommended Additions

Honeypot protection works best as one layer of a defence-in-depth strategy. Consider pairing it with:

**Rate limiting** on your form route:

```php
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:10,1');
```

**CSRF protection** — always use `@csrf` in non-Livewire forms (Livewire handles this automatically).

---

## Maintained by Blendbyte

<a href="https://www.blendbyte.com">
  <img src="https://avatars.githubusercontent.com/u/69378377?s=200&v=4" alt="Blendbyte" width="80" align="left" style="margin-right: 16px;">
</a>

This project is maintained by **[Blendbyte](https://www.blendbyte.com)** — a team of engineers with 20+ years of experience building cloud infrastructure, web applications, and developer tools. We use these packages in production ourselves and actively contribute to the open source ecosystem we rely on every day. Issues and PRs are always welcome.

🌐 [blendbyte.com](https://www.blendbyte.com) · 📧 [hello@blendbyte.com](mailto:hello@blendbyte.com)

<br clear="left">
