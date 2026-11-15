import { expect, test } from './fixtures';

for (const width of [320, 390, 1024, 1440]) {
    test(`F-006 geometry, keyboard and states at ${width}px`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.emulateMedia({
            colorScheme: 'dark',
            reducedMotion: 'reduce',
        });
        await page.goto('/__tests/forms');
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'light');
        await expect(page.locator('body')).toHaveCSS(
            'font-family',
            /Public Sans/,
        );
        const primitives = page.getByRole('region', {
            name: 'Prymitywy F-006',
        });
        const primary = primitives.getByRole('button', {
            name: 'primary',
            exact: true,
        });
        await expect(primary).toHaveCSS(
            'height',
            width < 768 ? '48px' : '40px',
        );
        await expect(primary).toHaveCSS('font-size', '13px');
        await expect(primary).toHaveCSS('background-color', 'rgb(35, 80, 216)');
        await expect(
            primitives.getByRole('button', { name: 'Ładowanie' }),
        ).toBeDisabled();
        await expect(
            primitives.getByRole('button', { name: 'Ładowanie' }),
        ).toHaveCSS('background-color', 'rgb(35, 80, 216)');
        await expect(
            primitives.getByRole('button', { name: 'Niedostępny' }),
        ).toHaveCSS('color', 'rgb(152, 162, 179)');
        const chip = primitives.getByRole('button', { name: 'Ocena 5' });
        const box = await chip.boundingBox();
        expect(box?.width).toBeGreaterThanOrEqual(44);
        expect(box?.height).toBeGreaterThanOrEqual(44);
        await expect(chip.locator('[data-slot="grade-chip-value"]')).toHaveCSS(
            'width',
            '24px',
        );
        await primary.hover();
        await expect(primary).toHaveCSS('background-color', 'rgb(26, 63, 176)');
        await page.mouse.move(0, 0);
        await primary.focus();
        await page.keyboard.press('Tab');
        await expect(
            primitives.getByRole('button', { name: 'secondary', exact: true }),
        ).toBeFocused();
        await expect(
            primitives.getByRole('button', { name: 'secondary', exact: true }),
        ).toHaveCSS('outline-color', 'rgb(35, 80, 216)');
        await chip.focus();
        await page.keyboard.press('Tab');
        const select = primitives.getByRole('combobox');
        await expect(select).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('listbox')).toBeVisible();
        await expect(
            page.getByRole('option', { name: 'Matematyka' }),
        ).toBeFocused();
        await page.keyboard.press('ArrowDown');
        await expect(
            page.getByRole('option', { name: 'Język polski' }),
        ).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(select).toBeFocused();
        await expect(select).toContainText('Język polski');
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await primitives.screenshot({
            path: testInfo.outputPath('primitives.png'),
        });
    });
}
