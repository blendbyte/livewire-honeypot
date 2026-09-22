<?php

namespace Blendbyte\LivewireHoneypot\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

class HoneypotRedirectException extends HttpResponseException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct(new RedirectResponse($url));
    }
}
