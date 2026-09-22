const http = require("node:http");
const net = require("node:net");

// Playwright routes only the first request of a redirect chain. Keep the
// transport restricted to the disposable installation, including HTTPS tunnels.
async function localProxy(urls) {
  const allowed = new Set(urls.filter(Boolean).map(value => {
    const url = new URL(value);
    return `${url.hostname}:${url.port || (url.protocol === "https:" ? 443 : 80)}`;
  }));
  const blocked = [];
  const sockets = new Set();
  const accept = (hostname, port) => {
    const address = `${hostname}:${port}`;
    if (allowed.has(address)) return true;
    blocked.push(address);
    return false;
  };
  const track = socket => {
    sockets.add(socket);
    socket.on("close", () => sockets.delete(socket));
    return socket;
  };
  const server = http.createServer((request, response) => {
    const url = new URL(request.url);
    if (url.protocol !== "http:" || !accept(url.hostname, url.port || 80)) {
      response.writeHead(403).end();
      return;
    }
    const upstream = http.request(url, { method: request.method, headers: request.headers }, reply => {
      response.writeHead(reply.statusCode, reply.headers);
      reply.pipe(response);
    });
    upstream.on("socket", track);
    upstream.on("error", () => response.destroy());
    request.pipe(upstream);
  });
  server.on("connection", track);
  server.on("connect", (request, client, head) => {
    const url = new URL(`https://${request.url}`);
    if (!accept(url.hostname, url.port || 443)) {
      client.end("HTTP/1.1 403 Forbidden\r\n\r\n");
      return;
    }
    const upstream = track(net.connect(Number(url.port || 443), url.hostname, () => {
      client.write("HTTP/1.1 200 Connection Established\r\n\r\n");
      if (head.length) upstream.write(head);
      client.pipe(upstream);
      upstream.pipe(client);
    }));
    upstream.on("error", () => client.destroy());
    client.on("error", () => upstream.destroy());
    client.on("close", () => upstream.destroy());
  });
  await new Promise(resolve => server.listen(0, "127.0.0.1", resolve));
  return {
    // Chromium normally bypasses proxies for loopback; tests must not.
    options: { server: `http://127.0.0.1:${server.address().port}`, bypass: "<-loopback>" },
    blocked,
    close: () => new Promise(resolve => {
      server.close(resolve);
      for (const socket of sockets) socket.destroy();
    })
  };
}

module.exports = { localProxy };
