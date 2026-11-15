import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const css = readFileSync('resources/css/tokens.css', 'utf8');
const tokens = Object.fromEntries(
    [...css.matchAll(/--([\w-]+):\s*(#[\da-f]{6});/g)].map((match) => [
        match[1],
        match[2],
    ]),
);
function luminance(hex: string) {
    const [r, g, b] = [1, 3, 5].map((index) => {
        const channel = parseInt(hex.slice(index, index + 2), 16) / 255;
        return channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
const pairs = [
    ['text-primary', 'surface'],
    ['text-secondary', 'surface'],
    ['text-secondary', 'surface-secondary'],
    ['text-on-primary', 'primary'],
    ['text-on-primary', 'primary-hover'],
    ['text-on-primary', 'error'],
    ['error', 'error-bg'],
    ['success', 'success-bg'],
    ['warning', 'warning-bg'],
    ['primary', 'primary-subtle'],
    ['nav-text', 'nav-bg'],
    ['nav-text-muted', 'nav-bg'],
    ...[1, 2, 3, 4, 5, 6].map((value) => [
        `grade-${value}-fg`,
        `grade-${value}-bg`,
    ]),
];
describe('F-006 text contrast (WCAG relative luminance)', () => {
    it.each(pairs)('%s on %s meets 4.5:1', (foreground, background) => {
        const a = luminance(tokens[foreground]);
        const b = luminance(tokens[background]);
        expect(
            (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05),
        ).toBeGreaterThanOrEqual(4.5);
    });
});
