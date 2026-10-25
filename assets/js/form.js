/*
 * Pawfectly — the reservation form: steps, conditional questions, browser-side checks,
 * draft saving, the calendar, file choice and the final submit.
 * The server checks everything again; these checks are only there to help people.
 */
(function () {
	'use strict';

	var CFG = window.PAW_FORM;
	var root = document.querySelector('[data-paw-form]');
	if (!CFG || !root) {
		return;
	}

	var form = root.querySelector('form');
	var prevBtn = root.querySelector('[data-paw-prev]');
	var nextBtn = root.querySelector('[data-paw-next]');
	var submitBtn = root.querySelector('[data-paw-submit]');
	var progress = root.querySelector('.paw-progress');
	var formError = root.querySelector('[data-paw-form-error]');
	var draftNote = root.querySelector('[data-paw-draft]');
	var fileList = root.querySelector('[data-paw-file-list]');
	var fileInput = root.querySelector('input[type=file]');
	var N = CFG.steps;
	var I18N = CFG.i18n;
	var DRAFT_KEY = 'paw_draft_v1';
	var DRAFT_DAYS = 7;
	var ALLOWED = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' };

	var current = 0;
	var reached = 0;
	var busy = false;
	var files = [];
	var nonce = CFG.nonce;
	var submissionId = uuid();
	var pickers = {};
	var pad = null;

	function uuid() {
		if (window.crypto && crypto.randomUUID) {
			return crypto.randomUUID();
		}
		var b = new Uint8Array(16);
		crypto.getRandomValues(b);
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}

	function format(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return str.replace(/%(\d)\$d|%d/g, function (m, n) { return n ? args[n - 1] : args[i++]; });
	}

	function fieldEl(id) {
		return root.querySelector('[data-field="' + id + '"]');
	}

	function stepEl(n) {
		return root.querySelector('[data-step="' + n + '"]');
	}

	/* ---------- Values ---------- */

	function value(id) {
		var f = CFG.fields[id];
		if (f.type === 'choice' || f.type === 'yesno') {
			var checked = form.querySelector('input[name="' + id + '"]:checked');
			return checked ? checked.value : '';
		}
		if (f.type === 'agree') {
			return form.querySelector('input[name="' + id + '"]').checked ? '1' : '';
		}
		if (f.type === 'file') {
			return files.length ? String(files.length) : '';
		}
		var input = form.querySelector('[name="' + id + '"]');
		return input ? input.value.trim() : '';
	}

	function isShown(id) {
		var cond = CFG.fields[id].showIf;
		return Object.keys(cond).every(function (target) { return value(target) === cond[target]; });
	}

	function isDate(v) {
		if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) {
			return false;
		}
		var d = new Date(v + 'T00:00:00Z');
		return !isNaN(d) && d.toISOString().slice(0, 10) === v;
	}

	function addDays(iso, n) {
		var d = new Date(iso + 'T00:00:00Z');
		d.setUTCDate(d.getUTCDate() + n);
		return d.toISOString().slice(0, 10);
	}

	/* ---------- Checks (the same rules as Validator.php) ---------- */

	function problem(id) {
		var f = CFG.fields[id];
		var v = value(id);

		if (f.type === 'signature') {
			return pad && !pad.isEmpty() ? null : I18N.signature;
		}
		if (f.type === 'file') {
			return files.length >= 1 && files.length <= CFG.maxFiles ? null : format(I18N.fileCount, CFG.maxFiles);
		}
		if (v === '') {
			if (!f.required) {
				return null;
			}
			return f.type === 'agree' ? I18N.agree : I18N.required;
		}
		if (Array.from(v).length > f.max) {
			return format(I18N.tooLong, f.max);
		}
		if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
			return I18N.email;
		}
		if (f.type === 'tel' && !/^\d{7,20}$/.test(v.replace(/[\s+\-().]/g, ''))) {
			return I18N.phone;
		}
		if (f.type === 'date') {
			if (!isDate(v)) {
				return I18N.date;
			}
			if (id === 'check_in' && v < CFG.today) {
				return I18N.pastCheckIn;
			}
			if (id === 'check_out' && isDate(value('check_in'))) {
				if (v <= value('check_in')) {
					return I18N.checkOut;
				}
				if (v > addDays(value('check_in'), CFG.maxNights)) {
					return format(I18N.tooLongStay, CFG.maxNights);
				}
			}
		}
		return null;
	}

	function showError(id, message) {
		var el = fieldEl(id);
		var slot = document.getElementById('paw-' + id + '-error');
		if (!el || !slot) {
			return;
		}
		el.classList.toggle('is-invalid', !!message);
		slot.textContent = message || '';
		slot.hidden = !message;
		el.querySelectorAll('input, select, textarea').forEach(function (input) {
			if (message) {
				input.setAttribute('aria-invalid', 'true');
			} else {
				input.removeAttribute('aria-invalid');
			}
		});
	}

	function fieldsOf(step) {
		return Object.keys(CFG.fields).filter(function (id) { return CFG.fields[id].step === step; });
	}

	/** Checks one step, shows its messages, and returns the first problem field id (or null). */
	function checkStep(step) {
		var first = null;
		fieldsOf(step).forEach(function (id) {
			var msg = isShown(id) ? problem(id) : null;
			showError(id, msg);
			if (msg && !first) {
				first = id;
			}
		});
		return first;
	}

	function focusField(id) {
		var el = fieldEl(id);
		if (!el) {
			return;
		}
		var target = id === 'signature' ? el.querySelector('canvas')
			: (pickers[id] && pickers[id].altInput) || el.querySelector('input:not([type=hidden]):not([type=file]), select, textarea') || el.querySelector('label');
		if (id === 'vaccination_files') {
			target = fileInput;
		}
		el.scrollIntoView({ block: 'center', behavior: 'auto' });
		if (target && target.focus) {
			target.focus({ preventScroll: true });
		}
		// Point at the date field without opening its calendar over the buttons below it.
		if (pickers[id]) {
			pickers[id].close();
		}
	}

	/* ---------- Conditional questions and warnings ---------- */

	function warned() {
		var blocking = false;
		root.querySelectorAll('[data-paw-warn]').forEach(function (p) {
			var on = value(p.getAttribute('data-paw-warn')) === p.getAttribute('data-paw-warn-value');
			p.hidden = !on;
			if (on && CFG.fields[p.getAttribute('data-paw-warn')].step === current) {
				blocking = true;
			}
		});
		return blocking;
	}

	function refresh() {
		root.querySelectorAll('[data-show-if]').forEach(function (el) {
			el.hidden = !isShown(el.getAttribute('data-field'));
		});
		nextBtn.disabled = warned();
	}

	/* ---------- Navigation ---------- */

	function go(n, push) {
		var from = stepEl(current);
		var to = stepEl(n);
		if (!to) {
			return;
		}
		if (from) {
			from.hidden = true;
		}
		to.hidden = false;
		to.classList.remove('is-entering');
		void to.offsetWidth;
		to.classList.add('is-entering');
		current = n;
		reached = Math.max(reached, n);
		root.setAttribute('data-current', String(n));

		prevBtn.hidden = n === 0;
		nextBtn.hidden = n === N;
		progress.style.setProperty('--paw-progress', (n / N * 100) + '%');
		progress.setAttribute('aria-valuenow', String(n));
		progress.setAttribute('aria-valuetext', n ? format(I18N.stepOf, n, N) : '');
		refresh();
		// A canvas measured while its step was hidden has no size yet.
		if (pad && to.contains(pad.canvas)) {
			pad.resize();
		}

		if (push) {
			history.pushState({ pawStep: n }, '', '#step-' + n);
		}
		var title = to.querySelector('h1, h2');
		root.scrollIntoView({ block: 'start', behavior: 'auto' });
		if (title && n > 0) {
			title.focus({ preventScroll: true });
		}
	}

	nextBtn.addEventListener('click', function () {
		if (current >= N || nextBtn.disabled) {
			return;
		}
		if (current > 0) {
			var bad = checkStep(current);
			if (bad) {
				focusField(bad);
				return;
			}
		}
		go(current + 1, true);
	});

	prevBtn.addEventListener('click', function () {
		if (current > 0) {
			go(current - 1, true);
		}
	});

	window.addEventListener('popstate', function (e) {
		var n = e.state && typeof e.state.pawStep === 'number' ? e.state.pawStep : 0;
		if (!root.classList.contains('is-done') && n <= reached) {
			go(n, false);
		}
	});

	/* ---------- Draft (browser only, never files or the signature) ---------- */

	var saveTimer = null;

	function saveDraft() {
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function () {
			var values = {};
			Object.keys(CFG.fields).forEach(function (id) {
				var t = CFG.fields[id].type;
				if (t !== 'file' && t !== 'signature' && id !== 'signed_date') {
					values[id] = value(id);
				}
			});
			try {
				localStorage.setItem(DRAFT_KEY, JSON.stringify({ t: Date.now(), v: values }));
			} catch (err) { /* Private mode or storage full: drafts are a convenience only. */ }
		}, 500);
	}

	function clearDraft() {
		clearTimeout(saveTimer);
		try {
			localStorage.removeItem(DRAFT_KEY);
		} catch (err) { /* Ignore. */ }
	}

	function setValue(id, v) {
		var t = CFG.fields[id].type;
		if (t === 'choice' || t === 'yesno') {
			var r = form.querySelector('input[name="' + id + '"][value="' + CSS.escape(v) + '"]');
			if (r) {
				r.checked = true;
			}
		} else if (t === 'agree') {
			form.querySelector('input[name="' + id + '"]').checked = v === '1';
		} else if (pickers[id]) {
			pickers[id].setDate(v || null, false);
		} else {
			var input = form.querySelector('[name="' + id + '"]');
			if (input) {
				input.value = v;
			}
		}
	}

	function restoreDraft() {
		var draft = null;
		try {
			draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
		} catch (err) {
			draft = null;
		}
		if (!draft || !draft.v || Date.now() - draft.t > DRAFT_DAYS * 864e5) {
			clearDraft();
			return;
		}
		var any = false;
		Object.keys(draft.v).forEach(function (id) {
			if (CFG.fields[id] && draft.v[id] !== '') {
				setValue(id, String(draft.v[id]));
				any = true;
			}
		});
		if (any && draftNote) {
			draftNote.hidden = false;
		}
	}

	if (draftNote) {
		draftNote.querySelector('[data-paw-restart]').addEventListener('click', function () {
			clearDraft();
			form.reset();
			Object.keys(pickers).forEach(function (id) {
				if (id === 'signed_date') {
					pickers[id].setDate(CFG.today, false);
				} else {
					pickers[id].clear();
				}
			});
			files = [];
			renderFiles();
			if (pad) {
				pad.clear();
			}
			Object.keys(CFG.fields).forEach(function (id) { showError(id, null); });
			draftNote.hidden = true;
			reached = 0;
			refresh();
		});
	}

	/* ---------- Calendar ---------- */

	function setupDates() {
		root.querySelectorAll('[data-paw-date]').forEach(function (input) {
			var id = input.name;
			if (typeof window.flatpickr !== 'function') {
				input.type = 'date';
				input.removeAttribute('inputmode');
				if (input.hasAttribute('data-paw-today')) {
					input.value = CFG.today;
				}
				return;
			}
			var today = input.hasAttribute('data-paw-today');
			pickers[id] = window.flatpickr(input, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'm/d/Y',
				minDate: today ? null : CFG.today,
				defaultDate: today ? CFG.today : null,
				clickOpens: !today,
				disableMobile: true,
				onChange: function () {
					if (id === 'check_in' && pickers.check_out) {
						var ci = value('check_in');
						pickers.check_out.set('minDate', isDate(ci) ? addDays(ci, 1) : CFG.today);
						pickers.check_out.set('maxDate', isDate(ci) ? addDays(ci, CFG.maxNights) : null);
					}
					showError(id, null);
					saveDraft();
				}
			});
			var alt = pickers[id].altInput;
			if (alt) {
				var label = fieldEl(id).querySelector('.paw-label');
				alt.id = 'paw-' + id + '-display';
				if (label) {
					label.setAttribute('for', alt.id);
				}
				alt.setAttribute('aria-describedby', input.getAttribute('aria-describedby') || '');
				alt.setAttribute('placeholder', input.getAttribute('placeholder') || '');
			}
		});
	}

	/* ---------- Files ---------- */

	function size(bytes) {
		return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
	}

	function renderFiles() {
		if (!fileList) {
			return;
		}
		fileList.textContent = '';
		files.forEach(function (file, i) {
			var li = document.createElement('li');
			li.className = 'paw-file__item';
			var name = document.createElement('span');
			name.className = 'paw-file__name';
			name.textContent = file.name;
			var sz = document.createElement('span');
			sz.className = 'paw-file__size';
			sz.textContent = size(file.size);
			var rm = document.createElement('button');
			rm.type = 'button';
			rm.className = 'paw-link';
			rm.textContent = I18N.remove;
			rm.setAttribute('aria-label', I18N.remove + ' ' + file.name);
			rm.addEventListener('click', function () {
				files.splice(i, 1);
				renderFiles();
			});
			li.append(name, sz, rm);
			fileList.appendChild(li);
		});
	}

	if (fileInput) {
		fileInput.addEventListener('change', function () {
			var error = null;
			Array.prototype.forEach.call(fileInput.files, function (file) {
				var ext = (file.name.split('.').pop() || '').toLowerCase();
				var total = files.reduce(function (sum, f) { return sum + f.size; }, 0);
				if (!ALLOWED[ext] || (file.type && file.type !== ALLOWED[ext])) {
					error = I18N.fileType;
				} else if (file.size > CFG.maxBytes) {
					error = I18N.fileSize;
				} else if (total + file.size > CFG.maxTotal) {
					error = I18N.tooLarge;
				} else if (files.length >= CFG.maxFiles) {
					error = format(I18N.fileCount, CFG.maxFiles);
				} else {
					files.push(file);
				}
			});
			fileInput.value = '';
			renderFiles();
			showError('vaccination_files', error);
		});
	}

	/* ---------- Signature ---------- */

	var canvas = root.querySelector('[data-paw-signature]');
	if (canvas && window.SignaturePad) {
		var sigWrap = canvas.closest('.paw-sig');
		var sigInput = sigWrap.querySelector('input[name=signature]');
		pad = new window.SignaturePad(canvas);
		pad.onchange = function () {
			sigInput.value = pad.isEmpty() ? '' : pad.toDataURL();
			sigWrap.classList.toggle('is-signed', !pad.isEmpty());
			if (!pad.isEmpty()) {
				showError('signature', null);
			}
		};
		sigWrap.querySelector('[data-paw-signature-clear]').addEventListener('click', function () {
			pad.clear();
		});
	}

	/* ---------- Typing clears messages, saves the draft, updates conditionals ---------- */

	function onEdit(e) {
		var t = e.target;
		var holder = t.closest && t.closest('[data-field]');
		if (holder && t.type !== 'file') {
			showError(holder.getAttribute('data-field'), null);
		}
		refresh();
		saveDraft();
	}
	form.addEventListener('input', onEdit);
	form.addEventListener('change', onEdit);

	/* ---------- Submit ---------- */

	function showFormError(message) {
		formError.textContent = message || '';
		formError.hidden = !message;
	}

	function payload() {
		var data = new FormData(form);
		data.delete('vaccination_files[]');
		files.forEach(function (file) { data.append('vaccination_files[]', file, file.name); });
		data.set('signature', pad && !pad.isEmpty() ? pad.toDataURL() : '');
		data.set('submission_id', submissionId);
		return data;
	}

	function send(retried) {
		return fetch(CFG.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': nonce },
			body: payload()
		}).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (body) {
				if (res.status === 403 && body.code === 'paw_nonce' && !retried) {
					return fetch(CFG.nonceUrl, { credentials: 'same-origin' })
						.then(function (r) { return r.json(); })
						.then(function (fresh) {
							nonce = fresh.nonce;
							return send(true);
						});
				}
				return { status: res.status, body: body };
			});
		});
	}

	function finish(reference) {
		clearDraft();
		root.querySelector('[data-paw-reference]').textContent = reference;
		root.classList.add('is-done');
		stepEl(current).hidden = true;
		var done = stepEl('done');
		done.hidden = false;
		history.replaceState({ pawStep: 'done' }, '', location.pathname + location.search);
		root.scrollIntoView({ block: 'start' });
		done.querySelector('h2').focus({ preventScroll: true });
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (busy) {
			return;
		}
		showFormError(null);
		for (var s = 1; s <= N; s++) {
			var bad = checkStep(s);
			if (bad) {
				if (s !== current) {
					go(s, true);
					checkStep(s);
				}
				focusField(bad);
				return;
			}
		}

		busy = true;
		submitBtn.disabled = true;
		var label = submitBtn.textContent;
		submitBtn.textContent = I18N.sending;

		send(false).then(function (r) {
			if (r.status === 201 && r.body.reference) {
				finish(r.body.reference);
				return;
			}
			var fields = r.body && r.body.data && r.body.data.fields;
			if (r.status === 400 && fields) {
				var ids = Object.keys(fields).filter(function (id) { return CFG.fields[id]; });
				ids.sort(function (a, b) { return CFG.fields[a].step - CFG.fields[b].step; });
				if (ids.length) {
					go(CFG.fields[ids[0]].step, true);
					ids.forEach(function (id) { showError(id, fields[id]); });
					focusField(ids[0]);
					return;
				}
			}
			showFormError(
				r.status === 429 ? I18N.tooMany
					: r.status === 403 ? I18N.expired
						: r.status === 413 ? I18N.tooLarge
							: I18N.failed
			);
		}).catch(function () {
			showFormError(I18N.network);
		}).then(function () {
			busy = false;
			submitBtn.disabled = false;
			submitBtn.textContent = label;
		});
	});

	/* ---------- Start ---------- */

	setupDates();
	restoreDraft();
	history.replaceState({ pawStep: 0 }, '', location.pathname + location.search);
	go(0, false);
})();
