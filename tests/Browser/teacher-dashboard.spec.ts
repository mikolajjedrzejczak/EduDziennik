import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

const seed = (...args: string[]) =>
    execFileSync(
        'php',
        ['tests/Infrastructure/seed-teacher-dashboard.php', ...args],
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
    test(`teacher dashboard opens its exact journal by keyboard at ${width}px`, async ({
        page,
    }, testInfo) => {
        seed();
        await page.setViewportSize({ width, height });
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('teacher-classes@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-classes-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/teacher$/);
        const summary = page.getByLabel('Podsumowanie semestru');
        await expect(
            summary.locator('dd').filter({ hasText: /^2$/ }),
        ).toHaveCount(3);
        await expect(
            page
                .getByRole('list')
                .filter({ has: page.locator('time') })
                .getByRole('listitem'),
        ).toHaveCount(5);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`teacher-dashboard-${width}.png`),
            fullPage: true,
        });
        const journal = page
            .getByRole('navigation', { name: 'Moje dzienniki' })
            .getByRole('link', { name: /Matematyka/ });
        await journal.focus();
        await expect(journal).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(
            page.getByRole('heading', { name: '2A · Matematyka', exact: true }),
        ).toBeVisible();
        await page.goto('/teacher?student_id=999');
        await expect(page).toHaveURL(/\/teacher$/);
        await expect(page.getByRole('alert')).toBeFocused();
        await page.getByRole('combobox', { name: 'Semestr' }).focus();
        await page.keyboard.press('Enter');
        await page.getByRole('option', { name: /Semestr 2/ }).click();
        await expect(page).toHaveURL(/term_id=/);
        await expect(
            page.getByRole('heading', { name: 'Brak ocen', exact: true }),
        ).toBeVisible();
        await page.reload();
        await expect(
            page.getByRole('heading', { name: 'Brak ocen', exact: true }),
        ).toBeVisible();
        seed('empty');
        // Reseeding rotates the password hash; AuthenticateSession ends the old session.
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('teacher-classes@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-classes-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/teacher$/);
        await expect(
            page.getByRole('heading', { name: 'Brak przypisań', exact: true }),
        ).toBeVisible();
    });
}
