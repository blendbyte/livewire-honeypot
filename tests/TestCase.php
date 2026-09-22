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
        // Setup app key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        HoneypotService::resetFake();

        parent::tearDown();
    }
}
