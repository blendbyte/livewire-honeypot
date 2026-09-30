# Running the test suites

## Testing silent rejection in your app

Submissions caught by `isHoneypotCaught()` have no errors, so assert on your fake-success state and on the work that should not run. Submitting immediately after mounting triggers the waiting-time check. Use `travel()` to cover a retry:

```php
Event::fake([HoneypotDetected::class]);

$component = Livewire::test(ContactForm::class);
$component->call('submit')->assertHasNoErrors()->assertSet('success', true);

// Waiting and resubmitting the same form is still caught.
$this->travel(5)->seconds();
$component->call('submit')->assertSet('success', true);

Event::assertDispatched(HoneypotDetected::class, fn ($event) => $event->reason === 'previously_detected');
Mail::assertNothingSent();
```

Caught tokens are stored in the cache, so use the `array` store in tests. `HoneypotService::fake()` makes `isHoneypotCaught()` return `false`.

## Package checks

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
