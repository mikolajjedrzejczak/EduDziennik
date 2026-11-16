import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

test.setTimeout(60_000);
for (const [width, height] of [
    [320, 740],
    [390, 844],
    [1024, 768],
    [1440, 900],
]) {
    test(`correct grade, reject stale form and read history at ${width}px`, async ({
        page,
        context,
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
        const stale = await context.newPage();
        await stale.setViewportSize({ width, height });
        await stale.goto(`/teacher/grades/${ids.grade}/edit`);
        await dialog.getByRole('radio', { name: '4', exact: true }).check();
        await dialog.getByLabel(/Powód zmiany/).fill('Korekta punktacji F-205');
        await page.screenshot({
            path: testInfo.outputPath(`edit-${width}.png`),
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await dialog
            .getByRole('button', { name: 'Zapisz zmiany', exact: true })
            .click();
        await expect(page).toHaveURL(`/teacher/grades/${ids.grade}`);
        await expect(
            page.getByText('4,57', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Korekta punktacji F-205', { exact: true }),
        ).toBeVisible();
        await stale.getByRole('radio', { name: '6', exact: true }).check();
        await stale.getByLabel(/Powód zmiany/).fill('Moja niezapisana korekta');
        await stale
            .getByRole('button', { name: 'Zapisz zmiany', exact: true })
            .click();
        await expect(stale.getByRole('alert')).toContainText(
            'Ocena została zmieniona',
        );
        await expect(stale.getByRole('alert')).toBeFocused();
        await expect(stale.getByLabel(/Powód zmiany/)).toHaveValue(
            'Moja niezapisana korekta',
        );
        await expect(
            stale.getByRole('radio', { name: '6', exact: true }),
        ).toBeChecked();
        expect(
            await stale.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await stale.screenshot({
            path: testInfo.outputPath(`conflict-${width}.png`),
            fullPage: true,
        });
        await stale
            .getByRole('button', { name: /Wczytaj aktualną ocenę/ })
            .click();
        await expect(stale.getByLabel(/Powód zmiany/)).toHaveValue('');
        await expect(
            stale.getByRole('radio', { name: '4', exact: true }),
        ).toBeChecked();
        await stale.close();
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
        await student.goto(`http://127.0.0.1:8189/student/grades/${ids.grade}`);
        await expect(
            student.getByText('Korekta punktacji F-205', { exact: true }),
        ).toBeVisible();
        await expect(
            student.getByRole('link', { name: 'Edytuj ocenę', exact: true }),
        ).toHaveCount(0);
        await studentContext.close();
    });
}
