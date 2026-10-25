/*
 * Pawfectly — a Show/Hide button on every password box in the booking form and account pages.
 * Labels come from window.PAW_PW ({ show, hide }).
 */
(function () {
	'use strict';

	var L = window.PAW_PW || { show: 'Show password', hide: 'Hide password' };
	var EYE = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5C6.5 5 2.7 9.3 1.5 12c1.2 2.7 5 7 10.5 7s9.3-4.3 10.5-7C21.3 9.3 17.5 5 12 5zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9zm0-2.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>';
	var count = 0;

	function enhance(input) {
		if (input.closest('.paw-pw')) {
			return;
		}
		if (!input.id) {
			input.id = 'paw-pw-' + (++count);
		}
		var wrap = document.createElement('span');
		wrap.className = 'paw-pw';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(input);

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'paw-pw__toggle';
		btn.setAttribute('aria-controls', input.id);
		btn.setAttribute('aria-pressed', 'false');
		btn.setAttribute('aria-label', L.show);
		btn.title = L.show;
		btn.innerHTML = EYE;
		wrap.appendChild(btn);

		btn.addEventListener('click', function () {
			var showing = input.type === 'password';
			input.type = showing ? 'text' : 'password';
			btn.setAttribute('aria-pressed', String(showing));
			btn.setAttribute('aria-label', showing ? L.hide : L.show);
			btn.title = showing ? L.hide : L.show;
		});
	}

	function run() {
		document.querySelectorAll('.paw-app input[type=password], .paw-acct input[type=password]').forEach(enhance);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
