/**
 * Pawfectly — the event sign-up form: dog cards, the live total, sending with a fresh nonce,
 * errors under their fields, and the thank-you panel. Text from the server is set with
 * textContent; the only HTML used is the page's own.
 */
(function () {
	'use strict';

	function uuid() {
		if (window.crypto && crypto.randomUUID) {
			return crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
		});
	}

	function setup(form) {
		var cfg = {};
		try { cfg = JSON.parse(form.getAttribute('data-config') || '{}'); } catch (err) { return; }
		var t = cfg.i18n || {};
		var dogs = form.querySelector('[data-paw-su-dogs]');
		var tpl = form.querySelector('[data-paw-su-dog-template]');
		var add = form.querySelector('[data-paw-su-add]');
		var total = form.querySelector('[data-paw-su-total]');
		var alertBox = form.querySelector('[data-paw-su-alert]');
		var thanks = form.parentNode.querySelector('[data-paw-su-thanks]');
		var submission = uuid();

		function format(str) {
			var args = Array.prototype.slice.call(arguments, 1);
			var i = 0;
			return String(str).replace(/%(\d)\$[ds]|%[ds]/g, function (m, n) { return n ? args[n - 1] : args[i++]; });
		}

		function money(n) {
			var whole = Math.abs(n - Math.round(n)) < 0.005;
			return cfg.currency + n.toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 });
		}

		function cards() { return Array.prototype.slice.call(dogs.querySelectorAll('[data-paw-su-dog]')); }

		/** A dog counts once any of its fields has text (blank cards are not sent). */
		function filledDogs() {
			return cards().filter(function (c) {
				return Array.prototype.some.call(c.querySelectorAll('[data-paw-su-dog-field]'), function (i) { return i.value.trim() !== ''; });
			}).length;
		}

		function renumber() {
			cards().forEach(function (card, n) {
				card.querySelector('[data-paw-su-dog-title]').textContent = format(t.dogN, n + 1);
				card.querySelector('[data-paw-su-remove]').hidden = n === 0;
				card.querySelectorAll('[data-paw-su-key]').forEach(function (f) {
					f.setAttribute('data-paw-su-key', f.getAttribute('data-paw-su-key').replace(/^dogs\.[^.]+\./, 'dogs.' + n + '.'));
				});
			});
			add.hidden = cards().length >= cfg.maxDogs;
		}

		function showTotal() {
			var n = Math.max(1, filledDogs());
			var coupon = form.querySelector('[name=coupon]');
			if (cfg.free) {
				total.textContent = t.free;
			} else if (cfg.price === null) {
				total.textContent = t.onRequest;
			} else {
				total.textContent = format(t.total, n, money(cfg.price), money(cfg.price * n)) + (coupon && coupon.value.trim() ? ' · ' + t.codeNote : '');
			}
		}

		add.addEventListener('click', function () {
			var holder = document.createElement('div');
			holder.innerHTML = tpl.innerHTML.replace(/__N__/g, String(cards().length)).replace(/__NUM__/g, String(cards().length + 1));
			var card = holder.firstElementChild;
			dogs.appendChild(card);
			renumber();
			card.querySelector('input').focus();
			showTotal();
		});
		dogs.addEventListener('click', function (e) {
			if (e.target.closest('[data-paw-su-remove]')) {
				e.target.closest('[data-paw-su-dog]').remove();
				renumber();
				showTotal();
				add.focus();
			}
		});
		form.addEventListener('input', showTotal);

		function clearErrors() {
			form.querySelectorAll('.is-invalid').forEach(function (f) { f.classList.remove('is-invalid'); });
			form.querySelectorAll('[data-paw-su-err]').forEach(function (e) { e.remove(); });
			alertBox.hidden = true;
		}

		function showErrors(fields) {
			var first = null;
			Object.keys(fields || {}).forEach(function (key) {
				var box = form.querySelector('[data-paw-su-key="' + key + '"]');
				if (!box) {
					return;
				}
				box.classList.add('is-invalid');
				var msg = document.createElement('p');
				msg.className = 'paw-event__su-error';
				msg.setAttribute('data-paw-su-err', '');
				msg.textContent = fields[key];
				box.appendChild(msg);
				first = first || box;
			});
			if (first) {
				first.scrollIntoView({ block: 'center', behavior: 'smooth' });
				var input = first.querySelector('input:not([disabled]), textarea');
				if (input) {
					input.focus({ preventScroll: true });
				}
			}
		}

		function collect() {
			var checked = form.querySelector('[name=session]:checked');
			var v = function (name) { var el = form.querySelector('[name=' + name + ']'); return el ? el.value.trim() : ''; };
			return {
				submission_id: submission,
				session: checked ? checked.value : '',
				first_name: v('first_name'),
				last_name: v('last_name'),
				email: v('email'),
				phone: v('phone'),
				coupon: v('coupon'),
				notes: v('notes'),
				website: v('website'),
				agree: form.querySelector('[name=agree]').checked ? '1' : '',
				dogs: cards().map(function (c) {
					var d = {};
					c.querySelectorAll('[data-paw-su-dog-field]').forEach(function (i) { d[i.getAttribute('data-paw-su-dog-field')] = i.value.trim(); });
					return d;
				})
			};
		}

		function done(d) {
			thanks.querySelector('[data-paw-su-ref]').textContent = d.reference;
			var list = thanks.querySelector('[data-paw-su-summary]');
			list.textContent = '';
			[['event', d.summary.event], ['when', d.summary.when], ['session', d.summary.session], ['dogs', d.summary.dogs], ['total', d.summary.total_text]].forEach(function (row) {
				if (!row[1]) {
					return;
				}
				var dd = document.createElement('dd');
				dd.textContent = row[1];
				dd.setAttribute('data-paw-su-row', row[0]);
				list.appendChild(dd);
			});
			var cal = thanks.querySelector('[data-paw-su-cal]');
			cal.textContent = '';
			[['Google Calendar', d.calendar.google, true], ['Outlook', d.calendar.outlook, true], ['Apple / iCal (.ics)', d.calendar.ics, false]].forEach(function (l) {
				var a = document.createElement('a');
				a.href = l[1];
				a.textContent = l[0];
				if (l[2]) {
					a.target = '_blank';
					a.rel = 'noopener';
				}
				cal.appendChild(a);
			});
			form.hidden = true;
			thanks.hidden = false;
			thanks.focus();
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = form.querySelector('button[type=submit]');
			var label = btn.textContent;
			clearErrors();
			btn.disabled = true;
			btn.textContent = t.sending;
			// Signed-in people get their own nonce in the page; visitors' pages may be cached.
			(window.PAW_SIGNUP_NONCE ? Promise.resolve({ nonce: window.PAW_SIGNUP_NONCE }) : fetch(cfg.nonceUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }))
				.then(function (n) {
					return fetch(cfg.route, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': n.nonce },
						body: JSON.stringify(collect())
					});
				})
				.then(function (res) {
					return res.json().catch(function () { return {}; }).then(function (body) {
						if (res.ok) {
							done(body);
							return;
						}
						alertBox.textContent = body.message || t.fixErrors;
						alertBox.hidden = false;
						showErrors(body.data && body.data.fields);
					});
				})
				.catch(function () {
					alertBox.textContent = t.failed;
					alertBox.hidden = false;
				})
				.then(function () {
					btn.disabled = false;
					btn.textContent = label;
				});
		});

		renumber();
		showTotal();
	}

	document.querySelectorAll('[data-paw-signup]').forEach(setup);
})();
