import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const viewports = [
    { width: 390, height: 844 },
    { width: 1024, height: 768 },
    { width: 1440, height: 900 },
] as const;

test.beforeAll(() => {
    execFileSync('php', ['tests/Infrastructure/seed-shell-users.php'], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE: 'edudziennik_test',
            DB_URL: '',
        },
        stdio: 'inherit',
    });
});

async function loginAsAdmin(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill('shell-admin@example.test');
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-shell-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
}

const noHorizontalScroll = (page: Page) =>
    page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
    );

for (const { width, height } of viewports) {
    test(`admin manages the subject dictionary at ${width}px`, async ({
        page,
    }, testInfo) => {
        const suffix = `${width}-${Date.now()}`;
        const name = `Matematyka ${suffix}`;
        const renamed = `Algebra ${suffix}`;
        await page.setViewportSize({ width, height });
        await loginAsAdmin(page);

        await page.goto('/admin/subjects');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Przedmioty' }),
        ).toBeVisible();
        await page
            .getByRole('link', { name: /Przedmioty/ })
            .first()
            .waitFor();

        // Create (keyboard-reachable dialog, focus returns on Escape).
        const add = page
            .getByRole('button', { name: 'Dodaj przedmiot' })
            .first();
        await add.click();
        const dialog = page.getByRole('dialog');
        await expect(dialog.getByLabel('Nazwa przedmiotu')).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(add).toBeFocused();

        await add.click();
        await dialog.getByLabel('Nazwa przedmiotu').fill(name);
        await dialog.getByRole('button', { name: 'Dodaj przedmiot' }).click();
        await expect(dialog).toBeHidden();
        await expect(page.getByText(name, { exact: true })).toBeVisible();
        expect(await noHorizontalScroll(page)).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`list-${width}.png`),
        });

        // Duplicate with different case: validation error, input kept, no second row.
        await add.click();
        await dialog.getByLabel('Nazwa przedmiotu').fill(name.toUpperCase());
        await dialog.getByRole('button', { name: 'Dodaj przedmiot' }).click();
        await expect(dialog.getByRole('alert')).toBeFocused();
        await expect(dialog.getByLabel('Nazwa przedmiotu')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await expect(dialog.getByLabel('Nazwa przedmiotu')).toHaveValue(
            name.toUpperCase(),
        );
        await page.screenshot({
            path: testInfo.outputPath(`error-${width}.png`),
        });
        await dialog.getByRole('button', { name: 'Anuluj' }).click();

        // Rename.
        await page
            .getByRole('button', { name: `Zmień nazwę: ${name}` })
            .click();
        await dialog.getByLabel('Nazwa przedmiotu').fill(renamed);
        await dialog.getByRole('button', { name: 'Zapisz nazwę' }).click();
        await expect(page.getByText(renamed, { exact: true })).toBeVisible();

        // Archive: destructive dialog focuses Cancel; the subject moves to the archive filter.
        await page
            .getByRole('button', { name: `Archiwizuj: ${renamed}` })
            .click();
        await expect(
            dialog.getByRole('button', { name: 'Anuluj' }),
        ).toBeFocused();
        await dialog.getByRole('button', { name: 'Archiwizuj' }).click();
        await expect(page.getByText(renamed, { exact: true })).toBeHidden();
        await page.getByRole('button', { name: 'Zarchiwizowane' }).click();
        await expect(page).toHaveURL(/archived=1/);
        await expect(page.getByText(renamed, { exact: true })).toBeVisible();
        await expect(
            page.getByText('Zarchiwizowany', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: `Zmień nazwę: ${renamed}` }),
        ).toHaveCount(0);
        expect(await noHorizontalScroll(page)).toBe(true);
    });
}

test('search without matches shows the no-results state and clears the filter', async ({
    page,
}) => {
    await loginAsAdmin(page);
    await page.goto('/admin/subjects');
    await page
        .getByRole('searchbox', { name: 'Szukaj przedmiotów' })
        .first()
        .fill('zzz-brak-takiego');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/q=zzz-brak-takiego/);
    await expect(page.getByText('Brak wyników')).toBeVisible();
    await page.getByRole('button', { name: 'Wyczyść filtry' }).click();
    await expect(page).not.toHaveURL(/q=/);
});

test('non-admins get 403 on the subject dictionary', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill('shell-teacher@example.test');
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-shell-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(/\/teacher$/);
    const response = await page.goto('/admin/subjects');
    expect(response?.status()).toBe(403);
});
