const { createServer } = require("node:http");
const { connect } = require("node:net");
const { test: base, expect } = require("@playwright/test");
const { guardNetwork } = require("../integration/beacons");
const { localProxy } = require("../integration/local-proxy");

// Both hostnames resolve to this loopback-only server. Unexpected requests can
// be observed without risking traffic to a real collector, even if the guard fails.
const test = base.extend({
  localNetwork: async ({ localStore }, use) => {
    const proxy = await localProxy([localStore.url]);
    try {
      await use(proxy);
    } finally {
      await proxy.close();
    }
  },
  proxy: async ({ localNetwork }, use) => use(localNetwork.options),
  localStore: async ({}, use) => {
    const requests = [];
    const server = createServer((request, response) => {
      requests.push(request.url);
      if (request.url === "/redirect-local") {
        response.writeHead(302, { location: "/redirect-external" });
      } else if (request.url === "/redirect-external") {
        response.writeHead(302, { location: `http://localhost:${server.address().port}/outside` });
      } else {
        response.writeHead(200, { "content-type": "text/html" });
      }
      response.end("<!doctype html><title>Local fixture</title>");
    });
    await new Promise(resolve => server.listen(0, "127.0.0.1", resolve));
    try {
      await use({
        url: `http://127.0.0.1:${server.address().port}`,
        otherOrigin: `http://localhost:${server.address().port}`,
        requests
      });
    } finally {
      await new Promise(resolve => {
        server.close(resolve);
        server.closeAllConnections();
      });
    }
  }
});

test("network guard intercepts the expected beacon and blocks unexpected destinations", async ({ context, page, localStore }) => {
  const traffic = await guardNetwork(context, {
    storefrontUrl: localStore.url,
    endpointUrl: `${localStore.otherOrigin}/beacon`
  });
  await page.goto(localStore.url);
  const results = await page.evaluate(async ({ url, otherOrigin }) => {
    const urls = [
      `${otherOrigin}/beacon`,
      `${otherOrigin}/wrong-path`,
      `${url}/unexpected-collector`
    ];
    return Promise.all(urls.map(async url => {
      try {
        await fetch(url, { method: "POST", body: "p_gen=mage2&brum_site_id=synthetic" });
        return "sent";
      } catch {
        return "blocked";
      }
    }));
  }, localStore);
  expect(results).toEqual(["sent", "blocked", "blocked"]);
  expect(traffic.beacons).toHaveLength(1);
  expect(traffic.blocked).toHaveLength(2);
  expect(localStore.requests).toEqual(["/"]);
});

test("network guard also applies to popup first requests", async ({ context, page, localStore }) => {
  const traffic = await guardNetwork(context, { storefrontUrl: localStore.url });
  const popup = context.waitForEvent("page");
  await page.evaluate(url => window.open(`${url}/popup`, "_blank"), localStore.otherOrigin);
  await popup;
  await expect.poll(() => traffic.blocked).toEqual([`${localStore.otherOrigin}/popup`]);
  expect(localStore.requests).toEqual([]);
});

test("same-origin redirect chains cannot escape to another origin", async ({ context, page, localStore, localNetwork }) => {
  const traffic = await guardNetwork(context, { storefrontUrl: localStore.url });
  const response = await page.goto(`${localStore.url}/redirect-local`);
  expect(response.status()).toBe(403);
  expect(traffic.blocked).toEqual([]);
  expect(localNetwork.blocked).toEqual([new URL(localStore.otherOrigin).host]);
  expect(localStore.requests).toEqual(["/redirect-local", "/redirect-external"]);
});

test("network guard closes WebSockets without connecting to their server", async ({ context, page, localStore }) => {
  const traffic = await guardNetwork(context, { storefrontUrl: localStore.url });
  await page.goto(localStore.url);
  await page.evaluate(url => new Promise(resolve => {
    const socket = new WebSocket(url.replace("http:", "ws:") + "/socket");
    socket.onclose = resolve;
    socket.onerror = resolve;
  }), localStore.url);
  expect(traffic.blocked).toEqual([localStore.url.replace("http:", "ws:") + "/socket"]);
  expect(localStore.requests).toEqual(["/"]);
});

test("proxy refuses an unexpected HTTPS tunnel before connecting", async ({ localNetwork, localStore }) => {
  const proxy = new URL(localNetwork.options.server);
  const destination = new URL(localStore.otherOrigin).host;
  const reply = await new Promise((resolve, reject) => {
    const socket = connect(Number(proxy.port), proxy.hostname, () => {
      socket.write(`CONNECT ${destination} HTTP/1.1\r\nHost: ${destination}\r\n\r\n`);
    });
    socket.once("error", reject);
    socket.once("data", data => {
      socket.destroy();
      resolve(data.toString());
    });
  });
  expect(reply).toContain("403 Forbidden");
  expect(localNetwork.blocked).toEqual([destination]);
  expect(localStore.requests).toEqual([]);
});
