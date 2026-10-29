/* داریا موبوآردر — سرویس‌ورکر سبک
   استاتیک‌های same-origin (css/js): cache-first با بروزرسانی پس‌زمینه؛ بقیه: شبکه */
'use strict';

var CACHE_NAME = 'mobo-v1';

self.addEventListener('install', function (e) {
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE_NAME; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;

  var url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (!/\.(css|js)(\?|$)/.test(url.pathname)) return;

  e.respondWith(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.match(req).then(function (hit) {
        var network = fetch(req).then(function (res) {
          if (res && res.ok) cache.put(req, res.clone());
          return res;
        }).catch(function () {
          return hit || Response.error();
        });
        return hit || network;
      });
    })
  );
});
