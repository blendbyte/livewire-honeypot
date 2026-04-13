<?php

namespace Blendbyte\LivewireHoneypot\Events;

class HoneypotDetected
{
    /**
     * @param  string       $fieldName   The honeypot bait field name (e.g. "hp_website")
     * @param  string       $reason      Why detection was triggered:
     *                                   "honeypot_filled" | "submitted_too_quickly" | "invalid_form_data"
     * @param  string|null  $ipAddress   IP address from the current request, if available
     * @param  string|null  $userAgent   User-agent from the current request, if available
     * @param  string|null  $component   Fully-qualified class name of the Livewire component, if applicable
     */
    public function __construct(
        public readonly string $fieldName,
        public readonly string $reason,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $component = null,
    ) {}
}
