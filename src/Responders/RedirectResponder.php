<?php

namespace Blendbyte\LivewireHoneypot\Responders;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Exceptions\HoneypotRedirectException;

/**
 * Responder that silently redirects the user back to the previous page.
 * The bot sees no validation error signal.
 */
class RedirectResponder implements SpamResponder
{
    public function respond(string $fieldName, string $message): never
    {
        throw new HoneypotRedirectException(url()->previous());
    }
}
