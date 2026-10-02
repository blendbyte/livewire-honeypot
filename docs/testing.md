# Testing

## Testing your forms

Bypass honeypot checks in tests that focus on the rest of your form:

```php
use Blendbyte\LivewireHoneypot\Services\HoneypotService;

beforeEach(fn () => HoneypotService::fake());
```

Fake mode ends with each test's application, so it never leaks into other tests. Call `HoneypotService::resetFake()` to turn it off within a test.

To test the waiting period itself, mount the component and advance time before submitting:

```php
$component = Livewire::test(ContactForm::class);
$this->travel(5)->seconds();
$component->call('submit');
```

The timestamp and token are locked properties, so use time travel instead of setting them through Livewire. To select the bait input in browser tests, use its `wire:model` attribute rather than its generated name.

## Testing silent rejection

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

Playwright starts two local Testbench servers on ports 18765 and 18766, then stops them after the run. The suite exercises real Livewire requests with the standard build and the CSP build under a strict policy. It covers validation retries, repeated submissions, custom bindings, multiple components, and a plain form posting to a controller.

Both suites run in CI. Node and Playwright are development dependencies only.

[Back to the README](../README.md)
