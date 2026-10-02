# Configuration

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

The spam responder is chosen in the [configuration file](../config/livewire-honeypot.php), which you can publish:

```bash
php artisan vendor:publish --tag=livewire-honeypot-config
```

## Per component and per action

Override settings for one component with `honeypotConfig()`:

```php
protected function honeypotConfig(): array
{
    return ['minimum_fill_seconds' => 10];
}
```

Supported keys are `minimum_fill_seconds`, `maximum_fill_seconds`, `field_name`, `token_length`, `randomize_field_name`, and `require_js_verification`. For a single action, use `$this->validateHoneypot(minimumSeconds: 2)`.

## Custom bait bindings

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

## Generated HTML names

The bait's HTML name is generated from the form's token, so it changes with every form, does not look like a honeypot, and avoids names that browsers and password managers autofill. Only the HTML name changes: the Livewire binding still targets `field_name` or your custom `wire:model` path. Password-manager ignore hints are included as well, but autofill behavior varies by browser and extension.

To keep a fixed name, set `HONEYPOT_RANDOMIZE_FIELD_NAME=false` or pass one with `<x-honeypot field-name="..." />`.

[Back to the README](../README.md)
