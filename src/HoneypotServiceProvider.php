<?php

namespace Blendbyte\LivewireHoneypot;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Events\HoneypotDetected;
use Blendbyte\LivewireHoneypot\Exceptions\HoneypotRedirectException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;

class HoneypotServiceProvider extends ServiceProvider
{
    /** Maximum characters of a bait value written to the log. */
    private const int LOGGED_VALUE_LIMIT = 200;

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/livewire-honeypot.php',
            'livewire-honeypot'
        );

        $this->app->bind(
            SpamResponder::class,
            static fn () => app(HoneypotConfig::get('spam_responder'))
        );
    }

    public function boot(): void
    {
        // Terminate the rejected action, then let Livewire serialize its redirect effect.
        Livewire::listen('exception', static function ($component, $exception, $stopPropagation): void {
            if ($component instanceof Component && $exception instanceof HoneypotRedirectException) {
                $component->redirect($exception->url);
                $stopPropagation();
            }
        });

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'livewire-honeypot');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'livewire-honeypot');

        // Register <x-honeypot />
        Blade::component('livewire-honeypot::components.honeypot', 'honeypot');

        // Allow publishing the views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/livewire-honeypot'),
        ], 'livewire-honeypot-views');

        // Allow publishing the translations
        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/livewire-honeypot'),
        ], 'livewire-honeypot-translations');

        // Allow publishing the config
        $this->publishes([
            __DIR__.'/../config/livewire-honeypot.php' => config_path('livewire-honeypot.php'),
        ], 'livewire-honeypot-config');

        // Guard against misconfigured token lengths and fill times
        HoneypotConfig::validateTokenLength((int) HoneypotConfig::get('token_length'));
        HoneypotConfig::validateFillSeconds(
            (int) HoneypotConfig::get('minimum_fill_seconds'),
            (int) HoneypotConfig::get('maximum_fill_seconds'),
        );

        // Register structured logging listener when enabled
        if (HoneypotConfig::get('logging.enabled')) {
            Event::listen(HoneypotDetected::class, static function (HoneypotDetected $event): void {
                $level = (string) HoneypotConfig::get('logging.level');
                $channel = HoneypotConfig::get('logging.channel');

                $context = [
                    'reason' => $event->reason,
                    'field_name' => $event->fieldName,
                    'ip' => $event->ipAddress,
                    'user_agent' => $event->userAgent,
                    'component' => $event->component,
                ];

                // Kept in the context, never the message, so the log formatter escapes it.
                if ($event->filledValue !== null && HoneypotConfig::get('logging.include_value')) {
                    $context['filled_value'] = Str::limit($event->filledValue, self::LOGGED_VALUE_LIMIT);
                }

                if ($channel) {
                    Log::channel((string) $channel)->log($level, 'Honeypot triggered', $context);
                } else {
                    Log::log($level, 'Honeypot triggered', $context);
                }
            });
        }
    }
}
