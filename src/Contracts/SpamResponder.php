<?php

namespace Blendbyte\LivewireHoneypot\Contracts;

interface SpamResponder
{
    /**
     * Handle a detected spam submission.
     * Implementations must always terminate execution.
     *
     * @param  string  $fieldName  The honeypot bait field name
     * @param  string  $message    The user-facing validation message
     */
    public function respond(string $fieldName, string $message): never;
}
