/* PawCare — shared UI helpers: toasts, modals, confirm dialogs, dropdowns,
   tabs, password toggles, photo previews, reveal-on-scroll and confetti. */
(function () {
  'use strict';
  var PC = (window.PC = window.PC || {});
  PC.icons = PC.icons || {};
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  PC.escape = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  PC.money = function (n) {
    return (PC.currency || '₱') + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
  };
  PC.moneyShort = function (n) {
    n = Number(n || 0);
    return (PC.currency || '₱') + (Math.abs(n) >= 1000 ? (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + 'k' : n);
  };

  /* ---------- fetch helper (JSON + CSRF) ---------- */
  PC.api = function (path, opts) {
    opts = opts || {};
    var body = opts.body;
    if (body && !(body instanceof FormData) && typeof body === 'object') body = new URLSearchParams(body);
    return fetch((PC.base || '') + '/' + path.replace(/^\//, ''), {
      method: opts.method || 'GET',
      headers: { Accept: 'application/json', 'X-CSRF-Token': PC.csrf || '' },
      body: body,
      credentials: 'same-origin',
      keepalive: !!opts.keepalive
    }).then(function (res) {
      return res.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; });
    }).catch(function () {
      return { ok: false, error: 'Network error. Please check your connection.' };
    });
  };

  /* ---------- Toasts ---------- */
  function stack() {
    var s = document.querySelector('[data-toast-stack]');
    if (!s) {
      s = document.createElement('div');
      s.className = 'toast-stack';
      s.setAttribute('data-toast-stack', '');
      document.body.appendChild(s);
    }
    return s;
  }
  function dismiss(t) {
    if (!t || t.classList.contains('is-leaving')) return;
    t.classList.add('is-leaving');
    setTimeout(function () { t.remove(); }, 350);
  }
  function arm(t) {
    var timer = setTimeout(function () { dismiss(t); }, 5500);
    t.addEventListener('mouseenter', function () { clearTimeout(timer); });
    t.addEventListener('mouseleave', function () { timer = setTimeout(function () { dismiss(t); }, 2500); });
  }
  PC.toast = function (message, type) {
    type = type || 'success';
    var map = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
    var t = document.createElement('div');
    t.className = 'toast toast-' + (map[type] || 'info');
    t.setAttribute('data-toast', '');
    t.innerHTML = '<span class="toast-icon">' + (PC.icons[type] || '') + '</span><p></p>' +
      '<button type="button" class="toast-close" data-toast-close aria-label="Dismiss">' + (PC.icons.x || '×') + '</button>';
    t.querySelector('p').textContent = message;
    stack().appendChild(t);
    arm(t);
  };
  document.querySelectorAll('[data-toast]').forEach(arm);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toast-close]');
    if (b) dismiss(b.closest('[data-toast]'));
  });

  /* ---------- Modals (native <dialog>) ---------- */
  function fillModal(dialog, opener) {
    if (!opener.dataset.fill) return;
    var data;
    try { data = JSON.parse(opener.dataset.fill); } catch (err) { return; }
    Object.keys(data).forEach(function (key) {
      var value = data[key];
      dialog.querySelectorAll('[name="' + key + '"]').forEach(function (el) {
        if (el.type === 'checkbox') {
          el.checked = Array.isArray(value) ? value.map(String).indexOf(el.value) !== -1 : !!Number(value);
        } else if (el.type === 'radio') {
          el.checked = String(el.value) === String(value);
        } else if (el.type !== 'file') {
          el.value = value == null ? '' : value;
        }
      });
      dialog.querySelectorAll('[data-fill-text="' + key + '"]').forEach(function (el) { el.textContent = value == null ? '' : value; });
      dialog.querySelectorAll('[data-fill-src="' + key + '"]').forEach(function (el) {
        if (value) { el.innerHTML = '<img alt="">'; el.querySelector('img').src = value; }
        else { el.innerHTML = PC.icons.image || ''; }
      });
    });
  }
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      var d = document.getElementById(opener.dataset.modalOpen);
      if (d && d.showModal) {
        e.preventDefault();
        fillModal(d, opener);
        d.showModal();
        var first = d.querySelector('[autofocus], input:not([type=hidden]):not([disabled]), textarea, select');
        if (first) setTimeout(function () { first.focus(); }, 50);
      }
    }
    var closer = e.target.closest('[data-modal-close]');
    if (closer) {
      var dlg = closer.closest('dialog');
      if (dlg) dlg.close();
    }
  });
  document.addEventListener('click', function (e) {
    if (e.target.tagName === 'DIALOG' && e.target.classList.contains('modal')) e.target.close();
  });
  document.querySelectorAll('dialog[data-open-on-load]').forEach(function (d) { if (d.showModal) d.showModal(); });

  /* ---------- Confirm before submitting: <form data-confirm="..."> ---------- */
  var confirmDialog = null;
  PC.confirm = function (message, options) {
    options = options || {};
    return new Promise(function (resolve) {
      if (!confirmDialog) {
        confirmDialog = document.createElement('dialog');
        confirmDialog.className = 'modal modal-sm';
        document.body.appendChild(confirmDialog);
      }
      var tone = options.tone || 'danger';
      confirmDialog.innerHTML =
        '<div class="confirm-body"><div class="confirm-icon tone-' + tone + '">' + (PC.icons[tone === 'danger' ? 'warning' : 'info'] || '') + '</div>' +
        '<h3></h3><p></p></div><div class="modal-foot"><button type="button" class="btn btn-outline" data-v="0">Cancel</button>' +
        '<button type="button" class="btn ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary') + '" data-v="1"></button></div>';
      confirmDialog.querySelector('h3').textContent = options.title || 'Are you sure?';
      confirmDialog.querySelector('p').textContent = message;
      confirmDialog.querySelector('[data-v="1"]').textContent = options.ok || 'Yes, continue';
      var settled = false;
      function finish(v) { if (settled) return; settled = true; if (confirmDialog.open) confirmDialog.close(); resolve(v); }
      confirmDialog.querySelectorAll('[data-v]').forEach(function (b) {
        b.addEventListener('click', function () { finish(b.dataset.v === '1'); });
      });
      confirmDialog.addEventListener('close', function () { finish(false); }, { once: true });
      confirmDialog.showModal();
      confirmDialog.querySelector('[data-v="1"]').focus();
    });
  };
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('[data-confirm]') || form.dataset.confirmed === '1') return;
    e.preventDefault();
    var submitter = e.submitter;
    PC.confirm(form.dataset.confirm, { title: form.dataset.confirmTitle, ok: form.dataset.confirmOk, tone: form.dataset.confirmTone }).then(function (ok) {
      if (!ok) return;
      form.dataset.confirmed = '1';
      if (form.requestSubmit && submitter) form.requestSubmit(submitter);
      else {
        if (submitter && submitter.name) {
          var h = document.createElement('input');
          h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value;
          form.appendChild(h);
        }
        form.submit();
      }
    });
  });
  // show a spinner on submit buttons
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var btn = e.submitter || e.target.querySelector('[type=submit]');
    if (btn && !btn.hasAttribute('data-no-loading')) setTimeout(function () { btn.classList.add('is-loading'); }, 10);
  });

  /* ---------- Dropdowns ---------- */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-dropdown-toggle]');
    var current = toggle ? toggle.closest('[data-dropdown]') : null;
    document.querySelectorAll('[data-dropdown].open').forEach(function (dd) {
      if (dd !== current && !dd.contains(e.target)) dd.classList.remove('open');
    });
    if (current) {
      var open = current.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      current.dispatchEvent(new CustomEvent(open ? 'dropdown:open' : 'dropdown:close'));
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('[data-dropdown].open').forEach(function (dd) { dd.classList.remove('open'); });
  });

  /* ---------- Tabs ---------- */
  document.querySelectorAll('[data-tabs]').forEach(function (tabs) {
    var buttons = Array.prototype.slice.call(tabs.querySelectorAll('[data-tab]'));
    function activate(id, remember) {
      buttons.forEach(function (b) {
        var on = b.dataset.tab === id;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        var panel = document.getElementById(b.dataset.tab);
        if (panel) panel.hidden = !on;
      });
      if (remember && tabs.hasAttribute('data-tabs-hash')) history.replaceState(null, '', '#' + id);
      document.dispatchEvent(new CustomEvent('tab:shown', { detail: id }));
    }
    buttons.forEach(function (b) { b.addEventListener('click', function () { activate(b.dataset.tab, true); }); });
    var hash = location.hash.slice(1);
    if (hash && buttons.some(function (b) { return b.dataset.tab === hash; })) activate(hash, false);
  });

  /* ---------- Password visibility + strength meter ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-password-toggle]');
    if (!b) return;
    var input = b.parentElement.querySelector('input');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.innerHTML = show ? (PC.icons['eye-off'] || 'Hide') : (PC.icons.eye || 'Show');
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
  document.querySelectorAll('[data-password-meter]').forEach(function (input) {
    var meter = document.querySelector(input.dataset.passwordMeter);
    if (!meter) return;
    var label = meter.parentElement.querySelector('[data-pw-label]');
    var words = ['Too short', 'Weak', 'Okay', 'Good', 'Strong'];
    input.addEventListener('input', function () {
      var v = input.value, score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) score++;
      if (v.length < 8) score = Math.min(score, 1);
      meter.dataset.score = v ? Math.max(1, score) : 0;
      if (label) label.textContent = v ? words[score] : 'Use 8+ characters with letters and a number';
    });
  });

  /* ---------- Photo drop zones with live preview ---------- */
  document.querySelectorAll('[data-dropzone]').forEach(function (dz) {
    var input = dz.querySelector('input[type=file]');
    var preview = dz.querySelector('[data-dz-preview]');
    var label = dz.querySelector('[data-dz-label]');
    var maxMb = Number(dz.dataset.maxMb || 4);
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.add('dragover'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.remove('dragover'); }); });
    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) return;
      if (!/^image\//.test(f.type)) { PC.toast('Please choose an image file (JPG, PNG, WEBP or GIF).', 'error'); input.value = ''; return; }
      if (f.size > maxMb * 1048576) { PC.toast('That photo is larger than ' + maxMb + ' MB.', 'error'); input.value = ''; return; }
      preview.innerHTML = '<img alt="Preview">';
      preview.querySelector('img').src = URL.createObjectURL(f);
      if (label) label.textContent = f.name;
    });
  });

  /* ---------- Reveal on scroll + count-up numbers ---------- */
  function countUp(el) {
    var raw = el.dataset.count;
    var target = parseFloat(raw);
    var decimals = (raw.split('.')[1] || '').length;
    var prefix = el.dataset.prefix || '', suffix = el.dataset.suffix || '';
    var fmt = function (n) { return prefix + n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix; };
    if (reduceMotion) { el.textContent = fmt(target); return; }
    var start = performance.now(), dur = 1500;
    (function step(now) {
      var t = Math.min(1, (now - start) / dur);
      el.textContent = fmt(target * (1 - Math.pow(1 - t, 3)));
      if (t < 1) requestAnimationFrame(step);
    })(start);
  }
  var targets = document.querySelectorAll('[data-reveal], [data-count]');
  if ('IntersectionObserver' in window && !reduceMotion) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('in');
        if (en.target.hasAttribute('data-count')) countUp(en.target);
        io.unobserve(en.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
    targets.forEach(function (el) { io.observe(el); });
  } else {
    targets.forEach(function (el) { el.classList.add('in'); if (el.hasAttribute('data-count')) countUp(el); });
  }

  /* ---------- Time slot chips (used by booking + staff forms) ---------- */
  PC.renderSlots = function (container, data, name, selected) {
    var slots = data.slots || [];
    if (!slots.length) {
      container.innerHTML = '<div class="slots-empty">' + (PC.icons['calendar-x'] || '') + PC.escape(data.message || 'No times available on this day.') + '</div>';
      return 0;
    }
    var groups = {}, order = [], available = 0;
    slots.forEach(function (s) {
      if (!groups[s.period]) { groups[s.period] = []; order.push(s.period); }
      groups[s.period].push(s);
      if (s.available) available++;
    });
    var html = '';
    order.forEach(function (period) {
      html += '<div class="slot-group-label">' + (PC.icons[period === 'Morning' ? 'sun' : 'clock'] || '') + period + '</div><div class="slots">';
      groups[period].forEach(function (s) {
        var title = s.available ? (s.free ? s.free + ' vet' + (s.free > 1 ? 's' : '') + ' free' : 'Available') : 'Not available';
        html += '<label class="slot" title="' + title + '"><input type="radio" name="' + name + '" value="' + s.time + '"' +
          (s.available ? '' : ' disabled') + (s.available && s.time === selected ? ' checked' : '') + '><span>' + PC.escape(s.label) + '</span></label>';
      });
      html += '</div>';
    });
    container.innerHTML = html;
    return available;
  };
  PC.slotSkeleton = function () {
    var h = '<div class="slots" style="margin-top:1rem">';
    for (var i = 0; i < 9; i++) h += '<div class="slot-skeleton"></div>';
    return h + '</div>';
  };

  /* ---------- Confetti ---------- */
  PC.confetti = function () {
    if (reduceMotion) return;
    var colors = ['#14a697', '#ff7a59', '#ffc53d', '#7c5cff', '#3b82f6', '#22c55e'];
    for (var i = 0; i < 110; i++) {
      var p = document.createElement('i');
      p.className = 'confetti';
      p.style.left = Math.random() * 100 + 'vw';
      p.style.background = colors[i % colors.length];
      p.style.setProperty('--x', ((Math.random() * 2 - 1) * 160).toFixed(0) + 'px');
      p.style.setProperty('--r', (Math.random() * 900).toFixed(0) + 'deg');
      p.style.animationDuration = (2.3 + Math.random() * 1.8).toFixed(2) + 's';
      p.style.animationDelay = (Math.random() * 0.5).toFixed(2) + 's';
      if (i % 3 === 0) p.style.borderRadius = '50%';
      document.body.appendChild(p);
      (function (el) { setTimeout(function () { el.remove(); }, 5200); })(p);
    }
  };
  if (document.body && document.body.hasAttribute('data-confetti')) setTimeout(PC.confetti, 300);
})();
