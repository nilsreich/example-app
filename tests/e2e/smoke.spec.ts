import { test, expect } from "@playwright/test";

test("homepage antwortet 200 und zeigt neutralen Template-Titel", async ({
    page,
}) => {
    const response = await page.goto("/");
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/B2E-Template/);
});

test("login page renders the neutral template brand", async ({ page }) => {
    const response = await page.goto("/admin/login");
    expect(response?.status()).toBe(200);
    await expect(page.locator("body")).toContainText("B2E-Template");
});
