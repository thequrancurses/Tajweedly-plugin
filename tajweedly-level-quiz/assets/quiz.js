/* Tajweedly Level Quiz - vanilla JS, no dependencies. */
(function () {
	'use strict';
	var root = document.getElementById('tjq-app');
	if (!root || !window.TJQ_CONFIG) { return; }
	var C = window.TJQ_CONFIG, T = C.i18n || {};
	var MODE = root.getAttribute('data-mode') || 'quiz';
	var urlLevel = new URLSearchParams(location.search).get('tjq_level') || '';
	C.fixedLevel = root.getAttribute('data-level') || (C.questions[urlLevel] ? urlLevel : '');
	C.goalStep = root.getAttribute('data-goalstep') !== '0';
	var state = { step: 'intro', level: C.fixedLevel || '', i: 0, answers: {}, goal: '', result: null, banner: '' };

	/* ---------- helpers ---------- */
	function t(key) {
		var s = T[key] != null ? T[key] : key, a = Array.prototype.slice.call(arguments, 1), n = 0;
		return s.replace(/%(\d+\$)?[sd]/g, function (m, pos) {
			var idx = pos ? parseInt(pos, 10) - 1 : n++;
			return a[idx] != null ? a[idx] : '';
		});
	}
	function h(tag, attrs, kids) {
		var el = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'class') { el.className = attrs[k]; }
			else if (k === 'text') { el.textContent = attrs[k]; }
			else if (k.indexOf('on') === 0) { el.addEventListener(k.slice(2), attrs[k]); }
			else { el.setAttribute(k, attrs[k]); }
		});
		(kids || []).forEach(function (c) {
			if (c == null) { return; }
			el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return el;
	}
	function store(key, val) {
		try {
			if (val === undefined) { return JSON.parse(localStorage.getItem(key)); }
			if (val === null) { localStorage.removeItem(key); } else { localStorage.setItem(key, JSON.stringify(val)); }
		} catch (e) { /* storage may be blocked */ }
		return null;
	}
	function qs() { return C.questions[state.level] || []; }
	function label(k) { return (C.labels[k] || [k, ''])[0]; }
	function track(name, data) {
		try {
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push(Object.assign({ event: name }, data || {}));
			if (typeof window.gtag === 'function') { window.gtag('event', name, data || {}); }
			if (typeof window.fbq === 'function') { window.fbq('trackCustom', name, data || {}); }
		} catch (e) { /* analytics must never break the quiz */ }
	}

	/* ---------- tracking context (utm, referral, A/B) ---------- */
	var params = new URLSearchParams(location.search);
	(function remember() {
		if (params.get('utm_source')) {
			store('tjq_utm', { utm_source: params.get('utm_source') || '', utm_medium: params.get('utm_medium') || '', utm_campaign: params.get('utm_campaign') || '' });
		}
		if (params.get('tjq_ref')) { store('tjq_ref', params.get('tjq_ref')); }
	})();
	var variant = '';
	if (C.abA && C.abB) {
		variant = store('tjq_variant');
		if (variant !== 'a' && variant !== 'b') { variant = Math.random() < 0.5 ? 'a' : 'b'; store('tjq_variant', variant); }
	}
	function ctx() {
		var u = store('tjq_utm') || {};
		return {
			utm_source: params.get('utm_source') || u.utm_source || '', utm_medium: params.get('utm_medium') || u.utm_medium || '', utm_campaign: params.get('utm_campaign') || u.utm_campaign || '',
			ref: params.get('tjq_ref') || store('tjq_ref') || '', variant: variant,
			referrer: document.referrer || '', page_url: location.href.split('#')[0]
		};
	}
	function api(path, body, method) {
		var opts = method === 'GET' ? { method: 'GET' } : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
		return fetch(C.restUrl + path, opts).then(function (r) {
			return r.json().then(function (j) { if (!r.ok) { throw new Error(j.message || t('error')); } return j; });
		});
	}

	/* ---------- progress (localStorage + email link) ---------- */
	function saveLocal() { if (state.step === 'quiz') { store('tjq_progress', { level: state.level, answers: state.answers, i: state.i, goal: state.goal, ts: Date.now() }); } }
	function clearLocal() { store('tjq_progress', null); }
	function restore(p) {
		if (!p || !C.questions[p.level]) { return false; }
		state.level = p.level; state.answers = p.answers || {}; state.i = Math.min(p.i || 0, qs().length - 1); state.goal = p.goal || '';
		state.step = 'quiz'; return true;
	}

	/* ---------- views ---------- */
	function header() {
		var logo = root.getAttribute('data-logo');
		return logo ? h('img', { class: 'tjq-logo', src: logo, alt: C.brand }) : null;
	}
	function render() {
		root.innerHTML = '';
		var view = MODE === 'challenge' ? challenge() : state.step === 'intro' ? intro() : state.step === 'goal' ? goal() : state.step === 'quiz' ? quiz() : results();
		root.appendChild(view);
		if (state.step !== 'intro' && MODE !== 'challenge') {
			window.scrollTo({ top: root.getBoundingClientRect().top + window.pageYOffset - 20, behavior: 'smooth' });
		}
	}
	function begin(level) {
		state.level = level; state.i = 0; state.answers = {};
		track('tjq_start', { level: level });
		state.step = C.goalStep && level !== 'kids' ? 'goal' : 'quiz'; render();
	}

	function intro() {
		var kids = [header(), h('h2', { text: root.getAttribute('data-title') }), h('p', { class: 'tjq-intro', text: root.getAttribute('data-intro') })];
		if (state.banner) { kids.push(h('div', { class: 'tjq-banner', text: state.banner })); }
		var saved = store('tjq_progress');
		if (saved && C.questions[saved.level] && Date.now() - saved.ts < 7 * 864e5 && (!C.fixedLevel || C.fixedLevel === saved.level)) {
			kids.push(h('div', { class: 'tjq-card' }, [
				h('div', { class: 'tjq-actions' }, [
					h('button', { class: 'tjq-btn', type: 'button', text: t('continueSaved') + ' (' + label(saved.level) + ')', onclick: function () { restore(saved); render(); } }),
					h('button', { class: 'tjq-btn alt', type: 'button', text: t('startOver'), onclick: function () { clearLocal(); render(); } })
				])
			]));
		}
		if (C.fixedLevel) {
			kids.push(h('button', { class: 'tjq-btn', type: 'button', text: t('startLevel', label(C.fixedLevel)), onclick: function () { begin(C.fixedLevel); } }));
		} else {
			var grid = h('div', { class: 'tjq-levels' });
			Object.keys(C.labels).forEach(function (k) {
				if (!C.questions[k] || !C.questions[k].length) { return; }
				grid.appendChild(h('button', { class: 'tjq-level', type: 'button', onclick: function () { begin(k); } }, [
					h('strong', { text: C.labels[k][0] }), h('span', { text: C.labels[k][1] }), h('em', { text: t('questionsN', C.questions[k].length) })
				]));
			});
			kids.push(grid);
		}
		return h('div', { class: 'tjq-center' }, kids);
	}

	function goal() {
		var list = h('ul', { class: 'tjq-opts' });
		Object.keys(C.goals).forEach(function (k) {
			list.appendChild(h('li', {}, [h('button', { class: 'tjq-opt' + (state.goal === k ? ' sel' : ''), type: 'button', text: C.goals[k], onclick: function () {
				state.goal = k; state.step = 'quiz'; render();
			} })]));
		});
		return h('div', {}, [header(), h('h2', { text: t('goalTitle') }), h('p', { class: 'tjq-intro', text: t('goalIntro') }), list]);
	}

	function quiz() {
		var q = qs()[state.i], total = qs().length, pick = state.answers[q.id];
		saveLocal();
		var list = h('ul', { class: 'tjq-opts', role: 'radiogroup', 'aria-label': q.q });
		q.options.forEach(function (o, idx) {
			list.appendChild(h('li', {}, [h('button', { class: 'tjq-opt' + (pick === idx ? ' sel' : ''), type: 'button', role: 'radio', 'aria-checked': pick === idx ? 'true' : 'false', text: o, onclick: function () {
				state.answers[q.id] = idx; render();
			} })]));
		});
		var next = h('button', { class: 'tjq-btn', type: 'button', text: state.i === total - 1 ? t('seeResult') : t('next'), onclick: function () {
			if (state.answers[q.id] === undefined) { return; }
			if (state.i < total - 1) { state.i++; render(); } else { finish(next); }
		} });
		if (pick === undefined) { next.setAttribute('disabled', 'disabled'); }
		var back = h('button', { class: 'tjq-btn alt', type: 'button', text: t('back'), onclick: function () {
			if (state.i > 0) { state.i--; render(); } else { state.step = 'intro'; render(); }
		} });
		var body = [
			h('div', { class: 'tjq-count', text: t('questionOf', state.i + 1, total) }),
			h('div', { class: 'tjq-progress' }, [h('span', { style: 'width:' + Math.round((state.i / total) * 100) + '%' })]),
			h('div', { class: 'tjq-q', text: q.q })
		];
		if (q.audio) {
			body.push(h('div', { class: 'tjq-audio' }, [h('audio', { controls: 'controls', preload: 'none', src: q.audio }), h('small', { text: t('audioHint') })]));
		}
		body.push(list, h('div', { class: 'tjq-nav' }, [back, next]), saveLater());
		return h('div', {}, body);
	}

	function saveLater() {
		var box = h('div', { class: 'tjq-later' });
		var link = h('button', { class: 'tjq-link', type: 'button', text: t('saveLater'), onclick: function () {
			link.style.display = 'none'; box.appendChild(laterForm(box));
		} });
		box.appendChild(link);
		return box;
	}
	function laterForm(box) {
		var email = h('input', { type: 'email', placeholder: 'you@example.com', 'aria-label': t('email') });
		var hp = h('input', { type: 'text', tabindex: '-1', autocomplete: 'off', class: 'tjq-hp', 'aria-hidden': 'true' });
		var consent = h('input', { type: 'checkbox' });
		var err = h('p', { class: 'tjq-err', role: 'alert' });
		var btn = h('button', { class: 'tjq-btn alt', type: 'submit', text: t('saveLaterBtn') });
		return h('form', { class: 'tjq-form', novalidate: 'novalidate', onsubmit: function (e) {
			e.preventDefault(); err.textContent = '';
			if (!email.value) { err.textContent = t('needEmail'); return; }
			if (!consent.checked) { err.textContent = t('needConsent'); return; }
			btn.setAttribute('disabled', 'disabled');
			api('progress', Object.assign({ level: state.level, answers: state.answers, i: state.i, email: email.value, consent: true, website: hp.value }, ctx())).then(function () {
				box.innerHTML = ''; box.appendChild(h('p', { class: 'tjq-ok', text: t('saved') })); track('tjq_progress_saved', { level: state.level });
			}).catch(function (ex) { err.textContent = ex.message; btn.removeAttribute('disabled'); });
		} }, [email, hp, consentRow(consent), err, btn]);
	}
	function consentRow(box) {
		var span = h('span', { text: C.consentText + ' ' });
		if (C.privacyUrl) { span.appendChild(h('a', { href: C.privacyUrl, target: '_blank', rel: 'noopener', text: t('privacy') })); }
		return h('label', { class: 'tjq-consent' }, [box, span]);
	}

	function finish(btn) {
		btn.setAttribute('disabled', 'disabled'); btn.textContent = t('calculating');
		api('result', Object.assign({ level: state.level, answers: state.answers, goal: state.goal }, ctx())).then(function (res) {
			state.result = res; state.step = 'results'; clearLocal();
			track('tjq_complete', { level: res.level, band: res.band, score: res.score });
			render();
		}).catch(function (e) {
			btn.removeAttribute('disabled'); btn.textContent = t('seeResult'); alert(e.message);
		});
	}

	function ring(score, total) {
		var r = 54, c = 2 * Math.PI * r, off = c * (1 - score / Math.max(total, 1)), ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg'); svg.setAttribute('width', 130); svg.setAttribute('height', 130); svg.setAttribute('viewBox', '0 0 130 130');
		[['#e5e7eb', c, 0], ['var(--tjq-primary)', c, off]].forEach(function (a) {
			var ci = document.createElementNS(ns, 'circle');
			ci.setAttribute('cx', 65); ci.setAttribute('cy', 65); ci.setAttribute('r', r); ci.setAttribute('fill', 'none');
			ci.setAttribute('stroke', a[0]); ci.setAttribute('stroke-width', 12); ci.setAttribute('stroke-linecap', 'round');
			ci.setAttribute('stroke-dasharray', a[1]); ci.setAttribute('stroke-dashoffset', a[2]); svg.appendChild(ci);
		});
		return h('div', { class: 'tjq-ring' }, [svg, h('div', { class: 'num', text: score + '/' + total })]);
	}

	function results() {
		var R = state.result, wrong = R.review.filter(function (x) { return !x.correct; }), review = null;
		if (wrong.length) {
			var ul = h('ul', {});
			wrong.forEach(function (x) {
				ul.appendChild(h('li', {}, [h('strong', { text: x.q }), h('div', { class: 'tjq-ans', text: t('correctAnswer') + ' ' + x.answer + '. ' + x.explain })]));
			});
			review = h('details', { class: 'tjq-review' }, [h('summary', { text: t('reviewN', wrong.length) }), ul]);
		}
		var top = [header()];
		if (R.level !== 'kids') { top.push(ring(R.score, R.total)); }
		top.push(h('div', { class: 'tjq-badge', text: R.label }), h('p', { text: R.message }), h('div', { class: 'tjq-card' }, [h('strong', { text: t('recommended') + ' ' + R.course })]));
		return h('div', {}, [h('div', { class: 'tjq-center' }, top), emailForm(R), shareBlock(R), review]);
	}

	/* ---------- share card ---------- */
	function wrapText(ctx2, text, maxW) {
		var words = String(text).split(' '), lines = [], line = '';
		words.forEach(function (w) {
			var test = line ? line + ' ' + w : w;
			if (ctx2.measureText(test).width > maxW && line) { lines.push(line); line = w; } else { line = test; }
		});
		if (line) { lines.push(line); }
		return lines;
	}
	function cardCanvas(R) {
		var cs = getComputedStyle(root), p = (cs.getPropertyValue('--tjq-primary') || '#0f766e').trim(), a = (cs.getPropertyValue('--tjq-accent') || '#f59e0b').trim();
		var cv = document.createElement('canvas'); cv.width = 1080; cv.height = 1080;
		var g = cv.getContext('2d'), fam = (cs.fontFamily || 'Arial, sans-serif');
		g.fillStyle = p; g.fillRect(0, 0, 1080, 1080);
		g.fillStyle = a; g.fillRect(0, 0, 1080, 18); g.fillRect(0, 1062, 1080, 18);
		g.fillStyle = '#ffffff'; g.textAlign = 'center'; g.direction = C.rtl ? 'rtl' : 'ltr';
		g.font = '700 54px ' + fam; g.fillText(C.brand, 540, 130);
		g.font = '400 40px ' + fam; g.fillText(t('shareTitle'), 540, 250);
		g.beginPath(); g.arc(540, 520, 190, 0, Math.PI * 2); g.fillStyle = 'rgba(255,255,255,.15)'; g.fill();
		g.fillStyle = '#ffffff';
		if (R.level === 'kids') { g.font = '700 150px ' + fam; g.fillText('✓', 540, 575); }
		else { g.font = '700 150px ' + fam; g.fillText(R.score + '/' + R.total, 540, 575); }
		g.fillStyle = a; g.font = '700 54px ' + fam;
		wrapText(g, R.label, 940).forEach(function (l, n) { g.fillText(l, 540, 805 + n * 64); });
		g.fillStyle = '#ffffff'; g.font = '400 36px ' + fam;
		g.fillText(location.host, 540, 1010);
		return cv;
	}
	function shareUrl(R) {
		var u = new URL(C.quizUrl || location.href.split('?')[0], location.href);
		u.searchParams.set('utm_source', 'share'); u.searchParams.set('utm_medium', 'result_card');
		if (R.ref_code) { u.searchParams.set('tjq_ref', R.ref_code); }
		return u.toString();
	}
	function shareBlock(R) {
		var wrap = h('div', { class: 'tjq-share' });
		var msg = t('shareText', R.label), url = shareUrl(R);
		var share = h('button', { class: 'tjq-btn alt', type: 'button', text: t('share'), onclick: function () {
			track('tjq_share', { level: R.level });
			cardCanvas(R).toBlob(function (blob) {
				var file = blob && typeof File === 'function' ? new File([blob], 'tajweed-result.png', { type: 'image/png' }) : null;
				if (navigator.share) {
					var data = { title: t('shareTitle'), text: msg, url: url };
					if (file && navigator.canShare && navigator.canShare({ files: [file] })) { data.files = [file]; }
					navigator.share(data).catch(function () {});
				} else {
					window.open('https://wa.me/?text=' + encodeURIComponent(msg + ' ' + url), '_blank', 'noopener');
				}
			}, 'image/png');
		} });
		var dl = h('button', { class: 'tjq-btn alt', type: 'button', text: t('download'), onclick: function () {
			var a = document.createElement('a'); a.download = 'tajweed-result.png'; a.href = cardCanvas(R).toDataURL('image/png'); a.click();
		} });
		wrap.appendChild(h('div', { class: 'tjq-actions' }, [share, dl]));
		return wrap;
	}

	/* ---------- lead form + thank-you ---------- */
	function emailForm(R) {
		var host = h('div', { class: 'tjq-form' });
		var err = h('p', { class: 'tjq-err', role: 'alert' });
		var name = h('input', { type: 'text', id: 'tjq-name', autocomplete: 'given-name', placeholder: t('namePh') });
		var email = h('input', { type: 'email', id: 'tjq-email', autocomplete: 'email', placeholder: 'you@example.com' });
		var hp = h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'tjq-hp', 'aria-hidden': 'true' });
		var consent = h('input', { type: 'checkbox', id: 'tjq-consent' });
		var btnText = variant === 'a' ? C.abA : variant === 'b' ? C.abB : t('emailBtn');
		var btn = h('button', { class: 'tjq-btn', type: 'submit', text: btnText });
		host.appendChild(h('form', { novalidate: 'novalidate', onsubmit: function (e) {
			e.preventDefault(); err.textContent = '';
			if (!email.value) { err.textContent = t('needEmail'); return; }
			if (!consent.checked) { err.textContent = t('needConsent'); return; }
			btn.setAttribute('disabled', 'disabled');
			api('lead', { attempt_id: R.attempt_id, token: R.token, tz: (function () { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return ''; } })(), name: name.value, email: email.value, consent: true, website: hp.value }).then(function (res) {
				track('tjq_lead', { level: R.level, band: R.band, variant: variant });
				host.innerHTML = ''; host.appendChild(thanks(R, res, name.value, email.value));
			}).catch(function (ex) { err.textContent = ex.message; btn.removeAttribute('disabled'); });
		} }, [
			h('p', { class: 'tjq-center', text: t('formIntro') }),
			h('label', { for: 'tjq-name', text: t('name') }), name,
			h('label', { for: 'tjq-email', text: t('email') }), email, hp,
			consentRow(consent), err, h('div', { class: 'tjq-center' }, [btn])
		]));
		return host;
	}

	function calendlyFrame(url, name, email, R) {
		var u;
		try { u = new URL(url); } catch (e) { return null; }
		u.searchParams.set('embed_domain', location.hostname); u.searchParams.set('embed_type', 'Inline'); u.searchParams.set('hide_gdpr_banner', '1');
		if (name) { u.searchParams.set('name', name); }
		if (email) { u.searchParams.set('email', email); }
		u.searchParams.set('a1', R.label);
		return h('iframe', { class: 'tjq-cal', src: u.toString(), title: t('bookInline'), loading: 'lazy' });
	}

	function thanks(R, res, nameVal, emailVal) {
		var kids = [h('strong', { text: t('thanksTitle') }), h('p', { text: t('thanksText') })];
		var cal = res.calendly_url ? calendlyFrame(res.calendly_url, nameVal, emailVal, R) : null;
		if (cal) {
			kids.push(h('p', { class: 'tjq-center', text: t('bookInline') }), cal);
			track('tjq_calendly_shown', { level: R.level });
		}
		var actions = h('div', { class: 'tjq-actions' });
		var trial = res.trial_url || C.trialUrl;
		if (trial && !cal) { actions.appendChild(h('a', { class: 'tjq-btn', href: trial, target: '_blank', rel: 'noopener', text: t('bookTrial'), onclick: function () { track('tjq_book_click', { level: R.level }); } })); }
		actions.appendChild(h('a', { class: 'tjq-btn alt', href: R.course_url, text: t('viewCourse') }));
		if (res.sheet_url) { actions.appendChild(h('a', { class: 'tjq-btn alt', href: res.sheet_url, target: '_blank', rel: 'noopener', text: t('sheet') })); }
		if (C.whatsapp) {
			var msg = encodeURIComponent(t('whatsappMsg', R.label, R.score, R.total));
			actions.appendChild(h('a', { class: 'tjq-btn alt', href: 'https://wa.me/' + C.whatsapp + '?text=' + msg, target: '_blank', rel: 'noopener', text: t('whatsapp') }));
		}
		kids.push(actions);
		if (res.ref_url) { kids.push(referralBox(res.ref_url, res.ref_reward)); }
		return h('div', { class: 'tjq-center tjq-card' }, kids);
	}

	function referralBox(url, reward) {
		var input = h('input', { type: 'text', readonly: 'readonly', value: url, 'aria-label': 'link', onclick: function () { this.select(); } });
		var btn = h('button', { class: 'tjq-btn alt', type: 'button', text: t('copy'), onclick: function () {
			var done = function () { btn.textContent = t('copied'); setTimeout(function () { btn.textContent = t('copy'); }, 1800); };
			if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done); } else { input.select(); document.execCommand('copy'); done(); }
			track('tjq_referral_copy');
		} });
		return h('div', { class: 'tjq-ref' }, [h('p', { text: t('referral', reward) }), h('div', { class: 'tjq-refrow' }, [input, btn])]);
	}

	/* ---------- challenge sign-up ---------- */
	function challenge() {
		var host = h('div', { class: 'tjq-form' });
		var err = h('p', { class: 'tjq-err', role: 'alert' });
		var name = h('input', { type: 'text', autocomplete: 'given-name', placeholder: t('namePh') });
		var email = h('input', { type: 'email', autocomplete: 'email', placeholder: 'you@example.com' });
		var hp = h('input', { type: 'text', tabindex: '-1', autocomplete: 'off', class: 'tjq-hp', 'aria-hidden': 'true' });
		var consent = h('input', { type: 'checkbox' });
		var btn = h('button', { class: 'tjq-btn', type: 'submit', text: t('challengeBtn') });
		host.appendChild(h('form', { novalidate: 'novalidate', onsubmit: function (e) {
			e.preventDefault(); err.textContent = '';
			if (!email.value) { err.textContent = t('needEmail'); return; }
			if (!consent.checked) { err.textContent = t('needConsent'); return; }
			btn.setAttribute('disabled', 'disabled');
			api('challenge', Object.assign({ name: name.value, email: email.value, consent: true, website: hp.value }, ctx())).then(function (res) {
				track('tjq_challenge');
				host.innerHTML = ''; var k = [h('strong', { text: t('challengeDone') })];
				if (res.ref_url) { k.push(referralBox(res.ref_url, res.ref_reward)); }
				host.appendChild(h('div', { class: 'tjq-center tjq-card' }, k));
			}).catch(function (ex) { err.textContent = ex.message; btn.removeAttribute('disabled'); });
		} }, [
			h('label', { text: t('name') }), name, h('label', { text: t('email') }), email, hp, consentRow(consent), err, h('div', { class: 'tjq-center' }, [btn])
		]));
		return h('div', {}, [h('div', { class: 'tjq-center' }, [header(), h('h2', { text: root.getAttribute('data-title') || t('challengeTitle') }), h('p', { class: 'tjq-intro', text: root.getAttribute('data-intro') || t('challengeIntro') })]), host]);
	}

	/* ---------- boot ---------- */
	var token = params.get('tjq_resume');
	if (MODE === 'quiz' && token) {
		api('resume?token=' + encodeURIComponent(token), null, 'GET').then(function (res) {
			if (restore(res.progress)) { state.banner = t('resumeBanner'); }
			render();
		}).catch(function () { render(); });
	} else {
		render();
	}
})();
