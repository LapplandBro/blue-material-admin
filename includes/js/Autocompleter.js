/*
 * Autocompleter (Harald Kirschner, MooTools More era) — NEUTRALIZED STUB.
 *
 * Not loaded by any template or page. The original built its dropdown with
 * new Element('li', {'html': this.markQueryValue(token)}), and markQueryValue() only wrapped
 * the matched substring in a <span> without ever encoding the token, so every suggestion
 * coming back from the server was injected as markup.
 *
 * Constructors are kept as inert no-ops so an accidental include cannot throw, and so
 * Autocompleter.Request.js still has a base object to hang its own stubs off.
 * Original contents are in git history for this path.
 */
var Autocompleter = function () {};

Autocompleter.prototype = {
	initialize: function () {},
	setOptions: function () { return this; },
	attach: function () { return this; },
	detach: function () { return this; },
	prefetch: function () { return this; },
	query: function () { return this; },
	update: function () { return this; },
	show: function () { return this; },
	hide: function () { return this; },
	choiceSelect: function () { return this; },
	markQueryValue: function (str) { return str; },
	addChoiceEvents: function (el) { return el; }
};

Autocompleter.Base = Autocompleter;

var OverlayFix = function () {};
OverlayFix.prototype = {
	initialize: function () {},
	show: function () { return this; },
	hide: function () { return this; }
};
