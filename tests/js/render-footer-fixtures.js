const assert = require("node:assert/strict");
const { execFileSync } = require("node:child_process");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "../..");
const output = path.join(root, ".test-results/rendered-footer.json");

// Run once before workers start. Never check in a stale copy of production JS.
module.exports = function renderFooterFixtures() {
  const base = {
    "basicrum/general/enabled": "1",
    "basicrum/general/beacon_endpoint": "https://collector.example.test/beacon",
    "basicrum/general/brum_site_id": "550e8400-e29b-41d4-a716-446655440000",
    "basicrum/consent/enabled": "1"
  };
  const cases = {
    default: base,
    redacted: { ...base, "basicrum/privacy/strip_query_string": "1" },
    delayed: {
      ...base,
      "basicrum/performance/wait_after_onload": "1",
      "basicrum/performance/delay_ms": "1000"
    },
    disabledWait: {
      ...base,
      "basicrum/performance/wait_after_onload": "0",
      "basicrum/performance/delay_ms": "1000"
    },
    zeroWait: {
      ...base,
      "basicrum/performance/wait_after_onload": "1",
      "basicrum/performance/delay_ms": "0"
    }
  };
  const php = process.env.BASICRUM_TEST_PHP;
  const args = php ? ["tests/php/render-browser-fixtures.php"] : [
    "run", "--rm", "-i", "-v", root + ":/module:ro", "-w", "/module",
    "php:8.3-cli", "php", "tests/php/render-browser-fixtures.php"
  ];
  const rendered = JSON.parse(execFileSync(php || "docker", args, {
    cwd: root,
    encoding: "utf8",
    input: JSON.stringify(cases),
    timeout: 60000,
    stdio: ["pipe", "pipe", "inherit"]
  }));
  const scripts = {};
  for (const [name, html] of Object.entries(rendered)) {
    const tags = [...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/g)];
    assert.equal(tags.length, 2, name + ": expected bootstrap and loader");
    const inline = tags.filter((tag) => !/\bsrc=/.test(tag[1]));
    assert.equal(inline.length, 1, name + ": expected one inline bootstrap");
    scripts[name] = inline[0][2];
  }
  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, JSON.stringify(scripts));
};
