import { test as base, expect } from '@playwright/test';

// Every test receives a fresh browser context and an explicit clock.
export const test = base.extend<{ checkedPage: void }>({
    checkedPage: [
        async ({ page }, use) => {
            const errors: string[] = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.clock.setFixedTime(new Date('2026-11-15T12:00:00Z'));
            await use();
            expect(errors, 'Uncaught browser errors').toEqual([]);
        },
        { auto: true },
    ],
});
export { expect };
