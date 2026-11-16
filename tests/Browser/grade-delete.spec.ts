import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

test.setTimeout(60_000);
for (const [width, height] of [
    [320, 740],
    [390, 844],
    [1024, 768],
    [1440, 900],
]) {
    test(`cancel deletion, confirm and read preserved history at ${width}px`, async ({
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
        ) as { grade: number };
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
        await page.goto(`/teacher/grades/${ids.grade}`);
        await page
            .getByRole('link', { name: 'Edytuj ocenę', exact: true })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Edytuj ocenę',
            exact: true,
        });
        await expect(dialog).toBeVisible();
        await expect(
            dialog.getByRole('radio', { name: '3', exact: true }),
        ).toBeChecked();
        await dialog.getByRole('button', { name: 'Zamknij formularz' }).focus();
        await page.keyboard.press('Shift+Tab');
        await expect(
            dialog.getByRole('button', { name: 'Zapisz zmiany', exact: true }),
        ).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(
            dialog.getByRole('button', { name: 'Zamknij formularz' }),
        ).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(page).toHaveURL(`/teacher/grades/${ids.grade}`);
        await expect(
            page.getByRole('link', { name: 'Edytuj ocenę', exact: true }),
        ).toBeFocused();
        await page
            .getByRole('link', { name: 'Edytuj ocenę', exact: true })
            .click();
        await dialog
            .getByRole('button', { name: 'Usuń ocenę', exact: true })
            .click();
        const confirmation = page.getByRole('dialog', {
            name: 'Usunąć ocenę 3?',
            exact: true,
        });
        await expect(
            confirmation.getByRole('button', { name: 'Anuluj', exact: true }),
        ).toBeFocused();
        await expect(confirmation).toContainText('Matematyka');
        await expect(confirmation).toContainText('Jan');
        await page.keyboard.press('Escape');
        await expect(
            dialog.getByRole('button', { name: 'Usuń ocenę', exact: true }),
        ).toBeFocused();
        await page.reload();
        await expect(
            dialog.getByRole('radio', { name: '3', exact: true }),
        ).toBeChecked();
        await dialog
            .getByRole('button', { name: 'Usuń ocenę', exact: true })
            .click();
        await page.screenshot({
            path: testInfo.outputPath(`delete-confirmation-${width}.png`),
            fullPage: false,
        });
        await confirmation
            .getByRole('button', { name: 'Usuń ocenę', exact: true })
            .click();
        await expect(confirmation.getByRole('alert')).toBeFocused();
        await confirmation
            .getByLabel(/Powód usunięcia/)
            .fill('Błędny wpis F-206');
        await page.screenshot({
            path: testInfo.outputPath(`delete-${width}.png`),
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        if (width === 390) {
            await page.route(
                `**/teacher/grades/${ids.grade}`,
                async (route) => {
                    if (route.request().method() === 'DELETE') {
                        const response = await route.fetch({
                            headers: {
                                ...route.request().headers(),
                                'Sec-Fetch-Site': 'same-origin',
                            },
                            maxRedirects: 0,
                        });
                        expect(response.status()).toBe(303);
                        expect(response.headers()['location']).toMatch(
                            /\/teacher\/gradebooks\/\d+$/,
                        );
                        await route.abort('failed');
                    } else {
                        await route.continue();
                    }
                },
            );
        }
        await confirmation
            .getByRole('button', { name: 'Usuń ocenę', exact: true })
            .click();
        if (width === 390) {
            await expect(confirmation.getByRole('alert')).toContainText(
                'Usunięcie mogło zostać zapisane',
            );
            await expect(
                confirmation.getByRole('button', {
                    name: 'Usuń ocenę',
                    exact: true,
                }),
            ).toBeDisabled();
            await confirmation
                .getByRole('button', { name: 'Sprawdź aktualny stan oceny' })
                .click();
            await expect(page).toHaveURL(`/teacher/grades/${ids.grade}`);
        } else {
            await expect(page).toHaveURL(/\/teacher\/gradebooks\/\d+$/);
        }
        await expect(
            page
                .getByText('4,67', { exact: true })
                .filter({ visible: true })
                .first(),
        ).toBeVisible();
        await page.goto(`/teacher/grades/${ids.grade}`);
        await expect(
            page.getByText('Błędny wpis F-206', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Edytuj ocenę', exact: true }),
        ).toHaveCount(0);
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
        await student.goto(
            `http://127.0.0.1:8189/student/grades/${ids.grade}/history`,
        );
        await expect(
            student.getByText('Błędny wpis F-206', { exact: true }),
        ).toBeVisible();
        await studentContext.close();
    });
}
