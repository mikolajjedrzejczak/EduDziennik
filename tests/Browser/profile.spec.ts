import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const seed = (...args: string[]) =>
    execFileSync('php', ['tests/Infrastructure/seed-profile.php', ...args], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE: 'edudziennik_test',
            DB_URL: '',
        },
        stdio: 'inherit',
    });
const login = async (
    page: Page,
    role = 'student',
    password = 'Profile-password-123',
) => {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill(`profile-${role}@example.test`);
    await page.getByLabel('Hasło', { exact: true }).fill(password);
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(new RegExp(`/${role}$`));
    await page.goto('/profile');
};
const fillPassword = async (page: Page, current: string) => {
    await page.getByLabel('Obecne hasło', { exact: true }).fill(current);
    await page
        .getByLabel('Nowe hasło', { exact: true })
        .fill('Changed-password-123');
    await page
        .getByLabel('Powtórz nowe hasło', { exact: true })
        .fill('Changed-password-123');
};
test.beforeEach(() => seed());
test.afterAll(() => seed('clean'));
for (const width of [320, 390, 1024, 1440]) {
    test(`profile names, validation and keyboard at ${width}px`, async ({
        page,
    }, info) => {
        await page.setViewportSize({
            width,
            height: width === 1024 ? 768 : 844,
        });
        await login(page);
        await expect(
            page.getByLabel('E-mail', { exact: true }),
        ).toHaveAttribute('readonly');
        await page.getByLabel('Imię', { exact: true }).fill('Łukasz');
        await page.getByRole('button', { name: 'Zapisz dane' }).focus();
        await page.keyboard.press('Enter');
        await expect(
            page.getByRole('status').filter({ hasText: /został/ }),
        ).toContainText('Dane profilu zostały zapisane.');
        await page.reload();
        await expect(page.getByLabel('Imię', { exact: true })).toHaveValue(
            'Łukasz',
        );
        await page.screenshot({
            path: info.outputPath(`profile-${width}.png`),
            fullPage: true,
        });
        await fillPassword(page, 'Wrong-password-123');
        await page
            .getByRole('button', { name: 'Zmień hasło', exact: true })
            .click();
        await expect(page.getByRole('alert')).toBeFocused();
        await expect(
            page.getByLabel('Obecne hasło', { exact: true }),
        ).toHaveValue('');
        await expect(
            page.getByLabel('Nowe hasło', { exact: true }),
        ).toHaveValue('');
        await page
            .getByRole('link', {
                name: /Obecne hasło: Obecne hasło jest nieprawidłowe/,
            })
            .click();
        await expect(
            page.getByLabel('Obecne hasło', { exact: true }),
        ).toBeFocused();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: info.outputPath(`profile-error-${width}.png`),
            fullPage: true,
        });
    });
}
test('password revokes second browser session, keeps current session and leaves no history secrets', async ({
    page,
    browser,
}) => {
    const second = await browser.newContext();
    const other = await second.newPage();
    try {
        await login(page);
        await login(other);
        await fillPassword(page, 'Profile-password-123');
        await page
            .getByRole('button', { name: 'Zmień hasło', exact: true })
            .click();
        await expect(
            page.getByRole('status').filter({ hasText: /został/ }),
        ).toContainText('Hasło zostało zmienione.');
        await page.reload();
        await expect(page).toHaveURL(/\/profile$/);
        await other.reload();
        await expect(other).toHaveURL(/\/login$/);
        await expect(
            page.getByLabel('Nowe hasło', { exact: true }),
        ).toHaveValue('');
        const state = await page.evaluate(() => JSON.stringify(history.state));
        expect(state).not.toContain('Profile-password-123');
        expect(state).not.toContain('Changed-password-123');
        await page.goto('/student');
        await page.goBack();
        await expect(
            page.getByLabel('Nowe hasło', { exact: true }),
        ).toHaveValue('');
        await login(other, 'student', 'Changed-password-123');
    } finally {
        await second.close();
    }
});
for (const role of ['teacher', 'admin']) {
    test(`${role} can use own profile`, async ({ page }) => {
        await login(page, role);
        await page.getByLabel('Nazwisko', { exact: true }).fill('Kowalska');
        await page.getByRole('button', { name: 'Zapisz dane' }).click();
        await expect(
            page.getByRole('status').filter({ hasText: /został/ }),
        ).toContainText('Dane profilu zostały zapisane.');
    });
}
