const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const path = require("node:path");
const UglifyJS = require("uglify-js");

const root = path.resolve(__dirname, "../..");

function sha256(contents) {
  return crypto.createHash("sha256").update(contents).digest("hex");
}

for (const loader of [
  "boomerang-loader-v15",
  "consent-boomerang-loader-v1-15"
]) {
  const source = fs.readFileSync(
    path.join(root, "view/frontend/web/js/loaders", `${loader}.js`),
    "utf8"
  );
  const actual = fs.readFileSync(
    path.join(root, "view/frontend/web/js/loaders", `${loader}.min.js`),
    "utf8"
  );
  const result = UglifyJS.minify(source, {
    compress: false,
    mangle: true,
    output: { comments: /^!/ }
  });

  if (result.error) {
    throw result.error;
  }

  assert.equal(actual, result.code, `${loader}.min.js must be regenerated from source`);

  if (loader === "boomerang-loader-v15") {
    assert.equal(
      sha256(source),
      "e22055fc1919b89ab8ba6530a415636c6d31df328c10f096a500ae5a37e93d6d",
      "readable standard loader must match the reviewed WordPress source"
    );
    assert.equal(
      sha256(actual),
      "a9c283722d1d2eb97a7e1820d51ba3c317a5f2641f7358922931ae305aab0281",
      "minified standard loader must match the reviewed WordPress source"
    );
  }
}

const standardSource = fs.readFileSync(
  path.join(root, "view/frontend/web/js/loaders/boomerang-loader-v15.js"),
  "utf8"
).trim();
const consentSource = fs.readFileSync(
  path.join(root, "view/frontend/web/js/loaders/consent-boomerang-loader-v1-15.js"),
  "utf8"
);
const embedded = consentSource.match(
  /\/\* BEGIN BASICRUM STANDARD LOADER \*\/\n([\s\S]*?)\n    \/\* END BASICRUM STANDARD LOADER \*\//
);
assert.ok(embedded, "consent wrapper must contain the marked standard loader block");
assert.equal(
  embedded[1].trim(),
  standardSource,
  "consent wrapper standard-loader block must remain byte-identical"
);

const boomerang = fs.readFileSync(
  path.join(root, "view/frontend/web/js/boomr/boomerang-1.815.60.cutting-edge.min.js")
);
assert.equal(
  sha256(boomerang),
  "90e8a1c85949b10d43e441efc3f0545f95e4384e26ee3042344a8b2b4110589c",
  "Boomerang artifact must match the reviewed build"
);

console.log("Minified loader checks passed.");
