const { expect } = require("@playwright/test");
const { beaconUrl } = require("./beacons");

function policySources(csp, directive) {
  const policy = csp.split(";")
    .map((value) => value.trim().split(/\s+/))
    .find((values) => values[0] === directive);
  expect(policy, directive + " must be explicitly present").toBeTruthy();
  return policy.slice(1);
}

function expectStorefrontCsp(csp) {
  expect(csp).toBeTruthy();
  for (const directive of ["connect-src", "img-src", "script-src"]) {
    const sources = policySources(csp, directive);
    expect(sources).toContain("'self'");
    // Stable fixture from enabled Magento_Paypal/etc/csp_whitelist.xml.
    // This detects losing the core whitelist collector as well as core config.
    expect(sources).toContain("www.paypal.com");
    if (directive !== "script-src") {
      expect(sources).toContain(new URL(beaconUrl).origin);
    }
  }
}

function expectAdminCsp(headers) {
  const policies = ["content-security-policy", "content-security-policy-report-only"]
    .map((name) => headers[name]).filter(Boolean);
  expect(policies.length).toBeGreaterThan(0);
  for (const csp of policies) {
    expect(policySources(csp, "script-src")).toContain("'self'");
    for (const directive of ["connect-src", "img-src"]) {
      expect(policySources(csp, directive)).not.toContain(new URL(beaconUrl).origin);
    }
  }
}

function expectEnforcingScriptCsp(headers, nonce) {
  const csp = headers["content-security-policy"];
  expect(csp, "An enforcing header is required, not report-only").toBeTruthy();
  expectStorefrontCsp(csp);
  const sources = policySources(csp, "script-src");
  expect(sources).not.toContain("'unsafe-inline'");
  expect(typeof nonce).toBe("string");
  expect(nonce.length).toBeGreaterThan(0);
  expect(sources, "Magento's inline bootstrap nonce must match the header").toContain(`'nonce-${nonce}'`);
}

module.exports = { expectAdminCsp, expectStorefrontCsp, expectEnforcingScriptCsp };
