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
    ids = JSON.parse(seed('detail-ids')) as typeof ids;
});
test.afterAll(() => seed('clean'));

for (const width of [390, 1024, 1440, 320]) {
    test(`grade details and archived history at ${width}px`, async ({
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
        await page.goto('/student/grades');
        await page
            .getByRole('link', { name: /Ocena \d/ })
            .filter({ visible: true })
            .first()
            .click();
        await expect(page).toHaveURL(/\/student\/grades\/\d+/);
        await expect(
            page.getByRole('region', { name: 'Szczegóły oceny' }),
        ).toBeVisible();
        const historyLink = page.getByRole('link', {
            name: 'Historia tej oceny',
            exact: true,
        });
        await historyLink.focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/#grade-history$/);
        await expect(page.locator('#grade-history')).toBeFocused();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`grade-detail-${width}.png`),
            fullPage: true,
        });
        expect(ids.deleted).toBeTruthy();
        await page.goto(`/student/grades/${ids.deleted}`);
        await expect(
            page.getByRole('status').filter({ hasText: 'Ocena usunięta' }),
        ).toBeVisible();
        await expect(
            page.getByRole('region', { name: /Historia tej oceny/ }),
        ).toContainText('Usunięcie');
        await expect(
            page.getByText('Aktualna (bez usuniętej oceny)'),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`grade-deleted-${width}.png`),
            fullPage: true,
        });
    });
}
