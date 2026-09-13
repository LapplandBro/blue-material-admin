/*
 * Bound the rel="boxed" heatmap thumbnails to SqueezeBox. Nothing loads this file (or
 * SqueezeBox.js) in blue_v2, and SqueezeBox.js is now a no-op stub, so the call is guarded:
 * without a real lightbox the thumbnails stay ordinary links to the full-size image.
 */
if (window.addEvent) {
	window.addEvent('domready', function () {
		if (window.SqueezeBox && typeof SqueezeBox.assign === 'function')
			SqueezeBox.assign($$('a[rel=boxed]'));
	});
}
