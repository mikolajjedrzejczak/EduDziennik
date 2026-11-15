import { expect, test } from './fixtures';

for (const width of [390, 1024, 1440]) {
    test(`built login and guest protection at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const failedAssets: string[] = [];
        page.on('response', (response) => {
            if (response.url().includes('/build/') && !response.ok()) {
                failedAssets.push(response.url());
            }
        });
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
        await expect(
            page.getByRole('button', { name: 'Log in', exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeFocused();
        expect(
            await page.locator('script[src*="/build/assets/"]').count(),
        ).toBeGreaterThan(0);
        expect(await page.locator('script[src*="@vite/client"]').count()).toBe(
            0,
        );
        await expect(page.locator('body')).toHaveCSS('margin', '0px');
        expect(failedAssets).toEqual([]);
    });
}
