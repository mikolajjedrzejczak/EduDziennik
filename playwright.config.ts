import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: 'test-results/playwright',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: true,
    reporter: [
        ['list'],
        ['junit', { outputFile: 'test-results/playwright.xml' }],
        ['html', { open: 'never' }],
    ],
    projects: [
        { name: 'forms', testMatch: /(?:forms|primitives)\.spec\.ts/ },
        {
            name: 'shell',
            testMatch:
                /(?:shell|subjects|assignments|user-create|user-edit|user-delete|student-grades)\.spec\.ts/,
            use: { baseURL: 'http://127.0.0.1:8189' },
        },
        {
            name: 'classes',
            testMatch: 'classes.spec.ts',
            use: { baseURL: 'http://127.0.0.1:8189' },
        },
        {
            name: 'build',
            testMatch: 'build.spec.ts',
            use: { baseURL: 'http://127.0.0.1:8188' },
        },
    ],
    use: {
        baseURL: 'http://127.0.0.1:8187',
        browserName: 'chromium',
        timezoneId: 'Europe/Warsaw',
        locale: 'pl-PL',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: [
        {
            command:
                process.env.BUILD_PHP_COMMAND ??
                'php -S 127.0.0.1:8188 -t public tests/Infrastructure/browser-server.php',
            url: 'http://127.0.0.1:8188/login',
            env: {
                APP_ENV: 'testing',
                APP_DEBUG: 'false',
                APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                SESSION_DRIVER: 'file',
                CACHE_STORE: 'array',
                // No DB access in this smoke. An accidental query must fail.
                DB_URL: '',
                DB_CONNECTION: 'mariadb',
                DB_DATABASE: 'edudziennik_e2e',
                DB_USERNAME: 'e2e_no_database_access',
                DB_PASSWORD: '',
                LOG_CHANNEL: 'stderr',
            },
        },
        {
            // F-007: real app, real sessions, isolated edudziennik_test database, built assets.
            command:
                process.env.SHELL_PHP_COMMAND ??
                'php -S 127.0.0.1:8189 -t public tests/Infrastructure/browser-server.php',
            url: 'http://127.0.0.1:8189/login',
            env: {
                APP_ENV: 'testing',
                APP_DEBUG: 'false',
                APP_LOCALE: 'pl',
                APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                SESSION_DRIVER: 'file',
                CACHE_STORE: 'array',
                DB_URL: '',
                DB_CONNECTION: 'mariadb',
                DB_DATABASE: 'edudziennik_test',
                BCRYPT_ROUNDS: '4',
                LOG_CHANNEL: 'stderr',
            },
        },
        {
            command:
                process.env.FORMS_PHP_COMMAND ??
                'php -S 127.0.0.1:8187 -t public tests/Fixtures/Forms/server.php',
            url: 'http://127.0.0.1:8187/__tests/forms',
            env: {
                APP_ENV: 'testing',
                APP_DEBUG: 'false',
                APP_LOCALE: 'pl',
                DB_URL: '',
                DB_CONNECTION: 'mariadb',
                DB_DATABASE: 'edudziennik_e2e',
                DB_USERNAME: 'e2e_no_database_access',
                DB_PASSWORD: '',
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
