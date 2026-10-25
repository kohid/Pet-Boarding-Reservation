/*
 * Pawfectly — staff dashboard: Overview tiles and chart, the Reservations list, and the side
 * panel. Everything comes from the staff REST routes; owner-entered text is set with textContent.
 */
(function () {
	'use strict';

	var CFG = window.PAW_DASH;
	var root = document.querySelector('[data-paw-dash]');
	if (!CFG || !root) {
		return;
	}
	var I18N = CFG.i18n;
	var $ = function (sel) { return root.querySelector(sel); };

	var state = { view: CFG.view, status: '', search: '', from: '', to: '', page: 1, pages: 1 };
	var current = null; // The reservation open in the side panel.
	var lastRow = null;

	function api(path, options) {
		options = options || {};
		options.credentials = 'same-origin';
		options.headers = Object.assign({ 'X-WP-Nonce': CFG.nonce }, options.json ? { 'Content-Type': 'application/json' } : {});
		if (options.json) {
			options.body = JSON.stringify(options.json);
		}
		return fetch(CFG.rest + path, options).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (body) {
				if (!res.ok) {
					throw new Error(body.message || I18N.failed);
				}
				return body;
			});
		});
	}

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

	function format(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return str.replace(/%(\d)\$d|%d/g, function (m, n) { return n ? args[n - 1] : args[i++]; });
	}

	function badge(status, label) {
		var b = el('span', 'paw-dash__badge paw-dash__badge--' + status, label);
		return b;
	}

	/* ---------- Views ---------- */

	function show(view, push) {
		state.view = view;
		root.querySelectorAll('[data-paw-view]').forEach(function (s) {
			s.hidden = s.getAttribute('data-paw-view') !== view;
		});
		root.querySelectorAll('[data-paw-nav]').forEach(function (a) {
			if (a.getAttribute('data-paw-nav') === view) {
				a.setAttribute('aria-current', 'page');
			} else {
				a.removeAttribute('aria-current');
			}
		});
		$('[data-paw-title]').textContent = I18N[view];
		if (push) {
			history.pushState({ pawView: view }, '', CFG.urls[view]);
		}
		if (view === 'overview') {
			loadOverview();
		} else {
			loadList();
		}
	}

	root.querySelectorAll('[data-paw-nav="overview"], [data-paw-nav="reservations"]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			if (e.metaKey || e.ctrlKey || e.shiftKey) {
				return;
			}
			e.preventDefault();
			show(a.getAttribute('data-paw-nav'), true);
		});
	});
	window.addEventListener('popstate', function (e) {
		show(e.state && e.state.pawView ? e.state.pawView : CFG.view, false);
	});

	/* ---------- Overview ---------- */

	function loadOverview() {
		api('staff/stats').then(function (s) {
			['check_ins', 'check_outs', 'boarding', 'grooming', 'pending'].forEach(function (key) {
				$('[data-paw-tile="' + key + '"] .paw-dash__tile-num').textContent = String(s[key]);
			});
			var chart = $('[data-paw-chart]');
			var max = Math.max.apply(null, s.nights.map(function (n) { return n.count; }).concat([1]));
			chart.textContent = '';
			s.nights.forEach(function (n, i) {
				var d = new Date(n.date + 'T00:00:00');
				var bar = el('div', 'paw-dash__bar' + (i === 0 ? ' is-today' : ''));
				bar.title = format(I18N.petsNight, n.count) + ' · ' + d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
				var fill = el('span', 'paw-dash__bar-fill');
				fill.style.height = (n.count / max * 100) + '%';
				var num = el('span', 'paw-dash__bar-num', String(n.count));
				var day = el('span', 'paw-dash__bar-day', d.toLocaleDateString(undefined, { weekday: 'narrow' }) + ' ' + d.getDate());
				bar.append(num, fill, day);
				chart.appendChild(bar);
			});
		}).catch(function () { /* Tiles keep their dash. */ });
	}

	/* ---------- Reservations list ---------- */

	var listToken = 0;

	function loadList() {
		var token = ++listToken;
		var q = new URLSearchParams({ page: String(state.page) });
		['status', 'search', 'from', 'to'].forEach(function (k) {
			if (state[k]) {
				q.set(k, state[k]);
			}
		});
		api('staff/reservations?' + q.toString()).then(function (data) {
			if (token !== listToken) {
				return; // A newer search already answered.
			}
			renderTabs(data.counts);
			renderRows(data.rows);
			state.pages = Math.max(1, data.pages || 1);
			var from = data.total ? (state.page - 1) * 20 + 1 : 0;
			$('[data-paw-showing]').textContent = format(I18N.showing, from, Math.min(state.page * 20, data.total), data.total);
			$('[data-paw-prev-page]').disabled = state.page <= 1;
			$('[data-paw-next-page]').disabled = state.page >= state.pages;
		}).catch(function (err) {
			var empty = $('[data-paw-empty]');
			empty.textContent = err.message;
			empty.hidden = false;
		});
	}

	function renderTabs(counts) {
		var wrap = $('[data-paw-status-tabs]');
		wrap.textContent = '';
		var total = Object.keys(counts).reduce(function (s, k) { return s + counts[k]; }, 0);
		var tabs = [['', I18N.all, total]].concat(Object.keys(CFG.statuses).map(function (k) { return [k, CFG.statuses[k], counts[k] || 0]; }));
		tabs.forEach(function (t) {
			var b = el('button', 'paw-dash__tab');
			b.type = 'button';
			b.setAttribute('role', 'tab');
			b.setAttribute('aria-selected', String(state.status === t[0]));
			b.setAttribute('data-paw-status-tab', t[0] || 'all');
			b.append(el('span', '', t[1]), el('span', 'paw-dash__tab-count', String(t[2])));
			b.addEventListener('click', function () {
				state.status = t[0];
				state.page = 1;
				loadList();
			});
			wrap.appendChild(b);
		});
	}

	function renderRows(rows) {
		var body = $('[data-paw-rows]');
		body.textContent = '';
		$('[data-paw-empty]').hidden = rows.length > 0;
		$('[data-paw-empty]').textContent = I18N.empty;
		rows.forEach(function (r) {
			var tr = el('tr');
			tr.tabIndex = 0;
			tr.setAttribute('data-id', String(r.id));
			var ref = el('td');
			ref.append(el('strong', '', r.reference), el('small', '', r.created_at));
			var pet = el('td');
			pet.append(el('span', '', r.pet_name), el('small', 'paw-dash__chip', r.pet_type));
			var owner = el('td');
			owner.append(el('span', '', r.owner), el('small', '', r.email));
			var stay = el('td');
			stay.append(el('span', '', r.stay), el('small', '', r.booking_type === 'grooming' ? r.service_name : r.nights + ' ' + (r.nights === 1 ? I18N.night : I18N.nights) + (r.service_name ? ' · ' + r.service_name : '')));
			var st = el('td');
			st.appendChild(badge(r.status, r.status_label));
			tr.append(ref, pet, owner, stay, st);
			tr.addEventListener('click', function () { open(r.id, tr); });
			tr.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					open(r.id, tr);
				}
			});
			body.appendChild(tr);
		});
	}

	var searchTimer = null;
	$('[data-paw-search]').addEventListener('input', function (e) {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			state.search = e.target.value.trim();
			state.page = 1;
			loadList();
		}, 300);
	});
	['from', 'to'].forEach(function (k) {
		$('[data-paw-' + k + ']').addEventListener('change', function (e) {
			state[k] = e.target.value;
			state.page = 1;
			loadList();
		});
	});
	$('[data-paw-prev-page]').addEventListener('click', function () {
		if (state.page > 1) {
			state.page--;
			loadList();
		}
	});
	$('[data-paw-next-page]').addEventListener('click', function () {
		if (state.page < state.pages) {
			state.page++;
			loadList();
		}
	});

	/* ---------- Side panel ---------- */

	var panel = $('[data-paw-panel]');
	var overlay = $('[data-paw-overlay]');

	function renderHistory(items) {
		var list = $('[data-paw-history]');
		list.textContent = '';
		items.forEach(function (h) {
			var li = el('li');
			li.append(badge(h.status, h.label), el('small', '', h.at + ' · ' + h.by));
			list.appendChild(li);
		});
	}

	function setBadge(status, label) {
		var b = $('[data-paw-panel-badge]');
		b.className = 'paw-dash__badge paw-dash__badge--' + status;
		b.textContent = label;
		$('[data-paw-status-select]').value = status;
	}

	function open(id, row) {
		lastRow = row || null;
		panel.hidden = false;
		overlay.hidden = false;
		$('[data-paw-panel-title]').textContent = I18N.loading;
		$('[data-paw-panel-sub]').textContent = '';
		$('[data-paw-answers]').textContent = '';
		api('staff/reservations/' + id).then(function (d) {
			current = d;
			$('[data-paw-panel-title]').textContent = d.pet_name + ' · ' + d.reference;
			$('[data-paw-panel-sub]').textContent = d.owner + ' · ' + d.stay;
			setBadge(d.status, d.status_label);
			var files = $('[data-paw-files]');
			files.textContent = '';
			d.files.forEach(function (f) {
				var li = el('li');
				var a = el('a', '', f.name);
				a.href = f.url;
				li.appendChild(a);
				files.appendChild(li);
			});
			$('[data-paw-signature]').src = d.signature_url;
			$('[data-paw-answers]').innerHTML = d.answers_html; // Rendered and escaped by the server.
			$('[data-paw-notes]').value = d.staff_notes;
			$('[data-paw-admin-link]').href = d.admin_url;
			renderHistory(d.history);
			$('[data-paw-close]').focus();
		}).catch(function (err) {
			$('[data-paw-panel-title]').textContent = err.message;
		});
	}

	function close() {
		panel.hidden = true;
		overlay.hidden = true;
		current = null;
		if (lastRow && document.contains(lastRow)) {
			lastRow.focus();
		}
	}

	$('[data-paw-close]').addEventListener('click', close);
	overlay.addEventListener('click', close);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !panel.hidden) {
			close();
		}
	});

	$('[data-paw-status-save]').addEventListener('click', function () {
		if (!current) {
			return;
		}
		var btn = this;
		btn.disabled = true;
		api('staff/reservations/' + current.id + '/status', {
			method: 'POST',
			json: { status: $('[data-paw-status-select]').value, notify: $('[data-paw-notify]').checked }
		}).then(function (d) {
			setBadge(d.status, d.status_label);
			renderHistory(d.history);
			if (state.view === 'reservations') {
				loadList();
			}
		}).catch(function (err) {
			window.alert(err.message);
		}).then(function () {
			btn.disabled = false;
		});
	});

	$('[data-paw-notes-save]').addEventListener('click', function () {
		if (!current) {
			return;
		}
		var btn = this;
		var label = btn.textContent;
		api('staff/reservations/' + current.id + '/notes', { method: 'POST', json: { notes: $('[data-paw-notes]').value } }).then(function () {
			btn.textContent = I18N.saved;
			setTimeout(function () { btn.textContent = label; }, 1500);
		}).catch(function (err) {
			window.alert(err.message);
		});
	});

	$('[data-paw-delete]').addEventListener('click', function () {
		if (!current || !window.confirm(I18N.confirmDelete)) {
			return;
		}
		api('staff/reservations/' + current.id, { method: 'DELETE' }).then(function () {
			close();
			loadList();
		}).catch(function (err) {
			window.alert(err.message);
		});
	});

	/* ---------- Start ---------- */

	history.replaceState({ pawView: state.view }, '', location.href);
	show(state.view, false);
	// Links in staff emails open one reservation: ?paw_view=reservations&open=ID.
	var opening = parseInt(new URLSearchParams(location.search).get('open') || '', 10);
	if (opening > 0) {
		open(opening, null);
	}
})();
