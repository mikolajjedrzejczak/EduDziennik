import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

beforeEach(() => {
    vi.setSystemTime(new Date('2026-11-15T12:00:00Z'));
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});
