<?php

namespace Blendbyte\LivewireHoneypot\Tests;

use Blendbyte\LivewireHoneypot\HoneypotServiceProvider;
use Blendbyte\LivewireHoneypot\Services\HoneypotService;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            HoneypotServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default config
        $app['config']->set('livewire-honeypot.minimum_fill_seconds', 5);
        $app['config']->set('livewire-honeypot.field_name', 'hp_website');
        $app['config']->set('livewire-honeypot.token_min_length', 10);
        $app['config']->set('livewire-honeypot.token_length', 24);
        $app['config']->set('livewire-honeypot.randomize_field_name', false);
        $app['config']->set('livewire-honeypot.logging.enabled', false);

        // Setup app key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        HoneypotService::resetFake();

        parent::tearDown();
    }
}
