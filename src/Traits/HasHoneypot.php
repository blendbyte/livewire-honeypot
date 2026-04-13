<?php

namespace Blendbyte\LivewireHoneypot\Traits;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-require-extends \Livewire\Component
 */
trait HasHoneypot
{
    public string $hp_website = '';
    public string $hp_field_name = '';
    public int $hp_started_at = 0;
    public string $hp_token = '';
    public string $hp_js = '';

    /**
     * Override this method in your component to customise honeypot settings
     * for that component without touching the global config.
     *
     * Supported keys: minimum_fill_seconds, field_name, token_length,
     *                 token_min_length, randomize_field_name, require_js_verification
     *
     * Example:
     *   protected function honeypotConfig(): array
     *   {
     *       return ['minimum_fill_seconds' => 10, 'token_length' => 32];
     *   }
     *
     * @return array<string, mixed>
     */
    protected function honeypotConfig(): array
    {
        return [];
    }

    /**
     * Read a honeypot config value, preferring any component-level override.
     */
    private function getHoneypotConfig(string $key, mixed $default = null): mixed
    {
        return $this->honeypotConfig()[$key] ?? config("livewire-honeypot.{$key}", $default);
    }

    public function mountHasHoneypot(): void
    {
        $fieldName = (string) $this->getHoneypotConfig('field_name', 'hp_website');

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
        $fieldName = (string) $this->getHoneypotConfig('field_name', 'hp_website');
        $this->$fieldName = '';
        $this->hp_started_at = now()->getTimestamp();
        $this->hp_token = Str::random((int) $this->getHoneypotConfig('token_length', 24));
        $this->hp_js = '';

        $this->hp_field_name = (bool) $this->getHoneypotConfig('randomize_field_name', false)
            ? 'hp_' . Str::lower(Str::random(6))
            : $fieldName;
    }

    protected function validateHoneypot(?int $minimumSeconds = null): void
    {
        if (HoneypotService::isFake()) {
            return;
        }

        $fieldName = (string) $this->getHoneypotConfig('field_name', 'hp_website');
        $tokenMinLength = (int) $this->getHoneypotConfig('token_min_length', 10);
        $minimumFillSeconds = $minimumSeconds ?? (int) $this->getHoneypotConfig('minimum_fill_seconds', 5);
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
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                component: static::class,
            ));

            throw $e;
        }

        // JS verification: field must be populated by Alpine.js on page load
        if ((bool) $this->getHoneypotConfig('require_js_verification', false) && trim($this->hp_js) === '') {
            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: 'js_verification_failed',
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                component: static::class,
            ));

            /** @var SpamResponder $responder */
            $responder = app(SpamResponder::class);
            $responder->respond($fieldName, __('livewire-honeypot::validation.js_verification_failed'));
        }

        // Time-trap: minimum time spent before submit
        $elapsed = $now - (int) $this->hp_started_at;
        if ($elapsed < $minimumFillSeconds) {
            event(new HoneypotDetected(
                fieldName: $fieldName,
                reason: 'submitted_too_quickly',
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                component: static::class,
            ));

            /** @var SpamResponder $responder */
            $responder = app(SpamResponder::class);
            $responder->respond($fieldName, __('livewire-honeypot::validation.submitted_too_quickly'));
        }
    }
}
