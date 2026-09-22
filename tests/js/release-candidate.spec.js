const { test, expect } = require("@playwright/test");
const { spawnSync } = require("node:child_process");
const { mkdtempSync, mkdirSync, copyFileSync, writeFileSync, rmSync } = require("node:fs");
const { tmpdir } = require("node:os");
const { join } = require("node:path");

test("candidate guard requires its own clean committed checkout", () => {
  const root = mkdtempSync(join(tmpdir(), "basicrum-release-candidate-"));
  const script = join(root, "tests/integration/check-candidate.sh");
  const run = (command, args) => spawnSync(command, args, { cwd: root, encoding: "utf8" });
  const git = (...args) => {
    const result = run("git", args);
    expect(result.status, result.stderr).toBe(0);
    return result.stdout.trim();
  };
  const commit = () => git("-c", "user.name=Basicrum Test", "-c", "user.email=test@example.test", "commit", "--no-gpg-sign", "-m", "fixture");
  const check = () => run("sh", [script]);
  try {
    mkdirSync(join(root, "tests/integration"), { recursive: true });
    copyFileSync(join(__dirname, "../integration/check-candidate.sh"), script);
    git("init", "--quiet");
    git("config", "core.hooksPath", "/dev/null");
    expect(check().status).not.toBe(0); // No committed candidate yet.
    git("add", ".");
    commit();
    const clean = check();
    expect(clean.status, clean.stderr).toBe(0);
    expect(clean.stdout.trim()).toBe(git("rev-parse", "HEAD"));
    writeFileSync(join(root, "extra.php"), "<?php // untracked production source");
    expect(check().stderr).toContain("must be clean");
    git("add", "extra.php");
    expect(check().stderr).toContain("must be clean");
    commit();
    writeFileSync(join(root, "extra.php"), "<?php // modified source");
    expect(check().stderr).toContain("must be clean");

    const nested = join(root, "nested/tests/integration");
    mkdirSync(nested, { recursive: true });
    copyFileSync(script, join(nested, "check-candidate.sh"));
    expect(run("sh", [join(nested, "check-candidate.sh")]).stderr).toContain("module's own Git checkout");
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
