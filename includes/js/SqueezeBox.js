/*
 * SqueezeBox - Expandable Lightbox (MooTools More era) — NEUTRALIZED STUB.
 *
 * Not loaded anywhere: blue_v2 only ships scripts/mootools.js + scripts/sourcebans.js, and
 * class_table.php's rel="boxed" links simply stay plain links. The original implementation
 * was an active code-execution sink if it ever came back:
 *   - handlers.ajax used Request.HTML and then called $exec() on the response's script
 *     blocks whenever options.evalScripts was falsy (inverted check, so that was the default);
 *   - handlers.iframe / parsers.ajax loaded whatever URL came off an element's href;
 *   - content was injected with set('html', ...) straight from the response.
 *
 * The API surface is kept as no-ops so an accidental include (or a leftover
 * SqueezeBox.assign() call such as the one in heatmap.js) fails quietly instead of throwing.
 * Original contents are in git history for this path.
 */
var SqueezeBox = {

	presets: {},
	options: {},
	handlers: {},
	parsers: {},
	isOpen: false,

	initialize: function () { return this; },
	extend: function () { return this; },
	setOptions: function () { return this; },
	assign: function () { return false; },
	open: function () { return false; },
	fromElement: function () { return false; },
	close: function () { return this; },
	reposition: function () { return this; },
	addEvent: function () { return this; },
	addEvents: function () { return this; },
	removeEvent: function () { return this; },
	removeEvents: function () { return this; },
	fireEvent: function () { return this; }

};
