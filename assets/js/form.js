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
	var createBox = form.querySelector('input[name=create_account]');
	var accountField = root.querySelector('[data-field=account_password]');
	var confirmField = root.querySelector('[data-field=account_password_confirm]');
	var ACCOUNT = CFG.account || { signedIn: false, prefill: {} };
	var N = CFG.steps;
	var I18N = CFG.i18n;
	var DRAFT_KEY = 'paw_draft_v2';
	var DRAFT_DAYS = 7;
	var ALLOWED = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' };

	var current = 0;
	var reached = 0;
	var busy = false;
	var files = [[]]; // Chosen vaccination files, one list per pet.
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

	/*
	 * Every answer has a key: the field id for booking answers ("check_in"), or "pets.N.id" for a
	 * pet's answer, the same keys the server uses for its errors.
	 */
	function parse(key) {
		var m = /^pets\.(\d+)\.(.+)$/.exec(key);
		return m ? { n: +m[1], id: m[2] } : { n: null, id: key };
	}

	function petKey(id, n) {
		return CFG.fields[id] && CFG.fields[id].perPet ? 'pets.' + n + '.' + id : id;
	}

	/** The key of a [data-field] element: per-pet fields live inside a [data-paw-pet] card. */
	function keyOf(el) {
		var card = el.closest('[data-paw-pet]');
		var id = el.getAttribute('data-field');
		return card ? 'pets.' + card.getAttribute('data-paw-pet') + '.' + id : id;
	}

	function fieldEl(key) {
		var k = parse(key);
		if (k.n === null) {
			return root.querySelector('[data-field="' + k.id + '"]');
		}
		return root.querySelector('[data-paw-pet="' + k.n + '"] [data-field="' + k.id + '"]');
	}

	/** The input name for a key: "check_in", "pets[0][pet_name]", or the file input's name. */
	function nameOf(key) {
		var k = parse(key);
		if (k.n === null) {
			return k.id;
		}
		return CFG.fields[k.id].type === 'file' ? 'vaccination_files_' + k.n + '[]' : 'pets[' + k.n + '][' + k.id + ']';
	}

	function petCount() {
		return root.querySelectorAll('[data-paw-pets="pet"] [data-paw-pet]').length;
	}

	function stepEl(n) {
		return root.querySelector('[data-step="' + n + '"]');
	}

	/* ---------- Values ---------- */

	function value(key) {
		var k = parse(key);
		var f = CFG.fields[k.id];
		var name = nameOf(key);
		if (f.type === 'choice' || f.type === 'yesno' || f.type === 'service') {
			var checked = form.querySelector('input[name="' + name + '"]:checked');
			return checked ? checked.value : '';
		}
		if (f.type === 'agree') {
			return form.querySelector('input[name="' + name + '"]').checked ? '1' : '';
		}
		if (f.type === 'file') {
			var list = files[k.n] || [];
			return list.length ? String(list.length) : '';
		}
		var input = form.querySelector('[name="' + name + '"]');
		return input ? input.value.trim() : '';
	}

	/**
	 * A pet's conditional question looks at that pet's answers and at the booking's. A hidden
	 * answer counts as empty, as on the server, so a service hidden by switching to Boarding
	 * also hides the groomer and schedule questions that depend on it.
	 */
	function isShown(key, depth) {
		var k = parse(key);
		var cond = CFG.fields[k.id].showIf;
		depth = depth || 0;
		return Object.keys(cond).every(function (target) {
			var wanted = cond[target];
			var targetKey = k.n === null ? target : petKey(target, k.n);
			var v = depth < 5 && !isShown(targetKey, depth + 1) ? '' : value(targetKey);
			return Array.isArray(wanted) ? wanted.indexOf(v) !== -1 : v === wanted;
		});
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

	function filesOptional() {
		return !CFG.groomingNeedsVaccines && value('booking_type') === 'grooming';
	}

	function problem(key) {
		var k = parse(key);
		var id = k.id;
		var f = CFG.fields[id];
		var v = value(key);

		if (f.type === 'signature') {
			return pad && !pad.isEmpty() ? null : I18N.signature;
		}
		if (f.type === 'file') {
			var count = (files[k.n] || []).length;
			if (count === 0 && filesOptional()) {
				return null;
			}
			return count >= 1 && count <= CFG.maxFiles ? null : format(I18N.fileCount, CFG.maxFiles);
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
			if (f.past && v > CFG.today) {
				return I18N.lastGroom;
			}
			if (id === 'appointment_date') {
				if (value('booking_type') === 'grooming' && v < CFG.today) {
					return I18N.pastGrooming;
				}
				if (value('booking_type') === 'both' && isDate(value('check_in')) && isDate(value('check_out')) && (v < value('check_in') || v > value('check_out'))) {
					return I18N.groomingStay;
				}
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

	function showError(key, message) {
		var el = fieldEl(key);
		var slot = el && el.querySelector('.paw-error');
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

	/** Every answer key on a step: booking answers once, pet answers for each pet. */
	function keysOf(step) {
		var keys = [];
		var pets = petCount();
		Object.keys(CFG.fields).forEach(function (id) {
			if (CFG.fields[id].step !== step) {
				return;
			}
			if (!CFG.fields[id].perPet) {
				keys.push(id);
				return;
			}
			for (var n = 0; n < pets; n++) {
				keys.push('pets.' + n + '.' + id);
			}
		});
		return keys;
	}

	function creatingAccount() {
		return !!(createBox && createBox.checked);
	}

	/** The step a server error belongs to (account_password is not a reservation field). */
	function stepOf(key) {
		var id = parse(key).id;
		if (CFG.fields[id]) {
			return CFG.fields[id].step;
		}
		if (key === 'pets') {
			return 2;
		}
		return id === 'account_password' || id === 'account_password_confirm' ? 1 : null;
	}

	/** Server keys for files ("vaccination_files_1") become this form's keys ("pets.1.vaccination_files"). */
	function fromServer(key) {
		var m = /^vaccination_files_(\d+)$/.exec(key);
		return m ? 'pets.' + m[1] + '.vaccination_files' : key;
	}

	/** Rules across pets on the Reservation Details step (the server checks them too). */
	function stayProblem() {
		var type = value('booking_type');
		var pets = petCount();
		var n;
		if (type === 'grooming') {
			for (n = 0; n < pets; n++) {
				if (value('pets.' + n + '.pet_type') !== 'dog') {
					return I18N.dogsOnly;
				}
			}
		}
		if (type === 'both') {
			for (n = 0; n < pets; n++) {
				var svc = isShown('pets.' + n + '.service') ? value('pets.' + n + '.service') : '';
				if (svc !== '' && svc !== 'none') {
					return null;
				}
			}
			for (n = 0; n < pets; n++) {
				if (isShown('pets.' + n + '.service') && value('pets.' + n + '.service') === '') {
					return null; // A service is still to be chosen: that field says so.
				}
			}
			return I18N.oneGroomed;
		}
		return null;
	}

	/** Checks one step, shows its messages, and returns the first problem field id (or null). */
	function checkStep(step) {
		var first = null;
		keysOf(step).forEach(function (key) {
			var msg = isShown(key) ? problem(key) : null;
			showError(key, msg);
			if (msg && !first) {
				first = key;
			}
		});
		var stepError = stepEl(step).querySelector('[data-paw-step-error]');
		if (stepError) {
			var rule = stayProblem();
			stepError.textContent = rule || '';
			stepError.hidden = !rule;
			if (rule && !first) {
				first = 'booking_type';
				stepError.scrollIntoView({ block: 'center' });
				return '__step';
			}
		}
		if (step === 1 && accountField) {
			var pw = form.querySelector('[name=account_password]').value;
			var bad = creatingAccount() && Array.from(pw).length < 8 ? I18N.passwordShort : null;
			showError('account_password', bad);
			if (bad && !first) {
				first = 'account_password';
			}
			var again = form.querySelector('[name=account_password_confirm]').value;
			var differs = creatingAccount() && !bad && again !== pw ? I18N.mismatch : null;
			showError('account_password_confirm', differs);
			if (differs && !first) {
				first = 'account_password_confirm';
			}
		}
		return first;
	}

	function focusField(key) {
		if (key === '__step') {
			return; // A rule across pets: its message sits at the top of the step.
		}
		var el = fieldEl(key);
		if (!el) {
			return;
		}
		var picker = pickers[nameOf(key)];
		var id = parse(key).id;
		var target = id === 'signature' ? el.querySelector('canvas')
			: (picker && picker.altInput) || el.querySelector('input:not([type=hidden]):not([type=file]), select, textarea') || el.querySelector('label');
		if (id === 'vaccination_files') {
			target = el.querySelector('input[type=file]');
		}
		el.scrollIntoView({ block: 'center', behavior: 'auto' });
		if (target && target.focus) {
			target.focus({ preventScroll: true });
		}
		// Point at the date field without opening its calendar over the buttons below it.
		if (picker) {
			picker.close();
		}
	}

	/* ---------- Conditional questions and warnings ---------- */

	function warned() {
		var blocking = false;
		root.querySelectorAll('[data-paw-warn]').forEach(function (p) {
			var id = p.getAttribute('data-paw-warn');
			var card = p.closest('[data-paw-pet]');
			var on = value(card ? 'pets.' + card.getAttribute('data-paw-pet') + '.' + id : id) === p.getAttribute('data-paw-warn-value');
			p.hidden = !on;
			if (on && CFG.fields[p.getAttribute('data-paw-warn')].step === current) {
				blocking = true;
			}
		});
		return blocking;
	}

	function refresh() {
		var type = value('booking_type');
		root.querySelectorAll('[data-paw-both-only]').forEach(function (el) {
			el.hidden = type !== 'both';
			var radio = el.querySelector('input');
			if (el.hidden && radio && radio.checked) {
				radio.checked = false;
			}
		});
		root.querySelectorAll('[data-show-if]').forEach(function (el) {
			el.hidden = !isShown(keyOf(el));
		});
		// A pet card with nothing to ask (a cat's grooming card) is hidden too.
		root.querySelectorAll('[data-paw-pets] [data-paw-pet]').forEach(function (card) {
			card.hidden = !card.querySelector('[data-field]:not([hidden])');
		});
		root.querySelectorAll('[data-paw-waiver]').forEach(function (box) {
			var kind = box.getAttribute('data-paw-waiver');
			box.hidden = kind === 'boarding' ? type === 'grooming' : type === 'boarding';
		});
		root.querySelectorAll('[data-title-grooming]').forEach(function (h) {
			h.textContent = type === 'grooming' ? h.getAttribute('data-title-grooming') : h.getAttribute('data-title');
		});
		if (accountField) {
			accountField.hidden = !creatingAccount();
			confirmField.hidden = !creatingAccount();
		}
		// A rule across pets stops showing as soon as it holds again.
		root.querySelectorAll('[data-paw-step-error]:not([hidden])').forEach(function (p) {
			if (!stayProblem()) {
				p.hidden = true;
			}
		});
		petNames();
		if (addBtn) {
			addBtn.hidden = petCount() >= CFG.maxPets;
		}
		showPrices();
		showEstimate();
		showProgress();
		nextBtn.disabled = warned();
	}

	/* ---------- Pets: add, remove, names ---------- */

	var addBtn = root.querySelector('[data-paw-add-pet]');

	function petNames() {
		root.querySelectorAll('[data-paw-pets] [data-paw-pet]').forEach(function (card) {
			if (card.closest('[data-paw-pets="pet"]')) {
				return; // The Pets step keeps "Pet 1", "Pet 2".
			}
			var n = +card.getAttribute('data-paw-pet');
			card.querySelector('[data-paw-pet-name]').textContent = value('pets.' + n + '.pet_name') || format(I18N.petN, n + 1);
		});
	}

	function petIds() {
		return Object.keys(CFG.fields).filter(function (id) { return CFG.fields[id].perPet && CFG.fields[id].type !== 'file'; });
	}

	function petValues(n) {
		var out = {};
		petIds().forEach(function (id) { out[id] = value('pets.' + n + '.' + id); });
		return out;
	}

	function setPetValues(n, values) {
		Object.keys(values).forEach(function (id) {
			if (CFG.fields[id] && CFG.fields[id].perPet) {
				setValue('pets.' + n + '.' + id, String(values[id]));
			}
		});
	}

	function addPet() {
		var n = petCount();
		if (n >= CFG.maxPets) {
			return;
		}
		root.querySelectorAll('[data-paw-pet-template]').forEach(function (tpl) {
			var list = root.querySelector('[data-paw-pets="' + tpl.getAttribute('data-paw-pet-template') + '"]');
			var holder = document.createElement('div');
			holder.innerHTML = tpl.innerHTML.replace(/__N__/g, String(n)).replace(/__NUM__/g, String(n + 1));
			var card = holder.firstElementChild;
			list.appendChild(card);
			setupDates(card);
		});
		files[n] = [];
	}

	/** Removes pet n: later pets move up one place with their answers and files. */
	function removePet(n) {
		var count = petCount();
		var later = [];
		for (var i = n + 1; i < count; i++) {
			later.push({ values: petValues(i), files: files[i] || [] });
		}
		root.querySelectorAll('[data-paw-pets]').forEach(function (list) {
			var card = list.querySelector('[data-paw-pet="' + (count - 1) + '"]');
			if (card) {
				card.querySelectorAll('[data-paw-date]').forEach(function (input) {
					if (pickers[input.name]) {
						pickers[input.name].destroy();
						delete pickers[input.name];
					}
				});
				card.remove();
			}
		});
		files.length = count - 1;
		later.forEach(function (pet, j) {
			clearPet(n + j);
			setPetValues(n + j, pet.values);
			files[n + j] = pet.files;
			renderFiles(n + j);
		});
		Object.keys(CFG.fields).forEach(function (id) {
			for (var k = n; k < count - 1; k++) {
				showError(petKey(id, k), null);
			}
		});
	}

	function clearPet(n) {
		root.querySelectorAll('[data-paw-pet="' + n + '"]').forEach(function (card) {
			card.querySelectorAll('input, textarea, select').forEach(function (input) {
				if (input.type === 'radio' || input.type === 'checkbox') {
					input.checked = input.defaultChecked;
				} else if (input.type !== 'file') {
					input.value = '';
				}
			});
			card.querySelectorAll('[data-paw-date]').forEach(function (input) {
				if (pickers[input.name]) {
					pickers[input.name].clear();
				}
			});
		});
	}

	function ensurePets(count) {
		count = Math.max(1, Math.min(CFG.maxPets, count));
		while (petCount() < count) {
			addPet();
		}
		while (petCount() > count) {
			removePet(petCount() - 1);
		}
	}

	if (addBtn) {
		addBtn.addEventListener('click', function () {
			addPet();
			refresh();
			saveDraft();
			var cards = root.querySelectorAll('[data-paw-pets="pet"] [data-paw-pet]');
			var input = cards[cards.length - 1].querySelector('input');
			if (input) {
				input.focus();
			}
		});
	}
	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-paw-remove-pet]');
		if (!btn) {
			return;
		}
		removePet(+btn.closest('[data-paw-pet]').getAttribute('data-paw-pet'));
		refresh();
		saveDraft();
		addBtn.focus();
	});

	/* ---------- Steps shown for this booking ---------- */

	/** A step with nothing left to ask (Special Care for a grooming-only visit) is skipped. */
	function stepShown(step) {
		if (step === 0 || step > N) {
			return true;
		}
		return keysOf(step).some(function (key) { return isShown(key); });
	}

	function shownSteps() {
		var list = [];
		for (var s = 1; s <= N; s++) {
			if (stepShown(s)) {
				list.push(s);
			}
		}
		return list;
	}

	function nextStep(from, dir) {
		var s = from + dir;
		while (s > 0 && s <= N && !stepShown(s)) {
			s += dir;
		}
		return Math.max(0, Math.min(N, s));
	}

	function showProgress() {
		var list = shownSteps();
		var at = current === 0 ? 0 : list.indexOf(current) + 1;
		progress.style.setProperty('--paw-progress', (at / list.length * 100) + '%');
		progress.setAttribute('aria-valuemax', String(list.length));
		progress.setAttribute('aria-valuenow', String(at));
		progress.setAttribute('aria-valuetext', at ? format(I18N.stepOf, at, list.length) : '');
	}

	/* ---------- Grooming: dogs only, prices by size, estimated total (display only) ---------- */

	function money(n) {
		var whole = Math.abs(n - Math.round(n)) < 0.005;
		return CFG.currency + n.toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 });
	}

	function priceText(amount, monthly) {
		return amount === null ? I18N.onRequest : money(amount) + (monthly ? ' ' + I18N.perMonth : '');
	}

	/** Each pet's service cards show the price for that pet's size. */
	function showPrices() {
		root.querySelectorAll('[data-paw-pets="stay"] [data-paw-pet]').forEach(function (card) {
			var size = value('pets.' + card.getAttribute('data-paw-pet') + '.dog_size');
			if (size !== 'small' && size !== 'large') {
				return; // Keep the server's "from–to" range until a size is chosen.
			}
			card.querySelectorAll('[data-paw-price]').forEach(function (el) {
				var svc = CFG.services[el.getAttribute('data-paw-price')];
				if (svc) {
					el.textContent = priceText(svc[size], svc.monthly);
					el.classList.toggle('is-request', svc[size] === null);
				}
			});
		});
	}

	function nights(a, b) {
		return isDate(a) && isDate(b) && b > a ? Math.round((new Date(b) - new Date(a)) / 864e5) : 0;
	}

	/** The same sums as Services::estimate(): boarding and the service, for each pet. */
	function estimate() {
		var type = value('booking_type');
		var nightsCount = nights(value('check_in'), value('check_out'));
		var lines = [];
		for (var n = 0; n < petCount(); n++) {
			var name = value('pets.' + n + '.pet_name') || format(I18N.petN, n + 1);
			var size = value('pets.' + n + '.dog_size');
			if ((type === 'boarding' || type === 'both') && nightsCount > 0) {
				var rate = value('pets.' + n + '.pet_type') === 'dog' ? (size ? CFG.rates[size] : null) : CFG.rates.other;
				lines.push({
					label: name + ': ' + format(nightsCount === 1 ? I18N.boardingPetOne : I18N.boardingPetMany, nightsCount),
					amount: rate === null || rate === undefined ? null : rate * nightsCount,
					monthly: false
				});
			}
			var id = value('pets.' + n + '.service');
			var svc = CFG.services[id];
			if (svc && (type === 'grooming' || type === 'both') && isShown('pets.' + n + '.service')) {
				var card = root.querySelector('[data-paw-pets="stay"] [data-paw-pet="' + n + '"] [data-paw-svc="' + id + '"] .paw-svc__name');
				lines.push({ label: name + ': ' + (card ? card.textContent : id), amount: size === 'small' || size === 'large' ? svc[size] : null, monthly: svc.monthly });
			}
		}
		return lines;
	}

	function showEstimate() {
		var box = root.querySelector('[data-paw-estimate]');
		if (!box) {
			return;
		}
		var lines = estimate();
		var known = lines.filter(function (l) { return l.amount !== null; });
		box.hidden = !known.length; // Nothing priced yet: no estimate to show.
		if (!known.length) {
			return;
		}
		var list = box.querySelector('[data-paw-estimate-lines]');
		list.textContent = '';
		lines.forEach(function (l) {
			var li = document.createElement('li');
			var name = document.createElement('span');
			name.textContent = l.label;
			var amount = document.createElement('span');
			amount.textContent = priceText(l.amount, l.monthly);
			li.appendChild(name);
			li.appendChild(amount);
			list.appendChild(li);
		});
		var total = known.reduce(function (sum, l) { return sum + l.amount; }, 0);
		box.querySelector('[data-paw-estimate-total]').textContent = money(total) + (known.length < lines.length ? ' + ' + I18N.plusOnRequest : '');
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
		go(nextStep(current, 1), true);
	});

	prevBtn.addEventListener('click', function () {
		if (current > 0) {
			go(nextStep(current, -1), true);
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
		saveTimer = setTimeout(writeDraft, 500);
	}

	function writeDraft() {
		clearTimeout(saveTimer);
		var values = {};
		Object.keys(CFG.fields).forEach(function (id) {
			var t = CFG.fields[id].type;
			if (!CFG.fields[id].perPet && t !== 'file' && t !== 'signature' && id !== 'signed_date') {
				values[id] = value(id);
			}
		});
		var pets = [];
		for (var n = 0; n < petCount(); n++) {
			pets.push(petValues(n));
		}
		try {
			localStorage.setItem(DRAFT_KEY, JSON.stringify({ t: Date.now(), v: values, pets: pets }));
		} catch (err) { /* Private mode or storage full: drafts are a convenience only. */ }
	}

	function clearDraft() {
		clearTimeout(saveTimer);
		try {
			localStorage.removeItem(DRAFT_KEY);
		} catch (err) { /* Ignore. */ }
	}

	function setValue(key, v) {
		var t = CFG.fields[parse(key).id].type;
		var name = nameOf(key);
		if (t === 'choice' || t === 'yesno' || t === 'service') {
			var r = form.querySelector('input[name="' + name + '"][value="' + CSS.escape(v) + '"]');
			if (r) {
				r.checked = true;
			}
		} else if (t === 'agree') {
			form.querySelector('input[name="' + name + '"]').checked = v === '1';
		} else if (pickers[name]) {
			pickers[name].setDate(v || null, false);
		} else {
			var input = form.querySelector('[name="' + name + '"]');
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
			if (CFG.fields[id] && !CFG.fields[id].perPet && draft.v[id] !== '') {
				setValue(id, String(draft.v[id]));
				any = true;
			}
		});
		var pets = Array.isArray(draft.pets) ? draft.pets : [];
		ensurePets(pets.length);
		pets.forEach(function (pet, n) {
			Object.keys(pet || {}).forEach(function (id) {
				if (CFG.fields[id] && CFG.fields[id].perPet && pet[id] !== '') {
					setValue('pets.' + n + '.' + id, String(pet[id]));
					any = true;
				}
			});
		});
		if (any && draftNote) {
			draftNote.hidden = false;
		}
	}

	if (draftNote) {
		draftNote.querySelector('[data-paw-restart]').addEventListener('click', function () {
			clearDraft();
			ensurePets(1);
			form.reset();
			Object.keys(pickers).forEach(function (id) {
				if (id === 'signed_date') {
					pickers[id].setDate(CFG.today, false);
				} else {
					pickers[id].clear();
				}
			});
			files = [[]];
			renderFiles(0);
			if (pad) {
				pad.clear();
			}
			keysOf(0).concat([1, 2, 3, 4, 5, 6, 7].reduce(function (all, s) { return all.concat(keysOf(s)); }, [])).forEach(function (key) { showError(key, null); });
			draftNote.hidden = true;
			reached = 0;
			refresh();
		});
	}

	/* ---------- Calendar ---------- */

	/** Calendars for the date inputs in scope (the whole form, or a newly added pet card). */
	function setupDates(scope) {
		(scope || root).querySelectorAll('[data-paw-date]').forEach(function (input) {
			if (input.closest('template')) {
				return;
			}
			var id = input.name;
			var past = !!input.closest('[data-field]') && CFG.fields[input.closest('[data-field]').getAttribute('data-field')].past;
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
				minDate: today || past ? null : CFG.today,
				maxDate: past ? CFG.today : null,
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
					refresh();
					saveDraft();
				}
			});
			var alt = pickers[id].altInput;
			if (alt) {
				var label = input.closest('[data-field]').querySelector('.paw-label');
				alt.id = input.id + '-display';
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

	function renderFiles(n) {
		var card = root.querySelector('[data-paw-pets="health"] [data-paw-pet="' + n + '"]');
		var fileList = card && card.querySelector('[data-paw-file-list]');
		if (!fileList) {
			return;
		}
		var list = files[n] || [];
		fileList.textContent = '';
		list.forEach(function (file, i) {
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
				list.splice(i, 1);
				renderFiles(n);
			});
			li.append(name, sz, rm);
			fileList.appendChild(li);
		});
	}

	function allFiles() {
		return files.reduce(function (all, list) { return all.concat(list || []); }, []);
	}

	// One listener for every pet's file input (cards are added later).
	form.addEventListener('change', function (e) {
		var fileInput = e.target;
		if (fileInput.type !== 'file') {
			return;
		}
		var n = +fileInput.closest('[data-paw-pet]').getAttribute('data-paw-pet');
		files[n] = files[n] || [];
		var error = null;
		Array.prototype.forEach.call(fileInput.files, function (file) {
			var ext = (file.name.split('.').pop() || '').toLowerCase();
			var total = allFiles().reduce(function (sum, f) { return sum + f.size; }, 0);
			if (!ALLOWED[ext] || (file.type && file.type !== ALLOWED[ext])) {
				error = I18N.fileType;
			} else if (file.size > CFG.maxBytes) {
				error = I18N.fileSize;
			} else if (total + file.size > CFG.maxTotal) {
				error = I18N.tooLarge;
			} else if (files[n].length >= CFG.maxFiles) {
				error = format(I18N.fileCount, CFG.maxFiles);
			} else {
				files[n].push(file);
			}
		});
		fileInput.value = '';
		renderFiles(n);
		showError('pets.' + n + '.vaccination_files', error);
	});

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
			showError(keyOf(holder), null);
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
		files.forEach(function (list, n) {
			data.delete('vaccination_files_' + n + '[]');
			(list || []).forEach(function (file) { data.append('vaccination_files_' + n + '[]', file, file.name); });
		});
		data.set('signature', pad && !pad.isEmpty() ? pad.toDataURL() : '');
		data.set('submission_id', submissionId);
		if (!creatingAccount()) {
			data.delete('create_account');
			data.delete('account_password');
			data.delete('account_password_confirm');
		}
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
				// WordPress itself answers rest_cookie_invalid_nonce for an expired nonce on a cached page.
				var staleNonce = body.code === 'paw_nonce' || body.code === 'rest_cookie_invalid_nonce';
				if (res.status === 403 && staleNonce && !retried) {
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

	function finish(reference, accountMade) {
		clearDraft();
		root.querySelector('[data-paw-reference]').textContent = reference;
		var est = root.querySelector('[data-paw-estimate]');
		var doneEst = root.querySelector('[data-paw-estimate-done]');
		if (est && doneEst && !est.hidden) {
			doneEst.innerHTML = est.innerHTML;
			doneEst.hidden = false;
		}
		if (accountMade) {
			var note = document.createElement('p');
			note.className = 'paw-done__text';
			note.textContent = I18N.accountReady;
			stepEl('done').appendChild(note);
		}
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
			if (!stepShown(s)) {
				continue;
			}
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
				finish(r.body.reference, r.body.account === true);
				return;
			}
			var fields = r.body && r.body.data && r.body.data.fields;
			if (r.status === 400 && fields) {
				var mapped = {};
				Object.keys(fields).forEach(function (key) { mapped[fromServer(key)] = fields[key]; });
				var ids = Object.keys(mapped).filter(function (key) { return stepOf(key) !== null; });
				ids.sort(function (a, b) { return stepOf(a) - stepOf(b); });
				if (ids.length) {
					go(stepOf(ids[0]), true);
					ids.forEach(function (key) { showError(key, mapped[key]); });
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

	/* ---------- Account: sign in on the owner step ---------- */

	var signinToggle = root.querySelector('[data-paw-signin-toggle]');
	if (signinToggle) {
		var signinBox = root.querySelector('#paw-signin');
		var signinBtn = root.querySelector('[data-paw-signin]');
		var signinError = root.querySelector('[data-paw-signin-error]');
		signinToggle.addEventListener('click', function () {
			signinBox.hidden = !signinBox.hidden;
			signinToggle.setAttribute('aria-expanded', String(!signinBox.hidden));
			if (!signinBox.hidden) {
				root.querySelector('#paw-login-email').focus();
			}
		});
		var signIn = function () {
			var label = signinBtn.textContent;
			signinError.hidden = true;
			signinBtn.disabled = true;
			signinBtn.textContent = I18N.signingIn;
			fetch(ACCOUNT.loginRest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					email: root.querySelector('#paw-login-email').value.trim(),
					password: root.querySelector('#paw-login-password').value
				})
			}).then(function (res) {
				return res.json().catch(function () { return {}; }).then(function (body) {
					if (res.ok && body.reload) {
						// The page's REST nonce belongs to the visitor; reload for the owner's.
						writeDraft();
						try {
							sessionStorage.setItem('paw_resume', '1');
						} catch (err) { /* Ignore. */ }
						location.reload();
						return;
					}
					signinError.textContent = body.message || I18N.signinFailed;
					signinError.hidden = false;
				});
			}).catch(function () {
				signinError.textContent = I18N.network;
				signinError.hidden = false;
			}).then(function () {
				signinBtn.disabled = false;
				signinBtn.textContent = label;
			});
		};
		signinBtn.addEventListener('click', signIn);
		root.querySelector('#paw-login-password').addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				signIn();
			}
		});
	}

	// On a shared computer the next person must not find this owner's details waiting for them.
	var logout = root.querySelector('[data-paw-logout]');
	if (logout) {
		logout.addEventListener('click', clearDraft);
	}

	/** Fills only what is still empty, so anything typed (or restored from the draft) wins. */
	function applyPrefill() {
		var prefill = ACCOUNT.prefill || {};
		Object.keys(prefill).forEach(function (id) {
			if (CFG.fields[id] && !CFG.fields[id].perPet && value(id) === '') {
				setValue(id, String(prefill[id]));
			}
		});
		var pets = Array.isArray(prefill.pets) ? prefill.pets : [];
		if (pets.length && petValues(0).pet_name === '') {
			ensurePets(pets.length);
			pets.forEach(function (pet, n) { setPetValues(n, pet); });
		}
	}

	/* ---------- Start ---------- */

	// The v0.3 draft (owner details) is no longer read: don't leave it on shared computers.
	try {
		localStorage.removeItem('paw_draft_v1');
	} catch (err) { /* Ignore. */ }
	setupDates();
	restoreDraft();
	applyPrefill();
	refresh();
	history.replaceState({ pawStep: 0 }, '', location.pathname + location.search);
	var resume = false;
	try {
		resume = sessionStorage.getItem('paw_resume') === '1';
		sessionStorage.removeItem('paw_resume');
	} catch (err) { /* Ignore. */ }
	go(resume ? 1 : 0, false);
})();
