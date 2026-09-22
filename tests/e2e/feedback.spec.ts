import { test, expect } from "@playwright/test";

/**
 * E2E: In-App-Feedback-Widget (Phase 3).
 *
 * Voraussetzungen (Demo-Umgebung):
 *  - `php artisan db:seed-kiventro-demo` ausgeführt (aktiviert u.a. das
 *    Setting `feedback_widget_enabled`)
 *  - config/feedback.php: enabled=true (Default)
 *
 * Abgedeckt wird der Kernpfad: Login → Widget öffnen → Meldung senden →
 * Erfolgsmeldung. Der "Element markieren"- und Screenshot-Pfad sind absichtlich
 * NICHT Teil dieses Specs (getDisplayMedia/Screen-Capture ist in Headless-
 * Browsern nicht zuverlässig) – er ist über die Feature-Tests abgedeckt
 * (tests/Feature/FeedbackFeedbackReportTest.php: Screenshot-Speicherung,
 * tests/Feature/FeedbackScreenshotTest.php: policy-geschützte Auslieferung).
 */

const DEMO_ADMIN = { email: "admin@kiventro.de", password: "kiventro-demo" };

async function loginAsDemoAdmin(page) {
    await page.goto("/admin/login");
    await page.locator('[id="form.email"]').fill(DEMO_ADMIN.email);
    await page.locator('[id="form.password"]').fill(DEMO_ADMIN.password);
    await page.locator('button[type="submit"]').click();
    // Nach dem Login landet man im Admin-Dashboard.
    await expect(page).toHaveURL(/\/admin$/);
}

test.describe("Feedback-Widget (Demo-Admin)", () => {
    test("Widget ist sichtbar, Meldung wird angenommen", async ({ page }) => {
        await loginAsDemoAdmin(page);
        await expect(page.locator("[data-feedback-widget]")).toBeVisible();

        // Panel öffnen.
        await page.locator("[data-fw-open]").click();
        await expect(page.locator("[data-fw-panel]")).toBeVisible();

        // Kategorie + Meldung ausfüllen, absenden.
        await page.locator("[data-fw-category]").selectOption("bug");
        await page
            .locator("[data-fw-message]")
            .fill(
                "E2E: Schichtzuweisung zeigt doppelten Eintrag im Dashboard.",
            );
        await expect(page.locator("[data-fw-submit]")).toBeEnabled();
        await page.locator("[data-fw-submit]").click();

        // Erfolg: Statusmeldung erscheint, Panel schließt sich kurz danach.
        await expect(page.locator("[data-fw-status]")).toContainText("Danke");
    });

    test("Formular validiert: zu kurze Meldung blockiert den Versand", async ({
        page,
    }) => {
        await loginAsDemoAdmin(page);

        await page.locator("[data-fw-open]").click();
        await page.locator("[data-fw-message]").fill("ab");
        await expect(page.locator("[data-fw-submit]")).toBeDisabled();

        await page
            .locator("[data-fw-message]")
            .fill("Eine ausreichend lange Meldung.");
        await expect(page.locator("[data-fw-submit]")).toBeEnabled();
    });
});
