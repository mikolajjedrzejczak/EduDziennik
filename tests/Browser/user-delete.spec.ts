import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

test.beforeAll(() => {
    execFileSync('php', ['tests/Infrastructure/seed-user-delete.php'], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_CONNECTION: 'mariadb',
            DB_DATABASE: 'edudziennik_test',
            DB_URL: '',
        },
        stdio: 'inherit',
    });
});
for (const [index, width] of [390, 1024, 1440, 320].entries()) {
    test(`cancel, validate and delete at ${width}px`, async ({
        page,
        browser,
    }, testInfo) => {
        await page.setViewportSize({
            width,
            height: width < 768 ? 844 : 900,
        });
        async function login(email: string) {
            await page.goto('/login');
            await page.getByLabel('Adres e-mail').fill(email);
            await page
                .getByLabel('Hasło', { exact: true })
                .fill('Playwright-create-1');
            await page
                .getByRole('button', { name: 'Zaloguj się', exact: true })
                .click();
        }
        await login('create-admin@example.test');
        await expect(page).toHaveURL(/\/admin$/);
        await page.goto('/admin/users/1');
        await expect(
            page.getByRole('button', { name: /Usuń konto:/ }),
        ).toHaveCount(0);
        const targetId = index + 3;
        const other = await browser.newContext();
        const targetPage = await other.newPage();
        await targetPage.goto('http://127.0.0.1:8189/login');
        await targetPage
            .getByLabel('Adres e-mail')
            .fill(`delete-target-${index}@example.test`);
        await targetPage
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-create-1');
        await targetPage
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(targetPage).toHaveURL(/\/teacher$/);
        await page.goto(
            width < 768
                ? `/admin/users/${targetId}`
                : `/admin/users?q=delete-target-${index}%40example.test`,
        );
        const trigger = page.getByRole('button', {
            name: 'Usuń konto: Nowak Anna',
        });
        await trigger.click();
        const input = page.getByLabel(/Aby potwierdzić/);
        await expect(input).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toBeHidden();
        await expect(trigger).toBeFocused();
        await trigger.click();
        await page.getByRole('button', { name: 'Anuluj' }).click();
        await expect(trigger).toBeFocused();
        await trigger.click();
        await input.fill('Nowak');
        await page.screenshot({
            path: testInfo.outputPath(`delete-clean-${width}.png`),
            fullPage: false,
            animations: 'disabled',
        });
        await input.fill('Literówka');
        await page
            .getByRole('button', { name: 'Usuń konto', exact: true })
            .click();
        await expect(page.getByRole('dialog').getByRole('alert')).toBeFocused();
        await expect(input).toHaveAttribute('aria-invalid', 'true');
        await input.fill('Nowak');
        await page.screenshot({
            path: testInfo.outputPath(`delete-${width}.png`),
            fullPage: false,
            animations: 'disabled',
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page
            .getByRole('button', { name: 'Usuń konto', exact: true })
            .focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/admin\/users$/);
        await expect(
            page.locator(`a[href="/admin/users/${targetId}"]`),
        ).toHaveCount(0);
        await targetPage.reload();
        await expect(targetPage).toHaveURL(/\/login$/);
        await page.goto(`/admin/users/${targetId}`);
        await expect(page.getByText(/Konto zostało usunięte/)).toBeVisible();
        await expect(
            page.getByRole('button', { name: /Usuń konto:/ }),
        ).toHaveCount(0);
        await other.close();
    });
}

test('teacher direct delete is forbidden and leaves the target intact', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill('create-existing@example.test');
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-create-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(/\/teacher$/);
    const status = await page.evaluate(async () => {
        const token =
            document.cookie
                .split('; ')
                .find((item) => item.startsWith('XSRF-TOKEN='))
                ?.slice(11) ?? '';
        const response = await fetch('/admin/users/1', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(token),
                Accept: 'application/json',
            },
            body: JSON.stringify({ confirmation_last_name: 'Testowa' }),
        });
        return response.status;
    });
    expect(status).toBe(403);
});
