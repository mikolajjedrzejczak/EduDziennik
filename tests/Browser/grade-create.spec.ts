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
        await expect(page).toHaveURL(/grades\/create/);
        await page.reload();
        const dialog = page.getByRole('dialog', { name: 'Wystaw ocenę' });
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page).toHaveURL(`/teacher/gradebooks/${ids.assignment}`);
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
        await expect(page).toHaveURL(`/teacher/gradebooks/${ids.assignment}`);
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
