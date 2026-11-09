/* Sepo Bridge — copy-link and share-menu buttons, the contents box highlight, and the page-view count. */
(function () {
  'use strict';

  // ---------------------------------------------------------------- share
  function say(bar, text) {
    var s = bar && bar.querySelector('.sepo-share__status');
    if (!s) return;
    s.textContent = text;
    setTimeout(function () { if (s.textContent === text) s.textContent = ''; }, 2500);
  }
  if (navigator.share) {
    document.querySelectorAll('[data-sepo-share-native-wrap]').forEach(function (li) { li.hidden = false; });
  }
  document.addEventListener('click', function (ev) {
    var copy = ev.target.closest('[data-sepo-share-copy]');
    if (copy) {
      var url = copy.getAttribute('data-sepo-share-copy');
      var bar = copy.closest('[data-sepo-share]');
      var done = function () { say(bar, copy.getAttribute('data-done') || 'Link copied'); };
      var fallback = function () {
        var t = document.createElement('textarea');
        t.value = url; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        t.remove();
        if (ok) done(); else say(bar, url);
      };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(url).then(done, fallback);
      else fallback();
      return;
    }
    var nat = ev.target.closest('[data-sepo-share-native]');
    if (nat && navigator.share) {
      navigator.share({ title: nat.getAttribute('data-title') || document.title, url: nat.getAttribute('data-url') || location.href }).catch(function () {});
    }
  });

  // ---------------------------------------------------------------- contents box
  document.querySelectorAll('[data-sepo-toc]').forEach(function (box) {
    var links = [].slice.call(box.querySelectorAll('[data-sepo-toc-link]'));
    if (!links.length || !('IntersectionObserver' in window)) return;
    var byId = {};
    links.forEach(function (a) { byId[decodeURIComponent(a.getAttribute('href').slice(1))] = a; });
    var visible = {};
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { visible[e.target.id] = e.isIntersecting; });
      var current = null;
      links.some(function (a) { var id = decodeURIComponent(a.getAttribute('href').slice(1)); if (visible[id]) { current = a; return true; } return false; });
      if (current) {
        links.forEach(function (a) { a.classList.toggle('is-current', a === current); if (a === current) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current'); });
      }
    }, { rootMargin: '0px 0px -65% 0px' });
    Object.keys(byId).forEach(function (id) { var h = document.getElementById(id); if (h) io.observe(h); });
  });

  // ---------------------------------------------------------------- page view
  var cfg = window.SepoWBView;
  if (cfg && cfg.url && document.visibilityState !== 'prerender') {
    var send = function () {
      var body = JSON.stringify({ p: cfg.post || 0, u: location.pathname, r: document.referrer || '' });
      var url = cfg.url + (cfg.nonce ? (cfg.url.indexOf('?') > -1 ? '&' : '?') + '_wpnonce=' + encodeURIComponent(cfg.nonce) : '');
      try {
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'same-origin' }).catch(function () {});
      } catch (e) { /* no network: nothing to count */ }
    };
    if (document.readyState === 'complete') setTimeout(send, 300); else window.addEventListener('load', function () { setTimeout(send, 300); });
  }
})();
