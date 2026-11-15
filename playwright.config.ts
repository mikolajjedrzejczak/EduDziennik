import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    use: { baseURL: 'http://127.0.0.1:8187', browserName: 'chromium' },
    webServer: [
        {
            command:
                process.env.FORMS_PHP_COMMAND ??
                'php -S 127.0.0.1:8187 -t public tests/Fixtures/Forms/server.php',
            url: 'http://127.0.0.1:8187/__tests/forms',
            env: {
                APP_ENV: 'testing',
                APP_DEBUG: 'false',
                APP_LOCALE: 'pl',
                APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                SESSION_DRIVER: 'file',
                CACHE_STORE: 'array',
                LOG_CHANNEL: 'stderr',
            },
        },
        {
            command:
                process.env.FORMS_VITE_COMMAND ??
                'npx vite --config vite.forms.config.ts',
            url: 'http://127.0.0.1:5187/@vite/client',
        },
    ],
});
