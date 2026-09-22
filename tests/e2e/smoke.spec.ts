import { test, expect } from "@playwright/test";

test("homepage antwortet 200 und zeigt neutralen Template-Titel", async ({
    page,
}) => {
    const response = await page.goto("/");
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/B2E-Template/);
});

test("browser sanity check gegen example.com", async ({ page }) => {
    await page.goto("https://example.com");
    await expect(page.locator("h1")).toContainText("Example Domain");
});
