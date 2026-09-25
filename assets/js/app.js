/* PawCare — portal features: theme, sidebar, notifications, global search,
   slot pickers, dependent selects and Chart.js dashboards. */
(function () {
  'use strict';
  var PC = window.PC || {};
  var root = document.documentElement;

  /* ---------- Dark mode ---------- */
  document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = next;
      try { localStorage.setItem('pc-theme', next); } catch (e) { /* private mode */ }
      document.dispatchEvent(new CustomEvent('themechange'));
    });
  });

  /* ---------- Mobile sidebar ---------- */
  document.querySelectorAll('[data-sidebar-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
  });
  var backdrop = document.querySelector('[data-sidebar-backdrop]');
  if (backdrop) backdrop.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });

  /* ---------- Notifications ---------- */
  var bell = document.querySelector('[data-notifications]');
  if (bell) {
    var countEl = bell.querySelector('[data-notif-count]');
    var setCount = function (n) {
      countEl.textContent = n > 9 ? '9+' : String(n);
      countEl.hidden = n <= 0;
      countEl.dataset.value = n;
    };
    bell.addEventListener('click', function (e) {
      var item = e.target.closest('[data-notif-id]');
      if (item && item.classList.contains('unread')) {
        PC.api('api/notifications.php', { method: 'POST', body: { action: 'read', id: item.dataset.notifId }, keepalive: true });
        item.classList.remove('unread');
        setCount(Math.max(0, Number(countEl.dataset.value || 0) - 1));
      }
      var all = e.target.closest('[data-notif-read-all]');
      if (all) {
        e.preventDefault();
        PC.api('api/notifications.php', { method: 'POST', body: { action: 'read_all' } }).then(function (r) {
          if (!r.ok) return PC.toast(r.error || 'Could not update notifications.', 'error');
          bell.querySelectorAll('.notif-item.unread').forEach(function (x) { x.classList.remove('unread'); });
          setCount(0);
        });
      }
    });
    setInterval(function () {
      if (document.hidden) return;
      PC.api('api/notifications.php?action=count').then(function (r) {
        if (!r.ok) return;
        var before = Number(countEl.dataset.value || 0);
        setCount(r.unread);
        if (r.unread > before) {
          var btn = bell.querySelector('.icon-btn');
          btn.classList.add('ring');
          setTimeout(function () { btn.classList.remove('ring'); }, 1100);
          if (r.latest) PC.toast(r.latest, 'info');
        }
      });
    }, 40000);
  }

  /* ---------- Global search (staff) ---------- */
  var search = document.querySelector('[data-global-search]');
  if (search) {
    var input = search.querySelector('input');
    var box = search.querySelector('[data-search-results]');
    var timer = null, active = -1, items = [], seq = 0;
    var render = function (data) {
      var groups = [['Patients', data.pets], ['Clients', data.clients], ['Appointments', data.appointments]];
      var html = '';
      groups.forEach(function (g) {
        if (!g[1] || !g[1].length) return;
        html += '<div class="sr-group">' + g[0] + '</div>';
        g[1].forEach(function (r) {
          html += '<a class="sr-item" href="' + PC.escape(r.url) + '">' + (r.thumb || '') +
            '<div class="li-main"><div class="li-title">' + PC.escape(r.title) + '</div><div class="li-sub">' + PC.escape(r.sub) + '</div></div>' + (r.badge || '') + '</a>';
        });
      });
      box.innerHTML = html || '<div class="sr-empty">No matches for “' + PC.escape(input.value) + '”</div>';
      items = Array.prototype.slice.call(box.querySelectorAll('.sr-item'));
      active = -1;
      box.classList.add('open');
    };
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { box.classList.remove('open'); return; }
      timer = setTimeout(function () {
        var mine = ++seq;
        PC.api('api/search.php?q=' + encodeURIComponent(q)).then(function (r) {
          if (mine === seq && r.ok) render(r);
        });
      }, 200);
    });
    input.addEventListener('focus', function () { if (items.length && input.value.trim().length >= 2) box.classList.add('open'); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { box.classList.remove('open'); input.blur(); return; }
      if (!items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach(function (it, k) { it.classList.toggle('active', k === active); });
        items[active].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        e.preventDefault();
        location.href = items[Math.max(active, 0)].href;
      }
    });
    document.addEventListener('keydown', function (e) {
      var tag = (document.activeElement && document.activeElement.tagName) || '';
      if ((e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag)) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
        e.preventDefault();
        input.focus();
        input.select();
      }
    });
    document.addEventListener('click', function (e) { if (!search.contains(e.target)) box.classList.remove('open'); });
  }

  /* ---------- Slot picker for staff forms ---------- */
  document.querySelectorAll('[data-slot-picker]').forEach(function (picker) {
    var q = function (sel) { return sel ? document.querySelector(sel) : null; };
    var service = q(picker.dataset.serviceInput), vet = q(picker.dataset.vetInput), date = q(picker.dataset.dateInput);
    var list = picker.querySelector('[data-slot-list]');
    var msg = picker.querySelector('[data-slot-msg]');
    var selected = picker.dataset.selected || '';
    function load() {
      var d = date && date.value;
      var s = (service && service.value) || picker.dataset.serviceId;
      if (!d || !s) { list.innerHTML = ''; msg.textContent = 'Choose a service and a date to see open times.'; return; }
      list.innerHTML = PC.slotSkeleton();
      msg.textContent = 'Checking the schedule…';
      var params = new URLSearchParams({ date: d, service_id: s, vet_id: (vet && vet.value) || '', ignore: picker.dataset.ignore || '' });
      PC.api('api/slots.php?' + params.toString()).then(function (r) {
        if (!r.ok) { list.innerHTML = ''; msg.textContent = r.error || 'Could not load times.'; return; }
        var n = PC.renderSlots(list, r, picker.dataset.name || 'time', selected);
        msg.textContent = r.closed ? r.message : (n ? n + ' open time' + (n > 1 ? 's' : '') + (r.message ? ' · ' + r.message : '') : (r.message || 'No open times.'));
      });
    }
    [service, vet, date].forEach(function (el) { if (el) el.addEventListener('change', load); });
    list.addEventListener('change', function (e) { if (e.target.name) selected = e.target.value; });
    load();
  });

  /* ---------- Client → pets dependent select ---------- */
  document.querySelectorAll('[data-owner-pets]').forEach(function (sel) {
    var petSel = document.querySelector(sel.dataset.ownerPets);
    sel.addEventListener('change', function () {
      if (!sel.value) { petSel.innerHTML = '<option value="">Choose a client first</option>'; return; }
      petSel.innerHTML = '<option value="">Loading pets…</option>';
      petSel.disabled = true;
      PC.api('api/pets.php?owner_id=' + encodeURIComponent(sel.value)).then(function (r) {
        var pets = (r && r.pets) || [];
        petSel.innerHTML = pets.length
          ? '<option value="">Select a pet</option>' + pets.map(function (p) { return '<option value="' + p.id + '">' + PC.escape(p.name) + ' · ' + PC.escape(p.species) + (p.breed ? ' (' + PC.escape(p.breed) + ')' : '') + '</option>'; }).join('')
          : '<option value="">This client has no pets yet</option>';
        petSel.disabled = false;
        if (pets.length === 1) petSel.value = pets[0].id;
      });
    });
  });
  document.querySelectorAll('[data-filter-select]').forEach(function (inp) {
    var sel = document.querySelector(inp.dataset.filterSelect);
    var opts = Array.prototype.slice.call(sel.options);
    inp.addEventListener('input', function () {
      var t = inp.value.toLowerCase();
      opts.forEach(function (o) { if (o.value) o.hidden = t && o.textContent.toLowerCase().indexOf(t) === -1; });
    });
  });

  /* ---------- Misc form helpers ---------- */
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    master.addEventListener('change', function () {
      document.querySelectorAll(master.dataset.checkAll).forEach(function (c) { c.checked = master.checked; });
    });
  });
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { if (el.form.requestSubmit) el.form.requestSubmit(); else el.form.submit(); });
  });
  document.querySelectorAll('[data-species-select]').forEach(function (holder) {
    var list = document.getElementById(holder.dataset.speciesSelect);
    var breeds = JSON.parse(holder.dataset.breeds || '{}');
    var name = holder.dataset.name || 'species';
    function fill() {
      var picked = document.querySelector('[name="' + name + '"]:checked');
      var species = picked ? picked.value : '';
      list.innerHTML = (breeds[species] || []).map(function (b) { return '<option value="' + PC.escape(b) + '">'; }).join('');
    }
    document.querySelectorAll('[name="' + name + '"]').forEach(function (r) { r.addEventListener('change', fill); });
    fill();
  });
  document.querySelectorAll('[data-due-from]').forEach(function (due) {
    var given = document.querySelector(due.dataset.dueFrom);
    var set = function () {
      if (!given.value || due.dataset.touched) return;
      var d = new Date(given.value + 'T00:00:00');
      d.setFullYear(d.getFullYear() + 1);
      due.value = d.toISOString().slice(0, 10);
    };
    due.addEventListener('input', function () { due.dataset.touched = '1'; });
    given.addEventListener('change', set);
    set();
  });
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(function () { PC.toast('Copied to clipboard'); });
    });
  });

  /* ---------- Charts ---------- */
  var charts = [];
  function css(name) { return getComputedStyle(root).getPropertyValue(name).trim(); }
  function hexA(hex, a) {
    var h = hex.replace('#', '');
    if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
    var n = parseInt(h, 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
  }
  function styleDataset(ds, type, isDoughnut) {
    var color = ds.color || '#14a697';
    if (isDoughnut) {
      return Object.assign({ borderWidth: 3, borderColor: css('--surface'), hoverOffset: 8, backgroundColor: ds.colors }, ds);
    }
    if ((ds.type || type) === 'line') {
      return Object.assign({
        borderColor: color, borderWidth: 3, tension: 0.4, cubicInterpolationMode: 'monotone', pointRadius: 0, pointHoverRadius: 6,
        pointBackgroundColor: color, pointBorderColor: css('--surface'), pointBorderWidth: 2, fill: ds.fill !== false,
        backgroundColor: function (ctx) {
          var area = ctx.chart.chartArea;
          if (!area) return hexA(color, 0.15);
          var g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
          g.addColorStop(0, hexA(color, 0.28));
          g.addColorStop(1, hexA(color, 0));
          return g;
        }
      }, ds);
    }
    return Object.assign({ backgroundColor: color, hoverBackgroundColor: hexA(color, 0.8), borderRadius: 8, borderSkipped: false, maxBarThickness: 30 }, ds);
  }
  function build(cfg) {
    var isDoughnut = cfg.type === 'doughnut';
    var money = !!cfg.money;
    var options = {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      interaction: { mode: isDoughnut ? 'nearest' : 'index', intersect: isDoughnut },
      plugins: {
        legend: { display: cfg.legend !== undefined ? cfg.legend : false, position: cfg.legendPosition || 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, font: { weight: '600' } } },
        tooltip: {
          backgroundColor: '#0b1f1c', titleColor: '#fff', bodyColor: '#d8ebe7', padding: 12, cornerRadius: 12, boxPadding: 5, usePointStyle: true,
          callbacks: {
            label: function (ctx) {
              var v = isDoughnut ? ctx.parsed : (cfg.horizontal ? ctx.parsed.x : ctx.parsed.y);
              var yMoney = ctx.dataset.yAxisID === 'y1' ? !!cfg.y1Money : money;
              return ' ' + (ctx.dataset.label || ctx.label) + ': ' + (yMoney ? PC.money(v) : v.toLocaleString() + (cfg.unit ? ' ' + cfg.unit : ''));
            }
          }
        }
      }
    };
    if (isDoughnut) {
      options.cutout = '70%';
    } else {
      var valueAxis = { beginAtZero: true, grid: { color: css('--line-2') }, border: { display: false }, ticks: { precision: 0, callback: money ? function (v) { return PC.moneyShort(v); } : undefined } };
      // Measurements (e.g. body weight) read better on an axis fitted to the data than one pinned at zero.
      if (cfg.fitY) {
        valueAxis.beginAtZero = false;
        valueAxis.grace = '35%';
        valueAxis.ticks = { precision: 1, maxTicksLimit: 6, callback: function (v) { return v + (cfg.unit ? ' ' + cfg.unit : ''); } };
      }
      var labelAxis = { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14 } };
      options.scales = cfg.horizontal ? { x: valueAxis, y: labelAxis } : { x: labelAxis, y: valueAxis };
      if (cfg.stacked) { options.scales.x.stacked = true; options.scales.y.stacked = true; }
      if (cfg.horizontal) options.indexAxis = 'y';
      if (cfg.y1) {
        options.scales.y1 = { position: 'right', beginAtZero: true, grid: { display: false }, border: { display: false }, ticks: { precision: 0, callback: cfg.y1Money ? function (v) { return PC.moneyShort(v); } : undefined } };
      }
    }
    return {
      type: cfg.type,
      data: { labels: cfg.labels, datasets: cfg.datasets.map(function (ds) { return styleDataset(ds, cfg.type, isDoughnut); }) },
      options: options
    };
  }
  function applyDefaults() {
    if (!window.Chart) return;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = css('--muted');
    Chart.defaults.borderColor = css('--line');
  }
  function initCharts() {
    if (!window.Chart) return;
    applyDefaults();
    document.querySelectorAll('[data-chart]').forEach(function (el) {
      var cfgEl = el.querySelector('script[type="application/json"]');
      if (!cfgEl) return;
      var chart = new Chart(el.querySelector('canvas'), build(JSON.parse(cfgEl.textContent)));
      chart.$cfg = JSON.parse(cfgEl.textContent);
      charts.push(chart);
    });
  }
  document.addEventListener('themechange', function () {
    applyDefaults();
    charts.forEach(function (c) {
      var fresh = build(c.$cfg);
      c.options = fresh.options;
      c.data.datasets.forEach(function (ds, i) { Object.assign(ds, fresh.data.datasets[i]); });
      c.update('none');
    });
  });
  if (document.readyState === 'complete') initCharts();
  else window.addEventListener('load', initCharts);
})();
