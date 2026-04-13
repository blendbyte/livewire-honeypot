<?php

namespace Blendbyte\LivewireHoneypot\Traits;

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-require-extends \Livewire\Component
 */
trait HasHoneypot
{
    public string $hp_website = '';
    public int $hp_started_at = 0;
    public string $hp_token = '';

    public function mountHasHoneypot(): void
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');

        if ($fieldName !== 'hp_website' && ! property_exists($this, $fieldName)) {
            throw new \LogicException(
                'LivewireHoneypot: The configured field_name "' . $fieldName . '" is not declared as a public ' .
                'property on ' . static::class . '. Add `public string $' . $fieldName . " = '';` to your component."
            );
        }

        $this->resetHoneypot();
    }

    protected function resetHoneypot(): void
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');
        $this->$fieldName = '';
        $this->hp_started_at = now()->getTimestamp();
        $this->hp_token = Str::random(config('livewire-honeypot.token_length', 24));
    }

    protected function validateHoneypot(?int $minimumSeconds = null): void
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');
        $tokenMinLength = config('livewire-honeypot.token_min_length', 10);
        $minimumFillSeconds = $minimumSeconds ?? config('livewire-honeypot.minimum_fill_seconds', 5);
        $now = now()->getTimestamp();

        try {
            // Require presence & emptiness of the bait field, plus meta fields
            $this->validate([
                $fieldName => 'present|size:0',
                'hp_started_at' => ['required', 'integer', 'min:' . ($now - 3600), 'max:' . $now],
                'hp_token' => "required|string|min:{$tokenMinLength}",
            ], [
                "{$fieldName}.size" => __('livewire-honeypot::validation.spam_detected'),
                'hp_started_at.min' => __('livewire-honeypot::validation.invalid_form_data'),
                'hp_started_at.max' => __('livewire-honeypot::validation.invalid_form_data'),
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $reason = isset($errors[$fieldName]) ? 'honeypot_filled' : 'invalid_form_data';

            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: $reason,
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent(),
                component: static::class,
            ));

            throw $e;
        }

        // Time-trap: minimum time spent before submit
        $elapsed = $now - (int) $this->hp_started_at;
        if ($elapsed < $minimumFillSeconds) {
            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: 'submitted_too_quickly',
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent(),
                component: static::class,
            ));

            throw ValidationException::withMessages([
                $fieldName => __('livewire-honeypot::validation.submitted_too_quickly'),
            ]);
        }
    }
}
