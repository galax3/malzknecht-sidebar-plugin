/* Malzknecht Post-Sidebar — TOC- und Anker-Fix
 *
 * Zwei Aufgaben:
 *   1) Im Beitragsinhalt allen H2/H3/H4 ohne ID eine slugified ID setzen,
 *      kompatibel mit gaengigen TOC-Block-Generatoren (Umlaute behalten).
 *   2) Anker-Klicks (a[href^="#..."]) robust verarbeiten: decodeURIComponent
 *      + getElementById statt querySelector. Damit crashed kein Astra-
 *      Smooth-Scroll mehr an %-encodeten Umlauten.
 *
 * Laeuft nur auf Singular-Views (PHP-Side enqueued).
 */
(function () {
	'use strict';

	function slugify(text) {
		if (!text) return '';
		var s = String(text).toLowerCase().trim();
		// Whitespace, NBSP, Unterstriche zu Bindestrich
		s = s.replace(/[\s _]+/g, '-');
		// Erlaubt: Buchstaben (inkl. Unicode/Umlaute), Ziffern, Bindestrich
		try {
			s = s.replace(/[^\p{L}\p{N}\-]/gu, '');
		} catch (e) {
			// Falls Browser kein Unicode-Property-Escape unterstuetzt
			s = s.replace(/[^a-z0-9À-ſ\-]/g, '');
		}
		s = s.replace(/-{2,}/g, '-').replace(/^-+|-+$/g, '');
		return s;
	}

	function findContentContainer() {
		return (
			document.querySelector('.entry-content') ||
			document.querySelector('article .ast-article-single') ||
			document.querySelector('article') ||
			document.querySelector('main')
		);
	}

	function ensureHeadingIds() {
		var container = findContentContainer();
		if (!container) return;
		var headings = container.querySelectorAll('h2, h3, h4');
		var used = {};
		headings.forEach(function (h) {
			if (h.id) {
				used[h.id] = true;
				return;
			}
			var slug = slugify(h.textContent);
			if (!slug) return;
			// Bei Duplikaten ein -2, -3 dranhaengen
			var unique = slug;
			var n = 2;
			while (used[unique] || document.getElementById(unique)) {
				unique = slug + '-' + n;
				n++;
			}
			h.id = unique;
			used[unique] = true;
		});
	}

	/**
	 * Versucht, fuer TOC-Links, deren Target-ID noch nicht im DOM existiert,
	 * das passende Heading per Text-Match zu finden und die ID zu setzen.
	 * Faengt Slugify-Abweichungen zwischen TOC-Generator und unserem Slugify ab.
	 */
	function syncTocAnchorsByText() {
		var aside = document.querySelector('aside.widget_mps_post_sidebar');
		if (!aside) return;
		var container = findContentContainer();
		if (!container) return;
		var tocLinks = aside.querySelectorAll('a[href^="#"]');
		if (!tocLinks.length) return;
		var headings = Array.prototype.slice.call(container.querySelectorAll('h2, h3, h4'));

		tocLinks.forEach(function (link) {
			var raw = (link.getAttribute('href') || '').slice(1);
			if (!raw) return;
			var id;
			try { id = decodeURIComponent(raw); } catch (_) { id = raw; }
			if (document.getElementById(id)) return; // schon gesetzt

			var linkText = (link.textContent || '').trim().toLowerCase();
			if (!linkText) return;

			var match = null;
			for (var i = 0; i < headings.length; i++) {
				var ht = (headings[i].textContent || '').trim().toLowerCase();
				if (!ht) continue;
				// 1) exakter Match
				if (ht === linkText) { match = headings[i]; break; }
			}
			if (!match) {
				// 2) substring-Match (TOC kuerzt manchmal)
				for (var j = 0; j < headings.length; j++) {
					var ht2 = (headings[j].textContent || '').trim().toLowerCase();
					if (ht2.indexOf(linkText.slice(0, Math.min(40, linkText.length))) === 0) {
						match = headings[j]; break;
					}
				}
			}
			if (match && !match.id) {
				match.id = id;
			}
		});
	}

	function getStickyHeaderOffset() {
		var sel = [
			'.ast-primary-sticky-header-active .main-header-bar-wrap',
			'.ast-primary-sticky-header-active #masthead',
			'.ast-sticky-active .main-header-bar-wrap',
			'.ast-stick-the-bar-active .main-header-bar-wrap'
		];
		for (var i = 0; i < sel.length; i++) {
			var el = document.querySelector(sel[i]);
			if (el && el.offsetHeight > 0) return el.offsetHeight + 16;
		}
		return 0;
	}

	function onAnchorClick(e) {
		var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
		if (!a) return;
		var href = a.getAttribute('href');
		if (!href || href.charAt(0) !== '#' || href.length < 2) return;
		var raw = href.slice(1);
		var id;
		try { id = decodeURIComponent(raw); } catch (_) { id = raw; }
		var target = document.getElementById(id) || document.getElementById(raw);
		if (!target) return; // kein passendes Element — Browser-Standard

		e.preventDefault();
		// Verhindert dass Astras (und anderer) Smooth-Scroll auf den ungueltigen
		// %-encodeten Selektor crashed.
		if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

		var offset = getStickyHeaderOffset();
		var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
		try {
			window.scrollTo({ top: top, behavior: 'smooth' });
		} catch (_) {
			window.scrollTo(0, top);
		}
		// History updaten, ohne erneut zu navigieren
		try { history.pushState(null, '', '#' + id); } catch (_) {}
	}

	function init() {
		ensureHeadingIds();
		syncTocAnchorsByText();
		// Capture-Phase, damit wir vor Astras Listener feuern
		document.addEventListener('click', onAnchorClick, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
