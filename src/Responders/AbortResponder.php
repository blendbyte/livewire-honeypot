<?php

namespace Blendbyte\LivewireHoneypot\Responders;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;

/**
 * Responder that aborts the request with a 403 Forbidden response.
 * Useful when you want a hard rejection rather than a field-level error.
 */
class AbortResponder implements SpamResponder
{
    public function respond(string $fieldName, string $message): never
    {
        abort(403, $message);
    }
}
