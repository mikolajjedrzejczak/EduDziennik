import { expect, test } from '@playwright/test';

for (const width of [390, 1024, 1440, 320]) {
    test(`server validation and keyboard feedback at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        await page.goto('/__tests/forms');
        await page.getByLabel('Adres e-mail', { exact: true }).fill('błędny');
        await page.getByLabel('Liczba', { exact: true }).fill('7');
        await page
            .getByLabel('Komentarz', { exact: true })
            .fill('Zażółć gęślą jaźń');
        await page.getByLabel('Hasło', { exact: true }).fill('sekret');
        await page.getByLabel('Powtórz hasło', { exact: true }).fill('inny');
        // Hold the real request briefly to inspect pending, then let PHP validate it.
        await page.route('**/__tests/forms/allowed', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            await route.continue();
        });
        await page.getByRole('button', { name: 'Zapisz' }).click();
        await expect(
            page.getByRole('button', { name: 'Zapisz' }),
        ).toBeDisabled();
        await expect(page.getByRole('alert')).toBeFocused();
        await expect(page.getByLabel('Komentarz')).toHaveValue(
            'Zażółć gęślą jaźń',
        );
        await expect(page.getByLabel('Liczba', { exact: true })).toHaveValue(
            '7',
        );
        await expect(page.getByLabel('Hasło', { exact: true })).toHaveValue('');
        await expect(
            page.getByLabel('Adres e-mail', { exact: true }),
        ).toHaveAttribute('aria-invalid', 'true');
        await expect(
            page.getByLabel('Adres e-mail', { exact: true }),
        ).toHaveCSS('border-top-color', 'rgb(185, 28, 28)');
        await expect(
            page.getByLabel('Adres e-mail', { exact: true }),
        ).toHaveCSS('height', width < 768 ? '48px' : '40px');
        await page.keyboard.press('Tab');
        await expect(page.getByRole('link').first()).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(
            page.getByLabel('Adres e-mail', { exact: true }),
        ).toBeFocused();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);

        await page.screenshot({
            path: testInfo.outputPath('validation.png'),
            fullPage: true,
        });
        await page
            .getByLabel('Adres e-mail', { exact: true })
            .fill('test@example.org');
        await page.getByLabel('Liczba', { exact: true }).fill('2');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('długie-testowe-hasło');
        await page
            .getByLabel('Powtórz hasło', { exact: true })
            .fill('długie-testowe-hasło');
        await page.getByRole('button', { name: 'Zapisz' }).click();
        await expect(page.getByRole('status')).toHaveText(
            'Formularz przyjęty.',
        );
        await expect(page.getByRole('alert')).toHaveCount(0);
        expect(pageErrors).toEqual([]);
    });
}
