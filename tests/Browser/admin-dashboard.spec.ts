import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

const seed = (...args: string[]) =>
    execFileSync(
        'php',
        ['tests/Infrastructure/seed-admin-dashboard.php', ...args],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_DATABASE: 'edudziennik_test',
                DB_URL: '',
            },
            stdio: 'inherit',
        },
    );
for (const [width, height] of [
    [390, 844],
    [1024, 768],
    [1440, 900],
    [320, 844],
]) {
    test(`admin dashboard counts, filters and keyboard at ${width}px`, async ({
        page,
    }, testInfo) => {
        seed();
        await page.setViewportSize({ width, height });
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('admin-dashboard@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-dashboard-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);
        await expect(
            page.getByRole('heading', { name: 'Panel administracyjny' }),
        ).toBeVisible();
        await expect(
            page.getByLabel('Ostatnie audyty ocen').locator('tbody tr'),
        ).toHaveCount(5);
        await expect(page.getByText('Nieaktywne konta: 1')).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`admin-dashboard-${width}.png`),
            fullPage: true,
        });
        const accounts = page.getByRole('link', {
            name: 'Pokaż konta',
            exact: true,
        });
        await accounts.focus();
        await expect(accounts).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/status=inactive/);
        await page.goto('/admin?term_id=999999');
        await expect(page).toHaveURL(/\/admin$/);
        await expect(page.getByRole('alert')).toBeFocused();
        await page.getByRole('combobox', { name: 'Semestr' }).focus();
        await page.keyboard.press('Enter');
        await page.getByRole('option', { name: /Semestr 2/ }).click();
        await expect(page).toHaveURL(/term_id=/);
        await expect(
            page.getByRole('heading', { name: 'Brak zmian ocen', exact: true }),
        ).toBeVisible();
        await page.reload();
        await expect(
            page.getByRole('heading', { name: 'Brak zmian ocen', exact: true }),
        ).toBeVisible();
        seed('empty');
        // Reseeding rotates the password hash; AuthenticateSession ends the old session.
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('admin-dashboard@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-dashboard-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);
        await expect(
            page.getByRole('heading', {
                name: 'Brak skonfigurowanych semestrów',
            }),
        ).toBeVisible();
    });
}
