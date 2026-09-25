/* PawCare — public website interactions */
(function () {
  'use strict';
  var PC = window.PC || {};

  /* ---------- Sticky header, back-to-top, mobile menu ---------- */
  var header = document.querySelector('[data-header]');
  var toTop = document.querySelector('[data-to-top]');
  function onScroll() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 8);
    if (toTop) toTop.classList.toggle('show', window.scrollY > 800);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

  var navToggle = document.querySelector('[data-nav-toggle]');
  if (navToggle) {
    navToggle.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  document.querySelectorAll('[data-nav] a').forEach(function (a) {
    a.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  });

  /* ---------- Highlight the section in view ---------- */
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-nav] a[href*="#"]'));
  var targets = links.map(function (a) { return { link: a, el: document.getElementById(a.hash.slice(1)) }; }).filter(function (t) { return t.el; });
  if (targets.length) {
    // The page's own link (e.g. "Home") is active whenever no section has been reached yet.
    var pageLink = document.querySelector('[data-nav] a[aria-current="page"]');
    var ticking = false;
    var spy = function () {
      ticking = false;
      var line = window.innerHeight * 0.45, current = null, best = -Infinity;
      var atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
      targets.forEach(function (t) {
        var top = t.el.getBoundingClientRect().top;
        if ((top <= line || atBottom) && top > best) { best = top; current = t.link; }
      });
      links.forEach(function (a) { a.classList.toggle('active', a === current); });
      if (pageLink) pageLink.classList.toggle('active', !current);
    };
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(spy); } }, { passive: true });
    spy();
  }

  /* ---------- Service filters (chips + search) ---------- */
  document.querySelectorAll('[data-service-filter]').forEach(function (wrap) {
    var chips = wrap.querySelectorAll('[data-filter]');
    var cards = document.querySelectorAll(wrap.dataset.serviceFilter + ' [data-category]');
    var search = wrap.querySelector('[data-service-search]');
    var empty = document.querySelector(wrap.dataset.empty || '#no-services');
    var cat = 'all';
    function apply() {
      var term = search ? search.value.trim().toLowerCase() : '';
      var shown = 0;
      var grid = document.querySelector(wrap.dataset.serviceFilter);
      if (grid) grid.scrollLeft = 0; // mobile carousel: show the first match
      cards.forEach(function (c) {
        var match = (cat === 'all' || c.dataset.category === cat) && (!term || c.textContent.toLowerCase().indexOf(term) !== -1);
        var wasHidden = c.classList.contains('is-hidden');
        c.classList.toggle('is-hidden', !match);
        if (match) {
          shown++;
          c.style.animationDelay = (shown * 45) + 'ms';
          if (wasHidden || !term) { c.classList.remove('is-entering'); void c.offsetWidth; c.classList.add('is-entering'); }
        }
      });
      if (empty) empty.hidden = shown > 0;
    }
    chips.forEach(function (ch) {
      ch.addEventListener('click', function () {
        chips.forEach(function (x) { x.classList.toggle('active', x === ch); });
        cat = ch.dataset.filter;
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
  });

  /* ---------- Pet age calculator ---------- */
  var calc = document.querySelector('[data-age-calc]');
  if (calc) {
    var species = 'dog', size = 'medium';
    var range = calc.querySelector('[data-age-range]');
    var out = calc.querySelector('[data-age-out]');
    var num = calc.querySelector('[data-age-num]');
    var bar = calc.querySelector('[data-age-bar]');
    var stageEl = calc.querySelector('[data-age-stage]');
    var tipEl = calc.querySelector('[data-age-tip]');
    var sizeRow = calc.querySelector('[data-size-row]');
    var shown = 0;
    var tips = {
      baby: ['Baby', 'Complete the vaccine series, start deworming every 2 weeks, and begin gentle socialisation.'],
      young: ['Young adult', 'Yearly check-ups and boosters, plus dental care and daily exercise keep them thriving.'],
      adult: ['Adult', 'Watch their weight, keep up with boosters and schedule an annual blood panel.'],
      senior: ['Senior', 'Twice-yearly wellness exams help catch arthritis, kidney and heart changes early.']
    };
    function humanAge(y) {
      if (species === 'cat') {
        if (y <= 1) return 15 * y;
        if (y <= 2) return 15 + 9 * (y - 1);
        return 24 + 4 * (y - 2);
      }
      var rate = { small: 4, medium: 5, large: 6 }[size];
      if (y <= 1) return 15 * y;
      if (y <= 2) return 15 + 9 * (y - 1);
      return 24 + rate * (y - 2);
    }
    function animateTo(target) {
      var start = shown, t0 = performance.now();
      (function step(now) {
        var t = Math.min(1, (now - t0) / 500);
        shown = start + (target - start) * (1 - Math.pow(1 - t, 3));
        num.textContent = Math.round(shown);
        if (t < 1) requestAnimationFrame(step);
      })(t0);
    }
    function update() {
      var years = parseFloat(range.value);
      range.style.setProperty('--p', (years / parseFloat(range.max) * 100) + '%');
      out.textContent = years < 1 ? Math.round(years * 12) + ' months' : years + (years === 1 ? ' year' : ' years');
      var h = humanAge(years);
      animateTo(h);
      bar.style.strokeDashoffset = 302 - 302 * Math.min(h, 100) / 100;
      var stage = h < 18 ? 'baby' : h < 40 ? 'young' : h < 60 ? 'adult' : 'senior';
      var babyWord = species === 'cat' ? 'Kitten' : 'Puppy';
      stageEl.textContent = stage === 'baby' ? babyWord : tips[stage][0];
      tipEl.textContent = tips[stage][1];
    }
    calc.querySelectorAll('[data-species]').forEach(function (b) {
      b.addEventListener('click', function () {
        species = b.dataset.species;
        calc.querySelectorAll('[data-species]').forEach(function (x) { x.classList.toggle('active', x === b); });
        sizeRow.hidden = species !== 'dog';
        update();
      });
    });
    calc.querySelectorAll('[data-size]').forEach(function (b) {
      b.addEventListener('click', function () {
        size = b.dataset.size;
        calc.querySelectorAll('[data-size]').forEach(function (x) { x.classList.toggle('active', x === b); });
        update();
      });
    });
    range.addEventListener('input', update);
    update();
  }

  /* ---------- Vaccine guide toggle ---------- */
  document.querySelectorAll('[data-vax-guide]').forEach(function (guide) {
    guide.querySelectorAll('[data-vax]').forEach(function (b) {
      b.addEventListener('click', function () {
        guide.querySelectorAll('[data-vax]').forEach(function (x) { x.classList.toggle('active', x === b); });
        guide.querySelectorAll('[data-vax-plan]').forEach(function (p) { p.hidden = p.dataset.vaxPlan !== b.dataset.vax; });
      });
    });
  });

  /* ---------- Testimonials carousel ---------- */
  document.querySelectorAll('[data-carousel]').forEach(function (car) {
    var track = car.querySelector('[data-track]');
    var slides = track.children;
    var dotsWrap = car.querySelector('[data-dots]');
    var i = 0, timer = null;
    var dots = Array.prototype.map.call(slides, function (_, n) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 't-dot';
      b.setAttribute('aria-label', 'Show review ' + (n + 1));
      b.addEventListener('click', function () { go(n, true); });
      dotsWrap.appendChild(b);
      return b;
    });
    function go(n, user) {
      i = (n + slides.length) % slides.length;
      track.style.transform = 'translateX(-' + i * 100 + '%)';
      dots.forEach(function (d, k) { d.classList.toggle('active', k === i); });
      if (user) restart();
    }
    function restart() { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 6500); }
    var prev = car.querySelector('[data-prev]'), next = car.querySelector('[data-next]');
    if (prev) prev.addEventListener('click', function () { go(i - 1, true); });
    if (next) next.addEventListener('click', function () { go(i + 1, true); });
    var x0 = null;
    track.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    track.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1), true);
      x0 = null;
    });
    car.addEventListener('mouseenter', function () { clearInterval(timer); });
    car.addEventListener('mouseleave', restart);
    go(0);
    restart();
  });

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll('[data-faq] .faq-q').forEach(function (q) {
    q.addEventListener('click', function () {
      var item = q.closest('.faq-item');
      var open = !item.classList.contains('open');
      item.parentElement.querySelectorAll('.faq-item.open').forEach(function (x) {
        x.classList.remove('open');
        x.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
      });
      item.classList.toggle('open', open);
      q.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  /* ---------- Demo account quick-fill (login page) ---------- */
  document.querySelectorAll('[data-demo-login]').forEach(function (b) {
    b.addEventListener('click', function () {
      var form = document.querySelector('[data-login-form]');
      form.querySelector('[name=email]').value = b.dataset.email;
      form.querySelector('[name=password]').value = b.dataset.password;
      form.requestSubmit ? form.requestSubmit() : form.submit();
    });
  });
})();
