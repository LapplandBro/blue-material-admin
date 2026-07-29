/**
 * Минимальный JS установщика (jQuery + SweetAlert).
 * Legacy-шаги используют $('fieldId') как getElementById (стиль MooTools).
 */
(function (window) {
	'use strict';

	var jQ = window.jQuery.noConflict(true);
	window.jQuery = jQ;

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

	jQ(function () {
		jQ(document).on('keydown', function (e) {
			if (e.which === 13 && typeof window.sbInstallEnter === 'function') {
				e.preventDefault();
				window.sbInstallEnter();
			}
		});
	});
})(window);
