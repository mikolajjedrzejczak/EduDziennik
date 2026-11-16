import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

const seed = (...args: string[]) =>
    execFileSync(
        'php',
        ['tests/Infrastructure/seed-student-grades.php', ...args],
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
test.beforeAll(() => seed());
test.afterAll(() => seed('clean'));

for (const width of [390, 1024, 1440, 320]) {
    test(`dashboard opens a subject in the same term at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({
            width,
            height: width === 1024 ? 768 : 900,
        });
        await page.goto('/login');
        await page.getByLabel('Adres e-mail').fill('student7@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-grades-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/student$/);
        await expect(page.getByTestId('summary-average')).toHaveText('4,43');
        await expect(
            page
                .getByRole('list')
                .filter({ has: page.getByRole('link', { name: /Ocena/ }) })
                .getByRole('listitem'),
        ).toHaveCount(4);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`dashboard-${width}.png`),
            fullPage: true,
        });
        const subject = page
            .getByRole('navigation', { name: 'Przedmioty' })
            .getByRole('link', { name: /Matematyka/ });
        await subject.focus();
        await expect(subject).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(
            (url) =>
                url.pathname === '/student/grades' &&
                url.searchParams.has('term_id') &&
                url.searchParams.has('subject_id'),
        );
        await expect(page.getByTestId('summary-average')).toHaveText('4,43');
        await page.reload();
        await expect(page.getByTestId('summary-average')).toHaveText('4,43');
    });
}

test('dashboard rejects an owner filter and focuses the error summary', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill('student7@example.test');
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-grades-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(/\/student$/);
    await page.goto('/student?student_id=8');
    await expect(page).toHaveURL(/\/student$/);
    await expect(page.getByRole('alert')).toBeFocused();
    await expect(page.getByTestId('summary-average')).toHaveText('4,43');
});

test('term selection keeps the URL and shows an empty semester', async ({
    page,
}, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill('student7@example.test');
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-grades-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(/\/student$/);
    await page.getByRole('combobox', { name: 'Semestr' }).focus();
    await page.keyboard.press('Enter');
    await expect(
        page.getByRole('option', {
            name: 'Semestr II (2026/2027)',
            exact: true,
        }),
    ).toBeVisible();
    await page.keyboard.press('End');
    await expect(
        page.getByRole('option', {
            name: 'Semestr II (2026/2027)',
            exact: true,
        }),
    ).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/student\?term_id=/);
    await expect(
        page.getByRole('heading', { name: 'Brak ocen' }),
    ).toBeVisible();
    await expect(page.getByTestId('summary-average')).toHaveText('—');
    await page.reload();
    await expect(page.getByTestId('summary-average')).toHaveText('—');
    await page.screenshot({
        path: testInfo.outputPath('dashboard-empty-390.png'),
        fullPage: true,
    });
});
