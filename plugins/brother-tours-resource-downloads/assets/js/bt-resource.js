/**
 * Brother Tours resource popup controller.
 *
 * One controller, one instance, one set of listeners. The legacy implementation
 * had several overlapping popup functions competing to open the same modal;
 * this replaces all of them.
 *
 * Two deliberate departures from that implementation:
 *
 *   1. It does not open after 2 seconds. Two seconds interrupts before anyone
 *      has read a sentence, which reads as an ad. The timed trigger waits ~10s
 *      AND requires evidence of engagement, or 40% scroll depth, whichever
 *      lands first.
 *   2. It never calls alert(). Download and print feedback goes through a
 *      non-blocking toast that does not steal focus or block the page.
 *
 * Public API, matching the documented contract:
 *   BTResourcePopup.open(trigger) / .close() / .download() / .print() / .track()
 */
(function () {
	'use strict';

	var cfg = window.BTResourceConfig;
	if (!cfg || !cfg.resource || !cfg.resource.pdf_url) {
		return;
	}

	var resource = cfg.resource;
	var triggers = cfg.triggers || {};
	var i18n = cfg.i18n || {};

	var overlay = document.querySelector('[data-btrd-overlay]');
	var dialog = document.querySelector('[data-btrd-dialog]');
	var toastEl = document.querySelector('[data-btrd-toast]');
	if (!overlay || !dialog) {
		return;
	}

	var isOpen = false;
	var lastFocused = null;
	var autoShown = false;
	var engaged = false;
	var timerId = null;
	var toastTimer = null;

	var SESSION_KEY = 'bt_pdf_popup_' + resource.resource_id + '_shown';
	var SUPPRESS_KEY = 'bt_pdf_popup_' + resource.resource_id + '_until';

	/* ------------------------------------------------------------------ */
	/* Storage — never let a disabled-storage browser break the page.      */
	/* Safari in private mode throws on write; the popup should degrade to */
	/* "always allowed to open manually", not to a thrown exception.       */
	/* ------------------------------------------------------------------ */

	function safeGet(store, key) {
		try {
			return window[store].getItem(key);
		} catch (e) {
			return null;
		}
	}

	function safeSet(store, key, value) {
		try {
			window[store].setItem(key, value);
		} catch (e) {
			/* Storage unavailable. Suppression simply does not persist. */
		}
	}

	/** True when an automatic open is allowed right now. */
	function mayAutoOpen() {
		if (autoShown || isOpen) {
			return false;
		}
		if (safeGet('sessionStorage', SESSION_KEY)) {
			return false;
		}
		var until = safeGet('localStorage', SUPPRESS_KEY);
		if (until && Date.now() < parseInt(until, 10)) {
			return false;
		}
		return true;
	}

	function suppressFor(days) {
		if (!days) {
			return;
		}
		safeSet('localStorage', SUPPRESS_KEY, String(Date.now() + days * 86400000));
	}

	/* ------------------------------------------------------------------ */
	/* Analytics — uses whatever the site already loads. Never injects its  */
	/* own GA4/GTM/pixel; if neither exists the calls are simply no-ops.    */
	/* ------------------------------------------------------------------ */

	function track(event, extra) {
		var payload = {
			resource_id: resource.resource_id,
			resource_name: resource.resource_name,
			resource_category: resource.resource_category,
			landing_page: cfg.landing || location.pathname,
			page_type: 'landing'
		};
		if (extra) {
			Object.keys(extra).forEach(function (k) {
				payload[k] = extra[k];
			});
		}
		if (typeof window.gtag === 'function') {
			window.gtag('event', event, payload);
		}
		if (Array.isArray(window.dataLayer)) {
			payload.event = event;
			window.dataLayer.push(payload);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Toast — replaces the legacy alert() calls.                          */
	/* ------------------------------------------------------------------ */

	function toast(message) {
		if (!toastEl || !message) {
			return;
		}
		toastEl.textContent = message;
		toastEl.hidden = false;
		// Next frame, so the transition runs instead of being skipped.
		requestAnimationFrame(function () {
			toastEl.classList.add('is-visible');
		});
		window.clearTimeout(toastTimer);
		toastTimer = window.setTimeout(function () {
			toastEl.classList.remove('is-visible');
			window.setTimeout(function () {
				toastEl.hidden = true;
			}, 250);
		}, 4000);
	}

	/* ------------------------------------------------------------------ */
	/* Focus management                                                    */
	/* ------------------------------------------------------------------ */

	var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

	function focusable() {
		return Array.prototype.slice.call(dialog.querySelectorAll(FOCUSABLE)).filter(function (el) {
			return el.offsetParent !== null || el === document.activeElement;
		});
	}

	function trapFocus(event) {
		if (event.key !== 'Tab') {
			return;
		}
		var items = focusable();
		if (!items.length) {
			return;
		}
		var first = items[0];
		var last = items[items.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function onKeydown(event) {
		if (event.key === 'Escape') {
			event.preventDefault();
			api.close('escape');
			return;
		}
		trapFocus(event);
	}

	/* ------------------------------------------------------------------ */
	/* Body scroll lock                                                     */
	/*                                                                      */
	/* position:fixed is the only lock iOS Safari respects, but it scrolls  */
	/* the page to the top unless the offset is preserved and restored.     */
	/* ------------------------------------------------------------------ */

	var scrollY = 0;

	function lockScroll() {
		scrollY = window.scrollY || window.pageYOffset || 0;
		document.body.style.position = 'fixed';
		document.body.style.top = '-' + scrollY + 'px';
		document.body.style.left = '0';
		document.body.style.right = '0';
		document.body.style.width = '100%';
	}

	function unlockScroll() {
		document.body.style.position = '';
		document.body.style.top = '';
		document.body.style.left = '';
		document.body.style.right = '';
		document.body.style.width = '';
		window.scrollTo(0, scrollY);
	}

	/* ------------------------------------------------------------------ */
	/* Public API                                                           */
	/* ------------------------------------------------------------------ */

	var api = {
		open: function (trigger) {
			if (isOpen) {
				return;
			}
			isOpen = true;
			lastFocused = document.activeElement;

			overlay.hidden = false;
			requestAnimationFrame(function () {
				overlay.classList.add('is-open');
			});
			lockScroll();
			document.addEventListener('keydown', onKeydown, true);

			var items = focusable();
			if (items.length) {
				items[0].focus({ preventScroll: true });
			} else {
				dialog.setAttribute('tabindex', '-1');
				dialog.focus({ preventScroll: true });
			}

			if (trigger !== 'manual') {
				autoShown = true;
				safeSet('sessionStorage', SESSION_KEY, '1');
			}
			track('resource_popup_view', { trigger_type: trigger || 'manual' });
		},

		close: function (reason) {
			if (!isOpen) {
				return;
			}
			isOpen = false;
			overlay.classList.remove('is-open');
			document.removeEventListener('keydown', onKeydown, true);
			unlockScroll();

			// Wait for the transition before hiding, so it does not snap away.
			window.setTimeout(function () {
				if (!isOpen) {
					overlay.hidden = true;
				}
			}, 200);

			if (lastFocused && typeof lastFocused.focus === 'function') {
				// preventScroll matters: focusing the opening button would
				// otherwise scroll it back into view and undo the scroll
				// position unlockScroll() just restored.
				lastFocused.focus({ preventScroll: true });
			}

			// A dismissal is a signal. Respect it beyond this session.
			suppressFor(triggers.suppress_days);
			track('resource_popup_close', { trigger_type: reason || 'close' });
		},

		download: function (source) {
			// The anchor's own download attribute does the work; this only
			// records it and acknowledges it without blocking anything.
			track('resource_download', { trigger_type: source || 'popup' });
			toast(i18n.downloadToast);
			suppressFor(triggers.suppress_days);
			if (isOpen) {
				window.setTimeout(function () {
					api.close('downloaded');
				}, 600);
			}
		},

		print: function (source) {
			track('resource_print', { trigger_type: source || 'popup' });
			toast(i18n.openedToast);
		},

		view: function (source) {
			track('resource_view', { trigger_type: source || 'popup' });
			toast(i18n.openedToast);
		},

		track: track
	};

	window.BTResourcePopup = api;

	/* ------------------------------------------------------------------ */
	/* Wiring                                                               */
	/* ------------------------------------------------------------------ */

	overlay.addEventListener('mousedown', function (event) {
		// Only a click on the backdrop itself closes — not a drag that started
		// inside the dialog and released on the overlay.
		if (event.target === overlay) {
			api.close('overlay');
		}
	});

	document.addEventListener('click', function (event) {
		var closeBtn = event.target.closest('[data-btrd-close]');
		if (closeBtn) {
			event.preventDefault();
			api.close('button');
			return;
		}

		var openBtn = event.target.closest('[data-btrd-open]');
		if (openBtn) {
			event.preventDefault();
			// Manual opens always work, whatever the suppression state says.
			api.open('manual');
			return;
		}

		var dl = event.target.closest('[data-btrd-download]');
		if (dl) {
			api.download(dl.closest('[data-btrd-dialog]') ? 'popup' : 'inline');
			return;
		}

		var secondary = event.target.closest('[data-btrd-secondary]');
		if (secondary) {
			var mode = secondary.getAttribute('data-btrd-secondary');
			if (mode === 'print') {
				api.print(secondary.closest('[data-btrd-dialog]') ? 'popup' : 'inline');
			} else {
				api.view(secondary.closest('[data-btrd-dialog]') ? 'popup' : 'inline');
			}
		}
	});

	// Carry resource attribution into Build My Trip, so the operations lead can
	// be traced back to the guide that produced it.
	document.addEventListener('click', function (event) {
		var link = event.target.closest('a[href]');
		if (!link) {
			return;
		}
		var href = link.getAttribute('href') || '';
		if (href.indexOf('build-my-trip') === -1) {
			return;
		}
		track('resource_cta_build_trip', {});
		try {
			var url = new URL(link.href, location.origin);
			if (!url.searchParams.has('source_resource')) {
				url.searchParams.set('source_resource', resource.resource_id);
			}
			if (!url.searchParams.has('trip_interest') && resource.trip_interest) {
				url.searchParams.set('trip_interest', resource.trip_interest);
			}
			link.href = url.toString();
		} catch (e) {
			/* Leave the link untouched rather than breaking navigation. */
		}
	});

	/* ------------------------------------------------------------------ */
	/* Triggers                                                             */
	/* ------------------------------------------------------------------ */

	function markEngaged() {
		engaged = true;
	}

	['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
		window.addEventListener(evt, markEngaged, { once: true, passive: true });
	});

	// Timed: waits for the delay AND for the visitor to have done something.
	// A tab opened and abandoned never triggers it.
	if (triggers.delay_ms) {
		timerId = window.setTimeout(function () {
			if (triggers.require_engagement && !engaged) {
				return;
			}
			if (mayAutoOpen()) {
				api.open('timed');
			}
		}, triggers.delay_ms);
	}

	// Scroll depth, whichever comes first.
	if (triggers.scroll_percent) {
		var onScroll = function () {
			var doc = document.documentElement;
			var scrollable = doc.scrollHeight - window.innerHeight;
			if (scrollable <= 0) {
				return;
			}
			var percent = ((window.scrollY || window.pageYOffset || 0) / scrollable) * 100;
			if (percent >= triggers.scroll_percent && mayAutoOpen()) {
				window.removeEventListener('scroll', onScroll);
				window.clearTimeout(timerId);
				api.open('scroll');
			}
		};
		window.addEventListener('scroll', onScroll, { passive: true });
	}

	// Exit intent, desktop pointers only — meaningless on touch, where moving
	// toward the top of the viewport is ordinary scrolling.
	if (triggers.exit_intent && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
		document.addEventListener('mouseout', function (event) {
			if (event.relatedTarget || event.clientY > 8) {
				return;
			}
			if (mayAutoOpen()) {
				api.open('exit');
			}
		});
	}
})();
