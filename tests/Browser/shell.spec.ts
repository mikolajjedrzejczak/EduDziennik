import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const roles = [
    { role: 'student', path: '/student', h1: 'Pulpit ucznia' },
    { role: 'teacher', path: '/teacher', h1: 'Pulpit nauczyciela' },
    { role: 'admin', path: '/admin', h1: 'Pulpit administratora' },
] as const;
const viewports = [
    { width: 390, height: 844, nav: 'Nawigacja mobilna' },
    { width: 1024, height: 768, nav: 'Główna nawigacja' },
    { width: 1440, height: 900, nav: 'Główna nawigacja' },
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

async function login(page: Page, role: string) {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill(`shell-${role}@example.test`);
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-shell-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
}

for (const { role, path, h1 } of roles) {
    for (const { width, height, nav } of viewports) {
        test(`${role} lands on own dashboard at ${width}px with visible navigation and logout`, async ({
            page,
        }, testInfo) => {
            await page.setViewportSize({ width, height });
            await login(page, role);
            await expect(page).toHaveURL(new RegExp(`${path}$`));
            await expect(
                page.getByRole('heading', { level: 1, name: h1 }),
            ).toBeVisible();
            await expect(
                page.getByRole('navigation', { name: nav }),
            ).toBeVisible();
            await expect(page.getByRole('navigation')).toHaveCount(
                width >= 768 ? 2 : 1,
            );
            await expect(
                page
                    .getByRole('navigation', { name: nav })
                    .getByRole('link', { name: /Pulpit|Start/ }),
            ).toHaveAttribute('aria-current', 'page');

            const sidebar = page.locator('aside');
            if (width >= 1280) {
                expect((await sidebar.boundingBox())?.width).toBe(248);
            } else if (width >= 768) {
                expect((await sidebar.boundingBox())?.width).toBe(72);
            } else {
                await expect(sidebar).toHaveCount(0);
            }

            const logout = page.getByRole('button', { name: 'Wyloguj' });
            const box = await logout.boundingBox();
            expect(box).not.toBeNull();
            expect(box!.x).toBeGreaterThanOrEqual(0);
            expect(box!.x + box!.width).toBeLessThanOrEqual(width);
            expect(box!.y + box!.height).toBeLessThanOrEqual(height);
            expect(box!.width).toBeGreaterThanOrEqual(44);
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
            await page.screenshot({
                path: testInfo.outputPath(`${role}-${width}.png`),
            });

            await logout.click();
            await expect(page).toHaveURL(/\/login$/);
            await page.goto(path);
            await expect(page).toHaveURL(/\/login$/);
        });
    }

    test(`${role} cannot open another role's dashboard`, async ({ page }) => {
        await login(page, role);
        await expect(page).toHaveURL(new RegExp(`${path}$`));
        for (const other of roles.filter((r) => r.role !== role)) {
            const response = await page.goto(other.path);
            expect(response?.status()).toBe(403);
            await expect(
                page.getByRole('heading', {
                    name: 'Nie masz dostępu do tej strony',
                }),
            ).toBeVisible();
        }
        await page.goto('/dashboard');
        await expect(page).toHaveURL(new RegExp(`${path}$`));
    });
}

test('keyboard reaches skip link, navigation and logout in order; 390px bar yields to the keyboard', async ({
    page,
}) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, 'student');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Pulpit ucznia' }),
    ).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('link', { name: 'Przejdź do treści' }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Pulpit' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('link', { name: 'Profil i hasło' }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Wyloguj' })).toBeFocused();

    await page.setViewportSize({ width: 390, height: 500 });
    await page.getByRole('link', { name: 'Profil' }).click();
    await expect(page).toHaveURL(/\/settings\/profile$/);
    await expect(
        page.getByRole('navigation', { name: 'Nawigacja mobilna' }),
    ).toBeVisible();
    await page.locator('input').first().focus();
    await expect(
        page.getByRole('navigation', { name: 'Nawigacja mobilna' }),
    ).toHaveCount(0);
});

test('guest is sent to login from every dashboard route', async ({ page }) => {
    for (const path of ['/dashboard', '/student', '/teacher', '/admin']) {
        await page.goto(path);
        await expect(page).toHaveURL(/\/login$/);
    }
});
