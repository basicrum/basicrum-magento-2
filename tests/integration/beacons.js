const beaconUrl = process.env.MAGENTO_BEACON_URL || "https://collector.basicrum.test/beacon";
const siteId = process.env.MAGENTO_SITE_ID || "550e8400-e29b-41d4-a716-446655440000";

function requestParameters(request) {
  const parameters = new URL(request.url()).searchParams;
  if (request.postData()) {
    for (const [key, value] of new URLSearchParams(request.postData())) {
      parameters.set(key, value);
    }
  }
  return parameters;
}

async function interceptBeacons(page) {
  const beacons = [];
  const endpoint = new URL(beaconUrl);
  await page.route(
    (url) => url.origin === endpoint.origin && url.pathname === endpoint.pathname,
    (route) => {
      beacons.push(route.request());
      return route.fulfill({
        status: 204,
        headers: { "access-control-allow-origin": "*" },
        body: ""
      });
    }
  );
  return beacons;
}

module.exports = { beaconUrl, interceptBeacons, requestParameters, siteId };
