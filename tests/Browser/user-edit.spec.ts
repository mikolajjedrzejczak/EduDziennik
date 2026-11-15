import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

test.beforeAll(() => {
    execFileSync('php', ['tests/Infrastructure/seed-user-edit.php'], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE: 'edudziennik_test',
            DB_URL: '',
        },
        stdio: 'inherit',
    });
});

for (const [index, width] of [390, 1024, 1440].entries()) {
    test(`edits status with validation and keyboard at ${width}px`, async ({
        page,
        browser,
    }, testInfo) => {
        await page.setViewportSize({
            width,
            height: width === 390 ? 844 : 1000,
        });
        await page.goto('/login');
        await page.getByLabel('Adres e-mail').fill('create-admin@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-create-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);
        const targetId = index + 3;
        const other = await browser.newContext();
        const targetPage = await other.newPage();
        await targetPage.goto('http://127.0.0.1:8189/login');
        await targetPage
            .getByLabel('Adres e-mail')
            .fill(`edit-target-${index}@example.test`);
        await targetPage
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-create-1');
        await targetPage
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(targetPage).toHaveURL(/\/teacher$/);

        await page.goto(`/admin/users/${targetId}`);
        await page.getByRole('link', { name: 'Edytuj użytkownika' }).click();
        await expect(page).toHaveURL(
            new RegExp(`/admin/users/${targetId}/edit$`),
        );
        await expect(
            page.getByLabel('Nowe hasło (opcjonalnie)', { exact: true }),
        ).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(
            'Nowak Anna',
        );
        await page.screenshot({
            path: testInfo.outputPath(`user-edit-clean-${width}.png`),
            fullPage: true,
        });
        await page
            .getByLabel('Adres e-mail (login)')
            .fill('CREATE-EXISTING@example.test');
        await page
            .getByLabel('Nowe hasło (opcjonalnie)', { exact: true })
            .fill('short');
        await page.getByRole('button', { name: 'Zapisz zmiany' }).click();
        await expect(page.getByRole('alert').first()).toBeFocused();
        await expect(page.getByLabel('Adres e-mail (login)')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await expect(
            page.getByLabel('Nowe hasło (opcjonalnie)', { exact: true }),
        ).toHaveValue('');
        await page
            .getByLabel('Adres e-mail (login)')
            .fill(`edit-target-${index}@example.test`);
        await page
            .getByRole('combobox', { name: 'Status konta', exact: true })
            .focus();
        await page.keyboard.press('Space');
        await page
            .getByRole('option', { name: 'Nieaktywne', exact: true })
            .click();
        await expect(page.getByRole('listbox')).toBeHidden();
        await page.screenshot({
            path: testInfo.outputPath(`user-edit-${width}.png`),
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.getByRole('button', { name: 'Zapisz zmiany' }).focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(new RegExp(`/admin/users/${targetId}$`));
        await expect(
            page.getByText('Nieaktywne', { exact: true }),
        ).toBeVisible();
        await targetPage.reload();
        await expect(targetPage).toHaveURL(/\/login$/);
        await other.close();
    });
}
