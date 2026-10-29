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
			f.querySelector('input, select, textarea').removeAttribute('aria-invalid');
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
			var input = f.querySelector('input, select, textarea');
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
				form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (i) { body[i.name] = i.type === 'checkbox' ? (i.checked ? '1' : '') : i.value; });
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

		root.querySelectorAll('[data-paw-pets]').forEach(setupPets);
	});

	/* ---------- My Pets: the saved pets, edited one at a time; every change saves the list. ---------- */

	function setupPets(box) {
		var P = JSON.parse(box.getAttribute('data-config') || '{}');
		var T = P.i18n || {};
		var pets = (P.pets || []).map(function (p) { return { uid: p.uid, values: p.values || {} }; });
		var list = box.querySelector('[data-paw-petlist]');
		var editorBox = box.querySelector('[data-paw-pet-editor]');
		var msg = box.querySelector('[data-paw-pets-msg]');
		var addBtn = box.querySelector('[data-paw-pet-add]');
		var fields = {};
		P.groups.forEach(function (g) { g.fields.forEach(function (f) { fields[f.id] = f; }); });
		var editing = -1; // Index in pets, or pets.length for a new one.
		var busy = false; // A save is on its way: one at a time, so a quick second Remove can't resend the first.

		function el(tag, cls, text) {
			var n = document.createElement(tag);
			if (cls) {
				n.className = cls;
			}
			if (text !== undefined) {
				n.textContent = text;
			}
			return n;
		}

		function optionLabel(id, value) {
			var o = fields[id] && fields[id].options;
			return o && o[value] ? o[value] : '';
		}

		function renderList() {
			list.textContent = '';
			if (!pets.length) {
				list.appendChild(el('li', 'paw-acct__muted', T.none));
			}
			pets.forEach(function (pet, i) {
				var li = el('li', 'paw-acct__pet');
				li.setAttribute('data-paw-pet-row', String(i));
				var main = el('div', 'paw-acct__pet-main');
				main.appendChild(el('strong', 'paw-acct__pet-name', pet.values.pet_name || T.newPet));
				var about = [optionLabel('pet_type', pet.values.pet_type), pet.values.pet_breed, pet.values.pet_age].filter(Boolean).join(' · ');
				if (about) {
					main.appendChild(el('span', 'paw-acct__muted', about));
				}
				li.appendChild(main);
				var actions = el('div', 'paw-acct__pet-actions');
				var edit = el('button', 'paw-acct__link', T.edit);
				edit.type = 'button';
				edit.setAttribute('data-paw-pet-edit', String(i));
				var remove = el('button', 'paw-acct__link paw-acct__link--danger', T.remove);
				remove.type = 'button';
				remove.setAttribute('data-paw-pet-remove', String(i));
				actions.append(edit, remove);
				li.appendChild(actions);
				list.appendChild(li);
			});
			addBtn.textContent = T.add;
			addBtn.hidden = pets.length >= P.max || editing !== -1;
		}

		function shown(f, values) {
			return Object.keys(f.showIf || {}).every(function (target) {
				var want = f.showIf[target];
				return (Array.isArray(want) ? want : [want]).indexOf(values[target] || '') !== -1;
			});
		}

		function editorValues() {
			var out = {};
			editorBox.querySelectorAll('[name]').forEach(function (i) { out[i.name] = i.value.trim(); });
			return out;
		}

		function refreshShown() {
			var values = editorValues();
			editorBox.querySelectorAll('[data-paw-field]').forEach(function (wrap) {
				wrap.hidden = !shown(fields[wrap.getAttribute('data-paw-field')], values);
			});
		}

		function control(f, value) {
			var id = 'paw-pet-' + f.id;
			var wrap = el('div', 'paw-acct__field' + (f.type === 'textarea' ? ' paw-acct__field--wide' : ''));
			wrap.setAttribute('data-paw-field', f.id);
			var label = el('label', '', f.label);
			label.htmlFor = id;
			wrap.appendChild(label);
			var input;
			var keys = Object.keys(f.options || {});
			if (f.type === 'textarea') {
				input = el('textarea');
				input.rows = 3;
			} else if (keys.length) {
				input = el('select');
				input.appendChild(el('option', '', '—')).value = '';
				keys.forEach(function (k) {
					var o = el('option', '', f.options[k]);
					o.value = k;
					input.appendChild(o);
				});
			} else {
				input = el('input');
				input.type = 'text';
				if (f.placeholder) {
					input.placeholder = f.placeholder;
				}
			}
			input.id = id;
			input.name = f.id;
			if (f.max) {
				input.maxLength = f.max;
			}
			input.value = value || '';
			wrap.appendChild(input);
			var err = el('p', 'paw-acct__error');
			err.setAttribute('role', 'alert');
			err.hidden = true;
			wrap.appendChild(err);
			return wrap;
		}

		function openEditor(i) {
			editing = i;
			var values = i < pets.length ? pets[i].values : {};
			editorBox.textContent = '';
			var form = el('form', 'paw-acct__pet-editor');
			form.noValidate = true;
			P.groups.forEach(function (g) {
				var set = el('fieldset', 'paw-acct__pet-group');
				set.appendChild(el('legend', 'paw-acct__subhead', g.title));
				var grid = el('div', 'paw-acct__grid paw-acct__grid--2');
				g.fields.forEach(function (f) { grid.appendChild(control(f, values[f.id])); });
				set.appendChild(grid);
				form.appendChild(set);
			});
			var formMsg = el('p', 'paw-acct__msg');
			formMsg.setAttribute('data-paw-msg', '');
			formMsg.setAttribute('role', 'status');
			formMsg.hidden = true;
			form.appendChild(formMsg);
			var actions = el('div', 'paw-acct__actions');
			var save = el('button', 'paw-acct__btn', T.save);
			save.type = 'submit';
			var cancel = el('button', 'paw-acct__link', T.cancel);
			cancel.type = 'button';
			cancel.setAttribute('data-paw-pet-cancel', '');
			actions.append(save, cancel);
			form.appendChild(actions);
			form.addEventListener('change', refreshShown);
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var next = pets.slice();
				var values2 = {};
				var all = editorValues();
				Object.keys(all).forEach(function (k) {
					if (all[k] !== '' && shown(fields[k], all)) {
						values2[k] = all[k];
					}
				});
				var pet = { uid: editing < pets.length ? pets[editing].uid : '', values: values2 };
				if (editing < pets.length) {
					next[editing] = pet;
				} else {
					next.push(pet);
				}
				saveList(next, form, save);
			});
			editorBox.appendChild(form);
			editorBox.hidden = false;
			refreshShown();
			renderList();
			// Inside the pet's own row (a list may only hold rows), or under the list for a new pet.
			var row = list.querySelector('[data-paw-pet-row="' + i + '"]');
			if (row) {
				row.appendChild(editorBox);
			} else {
				list.after(editorBox);
			}
			form.querySelector('input, select, textarea').focus();
		}

		/** Closes the editor; keyboard focus goes back to that pet's Edit button (or Add). */
		function closeEditor() {
			var was = editing;
			editing = -1;
			editorBox.hidden = true;
			editorBox.textContent = '';
			list.after(editorBox);
			renderList();
			var back = list.querySelector('[data-paw-pet-edit="' + was + '"]') || (addBtn.hidden ? null : addBtn);
			if (back) {
				back.focus();
			}
		}

		/** Sends the whole list; on errors, shows them in the open editor and keeps what was saved. */
		function saveList(next, form, btn) {
			busy = true;
			var label = btn ? btn.textContent : '';
			if (btn) {
				btn.disabled = true;
				btn.textContent = I18N.saving;
			}
			if (form) {
				clearErrors(form);
			}
			say(msg, '');
			return call('account/pets', { pets: next }).then(function (r) {
				if (r.ok) {
					pets = r.data.pets;
					closeEditor();
					say(msg, r.data.message || '', false);
					return;
				}
				var fieldsErr = (r.data.data && r.data.data.fields) || {};
				var own = {};
				var at = editing < 0 ? -1 : editing;
				Object.keys(fieldsErr).forEach(function (key) {
					var m = /^pets\.(\d+)\.(.+)$/.exec(key);
					if (m && +m[1] === at) {
						own[m[2]] = fieldsErr[key];
					}
				});
				var first = form ? fieldErrors(form, own) : null;
				if (first) {
					first.focus();
				} else {
					say(msg, r.data.message || I18N.failed, true);
				}
			}).catch(function () {
				say(msg, I18N.network, true);
			}).then(function () {
				busy = false;
				if (btn) {
					btn.disabled = false;
					btn.textContent = label;
				}
			});
		}

		box.addEventListener('click', function (e) {
			if (busy) {
				return;
			}
			var edit = e.target.closest('[data-paw-pet-edit]');
			var remove = e.target.closest('[data-paw-pet-remove]');
			if (edit) {
				openEditor(+edit.getAttribute('data-paw-pet-edit'));
			} else if (remove) {
				var i = +remove.getAttribute('data-paw-pet-remove');
				if (window.confirm(T.confirmRemove.replace('%s', pets[i].values.pet_name || T.newPet))) {
					if (editing !== -1) {
						closeEditor();
					}
					saveList(pets.filter(function (p, n) { return n !== i; }), null, null);
				}
			} else if (e.target.closest('[data-paw-pet-cancel]')) {
				closeEditor();
			} else if (e.target.closest('[data-paw-pet-add]')) {
				openEditor(pets.length);
			}
		});

		if (!P.saved && pets.length) {
			say(msg, T.unsaved, false);
		}
		renderList();
	}
})();
