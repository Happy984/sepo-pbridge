/*
 * Sepo Bridge — sign-in security for the signed-in person.
 * Used on the Profile screen and by the [sepo_account_security] shortcode,
 * plus the administrator's reset button on another user's edit screen.
 * Every confirmation is asked in the page, never in a browser dialog.
 */
(function () {
  'use strict';

  var i18n = (window.wp && window.wp.i18n) || null;
  var __ = function (s) { return i18n ? i18n.__(s, 'sepo-bridge') : s; };
  var sprintf = i18n ? i18n.sprintf : function (f) { var a = [].slice.call(arguments, 1), i = 0; return f.replace(/%(\d\$)?[sd]/g, function () { return a[i++]; }); };
  var _n = function (one, many, n) { return i18n ? i18n._n(one, many, n, 'sepo-bridge') : (n === 1 ? one : many); };

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function request(root, method, path, body) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-WP-Nonce': root.getAttribute('data-nonce') } };
    if (body) {
      body['return'] = root.getAttribute('data-return') || '';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(root.getAttribute('data-api') + path, opts).then(function (r) {
      return r.text().then(function (t) {
        var b = null;
        try { b = JSON.parse(t); } catch (e) { b = null; }
        if (!r.ok || !b) throw new Error((b && b.message) || __('That did not work. Please reload the page and try again.'));
        return b.data || {};
      });
    }, function () { throw new Error(__('The site could not be reached. Check your connection and try again.')); });
  }

  // ------------------------------------------------------------ dark mode

  /** A declared theme marker wins; otherwise the page background decides. */
  function isDark() {
    var nodes = [document.documentElement, document.body];
    var attrs = ['data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode'];
    for (var i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      if (!n) continue;
      for (var j = 0; j < attrs.length; j++) {
        var v = (n.getAttribute(attrs[j]) || '').toLowerCase();
        if (v === 'dark') return true;
        if (v === 'light') return false;
      }
      if (/(^|\s)(dark|dark-mode|theme-dark|is-dark-theme|wp-dark-mode-active)(\s|$)/.test(n.className || '')) return true;
    }
    var bg = '';
    [document.documentElement, document.body, document.getElementById('wpcontent')].forEach(function (n) {
      if (!n) return;
      var c = getComputedStyle(n).backgroundColor;
      if (c && !/rgba\(\s*0,\s*0,\s*0,\s*0\s*\)|transparent/.test(c)) bg = c;
    });
    var m = bg.match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/);
    return !!m && (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255 < 0.4;
  }
  function paint() {
    var dark = isDark();
    [].forEach.call(document.querySelectorAll('.sepo-si-scope'), function (s) { s.classList.toggle('is-dark', dark); });
  }

  // ------------------------------------------------------------ the account panel

  function Account(root) {
    this.root = root;
    this.body = root.querySelector('[data-si-body]');
    this.s = null;
    this.notice = null;
    this.load();
    var self = this;
    root.addEventListener('click', function (e) { self.click(e); });
    root.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.matches('[data-si-input]')) { e.preventDefault(); var b = e.target.closest('[data-si-form]').querySelector('[data-si-submit]'); if (b) b.click(); }
      if (e.key === 'Escape' && e.target.closest('[data-si-ask]')) { self.render(); }
    });
  }

  Account.prototype.load = function () {
    var self = this;
    var params = new URLSearchParams(location.search);
    var q = '?return=' + encodeURIComponent(this.root.getAttribute('data-return') || '');
    if (params.get('sepo_wb_error')) q += '&problem=' + encodeURIComponent(params.get('sepo_wb_error'));
    if (params.get('sepo_wb_linked')) q += '&linked=' + encodeURIComponent(params.get('sepo_wb_linked'));
    if (params.has('sepo_wb_error') || params.has('sepo_wb_linked')) {
      params.delete('sepo_wb_error');
      params.delete('sepo_wb_linked');
      try { history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : '') + location.hash); } catch (e) { /* the address keeps its parameters */ }
    }
    request(this.root, 'GET', '/account/security' + q).then(function (s) {
      self.s = s;
      if (s.notice) self.notice = s.notice;
      self.render();
      if (self.notice && location.hash === '#sepo-wb-security') self.root.scrollIntoView({ block: 'start' });
    }).catch(function (e) { self.body.innerHTML = '<p class="sepo-si-note sepo-si-note--bad" role="alert">' + esc(e.message) + '</p>'; });
  };

  Account.prototype.say = function (text, kind) {
    this.notice = { text: text, kind: kind || 'good' };
  };

  Account.prototype.noticeHtml = function () {
    if (!this.notice) return '';
    var n = this.notice;
    return '<p class="sepo-si-note sepo-si-note--' + esc(n.kind) + '" role="' + (n.kind === 'bad' ? 'alert' : 'status') + '">' + esc(n.text) + '</p>';
  };

  Account.prototype.render = function () {
    var s = this.s;
    var h = this.noticeHtml();
    h += this.twoFactorHtml();
    if (s.providers.length) h += this.providersHtml();
    if (s.history.length) {
      h += '<section class="sepo-si-card"><h3 class="sepo-si-h3">' + esc(__('Recent activity')) + '</h3><ul class="sepo-si-history">'
        + s.history.map(function (x) { return '<li><span>' + esc(x.label) + '</span><time>' + esc(x.at) + '</time></li>'; }).join('') + '</ul></section>';
    }
    this.body.innerHTML = h;
    paint();
  };

  Account.prototype.twoFactorHtml = function () {
    var s = this.s;
    var h = '<section class="sepo-si-card" data-si-2fa><div class="sepo-si-row"><h3 class="sepo-si-h3">' + esc(__('Authenticator app')) + '</h3>'
      + '<span class="sepo-si-pill' + (s.enabled ? ' is-on' : '') + '">' + esc(s.enabled ? __('On') : __('Off')) + '</span></div>';
    if (!s.offered && !s.enabled) {
      return h + '<p class="sepo-si-muted">' + esc(__('Two-factor authentication is not offered on this site.')) + '</p></section>';
    }
    if (s.required && !s.enabled) {
      h += '<p class="sepo-si-note sepo-si-note--warn"><strong>' + esc(__('Required for your account.')) + '</strong> ' + esc(__('Set it up now. Until you do, the rest of the site keeps bringing you back here.')) + '</p>';
    }
    if (s.enabled) {
      h += '<p>' + esc(__('Each time you sign in, you are asked for a six-digit code from your app.')) + ' '
        + (s.recoveryLeft > 0
          ? esc(sprintf(_n('%d recovery code left.', '%d recovery codes left.', s.recoveryLeft), s.recoveryLeft))
          : '<strong>' + esc(__('No recovery codes left. Make new ones before you need them.')) + '</strong>') + '</p>'
        + '<div class="sepo-si-actions"><button type="button" class="sepo-si-action" data-si-act="recovery">' + esc(__('Make new recovery codes')) + '</button>'
        + (s.required ? '' : '<button type="button" class="sepo-si-action sepo-si-action--danger" data-si-act="disable">' + esc(__('Turn off')) + '</button>') + '</div>';
      if (s.required) h += '<p class="sepo-si-muted">' + esc(__('Your role requires two-factor, so it cannot be turned off.')) + '</p>';
    } else if (!s.canStore) {
      h += '<p class="sepo-si-note sepo-si-note--bad">' + esc(__('Setup is unavailable on this server at the moment. Please tell the site administrator.')) + '</p>';
    } else {
      h += '<p>' + esc(__('Add a second step to your sign-in, so a stolen password is not enough on its own. Works with Google Authenticator, Microsoft Authenticator, Authy, 1Password and similar apps. No phone number or SMS.')) + '</p>'
        + '<div class="sepo-si-actions"><button type="button" class="sepo-si-action sepo-si-action--primary" data-si-act="begin">' + esc(__('Set it up')) + '</button></div>';
    }
    return h + '</section>';
  };

  Account.prototype.providersHtml = function () {
    var h = '<section class="sepo-si-card"><h3 class="sepo-si-h3">' + esc(__('Connected accounts')) + '</h3>'
      + '<p class="sepo-si-muted">' + esc(__('Sign in with one of these as well as with your password.')) + '</p><ul class="sepo-si-providers">';
    this.s.providers.forEach(function (p) {
      h += '<li class="sepo-si-provider" data-si-provider="' + esc(p.key) + '"><span class="sepo-si-provider__icon">' + p.icon + '</span>'
        + '<span class="sepo-si-provider__text"><strong>' + esc(p.label) + '</strong><span>'
        + esc(p.linked ? (p.email || __('Connected')) + (p.offered ? '' : ' · ' + __('no longer offered here')) : __('Not connected')) + '</span></span>'
        + (p.linked
          ? '<button type="button" class="sepo-si-action sepo-si-action--danger" data-si-act="unlink">' + esc(__('Disconnect')) + '</button>'
          : '<a class="sepo-si-action" href="' + esc(p.connect) + '">' + esc(__('Connect')) + '</a>')
        + '</li>';
    });
    return h + '</ul></section>';
  };

  /** Ask for a code inside the card, in place of a browser prompt. */
  Account.prototype.ask = function (card, opts) {
    var old = card.querySelector('[data-si-ask]');
    if (old) old.remove();
    var id = 'si-ask-' + Math.random().toString(36).slice(2, 8);
    var box = document.createElement('div');
    box.className = 'sepo-si-ask' + (opts.danger ? ' sepo-si-ask--danger' : '');
    box.setAttribute('data-si-ask', '');
    box.setAttribute('data-si-form', '');
    box.innerHTML = '<p class="sepo-si-ask__text">' + esc(opts.text) + '</p>'
      + (opts.code === false ? '' : '<label class="sepo-si-label" for="' + id + '">' + esc(opts.label || __('Code from your app, or a recovery code')) + '</label>'
        + '<input id="' + id + '" class="sepo-si-input sepo-si-input--code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="11" spellcheck="false" data-si-input>')
      + '<p class="sepo-si-error" role="alert" data-si-error></p>'
      + '<div class="sepo-si-actions"><button type="button" class="sepo-si-action ' + (opts.danger ? 'sepo-si-action--danger-solid' : 'sepo-si-action--primary') + '" data-si-submit>' + esc(opts.ok) + '</button>'
      + '<button type="button" class="sepo-si-action" data-si-cancel>' + esc(__('Cancel')) + '</button></div>';
    card.appendChild(box);
    var input = box.querySelector('[data-si-input]');
    (input || box.querySelector('[data-si-submit]')).focus();
    var self = this;
    box.querySelector('[data-si-cancel]').addEventListener('click', function () { box.remove(); });
    box.querySelector('[data-si-submit]').addEventListener('click', function () {
      var btn = this;
      var err = box.querySelector('[data-si-error]');
      var val = input ? input.value.trim() : '';
      if (input && val.replace(/[^0-9a-z]/gi, '').length < 6) { err.textContent = __('Enter the six digits your app shows, or a recovery code.'); input.focus(); return; }
      btn.disabled = true;
      err.textContent = '';
      Promise.resolve(opts.run(val)).catch(function (e) {
        btn.disabled = false;
        err.textContent = e.message;
        if (input) { input.select(); input.focus(); }
      });
    });
    return box;
  };

  Account.prototype.click = function (e) {
    var b = e.target.closest('[data-si-act]');
    if (!b) return;
    var act = b.getAttribute('data-si-act');
    var self = this;
    var root = this.root;
    var card = b.closest('.sepo-si-card');
    this.notice = null;

    if (act === 'begin') {
      b.disabled = true;
      request(root, 'POST', '/account/2fa/begin', {}).then(function (d) { self.setup(card, d); })
        .catch(function (err) { b.disabled = false; self.say(err.message, 'bad'); self.render(); });
    }
    if (act === 'recovery') {
      this.ask(card, {
        text: __('New recovery codes replace the old ones, which stop working. Enter a current code from your app to continue.'),
        label: __('Code from your app'), ok: __('Make new codes'),
        run: function (code) {
          return request(root, 'POST', '/account/2fa/recovery', { code: code }).then(function (d) { self.s = d.status; self.showCodes(d.recoveryCodes, true); });
        }
      });
    }
    if (act === 'disable') {
      this.ask(card, {
        danger: true,
        text: __('Turning this off removes the second step from your sign-in. Enter a current code, or one of your recovery codes, to confirm.'),
        ok: __('Turn off two-factor'),
        run: function (code) {
          return request(root, 'POST', '/account/2fa/disable', { code: code }).then(function (d) { self.s = d.status; self.say(__('Two-factor authentication is off.')); self.render(); });
        }
      });
    }
    if (act === 'unlink') {
      var li = b.closest('[data-si-provider]');
      var key = li.getAttribute('data-si-provider');
      var label = li.querySelector('strong').textContent;
      this.ask(card, {
        danger: true, code: false,
        text: sprintf(__('Disconnect %s? You will no longer be able to sign in with it. If you have never set a password here, set one first with "Lost your password?" on the login page, or you may lock yourself out.'), label),
        ok: sprintf(__('Disconnect %s'), label),
        run: function () {
          return request(root, 'POST', '/account/unlink', { provider: key }).then(function (d) { self.s = d.status; self.say(sprintf(__('%s is disconnected.'), label)); self.render(); });
        }
      });
    }
  };

  /** Step one: scan or type the key. Step two: the first code switches it on. */
  Account.prototype.setup = function (card, d) {
    var self = this;
    var root = this.root;
    var id = 'si-code-' + Math.random().toString(36).slice(2, 8);
    card.innerHTML = '<div class="sepo-si-row"><h3 class="sepo-si-h3">' + esc(__('Set up your authenticator app')) + '</h3></div>'
      + '<ol class="sepo-si-steps">'
      + '<li><strong>' + esc(__('Scan this code with the app')) + '</strong>'
      + '<div class="sepo-si-qr">' + d.qr + '</div>'
      + '<p class="sepo-si-muted">' + esc(__('On this phone? ')) + '<a href="' + esc(d.uri) + '">' + esc(__('Open it in your authenticator app')) + '</a>. '
      + esc(__('Or type this key into the app by hand:')) + '</p>'
      + '<code class="sepo-si-key">' + esc(d.key) + '</code>'
      + '<p class="sepo-si-muted">' + esc(sprintf(__('It will show up in the app as %s.'), d.issuer)) + '</p></li>'
      + '<li data-si-form><strong><label for="' + id + '">' + esc(__('Enter the six-digit code the app shows')) + '</label></strong>'
      + '<input id="' + id + '" class="sepo-si-input sepo-si-input--code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" spellcheck="false" placeholder="000000" data-si-input>'
      + '<p class="sepo-si-error" role="alert" data-si-error></p>'
      + '<div class="sepo-si-actions"><button type="button" class="sepo-si-action sepo-si-action--primary" data-si-submit>' + esc(__('Turn it on')) + '</button>'
      + '<button type="button" class="sepo-si-action" data-si-cancel>' + esc(__('Cancel')) + '</button></div></li></ol>';
    paint();
    var input = card.querySelector('[data-si-input]');
    var err = card.querySelector('[data-si-error]');
    var btn = card.querySelector('[data-si-submit]');
    input.focus();
    card.querySelector('[data-si-cancel]').addEventListener('click', function () { self.render(); });
    btn.addEventListener('click', function () {
      var code = input.value.replace(/\s/g, '');
      if (!/^\d{6}$/.test(code)) { err.textContent = __('Enter the six digits your app shows.'); input.focus(); return; }
      btn.disabled = true;
      err.textContent = '';
      request(root, 'POST', '/account/2fa/confirm', { code: code }).then(function (r) {
        self.s = r.status;
        self.showCodes(r.recoveryCodes, false);
      }).catch(function (e) { btn.disabled = false; err.textContent = e.message; input.select(); });
    });
  };

  /** The recovery codes, shown this once. */
  Account.prototype.showCodes = function (codes, renewed) {
    var self = this;
    var text = codes.join('\n');
    var file = 'data:text/plain;charset=utf-8,' + encodeURIComponent(sprintf(__('Recovery codes for %s'), location.host) + '\n\n' + text + '\n');
    this.body.innerHTML = '<section class="sepo-si-card sepo-si-card--codes">'
      + '<h3 class="sepo-si-h3">' + esc(renewed ? __('Your new recovery codes') : __('Two-factor is on. Save your recovery codes.')) + '</h3>'
      + '<p>' + esc(__('Each code signs you in once if you lose your phone. This is the only time they are shown: they are stored scrambled, so nobody can read them back, including the site administrator.')) + '</p>'
      + '<ul class="sepo-si-codes">' + codes.map(function (c) { return '<li><code>' + esc(c) + '</code></li>'; }).join('') + '</ul>'
      + '<div class="sepo-si-actions"><button type="button" class="sepo-si-action" data-si-copy>' + esc(__('Copy')) + '</button>'
      + '<a class="sepo-si-action" download="recovery-codes.txt" href="' + esc(file) + '">' + esc(__('Download')) + '</a>'
      + '<button type="button" class="sepo-si-action sepo-si-action--primary" data-si-done>' + esc(__('I have saved them')) + '</button></div>'
      + '<p class="sepo-si-muted" data-si-copied role="status" aria-live="polite"></p></section>';
    paint();
    this.body.querySelector('[data-si-done]').focus();
    this.body.querySelector('[data-si-copy]').addEventListener('click', function () {
      var note = self.body.querySelector('[data-si-copied]');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { note.textContent = __('Copied.'); }, function () { note.textContent = __('Select the codes and copy them.'); });
      } else {
        note.textContent = __('Select the codes and copy them.');
      }
    });
    this.body.querySelector('[data-si-done]').addEventListener('click', function () {
      self.say(renewed ? __('New recovery codes saved. The old ones no longer work.') : __('Two-factor authentication is on.'));
      self.render();
      self.root.scrollIntoView({ block: 'nearest' });
    });
  };

  // ------------------------------------------------------------ administrator reset

  function adminReset(root) {
    var btn = root.querySelector('[data-si-reset]');
    if (!btn) return;
    var box = root.querySelector('[data-si-reset-confirm]');
    var fb = root.querySelector('[data-si-feedback]');
    btn.addEventListener('click', function () { box.hidden = false; btn.hidden = true; box.querySelector('[data-si-reset-no]').focus(); });
    box.querySelector('[data-si-reset-no]').addEventListener('click', function () { box.hidden = true; btn.hidden = false; btn.focus(); });
    box.querySelector('[data-si-reset-yes]').addEventListener('click', function () {
      var yes = this;
      yes.disabled = true;
      request(root, 'POST', '/users/' + root.getAttribute('data-user') + '/2fa/reset', {}).then(function () {
        box.hidden = true;
        var pill = root.querySelector('[data-si-state]');
        pill.textContent = __('Off');
        pill.classList.remove('is-on');
        fb.textContent = __('Two-factor has been reset. They can sign in with their password and set it up again.');
      }).catch(function (e) { yes.disabled = false; fb.textContent = e.message; });
    });
  }

  function start() {
    paint();
    [].forEach.call(document.querySelectorAll('[data-sepo-si-account]'), function (r) { new Account(r); });
    [].forEach.call(document.querySelectorAll('[data-sepo-si-admin]'), adminReset);
    var mo = new MutationObserver(paint);
    var o = { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode', 'style'] };
    mo.observe(document.documentElement, o);
    if (document.body) mo.observe(document.body, o);
    if (window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      if (mq.addEventListener) mq.addEventListener('change', function () { setTimeout(paint, 50); });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
