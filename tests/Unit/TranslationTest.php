<?php

// ---------------------------------------------------------------------------
// English (en) — default locale
// ---------------------------------------------------------------------------

test('en: spam_detected', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detected.');
});

test('en: submitted_too_quickly', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Form submitted too quickly.');
});

test('en: honeypot_label', function () {
    app()->setLocale('en');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (leave empty)');
});

// ---------------------------------------------------------------------------
// Dutch (nl)
// ---------------------------------------------------------------------------

test('nl: spam_detected', function () {
    app()->setLocale('nl');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam gedetecteerd.');
});

test('nl: submitted_too_quickly', function () {
    app()->setLocale('nl');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Formulier te snel verzonden.');
});

test('nl: honeypot_label', function () {
    app()->setLocale('nl');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (laat leeg)');
});

// ---------------------------------------------------------------------------
// German (de)
// ---------------------------------------------------------------------------

test('de: spam_detected', function () {
    app()->setLocale('de');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam erkannt.');
});

test('de: submitted_too_quickly', function () {
    app()->setLocale('de');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Form zu schnell abgeschickt.');
});

test('de: honeypot_label', function () {
    app()->setLocale('de');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (frei lassen)');
});

// ---------------------------------------------------------------------------
// Fallback: unknown locale falls back to English
// ---------------------------------------------------------------------------

test('unknown locale falls back to English for spam_detected', function () {
    app()->setLocale('xx');
    app()->setFallbackLocale('en');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detected.');
});

// ---------------------------------------------------------------------------
// Spanish (es)
// ---------------------------------------------------------------------------

test('es: spam_detected', function () {
    app()->setLocale('es');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detectado.');
});

test('es: submitted_too_quickly', function () {
    app()->setLocale('es');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Formulario enviado demasiado rápido.');
});

test('es: honeypot_label', function () {
    app()->setLocale('es');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Sitio web (dejar vacío)');
});

// ---------------------------------------------------------------------------
// French (fr)
// ---------------------------------------------------------------------------

test('fr: spam_detected', function () {
    app()->setLocale('fr');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam détecté.');
});

test('fr: submitted_too_quickly', function () {
    app()->setLocale('fr');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Formulaire soumis trop rapidement.');
});

test('fr: honeypot_label', function () {
    app()->setLocale('fr');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Site web (laisser vide)');
});

// ---------------------------------------------------------------------------
// Portuguese (pt)
// ---------------------------------------------------------------------------

test('pt: spam_detected', function () {
    app()->setLocale('pt');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam detectado.');
});

test('pt: submitted_too_quickly', function () {
    app()->setLocale('pt');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Formulário enviado demasiado rápido.');
});

test('pt: honeypot_label', function () {
    app()->setLocale('pt');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Website (deixar vazio)');
});

// ---------------------------------------------------------------------------
// Italian (it)
// ---------------------------------------------------------------------------

test('it_locale: spam_detected', function () {
    app()->setLocale('it');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Spam rilevato.');
});

test('it_locale: submitted_too_quickly', function () {
    app()->setLocale('it');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Modulo inviato troppo rapidamente.');
});

test('it_locale: honeypot_label', function () {
    app()->setLocale('it');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Sito web (lasciare vuoto)');
});

// ---------------------------------------------------------------------------
// Russian (ru)
// ---------------------------------------------------------------------------

test('ru: spam_detected', function () {
    app()->setLocale('ru');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Обнаружен спам.');
});

test('ru: submitted_too_quickly', function () {
    app()->setLocale('ru');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Форма отправлена слишком быстро.');
});

test('ru: honeypot_label', function () {
    app()->setLocale('ru');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Веб-сайт (оставьте пустым)');
});

// ---------------------------------------------------------------------------
// Polish (pl)
// ---------------------------------------------------------------------------

test('pl: spam_detected', function () {
    app()->setLocale('pl');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('Wykryto spam.');
});

test('pl: submitted_too_quickly', function () {
    app()->setLocale('pl');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('Formularz przesłany zbyt szybko.');
});

test('pl: honeypot_label', function () {
    app()->setLocale('pl');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('Strona internetowa (zostaw puste)');
});

// ---------------------------------------------------------------------------
// Japanese (ja)
// ---------------------------------------------------------------------------

test('ja: spam_detected', function () {
    app()->setLocale('ja');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('スパムが検出されました。');
});

test('ja: submitted_too_quickly', function () {
    app()->setLocale('ja');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('フォームの送信が早すぎます。');
});

test('ja: honeypot_label', function () {
    app()->setLocale('ja');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('ウェブサイト（空白のまま）');
});

// ---------------------------------------------------------------------------
// Chinese Simplified (zh)
// ---------------------------------------------------------------------------

test('zh: spam_detected', function () {
    app()->setLocale('zh');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('检测到垃圾邮件。');
});

test('zh: submitted_too_quickly', function () {
    app()->setLocale('zh');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('表单提交过快。');
});

test('zh: honeypot_label', function () {
    app()->setLocale('zh');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('网站（请留空）');
});

// ---------------------------------------------------------------------------
// Chinese Traditional (zh-TW)
// ---------------------------------------------------------------------------

test('zh-TW: spam_detected', function () {
    app()->setLocale('zh-TW');

    expect(__('livewire-honeypot::validation.spam_detected'))->toBe('偵測到垃圾郵件。');
});

test('zh-TW: submitted_too_quickly', function () {
    app()->setLocale('zh-TW');

    expect(__('livewire-honeypot::validation.submitted_too_quickly'))->toBe('表單提交過快。');
});

test('zh-TW: honeypot_label', function () {
    app()->setLocale('zh-TW');

    expect(__('livewire-honeypot::validation.honeypot_label'))->toBe('網站（請留空）');
});
