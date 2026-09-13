/*
 * Neutralized stub.
 *
 * This path used to hold a second, minified copy of MooTools 1.2.1 that nothing loads:
 * blue_v2 (themes/blue_v2/templates/layout.twig) loads scripts/mootools.js, the 1.2dev-sb2
 * fork the rest of the site is written against. Including both copies overwrote the native
 * Event/Function.prototype.bind/Array.prototype.contains implementations a second time and
 * re-added JSON.decode(), which runs eval() on the response body.
 *
 * The file is kept (install manifests and docs still reference the path) but must never
 * define anything. Original contents are in git history for this path.
 */
(function () {
	if (window.MooTools)
		return;

	if (window.console && typeof console.warn === 'function')
		console.warn('includes/js/mootools.js is a neutralized stub — load scripts/mootools.js instead.');
})();
