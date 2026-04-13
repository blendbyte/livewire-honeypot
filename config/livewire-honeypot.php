<?php

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

    'minimum_fill_seconds' => (int) env('HONEYPOT_MINIMUM_FILL_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Honeypot Field Name
    |--------------------------------------------------------------------------
    |
    | The name of the honeypot field. Bots often fill in all fields,
    | but this field should remain empty for legitimate users.
    |
    */

    'field_name' => env('HONEYPOT_FIELD_NAME', 'hp_website'),

    /*
    |--------------------------------------------------------------------------
    | Token Minimum Length
    |--------------------------------------------------------------------------
    |
    | The minimum length for the honeypot token. This adds an extra
    | layer of validation to ensure the form was properly initialized.
    |
    */

    'token_min_length' => (int) env('HONEYPOT_TOKEN_MIN_LENGTH', 10),

    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    |
    | The length of the generated honeypot token.
    |
    */

    'token_length' => (int) env('HONEYPOT_TOKEN_LENGTH', 24),

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

    'randomize_field_name' => (bool) env('HONEYPOT_RANDOMIZE_FIELD_NAME', false),

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
        'enabled' => (bool) env('HONEYPOT_LOGGING', false),
        'channel' => env('HONEYPOT_LOG_CHANNEL', null),
        'level'   => env('HONEYPOT_LOG_LEVEL', 'warning'),
    ],

];
