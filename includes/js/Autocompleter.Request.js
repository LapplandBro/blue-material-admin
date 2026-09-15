/*
 * Autocompleter.Request (Harald Kirschner, MooTools More era) — NEUTRALIZED STUB.
 *
 * Not loaded by any template or page. The original issued Request.JSON / Request.HTML calls
 * to a caller-supplied URL and then re-injected each returned choice with
 * choice.set('html', this.markQueryValue(choice.innerHTML)), i.e. unencoded server output
 * straight back into the DOM as markup.
 *
 * Kept as inert no-ops so an accidental include cannot throw or issue requests.
 * Original contents are in git history for this path.
 */
window.Autocompleter = window.Autocompleter || function () {};

Autocompleter.Request = function () {};
Autocompleter.Request.prototype = {
	initialize: function () {},
	query: function () { return this; },
	queryResponse: function () { return this; }
};

Autocompleter.Request.JSON = function () {};
Autocompleter.Request.JSON.prototype = Autocompleter.Request.prototype;

Autocompleter.Request.HTML = function () {};
Autocompleter.Request.HTML.prototype = Autocompleter.Request.prototype;

Autocompleter.Ajax = {
	Base: Autocompleter.Request,
	Json: Autocompleter.Request.JSON,
	Xhtml: Autocompleter.Request.HTML
};
