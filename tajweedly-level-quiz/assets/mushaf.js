/* Colour-coded Quran reader: one settings panel, tap an ayah for translation, tap a colour for its rule. */
(function () {
	'use strict';
	var KEY = 'tjq_mushaf_prefs2';
	function load() { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
	function save(p) { try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) { /* storage may be blocked */ } }

	document.querySelectorAll('.tjq-mushaf').forEach(function (root) {
		var bar = root.querySelector('.tjq-gear'); if (!bar) { return; }
		var panel = root.querySelector('.tjq-panel'), pop = root.querySelector('.tjq-pop'), text = root.querySelector('.tjq-text');
		var rules = {}; try { rules = JSON.parse(root.getAttribute('data-rules')) || {}; } catch (e) { rules = {}; }
		var surah = root.getAttribute('data-surah') || '', n = root.getAttribute('data-n') || '';
		var hasTrans = !!root.querySelector('.tjq-trans');
		var p = load();

		function apply() {
			root.style.setProperty('--m-size', (p.size || 2) + 'rem');
			root.classList.toggle('tj-plain', p.colors === false);
			var mode = p.mode === 'flow' ? 'flow' : (hasTrans && p.mode === 'verse' ? 'verse' : 'lines'); root.setAttribute('data-mode', mode);
			if (p.theme && p.theme !== 'light') { root.setAttribute('data-theme', p.theme); } else { root.removeAttribute('data-theme'); }
			var c = root.querySelector('[data-act=colors]'); if (c) { c.setAttribute('aria-pressed', p.colors === false ? 'false' : 'true'); c.textContent = p.colors === false ? 'Off' : 'On'; }
			root.querySelectorAll('[data-theme-set]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-theme-set') === (p.theme || 'light')); });
			root.querySelectorAll('[data-mode-set]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-mode-set') === mode); });
		}
		function closePop() { pop.hidden = true; root.querySelectorAll('.tjq-ayah.is-sel').forEach(function (a) { a.classList.remove('is-sel'); }); }

		bar.addEventListener('click', function () { var o = panel.hidden; panel.hidden = !o; bar.setAttribute('aria-expanded', o ? 'true' : 'false'); });
		panel.addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) { return; }
			var act = b.getAttribute('data-act');
			if (act === 'font+') { p.size = Math.min(4, +((p.size || 2) + 0.2).toFixed(1)); }
			else if (act === 'font-') { p.size = Math.max(1.2, +((p.size || 2) - 0.2).toFixed(1)); }
			else if (act === 'colors') { p.colors = p.colors === false; }
			else if (b.hasAttribute('data-theme-set')) { p.theme = b.getAttribute('data-theme-set'); }
			else if (b.hasAttribute('data-mode-set')) { p.mode = b.getAttribute('data-mode-set'); closePop(); }
			save(p); apply();
		});

		text.addEventListener('click', function (e) {
			if (root.getAttribute('data-mode') === 'verse' && !e.target.closest('[class^=tj-]')) { return; }
			var ay = e.target.closest('.tjq-ayah'); if (!ay) { return; }
			var rule = null, sp = e.target.closest('[class^=tj-]');
			if (sp) { var m = /tj-([a-z])/.exec(sp.className); if (m && rules[m[1]]) { rule = rules[m[1]]; } }
			if (!rule && ay.classList.contains('is-sel') && !pop.hidden) { closePop(); return; }
			root.querySelectorAll('.tjq-ayah.is-sel').forEach(function (a) { a.classList.remove('is-sel'); });
			ay.classList.add('is-sel');
			var tr = ay.querySelector('.tjq-trans'), i = ay.getAttribute('data-i');
			pop.textContent = '';
			var close = document.createElement('button'); close.type = 'button'; close.className = 'tjq-x'; close.setAttribute('aria-label', 'Close'); close.textContent = '×'; close.addEventListener('click', closePop); pop.appendChild(close);
			if (rule) { var r = document.createElement('div'); r.innerHTML = ''; var b = document.createElement('b'); b.textContent = rule[0]; r.appendChild(b); r.appendChild(document.createTextNode(' - this colour')); pop.appendChild(r); }
			var ref = document.createElement('div'); ref.textContent = 'Surah ' + surah + ' ' + n + ':' + i; ref.style.opacity = '.75'; pop.appendChild(ref);
			if (tr) { var t = document.createElement('div'); t.textContent = tr.textContent.replace(/^\d+\.\s*/, ''); pop.appendChild(t); }
			pop.hidden = false;
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closePop(); } });
		apply();
	});
})();
