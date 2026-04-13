<?php

/**
 * This file exists solely to give PHPStan a concrete class context for
 * analysing the HasHoneypot trait. It is not shipped or autoloaded at runtime
 * — it is only referenced via phpstan.neon paths.
 *
 * @internal
 */

namespace Blendbyte\LivewireHoneypot\PhpStan;

use Blendbyte\LivewireHoneypot\Traits\HasHoneypot;
use Livewire\Component;

/**
 * @internal
 */
final class HasHoneypotTemplate extends Component
{
    use HasHoneypot;

    public function render(): string
    {
        return '';
    }
}
