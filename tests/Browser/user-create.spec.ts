import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const viewports = [
    { width: 390, height: 844 },
    { width: 1024, height: 768 },
    { width: 1440, height: 900 },
] as const;

test.beforeAll(() => {
    execFileSync('php', ['tests/Infrastructure/seed-user-create.php'], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE: 'edudziennik_test',
            DB_URL: '',
        },
        stdio: 'inherit',
    });
});

async function select(page: Page, label: string, option: string) {
    await page.getByRole('combobox', { name: label, exact: true }).click();
    await page.getByRole('option', { name: option, exact: true }).click();
}

for (const { width, height } of viewports) {
    test(`admin sees validation errors, then creates a teacher with a scope at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width, height });
        await page.goto('/login');
        await page.getByLabel('Adres e-mail').fill('create-admin@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-create-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);

        await page.goto('/admin/users/create');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Dodaj użytkownika' }),
        ).toBeVisible();

        // Validation: duplicate email + short password; the summary takes the focus, the password is cleared.
        await page.getByLabel('Imię').fill('Agnieszka');
        await page.getByLabel('Nazwisko').fill('Szymańska');
        await page
            .getByLabel('Adres e-mail (login)')
            .fill('CREATE-EXISTING@example.test');
        await page.getByLabel('Hasło', { exact: true }).fill('short');
        await page.getByLabel('Powtórz hasło').fill('short');
        await page.getByRole('button', { name: 'Utwórz konto' }).click();
        const summary = page.getByRole('alert').first();
        await expect(summary).toContainText('Popraw 2 pola');
        await expect(summary).toBeFocused();
        await expect(page.getByLabel('Hasło', { exact: true })).toHaveValue('');
        await expect(page.getByLabel('Adres e-mail (login)')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await page.screenshot({
            path: testInfo.outputPath(`user-create-errors-${width}.png`),
            fullPage: true,
        });

        // Fix and create a teacher with one assignment.
        const email = `agnieszka.${width}@example.test`;
        await page.getByLabel('Adres e-mail (login)').fill(email);
        await page.getByRole('radio', { name: /Nauczyciel/ }).check();
        await page.getByRole('button', { name: 'Dodaj przydział' }).click();
        await select(page, 'Przedmiot', 'Matematyka');
        await select(page, 'Klasa', '2A · 2026/2027');
        await select(page, 'Semestr', '2026/2027 · semestr 1');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Str0ng-Passw0rd!x');
        await page.getByLabel('Powtórz hasło').fill('Str0ng-Passw0rd!x');
        await page.screenshot({
            path: testInfo.outputPath(`user-create-form-${width}.png`),
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.getByRole('button', { name: 'Utwórz konto' }).click();

        await expect(page).toHaveURL(/\/admin\/users\/\d+$/);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: /Szymańska Agnieszka/,
            }),
        ).toBeVisible();
        await expect(page.getByText(email)).toBeVisible();
        await expect(page.getByText('Matematyka').first()).toBeVisible();
    });
}
