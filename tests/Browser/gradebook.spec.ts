import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

// Each scenario covers login, validation, two writes and several full navigations.
test.setTimeout(60_000);

const seed = () =>
    JSON.parse(
        execFileSync('php', ['tests/Infrastructure/seed-gradebook.php'], {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_DATABASE: 'edudziennik_test',
                DB_URL: '',
            },
            encoding: 'utf8',
        }),
    ) as { assignment: number; enrollment: number };

for (const role of ['teacher', 'admin']) {
    for (const [width, height] of [
        [390, 844],
        [320, 844],
        [1024, 768],
        [1440, 900],
    ]) {
        test(`${role} roster and empty columns at ${width}px`, async ({
            page,
        }, testInfo) => {
            const ids = seed();
            await page.setViewportSize({ width, height });
            await page.goto('/login');
            await page
                .getByLabel('Adres e-mail')
                .fill(`gradebook-${role}@example.test`);
            await page
                .getByLabel('Hasło', { exact: true })
                .fill('Playwright-grades-1');
            await page
                .getByRole('button', { name: 'Zaloguj się', exact: true })
                .click();
            await expect(page).toHaveURL(new RegExp(`/${role}$`));
            const base = `/${role}/gradebooks/${ids.assignment}`;
            await page.goto(base);
            await expect(
                page.getByRole('heading', { name: '2A · Matematyka' }),
            ).toBeVisible();
            if (width < 768)
                await expect(
                    page.getByRole('list', { name: 'Karty uczniów' }),
                ).toBeVisible();
            else
                await expect(
                    page.getByRole('table', { name: 'Dziennik ocen' }),
                ).toBeVisible();
            await expect(
                page
                    .getByText('4,43', { exact: true })
                    .filter({ visible: true }),
            ).toBeVisible();
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
            await page.screenshot({
                path: testInfo.outputPath(`gradebook-${role}-${width}.png`),
                fullPage: true,
            });
            const add = page.getByRole('button', {
                name: 'Dodaj kolumnę',
                exact: true,
            });
            await add.focus();
            await page.keyboard.press('Enter');
            await expect(page.getByRole('dialog')).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(add).toBeFocused();
            await add.click();
            await page
                .getByRole('dialog')
                .getByRole('button', { name: 'Dodaj kolumnę', exact: true })
                .click();
            await expect(
                page.getByRole('dialog').getByRole('alert'),
            ).toBeFocused();
            await page.getByRole('combobox', { name: 'Kategoria' }).click();
            await page
                .getByRole('option', { name: 'Kartkówka', exact: true })
                .click();
            await page
                .getByLabel('Temat', { exact: true })
                .fill('Nowa kolumna');
            await page.getByLabel('Data kolumny').fill('2026-10-15');
            await page
                .getByRole('dialog')
                .getByRole('button', { name: 'Dodaj kolumnę', exact: true })
                .click();
            await expect(page.getByRole('dialog')).toHaveCount(0);
            await expect(
                page
                    .getByText('4,43', { exact: true })
                    .filter({ visible: true }),
            ).toBeVisible();
            await page.goto(`${base}?assessment_page=2`);
            await expect(
                page
                    .getByText('Nowa kolumna', { exact: false })
                    .filter({ visible: true })
                    .first(),
            ).toBeVisible();
            if (width < 768)
                await page
                    .getByText('Zarządzanie kolumnami', { exact: true })
                    .click();
            await page
                .getByRole('button', {
                    name: 'Usuń kolumnę Nowa kolumna',
                    exact: true,
                })
                .filter({ visible: true })
                .click();
            await expect(
                page.getByRole('button', { name: 'Anuluj' }),
            ).toBeFocused();
            await page.getByLabel('Potwierdzam usunięcie kolumny').check();
            await page
                .getByRole('button', { name: 'Usuń kolumnę', exact: true })
                .click();
            await expect(page.getByRole('dialog')).toHaveCount(0);
            await expect(add).toBeFocused();
            await page
                .getByRole('link', { name: 'Jan Uczeń 01', exact: true })
                .filter({ visible: true })
                .click();
            await expect(
                page.getByRole('heading', { name: 'Jan Uczeń 01' }),
            ).toBeVisible();
            await expect(
                page.getByText('Członkostwo historyczne', { exact: true }),
            ).toBeVisible();
            await expect(page.getByText('4,43', { exact: true })).toBeVisible();
            await page.screenshot({
                path: testInfo.outputPath(`student-${role}-${width}.png`),
                fullPage: true,
            });
            await page
                .getByRole('link', { name: 'Pełna historia zmian' })
                .click();
            await expect(page).toHaveURL(/student_enrollment_id=/);
            await page.goto(`${base}?q=Nieistniejacy`);
            await expect(
                page.getByRole('heading', { name: 'Brak wyników' }),
            ).toBeVisible();
            await page.goto(`/${role}/grades`);
            await expect(
                page.getByRole('heading', { name: 'Oceny', exact: true }),
            ).toBeVisible();
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
        });
    }
}
