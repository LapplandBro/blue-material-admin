/**
 * JS установщика: vanilla + SweetAlert. Без jQuery / MooTools / Waves.
 */
(function (window, document) {
	'use strict';

	window.$id = function (id) {
		return typeof id === 'string' ? document.getElementById(id) : id;
	};
	window.$ = window.$id;

	window.ShowBox = function (title, msg, color, redirect, noclose) {
		title = title || 'Сообщение';
		msg = (msg == null) ? '' : String(msg);
		var type = 'info';
		if (color === 'red') type = 'error';
		else if (color === 'green') type = 'success';
		var plain = msg.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '');
		function afterClose() {
			if (redirect && !noclose) window.location = redirect;
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
			if (e.key === 'Enter' && typeof window.sbInstallEnter === 'function') {
				e.preventDefault();
				window.sbInstallEnter();
			}
		});
	});
})(window, document);
