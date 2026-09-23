const { test, expect } = require("@playwright/test");
const { spawnSync } = require("node:child_process");
const { mkdtempSync, rmSync } = require("node:fs");
const { tmpdir } = require("node:os");
const { join } = require("node:path");

// Exercise the real shell script with CLI doubles, without Magento or network
// access. Remap its guarded /var/www/html cd to an empty temporary directory.
const harness = `
basicrum_test_analytics_disabled=0
cd() { command cd "$BASICRUM_TEST_ROOT"; }
composer() { printf 'composer %s\\n' "$*"; }
php() {
    printf 'php %s\\n' "$*"
    if [ "$2" = module:disable ]; then
        for argument in "$@"; do
            if [ "$argument" = Magento_AdminAnalytics ]; then basicrum_test_analytics_disabled=1; fi
        done
    fi
    if [ "$2" = config:set ]; then
        if [ "$3" = admin/usage/enabled ] && [ "$basicrum_test_analytics_disabled" = 1 ]; then
            echo 'The "admin/usage/enabled" path does not exist.' >&2
            return 1
        fi
        if [ "$3" = "$BASICRUM_TEST_FAIL_CONFIG" ]; then
            echo 'Synthetic configuration failure' >&2
            return 42
        fi
    fi
}
. "$BASICRUM_PROVISION_SCRIPT"
`;

test("fresh provisioning keeps Admin Analytics disabled without writing its removed field", () => {
  const root = mkdtempSync(join(tmpdir(), "basicrum-provisioning-"));
  try {
    const run = extra => spawnSync("sh", ["-c", harness], {
      cwd: root,
      encoding: "utf8",
      env: {
        ...process.env,
        BASICRUM_TEST_ROOT: root,
        BASICRUM_PROVISION_SCRIPT: join(__dirname, "../integration/docker/provision.sh"),
        BASICRUM_DISPOSABLE_MAGENTO: "1",
        MAGENTO_ROOT: "/var/www/html",
        MAGENTO_STOREFRONT_URL: "https://web:8443/",
        MAGENTO_ADMIN_USERNAME: "synthetic",
        MAGENTO_ADMIN_PASSWORD: "synthetic",
        BASICRUM_TEST_FAIL_CONFIG: "",
        ...extra
      }
    });
    const success = run({});
    expect(success.status, success.stderr).toBe(0);
    expect(success.stdout).toMatch(/module:disable .*Magento_AdminAnalytics/);
    expect(success.stdout).not.toContain("config:set admin/usage/enabled");
    expect(success.stdout).toContain("config:set system/smtp/disable 1");
    expect(success.stdout).toContain("config:set web/secure/use_in_frontend 1");
    expect(success.stdout).toContain("php bin/magento cache:enable");
    expect(success.stdout).toContain("Pinned disposable Magento is installed.");

    const failure = run({ BASICRUM_TEST_FAIL_CONFIG: "web/secure/use_in_frontend" });
    expect(failure.status).toBe(42);
    expect(failure.stderr).toContain("Synthetic configuration failure");
    expect(failure.stdout).not.toContain("php bin/magento cache:enable");
    expect(failure.stdout).not.toContain("Pinned disposable Magento is installed.");
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
