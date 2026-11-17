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
test.afterEach(({ page }, testInfo) => {
    if (
        testInfo.title.startsWith('mobile list/detail/back') &&
        !page.isClosed()
    )
        seed();
});
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

for (const width of [320, 390]) {
    test(`mobile list/detail/back with 12 subjects at ${width}px`, async ({
        page,
    }, testInfo) => {
        seed('many-subjects');
        await page.setViewportSize({ width, height: 844 });
        await loginAsStudent(page);
        await page.goto('/student/grades?sort=value&direction=asc');
        const nav = page.getByRole('navigation', { name: 'Przedmioty' });
        await expect(nav.getByRole('link')).toHaveCount(12);
        await expect(
            page.getByRole('region', { name: 'Lista ocen' }),
        ).toBeHidden();
        await page.screenshot({
            path: testInfo.outputPath(`subjects-${width}.png`),
        });
        const selected = nav.getByRole('link', {
            name: /Przedmiot mobilny 11/,
        });
        await selected.click();
        await expect(page).toHaveURL(/subject_id=/);
        const detailUrl = page.url();
        const term = new URL(detailUrl).searchParams.get('term_id');
        const heading = page.locator('#grades-detail-heading');
        await expect(heading).toBeFocused();
        await expect(nav).toBeHidden();
        await expect(
            page.getByText('Mobilna ocena 11').last(),
        ).toBeInViewport();
        expect(await noHorizontalScroll(page)).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`subject-detail-${width}.png`),
        });
        await page.goBack();
        await expect(selected).toBeFocused();
        await expect(selected).toBeInViewport();
        await page.goForward();
        await expect(heading).toBeFocused();
        await page.getByRole('link', { name: 'Wróć do przedmiotów' }).click();
        await expect(selected).toBeFocused();
        const back = new URL(page.url()).searchParams;
        expect(back.get('term_id')).toBe(term);
        expect(back.get('subject_id')).toBeNull();
        expect(back.get('sort')).toBe('value');
        expect(back.get('direction')).toBe('asc');
        await page.reload();
        await expect(nav).toBeVisible();
        await page.goto(detailUrl);
        await expect(nav).toBeHidden();
        await expect(page.getByText('Mobilna ocena 11').last()).toBeVisible();
        await openFilters(page);
        await page.getByLabel('Temat oceny').fill('Mobilna');
        await page.getByRole('button', { name: 'Filtruj' }).click();
        await expect(page).toHaveURL(/q=Mobilna/);
        await page.getByRole('link', { name: 'Wróć do przedmiotów' }).click();
        await expect(page).toHaveURL(/q=Mobilna/);
        await expect(nav.getByRole('link')).toHaveCount(12);
        await expect(
            nav.getByRole('link', { name: /Matematyka/ }),
        ).toContainText('0 ocen');
        expect(await noHorizontalScroll(page)).toBe(true);
    });
}

test('mobile navigation reflows with CSS zoom 200%', async ({
    page,
}, testInfo) => {
    await page.setViewportSize({ width: 640, height: 844 });
    await loginAsStudent(page);
    await page.goto('/student/grades');
    await page.addStyleTag({ content: 'html { zoom: 2; }' });
    expect(await noHorizontalScroll(page)).toBe(true);
    await page
        .getByRole('navigation', { name: 'Przedmioty' })
        .getByRole('link', { name: /Matematyka/ })
        .click();
    await expect(
        page.getByRole('link', { name: 'Wróć do przedmiotów' }),
    ).toBeInViewport();
    expect(await noHorizontalScroll(page)).toBe(true);
    await openFilters(page);
    await expect(page.getByLabel('Temat oceny')).toBeVisible();
    expect(await noHorizontalScroll(page)).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('grades-css-zoom-200.png'),
    });
    await page.getByRole('link', { name: 'Wróć do przedmiotów' }).click();
    await expect(
        page.locator('#grades-subjects a').filter({ hasText: 'Matematyka' }),
    ).toBeFocused();
});

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
        if (width < 768) {
            await expect(
                page.getByRole('navigation', { name: 'Przedmioty' }),
            ).toBeVisible();
            await expect(page.getByText(/Średnia ważona: 4,43/)).toBeVisible();
        } else {
            await expect(page.getByTestId('summary-average')).toHaveText(
                '4,43',
            );
        }
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

for (const width of [320, 390, 1440]) {
    for (const { parameter, id, label } of [
        { parameter: 'date_from', id: 'grades-from', label: 'Data od' },
        { parameter: 'date_to', id: 'grades-to', label: 'Data do' },
        { parameter: 'category_id', id: 'grades-category', label: 'Kategoria' },
    ]) {
        test(`error link reveals and focuses ${parameter} at ${width}px`, async ({
            page,
        }, testInfo) => {
            await page.setViewportSize({ width, height: 844 });
            await loginAsStudent(page);
            await page.goto(`/student/grades?${parameter}=invalid`);
            const summary = page.getByRole('alert');
            const link = summary.getByRole('link', {
                name: new RegExp(`^${label}:`),
            });
            const field = page.locator(`#${id}`);
            const toggle = page.getByRole('button', { name: /Więcej filtrów/ });
            if (width < 768) {
                await expect(toggle).toHaveAttribute('aria-expanded', 'false');
                await expect(field).toBeHidden();
            }
            // The summary receives focus after the server validation redirect.
            await expect(summary).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(link).toBeFocused();
            await page.keyboard.press('Enter');
            await expect(field).toBeVisible();
            await expect(field).toBeFocused();
            await expect(field).toHaveAttribute('aria-invalid', 'true');
            const message = page.locator(`#${id}-error`);
            await expect(message).toBeVisible();
            await expect(field).toHaveAttribute(
                'aria-describedby',
                `${id}-error`,
            );
            expect(await noHorizontalScroll(page)).toBe(true);
            if (width < 768) {
                await expect(toggle).toHaveAttribute('aria-expanded', 'true');
                const panelId = await toggle.getAttribute('aria-controls');
                expect(
                    await field.evaluate(
                        (element, panelId) =>
                            element.closest('[id="' + panelId + '"]') !== null,
                        panelId,
                    ),
                ).toBe(true);
                // A manually collapsed panel can be revealed again by the same error link.
                await toggle.click();
                await expect(field).toBeHidden();
                await link.focus();
                await page.keyboard.press('Enter');
                await expect(field).toBeVisible();
                await expect(field).toBeFocused();
            }
            if (parameter === 'date_from') {
                await page.screenshot({
                    path: testInfo.outputPath(`filter-error-${width}.png`),
                });
            }
        });
    }
}
