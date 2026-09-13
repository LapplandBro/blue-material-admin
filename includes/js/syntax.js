/*
 * dp.SyntaxHighlighter 1.5 (Alex Gorbatchev, LGPL; LivePipe.net modifications)
 * — NEUTRALIZED STUB.
 *
 * Not loaded by any template or page. The original highlighter rebuilt every code block
 * through repeated `innerHTML +=` concatenation, opened popups it filled with
 * document.write(), and its "copy to clipboard" toolbar button injected an <embed> pointing
 * at dp.sh.ClipboardSwf — a Flash bridge that no longer works and that took its markup from
 * string concatenation.
 *
 * The namespace is kept with no-op entry points so an accidental include (or a leftover
 * dp.sh.HighlightAll() call) fails quietly. Original contents are in git history.
 */
var dp = {
	sh: {
		Toolbar: {
			Commands: {},
			Create: function () {},
			Command: function () {}
		},
		Utils: {
			CopyStyles: function () {}
		},
		RegexLib: {},
		Brushes: {},
		Strings: {},
		ClipboardSwf: null,
		Version: '1.5-stub',
		Highlighter: function () {},
		HighlightAll: function () {}
	}
};

dp.SyntaxHighlighter = dp.sh;
