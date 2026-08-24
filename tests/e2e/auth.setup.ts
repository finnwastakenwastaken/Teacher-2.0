import { expect, test as setup } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, SESSION_STATE } from './support/admin';

/**
 * The one real login the suite performs, saved for every spec that needs it.
 *
 * Not an optimisation. Fortify limits logins to five a minute per e-mail and
 * IP (`FortifyServiceProvider::configureRateLimiting`), the whole suite runs
 * from one container address, and it needed five. So it sat exactly on the
 * limit: the first run passed, a second run in the same minute was refused,
 * and the failure surfaced as an admin screen that would not load — which
 * reads as a product bug and is not one. Reusing a session keeps the suite
 * honest about the limiter instead of tempting anyone to raise it.
 *
 * The form is still exercised, once, here — so a broken login screen fails
 * the run rather than being skipped past.
 */
setup('the admin logs in once, and the session is reused', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(ADMIN_EMAIL);
    await page.locator('#password').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: /inloggen|log in/i }).click();

    // The dashboard is the only authenticated landing page, so arriving there
    // is the signal that the session exists rather than that a form submitted.
    await expect(page).toHaveURL(/\/dashboard/);

    await page.context().storageState({ path: SESSION_STATE });
});
