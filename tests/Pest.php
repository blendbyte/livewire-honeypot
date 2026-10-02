<?php

use Blendbyte\LivewireHoneypot\Tests\TestCase;
use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;

uses(TestCase::class)->in(__DIR__);

/**
 * Render a Blade template as if inside a mounted HasHoneypot component.
 * Outside such a component, <x-honeypot /> renders plain-form markup instead.
 */
function renderInHoneypotComponent(string $template, array $data = []): string
{
    $component = new BladeHostHoneypotComponent();
    $component->setId('blade-host');
    $component->mountHasHoneypot();

    view()->share('__livewire', $component);

    try {
        return Blade::render($template, $data);
    } finally {
        view()->share('__livewire', null);
    }
}

class BladeHostHoneypotComponent extends Component
{
    use HasHoneypot;

    public array $contact = ['trap' => ''];
    public string $trap = '';

    public function render(): string
    {
        return '<div></div>';
    }
}
