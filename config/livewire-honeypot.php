<?php

use Blendbyte\LivewireHoneypot\HoneypotConfig;

$defaults = HoneypotConfig::DEFAULTS;

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Fill Time (seconds)
    |--------------------------------------------------------------------------
    |
    | The minimum time in seconds that must pass between form load and
    | submission. This helps prevent automated bot submissions.
    | Set to 0 to disable the time check.
    |
    */

    'minimum_fill_seconds' => (int) env('HONEYPOT_MINIMUM_FILL_SECONDS', $defaults['minimum_fill_seconds']),

    /*
    |--------------------------------------------------------------------------
    | Honeypot Field Name
    |--------------------------------------------------------------------------
    |
    | The name of the honeypot field. Bots often fill in all fields,
    | but this field should remain empty for legitimate users.
    |
    */

    'field_name' => env('HONEYPOT_FIELD_NAME', $defaults['field_name']),

    /*
    |--------------------------------------------------------------------------
    | Token Minimum Length
    |--------------------------------------------------------------------------
    |
    | The minimum length for the locked Livewire token, or the random nonce
    | inside a signed plain-form token. Plain forms also require a valid signature.
    |
    */

    'token_min_length' => (int) env('HONEYPOT_TOKEN_MIN_LENGTH', $defaults['token_min_length']),

    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    |
    | The length of the generated Livewire token, or the random nonce inside
    | a signed plain-form token. The full signed token is longer than this value.
    |
    */

    'token_length' => (int) env('HONEYPOT_TOKEN_LENGTH', $defaults['token_length']),

    /*
    |--------------------------------------------------------------------------
    | Randomize Field Name
    |--------------------------------------------------------------------------
    |
    | When enabled, the honeypot bait field will be rendered in HTML with a
    | random name (e.g. "hp_a3f7c2") instead of the configured field_name.
    | This defeats bots that skip inputs by recognising known honeypot names.
    | The Livewire wire:model binding is unaffected — only the HTML name
    | attribute is randomised. Set to true to enable.
    |
    */

    'randomize_field_name' => (bool) env('HONEYPOT_RANDOMIZE_FIELD_NAME', $defaults['randomize_field_name']),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, a structured warning is written to your Laravel log
    | whenever a spam submission is detected. Set a channel to route logs
    | to a specific logging channel (e.g. "slack", "daily"); leave null to
    | use the default channel. The level must be a valid PSR-3 level string
    | (debug, info, notice, warning, error, critical, alert, emergency).
    |
    */

    'logging' => [
        'enabled' => (bool) env('HONEYPOT_LOGGING', $defaults['logging']['enabled']),
        'channel' => env('HONEYPOT_LOG_CHANNEL', $defaults['logging']['channel']),
        'level'   => env('HONEYPOT_LOG_LEVEL', $defaults['logging']['level']),
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Responder
    |--------------------------------------------------------------------------
    |
    | The class to use when spam is detected. Must implement
    | Blendbyte\LivewireHoneypot\Contracts\SpamResponder.
    |
    | Built-in options:
    |   - ValidationExceptionResponder::class  (default) field-level error
    |   - AbortResponder::class                abort(403)
    |   - RedirectResponder::class             redirect()->back() silently
    |
    */

    'spam_responder' => $defaults['spam_responder'],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Fill Verification
    |--------------------------------------------------------------------------
    |
    | When enabled, the Blade component renders a hidden field that is only
    | populated by Alpine.js (bundled with Livewire 4). Headless bots or
    | form scrapers that submit without executing JavaScript will fail this
    | check because the field will be empty.
    |
    | This is an opt-in layer on top of the existing honeypot and time-trap.
    | Set to true to enable.
    |
    */

    'require_js_verification' => (bool) env('HONEYPOT_JS_VERIFICATION', $defaults['require_js_verification']),

];
