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
    | Maximum Fill Time (seconds)
    |--------------------------------------------------------------------------
    |
    | Forms loaded longer ago than this are rejected with a message asking
    | the visitor to reload, so an old token cannot be replayed forever.
    | Applies to Livewire and plain forms, and must be greater than the
    | minimum fill time. Default: one hour. Set to 0 to disable expiry.
    |
    */

    'maximum_fill_seconds' => (int) env('HONEYPOT_MAXIMUM_FILL_SECONDS', $defaults['maximum_fill_seconds']),

    /*
    |--------------------------------------------------------------------------
    | Honeypot Field Name
    |--------------------------------------------------------------------------
    |
    | The bait field that must stay empty: the Livewire property bound by
    | <x-honeypot />, and the key that plain-form errors are reported under.
    | With randomize_field_name enabled, the HTML name is generated instead.
    | A custom value needs a matching public property on Livewire components.
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
    | When enabled, <x-honeypot /> in a Livewire component renders the bait
    | with a name derived from the form token (e.g. "referral_3f9a") instead
    | of the configured field_name. The name changes with every form, does
    | not look like a honeypot, and avoids names that browsers and password
    | managers autofill. Only the HTML name attribute changes; the Livewire
    | wire:model binding still targets field_name. Set to false to render
    | field_name as the HTML name.
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
    | Set include_value to true to also log what was typed into the bait
    | field, shortened to 200 characters. This helps tell bots apart from
    | browser autofill, but autofilled values can be a real visitor's
    | personal data, so it is off by default.
    |
    */

    'logging' => [
        'enabled' => (bool) env('HONEYPOT_LOGGING', $defaults['logging']['enabled']),
        'channel' => env('HONEYPOT_LOG_CHANNEL', $defaults['logging']['channel']),
        'level'   => env('HONEYPOT_LOG_LEVEL', $defaults['logging']['level']),
        'include_value' => (bool) env('HONEYPOT_LOG_VALUE', $defaults['logging']['include_value']),
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

    /*
    |--------------------------------------------------------------------------
    | Caught Token Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store used by isHoneypotCaught() and isCaught() to remember
    | caught form tokens until their form expires, or for one day when expiry
    | is disabled. Leave null to use
    | the default cache store. If the store is unavailable or not defined,
    | the error is reported and forms keep working without remembered tokens.
    |
    */

    'caught_cache_store' => env('HONEYPOT_CAUGHT_CACHE_STORE', $defaults['caught_cache_store']),

];
