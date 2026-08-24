import fs from 'node:fs';

import type { BrowserContext, Page } from '@playwright/test';

/**
 * Credentials written by `php artisan e2e:seed`. That command refuses to run
 * in production, which is what makes a fixed password here safe to commit.
 */
export const ADMIN_EMAIL = 'e2e@teacher.test';
export const ADMIN_PASSWORD = 'Playwright!2026#fixture';
export const ACCESS_PASSWORD = 'e2e-unlock-2026';

/**
 * A second access password, kept separate from ACCESS_PASSWORD above.
 * `passwords.spec.ts` changes this one's secret to prove the unlock cookie
 * gets invalidated — doing that to the password `gated-media.spec.ts` also
 * relies on would make the two specs order-dependent. Mirrors
 * `SeedBrowserTestFixtures::COOKIE_PASSWORD_SECRET`.
 */
export const COOKIE_TEST_PASSWORD = 'e2e-cookie-2026';

/** Written once by `auth.setup.ts`, which is the run's only real login. */
export const SESSION_STATE = 'tests/e2e/.auth/admin.json';

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

/**
 * Give this page the admin's session, without spending a login on it.
 *
 * Cookies go onto the page's own context rather than through the project's
 * `storageState`, deliberately: the `request` fixture is a separate context
 * and several specs rely on it being genuinely anonymous — the 403 for a file
 * behind a password is the whole point of one of them. A project-wide
 * storageState would have authenticated those too, and quietly turned the
 * security assertions into assertions about nothing.
 */
export async function useAdminSession(page: Page): Promise<void> {
    const state = JSON.parse(
        fs.readFileSync(SESSION_STATE, 'utf8'),
    ) as StorageState;

    await page.context().addCookies(state.cookies);
}
