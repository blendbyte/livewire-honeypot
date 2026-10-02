import { test, expect } from '@playwright/test';

test('a plain form fills the JS marker without Livewire and submits', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => message.type() === 'error' && errors.push(message.text()));

    await page.goto('/plain');
    await expect(page.locator('input[name="hp_js"]')).toHaveValue('1');
    await expect(page.locator('input[type="text"]')).toHaveAttribute('name', /^[a-z]+_[0-9a-f]{4}$/);
    await page.getByLabel('Email').fill('visitor@example.com');
    await page.getByRole('button', { name: 'Submit' }).click();

    await expect(page.locator('.accepted')).toHaveText('Accepted');
    await expect(page.locator('.hp-error')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('a filled plain bait is rejected with a visible error', async ({ page }) => {
    await page.goto('/plain');
    const bait = page.locator('input[type="text"]');
    // Make the visually hidden input reachable for actual keyboard input.
    await bait.evaluate(input => input.closest('[aria-hidden="true"]').removeAttribute('class'));
    await bait.fill('spam');
    await page.getByRole('button', { name: 'Submit' }).click();

    await expect(page.locator('.hp-error')).toHaveText('Spam detected.');
    await expect(page.locator('.accepted')).toHaveCount(0);
});

test('a plain form inside a Livewire component keeps its token across re-renders', async ({ page }) => {
    await page.goto('/newsletter');
    const token = page.locator('input[name="hp_token"]');
    const marker = page.locator('input[name="hp_js"]');
    const bait = page.locator('input[type="text"][tabindex="-1"]');
    await expect(marker).toHaveValue('1');
    const originalToken = await token.inputValue();
    const originalName = await bait.getAttribute('name');

    // wire:model.live re-renders the component, which would otherwise render a new token.
    await page.getByLabel('Name').fill('Visitor');
    await expect(page.locator('.length')).toHaveText('7');

    await expect(token).toHaveValue(originalToken);
    await expect(bait).toHaveAttribute('name', originalName);
    await expect(marker).toHaveValue('1');

    await page.getByRole('button', { name: 'Subscribe' }).click();
    await expect(page.locator('.accepted')).toHaveText('Accepted');
});
