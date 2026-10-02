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

## Silent rejection

`isHoneypotCaught()`, `isHoneypotCaughtForModel()`, and `HoneypotService::isCaught()` return a boolean instead of responding, so the component or controller decides how to answer, typically with a fake success. They ignore `spam_responder` and never add validation errors.

Caught tokens are remembered in the cache for one hour, which outlives the form they belong to. Later submissions with the same token are caught whatever their fields contain. Set `HONEYPOT_CAUGHT_CACHE_STORE` (`caught_cache_store`) to use a specific cache store; `null` uses the default store. If the store is unavailable or not defined, the error is reported once per submission through Laravel's exception handler and each submission is checked on its own, so forms keep working.

Unsigned tokens, and tokens with fewer than 8 random characters, are caught but never remembered, so short random values cannot collide between visitors. Any `token_length` of 8 or more is remembered, including the default of 24.

A form older than one hour is usually a visitor who left the tab open, so it is not answered with a fake success. The silent API throws the normal "Invalid form data." validation error instead, whatever `spam_responder` is set to, and does not remember the token. If the expired form also has another problem, such as a filled bait field, it is caught silently as usual. A token that was caught before its form expired stays caught.

A Livewire token is locked, so a bot can only get a new one by loading the page again, which also restarts the waiting time. Plain forms remember the signed token.

The fake success also applies to real users who are caught, such as someone who autofills a form and submits faster than `minimum_fill_seconds`. Their submission is silently dropped. Keep the minimum low, or use `validateHoneypot()` when a visible error is preferable.

## Detection events and logs

Set `HONEYPOT_LOGGING=true` to log detections. `HONEYPOT_LOG_CHANNEL` selects the channel and `HONEYPOT_LOG_LEVEL` defaults to `warning`.

Set `HONEYPOT_LOG_VALUE=true` to also log what was entered in the bait field as `filled_value`, shortened to 200 characters. This helps tell bots apart from browser or password manager autofill. It is off by default because an autofilled value can be a real visitor's name, email address, or other personal data.

For custom handling, listen for `Blendbyte\LivewireHoneypot\Events\HoneypotDetected`. Its properties are `reason`, `fieldName`, `ipAddress`, `userAgent`, `component` (null outside Livewire), and `filledValue`. `filledValue` is the full submitted bait value for `honeypot_filled` and null for every other reason. Arrays and other non-string values are JSON-encoded. Treat it as untrusted input that may contain personal data. Reasons are `honeypot_filled`, `submitted_too_quickly`, `invalid_form_data`, `js_verification_failed`, and `previously_detected`. The last one is used by the silent rejection API when a token that was already caught is submitted again.

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
