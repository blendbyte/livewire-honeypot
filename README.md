# livewire-honeypot

Lightweight **honeypot + time‑trap** protection for **Livewire 4** (Laravel 13).  
Blocks simple bots without CAPTCHAs, privacy‑friendly and unobtrusive.

Forked from [darvis/livewire-honeypot](https://github.com/darvis/livewire-honeypot)

## Features
- 🪤 Honeypot bait field (`present|size:0`)
- ⏱️ Time‑trap (minimum fill time, default 5 seconds)
- 🧩 Works as **Trait** for Livewire and as **Service** for controllers/APIs
- 🧱 Blade component `<x-honeypot />` for easy inclusion
- 🌍 Multilingual (English, Dutch & German included)
- ⚙️ Fully configurable via config file
- 🔌 Zero dependencies beyond Livewire 4 / Laravel 13

## Installation

```bash
composer require blendbyte/livewire-honeypot
```

(For local development, you can add a `path` repository in your app's `composer.json`.)

## Usage — Livewire (Trait)

1) In your Livewire component:

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
        $this->validate([
            'name' => 'required|string|min:2',
            'email' => 'required|email',
            'message' => 'required|string|min:10',
        ]);

        $this->validateHoneypot();

        // process form ...

        $this->reset(['name','email','message']);
        $this->resetHoneypot();
    }
}
```

2) In your Blade (or Flux) view, add the component (place anywhere inside the form):

```blade
<x-honeypot />
```

## Usage — Controller / API (Service)

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

public function store(Request $request, HoneypotService $honeypot)
{
    $honeypot->validate($request->only('hp_website', 'hp_started_at', 'hp_token'));
    // process form ...
}
```

To generate fields server‑side (non‑Livewire forms):

```php
$hp = app(Blendbyte\LivewireHoneypot\Services\HoneypotService::class)->generate();
// pass $hp to your view to prefill hidden inputs
```

## Configuration

Publish the config file to customize settings:

```bash
php artisan vendor:publish --tag=livewire-honeypot-config
```

Available options in `config/livewire-honeypot.php`:

- **`minimum_fill_seconds`** - Minimum time (in seconds) before form submission (default: `5`)
- **`field_name`** - Name of the honeypot field (default: `hp_website`)
- **`token_min_length`** - Minimum token length for validation (default: `10`)
- **`token_length`** - Length of generated token (default: `24`)

All settings can also be configured via environment variables:

```env
HONEYPOT_MINIMUM_FILL_SECONDS=5
HONEYPOT_FIELD_NAME=hp_website
HONEYPOT_TOKEN_MIN_LENGTH=10
HONEYPOT_TOKEN_LENGTH=24
```

## Translations

The package includes English, Dutch and German translations. Publish them to customize error messages:

```bash
php artisan vendor:publish --tag=livewire-honeypot-translations
```

Available translation keys in `resources/lang/vendor/livewire-honeypot/{locale}/validation.php`:

- `spam_detected` - Error when honeypot field is filled
- `submitted_too_quickly` - Error when form is submitted too fast
- `honeypot_label` - Label text for the honeypot field

## Publishing views (optional)

Customize the honeypot component:

```bash
php artisan vendor:publish --tag=livewire-honeypot-views
```

## Throttling (recommended)
Add request rate‑limiting on your form route:

```php
Route::get('/contact', \App\Livewire\ContactForm::class)->middleware('throttle:10,1');
```
