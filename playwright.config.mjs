import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    use: { browserName: 'chromium', trace: 'retain-on-failure' },
    projects: [
        { name: 'standard', use: { baseURL: 'http://127.0.0.1:18765' } },
        { name: 'csp', use: { baseURL: 'http://127.0.0.1:18766' } },
    ],
    webServer: [
        {
            command: 'php -S 127.0.0.1:18765 tests/Browser/server.php',
            url: 'http://127.0.0.1:18765',
            env: { HONEYPOT_BROWSER_CSP: '0' },
        },
        {
            command: 'php -S 127.0.0.1:18766 tests/Browser/server.php',
            url: 'http://127.0.0.1:18766',
            env: { HONEYPOT_BROWSER_CSP: '1' },
        },
    ],
});
