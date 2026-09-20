const fs = require("node:fs");
const path = require("node:path");
const UglifyJS = require("uglify-js");

const root = path.resolve(__dirname, "../..");
const loaders = [
  "boomerang-loader-v15",
  "consent-boomerang-loader-v1-15"
];

for (const loader of loaders) {
  const sourcePath = path.join(root, "view/frontend/web/js/loaders", `${loader}.js`);
  const outputPath = path.join(root, "view/frontend/web/js/loaders", `${loader}.min.js`);
  const result = UglifyJS.minify(fs.readFileSync(sourcePath, "utf8"), {
    compress: false,
    mangle: true,
    output: { comments: /^!/ }
  });

  if (result.error) {
    throw result.error;
  }

  fs.writeFileSync(outputPath, result.code);
}
