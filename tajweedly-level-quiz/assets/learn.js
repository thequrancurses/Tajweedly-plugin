(function () {
  'use strict';
  var KEY = 'tjq_done';
  function store(get, v) { try { if (get) { return JSON.parse(localStorage.getItem(KEY) || '[]'); } localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { return get ? [] : null; } return null; }
  function done() { var d = store(true); return Array.isArray(d) ? d : []; }
  function markDone(slug) { if (!slug) { return; } var d = done(); if (d.indexOf(slug) < 0) { d.push(slug); store(false, d); } refresh(); }
  function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (txt != null) { e.textContent = txt; } return e; }
  function parse(n, a) { try { return JSON.parse(n.getAttribute(a)); } catch (e) { return null; } }
  function shuffle(a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }

  /* ---- progress UI (path, lesson bar, buttons) ---- */
  function refresh() {
    var d = done();
    document.querySelectorAll('.tjq-path').forEach(function (p) {
      var total = +p.getAttribute('data-total') || 1, n = 0, next = null;
      p.querySelectorAll('.tjq-card').forEach(function (c) {
        var s = c.getAttribute('data-slug'), is = d.indexOf(s) >= 0;
        c.classList.toggle('is-done', is);
        if (is) { n++; } else if (!next && c.tagName === 'A') { next = c; }
      });
      p.querySelector('.tjq-path-count').textContent = n + ' of ' + total + ' lessons done';
      p.querySelector('.tjq-bar i').style.width = Math.round(n / total * 100) + '%';
      var go = p.querySelector('.tjq-path-go');
      if (next) { go.hidden = false; go.href = next.href; go.textContent = n ? 'Continue' : 'Start learning'; } else { go.hidden = n < total; if (n >= total) { go.textContent = 'All done - take the free test'; go.href = p.getAttribute('data-cta') || '#'; go.hidden = !p.getAttribute('data-cta'); } }
    });
    document.querySelectorAll('.tjq-lessonbar').forEach(function (b) {
      var total = +b.getAttribute('data-total') || 1, s = b.getAttribute('data-slug'), n = d.length > total ? total : d.length;
      b.querySelector('.tjq-bar i').style.width = Math.round(n / total * 100) + '%';
      b.querySelector('.tjq-lb-done').hidden = d.indexOf(s) < 0;
    });
    document.querySelectorAll('.tjq-markdone').forEach(function (btn) {
      var is = d.indexOf(btn.getAttribute('data-slug')) >= 0;
      btn.textContent = is ? '✓ Lesson completed' : 'Mark this lesson as done';
      btn.classList.toggle('is-done', is);
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.tjq-markdone') : null;
    if (b) { markDone(b.getAttribute('data-slug')); }
  });

  /* ---- table of contents ---- */
  function toc() {
    document.querySelectorAll('.tjq-lessonbar .tjq-toc').forEach(function (nav) {
      var root = nav.closest('.tjq-page') || document, hs = root.querySelectorAll('h2'), n = 0;
      if (hs.length < 3) { return; }
      var ul = el('ul');
      hs.forEach(function (h, i) {
        if (h.closest('.tjq-learn')) { return; }
        if (!h.id) { h.id = 'tjq-s' + i; }
        var li = el('li'), a = el('a', '', h.textContent); a.href = '#' + h.id; li.appendChild(a); ul.appendChild(li); n++;
      });
      if (n > 1) { nav.appendChild(el('strong', '', 'In this lesson')); nav.appendChild(ul); nav.hidden = false; }
    });
  }

  /* ---- explorer ---- */
  function explorers() {
    document.querySelectorAll('.tjq-explorer').forEach(function (x) {
      var groups = parse(x, 'data-groups') || [], note = x.querySelector('.tjq-exp-note'), cells = x.querySelectorAll('.tjq-let'), chips = x.querySelectorAll('.tjq-chip');
      chips.forEach(function (c) {
        c.addEventListener('click', function () {
          var g = groups[+c.getAttribute('data-i')], on = c.classList.contains('is-on');
          chips.forEach(function (o) { o.classList.remove('is-on'); });
          cells.forEach(function (l) { l.classList.remove('is-lit'); });
          if (on) { note.textContent = 'Tap a rule to light up its letters. (Hamzah shares the cell of alif.)'; return; }
          c.classList.add('is-on');
          var set = g[1];
          cells.forEach(function (l) { var t = l.textContent; if (set.indexOf(t) >= 0 || (t === 'ا' && set.indexOf('ء') >= 0)) { l.classList.add('is-lit'); } });
          note.textContent = g[0] + ' (' + set.length + ' letters): ' + g[2];
        });
      });
    });
  }

  /* ---- drills ---- */
  function drills() {
    document.querySelectorAll('.tjq-drill').forEach(function (root) {
      var items = parse(root, 'data-items') || [], type = root.getAttribute('data-type'), slug = root.getAttribute('data-slug'), cta = root.getAttribute('data-cta');
      var body = root.querySelector('.tjq-drill-body'), stat = root.querySelector('.tjq-drill-stat'), bestKey = 'tjq_best_' + type;
      if (!items.length) { return; }
      var round, i, score, streak;
      function best() { try { return +localStorage.getItem(bestKey) || 0; } catch (e) { return 0; } }
      function start() { round = shuffle(items).slice(0, 10); i = 0; score = 0; streak = 0; show(); }
      function head() { stat.textContent = 'Question ' + (i + 1) + ' of ' + round.length + ' · Score ' + score + (streak > 1 ? ' · 🔥 ' + streak : ''); }
      function show() {
        var q = round[i]; body.textContent = ''; head();
        body.appendChild(el('p', 'tjq-q', q.p));
        if (q.a) { var a = el('div', 'tjq-q-ar', q.a); a.setAttribute('lang', 'ar'); a.setAttribute('dir', 'rtl'); body.appendChild(a); }
        var opts = el('div', 'tjq-opts'), fb = el('div', 'tjq-fb'); fb.setAttribute('aria-live', 'polite');
        q.o.forEach(function (t, k) {
          var b = el('button', 'tjq-opt', t); b.type = 'button';
          b.addEventListener('click', function () {
            if (opts.getAttribute('data-locked')) { return; }
            opts.setAttribute('data-locked', '1');
            var ok = k === q.c; if (ok) { score++; streak++; } else { streak = 0; }
            b.classList.add(ok ? 'is-right' : 'is-wrong');
            opts.children[q.c].classList.add('is-right');
            fb.textContent = (ok ? 'Correct. ' : 'Not quite. ') + q.e; fb.className = 'tjq-fb ' + (ok ? 'ok' : 'no'); head();
            var nx = el('button', 'tjq-btn', i + 1 < round.length ? 'Next' : 'See result'); nx.type = 'button';
            nx.addEventListener('click', function () { i++; if (i < round.length) { show(); } else { finish(); } });
            body.appendChild(nx); nx.focus();
          });
          opts.appendChild(b);
        });
        body.appendChild(opts); body.appendChild(fb);
      }
      function finish() {
        var total = round.length, prev = best(), pct = Math.round(score / total * 100);
        try { if (score > prev) { localStorage.setItem(bestKey, String(score)); } } catch (e) {}
        body.textContent = ''; stat.textContent = '';
        body.appendChild(el('p', 'tjq-result', score + ' / ' + total));
        body.appendChild(el('p', '', pct >= 80 ? 'Excellent. Lesson marked as done.' : (pct >= 50 ? 'Good. Read the explanations again and try another round.' : 'Keep practising: re-read the lesson, then try again.')));
        if (prev && score <= prev) { body.appendChild(el('p', 'tjq-small', 'Your best: ' + prev + ' / ' + total)); }
        if (pct >= 80 && slug) { markDone(slug); }
        var again = el('button', 'tjq-btn', 'New round'); again.type = 'button'; again.addEventListener('click', start); body.appendChild(again);
        if (cta) { var a = el('a', 'tjq-btn tjq-btn-alt', 'Find your full level - free test'); a.href = cta; body.appendChild(a); }
      }
      start();
    });
  }

  function init() { toc(); explorers(); drills(); refresh(); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
