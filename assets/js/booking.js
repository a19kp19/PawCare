/* PawCare — 4-step booking wizard for pet owners */
(function () {
  'use strict';
  var PC = window.PC || {};
  var form = document.querySelector('[data-booking]');
  if (!form) return;

  var cfg = JSON.parse(document.getElementById('booking-config').textContent);
  var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
  var indicators = Array.prototype.slice.call(document.querySelectorAll('[data-step-indicator]'));
  var prevBtn = form.querySelector('[data-prev]');
  var nextBtn = form.querySelector('[data-next]');
  var submitBtn = form.querySelector('[data-submit]');
  var calEl = form.querySelector('[data-calendar]');
  var slotsEl = form.querySelector('[data-slots]');
  var slotMsg = form.querySelector('[data-slots-msg]');
  var slotTitle = form.querySelector('[data-slots-title]');
  var dateInput = form.querySelector('[name=date]');
  var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var dows = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  function checked(name) { var el = form.querySelector('[name="' + name + '"]:checked'); return el ? el.value : ''; }
  var state = { pet: checked('pet_id'), service: checked('service_id'), vet: checked('vet_id'), date: dateInput.value, time: cfg.selectedTime || '' };
  var current = 0;
  var view = null;

  function parse(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function iso(y, m, d) { return y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0'); }
  function isoDow(date) { return ((date.getDay() + 6) % 7) + 1; }
  function longDate(isoStr) {
    var d = parse(isoStr);
    return dows[isoDow(d) - 1] + ', ' + months[d.getMonth()].slice(0, 3) + ' ' + d.getDate();
  }

  function dayAllowed(isoStr) {
    if (isoStr < cfg.today || isoStr > cfg.maxDate) return false;
    var dow = isoDow(parse(isoStr));
    if (!cfg.hours[dow]) return false;
    if (state.vet) return (cfg.vets[state.vet] || { days: [] }).days.indexOf(dow) !== -1;
    return Object.keys(cfg.vets).some(function (id) { return cfg.vets[id].days.indexOf(dow) !== -1; });
  }

  function renderCalendar() {
    if (!view) {
      var base = parse(state.date || cfg.today);
      view = new Date(base.getFullYear(), base.getMonth(), 1);
    }
    var y = view.getFullYear(), m = view.getMonth();
    var offset = (new Date(y, m, 1).getDay() + 6) % 7;
    var days = new Date(y, m + 1, 0).getDate();
    var todayD = parse(cfg.today), maxD = parse(cfg.maxDate);
    var canPrev = y > todayD.getFullYear() || m > todayD.getMonth();
    var canNext = y < maxD.getFullYear() || m < maxD.getMonth();
    var html = '<div class="cal-head"><button type="button" class="btn btn-ghost btn-icon" data-cal="-1" aria-label="Previous month"' + (canPrev ? '' : ' disabled') + '>' + PC.icons['chevron-left'] + '</button>' +
      '<strong>' + months[m] + ' ' + y + '</strong><button type="button" class="btn btn-ghost btn-icon" data-cal="1" aria-label="Next month"' + (canNext ? '' : ' disabled') + '>' + PC.icons['chevron-right'] + '</button></div><div class="cal-grid">';
    dows.forEach(function (d) { html += '<div class="cal-dow">' + d + '</div>'; });
    for (var i = 0; i < offset; i++) html += '<span class="cal-day empty"></span>';
    for (var d = 1; d <= days; d++) {
      var ds = iso(y, m, d);
      var cls = 'cal-day' + (ds === cfg.today ? ' today' : '') + (ds === state.date ? ' selected' : '');
      html += '<button type="button" class="' + cls + '" data-date="' + ds + '"' + (dayAllowed(ds) ? '' : ' disabled') + ' aria-label="' + longDate(ds) + '">' + d + '</button>';
    }
    calEl.innerHTML = html + '</div><div class="cal-legend"><span><i class="sel"></i>Selected</span><span><i class="today"></i>Today</span><span><i></i>Available</span></div>';
  }

  calEl.addEventListener('click', function (e) {
    var nav = e.target.closest('[data-cal]');
    if (nav) { view = new Date(view.getFullYear(), view.getMonth() + Number(nav.dataset.cal), 1); renderCalendar(); return; }
    var day = e.target.closest('[data-date]');
    if (day && !day.disabled) {
      state.date = day.dataset.date;
      dateInput.value = state.date;
      state.time = '';
      renderCalendar();
      loadSlots();
      summary();
    }
  });

  function firstAllowedDate() {
    var d = parse(cfg.firstDate || cfg.today);
    for (var i = 0; i < 70; i++) {
      var ds = iso(d.getFullYear(), d.getMonth(), d.getDate());
      if (dayAllowed(ds)) return ds;
      d.setDate(d.getDate() + 1);
    }
    return '';
  }

  var loadSeq = 0;
  function loadSlots() {
    if (!state.date || !state.service) {
      slotsEl.innerHTML = '<div class="slots-empty">' + PC.icons['calendar-days'] + 'Pick a date to see open times.</div>';
      slotMsg.textContent = '';
      slotTitle.textContent = 'Available times';
      return;
    }
    slotTitle.textContent = longDate(state.date);
    slotsEl.innerHTML = PC.slotSkeleton();
    slotMsg.textContent = 'Checking the schedule…';
    var mine = ++loadSeq;
    var params = new URLSearchParams({ date: state.date, service_id: state.service, vet_id: state.vet || '' });
    PC.api('api/slots.php?' + params.toString()).then(function (r) {
      if (mine !== loadSeq) return;
      if (!r.ok) { slotsEl.innerHTML = ''; slotMsg.textContent = r.error || 'Could not load times.'; return; }
      var n = PC.renderSlots(slotsEl, r, 'time', state.time);
      if (state.time && !form.querySelector('[name=time]:checked')) state.time = '';
      slotMsg.textContent = n ? n + ' open time' + (n > 1 ? 's' : '') + ' · ' + (cfg.services[state.service] || {}).duration + ' min visit' : (r.message || 'Fully booked. Try another day.');
      summary();
    });
  }

  function setRow(key, html, filled) {
    var el = document.querySelector('[data-sum="' + key + '"]');
    if (!el) return;
    el.innerHTML = html;
    el.classList.toggle('is-empty', !filled);
  }
  function summary() {
    var pet = cfg.pets[state.pet], svc = cfg.services[state.service], vet = cfg.vets[state.vet];
    var hero = document.querySelector('[data-sum-hero]');
    if (hero) {
      hero.querySelector('[data-sum-photo]').innerHTML = pet ? pet.photo : cfg.placeholderPhoto;
      hero.querySelector('[data-sum-pet]').textContent = pet ? pet.name : 'Your pet';
      hero.querySelector('[data-sum-pet-sub]').textContent = pet ? pet.sub : 'Choose who is visiting';
    }
    setRow('service', svc ? PC.escape(svc.name) + ' <span class="muted">· ' + svc.duration + ' min</span>' : 'Not selected', !!svc);
    setRow('vet', state.vet ? (vet ? PC.escape(vet.name) : '—') : 'First available vet', true);
    setRow('when', state.date ? longDate(state.date) + (state.time ? ' · ' + PC.escape(labelFor(state.time)) : '') : 'Not selected', !!state.date);
    var total = document.querySelector('[data-sum-total]');
    if (total) total.textContent = svc ? PC.money(svc.price) : '—';
  }
  function labelFor(t) {
    var h = +t.slice(0, 2), m = t.slice(3, 5);
    return ((h % 12) || 12) + ':' + m + (h < 12 ? ' AM' : ' PM');
  }

  function validate(n) {
    if (n === 0 && !state.pet) return 'Please choose which pet is visiting.';
    if (n === 1 && !state.service) return 'Please choose a service.';
    if (n === 2 && !state.date) return 'Please pick a date.';
    if (n === 2 && !state.time) return 'Please pick an available time.';
    return null;
  }
  function show(n) {
    current = Math.max(0, Math.min(n, steps.length - 1));
    steps.forEach(function (s, i) { s.classList.toggle('active', i === current); });
    indicators.forEach(function (ind, i) {
      ind.classList.toggle('active', i === current);
      ind.classList.toggle('done', i < current);
    });
    prevBtn.hidden = current === 0;
    nextBtn.hidden = current === steps.length - 1;
    submitBtn.hidden = current !== steps.length - 1;
    if (current === 2) {
      if (state.date && !dayAllowed(state.date)) { state.date = ''; dateInput.value = ''; }
      if (!state.date) { state.date = firstAllowedDate(); dateInput.value = state.date; view = null; }
      renderCalendar();
      loadSlots();
    }
    summary();
    var top = form.getBoundingClientRect().top + window.scrollY - 96;
    if (window.scrollY > top) window.scrollTo({ top: top, behavior: 'smooth' });
  }
  function attempt(n) {
    for (var i = 0; i < n; i++) {
      var err = validate(i);
      if (err) {
        PC.toast(err, 'warning');
        steps[current].classList.remove('shake');
        void steps[current].offsetWidth;
        steps[current].classList.add('shake');
        if (i < current) show(i);
        return false;
      }
    }
    show(n);
    return true;
  }
  nextBtn.addEventListener('click', function () { attempt(current + 1); });
  prevBtn.addEventListener('click', function () { show(current - 1); });
  indicators.forEach(function (ind, i) {
    ind.addEventListener('click', function () { if (i < current) show(i); else if (i > current) attempt(i); });
  });

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'pet_id') { state.pet = t.value; setTimeout(function () { if (current === 0) attempt(1); }, 320); }
    if (t.name === 'service_id') { state.service = t.value; state.time = ''; setTimeout(function () { if (current === 1) attempt(2); }, 320); }
    if (t.name === 'vet_id') {
      state.vet = t.value;
      state.time = '';
      if (state.date && !dayAllowed(state.date)) { state.date = firstAllowedDate(); dateInput.value = state.date; view = null; }
      renderCalendar();
      loadSlots();
    }
    if (t.name === 'time') state.time = t.value;
    summary();
  });

  form.addEventListener('submit', function (e) {
    for (var i = 0; i < 3; i++) {
      var err = validate(i);
      if (err) { e.preventDefault(); PC.toast(err, 'warning'); show(i); return; }
    }
  });

  var initial = cfg.startStep != null ? cfg.startStep : (state.pet && state.service ? 2 : state.pet ? 1 : 0);
  if (initial >= 2) show(2);          // renders the calendar + time slots so the chosen time is submitted
  if (initial !== 2) show(initial);
})();
