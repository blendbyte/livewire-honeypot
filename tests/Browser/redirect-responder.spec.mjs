import { test, expect } from '@playwright/test';

for (const scenario of ['filled bait', 'expired form']) {
    test(`${scenario} redirects without JS errors or an error dialog`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => {
            if (message.type() === 'error') errors.push(message.text());
        });
        await page.addInitScript(() => {
            const showModal = HTMLDialogElement.prototype.showModal;
            HTMLDialogElement.prototype.showModal = function () {
                if (this.id === 'livewire-error') sessionStorage.setItem('honeypot-error-dialog', '1');
                return showModal.call(this);
            };
        });

        await page.goto('/redirect?source=browser');
        await expect(page.locator('input[name="hp_js"]')).toHaveValue('1');
        const destination = page.url();
        await page.getByLabel('Email').fill('visitor@example.com');

        if (scenario === 'filled bait') {
            await page.evaluate(() => {
                window.Livewire.all()[0].$wire.$set('hp_website', 'spam', false);
            });
        }

        const method = scenario === 'filled bait' ? 'submit' : 'submitExpired';
        const [response] = await Promise.all([
            page.waitForResponse(response => response.request().method() === 'POST'
                && response.request().postDataJSON()?.components?.some(component =>
                    component.calls.some(call => call.method === method))),
            page.waitForEvent('framenavigated', { predicate: frame => frame === page.mainFrame() }),
            page.getByRole('button', {
                name: scenario === 'filled bait' ? 'Submit' : 'Submit expired form', exact: true,
            }).click(),
        ]);
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/json');
        await page.waitForLoadState();

        await expect(page).toHaveURL(destination);
        await expect(page.locator('.submissions')).toHaveText('0');
        expect(await page.evaluate(() => sessionStorage.getItem('honeypot-error-dialog'))).toBeNull();
        expect(errors).toEqual([]);
    });
}
