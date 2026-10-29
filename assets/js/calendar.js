/**
 * Pawfectly — [pawfectly_calendar]: views, month and day navigation, the month picker,
 * search, categories, "+N more" and the event popover. Every change asks the public route
 * for the rendered views (GET pawfectly/v1/events/calendar) and swaps them in.
 */
(function () {
	'use strict';

	function esc(s) {
		return String(s || '').replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parse(s) { var p = String(s).split('-'); return new Date(+p[0], (+p[1] || 1) - 1, +p[2] || 1); }

	/* ---------- Popover (one per page, on <body> so the grid never clips it) ---------- */

	var pop = null;
	var hideTimer = null;

	function popover() {
		if (pop) {
			return pop;
		}
		pop = document.createElement('div');
		pop.className = 'paw-cal-pop';
		pop.hidden = true;
		pop.setAttribute('role', 'dialog');
		document.body.appendChild(pop);
		pop.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
		pop.addEventListener('mouseleave', hidePop);
		pop.addEventListener('click', function (e) {
			var toggle = e.target.closest('[data-paw-pop-add]');
			if (toggle) {
				var menu = pop.querySelector('.paw-cal-pop__menu');
				menu.hidden = !menu.hidden;
				toggle.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
			}
		});
		return pop;
	}

	function hidePop() {
		clearTimeout(hideTimer);
		hideTimer = setTimeout(function () {
			if (!pop) {
				return;
			}
			pop.classList.remove('is-shown');
			pop.hidden = true;
			pop.current = null;
		}, 150);
	}

	function showPop(ev, t) {
		var p = popover();
		clearTimeout(hideTimer);
		if (p.current === ev && !p.hidden) {
			return;
		}
		p.current = ev;
		var cal = {};
		try { cal = JSON.parse(ev.getAttribute('data-cal') || '{}'); } catch (err) { cal = {}; }
		var thumb = ev.getAttribute('data-thumb') || '';
		var html = '<div class="paw-cal-pop__media"' + (thumb ? ' style="background-image:url(\'' + esc(thumb).replace(/'/g, '%27') + '\')"' : '') + '>'
			+ (thumb ? '' : esc(ev.getAttribute('data-emoji') || '🐾')) + '</div><div class="paw-cal-pop__body">'
			+ '<div class="paw-cal-pop__time">' + esc(ev.getAttribute('data-time')) + '</div>'
			+ '<div class="paw-cal-pop__title">' + esc(ev.getAttribute('data-title')) + '</div>'
			+ (ev.getAttribute('data-venue') ? '<div class="paw-cal-pop__venue">' + esc(ev.getAttribute('data-venue')) + '</div>' : '')
			+ (ev.getAttribute('data-excerpt') ? '<p class="paw-cal-pop__ex">' + esc(ev.getAttribute('data-excerpt')) + '</p>' : '')
			+ '<div class="paw-cal-pop__actions"><a class="paw-cal-pop__btn paw-cal-pop__btn--primary" href="' + esc(ev.getAttribute('href')) + '">' + esc(t.viewEvent) + '</a>'
			+ '<button type="button" class="paw-cal-pop__btn" data-paw-pop-add aria-expanded="false">' + esc(t.addToCal) + '</button>'
			+ '<div class="paw-cal-pop__menu" hidden>'
			+ (cal.google ? '<a href="' + esc(cal.google) + '" target="_blank" rel="noopener">' + esc(t.google) + '</a>' : '')
			+ (cal.outlook ? '<a href="' + esc(cal.outlook) + '" target="_blank" rel="noopener">' + esc(t.outlook) + '</a>' : '')
			+ (cal.ics ? '<a href="' + esc(cal.ics) + '">' + esc(t.ics) + '</a>' : '')
			+ '</div></div></div>';
		p.innerHTML = html;
		p.hidden = false;
		var r = ev.getBoundingClientRect();
		var w = p.offsetWidth;
		var h = p.offsetHeight;
		var vw = document.documentElement.clientWidth;
		var left = Math.max(12, Math.min(r.left + r.width / 2 - w / 2, vw - w - 12));
		var top = r.bottom + 8;
		if (top + h > window.innerHeight - 12) {
			top = Math.max(12, r.top - h - 8);
		}
		p.style.left = (left + window.pageXOffset) + 'px';
		p.style.top = (top + window.pageYOffset) + 'px';
		requestAnimationFrame(function () { p.classList.add('is-shown'); });
	}

	/* ---------- One calendar ---------- */

	function setup(cal) {
		var t = {};
		try { t = JSON.parse(cal.getAttribute('data-i18n') || '{}'); } catch (err) { t = {}; }
		var boxes = {
			month: cal.querySelector('[data-paw-cal-box="month"]'),
			list: cal.querySelector('[data-paw-cal-box="list"]'),
			day: cal.querySelector('[data-paw-cal-box="day"]')
		};
		var titleBtn = cal.querySelector('[data-paw-cal-title]');
		var titleText = cal.querySelector('[data-paw-cal-title-text]');
		var picker = cal.querySelector('[data-paw-cal-picker]');
		var loader = cal.querySelector('[data-paw-cal-loader]');
		var q = cal.querySelector('[data-paw-cal-q]');
		var cat = cal.querySelector('[data-paw-cal-cat]');
		var req = 0;
		var pickerMonth = null;

		function view() { return cal.getAttribute('data-view') || 'month'; }
		function query() { return q ? q.value.trim() : ''; }

		function title() {
			titleText.textContent = cal.getAttribute(view() === 'day' ? 'data-day-title' : 'data-month-title') || '';
		}

		function load(date) {
			var mine = ++req;
			loader.hidden = false;
			var url = cal.getAttribute('data-rest');
			url += (url.indexOf('?') < 0 ? '?' : '&') + 'date=' + encodeURIComponent(date) + '&q=' + encodeURIComponent(query()) + '&cat=' + encodeURIComponent(cal.getAttribute('data-cat') || '');
			fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
				.then(function (r) {
					if (!r.ok) {
						throw new Error('HTTP ' + r.status);
					}
					return r.json();
				})
				.then(function (d) {
					if (mine !== req) {
						return; // A newer request replaced this one.
					}
					cal.setAttribute('data-date', d.date);
					cal.setAttribute('data-ym', d.ym);
					cal.setAttribute('data-month-title', d.monthTitle);
					cal.setAttribute('data-day-title', d.dayTitle);
					boxes.month.innerHTML = d.grid;
					boxes.list.innerHTML = d.agenda;
					boxes.day.innerHTML = d.day;
					title();
				})
				.catch(function () {
					if (mine === req) {
						boxes[view()].innerHTML = '<div class="paw-cal__empty"><p>' + esc(t.failed) + '</p></div>';
					}
				})
				.then(function () {
					if (mine === req) {
						loader.hidden = true;
					}
				});
		}

		function setView(v) {
			cal.setAttribute('data-view', v);
			cal.querySelectorAll('[data-paw-cal-view]').forEach(function (b) {
				var on = b.getAttribute('data-paw-cal-view') === v;
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
			Object.keys(boxes).forEach(function (k) { boxes[k].hidden = k !== v; });
			title();
			closePicker();
		}

		function step(delta) {
			var d = parse(cal.getAttribute('data-date'));
			if (view() === 'day') {
				d.setDate(d.getDate() + delta);
			} else {
				d = new Date(d.getFullYear(), d.getMonth() + delta, 1);
			}
			load(ymd(d));
		}

		cal.querySelector('[data-paw-cal-prev]').addEventListener('click', function () { step(-1); });
		cal.querySelector('[data-paw-cal-next]').addEventListener('click', function () { step(1); });
		cal.querySelector('[data-paw-cal-today]').addEventListener('click', function () { load(cal.getAttribute('data-today') || ymd(new Date())); });
		cal.querySelectorAll('[data-paw-cal-view]').forEach(function (b) {
			b.addEventListener('click', function () { setView(b.getAttribute('data-paw-cal-view')); });
		});

		cal.querySelector('[data-paw-cal-search]').addEventListener('submit', function (e) {
			e.preventDefault();
			load(cal.getAttribute('data-date'));
		});
		if (q) {
			var typing = null;
			q.addEventListener('input', function () {
				clearTimeout(typing);
				if (query().length === 1) {
					return; // Wait for a real word.
				}
				typing = setTimeout(function () { load(cal.getAttribute('data-date')); }, 400);
			});
		}
		if (cat) {
			cat.addEventListener('change', function () {
				cal.setAttribute('data-cat', cat.value);
				load(cal.getAttribute('data-date'));
			});
		}

		/* Month picker under the title. */
		function renderPicker() {
			var y = pickerMonth.y;
			var m = pickerMonth.m;
			var first = new Date(y, m, 1);
			var day = new Date(y, m, 1 - ((first.getDay() + 6) % 7));
			var sel = cal.getAttribute('data-date');
			var today = cal.getAttribute('data-today');
			var html = '<div class="paw-cal__pk-head"><button type="button" class="paw-cal__pk-nav" data-paw-pk="-1" aria-label="' + esc(t.prevMonth) + '">‹</button>'
				+ '<span>' + esc((t.months || [])[m]) + ' ' + y + '</span>'
				+ '<button type="button" class="paw-cal__pk-nav" data-paw-pk="1" aria-label="' + esc(t.nextMonth) + '">›</button></div><div class="paw-cal__pk-grid">';
			['M', 'T', 'W', 'T', 'F', 'S', 'S'].forEach(function (d) { html += '<span class="paw-cal__pk-dow" aria-hidden="true">' + d + '</span>'; });
			for (var i = 0; i < 42; i++) {
				var s = ymd(day);
				html += '<button type="button" class="paw-cal__pk-day' + (day.getMonth() !== m ? ' is-other' : '') + (s === today ? ' is-today' : '') + (s === sel ? ' is-sel' : '') + '" data-paw-pk-day="' + s + '">' + day.getDate() + '</button>';
				day.setDate(day.getDate() + 1);
			}
			picker.innerHTML = html + '</div>';
		}

		function openPicker() {
			var d = parse(cal.getAttribute('data-date'));
			pickerMonth = { y: d.getFullYear(), m: d.getMonth() };
			renderPicker();
			picker.hidden = false;
			titleBtn.setAttribute('aria-expanded', 'true');
		}

		function closePicker() {
			if (!picker.hidden) {
				picker.hidden = true;
				titleBtn.setAttribute('aria-expanded', 'false');
			}
		}

		titleBtn.addEventListener('click', function (e) {
			e.stopPropagation();
			if (picker.hidden) {
				openPicker();
			} else {
				closePicker();
			}
		});
		picker.addEventListener('click', function (e) {
			e.stopPropagation();
			var nav = e.target.closest('[data-paw-pk]');
			if (nav) {
				var d = new Date(pickerMonth.y, pickerMonth.m + Number(nav.getAttribute('data-paw-pk')), 1);
				pickerMonth = { y: d.getFullYear(), m: d.getMonth() };
				renderPicker();
				return;
			}
			var day = e.target.closest('[data-paw-pk-day]');
			if (day) {
				load(day.getAttribute('data-paw-pk-day'));
				closePicker();
			}
		});
		document.addEventListener('click', closePicker);
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closePicker();
				hidePop();
			}
		});

		/* "+N more", and a day number opens that day. */
		cal.addEventListener('click', function (e) {
			var more = e.target.closest('[data-paw-cal-more]');
			if (more) {
				var cell = more.closest('.paw-cal__cell');
				var open = !cell.classList.contains('is-expanded');
				cell.classList.toggle('is-expanded', open);
				cell.querySelectorAll('.paw-cal__ev.is-extra').forEach(function (ev) { ev.hidden = !open; });
				if (!more.hasAttribute('data-more')) {
					more.setAttribute('data-more', more.textContent);
				}
				more.textContent = open ? more.getAttribute('data-less') : more.getAttribute('data-more');
				more.setAttribute('aria-expanded', open ? 'true' : 'false');
				return;
			}
			var go = e.target.closest('[data-paw-cal-goto]');
			if (go) {
				var date = go.getAttribute('data-paw-cal-goto');
				setView('day');
				if (date === cal.getAttribute('data-date')) {
					return;
				}
				load(date);
			}
		});

		/* Popover on hover and keyboard focus. */
		cal.addEventListener('mouseover', function (e) {
			var ev = e.target.closest('[data-paw-cal-event]');
			if (ev && !ev.hidden && window.matchMedia('(min-width: 641px)').matches) {
				showPop(ev, t);
			}
		});
		cal.addEventListener('mouseout', function (e) {
			var ev = e.target.closest('[data-paw-cal-event]');
			if (ev && !(e.relatedTarget && (ev.contains(e.relatedTarget) || (pop && pop.contains(e.relatedTarget))))) {
				hidePop();
			}
		});
		cal.addEventListener('focusin', function (e) {
			var ev = e.target.closest('[data-paw-cal-event]');
			if (ev) {
				showPop(ev, t);
			}
		});
		window.addEventListener('scroll', function () {
			if (pop && !pop.hidden) {
				hidePop();
			}
		}, true);
	}

	document.querySelectorAll('.paw-cal').forEach(setup);

	/* ---------- [pawfectly_events]: Load more ---------- */

	document.querySelectorAll('.paw-events').forEach(function (list) {
		var btn = list.querySelector('[data-paw-events-more]');
		var grid = list.querySelector('[data-paw-events-grid]');
		if (!btn) {
			return;
		}
		btn.addEventListener('click', function () {
			var url = list.getAttribute('data-rest');
			url += (url.indexOf('?') < 0 ? '?' : '&') + 'tab=' + encodeURIComponent(list.getAttribute('data-tab'))
				+ '&cat=' + encodeURIComponent(list.getAttribute('data-cat') || '')
				+ '&limit=' + encodeURIComponent(list.getAttribute('data-limit') || '9')
				+ '&offset=' + grid.querySelectorAll('.paw-events__card').length;
			btn.disabled = true;
			fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					grid.insertAdjacentHTML('beforeend', d.html); // Rendered and escaped by the server.
					btn.hidden = !d.more;
				})
				.catch(function () { /* The button stays; trying again is safe. */ })
				.then(function () { btn.disabled = false; });
		});
	});
})();
