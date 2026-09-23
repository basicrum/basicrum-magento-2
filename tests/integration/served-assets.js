const { createHash } = require("node:crypto");
const { readFile } = require("node:fs/promises");
const { resolve } = require("node:path");

const assetPaths = [
  "js/loaders/consent-boomerang-loader-v1-15.js",
  "js/loaders/consent-boomerang-loader-v1-15.min.js",
  "js/loaders/boomerang-loader-v15.js",
  "js/loaders/boomerang-loader-v15.min.js",
  "js/boomr/boomerang-1.815.60.cutting-edge.min.js"
];

async function verifyAsset(response) {
  const pathname = new URL(response.url()).pathname;
  const marker = "/Basicrum_Analytics/";
  if (!pathname.includes(marker) || !pathname.endsWith(".js")) return null;
  const asset = pathname.split(marker)[1];
  if (!assetPaths.includes(asset)) throw new Error(`Unrecognized Basicrum script: ${asset}`);
  if (response.status() !== 200) throw new Error(`Basicrum script was not served: ${asset}`);
  const expected = await readFile(resolve(__dirname, "../../view/frontend/web", asset));
  const served = await response.body();
  const hash = value => createHash("sha256").update(value).digest("hex");
  if (hash(served) !== hash(expected)) {
    throw new Error(`Served Basicrum asset differs from the candidate: ${asset}`);
  }
  return asset;
}

module.exports = { verifyAsset };
