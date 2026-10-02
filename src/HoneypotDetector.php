<?php

namespace Blendbyte\LivewireHoneypot;

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The honeypot checks shared by the Livewire trait and plain forms.
 *
 * @internal
 */
final class HoneypotDetector
{
    /**
     * Return the first violation, or null for a clean submission.
     * Dispatches HoneypotDetected but never responds.
     *
     * @param  array<string, mixed>  $data  The bait, hp_started_at, hp_token and hp_js values
     * @param  list<mixed>  $tokenRules  Extra rules for hp_token, such as a signature check
     */
    public static function detect(
        array $data,
        string $fieldName,
        int $minimumSeconds,
        int $maximumSeconds,
        bool $requireJs,
        array $tokenRules = [],
        ?string $component = null,
    ): ?HoneypotViolation {
        $now = now()->getTimestamp();
        $violation = null;

        try {
            // Require presence and emptiness of the bait field, plus valid metadata.
            validator($data, [
                $fieldName => 'present|size:0',
                'hp_started_at' => HoneypotConfig::startedAtRules($now, $maximumSeconds),
                'hp_token' => ['required', 'string', ...$tokenRules],
            ], [
                "{$fieldName}.size" => __('livewire-honeypot::validation.spam_detected'),
                'hp_started_at.min' => __('livewire-honeypot::validation.form_expired'),
                'hp_started_at.max' => __('livewire-honeypot::validation.invalid_form_data'),
            ])->validate();
        } catch (ValidationException $e) {
            $violation = HoneypotViolation::fromValidationException($e, $fieldName, Arr::get($data, $fieldName));
        }

        // JS verification: the marker is filled by JavaScript on page load.
        // Livewire exposes unset typed properties as null, and plain forms can post any type.
        $jsMarker = $data['hp_js'] ?? null;
        if ($violation === null && $requireJs && (! is_string($jsMarker) || trim($jsMarker) === '')) {
            $violation = new HoneypotViolation(
                'js_verification_failed',
                __('livewire-honeypot::validation.js_verification_failed'),
            );
        }

        // Time trap: minimum time spent before submitting.
        if ($violation === null && $now - (int) ($data['hp_started_at'] ?? 0) < $minimumSeconds) {
            $violation = new HoneypotViolation(
                'submitted_too_quickly',
                __('livewire-honeypot::validation.submitted_too_quickly'),
            );
        }

        if ($violation !== null) {
            event(HoneypotDetected::fromRequest($fieldName, $violation->reason, $component, $violation->filledValue));
        }

        return $violation;
    }
}
