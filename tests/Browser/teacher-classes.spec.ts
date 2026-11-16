import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

const seed = (...args: string[]) =>
    execFileSync(
        'php',
        ['tests/Infrastructure/seed-teacher-classes.php', ...args],
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
    test(`teacher selects an exact assignment and loses revoked access at ${width}px`, async ({
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
        await page
            .getByRole('link', { name: /Moje klasy|Klasy/ })
            .first()
            .click();
        await expect(
            page.getByRole('heading', { name: 'Moje klasy', exact: true }),
        ).toBeVisible();
        const math = page.getByRole('link', {
            name: /Otwórz dziennik: .*Matematyka/,
        });
        const physics = page.getByRole('link', {
            name: /Otwórz dziennik: .*Fizyka/,
        });
        const mathUrl = await math.getAttribute('href');
        expect(await physics.getAttribute('href')).not.toBe(mathUrl);
        await math.focus();
        await expect(math).toBeFocused();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`teacher-classes-${width}.png`),
            fullPage: true,
        });
        await page.keyboard.press('Enter');
        await expect(
            page.getByRole('heading', { name: '2A · Matematyka', exact: true }),
        ).toBeVisible();
        await page.goto('/teacher/classes');
        await page.getByRole('combobox', { name: 'Semestr' }).click();
        await page.getByRole('option', { name: /Semestr 2/ }).click();
        await page
            .getByRole('button', { name: 'Filtruj', exact: true })
            .click();
        await expect(page).toHaveURL(/term_id=/);
        await page
            .getByRole('link', { name: /Otwórz dziennik: .*Fizyka/ })
            .click();
        await expect(
            page.getByRole('status').filter({ hasText: 'Semestr zamknięty' }),
        ).toBeVisible();
        await page.goto('/teacher/classes?per_page=3');
        await expect(page.getByRole('alert')).toBeFocused();
        await expect(page).not.toHaveURL(/per_page=3/);
        seed('revoke');
        await page.goto(mathUrl!);
        await expect(
            page.getByRole('heading', { name: /Nie znaleziono/ }),
        ).toBeVisible();
        await page.goto('/teacher/classes');
        await expect(math).toHaveCount(0);
        await expect(physics).toBeVisible();
        await page.getByLabel('Klasa lub przedmiot').fill('Nieistniejąca');
        await page
            .getByRole('button', { name: 'Filtruj', exact: true })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeVisible();
        await expect(page).toHaveURL(/q=Nieistniej/);
    });
}
