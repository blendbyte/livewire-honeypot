<?php

namespace Blendbyte\LivewireHoneypot;

use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Illuminate\Support\Arr;

/** @internal */
final class HoneypotConfig
{
    public const array DEFAULTS = [
        'minimum_fill_seconds' => 5,
        'field_name' => 'hp_website',
        'token_min_length' => 10,
        'token_length' => 24,
        'randomize_field_name' => false,
        'logging' => [
            'enabled' => false,
            'channel' => null,
            'level' => 'warning',
        ],
        'spam_responder' => ValidationExceptionResponder::class,
        'require_js_verification' => false,
    ];

    /**
     * Read current package settings, including defaults for missing nested keys.
     * Explicit null values are preserved by Laravel's config repository.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return config('livewire-honeypot.' . $key, $default ?? Arr::get(self::DEFAULTS, $key));
    }

    public static function validateTokenLengths(int $length, int $minimum): void
    {
        if ($length < 1 || $minimum < 1 || $length < $minimum) {
            throw new \InvalidArgumentException(
                "livewire-honeypot: token_length ({$length}) and token_min_length ({$minimum}) must be positive, " .
                'and token_length must be greater than or equal to token_min_length. ' .
                'Check your livewire-honeypot config and honeypotConfig() overrides.'
            );
        }
    }
}
