# Running the test suites

Install PHP dependencies and run the backend checks:

```bash
composer install
composer test
composer analyse
```

The browser suite uses Node 24 and Playwright with Chromium:

```bash
npm ci
npx playwright install chromium
npm run test:browser
```

Playwright starts two local Testbench servers on ports 18765 and 18766, then stops them after the run. The suite exercises real Livewire requests with the standard build and the CSP build under a strict policy. It covers validation retries, repeated submissions, custom bindings, and multiple components.

Both suites run in CI. Node and Playwright are development dependencies only.

[Back to the README](../README.md)
