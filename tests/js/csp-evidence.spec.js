const { test, expect } = require("@playwright/test");
const { expectEnforcingScriptCsp } = require("../integration/csp");
const { beaconUrl } = require("../integration/beacons");

const nonce = "native-test-nonce";
const collector = new URL(beaconUrl).origin;
const policy = `script-src 'self' www.paypal.com 'nonce-${nonce}'; ` +
  `connect-src 'self' www.paypal.com ${collector}; img-src 'self' www.paypal.com ${collector};`;

test("enforcing script evidence rejects report-only, unsafe-inline and missing or mismatched nonces", () => {
  expectEnforcingScriptCsp({ "content-security-policy": policy }, nonce);
  expect(() => expectEnforcingScriptCsp({ "content-security-policy-report-only": policy }, nonce)).toThrow();
  for (const incorrect of [undefined, "", "different-nonce"]) {
    expect(() => expectEnforcingScriptCsp({ "content-security-policy": policy }, incorrect)).toThrow();
  }
  expect(() => expectEnforcingScriptCsp({
    "content-security-policy": policy.replace("script-src", "script-src 'unsafe-inline'")
  }, nonce)).toThrow();
  expect(() => expectEnforcingScriptCsp({
    "content-security-policy": policy.replace(`'nonce-${nonce}'`, "")
  }, nonce)).toThrow();
});
