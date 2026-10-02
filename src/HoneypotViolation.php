<?php

namespace Blendbyte\LivewireHoneypot;

use Illuminate\Validation\ValidationException;

/**
 * The first honeypot check that failed. The original exception is kept for the default responder.
 *
 * @internal
 */
final readonly class HoneypotViolation
{
    public function __construct(
        public string $reason,
        public string $message,
        public ?ValidationException $exception = null,
        public ?string $filledValue = null,
    ) {}

    /**
     * @param  mixed  $baitValue  The submitted bait field value, kept only when the bait field failed
     */
    public static function fromValidationException(ValidationException $e, string $fieldName, mixed $baitValue = null): self
    {
        $errors = $e->errors();
        $baitFailed = isset($errors[$fieldName]);
        // An expired form is usually a visitor who left the tab open, so it is reported apart from tampering.
        $metadataReason = self::onlyExpired($e) ? 'form_expired' : 'invalid_form_data';

        return new self(
            reason: $baitFailed ? 'honeypot_filled' : $metadataReason,
            message: $errors[$fieldName][0] ?? __('livewire-honeypot::validation.' . $metadataReason),
            exception: $e,
            filledValue: $baitFailed ? self::stringify($baitValue) : null,
        );
    }

    /**
     * Bots may submit any JSON type, so non-strings are encoded rather than cast.
     */
    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_scalar($value) => var_export($value, true),
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                ?: get_debug_type($value),
        };
    }

    /**
     * True when the only problem is an expired form, which is usually a real visitor.
     */
    public function isExpiredForm(): bool
    {
        return $this->reason === 'form_expired';
    }

    private static function onlyExpired(ValidationException $e): bool
    {
        $failed = $e->validator->failed();

        return array_keys($failed) === ['hp_started_at'] && array_keys($failed['hp_started_at']) === ['Min'];
    }
}
