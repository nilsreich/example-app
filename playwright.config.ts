import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./tests/e2e",
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [["list"], ["html", { open: "never" }]],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:8000",
        trace: "on-first-retry",
    },
    // Startet den Dev-Server automatisch, falls nicht bereits einer läuft
    // (lokal wie in CI). Port 8000 entspricht `php artisan serve`.
    webServer: process.env.PLAYWRIGHT_BASE_URL
        ? undefined
        : {
              command: "php artisan serve --host=127.0.0.1 --port=8000",
              url: "http://127.0.0.1:8000/up",
              reuseExistingServer: !process.env.CI,
              timeout: 60_000,
          },
    projects: [
        {
            name: "chromium",
            use: {
                ...devices["Desktop Chrome"],
                // Container läuft als root: Sandbox deaktivieren
                launchOptions: { args: ["--no-sandbox"] },
            },
        },
    ],
});
