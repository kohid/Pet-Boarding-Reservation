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

	// The address wins (as on the server), when that view is on the page.
	var askedView = new URLSearchParams(location.search).get('paw_view');
	var startView = askedView && root.querySelector('[data-paw-view="' + askedView + '"]') ? askedView : CFG.view;
	var state = { view: startView, status: '', search: '', from: '', to: '', page: 1, pages: 1 };
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
		} else if (view === 'reservations') {
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
		// Export CSV follows the same filters (the wp-admin export reads s, not search).
		var exportLink = $('[data-paw-export]');
		if (exportLink) {
			var e = new URLSearchParams();
			['status', 'from', 'to'].forEach(function (k) {
				if (state[k]) {
					e.set(k, state[k]);
				}
			});
			if (state.search) {
				e.set('s', state.search);
			}
			exportLink.href = CFG.urls.export + (e.toString() ? '&' + e.toString() : '');
		}
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
			renderFiles(d.files);
			$('[data-paw-signature]').src = d.signature_url;
			$('[data-paw-answers]').innerHTML = d.answers_html; // Rendered and escaped by the server.
			$('[data-paw-notes]').value = d.staff_notes;
			$('[data-paw-admin-link]').href = d.admin_url || '#';
			$('[data-paw-admin-link]').hidden = !d.admin_url; // Only for accounts that may open wp-admin.
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

	/* ---------- Vaccination records: thumbnails for pictures, links for PDFs, a lightbox ---------- */

	var lightbox = $('[data-paw-lightbox]');
	var images = [];
	var shown = 0;
	var opener = null;

	/** One group per pet when the booking has several (files carry the pet's name then). */
	function renderFiles(list) {
		var box = $('[data-paw-files]');
		box.textContent = '';
		images = list.filter(function (f) { return f.image; });
		if (!list.length) {
			box.appendChild(el('p', 'paw-dash__files-empty', I18N.noFiles));
			return;
		}
		var groups = [];
		list.forEach(function (f) {
			var g = groups.filter(function (x) { return x.pet === f.pet; })[0];
			if (!g) {
				g = { pet: f.pet, files: [] };
				groups.push(g);
			}
			g.files.push(f);
		});
		groups.forEach(function (g) {
			if (g.pet) {
				box.appendChild(el('p', 'paw-dash__files-pet', g.pet));
			}
			var ul = el('ul', 'paw-dash__thumbs');
			g.files.forEach(function (f) {
				var li = el('li', 'paw-dash__thumb');
				if (f.image) {
					var btn = el('button', 'paw-dash__thumb-btn');
					btn.type = 'button';
					btn.setAttribute('data-paw-thumb', String(images.indexOf(f)));
					var img = el('img');
					img.src = f.url;
					img.alt = f.name;
					img.loading = 'lazy';
					btn.appendChild(img);
					li.appendChild(btn);
				} else {
					var a = el('a', 'paw-dash__thumb-btn paw-dash__thumb-btn--pdf', I18N.pdf);
					a.href = f.url;
					a.target = '_blank';
					a.rel = 'noopener';
					a.setAttribute('aria-label', f.name);
					li.appendChild(a);
				}
				li.appendChild(el('span', 'paw-dash__thumb-name', f.name));
				ul.appendChild(li);
			});
			box.appendChild(ul);
		});
	}

	function showImage(i) {
		shown = (i + images.length) % images.length;
		var f = images[shown];
		$('[data-paw-lightbox-img]').src = f.url;
		$('[data-paw-lightbox-img]').alt = f.name;
		$('[data-paw-lightbox-name]').textContent = (f.pet ? f.pet + ': ' : '') + f.name;
		$('[data-paw-lightbox-count]').textContent = images.length > 1 ? format(I18N.imageOf, shown + 1, images.length) : '';
		$('[data-paw-lightbox-download]').href = f.url;
		$('[data-paw-lightbox-download]').setAttribute('download', f.name);
		$('[data-paw-lightbox-prev]').hidden = images.length < 2;
		$('[data-paw-lightbox-next]').hidden = images.length < 2;
	}

	function openLightbox(i, from) {
		opener = from || null;
		showImage(i);
		lightbox.hidden = false;
		$('[data-paw-lightbox-close]').focus();
	}

	function closeLightbox() {
		lightbox.hidden = true;
		$('[data-paw-lightbox-img]').removeAttribute('src');
		if (opener) {
			opener.focus();
		}
	}

	$('[data-paw-files]').addEventListener('click', function (e) {
		var btn = e.target.closest('[data-paw-thumb]');
		if (btn) {
			openLightbox(+btn.getAttribute('data-paw-thumb'), btn);
		}
	});
	$('[data-paw-lightbox-close]').addEventListener('click', closeLightbox);
	$('[data-paw-lightbox-prev]').addEventListener('click', function () { showImage(shown - 1); });
	$('[data-paw-lightbox-next]').addEventListener('click', function () { showImage(shown + 1); });
	lightbox.addEventListener('click', function (e) {
		if (e.target === lightbox || e.target.classList.contains('paw-dash__lightbox-figure')) {
			closeLightbox(); // A click on the dark area outside the picture.
		}
	});

	$('[data-paw-close]').addEventListener('click', close);
	overlay.addEventListener('click', close);
	document.addEventListener('keydown', function (e) {
		if (!lightbox.hidden) {
			if (e.key === 'Escape') {
				closeLightbox();
			} else if (e.key === 'ArrowLeft' && images.length > 1) {
				showImage(shown - 1);
			} else if (e.key === 'ArrowRight' && images.length > 1) {
				showImage(shown + 1);
			} else if (e.key === 'Tab') {
				// Keep focus on the lightbox's own buttons while it is open.
				var stops = Array.prototype.filter.call(lightbox.querySelectorAll('a, button'), function (b) { return !b.hidden; });
				var at = stops.indexOf(document.activeElement);
				var to = e.shiftKey ? (at <= 0 ? stops.length - 1 : at - 1) : (at + 1) % stops.length;
				stops[to].focus();
				e.preventDefault();
			}
			return;
		}
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

	/* ---------- Settings ---------- */

	var settingsForm = $('[data-paw-settings]');

	/** Form fields as the nested object Settings expects: services[basic_bath][small] → services.basic_bath.small. */
	function settingsValues(form) {
		var out = {};
		Array.prototype.forEach.call(form.elements, function (input) {
			if (!input.name || input.disabled) {
				return;
			}
			var list = /\[\]$/.test(input.name);
			var path = input.name.replace(/\[\]$/, '').replace(/\]/g, '').split('[');
			var last = path.pop();
			var node = path.reduce(function (o, key) { return (o[key] = o[key] || {}); }, out);
			if (list) {
				node[last] = node[last] || [];
				if (input.checked) {
					node[last].push(input.value);
				}
			} else {
				node[last] = input.type === 'checkbox' ? input.checked : input.value;
			}
		});
		return out;
	}

	function settingsErrors(form, fields) {
		form.querySelectorAll('.paw-dash__field.is-invalid').forEach(function (f) {
			f.classList.remove('is-invalid');
			var p = f.querySelector('.paw-dash__error');
			if (p) {
				p.remove();
			}
		});
		var first = null;
		Object.keys(fields || {}).forEach(function (key) {
			var f = form.querySelector('[data-paw-setting="' + key + '"]');
			if (!f) {
				return;
			}
			f.classList.add('is-invalid');
			f.appendChild(el('small', 'paw-dash__error', fields[key]));
			first = first || f.querySelector('input, textarea, select');
		});
		return first;
	}

	if (settingsForm) {
		var settingsMsg = $('[data-paw-settings-msg]');
		settingsForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = settingsForm.querySelector('button[type=submit]');
			btn.disabled = true;
			settingsMsg.hidden = true;
			settingsErrors(settingsForm, {});
			fetch(CFG.rest + 'staff/settings', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': CFG.nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify(settingsValues(settingsForm))
			}).then(function (res) {
				return res.json().catch(function () { return {}; }).then(function (body) {
					if (res.ok) {
						settingsMsg.textContent = I18N.settingsSaved;
						settingsMsg.className = 'paw-dash__savemsg is-ok';
					} else {
						var first = settingsErrors(settingsForm, body.data && body.data.fields);
						settingsMsg.textContent = body.message || I18N.failed;
						settingsMsg.className = 'paw-dash__savemsg is-error';
						if (first) {
							first.focus();
						}
					}
					settingsMsg.hidden = false;
				});
			}).catch(function () {
				settingsMsg.textContent = I18N.failed;
				settingsMsg.className = 'paw-dash__savemsg is-error';
				settingsMsg.hidden = false;
			}).then(function () {
				btn.disabled = false;
			});
		});

		var logoId = settingsForm.querySelector('[name=logo_id]');
		var logoImg = settingsForm.querySelector('[data-paw-logo-preview]');
		var frame = null;
		settingsForm.querySelector('[data-paw-logo-choose]').addEventListener('click', function () {
			if (!window.wp || !window.wp.media) {
				return;
			}
			frame = frame || window.wp.media({ title: I18N.chooseLogo, library: { type: 'image' }, multiple: false });
			frame.off('select').on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				logoId.value = String(file.id);
				logoImg.src = (file.sizes && file.sizes.thumbnail ? file.sizes.thumbnail.url : file.url);
			});
			frame.open();
		});
		settingsForm.querySelector('[data-paw-logo-remove]').addEventListener('click', function () {
			logoId.value = '0';
			logoImg.src = logoImg.getAttribute('data-placeholder');
		});
	}

	/* ---------- Start ---------- */

	history.replaceState({ pawView: state.view }, '', location.href);
	show(state.view, false);
	// Links in staff emails open one reservation: ?paw_view=reservations&open=ID.
	var opening = parseInt(new URLSearchParams(location.search).get('open') || '', 10);
	if (opening > 0) {
		open(opening, null);
	}
})();
