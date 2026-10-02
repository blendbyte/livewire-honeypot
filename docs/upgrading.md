# Upgrading existing integrations

## Since 2.1.0

Existing method signatures keep working. Review these changes if you select the bait input by name, style or publish the view, render `<x-honeypot />` outside a `HasHoneypot` component, or rely on the expired-form message.

- **Generated bait names:** `<x-honeypot />` in a `HasHoneypot` component now renders the bait with a name derived from the form token, such as `referral_3f9a`, instead of `hp_website`. The Livewire binding and error keys are unchanged. Update browser tests or scripts that select the input by name, for example select it by its `wire:model` attribute instead. Set `HONEYPOT_RANDOMIZE_FIELD_NAME=false` to keep the old name. Names from the old randomization (`hp_` plus six characters) are no longer generated.
- **Deferred bait binding:** the default bait binding is now `wire:model` instead of `wire:model.lazy`, so filling the bait no longer sends a request until the form is submitted. Detection is unchanged. Update browser tests that select `input[wire\:model.lazy=...]`. Explicit `wire:model` attributes you pass to `<x-honeypot />` are used as before.
- **Hidden wrapper:** the `hp-field` class is gone. The wrapper now uses a class derived from `APP_KEY` and screen-reader-only CSS instead of moving it off screen. Update any CSS or published views that targeted `.hp-field`; `.hp-error` is unchanged. A published view keeps its old name and wrapper until you republish it or compare it with the [package view](../resources/views/components/honeypot.blade.php).
- **Component outside Livewire:** `<x-honeypot />` outside a `HasHoneypot` component now renders a plain form: the bait, a signed `hp_token`, and no `wire:model` bindings. This includes Livewire components that do not use the trait, where the old bindings could not be validated anyway. Hand-written plain-form markup keeps working; see [plain forms](plain-forms.md) to switch to the component.
- **Expired forms:** an expired form now shows "This form has expired. Please reload the page and try again." (translation key `form_expired`) instead of "Invalid form data.", and custom responders receive that message too. `HoneypotDetected` and the detection log now report these with the reason `form_expired` instead of `invalid_form_data`, so update listeners or log filters that should still include expired forms. Published translations fall back to the package text until you add the key.
- **Fake mode:** `HoneypotService::fake()` is now stored in the application container, so it ends with each test's application and no longer leaks into later tests. `afterEach(fn () => HoneypotService::resetFake())` is no longer needed but still works. Call `fake()` once the application has booted, such as in `beforeEach()`, and do not rely on the removed `HoneypotService::$fake` property.
- **Deprecated `token_min_length`:** the setting (`HONEYPOT_TOKEN_MIN_LENGTH`) is ignored and no longer in the package config. Livewire tokens are locked and plain-form tokens are signed, so a minimum length added no protection; it only rejected forms after the setting was raised. You can remove it from a published config and from `honeypotConfig()`; leaving it in does no harm. It will be removed in the next major version.
- **Form expiry:** the one-hour limit is now `maximum_fill_seconds` (`HONEYPOT_MAXIMUM_FILL_SECONDS`), also available in `honeypotConfig()`. The default is unchanged. It must be `0` or greater than `minimum_fill_seconds`; other values now throw at boot or when a component initializes.

## Earlier changes

Review these changes if you used the package before locked Livewire metadata and signed plain-form tokens were introduced.

- **Published Livewire views:** remove the `hp_started_at` and `hp_token` inputs and their bindings. Those properties are now locked. Compare your copy with the [package view](../resources/views/components/honeypot.blade.php) for custom bindings, visible errors, autofill hints, CSP nonces, and JS verification. The JS field now reads the component's settings and uses a `wire:key` that changes on reset so its initializer runs again.
- **Plain forms:** use `<x-honeypot />` or `HoneypotService::generate()` for signed tokens. Old unsigned tokens are rejected, so forms opened before deployment need reloading. Do not truncate tokens to `token_length`; that setting controls only the nonce inside a plain-form token.
- **Custom bait bindings:** match the Blade `wire:model` path with `validateHoneypotForModel()` and `resetHoneypotForModel()`. The original validation and reset method signatures remain available.
- **Livewire form objects:** keep `HasHoneypot` on the component. Direct use on an uninitialized form object now throws a developer-facing `LogicException`.
- **Published translations:** update `honeypot_label` to the neutral label from the package if you want the autofill changes.
- **Tests:** use time travel instead of setting locked metadata. `HoneypotService::fake()` bypasses validation but does not unlock those properties.
- **Custom responders:** bait and metadata failures now honor your selected responder consistently. An abort responder returns 403 for invalid or expired tokens too; the built-in default keeps its existing validation errors.
- **Validation hooks:** honeypot checks run independently of application `withValidator()` and `prepareForValidation()` hooks. Keep those hooks for your normal form validation.
- **Redirect tests:** Livewire rejections using `RedirectResponder` now return a redirect effect in a 200 response. Use `assertRedirect()` in component tests; plain forms still return HTTP 302.
- **Component configuration:** the default Blade binding now follows component-level `field_name` overrides. Republish or update customized views to receive this fix. Explicit `wire:model` bindings still take precedence.
- **Token lengths:** `token_length` must be positive. Invalid component overrides now throw during initialization or reset, instead of producing tokens that fail submission.

The configuration accessor refactor requires no application changes. Existing published config files continue to work.

[Back to the README](../README.md)
