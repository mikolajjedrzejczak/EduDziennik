import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';
const seed = (arg?: string) =>
    execFileSync(
        'php',
        ['tests/Infrastructure/seed-student-grades.php', ...(arg ? [arg] : [])],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_DATABASE: 'edudziennik_test',
                DB_URL: '',
            },
            encoding: 'utf8',
        },
    );
let ids: { active: number; deleted: number };
test.beforeAll(() => {
    seed();
    ids = JSON.parse(seed('history-ids')) as typeof ids;
});
test.afterAll(() => seed('clean'));
for (const width of [390, 1024, 1440, 320]) {
    test(`history filters, snapshots and keyboard at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.getByLabel('Adres e-mail').fill('student7@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-grades-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/student$/);
        await page.goto(`/student/grades/${ids.deleted}`);
        await page
            .getByRole('link', { name: 'Historia tej oceny', exact: true })
            .click();
        await expect(page).toHaveURL(
            new RegExp(`/student/grades/${ids.deleted}/history$`),
        );
        await expect(
            page.getByRole('link', {
                name: width < 768 ? 'Historia' : 'Historia zmian',
                exact: true,
            }),
        ).toHaveAttribute('aria-current', 'page');
        const region = page.getByRole('region', { name: 'Historia ocen' });
        for (const action of ['Dodanie', 'Zmiana', 'Usunięcie'])
            await expect(
                region.getByText(action, { exact: true }),
            ).toBeVisible();
        await expect(region).toContainText('Fixture: korekta punktów');
        await region
            .getByText('Wartości przed i po', { exact: true })
            .first()
            .click();
        await expect(
            region.getByText('Po korekcie', { exact: true }).first(),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`history-${width}.png`),
            fullPage: true,
        });
        if (width < 768) {
            const trigger = page.getByRole('button', {
                name: 'Filtry',
                exact: true,
            });
            await trigger.click();
            await expect(
                page.getByRole('dialog', { name: 'Filtry historii' }),
            ).toBeVisible();
            await page.screenshot({
                path: testInfo.outputPath(`history-filters-${width}.png`),
                fullPage: true,
            });
            await page.keyboard.press('Escape');
            await expect(trigger).toBeFocused();
            await trigger.click();
        }
        await page
            .getByRole('combobox', { name: 'Operacja', exact: true })
            .click();
        await page.getByRole('option', { name: 'Zmiana', exact: true }).click();
        await page
            .getByRole('button', { name: 'Pokaż wyniki', exact: true })
            .click();
        await expect(page).toHaveURL(/action=update/);
        await expect(region.getByText('Zmiana', { exact: true })).toBeVisible();
        await expect(
            region.getByText('Usunięcie', { exact: true }),
        ).toHaveCount(0);
        await page.reload();
        await expect(region.getByText('Zmiana', { exact: true })).toBeVisible();
        await page.goto(
            '/student/history?date_from=2020-01-01&date_to=2020-01-02',
        );
        await expect(
            page.getByText('Brak wpisów historii', { exact: true }),
        ).toBeVisible();
        await page.goto('/student/grades/99999999/history');
        await expect(
            page.getByRole('heading', { name: 'Nie znaleziono strony' }),
        ).toBeVisible();
    });
}
