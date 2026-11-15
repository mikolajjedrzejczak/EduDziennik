import { expect, test } from './fixtures';

for (const width of [320, 390, 1024, 1440]) {
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
            page.getByRole('button', { name: 'Zaloguj się', exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Adres e-mail')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByLabel('Hasło', { exact: true })).toBeFocused();
        await expect(page.getByLabel('Hasło', { exact: true })).toHaveCSS(
            'padding-right',
            '48px',
        );
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

for (const width of [390, 1024, 1440]) {
    test(`login validation, pending and reflow at ${width}px`, async ({
        page,
    }) => {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/login');
        await page.screenshot({
            path: `test-results/login-${width}.png`,
            fullPage: true,
        });
        let release!: () => void;
        const gate = new Promise<void>((resolve) => {
            release = resolve;
        });
        await page.route('**/login', async (route) => {
            if (route.request().method() === 'POST') await gate;
            await route.continue();
        });
        await page.getByLabel('Adres e-mail').fill('invalid');
        await page.getByRole('button', { name: 'Zaloguj się' }).click();
        await expect(
            page.getByRole('button', { name: 'Logowanie…' }),
        ).toBeDisabled();
        await expect(page.getByLabel('Adres e-mail')).toBeDisabled();
        await page.screenshot({
            path: `test-results/login-pending-${width}.png`,
            fullPage: true,
        });
        release();
        await expect(page.getByRole('alert')).toBeFocused();
        await expect(page.getByLabel('Adres e-mail')).toHaveValue('invalid');
        await expect(page.getByLabel('Hasło', { exact: true })).toHaveValue('');
        await expect(page.getByLabel('Adres e-mail')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await page.screenshot({
            path: `test-results/login-validation-${width}.png`,
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        await page.getByRole('link', { name: /Adres e-mail:/ }).click();
        await expect(page.getByLabel('Adres e-mail')).toBeFocused();
        await page.keyboard.press('Tab');
        await page.keyboard.press('Tab');
        await expect(
            page.getByRole('button', { name: 'Pokaż hasło' }),
        ).toBeFocused();
    });
}

for (const status of [419, 429]) {
    test(`login handles HTTP ${status} without replaying credentials`, async ({
        page,
    }) => {
        await page.goto('/login');
        const payload: unknown = JSON.parse(
            (await page.locator('script[data-page]').textContent()) ?? 'null',
        );
        if (
            !payload ||
            typeof payload !== 'object' ||
            !('props' in payload) ||
            !payload.props ||
            typeof payload.props !== 'object'
        )
            throw new Error('Invalid Inertia page');
        const message =
            status === 419
                ? 'Sesja wygasła. Zaloguj się ponownie.'
                : 'Zbyt wiele prób logowania. Spróbuj ponownie za 900 s.';
        Object.assign(payload.props, {
            errors: { form: message },
            retryAfter: status === 429 ? 900 : null,
        });
        let attempts = 0;
        await page.route('**/login', async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }
            attempts++;
            await route.fulfill({
                status,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Inertia': 'true',
                    ...(status === 429 ? { 'Retry-After': '900' } : {}),
                },
                body: JSON.stringify(payload),
            });
        });
        await page.getByLabel('Adres e-mail').fill('anna@example.com');
        await page.getByLabel('Hasło', { exact: true }).fill('secret');
        await page.getByRole('button', { name: 'Zaloguj się' }).click();
        await expect(page.getByRole('alert')).toContainText(message);
        await expect(page.getByLabel('Hasło', { exact: true })).toHaveValue('');
        await expect(
            page.getByRole('button', { name: 'Zaloguj się' }),
        ).toBeEnabled();
        expect(attempts).toBe(1);
        await page.screenshot({
            path: `test-results/login-${status}.png`,
            fullPage: true,
        });
    });
}

test('F-006 keeps the built MVP light with a legacy dark preference', async ({
    page,
    context,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await context.addCookies([
        { name: 'appearance', value: 'dark', url: 'http://127.0.0.1:8188' },
    ]);
    await page.addInitScript(() => localStorage.setItem('appearance', 'dark'));
    await page.goto('/login');
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await expect(page.locator('html')).toHaveCSS('color-scheme', 'light');
    await expect(page.locator('body')).toHaveCSS('font-family', /Public Sans/);
    await page.evaluate(() =>
        document.fonts.load('700 14px "Public Sans"', 'Zażółć gęślą jaźń'),
    );
    expect(
        await page.evaluate(() =>
            document.fonts.check('700 14px "Public Sans"', 'Zażółć gęślą jaźń'),
        ),
    ).toBe(true);
});
