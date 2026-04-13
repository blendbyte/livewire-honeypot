<?php

namespace Blendbyte\LivewireHoneypot\Responders;

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Responder that silently redirects the user back to the previous page.
 * The form appears to do nothing — the bot sees no error signal.
 */
class RedirectResponder implements SpamResponder
{
    public function respond(string $fieldName, string $message): never
    {
        throw new HttpResponseException(redirect()->back());
    }
}
