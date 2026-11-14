/* Reading controls for the colour-coded Quran: font size, colours, translation, theme. */
(function () {
	'use strict';
	var KEY = 'tjq_mushaf_prefs';
	function load() { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
	function save(p) { try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) { /* storage may be blocked */ } }
	var THEMES = ['light', 'sepia', 'dark'];

	document.querySelectorAll('.tjq-mushaf').forEach(function (root) {
		if (!root.querySelector('.tjq-tools')) { return; }
		var p = load();
		function apply() {
			root.style.setProperty('--m-size', (p.size || 2) + 'rem');
			root.classList.toggle('tj-plain', p.colors === false);
			if (p.trans !== undefined) { root.setAttribute('data-trans', p.trans ? '1' : '0'); }
			if (p.theme && p.theme !== 'light') { root.setAttribute('data-theme', p.theme); } else { root.removeAttribute('data-theme'); }
			var c = root.querySelector('[data-act=colors]'); if (c) { c.setAttribute('aria-pressed', p.colors === false ? 'false' : 'true'); }
			var t = root.querySelector('[data-act=trans]'); if (t) { t.setAttribute('aria-pressed', root.getAttribute('data-trans') === '1' ? 'true' : 'false'); }
		}
		root.querySelector('.tjq-tools').addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) { return; }
			var act = b.getAttribute('data-act');
			if (act === 'font+') { p.size = Math.min(4, +((p.size || 2) + 0.2).toFixed(1)); }
			else if (act === 'font-') { p.size = Math.max(1.2, +((p.size || 2) - 0.2).toFixed(1)); }
			else if (act === 'colors') { p.colors = p.colors === false; }
			else if (act === 'trans') { p.trans = root.getAttribute('data-trans') !== '1'; }
			else if (act === 'theme') { p.theme = THEMES[(THEMES.indexOf(p.theme || 'light') + 1) % THEMES.length]; }
			save(p); apply();
		});
		apply();
	});
})();
