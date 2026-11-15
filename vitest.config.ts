import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/Frontend/**/*.test.{ts,tsx}'],
        reporters: ['default', 'junit'],
        outputFile: { junit: 'test-results/vitest.xml' },
        clearMocks: true,
        restoreMocks: true,
        setupFiles: ['tests/Frontend/setup.ts'],
    },
});
