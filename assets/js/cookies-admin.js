/* Sepo Bridge — Cookies tab. */
(function () {
  'use strict';
  var A = window.SepoWBAdmin;
  if (!A) return;
  var __ = A.__, sprintf = A.sprintf, esc = A.esc;
  var P = A.panel('cookies');
  if (!P) return;
  var box = P.querySelector('[data-wb-cc]');
  var D = null;          // data from the server
  var view = 'cookies';
  var CATS = ['necessary', 'preferences', 'analytics', 'marketing'];
  var ORDER = ['unclassified'].concat(CATS);

  function q(sel) { return box.querySelector(sel); }
  function qa(sel) { return [].slice.call(box.querySelectorAll(sel)); }

  function catLabel(id) {
    if (id === 'unclassified') return __('Unclassified');
    return (D.settings.categories[id] || {}).label || id;
  }

  // ------------------------------------------------------------ load

  A.addLoader('cookies', load);

  function load() {
    A.setState('cookies', 'loading');
    A.get('/cookies').then(function (d) {
      D = d;
      A.setState('cookies', 'ready');
      box.hidden = false;
      render();
    }).catch(function (e) { A.toast(e.message, true); });
  }

  function render() {
    renderStatus();
    renderCookies();
    fillForm();
    renderRules();
    renderServices();
    renderStats();
    q('[data-cc-preview]').href = D.previewUrl;
    q('[data-cc-csv]').href = D.csvUrl;
  }

  function renderStatus() {
    var s = D.settings;
    var on = !!s.enabled;
    q('[data-cc-enabled]').checked = on;
    q('[data-cc-status-title]').textContent = on ? __('The cookie banner is on') : __('The cookie banner is off');
    var n = D.cookies.length;
    var un = D.cookies.filter(function (c) { return c.category === 'unclassified'; }).length;
    var parts = [];
    parts.push(s.last_scan ? sprintf(__('Last scan %1$s, %2$d pages.'), s.last_scan.slice(0, 16), s.scan_pages) : __('The site has not been scanned yet.'));
    parts.push(sprintf(n === 1 ? __('%d cookie listed.') : __('%d cookies listed.'), n));
    if (un) parts.push(sprintf(un === 1 ? __('%d needs a category.') : __('%d need a category.'), un));
    q('[data-cc-status-sub]').textContent = parts.join(' ');
    box.classList.toggle('is-off', !on);
  }

  q('[data-cc-enabled]').addEventListener('change', function (e) {
    var on = e.target.checked;
    if (on && !D.cookies.length && !D.settings.last_scan) {
      A.toast(__('Tip: scan the site so visitors can see which cookies they are agreeing to.'));
    }
    A.post('/cookies/save', { enabled: on ? 1 : 0 }).then(function (d) {
      D.settings = d.settings;
      renderStatus();
      A.toast(on ? __('Cookie banner switched on.') : __('Cookie banner switched off.'));
    }).catch(function (err) { e.target.checked = !on; A.toast(err.message, true); });
  });

  // ------------------------------------------------------------ sub views

  box.addEventListener('click', function (e) {
    var t = e.target.closest('[data-cc-view]');
    if (t) show(t.getAttribute('data-cc-view'));
    var c = e.target.closest('[data-cc-copy]');
    if (c) copy(c.getAttribute('data-cc-copy'), c);
  });

  function show(v) {
    view = v;
    qa('[data-cc-view]').forEach(function (b) {
      var on = b.getAttribute('data-cc-view') === v;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    qa('[data-cc-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-cc-pane') !== v; });
    q('[data-cc-save-bar]').hidden = ['banner', 'blocking', 'records'].indexOf(v) < 0;
    if (v === 'records') refreshStats();
  }

  function copy(text, btn) {
    var done = function () {
      var old = btn.textContent;
      btn.textContent = __('Copied');
      setTimeout(function () { btn.textContent = old; }, 1400);
    };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(done, function () { A.toast(text); });
    } else {
      A.toast(text);
    }
  }

  // ------------------------------------------------------------ cookie list

  q('[data-cc-filter]').addEventListener('input', renderCookies);

  function renderCookies() {
    var needle = (q('[data-cc-filter]').value || '').trim().toLowerCase();
    var groups = {};
    ORDER.forEach(function (k) { groups[k] = []; });
    D.cookies.forEach(function (c, i) {
      var hay = (c.name + ' ' + c.provider + ' ' + c.purpose).toLowerCase();
      if (needle && hay.indexOf(needle) < 0) return;
      (groups[c.category] || groups.unclassified).push({ c: c, i: i });
    });
    var html = '';
    if (!D.cookies.length) {
      html = '<div class="wb-cc-empty"><strong>' + esc(__('No cookies listed yet')) + '</strong><p>' + esc(__('Scan the site to find the cookies it sets, or add them yourself. The scan loads a sample of your pages in the background and reads the cookies they set, including ones from Google, Facebook, YouTube and other services the plugin recognises.')) + '</p></div>';
    }
    ORDER.forEach(function (k) {
      var rows = groups[k];
      if (!rows.length && (k === 'unclassified' || !D.cookies.length)) return;
      html += '<div class="wb-group wb-cc-group' + (k === 'unclassified' ? ' is-attention' : '') + '">'
        + '<div class="wb-group-h"><h2>' + esc(catLabel(k)) + '</h2><span>'
        + esc(k === 'unclassified' ? __('Choose a category for these, or visitors will not see them.') : sprintf(rows.length === 1 ? __('%d cookie') : __('%d cookies'), rows.length))
        + '</span></div>';
      if (!rows.length) {
        html += '<p class="wb-cc-none">' + esc(__('None listed.')) + '</p></div>';
        return;
      }
      html += '<table class="wb-table wb-cc-table"><thead><tr><th>' + esc(__('Cookie')) + '</th><th>' + esc(__('Provider')) + '</th><th>' + esc(__('Purpose')) + '</th><th>' + esc(__('Duration')) + '</th><th></th></tr></thead><tbody>';
      rows.forEach(function (r) {
        var c = r.c;
        html += '<tr>'
          + '<td data-label="' + esc(__('Cookie')) + '"><code>' + esc(c.name) + '</code>'
          + (c.seen ? '<span class="wb-sub-line">' + esc(sprintf(__('Seen %s'), c.seen)) + '</span>' : (c.source === 'manual' ? '<span class="wb-sub-line">' + esc(__('Added by hand')) + '</span>' : '')) + '</td>'
          + '<td data-label="' + esc(__('Provider')) + '">' + esc(c.provider || '—') + (c.domain ? '<span class="wb-sub-line">' + esc(c.domain) + '</span>' : '') + '</td>'
          + '<td data-label="' + esc(__('Purpose')) + '">' + (c.purpose ? esc(c.purpose) : '<span class="wb-cc-missing">' + esc(__('No description yet')) + '</span>') + '</td>'
          + '<td data-label="' + esc(__('Duration')) + '">' + esc(c.duration || '—') + '</td>'
          + '<td class="wb-actions-cell">'
          + (k === 'unclassified' ? quickCats(r.i) : '')
          + '<button type="button" class="wb-btn wb-btn--sm" data-cc-edit="' + r.i + '">' + esc(__('Edit')) + '</button> '
          + '<button type="button" class="wb-btn wb-btn--sm wb-btn--link" data-cc-del="' + r.i + '">' + esc(__('Remove')) + '</button></td>'
          + '</tr>';
      });
      html += '</tbody></table></div>';
    });
    q('[data-cc-groups]').innerHTML = html;
  }

  function quickCats(i) {
    return '<select class="wb-cc-quick" data-cc-quick="' + i + '" aria-label="' + esc(__('Category')) + '"><option value="">' + esc(__('Category…')) + '</option>'
      + CATS.map(function (k) { return '<option value="' + k + '">' + esc(catLabel(k)) + '</option>'; }).join('') + '</select> ';
  }

  q('[data-cc-groups]').addEventListener('change', function (e) {
    var s = e.target.closest('[data-cc-quick]');
    if (!s || !s.value) return;
    var list = D.cookies.slice();
    list[+s.getAttribute('data-cc-quick')] = Object.assign({}, list[+s.getAttribute('data-cc-quick')], { category: s.value });
    saveList(list, __('Category saved.'));
  });

  q('[data-cc-groups]').addEventListener('click', function (e) {
    var ed = e.target.closest('[data-cc-edit]');
    if (ed) editCookie(+ed.getAttribute('data-cc-edit'));
    var del = e.target.closest('[data-cc-del]');
    if (del) {
      var i = +del.getAttribute('data-cc-del');
      A.confirmModal(__('Remove cookie'), sprintf(__('Remove %s from the list? A new scan adds it back if the site still sets it.'), D.cookies[i].name), __('Remove')).then(function (r) {
        if (!r.ok) return;
        var list = D.cookies.slice();
        list.splice(i, 1);
        saveList(list, __('Cookie removed.'));
      });
    }
  });
  q('[data-cc-add]').addEventListener('click', function () { editCookie(-1); });

  function editCookie(i) {
    var c = i >= 0 ? D.cookies[i] : { name: '', category: 'unclassified', provider: '', purpose: '', duration: '', domain: '', source: 'manual', seen: '' };
    A.modal({
      title: i >= 0 ? __('Edit cookie') : __('Add cookie'),
      okLabel: __('Save'),
      body: '<div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Name')) + '<input type="text" data-f="name" value="' + esc(c.name) + '" placeholder="_example_id"><small>' + esc(__('A * matches any ending, as in _ga_*.')) + '</small></label>'
        + '<label class="wb-field">' + esc(__('Category')) + '<select data-f="category">' + ORDER.map(function (k) {
          return '<option value="' + k + '"' + (k === c.category ? ' selected' : '') + '>' + esc(catLabel(k)) + '</option>';
        }).join('') + '</select></label>'
        + '<label class="wb-field">' + esc(__('Provider')) + '<input type="text" data-f="provider" value="' + esc(c.provider) + '" placeholder="' + esc(__('This site')) + '"></label>'
        + '<label class="wb-field">' + esc(__('Duration')) + '<input type="text" data-f="duration" value="' + esc(c.duration) + '" placeholder="' + esc(__('1 year')) + '"></label>'
        + '</div>'
        + '<label class="wb-field">' + esc(__('Purpose')) + '<textarea rows="2" data-f="purpose">' + esc(c.purpose) + '</textarea><small>' + esc(__('One plain sentence: what it does for the visitor or the site.')) + '</small></label>'
        + '<label class="wb-field">' + esc(__('Set on another domain')) + '<input type="text" data-f="domain" value="' + esc(c.domain) + '" placeholder="youtube.com"><small>' + esc(__('Leave empty for cookies set on this site.')) + '</small></label>',
      onOk: function (card) {
        var v = function (f) { return card.querySelector('[data-f="' + f + '"]').value.trim(); };
        if (!v('name')) throw new Error(__('Enter the cookie name.'));
        var row = Object.assign({}, c, { name: v('name'), category: v('category'), provider: v('provider'), duration: v('duration'), purpose: v('purpose'), domain: v('domain') });
        var list = D.cookies.slice();
        if (i >= 0) list[i] = row; else list.push(Object.assign(row, { source: 'manual' }));
        return saveList(list, __('Cookie saved.'));
      }
    });
  }

  function saveList(list, msg) {
    return A.post('/cookies/list', { cookies: list }).then(function (d) {
      D.cookies = d.cookies;
      renderCookies();
      renderStatus();
      A.toast(msg);
    }).catch(function (e) { A.toast(e.message, true); throw e; });
  }

  // ------------------------------------------------------------ scan

  var scanning = false;
  q('[data-cc-scan]').addEventListener('click', function () { if (!scanning) scan(); });

  function scan() {
    scanning = true;
    var btn = q('[data-cc-scan]');
    btn.disabled = true;
    var sb = q('[data-cc-scan-box]');
    sb.hidden = false;
    setProgress(0, __('Finding pages to scan…'), '');
    var names = {}, hits = {}, hosts = {};
    var done = 0;
    A.get('/cookies/scan-urls').then(function (d) {
      var urls = d.urls || [];
      var i = 0;
      return new Promise(function (resolve) {
        var active = 0;
        function next() {
          if (i >= urls.length && active === 0) { resolve(urls.length); return; }
          while (active < 2 && i < urls.length) {
            active++;
            var url = urls[i++];
            q('[data-cc-scan-page]').textContent = url.replace(/[?&]sepo_wb_scan=[^&]+/, '');
            loadPage(url).then(function (r) {
              r.cookies.forEach(function (n) { names[n] = 1; });
              Object.keys(r.hits).forEach(function (k) { hits[k] = r.hits[k]; });
              r.hosts.forEach(function (h) { hosts[h] = 1; });
            }).then(function () {
              active--;
              done++;
              setProgress(Math.round(done / urls.length * 100), __('Scanning…'), sprintf(__('%1$d of %2$d pages'), done, urls.length));
              next();
            });
          }
        }
        next();
      });
    }).then(function (pages) {
      setProgress(100, __('Saving what was found…'), '');
      return A.post('/cookies/scan-results', { cookies: Object.keys(names), hits: hits, hosts: Object.keys(hosts), pages: pages });
    }).then(function (r) {
      D.cookies = r.cookies;
      D.settings.last_scan = r.lastScan;
      D.settings.scan_pages = r.pages;
      D.settings.services = Object.keys(hits);
      sb.hidden = true;
      renderStatus();
      renderCookies();
      renderServices();
      showFound(r);
      show('cookies');
    }).catch(function (e) {
      sb.hidden = true;
      A.toast(e.message, true);
    }).then(function () {
      scanning = false;
      btn.disabled = false;
      q('[data-cc-frames]').innerHTML = '';
    });
  }

  function setProgress(pct, label, count) {
    var bar = q('[data-cc-progress]');
    bar.setAttribute('aria-valuenow', String(pct));
    bar.firstElementChild.style.width = pct + '%';
    q('[data-cc-scan-label]').textContent = label;
    q('[data-cc-scan-count]').textContent = count;
  }

  /** Load one page in a hidden frame, let its scripts run, then read what it set. */
  function loadPage(url) {
    return new Promise(function (resolve) {
      var f = document.createElement('iframe');
      var finished = false;
      var out = { cookies: [], hits: {}, hosts: [] };
      function finish() {
        if (finished) return;
        finished = true;
        try {
          var doc = f.contentDocument;
          if (doc) {
            out.cookies = doc.cookie.split(/;\s*/).map(function (p) { return p.split('=')[0]; }).filter(Boolean);
            var j = doc.getElementById('sepo-wb-scan-hits');
            if (j) {
              var data = JSON.parse(j.textContent || '{}');
              out.hits = data.hits || {};
              out.hosts = data.hosts || [];
            }
          }
        } catch (e) { /* a page that redirects to another site cannot be read */ }
        f.remove();
        resolve(out);
      }
      f.addEventListener('load', function () { setTimeout(finish, 3000); });
      setTimeout(finish, 20000);
      f.setAttribute('tabindex', '-1');
      f.setAttribute('title', 'scan');
      f.src = url;
      q('[data-cc-frames]').appendChild(f);
    });
  }

  function showFound(r) {
    var el = q('[data-cc-found]');
    var html = '<div class="wb-cc-found__head"><strong>' + esc(r.added ? sprintf(r.added === 1 ? __('Scan complete: %d new cookie found.') : __('Scan complete: %d new cookies found.'), r.added) : __('Scan complete: no new cookies.')) + '</strong>'
      + '<button type="button" class="wb-btn wb-btn--sm wb-btn--link" data-cc-found-close>' + esc(__('Dismiss')) + '</button></div>';
    if (r.services && r.services.length) {
      html += '<p>' + esc(__('Services found on the site:')) + ' ' + r.services.map(function (s) { return '<span class="wb-tag wb-tag--on">' + esc(s) + '</span>'; }).join(' ') + '</p>';
    }
    if (r.hosts && r.hosts.length) {
      html += '<p>' + esc(__('Pages also load scripts or frames from these sites. If one of them tracks visitors, add a blocking rule for it:')) + '</p><div class="wb-cc-hosts">'
        + r.hosts.map(function (h) { return '<button type="button" class="wb-btn wb-btn--sm" data-cc-host="' + esc(h) + '">' + esc(h) + ' <span aria-hidden="true">+</span></button>'; }).join('') + '</div>';
    }
    el.innerHTML = html;
    el.hidden = false;
  }
  q('[data-cc-found]').addEventListener('click', function (e) {
    if (e.target.closest('[data-cc-found-close]')) q('[data-cc-found]').hidden = true;
    var h = e.target.closest('[data-cc-host]');
    if (h) {
      show('blocking');
      addRule({ pattern: h.getAttribute('data-cc-host'), name: h.getAttribute('data-cc-host'), category: 'marketing' }, true);
      A.toast(__('Rule added. Choose its category, then save.'));
    }
  });

  // ------------------------------------------------------------ settings form

  var form = q('[data-cc-form]');

  function fillForm() {
    var s = D.settings;
    qa('[data-cc]').forEach(function (i) {
      var k = i.getAttribute('data-cc');
      if (i.type === 'checkbox') i.checked = !!s[k];
      else if (k !== 'policy_page') i.value = s[k] == null ? '' : s[k];
    });
    qa('[name="cc-layout"]').forEach(function (r) { r.checked = r.value === s.layout; });
    var sel = q('[data-cc="policy_page"]');
    sel.innerHTML = '<option value="0">' + esc(D.privacyPage ? __('The privacy policy page') : __('None')) + '</option>'
      + D.pages.map(function (p) { return '<option value="' + p.value + '"' + (p.value === +s.policy_page ? ' selected' : '') + '>' + esc(p.label) + '</option>'; }).join('');
    var themed = !s.accent;
    q('[data-cc-accent-theme]').checked = themed;
    q('[data-cc-accent-picker]').value = s.accent || '#0b3d33';
    q('[data-cc-accent-picker]').disabled = themed;
    qa('[data-cc-text]').forEach(function (i) {
      var k = i.getAttribute('data-cc-text');
      var def = D.defaults.texts[k] || '';
      i.placeholder = def;
      i.value = s.texts[k] && s.texts[k] !== def ? s.texts[k] : '';
    });
    q('[data-cc-cats]').innerHTML = CATS.map(function (k) {
      var c = s.categories[k];
      var d = D.defaults.categories[k];
      return '<div class="wb-cc-catedit" data-cat="' + k + '"><div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Name')) + (k === 'necessary' ? ' <span class="wb-tag">' + esc(__('Always on')) + '</span>' : '')
        + '<input type="text" data-c="label" placeholder="' + esc(d.label) + '" value="' + esc(c.label !== d.label ? c.label : '') + '"></label>'
        + '<label class="wb-field">' + esc(__('Description')) + '<textarea rows="2" data-c="description" placeholder="' + esc(d.description) + '">' + esc(c.description !== d.description ? c.description : '') + '</textarea></label>'
        + '</div></div>';
    }).join('');
    q('[data-cc-bump]').textContent = __('Ask everyone again');
    q('[data-cc-version]').textContent = sprintf(__('Banner version %d'), s.version);
  }

  q('[data-cc-accent-theme]').addEventListener('change', function (e) { q('[data-cc-accent-picker]').disabled = e.target.checked; });

  function readForm() {
    var out = { texts: {}, categories: {}, rules: readRules() };
    qa('[data-cc]').forEach(function (i) {
      var k = i.getAttribute('data-cc');
      out[k] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value;
    });
    var lay = q('[name="cc-layout"]:checked');
    out.layout = lay ? lay.value : 'card';
    out.accent = q('[data-cc-accent-theme]').checked ? '' : q('[data-cc-accent-picker]').value;
    qa('[data-cc-text]').forEach(function (i) { out.texts[i.getAttribute('data-cc-text')] = i.value.trim(); });
    qa('[data-cc-cats] [data-cat]').forEach(function (row) {
      out.categories[row.getAttribute('data-cat')] = {
        label: row.querySelector('[data-c="label"]').value.trim(),
        description: row.querySelector('[data-c="description"]').value.trim()
      };
    });
    return out;
  }

  q('[data-cc-save]').addEventListener('click', save);
  form.addEventListener('submit', function (e) { e.preventDefault(); save(); });

  function save() {
    var btn = q('[data-cc-save]');
    btn.disabled = true;
    A.post('/cookies/save', readForm()).then(function (d) {
      D.settings = d.settings;
      fillForm();
      renderRules();
      renderCookies();
      renderStatus();
      A.toast(__('Cookie banner settings saved.'));
    }).catch(function (e) { A.toast(e.message, true); }).then(function () { btn.disabled = false; });
  }

  // ------------------------------------------------------------ rules

  function ruleRow(r) {
    return '<tr data-rule>'
      + '<td data-label="' + esc(__('Address or code contains')) + '"><input type="text" data-r="pattern" value="' + esc(r.pattern) + '" placeholder="widget.example.com" aria-label="' + esc(__('Address or code contains')) + '"></td>'
      + '<td data-label="' + esc(__('Service name')) + '"><input type="text" data-r="name" value="' + esc(r.name) + '" placeholder="Example widget" aria-label="' + esc(__('Service name')) + '"></td>'
      + '<td data-label="' + esc(__('Category')) + '"><select data-r="category" aria-label="' + esc(__('Category')) + '">' + ['preferences', 'analytics', 'marketing'].map(function (k) {
        return '<option value="' + k + '"' + (k === r.category ? ' selected' : '') + '>' + esc(catLabel(k)) + '</option>';
      }).join('') + '</select></td>'
      + '<td class="wb-actions-cell"><button type="button" class="wb-btn wb-btn--sm wb-btn--link" data-rule-remove>' + esc(__('Remove')) + '</button></td></tr>';
  }

  function renderRules() {
    var rows = D.settings.rules || [];
    q('[data-cc-rules]').innerHTML = rows.length ? rows.map(ruleRow).join('') : emptyRule();
  }
  function emptyRule() {
    return '<tr data-rule-empty><td colspan="4" class="wb-cc-none">' + esc(__('No rules of your own.')) + '</td></tr>';
  }
  function addRule(r, focus) {
    var tb = q('[data-cc-rules]');
    var empty = tb.querySelector('[data-rule-empty]');
    if (empty) empty.remove();
    tb.insertAdjacentHTML('beforeend', ruleRow(r));
    if (focus) tb.querySelector('tr:last-child [data-r="category"]').focus();
  }
  function readRules() {
    return qa('[data-rule]').map(function (tr) {
      return { pattern: tr.querySelector('[data-r="pattern"]').value.trim(), name: tr.querySelector('[data-r="name"]').value.trim(), category: tr.querySelector('[data-r="category"]').value };
    }).filter(function (r) { return r.pattern; });
  }
  q('[data-cc-rule-add]').addEventListener('click', function () {
    addRule({ pattern: '', name: '', category: 'marketing' });
    q('[data-cc-rules] tr:last-child [data-r="pattern"]').focus();
  });
  q('[data-cc-rules]').addEventListener('click', function (e) {
    var rm = e.target.closest('[data-rule-remove]');
    if (rm) {
      rm.closest('[data-rule]').remove();
      if (!q('[data-rule]')) q('[data-cc-rules]').innerHTML = emptyRule();
    }
  });

  function renderServices() {
    var found = D.settings.services || [];
    var byCat = {};
    Object.keys(D.services).forEach(function (id) {
      var s = D.services[id];
      (byCat[s.category] = byCat[s.category] || []).push({ id: id, name: s.name });
    });
    q('[data-cc-services]').innerHTML = CATS.filter(function (k) { return byCat[k]; }).map(function (k) {
      return '<div class="wb-cc-svc"><h3>' + esc(catLabel(k)) + '</h3><div>' + byCat[k].map(function (s) {
        var on = found.indexOf(s.id) > -1;
        return '<span class="wb-tag' + (on ? ' wb-tag--on' : '') + '">' + esc(s.name) + (on ? ' · ' + esc(__('on this site')) : '') + '</span>';
      }).join(' ') + '</div></div>';
    }).join('');
  }

  // ------------------------------------------------------------ records

  var ACTIONS = { accept_all: __('Accepted all'), reject_all: __('Rejected all'), custom: __('Chose some'), revoke: __('Withdrew') };

  function refreshStats() {
    A.get('/cookies/log').then(function (d) { D.stats = d.stats; renderStats(); }).catch(function () {});
  }

  function renderStats() {
    var st = D.stats;
    q('[data-cc-stats]').innerHTML = Object.keys(ACTIONS).map(function (k) {
      var n = st.counts[k] || 0;
      var pct = st.total ? Math.round(n / st.total * 100) : 0;
      return '<div class="wb-cc-stat wb-cc-stat--' + k + '"><span>' + esc(ACTIONS[k]) + '</span><strong>' + n + '</strong><em>' + (st.total ? pct + '%' : '—') + '</em></div>';
    }).join('');
    var max = Math.max.apply(null, CATS.map(function (k) { return st.byCategory[k] || 0; }).concat([1]));
    q('[data-cc-bars]').innerHTML = '<h3>' + esc(__('Allowed, by category')) + '</h3>' + CATS.map(function (k) {
      var n = st.byCategory[k] || 0;
      return '<div class="wb-cc-bar"><span>' + esc(catLabel(k)) + '</span><div><i style="width:' + Math.round(n / max * 100) + '%"></i></div><b>' + n + '</b></div>';
    }).join('');
    q('[data-cc-recent]').innerHTML = st.recent.length
      ? '<table class="wb-table"><thead><tr><th>' + esc(__('When')) + '</th><th>' + esc(__('Choice')) + '</th><th>' + esc(__('Allowed')) + '</th><th>' + esc(__('Consent ID')) + '</th><th>' + esc(__('IP (shortened)')) + '</th></tr></thead><tbody>'
        + st.recent.map(function (r) {
          return '<tr><td data-label="' + esc(__('When')) + '">' + esc(r.created) + '</td><td data-label="' + esc(__('Choice')) + '">' + esc(ACTIONS[r.action] || r.action) + '</td>'
            + '<td data-label="' + esc(__('Allowed')) + '">' + esc(String(r.categories).split(',').map(catLabel).join(', ')) + '</td>'
            + '<td data-label="' + esc(__('Consent ID')) + '"><code>' + esc(String(r.consent_id).slice(0, 13)) + '…</code></td><td data-label="' + esc(__('IP (shortened)')) + '">' + esc(r.ip || '—') + '</td></tr>';
        }).join('') + '</tbody></table>'
      : '<p class="wb-cc-none">' + esc(__('No choices recorded yet.')) + '</p>';
  }

  q('[data-cc-bump]').addEventListener('click', function () {
    A.confirmModal(__('Ask everyone again'), __('Every visitor will see the banner again on their next visit, and their earlier choice stops applying until they choose. Records already kept stay as they are.'), __('Ask again')).then(function (r) {
      if (!r.ok) return;
      A.post('/cookies/version').then(function (d) {
        D.settings.version = d.version;
        q('[data-cc-version]').textContent = sprintf(__('Banner version %d'), d.version);
        A.toast(__('Visitors will be asked again.'));
      }).catch(function (e) { A.toast(e.message, true); });
    });
  });
})();
