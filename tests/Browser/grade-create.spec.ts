import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

test.setTimeout(60_000);
for (const [width, height] of [
    [390, 844],
    [1024, 768],
    [1440, 900],
]) {
    test(`create grade and student reads it at ${width}px`, async ({
        page,
        browser,
    }, testInfo) => {
        const ids = JSON.parse(
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
        await page.setViewportSize({ width, height });
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('gradebook-teacher@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-grades-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/teacher$/);
        await page.goto(`/teacher/gradebooks/${ids.assignment}`);
        const add = page
            .getByRole('link', {
                name: 'Wystaw ocenę: Jan Uczeń 01, Kolumna 5',
                exact: true,
            })
            .filter({ visible: true });
        await add.click();
        await expect(page).toHaveURL(/grades\/create/, { timeout: 15000 });
        await page.reload();
        const dialog = page.getByRole('dialog', { name: 'Wystaw ocenę' });
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page).toHaveURL(`/teacher/gradebooks/${ids.assignment}`, {
            timeout: 15000,
        });
        await expect(add).toBeFocused();
        await add.click();
        await expect(dialog).toBeVisible();
        await expect(dialog.getByLabel('Temat', { exact: true })).toHaveValue(
            'Kolumna 5',
        );
        await dialog
            .getByRole('button', { name: 'Zapisz ocenę', exact: true })
            .click();
        await expect(dialog.getByRole('alert')).toBeFocused();
        await page.screenshot({
            path: testInfo.outputPath(`errors-${width}.png`),
            fullPage: true,
        });
        await dialog.getByRole('radio', { name: '4', exact: true }).check();
        await dialog.getByLabel('Waga', { exact: true }).click();
        await page.getByRole('option', { name: '2', exact: true }).click();
        await dialog
            .getByLabel('Komentarz (widoczny dla ucznia)')
            .fill('Ocena F-204');
        await page.screenshot({
            path: testInfo.outputPath(`form-${width}.png`),
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await dialog
            .getByRole('button', { name: 'Zapisz ocenę', exact: true })
            .click();
        await expect(page).toHaveURL(`/teacher/gradebooks/${ids.assignment}`, {
            timeout: 15000,
        });
        await expect(
            page.getByText('4,33', { exact: true }).filter({ visible: true }),
        ).toBeVisible();
        await expect(page.getByText('Ocena została zapisana.')).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath(`saved-${width}.png`),
            fullPage: true,
        });
        const studentContext = await browser.newContext();
        const student = await studentContext.newPage();
        await student.goto('http://127.0.0.1:8189/login');
        await student
            .getByLabel('Adres e-mail')
            .fill('gradebook-student@example.test');
        await student
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-grades-1');
        await student
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(student).toHaveURL(/\/student$/);
        await student.goto('http://127.0.0.1:8189/student/grades?q=Kolumna+5');
        await expect(student.getByTestId('summary-average')).toHaveText('4,00');
        await expect(
            student
                .getByText('Kolumna 5', { exact: true })
                .filter({ visible: true }),
        ).toBeVisible();
        await studentContext.close();
    });
}

for (const width of [390, 1024, 1440]) {
    test(`preserve gradebook context on cancel and save at ${width}px`, async ({
        page,
    }) => {
        const ids = JSON.parse(
            execFileSync(
                'php',
                ['tests/Infrastructure/seed-gradebook.php', '--large-roster'],
                {
                    env: {
                        ...process.env,
                        APP_ENV: 'testing',
                        DB_DATABASE: 'edudziennik_test',
                        DB_URL: '',
                    },
                    encoding: 'utf8',
                },
            ),
        ) as { assignment: number };
        await page.setViewportSize({
            width,
            height: width === 1024 ? 768 : 900,
        });
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('gradebook-teacher@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-grades-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await expect(page).toHaveURL(/\/teacher$/);
        const base = `/teacher/gradebooks/${ids.assignment}`;
        const query = new URLSearchParams({
            q: 'Uczeń',
            page: '2',
            assessment_page: '2',
            per_page: '25',
            direction: 'desc',
            sort: 'last_name',
        });
        await page.goto(`${base}?${query}`);
        const add = page
            .getByRole('link', {
                name: 'Wystaw ocenę: Jan Uczeń 01, Kolumna 11',
                exact: true,
            })
            .filter({ visible: true });
        const dialog = page.getByRole('dialog', { name: 'Wystaw ocenę' });
        const assertContext = async () => {
            await expect(page).toHaveURL(
                (url) =>
                    url.pathname === base &&
                    [...query].every(
                        ([key, value]) => url.searchParams.get(key) === value,
                    ),
            );
            await expect(page.locator('#roster-q')).toHaveValue('Uczeń');
            await expect(add).toBeVisible();
        };
        for (const cancel of ['Escape', 'Anuluj']) {
            await add.click();
            await expect(dialog).toBeVisible();
            await page.reload();
            await expect(dialog).toBeVisible();
            // The server-rendered background must contain the same cell after reload.
            await expect(
                page
                    .locator(
                        'a[aria-label="Wystaw ocenę: Jan Uczeń 01, Kolumna 11"]',
                    )
                    .filter({ visible: true }),
            ).toHaveCount(1);
            if (cancel === 'Escape') await page.keyboard.press('Escape');
            else
                await dialog
                    .getByRole('button', { name: 'Anuluj', exact: true })
                    .click();
            await assertContext();
            await expect(add).toBeFocused();
        }
        await add.click();
        await expect(dialog).toBeVisible();
        await dialog
            .getByRole('button', { name: 'Zapisz ocenę', exact: true })
            .click();
        await expect(dialog.getByRole('alert')).toBeFocused();
        await dialog.getByRole('radio', { name: '4', exact: true }).check();
        await dialog
            .getByRole('button', { name: 'Zapisz ocenę', exact: true })
            .click();
        await expect(page).toHaveURL(
            (url) =>
                url.pathname === base &&
                [...query].every(
                    ([key, value]) => url.searchParams.get(key) === value,
                ),
        );
        await expect(page.getByText('Ocena została zapisana.')).toBeVisible();
        // Another session removes the next empty column while this form is open.
        const next = page
            .getByRole('link', {
                name: 'Wystaw ocenę: Jan Uczeń 01, Kolumna 12',
                exact: true,
            })
            .filter({ visible: true });
        await next.click();
        await expect(dialog).toBeVisible();
        const assessmentId = new URL(page.url()).searchParams.get(
            'assessment_id',
        );
        execFileSync(
            'php',
            [
                'tests/Infrastructure/remove-gradebook-assessment.php',
                assessmentId!,
            ],
            {
                env: {
                    ...process.env,
                    APP_ENV: 'testing',
                    DB_DATABASE: 'edudziennik_test',
                    DB_URL: '',
                },
            },
        );
        await page.keyboard.press('Escape');
        await expect(page).toHaveURL(
            (url) =>
                url.pathname === base &&
                [...query].every(
                    ([key, value]) => url.searchParams.get(key) === value,
                ),
        );
        await expect(page.locator('#roster-q')).toBeFocused();
    });
}
