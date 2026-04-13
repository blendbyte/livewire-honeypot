<?php

namespace Blendbyte\LivewireHoneypot\Responders;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Illuminate\Validation\ValidationException;

/**
 * Default responder: throws a ValidationException with a field-level error.
 * Livewire surfaces this as a standard inline validation error.
 */
class ValidationExceptionResponder implements SpamResponder
{
    public function respond(string $fieldName, string $message): never
    {
        throw ValidationException::withMessages([$fieldName => $message]);
    }
}
