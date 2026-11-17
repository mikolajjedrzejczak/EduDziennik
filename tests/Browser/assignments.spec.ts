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

for (const { width, height } of viewports) {
    test(`historical filters survive reload and stay out of create at ${width}px`, async ({
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
        await page
            .getByRole('button', { name: 'Cofnięte', exact: true })
            .click();
        const labels = [
            ['Nauczyciel', 'Jan Nieaktywny (nieaktywny)'],
            ['Klasa', '1B · 2025/2026 (zarchiwizowana)'],
            ['Przedmiot', 'Geografia (zarchiwizowany)'],
            ['Semestr', 'Semestr 1 · 2025/2026 (zamknięty)'],
        ] as const;
        for (const [label, option] of labels) {
            const previousUrl = page.url();
            await select(page, label, option);
            await expect(page).not.toHaveURL(previousUrl);
            await expect(
                page.getByRole('combobox', { name: label, exact: true }),
            ).toContainText(option);
        }
        await expect(
            page
                .getByRole('region', { name: 'Lista przydziałów' })
                .getByText('Jan Nieaktywny', { exact: true }),
        ).toBeVisible();
        await page.reload();
        for (const [label, option] of labels) {
            await expect(
                page.getByRole('combobox', { name: label, exact: true }),
            ).toContainText(option);
        }
        const previousUrl = page.url();
        await select(page, 'Nauczyciel', 'Jan Usunięty (usunięty)');
        await expect(page).not.toHaveURL(previousUrl);
        await page.reload();
        await expect(
            page.getByRole('combobox', { name: 'Nauczyciel', exact: true }),
        ).toContainText('Jan Usunięty (usunięty)');
        await expect(
            page
                .getByRole('region', { name: 'Lista przydziałów' })
                .getByText('Jan Usunięty', { exact: true }),
        ).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath(`historical-filters-${width}.png`),
            fullPage: true,
        });

        await page
            .getByRole('button', { name: 'Przypisz nauczyciela', exact: true })
            .first()
            .click();
        const dialog = page.getByRole('dialog');
        const current = [
            'Anna Nowak',
            '2A · 2026/2027',
            'Matematyka',
            'Semestr 1 · 2026/2027',
        ];
        for (const [index, [label, option]] of labels.entries()) {
            await dialog
                .getByRole('combobox', { name: label, exact: true })
                .click();
            await expect(
                page.getByRole('option', { name: option, exact: true }),
            ).toHaveCount(0);
            await expect(
                page.getByRole('option', {
                    name: 'Jan Usunięty (usunięty)',
                    exact: true,
                }),
            ).toHaveCount(0);
            await page
                .getByRole('option', { name: current[index], exact: true })
                .click();
        }
        await page.keyboard.press('Escape');
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    });
}
