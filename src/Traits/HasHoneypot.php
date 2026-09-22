<?php

namespace Blendbyte\LivewireHoneypot\Traits;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\HoneypotConfig;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @phpstan-require-extends \Livewire\Component
 */
trait HasHoneypot
{
    public string $hp_website = '';
    public string $hp_field_name = '';

    #[Locked]
    public int $hp_started_at = 0;

    #[Locked]
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
        return $this->honeypotConfig()[$key] ?? HoneypotConfig::get($key, $default);
    }

    /**
     * Expose the effective setting to the Blade component without client state.
     */
    public function isHoneypotJsVerificationRequired(): bool
    {
        return (bool) $this->getHoneypotConfig('require_js_verification');
    }

    public function mountHasHoneypot(): void
    {
        $fieldName = (string) $this->getHoneypotConfig('field_name');

        if ($fieldName !== 'hp_website' && ! property_exists($this, $fieldName)) {
            throw new \LogicException(
                'LivewireHoneypot: The configured field_name "' . $fieldName . '" is not declared as a public ' .
                'property on ' . static::class . '. Add `public string $' . $fieldName . " = '';` to your component."
            );
        }

        $this->resetHoneypot();
    }

    /**
     * Refresh the form metadata and clear the configured bait field.
     */
    protected function resetHoneypot(): void
    {
        $fieldName = (string) $this->getHoneypotConfig('field_name');
        $this->$fieldName = '';

        $this->hp_started_at = now()->getTimestamp();
        $this->hp_token = Str::random((int) $this->getHoneypotConfig('token_length'));
        // The Blade input key follows hp_token so Alpine runs again after a reset.
        $this->hp_js = '';

        $this->hp_field_name = (bool) $this->getHoneypotConfig('randomize_field_name')
            ? 'hp_' . Str::lower(Str::random(6))
            : $fieldName;
    }

    /**
     * Clear a custom bait field and run the normal honeypot reset.
     */
    protected function resetHoneypotForModel(string $model): void
    {
        data_set($this, $model, '');
        $this->resetHoneypot();
    }

    protected function validateHoneypot(?int $minimumSeconds = null): void
    {
        if (HoneypotService::isFake()) {
            return;
        }

        $this->validateHoneypotForModel(
            (string) $this->getHoneypotConfig('field_name'),
            $minimumSeconds,
        );
    }

    /**
     * Validate the property path used by the Blade component's wire:model binding.
     */
    protected function validateHoneypotForModel(string $model, ?int $minimumSeconds = null): void
    {
        if (HoneypotService::isFake()) {
            return;
        }

        // Form objects do not run the component's mount hook.
        // Components with missing metadata must still receive validation errors.
        if ($this->hp_started_at === 0 && ! ($this instanceof Component)) {
            throw new \LogicException(
                'LivewireHoneypot: Use the HasHoneypot trait on the Livewire component, not on a form object. ' .
                'For a bait field on a form object, call validateHoneypotForModel(\'form.trap\') on the component.'
            );
        }

        $fieldName = $model;
        $tokenMinLength = (int) $this->getHoneypotConfig('token_min_length');
        $minimumFillSeconds = $minimumSeconds ?? (int) $this->getHoneypotConfig('minimum_fill_seconds');
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
        if ($this->isHoneypotJsVerificationRequired() && trim($this->hp_js) === '') {
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
