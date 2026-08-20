const CACHE_PREFIX = "emc-";
const CACHE_NAME = "emc-static-v7";
const APP_SHELL = [
  "./",
  "./offline.html",
  "./offline.css",
  "./manifest.webmanifest",
  "./manifest-admin.webmanifest",
  "./icon-192.png",
  "./icon-512.png",
  "./maskable-512.png",
  "./apple-touch-icon.png",
  "./emcicon.jpg",
];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
});

self.addEventListener("message", (event) => {
  if (event.data === "SKIP_WAITING") self.skipWaiting();
});

self.addEventListener("push", (event) => {
  let data = {};
  try { data = event.data?.json() || {}; } catch { data = { body: event.data?.text() || "" }; }
  event.waitUntil(self.registration.showNotification(data.title || "EMC Shoes Care", {
    body: data.body || "You have a new update.",
    icon: "./icon-192.png",
    badge: "./icon-192.png",
    tag: data.tag || "emc-update",
    data: { url: data.url || "./" },
  }));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const targetUrl = new URL(event.notification.data?.url || "./", self.registration.scope).href;
  event.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(async (clients) => {
    const existing = clients.find((client) => client.url.startsWith(self.registration.scope));
    if (existing) {
      await existing.navigate(targetUrl);
      return existing.focus();
    }
    return self.clients.openWindow(targetUrl);
  }));
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME).map((key) => caches.delete(key)),
    )),
  );
  self.clients.claim();
});

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response.ok && response.type === "basic") {
      const cache = await caches.open(CACHE_NAME);
      await cache.put(request, response.clone());
    }
    return response;
  } catch {
    return (await caches.match(request)) || (await caches.match("./")) || caches.match("./offline.html");
  }
}

async function staleWhileRevalidate(request) {
  const cached = await caches.match(request);
  const network = fetch(request).then(async (response) => {
    if (response.ok && response.type === "basic") {
      const cache = await caches.open(CACHE_NAME);
      await cache.put(request, response.clone());
    }
    return response;
  }).catch(() => null);
  return cached || network || Response.error();
}

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") return;
  const requestUrl = new URL(event.request.url);

  // Authentication, orders, admin data, and private photos always come from the network.
  if (requestUrl.origin !== self.location.origin || requestUrl.pathname.includes("/api/")) {
    event.respondWith(fetch(event.request));
    return;
  }

  if (event.request.mode === "navigate") {
    event.respondWith(networkFirst(event.request));
    return;
  }

  if (["script", "style", "image", "font", "manifest"].includes(event.request.destination)) {
    event.respondWith(staleWhileRevalidate(event.request));
  }
});
