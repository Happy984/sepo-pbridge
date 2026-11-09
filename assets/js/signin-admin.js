/* Sepo Bridge — Sign-in tab. */
(function () {
  'use strict';
  var A = window.SepoWBAdmin;
  if (!A) return;
  var __ = A.__, sprintf = A.sprintf, esc = A.esc;
  var P = A.panel('signin');
  if (!P) return;
  var form = P.querySelector('[data-wb-si]');
  var D = null;

  function q(sel) { return form.querySelector(sel); }
  function qa(sel) { return [].slice.call(form.querySelectorAll(sel)); }

  A.addLoader('signin', load);

  function load() {
    A.setState('signin', 'loading');
    A.get('/signin').then(function (d) {
      D = d;
      A.setState('signin', 'ready');
      form.hidden = false;
      render();
    }).catch(function (e) { A.toast(e.message, true); });
  }

  function render() {
    var s = D.settings;
    qa('[data-si]').forEach(function (el) {
      var k = el.getAttribute('data-si');
      if (el.type === 'checkbox') el.checked = !!+s[k];
      else if (el.tagName !== 'SELECT') el.value = s[k] == null ? '' : s[k];
    });
    var role = q('[data-si="signup_role"]');
    role.innerHTML = D.signupRoles.map(function (r) { return '<option value="' + esc(r.value) + '">' + esc(r.label) + '</option>'; }).join('');
    role.value = s.signup_role;
    q('[data-si="twofactor_issuer"]').placeholder = D.siteName || '';
    q('[data-si-roles]').innerHTML = D.roles.map(function (r) {
      return '<label><input type="checkbox" data-si-role value="' + esc(r.value) + '"' + ((s.twofactor_roles || []).indexOf(r.value) !== -1 ? ' checked' : '') + '> ' + esc(r.label) + '</label>';
    }).join('');
    q('[data-si-login]').href = D.loginUrl;
    q('[data-si-enrolled]').textContent = sprintf(D.enrolled === 1 ? __('%d person has two-factor on.') : __('%d people have two-factor on.'), D.enrolled);
    q('[data-si-registration]').textContent = D.registration
      ? __('Anyone can register on this site (Settings → General), so new accounts can also be made with the normal form.')
      : __('Registration is closed in Settings → General. Creating accounts here works either way, because it is its own setting.');
    renderStatus();
    renderNotes();
    renderProviders();
  }

  function readyCount() { return D.providers.filter(function (p) { return p.ready; }).length; }

  function renderStatus() {
    var on = q('[data-si="enabled"]').checked;
    form.classList.toggle('is-off', !on);
    q('[data-si-status-title]').textContent = on ? __('Social sign-in is on') : __('Social sign-in is off');
    var n = readyCount();
    var sub = on
      ? (n ? sprintf(n === 1 ? __('%d provider is ready and shown on the login page.') : __('%d providers are ready and shown on the login page.'), n) : __('No provider is ready yet. Switch one on below and add its credentials.'))
      : __('No provider buttons are shown. Two-factor authentication works either way.');
    q('[data-si-status-sub]').textContent = sub;
  }

  function renderNotes() {
    var out = '';
    if (!D.https) {
      out += '<div class="wb-si-note"><strong>' + esc(__('This site is not on https.')) + '</strong>' + esc(__('Providers refuse a redirect address that is not https, except on localhost. Switch the site address to https before going live.')) + '</div>';
    }
    if (!D.pretty) {
      out += '<div class="wb-si-note">' + esc(__('Plain permalinks are in use, so the redirect addresses contain a query string. Google and GitHub accept that; Microsoft accounts for personal use do not. Any other permalink setting gives clean addresses.')) + '</div>';
    }
    if (!D.encryption) {
      out += '<div class="wb-si-note wb-si-note--bad"><strong>' + esc(__('Two-factor setup is unavailable on this server.')) + '</strong>' + esc(__('Authenticator secrets are stored encrypted, which needs PHP\'s OpenSSL extension with AES-256-GCM. Until it is available, setup is refused rather than storing secrets in plain text.')) + '</div>';
    }
    q('[data-si-notes]').innerHTML = out;
  }

  var fromLabels = { constant: __('from wp-config.php'), environment: __('from the server environment'), settings: '' };

  function renderProviders() {
    q('[data-si-providers]').innerHTML = D.providers.map(function (p) {
      var on = !!p.enabled;
      var pill = p.ready ? '<span class="wb-si-pill wb-si-pill--ready">' + esc(__('Ready')) + '</span>'
        : (on ? '<span class="wb-si-pill wb-si-pill--incomplete">' + esc(__('Needs credentials')) + '</span>' : '<span class="wb-si-pill">' + esc(__('Off')) + '</span>');
      var idLocked = p.id_from === 'constant' || p.id_from === 'environment';
      var secretLocked = p.secret_from === 'constant' || p.secret_from === 'environment';
      var secretPh = secretLocked ? __('Set outside the database') : (p.has_secret ? __('Saved. Leave empty to keep it.') : __('Paste the client secret'));
      return '<div class="wb-si-prov' + (on ? '' : ' is-off') + '" data-prov="' + esc(p.key) + '">'
        + '<div class="wb-si-prov__head">'
        + '<label class="wb-switch"><input type="checkbox" data-p="enabled"' + (on ? ' checked' : '') + ' aria-label="' + esc(sprintf(__('Offer %s'), p.label)) + '"><span class="wb-slider"></span></label>'
        + '<span class="wb-si-prov__name">' + esc(p.label) + '</span>' + pill
        + '</div>'
        + '<div class="wb-si-prov__body">'
        + '<span class="wb-si-label">' + esc(__('Redirect address to register')) + '</span>'
        + '<div class="wb-si-uri"><code>' + esc(p.redirect_uri) + '</code><button type="button" class="wb-btn wb-btn--sm" data-copy="' + esc(p.redirect_uri) + '">' + esc(__('Copy')) + '</button>'
        + (p.console ? '<a class="wb-btn wb-btn--sm wb-btn--link" href="' + esc(p.console) + '" target="_blank" rel="noopener noreferrer">' + esc(sprintf(__('%s developer console'), p.label)) + '</a>' : '') + '</div>'
        + '<div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Client ID')) + (idLocked ? '<span class="wb-si-src">' + esc(fromLabels[p.id_from]) + '</span>' : '')
        + '<input type="text" data-p="client_id" autocomplete="off" spellcheck="false" value="' + esc(idLocked ? '' : p.client_id) + '"' + (idLocked ? ' disabled placeholder="' + esc(__('Set outside the database')) + '"' : '') + '></label>'
        + '<div><label class="wb-field" style="margin-bottom:6px">' + esc(__('Client secret')) + (secretLocked ? '<span class="wb-si-src">' + esc(fromLabels[p.secret_from]) + '</span>' : '')
        + '<input type="password" data-p="client_secret" autocomplete="new-password" spellcheck="false" placeholder="' + esc(secretPh) + '"' + (secretLocked ? ' disabled' : '') + '></label>'
        + (p.has_secret && !secretLocked ? '<label class="wb-field wb-field--check"><input type="checkbox" data-p="clear_secret"> <span>' + esc(__('Remove the saved secret')) + '</span></label>' : '')
        + '</div></div>'
        + '<p class="wb-help" style="margin-bottom:0">' + esc(sprintf(__('Scope requested: %s'), p.scope)) + '</p>'
        + '</div></div>';
    }).join('');
  }

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-si="enabled"]')) {
      renderStatus();
      save(true);
    }
    if (t.matches('[data-p="enabled"]')) {
      t.closest('[data-prov]').classList.toggle('is-off', !t.checked);
    }
  });

  form.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]');
    if (!c) return;
    var text = c.getAttribute('data-copy');
    var done = function () { A.toast(__('Copied. Paste it into the provider exactly as it is.')); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () { selectCode(c); });
    } else {
      selectCode(c);
    }
  });

  function selectCode(btn) {
    var code = btn.parentNode.querySelector('code');
    var r = document.createRange();
    r.selectNodeContents(code);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(r);
    A.toast(__('Selected. Press Ctrl+C or Cmd+C to copy.'));
  }

  function collect() {
    var out = {};
    qa('[data-si]').forEach(function (el) {
      var k = el.getAttribute('data-si');
      out[k] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
    });
    out.twofactor_roles = qa('[data-si-role]:checked').map(function (i) { return i.value; });
    qa('[data-prov]').forEach(function (card) {
      var k = card.getAttribute('data-prov');
      var get = function (n) { return card.querySelector('[data-p="' + n + '"]'); };
      out[k + '_enabled'] = get('enabled').checked ? 1 : 0;
      if (!get('client_id').disabled) out[k + '_client_id'] = get('client_id').value.trim();
      if (!get('client_secret').disabled && get('client_secret').value.trim() !== '') out[k + '_client_secret'] = get('client_secret').value.trim();
      if (get('clear_secret') && get('clear_secret').checked) out[k + '_clear_secret'] = 1;
    });
    return out;
  }

  function save(quiet) {
    var btn = q('button[type="submit"]');
    btn.disabled = true;
    return A.post('/signin', collect()).then(function (d) {
      D = d;
      render();
      A.toast(quiet ? (D.settings.enabled ? __('Social sign-in switched on.') : __('Social sign-in switched off.')) : __('Sign-in settings saved.'));
    }).catch(function (e) { A.toast(e.message, true); })
      .then(function () { btn.disabled = false; });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    save(false);
  });
})();
