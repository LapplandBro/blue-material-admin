/*
 * Observer - Observe formelements for changes (Harald Kirschner) — NEUTRALIZED STUB.
 *
 * Not loaded by any template or page. Kept inert rather than live because the original also
 * published a global $equals() built on JSON.encode(), which pulls in MooTools' Hash-based
 * JSON implementation (whose companion JSON.decode() is an eval wrapper).
 *
 * Original contents are in git history for this path.
 */
var Observer = function () {};

Observer.prototype = {
	initialize: function () {},
	changed: function () { return this; },
	setValue: function () { return this; },
	onFired: function () { return this; },
	clear: function () { return this; },
	pause: function () { return this; },
	resume: function () { return this; },
	addEvent: function () { return this; },
	setOptions: function () { return this; }
};
