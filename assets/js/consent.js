/**
 * Cookie banner.
 *
 * Shows the banner until the visitor chooses, keeps the choice in a
 * first-party cookie, turns held-back scripts and embeds on for the allowed
 * categories, and clears cookies from categories the visitor turns off.
 *
 * Reopen the choices from anywhere: a link to #cookie-settings, any element
 * with the class sepo-cookie-settings, or window.SepoConsent.open().
 */
(function () {
  'use strict';

  var C = window.SepoConsentConfig;
  if (!C || window.SepoConsent) {
    return;
  }
  var T = C.texts;
  var L = C.i18n;
  var CATS = C.categories.map(function (c) { return c.id; });
  var listeners = [];
  var state = read();
  var root, banner, prefs, fab, lastFocus, statusTimer;

  // ------------------------------------------------------------ helpers

  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { n.textContent = attrs[k]; }
      else if (k === 'html') { n.innerHTML = attrs[k]; }
      else if (k === 'class') { n.className = attrs[k]; }
      else if (attrs[k] !== null && attrs[k] !== false && attrs[k] !== undefined) { n.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
    });
    (kids || []).forEach(function (k) { if (k) { n.appendChild(typeof k === 'string' ? document.createTextNode(k) : k); } });
    return n;
  }

  function fmt(s) {
    var args = Array.prototype.slice.call(arguments, 1);
    var i = 0;
    return String(s).replace(/%(\d+)\$[sd]|%[sd]/g, function (m, n) {
      return String(n ? args[n - 1] : args[i++]);
    });
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    var b = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(b);
    return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('').replace(/^(.{8})(.{4})(.{4})(.{4})/, '$1-$2-$3-$4-');
  }

  function label(id) {
    for (var i = 0; i < C.categories.length; i++) {
      if (C.categories[i].id === id) { return C.categories[i].label; }
    }
    return id;
  }

  function matches(pattern, name) {
    if (pattern.indexOf('*') < 0) { return pattern === name; }
    var re = new RegExp('^' + pattern.split('*').map(function (p) { return p.replace(/[.+?^${}()|[\]\\]/g, '\\$&'); }).join('.*') + '$');
    return re.test(name);
  }

  var ICON = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4.6-4.6A4 4 0 0 1 12 2Zm-3.5 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-1 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm5 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm4-1a1 1 0 1 1 0 2 1 1 0 0 1 0-2ZM12 9.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>';
  var CLOSE = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>';
  var CHEVRON = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>';

  // ------------------------------------------------------------ stored choice

  function read() {
    var m = document.cookie.match(new RegExp('(?:^|; )' + C.cookie + '=([^;]*)'));
    if (!m) { return null; }
    try {
      var d = JSON.parse(decodeURIComponent(m[1]));
      if (+d.v !== C.version || !Array.isArray(d.c)) { return null; }
      d.c = d.c.filter(function (c) { return CATS.indexOf(c) > -1; });
      if (d.c.indexOf('necessary') < 0) { d.c.unshift('necessary'); }
      return d;
    } catch (e) {
      return null;
    }
  }

  function write(d) {
    var v = encodeURIComponent(JSON.stringify(d));
    var c = C.cookie + '=' + v + '; path=' + C.path + '; max-age=' + (C.lifetime * 86400) + '; SameSite=Lax';
    if (C.domain) { c += '; domain=' + C.domain; }
    if (C.secure) { c += '; Secure'; }
    document.cookie = c;
  }

  function has(cat) {
    return cat === 'necessary' || !!(state && state.c.indexOf(cat) > -1);
  }

  /** Save a choice: cats is the full list of allowed categories. */
  function choose(cats, action) {
    var prev = state ? state.c.slice() : null;
    cats = CATS.filter(function (c) { return c === 'necessary' || cats.indexOf(c) > -1; });
    state = { v: C.version, c: cats, t: Math.floor(Date.now() / 1000), id: (state && state.id) || uuid() };
    write(state);
    record(action);
    signal();
    var removed = prev ? prev.filter(function (c) { return cats.indexOf(c) < 0; }) : [];
    clearCookies();
    hideBanner();
    closePrefs();
    showFab();
    emit();
    if (removed.length && !C.preview) {
      // Scripts that already ran cannot be stopped, so the page starts again without them.
      window.setTimeout(function () { window.location.reload(); }, 150);
      return;
    }
    activate();
    updateStatus();
  }

  function record(action) {
    if (!C.log || C.preview) { return; }
    var body = JSON.stringify({ id: state.id, c: state.c, action: action, url: location.href.split('#')[0] });
    try {
      fetch(C.log, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'omit' }).catch(function () {});
    } catch (e) { /* the choice still applies without a log entry */ }
  }

  /** Google Consent Mode and the WP Consent API. */
  function signal() {
    var g = function (c) { return has(c) ? 'granted' : 'denied'; };
    if (C.consentMode && typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        ad_storage: g('marketing'), ad_user_data: g('marketing'), ad_personalization: g('marketing'),
        analytics_storage: g('analytics'), functionality_storage: g('preferences'), personalization_storage: g('preferences')
      });
    }
    window.wp_consent_type = 'optin';
    if (typeof window.wp_set_consent === 'function') {
      CATS.forEach(function (c) {
        (C.wpConsent[c] || []).forEach(function (w) { window.wp_set_consent(w, has(c) ? 'allow' : 'deny'); });
      });
    }
  }

  function emit() {
    var detail = { categories: state ? state.c.slice() : ['necessary'], id: state ? state.id : '' };
    listeners.forEach(function (fn) { try { fn(detail); } catch (e) { /* one listener failing does not stop the rest */ } });
    document.dispatchEvent(new CustomEvent('sepo:consent', { detail: detail }));
  }

  /** Remove cookies from categories that are not allowed. */
  function clearCookies() {
    var names = document.cookie.split(/;\s*/).map(function (p) { return p.split('=')[0]; }).filter(Boolean);
    var host = location.hostname;
    var parts = host.split('.');
    var domains = [''];
    for (var i = 0; i < parts.length - 1; i++) {
      var d = parts.slice(i).join('.');
      domains.push(d, '.' + d);
    }
    Object.keys(C.clear).forEach(function (cat) {
      if (has(cat)) { return; }
      names.forEach(function (n) {
        if (n === C.cookie) { return; }
        var hit = C.clear[cat].some(function (p) { return matches(p, n); });
        if (!hit) { return; }
        domains.forEach(function (d) {
          ['/', C.path].forEach(function (path) {
            document.cookie = n + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + path + (d ? '; domain=' + d : '');
          });
        });
      });
    });
  }

  // ------------------------------------------------------------ held-back content

  /** Run held-back scripts for allowed categories, one after another, in page order. */
  function activate() {
    var scripts = Array.prototype.filter.call(document.querySelectorAll('script[type="text/plain"][data-sepo-consent]'), function (s) {
      return has(s.getAttribute('data-sepo-consent'));
    });
    (function next(i) {
      if (i >= scripts.length) { return; }
      var old = scripts[i];
      var s = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (a) {
        if (a.name === 'type' || a.name.indexOf('data-sepo-') === 0) { return; }
        s.setAttribute(a.name, a.value);
      });
      if (old.getAttribute('data-sepo-type')) { s.type = old.getAttribute('data-sepo-type'); }
      var src = old.getAttribute('data-sepo-src');
      if (src) {
        s.async = false;
        s.onload = s.onerror = function () { next(i + 1); };
        s.src = src;
      } else {
        s.text = old.text;
      }
      old.parentNode.replaceChild(s, old);
      if (!src) { next(i + 1); }
    })(0);

    Array.prototype.forEach.call(document.querySelectorAll('iframe[data-sepo-src]'), function (f) {
      var cat = f.getAttribute('data-sepo-consent');
      if (has(cat)) {
        f.src = f.getAttribute('data-sepo-src');
        f.removeAttribute('data-sepo-src');
        f.removeAttribute('hidden');
        f.style.removeProperty('display');
        var ph = f.previousElementSibling;
        if (ph && ph.classList.contains('sepo-cc-embed')) { ph.parentNode.removeChild(ph); }
      } else {
        placeholder(f, cat);
      }
    });
  }

  function placeholder(f, cat) {
    var prev = f.previousElementSibling;
    if (prev && prev.classList.contains('sepo-cc-embed')) { return; }
    var w = f.getAttribute('width');
    var h = f.getAttribute('height');
    var name = f.getAttribute('data-sepo-name') || '';
    var box = el('div', { class: 'sepo-cc-embed sepo-cc-scope', role: 'group', 'aria-label': name }, [
      el('span', { class: 'sepo-cc-embed__icon', html: ICON }),
      el('p', { class: 'sepo-cc-embed__text', text: fmt(T.blocked, name, label(cat).toLowerCase()) }),
      el('div', { class: 'sepo-cc-embed__actions' }, [
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--primary', 'data-allow': cat, text: T.allowBlocked }),
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--link', 'data-open': '1', text: T.revisit })
      ])
    ]);
    if (w && /^\d+$/.test(w)) { box.style.maxWidth = w + 'px'; }
    if (h && /^\d+$/.test(h)) { box.style.minHeight = Math.min(+h, 520) + 'px'; }
    if (/(\b|^)(wp-embed|wp-block-embed|responsive|embed)/.test(f.parentNode.className || '')) { box.classList.add('is-fill'); }
    f.style.display = 'none';
    f.parentNode.insertBefore(box, f);
    box.querySelector('[data-allow]').addEventListener('click', function () {
      choose((state ? state.c : ['necessary']).concat(cat), 'custom');
    });
    box.querySelector('[data-open]').addEventListener('click', function () { openPrefs(); });
  }

  // ------------------------------------------------------------ banner

  function build() {
    root = el('div', { class: 'sepo-cc sepo-cc-scope', 'data-layout': C.layout, 'data-position': C.position });
    var policy = C.policyUrl ? el('a', { class: 'sepo-cc__policy', href: C.policyUrl, text: T.policy }) : null;
    banner = el('div', {
      class: 'sepo-cc__banner',
      role: C.layout === 'modal' ? 'dialog' : 'region',
      'aria-modal': C.layout === 'modal' ? 'true' : null,
      'aria-labelledby': 'sepo-cc-title',
      'aria-describedby': 'sepo-cc-msg',
      tabindex: '-1',
      hidden: true
    }, [
      C.preview ? el('p', { class: 'sepo-cc__preview', text: L.preview }) : null,
      el('div', { class: 'sepo-cc__head' }, [
        el('span', { class: 'sepo-cc__icon', html: ICON }),
        el('h2', { class: 'sepo-cc__title', id: 'sepo-cc-title', text: T.title })
      ]),
      el('p', { class: 'sepo-cc__msg', id: 'sepo-cc-msg' }, [T.message + ' ', policy]),
      el('div', { class: 'sepo-cc__actions' }, [
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--ghost', 'data-act': 'manage', text: T.manage }),
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--soft', 'data-act': 'reject', text: T.reject }),
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--primary', 'data-act': 'accept', text: T.accept })
      ])
    ]);
    root.appendChild(banner);
    if (C.layout === 'modal') { root.appendChild(el('div', { class: 'sepo-cc__veil', hidden: true })); }
    banner.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]');
      if (!b) { return; }
      var a = b.getAttribute('data-act');
      if (a === 'accept') { choose(CATS.slice(), 'accept_all'); }
      else if (a === 'reject') { choose(['necessary'], 'reject_all'); }
      else { openPrefs(); }
    });
    banner.addEventListener('keydown', function (e) { if (C.layout === 'modal') { trap(e, banner); } });

    if (C.revisit === 'floating') {
      fab = el('button', { type: 'button', class: 'sepo-cc-fab sepo-cc-scope', 'data-position': C.position, 'aria-label': T.revisit, title: T.revisit, hidden: true, html: ICON });
      fab.addEventListener('click', function () { openPrefs(); });
      document.body.appendChild(fab);
    }
    document.body.appendChild(root);
    paintMode();
  }

  function showBanner() {
    banner.hidden = false;
    var veil = root.querySelector('.sepo-cc__veil');
    if (veil) { veil.hidden = false; document.documentElement.classList.add('sepo-cc-locked'); }
    if (fab) { fab.hidden = true; }
    requestAnimationFrame(function () { banner.classList.add('is-in'); });
    if (C.layout === 'modal') {
      lastFocus = document.activeElement;
      banner.focus({ preventScroll: true });
    }
  }

  function hideBanner() {
    if (!banner || banner.hidden) { return; }
    banner.classList.remove('is-in');
    banner.hidden = true;
    var veil = root.querySelector('.sepo-cc__veil');
    if (veil) { veil.hidden = true; }
    if (!prefs || prefs.hidden) { document.documentElement.classList.remove('sepo-cc-locked'); }
  }

  function showFab() {
    if (fab) { fab.hidden = false; }
  }

  // ------------------------------------------------------------ preferences

  function buildPrefs() {
    var body = el('div', { class: 'sepo-cc-prefs__body' }, [
      el('p', { class: 'sepo-cc-prefs__intro' }, [T.prefsIntro + ' ', C.policyUrl ? el('a', { href: C.policyUrl, text: T.policy }) : null])
    ]);
    C.categories.forEach(function (c) {
      var n = c.cookies.length;
      var listId = 'sepo-cc-list-' + c.id;
      var sw = c.locked
        ? el('span', { class: 'sepo-cc-badge', text: L.alwaysOn })
        : el('label', { class: 'sepo-cc-switch' }, [
          el('input', { type: 'checkbox', role: 'switch', 'data-cat': c.id, 'aria-label': c.label }),
          el('span', { class: 'sepo-cc-switch__track', 'aria-hidden': 'true' })
        ]);
      var list = el('div', { class: 'sepo-cc-cookies', id: listId, hidden: true });
      if (!n) {
        list.appendChild(el('p', { class: 'sepo-cc-cookies__none', text: L.none }));
      }
      c.cookies.forEach(function (k) {
        list.appendChild(el('div', { class: 'sepo-cc-cookie' }, [
          el('div', { class: 'sepo-cc-cookie__top' }, [
            el('code', { class: 'sepo-cc-cookie__name', text: k.name }),
            k.duration ? el('span', { class: 'sepo-cc-cookie__dur', text: k.duration }) : null
          ]),
          k.purpose ? el('p', { class: 'sepo-cc-cookie__purpose', text: k.purpose }) : null,
          k.provider ? el('p', { class: 'sepo-cc-cookie__meta', text: L.provider + ': ' + k.provider + (k.domain ? ' · ' + k.domain : '') }) : null
        ]));
      });
      var toggle = el('button', { type: 'button', class: 'sepo-cc-cat__more', 'aria-expanded': 'false', 'aria-controls': listId }, [
        el('span', { class: 'sepo-cc-cat__chev', html: CHEVRON }),
        el('span', { 'data-label': '1', text: L.showList }),
        el('span', { class: 'sepo-cc-cat__count', text: n === 1 ? L.countOne : fmt(L.count, n) })
      ]);
      toggle.addEventListener('click', function () {
        var open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('[data-label]').textContent = open ? L.hideList : L.showList;
        list.hidden = !open;
      });
      body.appendChild(el('section', { class: 'sepo-cc-cat' + (c.locked ? ' is-locked' : ''), 'data-cat-id': c.id }, [
        el('div', { class: 'sepo-cc-cat__head' }, [el('h3', { class: 'sepo-cc-cat__title', text: c.label }), sw]),
        el('p', { class: 'sepo-cc-cat__desc', text: c.description }),
        toggle,
        list
      ]));
    });

    var box = el('div', { class: 'sepo-cc-prefs__box', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'sepo-cc-prefs-title', tabindex: '-1' }, [
      el('header', { class: 'sepo-cc-prefs__head' }, [
        el('span', { class: 'sepo-cc__icon', html: ICON }),
        el('h2', { class: 'sepo-cc-prefs__title', id: 'sepo-cc-prefs-title', text: T.prefsTitle }),
        el('button', { type: 'button', class: 'sepo-cc-prefs__close', 'data-act': 'close', 'aria-label': L.close, html: CLOSE })
      ]),
      body,
      el('footer', { class: 'sepo-cc-prefs__foot' }, [
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--soft', 'data-act': 'reject', text: L.necessaryOnly }),
        el('span', { class: 'sepo-cc-prefs__gap' }),
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--ghost', 'data-act': 'save', text: T.save }),
        el('button', { type: 'button', class: 'sepo-cc-btn sepo-cc-btn--primary', 'data-act': 'accept', text: T.accept })
      ])
    ]);
    prefs = el('div', { class: 'sepo-cc-prefs sepo-cc-scope', hidden: true }, [el('div', { class: 'sepo-cc-prefs__veil', 'data-act': 'close' }), box]);
    prefs.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]');
      if (!b) { return; }
      var a = b.getAttribute('data-act');
      if (a === 'close') { closePrefs(true); }
      else if (a === 'accept') { choose(CATS.slice(), 'accept_all'); }
      else if (a === 'reject') { choose(['necessary'], state ? 'revoke' : 'reject_all'); }
      else if (a === 'save') {
        var cats = ['necessary'];
        prefs.querySelectorAll('input[data-cat]').forEach(function (i) { if (i.checked) { cats.push(i.getAttribute('data-cat')); } });
        var all = cats.length === CATS.length;
        var withdrew = state && state.c.some(function (c) { return cats.indexOf(c) < 0; });
        choose(cats, all ? 'accept_all' : (withdrew ? 'revoke' : (cats.length === 1 ? 'reject_all' : 'custom')));
      }
    });
    prefs.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); closePrefs(true); return; }
      trap(e, box);
    });
    document.body.appendChild(prefs);
    paintMode();
  }

  function openPrefs() {
    if (!root) { build(); }
    if (!prefs) { buildPrefs(); }
    prefs.querySelectorAll('input[data-cat]').forEach(function (i) { i.checked = has(i.getAttribute('data-cat')); });
    if (!prefs.hidden) { return; }
    lastFocus = document.activeElement;
    prefs.hidden = false;
    document.documentElement.classList.add('sepo-cc-locked');
    requestAnimationFrame(function () { prefs.classList.add('is-in'); });
    // The window itself takes focus, so a screen reader starts at its title and the list opens at the top.
    prefs.querySelector('.sepo-cc-prefs__body').scrollTop = 0;
    prefs.querySelector('.sepo-cc-prefs__box').focus({ preventScroll: true });
  }

  /** userClosed: the visitor closed it without choosing, so the banner returns when there is no choice yet. */
  function closePrefs(userClosed) {
    if (!prefs || prefs.hidden) { return; }
    prefs.classList.remove('is-in');
    prefs.hidden = true;
    document.documentElement.classList.remove('sepo-cc-locked');
    if (userClosed && !state) {
      showBanner();
      (banner.querySelector('[data-act="manage"]')).focus();
      return;
    }
    if (lastFocus && document.contains(lastFocus) && lastFocus.focus) { lastFocus.focus(); }
    else if (fab && !fab.hidden) { fab.focus(); }
  }

  function trap(e, box) {
    if (e.key !== 'Tab') { return; }
    var f = Array.prototype.filter.call(box.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (n) {
      return !n.disabled && n.offsetParent !== null;
    });
    if (!f.length) { return; }
    var first = f[0];
    var last = f[f.length - 1];
    if (e.shiftKey && (document.activeElement === first || document.activeElement === box)) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  // ------------------------------------------------------------ dark mode

  /**
   * Themes mark dark mode in different ways. A declared marker on <html> or
   * <body> wins; otherwise the page background colour decides.
   */
  function isDark() {
    var nodes = [document.documentElement, document.body];
    for (var i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      if (!n) { continue; }
      var attr = ['data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode'];
      for (var j = 0; j < attr.length; j++) {
        var v = (n.getAttribute(attr[j]) || '').toLowerCase();
        if (v === 'dark') { return true; }
        if (v === 'light') { return false; }
      }
      if (/(^|\s)(dark|dark-mode|theme-dark|is-dark-theme|wp-dark-mode-active)(\s|$)/.test(n.className || '')) { return true; }
    }
    var bg = '';
    for (var k = 0; k < nodes.length; k++) {
      if (!nodes[k]) { continue; }
      var c = getComputedStyle(nodes[k]).backgroundColor;
      if (c && !/rgba\(\s*0,\s*0,\s*0,\s*0\s*\)|transparent/.test(c)) { bg = c; }
    }
    var m = bg.match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/);
    if (!m) { return false; }
    return (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255 < 0.4;
  }

  function paintMode() {
    var dark = isDark();
    document.querySelectorAll('.sepo-cc-scope').forEach(function (n) {
      n.classList.toggle('is-dark', dark);
    });
  }

  // ------------------------------------------------------------ cookie list shortcode

  function updateStatus() {
    window.clearTimeout(statusTimer);
    statusTimer = window.setTimeout(function () {
      document.querySelectorAll('[data-sepo-consent-status]').forEach(function (n) {
        if (!state) { n.textContent = L.statusNone; return; }
        var d = new Date(state.t * 1000);
        n.textContent = fmt(L.statusSet, state.c.map(label).join(', '), d.toLocaleDateString()) + ' ' + fmt(L.consentId, state.id);
      });
      document.querySelectorAll('[data-sepo-cat-state]').forEach(function (n) {
        var on = has(n.getAttribute('data-sepo-cat-state'));
        n.textContent = on ? L.allowed : L.notAllowed;
        n.classList.toggle('is-on', on);
      });
    }, 0);
  }

  // ------------------------------------------------------------ administrators: new cookies

  function report() {
    var R = C.report;
    if (!R) { return; }
    window.setTimeout(function () {
      var names = document.cookie.split(/;\s*/).map(function (p) { return p.split('=')[0]; }).filter(Boolean);
      var unknown = names.filter(function (n) {
        return !R.known.some(function (p) { return matches(p, n); });
      });
      var key = 'sepo-cc-reported';
      var seen = [];
      try { seen = JSON.parse(sessionStorage.getItem(key) || '[]'); } catch (e) { seen = []; }
      unknown = unknown.filter(function (n) { return seen.indexOf(n) < 0; });
      if (!unknown.length) { return; }
      fetch(R.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': R.nonce }, body: JSON.stringify({ cookies: unknown }) })
        .then(function () {
          try { sessionStorage.setItem(key, JSON.stringify(seen.concat(unknown))); } catch (e) { /* reported again next visit */ }
        })
        .catch(function () {});
    }, 4000);
  }

  // ------------------------------------------------------------ start

  function triggerFrom(t) {
    return t.closest && t.closest('a[href$="#cookie-settings"], a[href$="#sepo-cookie-settings"], .sepo-cookie-settings, [data-sepo-cookie-settings]');
  }

  function start() {
    build();
    clearCookies();
    activate();
    if (state && !C.preview) {
      signal();
      showFab();
    } else {
      showBanner();
    }
    updateStatus();
    report();
    if (/^#(sepo-)?cookie-settings$/.test(location.hash)) { openPrefs(); }

    document.addEventListener('click', function (e) {
      var t = triggerFrom(e.target);
      if (!t || (root && root.contains(t))) { return; }
      e.preventDefault();
      openPrefs();
    });
    window.addEventListener('hashchange', function () {
      if (/^#(sepo-)?cookie-settings$/.test(location.hash)) { openPrefs(); }
    });
    var mo = new MutationObserver(paintMode);
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode', 'style'] });
    mo.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode', 'style'] });
    if (window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      if (mq.addEventListener) { mq.addEventListener('change', function () { window.setTimeout(paintMode, 50); }); }
    }
  }

  window.SepoConsent = {
    /** Open the cookie choices. */
    open: openPrefs,
    /** The allowed categories, or null before the visitor chooses. */
    get: function () { return state ? state.c.slice() : null; },
    /** Is a category allowed? */
    has: has,
    /** Run fn({categories, id}) whenever the choice changes. */
    on: function (fn) { if (typeof fn === 'function') { listeners.push(fn); } },
    /** Allow every category, or only the given ones. */
    accept: function (cats) { choose(Array.isArray(cats) ? cats : CATS.slice(), Array.isArray(cats) ? 'custom' : 'accept_all'); },
    /** Withdraw everything except necessary cookies. */
    reject: function () { choose(['necessary'], state ? 'revoke' : 'reject_all'); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
