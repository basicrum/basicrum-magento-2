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

async function guardNetwork(context, {
  endpointUrl = beaconUrl,
  storefrontUrl = process.env.MAGENTO_STOREFRONT_URL,
  adminUrl = process.env.MAGENTO_ADMIN_URL,
  blocked = []
} = {}) {
  const beacons = [];
  const endpoint = new URL(endpointUrl);
  const localOrigins = new Set([storefrontUrl, adminUrl].filter(Boolean).map(url => new URL(url).origin));
  await context.route("**/*", async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (url.origin === endpoint.origin && url.pathname === endpoint.pathname) {
      beacons.push(route.request());
      return route.fulfill({
        status: 204,
        headers: { "access-control-allow-origin": "*" },
        body: ""
      });
    }

    const parameters = requestParameters(request);
    const measurement = parameters.has("brum_site_id") || parameters.get("p_gen") === "mage2";
    if (localOrigins.has(url.origin) && !measurement) {
      return route.continue();
    }

    // Do not print query strings or POST bodies in failure diagnostics.
    blocked.push(url.origin + url.pathname);
    await route.abort("blockedbyclient");
  });
  // Boomerang uses HTTP, but do not let another script open an unguarded socket.
  await context.routeWebSocket("**/*", socket => {
    const url = new URL(socket.url());
    blocked.push(url.origin + url.pathname);
    socket.close();
  });
  return { beacons, blocked };
}

module.exports = { beaconUrl, guardNetwork, requestParameters, siteId };
