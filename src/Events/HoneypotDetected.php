<?php

namespace Blendbyte\LivewireHoneypot\Events;

class HoneypotDetected
{
    /**
     * @param  string       $fieldName   The honeypot bait field name (e.g. "hp_website")
     * @param  string       $reason      Why detection was triggered:
     *                                   "honeypot_filled" | "submitted_too_quickly" | "invalid_form_data" |
     *                                   "js_verification_failed" | "previously_detected"
     * @param  string|null  $ipAddress   IP address from the current request, if available
     * @param  string|null  $userAgent   User-agent from the current request, if available
     * @param  string|null  $component   Fully-qualified class name of the Livewire component, if applicable
     * @param  string|null  $filledValue The untruncated value submitted in the bait field, only for "honeypot_filled".
     *                                   Untrusted input that may contain a real visitor's autofilled data.
     */
    public function __construct(
        public readonly string $fieldName,
        public readonly string $reason,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $component = null,
        public readonly ?string $filledValue = null,
    ) {}

    /**
     * Build the event with the IP address and user agent of the current request.
     */
    public static function fromRequest(
        string $fieldName,
        string $reason,
        ?string $component = null,
        ?string $filledValue = null,
    ): self {
        return new self(
            fieldName: $fieldName,
            reason: $reason,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            component: $component,
            filledValue: $filledValue,
        );
    }
}
