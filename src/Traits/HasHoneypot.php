<?php

namespace Blendbyte\LivewireHoneypot\Traits;

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
        $this->resetHoneypot();
    }

    protected function resetHoneypot(): void
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');
        $this->$fieldName = '';
        $this->hp_started_at = now()->getTimestamp();
        $this->hp_token = Str::random(config('livewire-honeypot.token_length', 24));
    }

    protected function validateHoneypot(): void
    {
        $fieldName = config('livewire-honeypot.field_name', 'hp_website');
        $tokenMinLength = config('livewire-honeypot.token_min_length', 10);
        $minimumFillSeconds = config('livewire-honeypot.minimum_fill_seconds', 5);

        // Require presence & emptiness of the bait field, plus meta fields
        $this->validate([
            $fieldName => 'present|size:0',
            'hp_started_at' => 'required|integer',
            'hp_token' => "required|string|min:{$tokenMinLength}",
        ], [
            "{$fieldName}.size" => __('livewire-honeypot::validation.spam_detected'),
        ]);

        // Time-trap: minimum time spent before submit
        $elapsed = now()->getTimestamp() - (int) $this->hp_started_at;
        if ($elapsed < $minimumFillSeconds) {
            throw ValidationException::withMessages([
                $fieldName => __('livewire-honeypot::validation.submitted_too_quickly'),
            ]);
        }
    }
}