import { defineConfig, devices } from "@playwright/test";

/**
 * Test E2E. Si eseguono SOLO dentro il loro container: `./scripts/run-test-e2e.sh`.
 *
 * Fuori dal container mancano l'applicazione su localhost:8000, il database sqlite usa-e-getta
 * e le credenziali, che l'entrypoint genera e passa come variabili d'ambiente.
 */
const outputDir = process.env.E2E_OUTPUT_DIR || "/tmp/e2e-report";

export default defineConfig({
    testDir: "tests/e2e",
    testMatch: "**/*.spec.js",
    outputDir: `${outputDir}/results`,
    // Un worker solo: i test condividono un database sqlite, e alcuni lo azzerano.
    workers: 1,
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    timeout: 30_000,
    expect: { timeout: 10_000 },
    reporter: [["list"], ["html", { outputFolder: `${outputDir}/html`, open: "never" }]],
    use: {
        baseURL: process.env.E2E_BASE_URL || "http://localhost:8000",
        // L'italiano e' la lingua di partenza dell'applicazione; i test in inglese la cambiano
        // con `test.use()` (tests/e2e/i18n).
        locale: "it-IT",
        extraHTTPHeaders: { "Accept-Language": "it" },
        trace: "retain-on-failure",
        screenshot: "only-on-failure",
    },
    projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
