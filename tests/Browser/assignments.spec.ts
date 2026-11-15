import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const viewports = [
    { width: 390, height: 844 },
    { width: 1024, height: 768 },
    { width: 1440, height: 900 },
] as const;

test.beforeAll(() => {
    execFileSync('php', ['tests/Infrastructure/seed-assignment-users.php'], {
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
    test(`admin grants, revokes and restores a teaching scope at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width, height });
        await page.goto('/login');
        await page.getByLabel('Adres e-mail').fill('assign-admin@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-assign-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);

        await page.goto('/admin/assignments');
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'Przydziały nauczycieli',
            }),
        ).toBeVisible();

        // Open the grant dialog from the keyboard-reachable button; Escape returns focus.
        const grant = page
            .getByRole('button', { name: 'Przypisz nauczyciela' })
            .first();
        await grant.click();
        const dialog = page.getByRole('dialog');
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(grant).toBeFocused();

        await grant.click();
        await select(page, 'Nauczyciel', 'Anna Nowak');
        await select(page, 'Klasa', '2A · 2026/2027');
        await select(page, 'Przedmiot', 'Matematyka');
        await select(page, 'Semestr', 'Semestr 1 · 2026/2027');
        await dialog
            .getByRole('button', { name: 'Przypisz nauczyciela' })
            .click();
        await expect(dialog).toBeHidden();
        await expect(
            page
                .getByRole('region', { name: 'Lista przydziałów' })
                .getByText('Anna Nowak')
                .first(),
        ).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath(`assignments-${width}.png`),
            fullPage: true,
        });

        // Revoke: Cancel gets the focus, the row moves to the revoked list.
        await page
            .getByRole('button', { name: /Cofnij przydział: Matematyka/ })
            .click();
        await expect(
            page.getByRole('button', { name: 'Anuluj' }),
        ).toBeFocused();
        await dialog
            .getByRole('button', { name: 'Cofnij przydział', exact: true })
            .click();
        await expect(dialog).toBeHidden();
        await expect(
            page.getByRole('heading', { name: 'Brak przydziałów' }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Cofnięte' }).click();
        await expect(page).toHaveURL(/revoked=1/);
        await page
            .getByRole('button', { name: /Przywróć przydział: Matematyka/ })
            .click();
        await dialog
            .getByRole('button', { name: 'Przywróć przydział', exact: true })
            .click();
        await expect(dialog).toBeHidden();
        await page.getByRole('button', { name: 'Aktywne' }).click();
        await expect(
            page.getByRole('button', { name: /Cofnij przydział: Matematyka/ }),
        ).toBeVisible();

        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    });
}
