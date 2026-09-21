/**
 * JS установщика: vanilla + SweetAlert. Без jQuery / MooTools / Waves.
 */
(function (window, document) {
	'use strict';

	window.$id = function (id) {
		return typeof id === 'string' ? document.getElementById(id) : id;
	};

	/* Переход только по своему origin: javascript:/data:/чужой хост отбрасываем,
	   даже если адрес пришёл из поля установщика. */
	function safeRedirect(url) {
		var raw = (url == null) ? '' : String(url);
		if (raw === '')
			return null;
		var probe = raw.replace(/[\u0000-\u0020\u00a0\u2028\u2029]/g, '').toLowerCase();
		if (/^(javascript|vbscript|livescript|data):/.test(probe))
			return null;
		if (typeof window.URL !== 'function')
			return /^[a-z0-9+.-]*:/.test(probe) ? null : raw;
		try {
			var u = new window.URL(raw, window.location.href);
			if (u.protocol !== window.location.protocol || u.host !== window.location.host)
				return null;
			return u.href;
		} catch (e) {
			return null;
		}
	}

	window.ShowBox = function (title, msg, color, redirect, noclose) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				window.ShowBox(title, msg, color, redirect, noclose);
			});
			return;
		}
		title = (title == null || title === '') ? 'Сообщение' : String(title);
		msg = (msg == null) ? '' : String(msg);
		var type = 'info';
		if (color === 'red') type = 'error';
		else if (color === 'green') type = 'success';
		/* Диалог всегда текстовый (html: false), но сначала убираем содержимое
		   script/style, чтобы код не показывался как «сообщение установщика». */
		var plain = msg
			.replace(/<\s*(script|style)\b[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
			.replace(/<br\s*\/?>/gi, '\n')
			.replace(/<[^>]*>/g, '');
		var target = safeRedirect(redirect);
		function afterClose() {
			if (target && !noclose) window.location.href = target;
		}
		if (typeof window.swal === 'function') {
			window.swal({
				title: title,
				text: plain,
				type: type,
				html: false,
				confirmButtonText: 'OK'
			}, afterClose);
			return;
		}
		alert(title + '\n\n' + plain);
		afterClose();
	};

	document.addEventListener('DOMContentLoaded', function () {
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' || typeof window.sbInstallEnter !== 'function')
				return;
			var t = e.target;
			if (!t)
				return;
			var tag = (t.tagName || '').toUpperCase();
			if (tag === 'TEXTAREA')
				return;
			var typ = (t.type || '').toLowerCase();
			if ((tag === 'BUTTON' || tag === 'INPUT') && typ === 'button')
				return;
			e.preventDefault();
			window.sbInstallEnter();
		});
	});
})(window, document);
