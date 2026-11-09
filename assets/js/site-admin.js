/* Sepo Bridge — busy buttons, in-page confirmations, switches that save, the media picker and the permissions filter. */
(function () {
  'use strict';
  var __ = (window.wp && wp.i18n && wp.i18n.__) || function (s) { return s; };

  function busy(btn) {
    if (!btn || btn.getAttribute('aria-busy') === 'true') return;
    btn.setAttribute('aria-busy', 'true');
    var label = btn.getAttribute('data-busy-label');
    if (label) { btn.setAttribute('data-label', btn.textContent); btn.textContent = label; }
    setTimeout(function () { btn.disabled = true; }, 0);
  }

  // A dialog drawn in the page, never the browser's own.
  function ask(message, okLabel) {
    return new Promise(function (resolve) {
      var last = document.activeElement;
      var wrap = document.createElement('div');
      wrap.className = 'wb-modal';
      wrap.innerHTML = '<div class="wb-modal-card wb-modal-card--sm" role="alertdialog" aria-modal="true" aria-labelledby="wb-ask-t" aria-describedby="wb-ask-m">'
        + '<h2 id="wb-ask-t">' + __('Are you sure?', 'sepo-bridge') + '</h2><p id="wb-ask-m"></p>'
        + '<div class="wb-modal-actions"><button type="button" class="wb-btn" data-no>' + __('Cancel', 'sepo-bridge') + '</button> <button type="button" class="wb-btn wb-btn--primary wb-btn--danger-fill" data-yes></button></div></div>';
      wrap.querySelector('#wb-ask-m').textContent = message;
      wrap.querySelector('[data-yes]').textContent = okLabel || __('Continue', 'sepo-bridge');
      document.body.appendChild(wrap);
      var yes = wrap.querySelector('[data-yes]'), no = wrap.querySelector('[data-no]');
      function done(v) { document.removeEventListener('keydown', key); wrap.remove(); if (last && last.focus) last.focus(); resolve(v); }
      function key(e) { if (e.key === 'Escape') done(false); if (e.key === 'Tab') { e.preventDefault(); (document.activeElement === yes ? no : yes).focus(); } }
      document.addEventListener('keydown', key);
      yes.addEventListener('click', function () { done(true); });
      no.addEventListener('click', function () { done(false); });
      wrap.addEventListener('click', function (e) { if (e.target === wrap) done(false); });
      no.focus();
    });
  }

  document.addEventListener('click', function (ev) {
    var c = ev.target.closest('[data-wb-confirm]');
    if (c && !c.hasAttribute('data-confirmed')) {
      ev.preventDefault();
      ask(c.getAttribute('data-wb-confirm'), c.getAttribute('data-wb-confirm-ok')).then(function (ok) {
        if (!ok) return;
        c.setAttribute('data-confirmed', '1');
        var f = c.form;
        busy(c);
        if (f) { if (f.requestSubmit) f.requestSubmit(); else f.submit(); }
      });
      return;
    }
    var all = ev.target.closest('[data-wb-cap-all], [data-wb-cap-none]');
    if (all) {
      var on = all.hasAttribute('data-wb-cap-all');
      all.closest('[data-wb-capgroup]').querySelectorAll('input[type=checkbox]:not(:disabled)').forEach(function (i) {
        if (!i.closest('[hidden]')) i.checked = on;
      });
    }
  });

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.matches || !f.matches('[data-wb-busy], [data-wb-autosubmit], .wb-site-danger')) return;
    busy(ev.submitter || f.querySelector('button[type=submit], button:not([type])'));
  });

  // Switches that save as soon as they change.
  document.addEventListener('change', function (ev) {
    var f = ev.target.closest('form[data-wb-autosubmit]');
    if (!f || ev.target.type !== 'checkbox') return;
    var sw = ev.target.closest('.wb-switch');
    if (sw) sw.setAttribute('aria-busy', 'true');
    ev.target.disabled = false;
    if (f.requestSubmit) f.requestSubmit(); else f.submit();
  });

  // Permission search.
  document.addEventListener('input', function (ev) {
    var q = ev.target.closest('[data-wb-cap-filter]');
    if (!q) return;
    var needle = q.value.trim().toLowerCase();
    document.querySelectorAll('.wb-site-cap').forEach(function (row) {
      row.hidden = needle !== '' && (row.getAttribute('data-search') || '').indexOf(needle) === -1;
    });
    document.querySelectorAll('[data-wb-capgroup]').forEach(function (g) {
      g.hidden = !g.querySelector('.wb-site-cap:not([hidden])');
    });
  });

  // Media picker for the default social image.
  document.querySelectorAll('[data-wb-media]').forEach(function (box) {
    var id = box.querySelector('[data-wb-media-id]'), img = box.querySelector('[data-wb-media-preview]');
    var pick = box.querySelector('[data-wb-media-pick]'), clear = box.querySelector('[data-wb-media-clear]');
    var frame;
    pick.addEventListener('click', function () {
      if (!window.wp || !wp.media) return;
      frame = frame || wp.media({ title: __('Social image', 'sepo-bridge'), library: { type: 'image' }, multiple: false, button: { text: __('Use this image', 'sepo-bridge') } });
      frame.off('select').on('select', function () {
        var a = frame.state().get('selection').first().toJSON();
        id.value = a.id;
        img.src = (a.sizes && (a.sizes.medium || a.sizes.full) || a).url;
        img.hidden = false; clear.hidden = false;
      });
      frame.open();
    });
    clear.addEventListener('click', function () { id.value = ''; img.hidden = true; img.removeAttribute('src'); clear.hidden = true; pick.focus(); });
  });
})();
