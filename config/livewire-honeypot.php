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

];
