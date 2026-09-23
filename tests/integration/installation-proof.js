const { randomBytes } = require("node:crypto");
const { writeFile, unlink, readFile } = require("node:fs/promises");
const { join } = require("node:path");

// Test-only, short-lived challenge. No diagnostic endpoint is shipped by the module.
async function verifyInstallation(request, root, urls) {
  if (process.env.BASICRUM_DISPOSABLE_MAGENTO !== "1" || !root) {
    throw new Error("Storefront verification requires BASICRUM_DISPOSABLE_MAGENTO=1 and MAGENTO_ROOT.");
  }
  const nonce = randomBytes(32).toString("hex");
  const name = `basicrum-test-${nonce}.php`;
  const path = join(root, "pub", name);
  const baseline = await readFile(join(__dirname, "baseline.env"), "utf8");
  const phpLine = baseline.match(/^PHP_VERSION=(.+)$/m)[1];
  await writeFile(path, `<?php\nheader('Cache-Control: no-store');\nheader('Content-Type: application/json');\necho json_encode(['nonce' => '${nonce}', 'php' => PHP_VERSION]);\n`, { flag: "wx" });
  try {
    for (const base of new Set(urls.filter(Boolean))) {
      const response = await request.get(new URL(name, base).href, { maxRedirects: 0 });
      if (response.status() !== 200) {
        throw new Error(`The browser URL does not serve the verified MAGENTO_ROOT (challenge HTTP ${response.status()}).`);
      }
      const proof = await response.json();
      if (proof.nonce !== nonce || !proof.php?.startsWith(`${phpLine}.`)) {
        throw new Error("Storefront installation/PHP runtime does not match the verified baseline.");
      }
    }
  } finally {
    await unlink(path);
  }
}

module.exports = { verifyInstallation };
