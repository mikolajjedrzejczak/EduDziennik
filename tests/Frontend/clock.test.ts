import { describe, expect, it, vi } from 'vitest';

describe('test clock', () => {
    it('starts at the fixture instant regardless of the host clock', () => {
        expect(new Date().toISOString()).toBe('2026-11-15T12:00:00.000Z');
    });

    it('can test Warsaw midnight without changing the machine timezone', () => {
        vi.setSystemTime(new Date('2026-11-15T23:30:00Z'));
        const day = new Intl.DateTimeFormat('pl-PL', {
            timeZone: 'Europe/Warsaw',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(new Date());
        expect(day).toBe('16.11.2026');
        expect(new Date().toISOString().slice(0, 10)).toBe('2026-11-15');
    });
});
