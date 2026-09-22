<?php

namespace Blendbyte\LivewireHoneypot\Services;

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\HoneypotConfig;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class HoneypotService
{
    protected static bool $fake = false;

    /**
     * Put the honeypot into fake mode: all validation is bypassed.
     * Call this in your test setUp or at the top of a test.
     * Remember to call resetFake() afterwards (or use afterEach()).
     */
    public static function fake(): void
    {
        static::$fake = true;
    }

    /**
     * Restore normal validation behaviour.
     * Call this in your test tearDown or afterEach().
     */
    public static function resetFake(): void
    {
        static::$fake = false;
    }

    /**
     * Returns true if fake mode is active.
     */
    public static function isFake(): bool
    {
        return static::$fake;
    }

    public function generate(): array
    {
        $fieldName = HoneypotConfig::get('field_name');
        $startedAt = now()->getTimestamp();

        return [
            $fieldName => '',
            'hp_started_at' => $startedAt,
            'hp_token' => $this->token($startedAt),
        ];
    }

    /**
     * Sign a plain form's random nonce and start time with the current app key.
     * The optional timestamp is for trusted server-side use only.
     */
    public function token(?int $startedAt = null): string
    {
        $key = $this->signingKeys()[0];
        $payload = Str::random(max(1, (int) HoneypotConfig::get('token_length')))
            . '.' . ($startedAt ?? now()->getTimestamp());

        return $payload . '.' . $this->sign($payload, $key);
    }

    /**
     * Return the authenticated start time, or null for an invalid token.
     * Form age is checked by validate(), not by this method.
     */
    public function startedAtFromToken(mixed $token): ?int
    {
        $keys = $this->signingKeys();

        if (! is_string($token) || substr_count($token, '.') !== 2) {
            return null;
        }

        [$nonce, $timestamp, $signature] = explode('.', $token);

        if (! ctype_alnum($nonce)
            || strlen($nonce) < max(1, (int) HoneypotConfig::get('token_min_length'))
            || ! ctype_digit($timestamp)
            || strlen($signature) !== 64
        ) {
            return null;
        }

        $startedAt = filter_var($timestamp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($startedAt === false) {
            return null;
        }

        foreach ($keys as $key) {
            if (hash_equals($this->sign($nonce . '.' . $timestamp, $key), $signature)) {
                return $startedAt;
            }
        }

        return null;
    }

    /** @return non-empty-list<string> */
    private function signingKeys(): array
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new MissingAppKeyException();
        }

        $previousKeys = array_filter(
            (array) config('app.previous_keys', []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );

        return array_values(array_unique([$key, ...$previousKeys]));
    }

    private function sign(string $payload, string $key): string
    {
        return hash_hmac('sha256', 'livewire-honeypot|' . $payload, $key);
    }

    public function validate(array $data, ?int $minimumSeconds = null): void
    {
        if (static::$fake) {
            return;
        }

        $fieldName = HoneypotConfig::get('field_name');
        $minimumSeconds = $minimumSeconds ?? HoneypotConfig::get('minimum_fill_seconds');
        $now = now()->getTimestamp();
        $startedAt = $this->startedAtFromToken($data['hp_token'] ?? null);

        // Never trust a timestamp supplied separately by the client.
        $data['hp_started_at'] = $startedAt;

        try {
            validator($data, [
                $fieldName => 'present|size:0',
                'hp_started_at' => ['required', 'integer', 'min:' . ($now - 3600), 'max:' . $now],
                'hp_token' => ['required', 'string', function ($attribute, $value, $fail) use ($startedAt): void {
                    if ($startedAt === null) {
                        $fail(__('livewire-honeypot::validation.invalid_form_data'));
                    }
                }],
            ], [
                "{$fieldName}.size" => __('livewire-honeypot::validation.spam_detected'),
                'hp_started_at.min' => __('livewire-honeypot::validation.invalid_form_data'),
                'hp_started_at.max' => __('livewire-honeypot::validation.invalid_form_data'),
            ])->validate();
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $reason = isset($errors[$fieldName]) ? 'honeypot_filled' : 'invalid_form_data';

            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: $reason,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            ));

            if ($reason === 'honeypot_filled') {
                $message = $errors[$fieldName][0];

                /** @var \Blendbyte\LivewireHoneypot\Contracts\SpamResponder $responder */
                $responder = app(\Blendbyte\LivewireHoneypot\Contracts\SpamResponder::class);
                $responder->respond($fieldName, $message);
            }

            throw $e;
        }

        // JS verification: field must be populated by Alpine.js on page load
        if (HoneypotConfig::get('require_js_verification') && trim((string) ($data['hp_js'] ?? '')) === '') {
            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: 'js_verification_failed',
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            ));

            /** @var \Blendbyte\LivewireHoneypot\Contracts\SpamResponder $responder */
            $responder = app(\Blendbyte\LivewireHoneypot\Contracts\SpamResponder::class);
            $responder->respond($fieldName, __('livewire-honeypot::validation.js_verification_failed'));
        }

        $elapsed = $now - (int) $data['hp_started_at'];
        if ($elapsed < $minimumSeconds) {
            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: 'submitted_too_quickly',
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            ));

            /** @var \Blendbyte\LivewireHoneypot\Contracts\SpamResponder $responder */
            $responder = app(\Blendbyte\LivewireHoneypot\Contracts\SpamResponder::class);
            $responder->respond($fieldName, __('livewire-honeypot::validation.submitted_too_quickly'));
        }
    }
}
