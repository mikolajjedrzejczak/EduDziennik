import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

test.setTimeout(90000);

test.beforeAll(() => {
    execFileSync('php', ['tests/Infrastructure/seed-class-users.php'], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE: 'edudziennik_test',
            DB_URL: '',
        },
        stdio: 'inherit',
    });
});
async function select(page: Page, label: string, option: string) {
    await page.getByRole('combobox', { name: label, exact: true }).click();
    await page.getByRole('option', { name: option, exact: true }).click();
}
async function createClass(page: Page, name: string) {
    await page.getByRole('button', { name: 'Dodaj klasę' }).click();
    await page.getByLabel('Nazwa klasy', { exact: true }).fill(name);
    await select(page, 'Rok szkolny', '2026/2027');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: `Klasa ${name}`, exact: true }),
    ).toBeVisible();
}
for (const width of [390, 1024, 1440]) {
    test(`admin creates, renames, enrolls, transfers, ends and archives at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({
            width,
            height: width === 390 ? 844 : width === 1024 ? 768 : 900,
        });
        await page.goto('/login');
        await page
            .getByLabel('Adres e-mail')
            .fill('classes-admin@example.test');
        await page
            .getByLabel('Hasło', { exact: true })
            .fill('Playwright-classes-1');
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await page
            .getByRole('link', {
                name: width < 768 ? 'Klasy' : 'Klasy i przedmioty',
                exact: true,
            })
            .click();
        await expect(
            page.getByRole('heading', {
                name: 'Klasy i przedmioty',
                exact: true,
            }),
        ).toBeVisible();
        if (width === 390) {
            await page.getByRole('button', { name: 'Dodaj klasę' }).click();
            await page
                .getByRole('button', { name: 'Zapisz', exact: true })
                .click();
            await expect(page.getByRole('alert')).toBeFocused();
            await page.keyboard.press('Tab');
            expect(
                await page.evaluate(() =>
                    Boolean(document.activeElement?.closest('[role=dialog]')),
                ),
            ).toBe(true);
            await page.keyboard.press('Escape');
            await expect(
                page.getByRole('button', { name: 'Dodaj klasę' }),
            ).toBeFocused();
        }
        await createClass(page, `B${width}`);
        await createClass(page, `A${width}`);
        await page
            .getByRole('button', { name: 'Zmień nazwę', exact: true })
            .click();
        await page.getByLabel('Nazwa klasy', { exact: true }).fill(`C${width}`);
        await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await page
            .getByRole('button', { name: 'Dodaj ucznia', exact: true })
            .click();
        await select(page, 'Uczeń', `Uczeń Testowy ${width}`);
        await page
            .getByLabel('Data rozpoczęcia', { exact: true })
            .fill('2026-09-01');
        await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(
            page.getByText(`Uczeń Testowy ${width}`, { exact: true }),
        ).toBeVisible();
        await page
            .getByRole('button', { name: 'Archiwizuj', exact: true })
            .click();
        await expect(
            page.getByRole('button', { name: 'Anuluj' }),
        ).toBeFocused();
        await page.getByLabel('Potwierdzam archiwizację klasy').check();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Archiwizuj', exact: true })
            .click();
        await expect(page.getByRole('alert')).toContainText('Najpierw zakończ');
        await page.keyboard.press('Escape');
        await page
            .getByRole('button', { name: `Przenieś: Uczeń Testowy ${width}` })
            .click();
        await select(page, 'Klasa docelowa', `B${width} · 2026/2027`);
        await page
            .getByLabel('Data przeniesienia', { exact: true })
            .fill('2026-11-01');
        await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(
            page.getByRole('heading', { name: `Klasa B${width}`, exact: true }),
        ).toBeVisible();
        await page
            .getByRole('button', { name: `Zmień daty: Uczeń Testowy ${width}` })
            .click();
        await page
            .getByLabel('Data zakończenia', { exact: true })
            .fill('2026-11-14');
        await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(page.getByText('2026-11-01 — 2026-11-14')).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath(`classes-${width}.png`),
            fullPage: true,
        });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        if (width === 390) {
            await page.setViewportSize({ width: 320, height: 844 });
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
            await page.screenshot({
                path: testInfo.outputPath('classes-320.png'),
                fullPage: true,
            });
            await page.setViewportSize({
                width,
                height: width === 390 ? 844 : width === 1024 ? 768 : 900,
            });
        }

        await page
            .getByRole('button', { name: 'Archiwizuj', exact: true })
            .click();
        await page.getByLabel('Potwierdzam archiwizację klasy').check();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Archiwizuj', exact: true })
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await page.getByLabel('Archiwalne', { exact: true }).check();
        await page
            .getByRole('button', { name: 'Filtruj', exact: true })
            .click();
        await page
            .getByRole('link', {
                name: `Klasa B${width}, 2026/2027`,
                exact: true,
            })
            .click();
        await expect(
            page.getByText(`Uczeń Testowy ${width}`, { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Dodaj ucznia', exact: true }),
        ).toHaveCount(0);
    });
}
