/*
 * Pawfectly — a small signature pad. Pointer events cover mouse, touch and pen;
 * strokes are kept so the drawing survives a resize or a rotate.
 */
(function () {
	'use strict';

	function SignaturePad(canvas) {
		this.canvas = canvas;
		this.ctx = canvas.getContext('2d');
		this.paths = [];
		this.current = null;
		this.onchange = null;

		var self = this;
		canvas.addEventListener('pointerdown', function (e) { self.start(e); });
		canvas.addEventListener('pointermove', function (e) { self.move(e); });
		['pointerup', 'pointercancel', 'pointerleave'].forEach(function (type) {
			canvas.addEventListener(type, function () { self.end(); });
		});
		window.addEventListener('resize', function () { self.resize(); });
		this.resize();
	}

	SignaturePad.prototype = {
		get strokes() {
			return this.paths.length;
		},

		isEmpty: function () {
			return this.paths.length === 0;
		},

		clear: function () {
			this.paths = [];
			this.current = null;
			this.redraw();
			this.changed();
		},

		toDataURL: function () {
			return this.canvas.toDataURL('image/png');
		},

		/** Matches the backing store to the displayed size and the screen's pixel density. */
		resize: function () {
			var rect = this.canvas.getBoundingClientRect();
			if (!rect.width) {
				return;
			}
			// Capped at 2: sharper adds nothing to a signature, and very dense screens would
			// otherwise produce an image bigger than the server accepts.
			var ratio = Math.min(Math.max(window.devicePixelRatio || 1, 1), 2);
			this.canvas.width = Math.round(rect.width * ratio);
			this.canvas.height = Math.round(rect.height * ratio);
			this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			this.redraw();
		},

		point: function (e) {
			var rect = this.canvas.getBoundingClientRect();
			return { x: e.clientX - rect.left, y: e.clientY - rect.top };
		},

		start: function (e) {
			if (e.button > 0) {
				return;
			}
			e.preventDefault();
			if (this.canvas.setPointerCapture) {
				this.canvas.setPointerCapture(e.pointerId);
			}
			this.current = [this.point(e)];
			this.paths.push(this.current);
			this.redraw();
		},

		move: function (e) {
			if (!this.current) {
				return;
			}
			e.preventDefault();
			this.current.push(this.point(e));
			this.redraw();
		},

		end: function () {
			if (!this.current) {
				return;
			}
			this.current = null;
			this.changed();
		},

		redraw: function () {
			var ctx = this.ctx;
			ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
			ctx.strokeStyle = '#2c3947';
			ctx.fillStyle = '#2c3947';
			ctx.lineWidth = 2.25;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			this.paths.forEach(function (pts) {
				if (pts.length === 1) {
					ctx.beginPath();
					ctx.arc(pts[0].x, pts[0].y, 1.4, 0, Math.PI * 2);
					ctx.fill();
					return;
				}
				ctx.beginPath();
				ctx.moveTo(pts[0].x, pts[0].y);
				for (var i = 1; i < pts.length - 1; i++) {
					var mx = (pts[i].x + pts[i + 1].x) / 2;
					var my = (pts[i].y + pts[i + 1].y) / 2;
					ctx.quadraticCurveTo(pts[i].x, pts[i].y, mx, my);
				}
				ctx.lineTo(pts[pts.length - 1].x, pts[pts.length - 1].y);
				ctx.stroke();
			});
		},

		changed: function () {
			if (typeof this.onchange === 'function') {
				this.onchange(this);
			}
		}
	};

	window.SignaturePad = SignaturePad;
})();
