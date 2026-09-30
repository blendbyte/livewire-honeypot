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
    ) {}

    public static function fromValidationException(ValidationException $e, string $fieldName): self
    {
        $errors = $e->errors();

        return new self(
            reason: isset($errors[$fieldName]) ? 'honeypot_filled' : 'invalid_form_data',
            message: $errors[$fieldName][0] ?? __('livewire-honeypot::validation.invalid_form_data'),
            exception: $e,
        );
    }

    /**
     * True when the only problem is a form older than one hour, which is usually a real visitor.
     */
    public function isExpiredForm(): bool
    {
        $failed = $this->exception?->validator->failed() ?? [];

        return array_keys($failed) === ['hp_started_at'] && array_keys($failed['hp_started_at']) === ['Min'];
    }
}
