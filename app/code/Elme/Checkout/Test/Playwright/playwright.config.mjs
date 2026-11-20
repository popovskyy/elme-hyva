import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.mjs',
    globalSetup: './global-setup.mjs',
    workers: 1,
    timeout: 120_000,
    use: {
        actionTimeout: 20_000,
        baseURL: process.env.BASE_URL || 'https://elme-hyva.local',
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        ...devices['Desktop Chrome'],
    },
    projects: [
        { name: 'chromium' },
        // FE-78: Roman tests the heart in Firefox
        { name: 'firefox', testMatch: 'heart.spec.mjs', use: { ...devices['Desktop Firefox'] } },
    ],
});
