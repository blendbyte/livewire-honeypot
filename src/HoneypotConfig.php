<?php

namespace Blendbyte\LivewireHoneypot;

use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Illuminate\Support\Arr;

/** @internal */
final class HoneypotConfig
{
    public const array DEFAULTS = [
        'minimum_fill_seconds' => 5,
        'maximum_fill_seconds' => 3600,
        'field_name' => 'hp_website',
        'token_min_length' => 10,
        'token_length' => 24,
        'randomize_field_name' => true,
        'logging' => [
            'enabled' => false,
            'channel' => null,
            'level' => 'warning',
            'include_value' => false,
        ],
        'spam_responder' => ValidationExceptionResponder::class,
        'require_js_verification' => false,
        'caught_cache_store' => null,
    ];

    /**
     * Read current package settings, including defaults for missing nested keys.
     * Explicit null values are preserved by Laravel's config repository.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return config('livewire-honeypot.' . $key, $default ?? Arr::get(self::DEFAULTS, $key));
    }

    public static function validateFillSeconds(int $minimum, int $maximum): void
    {
        if ($maximum < 0 || ($maximum > 0 && $maximum <= $minimum)) {
            throw new \InvalidArgumentException(
                "livewire-honeypot: maximum_fill_seconds ({$maximum}) must be 0 to disable form expiry, " .
                "or greater than minimum_fill_seconds ({$minimum}). " .
                'Check your livewire-honeypot config and honeypotConfig() overrides.'
            );
        }
    }

    /**
     * The validation rules for a form's start time. A maximum of 0 disables expiry.
     *
     * @return list<string>
     */
    public static function startedAtRules(int $now, int $maximumSeconds): array
    {
        $rules = ['required', 'integer', 'max:' . $now];

        if ($maximumSeconds > 0) {
            $rules[] = 'min:' . ($now - $maximumSeconds);
        }

        return $rules;
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
