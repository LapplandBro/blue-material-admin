/*
 * Tabs - HLstatsX-era AJAX tab loader — NEUTRALIZED STUB.
 *
 * Not loaded by any template or page, and unrelated to the admin tabs the site actually uses
 * (ProcessAdminTabs/SwapPane live in scripts/sourcebans.js). The original fetched
 * hlstats.php?mode=...&type=ajax with URL parameters taken from its options, injected the
 * reply with set('html', txt) and then ran txt.stripScripts(true), which hands the response's
 * <script> blocks to $exec() — remote code execution on any tampered or proxied response.
 *
 * Kept as an inert no-op so an accidental include cannot throw or issue requests.
 * Original contents are in git history for this path.
 */
var Tabs = function () {};

Tabs.prototype = {
	togglers: [],
	elements: [],
	currentRequest: false,
	initialize: function () {},
	setOptions: function () { return this; },
	addTab: function () { return this; },
	updateTab: function () { return this; },
	refreshTab: function () { return this; },
	loadTab: function () { return this; }
};
