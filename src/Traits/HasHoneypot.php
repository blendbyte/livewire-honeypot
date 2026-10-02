<?php

namespace Blendbyte\LivewireHoneypot\Traits;

use Blendbyte\LivewireHoneypot\CaughtTokens;
use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\HoneypotConfig;
use Blendbyte\LivewireHoneypot\HoneypotViolation;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Illuminate\Support\Arr;
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

    public function getHoneypotFieldName(): string
    {
        return (string) $this->getHoneypotConfig('field_name');
    }

    public function mountHasHoneypot(): void
    {
        $fieldName = $this->getHoneypotFieldName();

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
        $tokenLength = (int) $this->getHoneypotConfig('token_length');
        HoneypotConfig::validateTokenLengths($tokenLength, (int) $this->getHoneypotConfig('token_min_length'));

        $fieldName = $this->getHoneypotFieldName();
        $this->$fieldName = '';

        $this->hp_started_at = now()->getTimestamp();
        $this->hp_token = Str::random($tokenLength);
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
            $this->getHoneypotFieldName(),
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

        $this->ensureHoneypotIsOnComponent();
        $violation = $this->detectHoneypotViolation($model, $minimumSeconds);

        if ($violation !== null) {
            /** @var SpamResponder $responder */
            $responder = app(SpamResponder::class);

            // Keep the original error keys and failed rules for the built-in default.
            // An exact class check ensures subclass overrides are still invoked.
            if ($violation->exception !== null && $responder::class === ValidationExceptionResponder::class) {
                throw $violation->exception;
            }

            $responder->respond($model, $violation->message);
        }

        $this->resetValidation([$model, 'hp_started_at', 'hp_token']);
    }

    /**
     * Silently check the configured bait field. See isHoneypotCaughtForModel().
     */
    protected function isHoneypotCaught(?int $minimumSeconds = null): bool
    {
        return $this->isHoneypotCaughtForModel($this->getHoneypotFieldName(), $minimumSeconds);
    }

    /**
     * Return true when the submission is spam, without calling the responder or adding errors.
     * Once a form token is caught, every later submission with it is caught too.
     * A form older than one hour still throws the normal validation error, so a visitor can reload.
     *
     * Answer a caught submission yourself, typically with a fake success. Do not call
     * resetHoneypot() in that case: a fresh token would let the bot start over.
     */
    protected function isHoneypotCaughtForModel(string $model, ?int $minimumSeconds = null): bool
    {
        if (HoneypotService::isFake()) {
            return false;
        }

        $this->ensureHoneypotIsOnComponent();

        // Missing or short tokens are always rejected by detection, and must not share a cache key.
        $minimumLength = max(CaughtTokens::MIN_REMEMBERED_LENGTH, (int) $this->getHoneypotConfig('token_min_length'));
        $token = strlen($this->hp_token) >= $minimumLength ? $this->hp_token : null;

        $caught = CaughtTokens::check(
            $token,
            fn (): ?HoneypotViolation => $this->detectHoneypotViolation($model, $minimumSeconds),
            fn () => event(HoneypotDetected::fromRequest($model, 'previously_detected', static::class)),
        );

        if (! $caught) {
            $this->resetValidation([$model, 'hp_started_at', 'hp_token']);
        }

        return $caught;
    }

    /**
     * Form objects do not run the component's mount hook.
     * Components with missing metadata still follow the normal rejection path.
     */
    private function ensureHoneypotIsOnComponent(): void
    {
        if ($this->hp_started_at === 0 && ! ($this instanceof Component)) {
            throw new \LogicException(
                'LivewireHoneypot: Use the HasHoneypot trait on the Livewire component, not on a form object. ' .
                'For a bait field on a form object, call validateHoneypotForModel(\'form.trap\') on the component.'
            );
        }
    }

    /**
     * Return the first violation, or null for a clean submission.
     * Dispatches HoneypotDetected but never responds.
     */
    private function detectHoneypotViolation(string $model, ?int $minimumSeconds): ?HoneypotViolation
    {
        $fieldName = $model;
        $tokenMinLength = (int) $this->getHoneypotConfig('token_min_length');
        $minimumFillSeconds = $minimumSeconds ?? (int) $this->getHoneypotConfig('minimum_fill_seconds');
        $now = now()->getTimestamp();

        // Validate only honeypot state without consuming application validation hooks.
        $rootField = explode('.', $fieldName)[0];
        $data = $this->unwrapDataForValidation(
            Arr::only($this->all(), [$rootField, 'hp_started_at', 'hp_token', 'hp_js']),
        );

        $violation = null;

        try {
            // Require presence & emptiness of the bait field, plus meta fields
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
            $violation = HoneypotViolation::fromValidationException($e, $fieldName, Arr::get($data, $fieldName));
        }

        // JS verification: field must be populated by Alpine.js on page load
        // Livewire exposes unset typed properties as null in all().
        if ($violation === null && $this->isHoneypotJsVerificationRequired() && trim($data['hp_js'] ?? '') === '') {
            $violation = new HoneypotViolation(
                'js_verification_failed',
                __('livewire-honeypot::validation.js_verification_failed'),
            );
        }

        // Time-trap: minimum time spent before submit
        if ($violation === null && $now - (int) $this->hp_started_at < $minimumFillSeconds) {
            $violation = new HoneypotViolation(
                'submitted_too_quickly',
                __('livewire-honeypot::validation.submitted_too_quickly'),
            );
        }

        if ($violation !== null) {
            event(HoneypotDetected::fromRequest($fieldName, $violation->reason, static::class, $violation->filledValue));
        }

        return $violation;
    }
}
