<?php

namespace Blendbyte\LivewireHoneypot;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The silent rejection flow shared by the Livewire trait and plain forms.
 * Cache failures are reported and treated as "not remembered" so forms keep working.
 *
 * @internal
 */
final class CaughtTokens
{
    /**
     * Without form expiry a caught token could be replayed forever, but remembering it forever
     * would let bots grow the cache without limit, so it is kept for one day.
     */
    public const int TTL_WITHOUT_EXPIRY_SECONDS = 86400;

    /** Shorter random values could collide between visitors, so they are never remembered. */
    public const int MIN_REMEMBERED_LENGTH = 8;

    /**
     * Return true for a remembered or newly caught token, false for a clean submission.
     * An expired form throws its validation error so a real visitor can reload and retry.
     *
     * @param  string|null  $token  The token to remember, or null when it must not be remembered
     * @param  callable(): ?HoneypotViolation  $detect
     * @param  callable(): void  $onRepeat  Called when the token was caught before
     * @param  int  $maximumFormSeconds  The form expiry, so a remembered token outlives its form; 0 when disabled
     */
    public static function check(?string $token, callable $detect, callable $onRepeat, int $maximumFormSeconds): bool
    {
        $cacheAvailable = true;

        if ($token !== null) {
            $remembered = self::lookup($token);
            $cacheAvailable = $remembered !== null;

            if ($remembered === true) {
                $onRepeat();

                return true;
            }
        }

        $violation = $detect();

        if ($violation === null) {
            return false;
        }

        if ($violation->exception !== null && $violation->isExpiredForm()) {
            throw $violation->exception;
        }

        if ($token !== null && $cacheAvailable) {
            self::remember($token, $maximumFormSeconds > 0 ? $maximumFormSeconds : self::TTL_WITHOUT_EXPIRY_SECONDS);
        }

        return true;
    }

    public static function key(string $token): string
    {
        return 'livewire-honeypot:caught:'.hash('sha256', $token);
    }

    /** Return null when the cache store is unavailable. */
    private static function lookup(string $token): ?bool
    {
        try {
            return (bool) self::store()->get(self::key($token), false);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function remember(string $token, int $seconds): void
    {
        try {
            self::store()->put(self::key($token), true, $seconds);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function store(): Repository
    {
        $store = HoneypotConfig::get('caught_cache_store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }
}
