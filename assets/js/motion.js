/**
 * Yuniq.ai — optional GSAP motion layer.
 *
 * Loaded lazily, only once the visitor shows intent to open the panel,
 * and only from this plugin's own folder. Everything here is an
 * enhancement: if this file or GSAP never loads, public.js falls back to
 * the CSS transitions and the widget behaves identically, just with
 * simpler motion.
 *
 * @author Yegane Norouzi <https://github.com/Yeganenorouzi>
 */
(function () {
	'use strict';

	if (typeof window.gsap === 'undefined') {
		return;
	}

	var gsap = window.gsap;

	/**
	 * Open the panel.
	 *
	 * @param {Object} ctx root, panel, isMobile, onComplete
	 */
	function open(ctx) {
		var panel = ctx.panel;

		gsap.killTweensOf(panel);

		if (ctx.isMobile) {
			gsap.fromTo(
				panel,
				{ yPercent: 100, opacity: 1 },
				{
					yPercent: 0,
					duration: 0.42,
					ease: 'power3.out',
					onComplete: ctx.onComplete
				}
			);
			return;
		}

		gsap.fromTo(
			panel,
			{ opacity: 0, y: 16, scale: 0.97 },
			{
				opacity: 1,
				y: 0,
				scale: 1,
				duration: 0.38,
				ease: 'back.out(1.4)',
				onComplete: ctx.onComplete
			}
		);
	}

	/**
	 * Close the panel.
	 *
	 * @param {Object} ctx root, panel, isMobile, onComplete
	 */
	function close(ctx) {
		var panel = ctx.panel;

		gsap.killTweensOf(panel);

		if (ctx.isMobile) {
			gsap.to(panel, {
				yPercent: 100,
				duration: 0.3,
				ease: 'power2.in',
				onComplete: ctx.onComplete
			});
			return;
		}

		gsap.to(panel, {
			opacity: 0,
			y: 12,
			scale: 0.98,
			duration: 0.22,
			ease: 'power2.in',
			onComplete: ctx.onComplete
		});
	}

	/**
	 * Animate a newly appended message bubble into place.
	 *
	 * @param {HTMLElement} el Message element.
	 */
	function enterMessage(el) {
		gsap.fromTo(
			el,
			{ opacity: 0, y: 10, scale: 0.985 },
			{ opacity: 1, y: 0, scale: 1, duration: 0.3, ease: 'power3.out', clearProps: 'transform' }
		);
	}

	/**
	 * Stagger the suggestion chips in when the panel opens.
	 *
	 * @param {NodeList|Array} chips Chip elements.
	 */
	function enterChips(chips) {
		if (!chips || !chips.length) return;

		gsap.fromTo(
			chips,
			{ opacity: 0, y: 8 },
			{
				opacity: 1,
				y: 0,
				duration: 0.3,
				ease: 'power2.out',
				stagger: 0.04,
				clearProps: 'all'
			}
		);
	}

	/**
	 * Nudge the send button when a reply finishes arriving.
	 *
	 * @param {HTMLElement} el Element to pulse.
	 */
	function pulse(el) {
		if (!el) return;
		gsap.fromTo(
			el,
			{ scale: 1 },
			{ scale: 1.12, duration: 0.14, ease: 'power2.out', yoyo: true, repeat: 1, clearProps: 'transform' }
		);
	}

	/**
	 * Reset any inline transforms GSAP left behind, so the CSS rules can
	 * take the element back if the motion layer is disabled later.
	 *
	 * @param {HTMLElement} el Element to clear.
	 */
	function reset(el) {
		if (!el) return;
		gsap.set(el, { clearProps: 'all' });
	}

	window.yuniqAIMotion = {
		version: gsap.version || 'unknown',
		open: open,
		close: close,
		enterMessage: enterMessage,
		enterChips: enterChips,
		pulse: pulse,
		reset: reset
	};
})();
