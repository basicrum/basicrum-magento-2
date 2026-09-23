const { test, expect } = require("@playwright/test");
const { mkdtemp, mkdir, readdir, rm, readFile } = require("node:fs/promises");
const { join } = require("node:path");
const { tmpdir } = require("node:os");
const { verifyInstallation } = require("../integration/installation-proof");
const { verifyAsset } = require("../integration/served-assets");

test("installation challenge binds URL and web PHP to the local root and is always removed", async () => {
  const root = await mkdtemp(join(tmpdir(), "basicrum-installation-"));
  const previous = process.env.BASICRUM_DISPOSABLE_MAGENTO;
  await mkdir(join(root, "pub"));
  process.env.BASICRUM_DISPOSABLE_MAGENTO = "1";
  try {
    for (const condition of ["match", "wrong-root", "wrong-php", "redirect"]) {
      const request = { get: async url => {
        const name = new URL(url).pathname.slice(1);
        const nonce = name.match(/^basicrum-test-([a-f0-9]+)\.php$/)[1];
        expect(await readFile(join(root, "pub", name), "utf8")).toContain(nonce);
        return {
          status: () => condition === "redirect" ? 302 : 200,
          json: async () => ({ nonce: condition === "wrong-root" ? "stale" : nonce,
            php: condition === "wrong-php" ? "8.5.1" : "8.3.31" })
        };
      } };
      const check = verifyInstallation(request, root, ["https://store.test/"]);
      if (condition === "match") await check;
      else await expect(check).rejects.toThrow(/installation|MAGENTO_ROOT/);
      expect(await readdir(join(root, "pub"))).toEqual([]);
    }
    process.env.BASICRUM_DISPOSABLE_MAGENTO = "0";
    await expect(verifyInstallation({}, root, [])).rejects.toThrow("DISPOSABLE");
    expect(await readdir(join(root, "pub"))).toEqual([]);
  } finally {
    if (previous === undefined) delete process.env.BASICRUM_DISPOSABLE_MAGENTO;
    else process.env.BASICRUM_DISPOSABLE_MAGENTO = previous;
    await rm(root, { recursive: true, force: true });
  }
});

test("served asset evidence rejects stale bytes, unexpected scripts and error responses", async () => {
  const asset = "js/loaders/consent-boomerang-loader-v1-15.min.js";
  const bytes = await readFile(join(__dirname, "../../view/frontend/web", asset));
  const response = (body, status = 200, name = asset) => ({
    url: () => `https://store.test/static/version123/frontend/Magento/luma/en_US/Basicrum_Analytics/${name}`,
    status: () => status,
    body: async () => body
  });
  expect(await verifyAsset(response(bytes))).toBe(asset);
  await expect(verifyAsset(response(Buffer.from("stale")))).rejects.toThrow("differs");
  await expect(verifyAsset(response(bytes, 404))).rejects.toThrow("not served");
  await expect(verifyAsset(response(bytes, 200, "js/unexpected.js"))).rejects.toThrow("Unrecognized");
});
