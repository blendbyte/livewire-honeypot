<?php

// Config files published from earlier releases read HoneypotConfig::DEFAULTS directly,
// so every key they reference must stay defined until the next major version.

test('a config file published from 2.1.0 loads without warnings', function () {
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        $config = require dirname(__DIR__).'/Fixtures/published-config-2.1.0.php';
    } finally {
        restore_error_handler();
    }

    expect($config)->toBeArray()->toHaveKey('token_min_length', 10);
});
