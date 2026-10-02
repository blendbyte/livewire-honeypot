<?php

namespace Blendbyte\LivewireHoneypot\Services;

use Blendbyte\LivewireHoneypot\CaughtTokens;
use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\HoneypotConfig;
use Blendbyte\LivewireHoneypot\HoneypotViolation;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class HoneypotService
{
    /**
     * Words for generated bait names. They read like ordinary form fields but avoid the names
     * browsers and password managers autofill (name, email, url, website, company, ...).
     */
    private const array BAIT_WORDS = [
        'reference', 'remarks', 'topic', 'occasion', 'referral', 'interest', 'preference', 'category',
    ];

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

    /**
     * An inconspicuous bait name derived from a form token, such as "referral_3f9a".
     * Plain forms can render it instead of field_name; validate() finds it again from the token.
     */
    public function baitName(string $token): string
    {
        return $this->baitNameWithKey($token, $this->signingKeys()[0]);
    }

    /**
     * A class for the hidden wrapper that is stable per app but not recognisable as a honeypot.
     */
    public function wrapperClass(): string
    {
        return 'f' . substr($this->sign('wrapper', $this->signingKeys()[0]), 0, 8);
    }

    private function baitNameWithKey(string $token, string $key): string
    {
        $hash = $this->sign('bait|' . $token, $key);

        return self::BAIT_WORDS[hexdec(substr($hash, 0, 2)) % count(self::BAIT_WORDS)] . '_' . substr($hash, 2, 4);
    }

    /**
     * Read the bait from its derived name when the form rendered one, under any signing key.
     * A filled field_name is kept, so rendering both names cannot hide a filled bait.
     */
    private function withDerivedBait(array $data, string $fieldName): array
    {
        $token = $data['hp_token'] ?? null;
        $staticValue = Arr::get($data, $fieldName);

        if (! is_string($token) || ($staticValue !== null && $staticValue !== '')) {
            return $data;
        }

        foreach ($this->signingKeys() as $key) {
            $baitName = $this->baitNameWithKey($token, $key);

            if (array_key_exists($baitName, $data)) {
                Arr::set($data, $fieldName, $data[$baitName]);
                break;
            }
        }

        return $data;
    }

    public function validate(array $data, ?int $minimumSeconds = null): void
    {
        if (static::$fake) {
            return;
        }

        $violation = $this->detectViolation($data, $minimumSeconds, $this->startedAtFromToken($data['hp_token'] ?? null));

        if ($violation === null) {
            return;
        }

        /** @var SpamResponder $responder */
        $responder = app(SpamResponder::class);

        // Preserve the default metadata error bag without bypassing subclass overrides.
        if ($violation->reason === 'invalid_form_data'
            && $violation->exception !== null
            && $responder::class === ValidationExceptionResponder::class
        ) {
            throw $violation->exception;
        }

        $responder->respond(HoneypotConfig::get('field_name'), $violation->message);
    }

    /**
     * Return true when the submission is spam, without calling the responder or adding errors.
     * Once a signed token is caught, every later submission with it is caught too.
     * A form older than one hour still throws the normal validation error, so a visitor can reload.
     * Answer a caught submission yourself, typically with a fake success.
     */
    public function isCaught(array $data, ?int $minimumSeconds = null): bool
    {
        if (static::$fake) {
            return false;
        }

        $token = $data['hp_token'] ?? null;
        $startedAt = $this->startedAtFromToken($token);

        // Only authentic tokens with enough random characters are remembered.
        $remember = is_string($token)
            && $startedAt !== null
            && strlen(explode('.', $token)[0]) >= CaughtTokens::MIN_REMEMBERED_LENGTH;

        return CaughtTokens::check(
            $remember ? $token : null,
            fn (): ?HoneypotViolation => $this->detectViolation($data, $minimumSeconds, $startedAt),
            fn () => event(HoneypotDetected::fromRequest(HoneypotConfig::get('field_name'), 'previously_detected')),
        );
    }

    /**
     * Return the first violation, or null for a clean submission.
     * Dispatches HoneypotDetected but never responds.
     *
     * @param  int|null  $startedAt  The start time from startedAtFromToken(), or null for an invalid token
     */
    private function detectViolation(array $data, ?int $minimumSeconds, ?int $startedAt): ?HoneypotViolation
    {
        $fieldName = HoneypotConfig::get('field_name');
        $minimumSeconds = $minimumSeconds ?? HoneypotConfig::get('minimum_fill_seconds');
        $now = now()->getTimestamp();

        $data = $this->withDerivedBait($data, $fieldName);

        // Never trust a timestamp supplied separately by the client.
        $data['hp_started_at'] = $startedAt;
        $violation = null;

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
            $violation = HoneypotViolation::fromValidationException($e, $fieldName, Arr::get($data, $fieldName));
        }

        // JS verification: field must be populated by Alpine.js on page load
        $jsMarker = $data['hp_js'] ?? null;
        if ($violation === null
            && HoneypotConfig::get('require_js_verification')
            && (! is_string($jsMarker) || trim($jsMarker) === '')
        ) {
            $violation = new HoneypotViolation(
                'js_verification_failed',
                __('livewire-honeypot::validation.js_verification_failed'),
            );
        }

        if ($violation === null && $now - (int) $startedAt < $minimumSeconds) {
            $violation = new HoneypotViolation(
                'submitted_too_quickly',
                __('livewire-honeypot::validation.submitted_too_quickly'),
            );
        }

        if ($violation !== null) {
            event(HoneypotDetected::fromRequest($fieldName, $violation->reason, filledValue: $violation->filledValue));
        }

        return $violation;
    }
}
