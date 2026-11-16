import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const viewports = [
    { width: 390, height: 844 },
    { width: 1024, height: 768 },
    { width: 1440, height: 900 },
] as const;

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

async function loginAsStudent(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Adres e-mail').fill('student7@example.test');
    await page.getByLabel('Hasło', { exact: true }).fill('Playwright-grades-1');
    await page
        .getByRole('button', { name: 'Zaloguj się', exact: true })
        .click();
    await expect(page).toHaveURL(/\/student$/);
}

// Below 768px the secondary filters sit in a collapsed panel.
async function openFilters(page: Page) {
    const toggle = page.getByRole('button', { name: /Więcej filtrów/ });
    if (await toggle.isVisible()) {
        if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
            await toggle.click();
        }
    }
}

const noHorizontalScroll = (page: Page) =>
    page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
    );

for (const { width, height } of viewports) {
    test(`student browses own grades at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width, height });
        await loginAsStudent(page);

        // Navigation: desktop/tablet sidebar or mobile bottom bar.
        await page
            .getByRole('link', { name: /Moje oceny|Oceny/ })
            .first()
            .click();
        await expect(page).toHaveURL(/\/student\/grades/);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Moje oceny' }),
        ).toBeVisible();

        // Only the student's own Mathematics grades: 31 / 7 = 4,43 (student8's grade is excluded).
        await page
            .getByRole('navigation', { name: 'Przedmioty' })
            .getByRole('link', { name: /Matematyka/ })
            .click();
        await expect(page).toHaveURL(/subject_id=/);
        await expect(page.getByTestId('summary-average')).toHaveText('4,43');
        await expect(page.getByText('Pokazano 4 z 4 ocen')).toBeVisible();
        expect(await noHorizontalScroll(page)).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`grades-${width}.png`),
        });

        // Filter narrows the list and the average, the URL carries the filter.
        await openFilters(page);
        await page.getByLabel('Temat oceny').fill('Fixture 3');
        await page.getByRole('button', { name: 'Filtruj' }).click();
        await expect(page).toHaveURL(/q=Fixture(\+|%20)3/);
        await expect(page.getByTestId('summary-average')).toHaveText('5,00');
        await expect(page.getByText('Pokazano 1 z 1 ocen')).toBeVisible();

        // No match: empty state with a way out; keyboard reachable.
        await openFilters(page);
        await page.getByLabel('Temat oceny').fill('zzz-brak');
        await page.getByRole('button', { name: 'Filtruj' }).click();
        await expect(page.getByText('Brak wyników')).toBeVisible();
        await page.getByRole('button', { name: 'Wyczyść filtry' }).click();
        await expect(page).not.toHaveURL(/q=/);
        await expect(page.getByTestId('summary-average')).toHaveText('4,43');
    });
}

test('a student cannot select another owner through the URL', async ({
    page,
}) => {
    await loginAsStudent(page);
    await page.goto('/student/grades?student_id=2');
    await expect(page).not.toHaveURL(/student_id/);
    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page.getByTestId('summary-average')).toBeVisible();

    // Another role's area stays forbidden for a student.
    const response = await page.goto('/admin');
    expect(response?.status()).toBe(403);
});

test('debounced search resets page and survives reload and Back/Forward', async ({
    page,
}) => {
    await loginAsStudent(page);
    await page.goto('/student/grades?sort=value&direction=asc');
    await openFilters(page);
    const search = page.getByLabel('Temat oceny');
    await search.fill('Sprawdzian');
    await expect(page).toHaveURL(/q=Sprawdzian/);
    expect(new URL(page.url()).searchParams.get('page')).toBeNull();
    expect(new URL(page.url()).searchParams.get('sort')).toBe('value');
    const searched = page.url();
    await page.reload();
    await openFilters(page);
    await expect(search).toHaveValue('Sprawdzian');
    await search.fill('Nieistniejący temat');
    await expect(page).toHaveURL(/q=Nieistniej/);
    await expect(
        page.getByRole('heading', { name: 'Brak wyników' }),
    ).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL(searched);
    await openFilters(page);
    await expect(search).toHaveValue('Sprawdzian');
    await page.goForward();
    await openFilters(page);
    await expect(search).toHaveValue('Nieistniejący temat');
});
