const { test, expect } = require("@playwright/test");
const { spawnSync } = require("node:child_process");
const { mkdtempSync, mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } = require("node:fs");
const { tmpdir } = require("node:os");
const { join, resolve } = require("node:path");

test("CSP fixture installer guards the target, refreshes it and rejects stale extra files", () => {
  const root = mkdtempSync(join(tmpdir(), "basicrum-csp-fixture-"));
  const installer = resolve(__dirname, "../integration/install-csp-fixture.sh");
  const calls = join(root, "magento-calls");
  const target = join(root, "magento/app/code/Basicrum/CspTest");
  try {
    mkdirSync(join(root, "magento/bin"), { recursive: true });
    writeFileSync(join(root, "magento/bin/magento"),
      '#!/bin/sh\nprintf "%s\\n" "$*" >> "$BASICRUM_TEST_MAGENTO_CALLS"\n', { mode: 0o755 });
    const run = extra => spawnSync("sh", [installer], {
      cwd: root,
      encoding: "utf8",
      env: {
        ...process.env,
        BASICRUM_DISPOSABLE_MAGENTO: "1",
        MAGENTO_ROOT: "magento", // A relative installation root must also work.
        BASICRUM_TEST_MAGENTO_CALLS: calls,
        ...extra
      }
    });
    expect(run({ BASICRUM_DISPOSABLE_MAGENTO: "" }).status).not.toBe(0);
    expect(run({ MAGENTO_ROOT: "missing" }).status).not.toBe(0);
    expect(existsSync(target)).toBe(false);
    expect(existsSync(calls)).toBe(false);

    const installed = run();
    expect(installed.status, installed.stdout + installed.stderr).toBe(0);
    const registration = join(target, "registration.php");
    const expected = readFileSync(registration, "utf8");
    writeFileSync(registration, "outdated fixture");
    const refreshed = run();
    expect(refreshed.status, refreshed.stdout + refreshed.stderr).toBe(0);
    expect(readFileSync(registration, "utf8")).toBe(expected);
    expect(readFileSync(calls, "utf8")).toBe("module:enable Basicrum_CspTest\n".repeat(2));

    const stale = join(target, "stale.php");
    writeFileSync(stale, "stale fixture file");
    const rejected = run();
    expect(rejected.status).not.toBe(0);
    expect(rejected.stderr).toContain("inspect and remove stale fixture files");
    expect(readFileSync(stale, "utf8")).toBe("stale fixture file");
    expect(readFileSync(calls, "utf8")).toBe("module:enable Basicrum_CspTest\n".repeat(2));
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
