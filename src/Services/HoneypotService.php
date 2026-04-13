<?php

namespace Blendbyte\LivewireHoneypot\Services;

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class HoneypotService
{
    public function generate(): array
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');

        return [
            $fieldName => '',
            'hp_started_at' => now()->getTimestamp(),
            'hp_token' => Str::random(config('livewire-honeypot.token_length', 24)),
        ];
    }

    public function validate(array $data, ?int $minimumSeconds = null): void
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');
        $minimumSeconds = $minimumSeconds ?? config('livewire-honeypot.minimum_fill_seconds', 5);
        $tokenMinLength = config('livewire-honeypot.token_min_length', 10);
        $now = now()->getTimestamp();

        try {
            validator($data, [
                $fieldName => 'present|size:0',
                'hp_started_at' => ['required', 'integer', 'min:' . ($now - 3600), 'max:' . $now],
                'hp_token' => "required|string|min:{$tokenMinLength}",
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
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent(),
            ));

            throw $e;
        }

        $elapsed = $now - (int) $data['hp_started_at'];
        if ($elapsed < $minimumSeconds) {
            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: 'submitted_too_quickly',
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent(),
            ));

            throw ValidationException::withMessages([
                $fieldName => __('livewire-honeypot::validation.submitted_too_quickly'),
            ]);
        }
    }
}
