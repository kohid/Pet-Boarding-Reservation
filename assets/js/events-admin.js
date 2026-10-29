/*
 * Pawfectly — dashboard Events: the list, the Add / Edit editor with its repeatable sections
 * (add, remove, Move up / Move down, drag), the emoji fields, the cover photo, and Settings →
 * Event categories. Everything goes through the staff routes; staff-typed text is set with
 * textContent or .value, never as HTML.
 */
(function () {
	'use strict';

	var CFG = window.PAW_DASH;
	var root = document.querySelector('[data-paw-dash]');
	if (!CFG || !root || !CFG.events) {
		return;
	}
	var E = CFG.events;
	var T = E.i18n;
	var list = root.querySelector('[data-paw-ev-list]');
	var form = root.querySelector('[data-paw-ev-form]');
	var SECTIONS = ['sessions', 'includes', 'goodies', 'good_to_know'];
	var state = { tab: 'upcoming', q: '', page: 1, pages: 1, id: 0, dirty: false };
	var uid = 0;

	function $(sel, scope) { return (scope || root).querySelector(sel); }
	function $$(sel, scope) { return Array.prototype.slice.call((scope || root).querySelectorAll(sel)); }

	function el(tag, cls, text) {
		var node = document.createElement(tag);
		if (cls) {
			node.className = cls;
		}
		if (text !== undefined) {
			node.textContent = text;
		}
		return node;
	}

	/** fetch a staff route; resolves {ok, status, body}. */
	function api(path, options) {
		options = options || {};
		var init = {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: Object.assign({ 'X-WP-Nonce': CFG.nonce }, options.json ? { 'Content-Type': 'application/json' } : {})
		};
		if (options.json) {
			init.body = JSON.stringify(options.json);
		}
		return fetch(CFG.rest + path, init).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (body) {
				return { ok: res.ok, status: res.status, body: body };
			});
		});
	}

	function url(params) {
		var u = new URL(location.href);
		u.searchParams.set('paw_view', 'events');
		u.searchParams.delete('event');
		Object.keys(params || {}).forEach(function (k) { u.searchParams.set(k, params[k]); });
		return u.toString();
	}

	/* ---------- List ---------- */

	var listToken = 0;

	function loadList() {
		var token = ++listToken;
		var q = new URLSearchParams({ tab: state.tab, q: state.q, page: String(state.page) });
		api('staff/events?' + q.toString()).then(function (r) {
			if (token !== listToken) {
				return;
			}
			var rows = $('[data-paw-ev-rows]', list);
			rows.textContent = '';
			var data = r.ok ? r.body : { rows: [], pages: 1 };
			state.pages = data.pages || 1;
			data.rows.forEach(function (ev) {
				var li = el('li', 'paw-dash__ev-item');
				li.style.setProperty('--paw-ev', ev.color || '#ff7f50');
				li.appendChild(el('span', 'paw-dash__ev-item-emoji', ev.emoji || '🐾'));
				var main = el('div', 'paw-dash__ev-item-main');
				var open = el('button', 'paw-dash__ev-item-title', ev.title);
				open.type = 'button';
				open.setAttribute('data-paw-ev-open', String(ev.id));
				main.appendChild(open);
				main.appendChild(el('span', 'paw-dash__ev-item-when', ev.when));
				var meta = el('span', 'paw-dash__ev-item-meta');
				[ev.venue, ev.price_text].filter(Boolean).forEach(function (t) { meta.appendChild(el('span', '', t)); });
				if (ev.category_name) {
					meta.appendChild(el('span', 'paw-dash__ev-chip', ev.category_name));
				}
				main.appendChild(meta);
				li.appendChild(main);
				li.appendChild(el('span', 'paw-dash__badge paw-dash__badge--' + (ev.status === 'publish' ? 'confirmed' : 'pending'), ev.status === 'publish' ? T.published : T.draft));
				var actions = el('span', 'paw-dash__ev-item-actions');
				var view = el('a', 'paw-dash__link', T.view);
				view.href = ev.url;
				view.target = '_blank';
				view.rel = 'noopener';
				var dup = el('button', 'paw-dash__link', T.duplicate);
				dup.type = 'button';
				dup.setAttribute('data-paw-ev-duplicate', String(ev.id));
				actions.append(view, dup);
				li.appendChild(actions);
				rows.appendChild(li);
			});
			$('[data-paw-ev-empty]', list).hidden = data.rows.length > 0;
			$('[data-paw-ev-prev-page]', list).disabled = state.page <= 1;
			$('[data-paw-ev-next-page]', list).disabled = state.page >= state.pages;
		});
	}

	$$('[data-paw-ev-tab]', list).forEach(function (b) {
		b.addEventListener('click', function () {
			state.tab = b.getAttribute('data-paw-ev-tab');
			state.page = 1;
			$$('[data-paw-ev-tab]', list).forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
			loadList();
		});
	});
	var searchTimer = null;
	$('[data-paw-ev-search]', list).addEventListener('input', function (e) {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			state.q = e.target.value.trim();
			state.page = 1;
			loadList();
		}, 300);
	});
	$('[data-paw-ev-prev-page]', list).addEventListener('click', function () { state.page--; loadList(); });
	$('[data-paw-ev-next-page]', list).addEventListener('click', function () { state.page++; loadList(); });
	$('[data-paw-ev-new]', list).addEventListener('click', function () { openEditor('new', true); });
	list.addEventListener('click', function (e) {
		var open = e.target.closest('[data-paw-ev-open]');
		if (open) {
			openEditor(open.getAttribute('data-paw-ev-open'), true);
			return;
		}
		var dup = e.target.closest('[data-paw-ev-duplicate]');
		if (dup) {
			dup.disabled = true;
			api('staff/events/' + dup.getAttribute('data-paw-ev-duplicate') + '/duplicate', { method: 'POST' }).then(function (r) {
				dup.disabled = false;
				if (r.ok) {
					openEditor(String(r.body.id), true);
				}
			});
		}
	});

	/* ---------- Editor: rows ---------- */

	function rowsOf(name) { return $('[data-paw-ev-rows-for="' + name + '"]', form); }

	function refreshSection(name) {
		var n = rowsOf(name).children.length;
		$('[data-paw-ev-add="' + name + '"]', form).hidden = n >= E.limits[name];
		$('[data-paw-ev-section="' + name + '"] [data-paw-ev-none]', form).hidden = n > 0;
		var rows = rowsOf(name).children;
		Array.prototype.forEach.call(rows, function (row, i) {
			$('[data-paw-ev-up]', row).disabled = i === 0;
			$('[data-paw-ev-down]', row).disabled = i === rows.length - 1;
		});
	}

	function addRow(name, values) {
		var tpl = $('[data-paw-ev-row-template="' + name + '"]', form);
		var holder = document.createElement('div');
		holder.innerHTML = tpl.innerHTML.replace(/__ID__/g, 'r' + (++uid));
		var row = holder.firstElementChild;
		row.draggable = false; // Only the handle starts a drag, so text in the fields stays selectable.
		values = values || {};
		$$('[data-paw-ev-row-field]', row).forEach(function (input) {
			var key = input.getAttribute('data-paw-ev-row-field');
			input.value = values[key] !== undefined ? values[key] : (key === 'emoji' && name === 'good_to_know' && !values.text ? '🐾' : '');
		});
		rowsOf(name).appendChild(row);
		refreshSection(name);
		return row;
	}

	form.addEventListener('click', function (e) {
		var add = e.target.closest('[data-paw-ev-add]');
		if (add) {
			var row = addRow(add.getAttribute('data-paw-ev-add'));
			var first = row.querySelector('input:not(.paw-dash__ev-emoji-input)') || row.querySelector('input');
			first.focus();
			dirty();
			return;
		}
		var row2 = e.target.closest('[data-paw-ev-row]');
		if (!row2) {
			return;
		}
		var name = row2.parentNode.getAttribute('data-paw-ev-rows-for');
		if (e.target.closest('[data-paw-ev-remove]')) {
			row2.remove();
		} else if (e.target.closest('[data-paw-ev-up]') && row2.previousElementSibling) {
			row2.parentNode.insertBefore(row2, row2.previousElementSibling);
			$('[data-paw-ev-up]', row2).focus();
		} else if (e.target.closest('[data-paw-ev-down]') && row2.nextElementSibling) {
			row2.parentNode.insertBefore(row2.nextElementSibling, row2);
			$('[data-paw-ev-down]', row2).focus();
		} else {
			return;
		}
		refreshSection(name);
		dirty();
	});

	/* Drag to reorder, within a section. */
	var dragging = null;
	form.addEventListener('mousedown', function (e) {
		var handle = e.target.closest('[data-paw-ev-drag]');
		if (handle) {
			handle.closest('[data-paw-ev-row]').draggable = true;
		}
	});
	form.addEventListener('dragstart', function (e) {
		var row = e.target.closest && e.target.closest('[data-paw-ev-row]');
		if (!row || !row.draggable) {
			return;
		}
		dragging = row;
		row.classList.add('is-dragging');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', '');
	});
	form.addEventListener('dragover', function (e) {
		if (!dragging) {
			return;
		}
		var over = e.target.closest && e.target.closest('[data-paw-ev-row]');
		if (!over || over === dragging || over.parentNode !== dragging.parentNode) {
			return;
		}
		e.preventDefault();
		var r = over.getBoundingClientRect();
		over.parentNode.insertBefore(dragging, e.clientY > r.top + r.height / 2 ? over.nextElementSibling : over);
	});
	form.addEventListener('drop', function (e) {
		if (dragging) {
			e.preventDefault();
		}
	});
	form.addEventListener('dragend', function () {
		if (!dragging) {
			return;
		}
		dragging.classList.remove('is-dragging');
		dragging.draggable = false;
		refreshSection(dragging.parentNode.getAttribute('data-paw-ev-rows-for'));
		dragging = null;
		dirty();
	});

	/* An emoji pick replaces the field's emoji instead of adding to it. */
	form.addEventListener('click', function (e) {
		var trigger = e.target.closest('[data-emoji-trigger]');
		var target = trigger && document.querySelector(trigger.getAttribute('data-emoji-target'));
		if (target) {
			target.setSelectionRange(0, target.value.length);
		}
	}, true);

	/* ---------- Editor: fill, collect, save ---------- */

	function field(key) { return $('[data-paw-ev-field="' + key + '"]', form); }

	function categoryOptions(selected) {
		var select = field('category');
		select.textContent = '';
		var none = el('option', '', T.noCategory);
		none.value = '';
		select.appendChild(none);
		E.categories.forEach(function (c) {
			var o = el('option', '', c.name);
			o.value = c.slug;
			select.appendChild(o);
		});
		select.value = selected || '';
	}

	function setPhoto(id, src) {
		field('photo_id').value = String(id || 0);
		var img = $('[data-paw-ev-photo-img]', form);
		img.hidden = !id;
		if (id && src) {
			img.src = src;
		} else {
			img.removeAttribute('src');
		}
		$('[data-paw-ev-photo-remove]', form).hidden = !id;
	}

	function fill(ev) {
		['title', 'tagline', 'emoji', 'start_date', 'start_time', 'end_date', 'end_time', 'venue', 'address', 'price', 'ticket_url', 'note', 'description'].forEach(function (k) {
			field(k).value = ev[k] || '';
		});
		field('free').checked = !!ev.free;
		categoryOptions(ev.category);
		setPhoto(ev.photo_id, ev.photo_url);
		var sections = ev.sections || E.defaults;
		Object.keys(E.defaults).forEach(function (name) {
			var box = $('[data-paw-ev-section="' + name + '"]', form);
			$$('[data-paw-ev-part]', box).forEach(function (input) {
				var part = input.getAttribute('data-paw-ev-part');
				input.value = (sections[name] && sections[name][part] !== undefined) ? sections[name][part] : E.defaults[name][part];
			});
		});
		SECTIONS.forEach(function (name) {
			rowsOf(name).textContent = '';
			((sections[name] && sections[name].rows) || []).forEach(function (row) { addRow(name, row); });
			refreshSection(name);
		});
		var published = ev.status === 'publish';
		$('[data-paw-ev-heading]', form).textContent = ev.id ? (ev.title || T.editEvent) : T.addEvent;
		var badge = $('[data-paw-ev-status]', form);
		badge.hidden = !ev.id;
		badge.className = 'paw-dash__badge paw-dash__badge--' + (published ? 'confirmed' : 'pending');
		badge.textContent = published ? T.published : T.draft;
		$('[data-paw-ev-save="publish"]', form).textContent = published ? T.update : T.publish;
		$('[data-paw-ev-save="draft"]', form).textContent = published ? T.toDraft : T.saveDraft; // On a live event, drafting takes it offline.
		$('[data-paw-ev-preview]', form).hidden = !ev.id;
		$('[data-paw-ev-preview]', form).href = ev.preview_url || '#';
		$('[data-paw-ev-delete]', form).hidden = !ev.id;
		clearErrors();
		$('[data-paw-ev-msg]', form).hidden = true;
		state.dirty = false;
	}

	function collect(status) {
		var out = { status: status, free: field('free').checked, sections: {} };
		['title', 'tagline', 'emoji', 'category', 'photo_id', 'start_date', 'start_time', 'end_date', 'end_time', 'venue', 'address', 'price', 'ticket_url', 'note', 'description'].forEach(function (k) {
			out[k] = field(k).value.trim();
		});
		Object.keys(E.defaults).forEach(function (name) {
			var sec = {};
			$$('[data-paw-ev-part]', $('[data-paw-ev-section="' + name + '"]', form)).forEach(function (input) {
				sec[input.getAttribute('data-paw-ev-part')] = input.value.trim();
			});
			if (SECTIONS.indexOf(name) >= 0) {
				sec.rows = Array.prototype.map.call(rowsOf(name).children, function (row) {
					var r = {};
					$$('[data-paw-ev-row-field]', row).forEach(function (input) { r[input.getAttribute('data-paw-ev-row-field')] = input.value.trim(); });
					return r;
				});
			}
			out.sections[name] = sec;
		});
		return out;
	}

	function clearErrors() {
		$$('.is-invalid', form).forEach(function (f) { f.classList.remove('is-invalid'); });
		$$('.paw-dash__error[data-paw-ev-err]', form).forEach(function (s) { s.remove(); });
		$$('[data-paw-ev-section-error]', form).forEach(function (p) { p.hidden = true; });
	}

	/** Server error keys ("title", "sections.sessions.1.to", "sections.goodies") to their fields. */
	function showErrors(fields) {
		var first = null;
		Object.keys(fields || {}).forEach(function (key) {
			var m = /^sections\.([a-z_]+)\.(\d+)\.([a-z_]+)$/.exec(key);
			var box = null;
			if (m) {
				var row = rowsOf(m[1]) && rowsOf(m[1]).children[+m[2]];
				box = row ? $('[data-paw-ev-key="' + m[3] + '"]', row) : null;
			} else if (/^sections\.[a-z_]+$/.test(key)) {
				var p = $('[data-paw-ev-section="' + key.split('.')[1] + '"] [data-paw-ev-section-error]', form);
				if (p) {
					p.textContent = fields[key];
					p.hidden = false;
					first = first || p;
				}
				return;
			} else {
				box = $('[data-paw-ev-key="' + key + '"]', form);
			}
			if (!box) {
				return;
			}
			box.classList.add('is-invalid');
			var msg = el('small', 'paw-dash__error', fields[key]);
			msg.setAttribute('data-paw-ev-err', '');
			box.appendChild(msg);
			first = first || box;
		});
		if (first) {
			first.scrollIntoView({ block: 'center', behavior: 'smooth' });
			var input = first.querySelector && first.querySelector('input, select, textarea');
			if (input) {
				input.focus({ preventScroll: true });
			}
		}
	}

	function save(status) {
		var buttons = $$('[data-paw-ev-save]', form);
		buttons.forEach(function (b) { b.disabled = true; });
		clearErrors();
		var msg = $('[data-paw-ev-msg]', form);
		msg.hidden = true;
		api(state.id ? 'staff/events/' + state.id : 'staff/events', { method: 'POST', json: collect(status) }).then(function (r) {
			if (r.ok) {
				state.id = r.body.id;
				fill(r.body.event);
				history.replaceState({ pawView: 'events', pawEvent: String(state.id) }, '', url({ event: String(state.id) }));
				var saved = $('[data-paw-ev-saved]', form);
				saved.textContent = T.saved;
				setTimeout(function () { saved.textContent = ''; }, 2500);
			} else {
				msg.textContent = (r.body && r.body.message) || CFG.i18n.failed;
				msg.hidden = false;
				showErrors(r.body && r.body.data && r.body.data.fields);
			}
		}).catch(function () {
			msg.textContent = CFG.i18n.failed;
			msg.hidden = false;
		}).then(function () {
			buttons.forEach(function (b) { b.disabled = false; });
		});
	}

	$$('[data-paw-ev-save]', form).forEach(function (b) {
		b.addEventListener('click', function () { save(b.getAttribute('data-paw-ev-save')); });
	});
	$('[data-paw-ev-delete]', form).addEventListener('click', function () {
		if (!state.id || !window.confirm(T.confirmDelete)) {
			return;
		}
		api('staff/events/' + state.id, { method: 'DELETE' }).then(function (r) {
			if (r.ok) {
				state.dirty = false;
				showList(true);
			}
		});
	});

	function dirty() { state.dirty = true; }
	form.addEventListener('input', dirty);
	form.addEventListener('change', dirty);
	window.addEventListener('beforeunload', function (e) {
		if (state.dirty && !form.hidden) {
			e.preventDefault();
			e.returnValue = '';
		}
	});

	function mayLeave() {
		return !state.dirty || form.hidden || window.confirm(T.unsaved);
	}

	/* Cover photo, from the media library. */
	var frame = null;
	$('[data-paw-ev-photo-choose]', form).addEventListener('click', function () {
		if (!window.wp || !window.wp.media) {
			return;
		}
		frame = frame || window.wp.media({ title: T.choosePhoto, library: { type: 'image' }, multiple: false });
		frame.off('select').on('select', function () {
			var file = frame.state().get('selection').first().toJSON();
			setPhoto(file.id, file.sizes && file.sizes.medium ? file.sizes.medium.url : file.url);
			dirty();
		});
		frame.open();
	});
	$('[data-paw-ev-photo-remove]', form).addEventListener('click', function () {
		setPhoto(0);
		dirty();
	});

	/* ---------- Switching between the list and the editor ---------- */

	function showList(push) {
		form.hidden = true;
		list.hidden = false;
		state.id = 0;
		state.dirty = false;
		if (push) {
			history.pushState({ pawView: 'events' }, '', url());
		}
		loadList();
	}

	function openEditor(id, push) {
		if (push) {
			history.pushState({ pawView: 'events', pawEvent: id }, '', url({ event: id }));
		}
		list.hidden = true;
		form.hidden = false;
		window.scrollTo(0, 0);
		if (id === 'new') {
			state.id = 0;
			fill({ sections: E.defaults, status: 'draft' });
			field('title').focus();
			return;
		}
		state.id = +id;
		api('staff/events/' + id).then(function (r) {
			if (r.ok) {
				fill(r.body);
			} else {
				showList(true);
			}
		});
	}

	$('[data-paw-ev-back]', form).addEventListener('click', function () {
		if (mayLeave()) {
			showList(true);
		}
	});
	root.querySelectorAll('[data-paw-nav]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			if (!mayLeave()) {
				e.preventDefault();
				e.stopImmediatePropagation();
			} else {
				state.dirty = false;
			}
		}, true);
	});

	function route() {
		var id = new URLSearchParams(location.search).get('event');
		if (id && /^(new|\d+)$/.test(id)) {
			openEditor(id, false);
		} else {
			showList(false);
		}
	}

	root.addEventListener('paw:view', function (e) {
		if (e.detail.view === 'events') {
			route();
		}
	});
	if (!root.querySelector('[data-paw-view="events"]').hidden) {
		route();
	}

	/* ---------- Settings → Event categories ---------- */

	var cats = root.querySelector('[data-paw-ev-cats]');
	if (cats) {
		var catList = $('[data-paw-ev-catlist]', cats);
		var catMsg = $('[data-paw-ev-cat-msg]', cats);

		var addCat = function (c) {
			var holder = document.createElement('div');
			holder.innerHTML = $('[data-paw-ev-cat-template]', cats).innerHTML;
			var li = holder.firstElementChild;
			var name = 'paw-ev-cat-' + (++uid);
			li.setAttribute('data-slug', c.slug || '');
			$('[data-paw-ev-cat-name]', li).value = c.name || '';
			$$('[data-paw-ev-cat-color]', li).forEach(function (r) {
				r.name = name;
				r.checked = r.value === (c.color || E.palette[0]);
			});
			catList.appendChild(li);
			return li;
		};

		E.categories.forEach(addCat);
		$('[data-paw-ev-cat-add]', cats).addEventListener('click', function () {
			$('[data-paw-ev-cat-name]', addCat({})).focus();
		});
		catList.addEventListener('click', function (e) {
			if (e.target.closest('[data-paw-ev-cat-remove]')) {
				e.target.closest('[data-paw-ev-cat]').remove();
			}
		});
		$('[data-paw-ev-cat-save]', cats).addEventListener('click', function () {
			var items = $$('[data-paw-ev-cat]', catList);
			var payload = items.map(function (li) {
				var checked = li.querySelector('[data-paw-ev-cat-color]:checked');
				return { slug: li.getAttribute('data-slug'), name: $('[data-paw-ev-cat-name]', li).value.trim(), color: checked ? checked.value : E.palette[0] };
			});
			items.forEach(function (li) {
				$('[data-paw-ev-key="name"]', li).classList.remove('is-invalid');
			});
			api('staff/event-categories', { method: 'POST', json: { categories: payload } }).then(function (r) {
				if (r.ok) {
					E.categories = r.body.categories;
					catList.textContent = '';
					E.categories.forEach(addCat);
					categoryOptions(field('category').value);
					catMsg.textContent = T.categoriesSaved;
					catMsg.className = 'paw-dash__savemsg is-ok';
				} else {
					var fields = (r.body && r.body.data && r.body.data.fields) || {};
					Object.keys(fields).forEach(function (key) {
						var n = +key.split('.')[1];
						if (items[n]) {
							$('[data-paw-ev-key="name"]', items[n]).classList.add('is-invalid');
						}
					});
					catMsg.textContent = (r.body && r.body.message) || CFG.i18n.failed;
					catMsg.className = 'paw-dash__savemsg is-error';
				}
				catMsg.hidden = false;
			});
		});
	}
})();
