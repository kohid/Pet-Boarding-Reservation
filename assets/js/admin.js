/* Pawfectly — wp-admin helpers: confirm deletes, print, copy shortcode, choose logo. */
(function () {
	'use strict';

	var L10N = window.PAW_ADMIN || {};

	document.addEventListener('submit', function (e) {
		var form = e.target.closest('[data-paw-confirm]');
		if (form && !window.confirm(form.getAttribute('data-paw-confirm'))) {
			e.preventDefault();
		}
	});

	// Bulk "Delete permanently" asks first, too.
	document.addEventListener('submit', function (e) {
		var top = e.target.querySelector('#bulk-action-selector-top');
		var bottom = e.target.querySelector('#bulk-action-selector-bottom');
		var deleting = (top && top.value === 'delete') || (bottom && bottom.value === 'delete');
		if (deleting && !window.confirm(L10N.confirmBulkDelete || '')) {
			e.preventDefault();
		}
	});

	document.addEventListener('click', function (e) {
		var print = e.target.closest('[data-paw-print]');
		if (print) {
			window.print();
			return;
		}

		var copy = e.target.closest('[data-paw-copy]');
		if (copy) {
			var source = document.querySelector(copy.getAttribute('data-paw-copy'));
			if (source && navigator.clipboard) {
				navigator.clipboard.writeText(source.textContent.trim()).then(function () {
					var label = copy.textContent;
					copy.textContent = L10N.copied || 'Copied';
					setTimeout(function () { copy.textContent = label; }, 1500);
				});
			}
			return;
		}

		var choose = e.target.closest('[data-paw-logo-choose]');
		if (choose && window.wp && wp.media) {
			var frame = wp.media({ title: L10N.chooseLogo || '', library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var image = frame.state().get('selection').first().toJSON();
				document.querySelector('[data-paw-logo-id]').value = image.id;
				document.querySelector('[data-paw-logo-preview]').src = (image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail : image).url;
			});
			frame.open();
			return;
		}

		var remove = e.target.closest('[data-paw-logo-remove]');
		if (remove) {
			document.querySelector('[data-paw-logo-id]').value = '0';
			document.querySelector('[data-paw-logo-preview]').src = remove.getAttribute('data-placeholder');
		}
	});
})();
