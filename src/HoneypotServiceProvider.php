<?php

namespace Blendbyte\LivewireHoneypot;

use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class HoneypotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/livewire-honeypot.php',
            'livewire-honeypot'
        );

        $this->app->bind(
            \Blendbyte\LivewireHoneypot\Contracts\SpamResponder::class,
            static fn () => app(config(
                'livewire-honeypot.spam_responder',
                \Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder::class
            ))
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'livewire-honeypot');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'livewire-honeypot');

        // Register <x-honeypot />
        Blade::component('livewire-honeypot::components.honeypot', 'honeypot');

        // Allow publishing the views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/livewire-honeypot'),
        ], 'livewire-honeypot-views');

        // Allow publishing the translations
        $this->publishes([
            __DIR__ . '/../resources/lang' => lang_path('vendor/livewire-honeypot'),
        ], 'livewire-honeypot-translations');

        // Allow publishing the config
        $this->publishes([
            __DIR__ . '/../config/livewire-honeypot.php' => config_path('livewire-honeypot.php'),
        ], 'livewire-honeypot-config');

        // Guard against misconfigured token lengths
        $tokenLength    = (int) config('livewire-honeypot.token_length', 24);
        $tokenMinLength = (int) config('livewire-honeypot.token_min_length', 10);

        if ($tokenLength < $tokenMinLength) {
            throw new \InvalidArgumentException(
                "livewire-honeypot: token_length ({$tokenLength}) must be greater than or equal to " .
                "token_min_length ({$tokenMinLength}). Check your HONEYPOT_TOKEN_LENGTH and " .
                "HONEYPOT_TOKEN_MIN_LENGTH environment variables."
            );
        }

        // Register structured logging listener when enabled
        if (config('livewire-honeypot.logging.enabled', false)) {
            Event::listen(HoneypotDetected::class, static function (HoneypotDetected $event): void {
                $level   = (string) config('livewire-honeypot.logging.level', 'warning');
                $channel = config('livewire-honeypot.logging.channel');

                $context = [
                    'reason'     => $event->reason,
                    'field_name' => $event->fieldName,
                    'ip'         => $event->ipAddress,
                    'user_agent' => $event->userAgent,
                    'component'  => $event->component,
                ];

                if ($channel) {
                    Log::channel((string) $channel)->log($level, 'Honeypot triggered', $context);
                } else {
                    Log::log($level, 'Honeypot triggered', $context);
                }
            });
        }
    }
}