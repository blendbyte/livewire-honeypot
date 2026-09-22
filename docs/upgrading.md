# Upgrading existing integrations

Review these changes if you used the package before locked Livewire metadata and signed plain-form tokens were introduced.

- **Published Livewire views:** remove the `hp_started_at` and `hp_token` inputs and their bindings. Those properties are now locked. Compare your copy with the [package view](../resources/views/components/honeypot.blade.php) for custom bindings, visible errors, autofill hints, CSP nonces, and JS verification. The JS field now reads the component's settings and uses a `wire:key` that changes on reset so its initializer runs again.
- **Plain forms:** use `HoneypotService::generate()` for signed tokens. Old unsigned tokens are rejected, so forms opened before deployment need reloading. Do not truncate tokens to `token_length`; that setting controls only the nonce inside a plain-form token.
- **Custom bait bindings:** match the Blade `wire:model` path with `validateHoneypotForModel()` and `resetHoneypotForModel()`. The original validation and reset method signatures remain available.
- **Livewire form objects:** keep `HasHoneypot` on the component. Direct use on an uninitialized form object now throws a developer-facing `LogicException`.
- **Published translations:** update `honeypot_label` to the neutral label from the package if you want the autofill changes.
- **Tests:** use time travel instead of setting locked metadata. `HoneypotService::fake()` bypasses validation but does not unlock those properties.

The configuration accessor refactor requires no application changes. Existing published config files continue to work.

[Back to the README](../README.md)
