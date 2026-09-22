import { test, expect } from '@playwright/test';

// Fail on JS exceptions or blocked resources, including unsafe-eval under CSP.
test.beforeEach(async ({ page }) => {
    page.on('pageerror', error => { throw error; });
    await page.addInitScript(() => {
        window.cspViolations = [];
        document.addEventListener('securitypolicyviolation', event => {
            window.cspViolations.push(event.violatedDirective);
        });
    });
});

test.afterEach(async ({ page }) => {
    expect(await page.evaluate(() => window.cspViolations)).toEqual([]);
});

for (const binding of ['default', 'custom']) {
    test(`${binding} binding survives refresh, validation failure, and repeated submits`, async ({ page }) => {
        await page.goto(`/${binding}`);
        const marker = page.locator('input[name="hp_js"]');
        await expect(marker).toHaveValue('1');
        const initialKey = await marker.getAttribute('wire:key');

        const refresh = page.waitForResponse(response => response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Refresh', exact: true }).click();
        await refresh;
        await expect(marker).toHaveAttribute('wire:key', initialKey);
        await expect(marker).toHaveValue('1');

        await page.getByRole('button', { name: 'Submit', exact: true }).click();
        await expect(page.locator('.email-error')).toBeVisible();
        await expect(marker).toHaveValue('1');
        await expect(page.locator('.hp-error')).toHaveCount(0);

        for (let submission = 1; submission <= 2; submission++) {
            const previousKey = await marker.getAttribute('wire:key');
            await page.getByLabel('Email').fill('visitor@example.com');
            await page.getByRole('button', { name: 'Submit', exact: true }).click();
            await expect(page.locator('.submissions')).toHaveText(String(submission));
            await expect(marker).not.toHaveAttribute('wire:key', previousKey);
            await expect(marker).toHaveValue('1');
            await expect(page.locator('.hp-error')).toHaveCount(0);
        }
    });
}

test('resetting one component leaves another component ready to submit', async ({ page }) => {
    await page.goto('/multiple');
    const forms = page.locator('form');
    const secondMarker = forms.nth(1).locator('input[name="hp_js"]');
    await expect(secondMarker).toHaveValue('1');
    const secondKey = await secondMarker.getAttribute('wire:key');

    for (const [index, count] of [[0, 1], [1, 1], [0, 2]]) {
        const form = forms.nth(index);
        await form.getByLabel('Email').fill('visitor@example.com');
        await form.getByRole('button', { name: 'Submit', exact: true }).click();
        await expect(form.locator('.submissions')).toHaveText(String(count));
        await expect(form.locator('input[name="hp_js"]')).toHaveValue('1');
        await expect(form.locator('.hp-error')).toHaveCount(0);
        if (index === 0 && count === 1) {
            await expect(secondMarker).toHaveAttribute('wire:key', secondKey);
        }
    }
});
