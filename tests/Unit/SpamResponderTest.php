<?php

use Blendbyte\LivewireHoneypot\Contracts\SpamResponder;
use Blendbyte\LivewireHoneypot\Responders\AbortResponder;
use Blendbyte\LivewireHoneypot\Responders\RedirectResponder;
use Blendbyte\LivewireHoneypot\Responders\ValidationExceptionResponder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ---------------------------------------------------------------------------
// ValidationExceptionResponder
// ---------------------------------------------------------------------------

test('ValidationExceptionResponder implements SpamResponder', function () {
    expect(new ValidationExceptionResponder())->toBeInstanceOf(SpamResponder::class);
});

test('ValidationExceptionResponder throws ValidationException with field error', function () {
    $responder = new ValidationExceptionResponder();

    expect(fn () => $responder->respond('hp_website', 'Spam detected.'))
        ->toThrow(ValidationException::class);
});

test('ValidationExceptionResponder sets the correct field and message', function () {
    $responder = new ValidationExceptionResponder();

    try {
        $responder->respond('hp_website', 'Spam detected.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('hp_website');
        expect($e->errors()['hp_website'][0])->toBe('Spam detected.');
    }
});

// ---------------------------------------------------------------------------
// AbortResponder
// ---------------------------------------------------------------------------

test('AbortResponder implements SpamResponder', function () {
    expect(new AbortResponder())->toBeInstanceOf(SpamResponder::class);
});

test('AbortResponder throws a 403 HttpException', function () {
    $responder = new AbortResponder();

    expect(fn () => $responder->respond('hp_website', 'Forbidden.'))
        ->toThrow(HttpException::class);
});

test('AbortResponder uses 403 status code', function () {
    $responder = new AbortResponder();

    try {
        $responder->respond('hp_website', 'Forbidden.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

// ---------------------------------------------------------------------------
// RedirectResponder
// ---------------------------------------------------------------------------

test('RedirectResponder implements SpamResponder', function () {
    expect(new RedirectResponder())->toBeInstanceOf(SpamResponder::class);
});

test('RedirectResponder throws HttpResponseException', function () {
    $responder = new RedirectResponder();

    expect(fn () => $responder->respond('hp_website', 'Spam.'))
        ->toThrow(HttpResponseException::class);
});

// ---------------------------------------------------------------------------
// Container binding
// ---------------------------------------------------------------------------

test('container resolves ValidationExceptionResponder by default', function () {
    $responder = app(SpamResponder::class);

    expect($responder)->toBeInstanceOf(ValidationExceptionResponder::class);
});

test('container resolves AbortResponder when configured', function () {
    config(['livewire-honeypot.spam_responder' => AbortResponder::class]);

    // Re-bind to pick up the new config
    app()->bind(SpamResponder::class, static fn () => app(config('livewire-honeypot.spam_responder')));

    $responder = app(SpamResponder::class);

    expect($responder)->toBeInstanceOf(AbortResponder::class);
});
