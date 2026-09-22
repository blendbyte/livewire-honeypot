# Advanced options

## Content Security Policy

The component uses Laravel Vite's CSP nonce automatically, or accepts an explicit nonce:

```blade
<x-honeypot :nonce="$cspNonce" />
```

Use the same nonce in your response's `style-src` policy, or `style-src-elem` when specified. The prop covers the honeypot's inline stylesheet only. Configure Livewire's own scripts and styles as well, and enable `livewire.csp_safe` when your policy prohibits `unsafe-eval`.

For external stylesheets only, publish the view, move its `.hp-field` CSS into your application stylesheet, and remove the inline style block.

## Errors and responders

The default responder shows a validation error. The component displays the first relevant error outside its hidden wrapper. To display an error stored under another key:

```blade
<x-honeypot wire:model="contact.trap" error-key="form_spam" />
```

`error-key` selects the displayed message; it does not change the validation target.

The `spam_responder` config accepts `ValidationExceptionResponder`, `AbortResponder` (403), or `RedirectResponder` (redirect back), all under `Blendbyte\LivewireHoneypot\Responders`. Custom responders implement `Blendbyte\LivewireHoneypot\Contracts\SpamResponder::respond(string $fieldName, string $message): never` and must terminate execution.

Configured responders handle bait, metadata, expiry, timing, and JS-verification failures in both Livewire and plain forms. Custom responders receive the bait field name (or custom model path) and a message; metadata failures use `invalid_form_data`. The built-in default preserves the existing validation error keys and rule details.

Honeypot checks use a separate validator, leaving application `withValidator()` and `prepareForValidation()` hooks for your normal form validation. Successful checks clear only honeypot errors.

`RedirectResponder` stops the rejected action and uses Livewire's redirect effect for component requests. Plain forms receive a normal HTTP 302 redirect.

## Detection events and logs

Set `HONEYPOT_LOGGING=true` to log detections. `HONEYPOT_LOG_CHANNEL` selects the channel and `HONEYPOT_LOG_LEVEL` defaults to `warning`.

For custom handling, listen for `Blendbyte\LivewireHoneypot\Events\HoneypotDetected`. Its properties are `reason`, `fieldName`, `ipAddress`, `userAgent`, and `component` (null outside Livewire). Reasons are `honeypot_filled`, `submitted_too_quickly`, `invalid_form_data`, and `js_verification_failed`.

## Optional JavaScript verification

`HONEYPOT_JS_VERIFICATION=true` requires a nonempty marker populated by Alpine. It is an additional heuristic, not proof of a human visitor.

You can also enable or disable verification per component with `honeypotConfig()`. The view uses that same setting. After `resetHoneypot()`, the JS input is replaced and Alpine fills a fresh marker, so the form can be submitted again without reloading.

## Views and translations

Twelve locales are included. Publish only what you need to customize:

```bash
php artisan vendor:publish --tag=livewire-honeypot-views
php artisan vendor:publish --tag=livewire-honeypot-translations
```

The view is copied to `resources/views/vendor/livewire-honeypot/components/honeypot.blade.php`; translations go to `lang/vendor/livewire-honeypot`. Published copies need manual updates when the package changes.

[Back to the README](../README.md)
