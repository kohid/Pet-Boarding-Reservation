/*
 * Pawfectly — login page, My Profile and My Bookings: tabs, JSON forms to the account REST routes,
 * resend verification, and owner cancel. Pages are rendered by the server.
 */
(function () {
	'use strict';

	var CFG = window.PAW_ACCOUNT;
	var roots = document.querySelectorAll('[data-paw-acct]');
	if (!CFG || !roots.length) {
		return;
	}
	var I18N = CFG.i18n;

	/** Sign-in and sign-up send no nonce: on a cached page it may be stale, and they need none. */
	var NO_NONCE = { 'account/login': true, 'account/signup': true };

	function call(route, body) {
		var headers = { 'Content-Type': 'application/json' };
		if (!NO_NONCE[route]) {
			headers['X-WP-Nonce'] = CFG.nonce;
		}
		return fetch(CFG.rest + route, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify(body || {})
		}).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (data) {
				return { ok: res.ok, status: res.status, data: data };
			});
		});
	}

	function say(el, text, isError) {
		if (!el) {
			return;
		}
		el.textContent = text || '';
		el.hidden = !text;
		el.classList.toggle('is-error', !!isError);
		el.classList.toggle('is-ok', !isError);
	}

	function clearErrors(form) {
		form.querySelectorAll('[data-paw-field]').forEach(function (f) {
			f.classList.remove('is-invalid');
			var p = f.querySelector('.paw-acct__error');
			p.hidden = true;
			p.textContent = '';
			f.querySelector('input').removeAttribute('aria-invalid');
		});
	}

	function fieldErrors(form, fields) {
		var first = null;
		Object.keys(fields || {}).forEach(function (name) {
			var f = form.querySelector('[data-paw-field="' + name + '"]');
			if (!f) {
				return;
			}
			f.classList.add('is-invalid');
			var p = f.querySelector('.paw-acct__error');
			p.textContent = fields[name];
			p.hidden = false;
			var input = f.querySelector('input');
			input.setAttribute('aria-invalid', 'true');
			first = first || input;
		});
		return first;
	}

	roots.forEach(function (root) {
		/* Tabs: Sign in / Create account. #signup opens the second. */
		var tabs = root.querySelectorAll('[data-paw-tab]');
		function select(name, focus) {
			tabs.forEach(function (t) {
				var on = t.getAttribute('data-paw-tab') === name;
				t.setAttribute('aria-selected', String(on));
				t.tabIndex = on ? 0 : -1;
				root.querySelector('#' + t.getAttribute('aria-controls')).hidden = !on;
				if (on && focus) {
					t.focus();
				}
			});
		}
		if (tabs.length) {
			tabs.forEach(function (t) {
				t.addEventListener('click', function () { select(t.getAttribute('data-paw-tab'), false); });
				t.addEventListener('keydown', function (e) {
					if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
						e.preventDefault();
						select(t.getAttribute('data-paw-tab') === 'signin' ? 'signup' : 'signin', true);
					}
				});
			});
			select(location.hash === '#signup' || root.getAttribute('data-paw-start') === 'signup' ? 'signup' : 'signin', false);
			window.addEventListener('hashchange', function () {
				if (location.hash === '#signup') {
					select('signup', true);
				}
			});
		}

		/* JSON forms. */
		root.querySelectorAll('form[data-paw-form]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var btn = form.querySelector('button[type=submit]');
				var msg = form.querySelector('[data-paw-msg]');
				var body = {};
				form.querySelectorAll('input[name]').forEach(function (i) { body[i.name] = i.type === 'checkbox' ? (i.checked ? '1' : '') : i.value; });
				clearErrors(form);
				say(msg, '');
				if ('confirm' in body && body.confirm !== body.password) {
					fieldErrors(form, { confirm: I18N.mismatch }).focus();
					return;
				}
				btn.disabled = true;
				var label = btn.textContent;
				btn.textContent = I18N.saving;
				call(form.getAttribute('data-paw-form'), body).then(function (r) {
					if (r.ok && r.data.redirect && root.hasAttribute('data-paw-start')) {
						location.assign(r.data.redirect);
						return;
					}
					if (r.ok && r.data.reload) {
						location.reload();
						return;
					}
					if (r.ok) {
						say(msg, r.data.message || '', false);
						if (form.getAttribute('data-paw-form') === 'account/password') {
							form.reset();
						}
						return;
					}
					var first = fieldErrors(form, r.data.data && r.data.data.fields);
					say(msg, first ? '' : (r.data.message || I18N.failed), true);
					if (first) {
						first.focus();
					}
				}).catch(function () {
					say(msg, I18N.network, true);
				}).then(function () {
					btn.disabled = false;
					btn.textContent = label;
				});
			});
		});

		/* Send a new verification link. */
		var resend = root.querySelector('[data-paw-resend]');
		if (resend) {
			resend.addEventListener('click', function () {
				resend.disabled = true;
				call('account/resend').then(function (r) {
					resend.textContent = r.data.message || I18N.failed;
				}).catch(function () {
					resend.textContent = I18N.network;
				});
			});
		}

		/* Owner cancels a reservation or an event sign-up. */
		root.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-paw-cancel], [data-paw-cancel-signup]');
			var signup = btn && btn.hasAttribute('data-paw-cancel-signup');
			if (!btn || !window.confirm(signup ? I18N.confirmCancelSignup : I18N.confirmCancel)) {
				return;
			}
			var row = btn.closest('[data-paw-row]');
			var msg = btn.closest('.paw-acct__card').querySelector('[data-paw-msg]');
			btn.disabled = true;
			var path = signup ? 'account/signups/' + btn.getAttribute('data-paw-cancel-signup') : 'account/reservations/' + btn.getAttribute('data-paw-cancel');
			call(path + '/cancel').then(function (r) {
				if (r.ok) {
					var badge = row.querySelector('[data-paw-status]');
					badge.className = 'paw-badge paw-badge--' + r.data.status;
					badge.textContent = r.data.label;
					btn.remove();
					return;
				}
				btn.disabled = false;
				say(msg, r.data.message || I18N.failed, true);
			}).catch(function () {
				btn.disabled = false;
				say(msg, I18N.network, true);
			});
		});
	});
})();
