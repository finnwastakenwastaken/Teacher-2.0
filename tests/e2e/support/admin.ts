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
 * Kept separate from ACCESS_PASSWORD: `passwords.spec.ts` changes this one's
 * secret, and reusing the same password `gated-media.spec.ts` relies on would
 * make the two specs order-dependent. Mirrors
 * `SeedBrowserTestFixtures::COOKIE_PASSWORD_SECRET`.
 */
export const COOKIE_TEST_PASSWORD = 'e2e-cookie-2026';

/** Written once by `auth.setup.ts`, which is the run's only real login. */
export const SESSION_STATE = 'tests/e2e/.auth/admin.json';

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

/**
 * Gives this page the admin's session without spending another login.
 * Cookies go onto the page's own context, not the project-wide
 * `storageState`, because the `request` fixture is a separate context that
 * several specs need genuinely anonymous — a project-wide storageState would
 * authenticate those too and turn security assertions into assertions about
 * nothing.
 */
export async function useAdminSession(page: Page): Promise<void> {
    const state = JSON.parse(
        fs.readFileSync(SESSION_STATE, 'utf8'),
    ) as StorageState;

    await page.context().addCookies(state.cookies);
}
