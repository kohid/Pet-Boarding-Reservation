/*
 * Pawfectly — account dropdown. Opens on click or Enter/Space/ArrowDown, closes on Escape,
 * a click elsewhere, or when focus leaves. Arrow keys, Home and End move between items.
 * The menu itself is rendered by the server.
 */
(function () {
	'use strict';

	var menus = Array.prototype.slice.call(document.querySelectorAll('[data-paw-um]'));
	if (!menus.length) {
		return;
	}

	function closeAll(except) {
		menus.forEach(function (m) {
			if (m !== except && m.pawClose) {
				m.pawClose(false);
			}
		});
	}

	menus.forEach(function (root) {
		var btn = root.querySelector('.paw-um__btn');
		var panel = root.querySelector('.paw-um__panel');
		if (!btn || !panel) {
			return;
		}
		var items = function () {
			return Array.prototype.slice.call(panel.querySelectorAll('[role="menuitem"]'));
		};

		function open(focusFirst) {
			closeAll(root);
			panel.hidden = false;
			btn.setAttribute('aria-expanded', 'true');
			if (focusFirst && items()[0]) {
				items()[0].focus();
			}
		}

		function close(returnFocus) {
			if (panel.hidden) {
				return;
			}
			panel.hidden = true;
			btn.setAttribute('aria-expanded', 'false');
			if (returnFocus) {
				btn.focus();
			}
		}
		root.pawClose = close;

		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			if (panel.hidden) {
				open(e.detail === 0); // detail 0: opened with the keyboard.
			} else {
				close(false);
			}
		});
		btn.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				open(true);
			}
		});
		panel.addEventListener('keydown', function (e) {
			var list = items();
			var i = list.indexOf(document.activeElement);
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				list[(i + 1) % list.length].focus();
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				list[(i - 1 + list.length) % list.length].focus();
			} else if (e.key === 'Home') {
				e.preventDefault();
				list[0].focus();
			} else if (e.key === 'End') {
				e.preventDefault();
				list[list.length - 1].focus();
			} else if (e.key === 'Tab') {
				close(false);
			}
		});
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !panel.hidden) {
				e.stopPropagation();
				close(true);
			}
		});
	});

	document.addEventListener('click', function (e) {
		menus.forEach(function (m) {
			if (!m.contains(e.target) && m.pawClose) {
				m.pawClose(false);
			}
		});
	});
})();
