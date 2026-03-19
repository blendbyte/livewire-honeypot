<?php

namespace Blendbyte\LivewireHoneypot\Services;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class HoneypotService
{
    public function generate(): array
    {
        return [
            'hp_website' => '',
            'hp_started_at' => now()->getTimestamp(),
            'hp_token' => Str::random(config('livewire-honeypot.token_length', 24)),
        ];
    }

    public function validate(array $data, ?int $minimumSeconds = null): void
    {
        $minimumSeconds = $minimumSeconds ?? config('livewire-honeypot.minimum_fill_seconds', 5);
        $tokenMinLength = config('livewire-honeypot.token_min_length', 10);

        validator($data, [
            'hp_website' => 'present|size:0',
            'hp_started_at' => 'required|integer',
            'hp_token' => "required|string|min:{$tokenMinLength}",
        ], [
            'hp_website.size' => __('livewire-honeypot::validation.spam_detected'),
        ])->validate();

        $elapsed = now()->getTimestamp() - (int)($data['hp_started_at'] ?? 0);
        if ($elapsed < $minimumSeconds) {
            throw ValidationException::withMessages([
                'hp_website' => __('livewire-honeypot::validation.submitted_too_quickly'),
            ]);
        }
    }
}