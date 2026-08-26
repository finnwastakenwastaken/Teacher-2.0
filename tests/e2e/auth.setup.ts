import { expect, test as setup } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, SESSION_STATE } from './support/admin';

/**
 * The suite's one real login, saved for every spec to reuse.
 *
 * Fortify limits logins to 5/min per e-mail+IP, and the whole suite runs from
 * one container address — logging in per-spec would hit that limit and the
 * failure would surface as a broken admin screen, not an obvious rate-limit
 * error. The form is still exercised here, once, so a broken login fails the
 * run rather than being skipped past.
 */
setup('the admin logs in once, and the session is reused', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(ADMIN_EMAIL);
    await page.locator('#password').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: /inloggen|log in/i }).click();

    // The dashboard is the only authenticated landing page, so arriving there
    // proves the session exists, not just that the form submitted.
    await expect(page).toHaveURL(/\/dashboard/);

    await page.context().storageState({ path: SESSION_STATE });
});
