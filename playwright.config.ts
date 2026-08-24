import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests, run against the real stack through nginx.
 *
 * There is deliberately no jsdom half to this. The regressions that motivated
 * a JS runner at all were a drag landing on the wrong list, an iframe missing
 * an attribute, and a header only nginx sets — and jsdom has no layout engine,
 * so `getBoundingClientRect()` returns zeroes and dnd-kit's collision
 * detection degenerates. A test for the first bug would only ever assert
 * against rectangles the test itself had faked, which is the one thing it
 * must not do.
 *
 * So this drives Chromium against `web`, the same nginx that ships, in front
 * of the same PHP-FPM and the same PostgreSQL. It is the browser half of what
 * the PHP suite already does at the HTTP layer.
 */
export default defineConfig({
    testDir: './tests/e2e',

    // The whole suite shares one database and one site, and several specs
    // reorder or attach things. Parallel workers would race each other through
    // the same fixtures, and the failures would look like product bugs.
    workers: 1,
    fullyParallel: false,

    // No retries, deliberately. A test that passes on the second attempt is
    // reporting something real about the application or about itself, and
    // hiding that is how a suite stops being believed.
    retries: 0,

    // `test.only` left in a file would silently shrink CI to one test.
    forbidOnly: !!process.env.CI,

    timeout: 30_000,
    expect: { timeout: 10_000 },

    reporter: process.env.CI ? [['github'], ['list']] : [['list']],

    use: {
        // `web` inside the compose network; override when running from a host
        // that publishes the site on a port.
        baseURL: process.env.E2E_BASE_URL ?? 'http://web',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        // The site is Dutch unless the visitor says otherwise, and several
        // assertions are about exactly that negotiation. Pinning the header
        // here stops the result depending on the machine's locale.
        locale: 'nl-NL',
        extraHTTPHeaders: { 'Accept-Language': 'nl' },
    },

    projects: [
        // Logs in once and saves the session. Fortify allows five logins a
        // minute per e-mail and IP, the suite runs from one address, and it
        // wanted one per test — so back-to-back runs were being throttled and
        // the refusal looked like an admin screen that would not load. See
        // `auth.setup.ts`.
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts$/,
        },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
        },
    ],
});
