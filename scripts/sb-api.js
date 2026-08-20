/* SourceBans JSON AJAX: fetch/XHR + JSON. Замена xajax 0.2.5. */
(function (window, document) {
	'use strict';

	function csrfToken() {
		return (typeof window.SB_CSRF === 'string') ? window.SB_CSRF : '';
	}

	function failBox(title, detail) {
		var msg = String(title || 'Ошибка запроса');
		var extra = String(detail || '');
		if (extra.length > 800)
			extra = extra.substr(0, 800) + '…';
		if (extra)
			msg += '\n' + extra;
		if (typeof ShowBox === 'function')
			ShowBox('AJAX ошибка', msg.replace(/\n/g, '<br>'), 'red', '', true);
		else
			alert(msg);
		if (document.body)
			document.body.style.cursor = 'default';
		var cmd = document.getElementById('cmd');
		if (cmd) {
			cmd.disabled = false;
			cmd.value = '';
		}
		var rconBtn = document.getElementById('rcon_btn');
		if (rconBtn)
			rconBtn.disabled = false;
	}

	function setProp(el, prop, value) {
		if (!el)
			return;
		prop = String(prop || '');
		if (prop === 'innerHTML')
			el.innerHTML = value == null ? '' : String(value);
		else if (prop === 'outerHTML')
			el.outerHTML = value == null ? '' : String(value);
		else if (prop === 'value')
			el.value = value == null ? '' : value;
		else if (prop.indexOf('style.') === 0)
			el.style[prop.slice(6)] = value;
		else
			el[prop] = value;
	}

	function elById(id) {
		if (id == null || id === '')
			return null;
		return document.getElementById(String(id));
	}

	function callFn(name, args) {
		args = args || [];
		var fn = null;
		if (name === 'setTimeout')
			fn = window.setTimeout;
		else if (typeof window[name] === 'function')
			fn = window[name];
		if (typeof fn !== 'function') {
			try {
				fn = eval(name);
			} catch (e) {
				fn = null;
			}
		}
		if (typeof fn === 'function')
			return fn.apply(window, args);
	}

	function asArgs(data) {
		if (data == null)
			return [];
		if (Object.prototype.toString.call(data) === '[object Array]')
			return data;
		if (typeof data === 'object' && typeof data.length === 'number') {
			var out = [], i;
			for (i = 0; i < data.length; i++)
				out.push(data[i]);
			return out;
		}
		return [data];
	}

	function applyCmds(data) {
		var cmds = (data && data.cmds) ? data.cmds : [];
		var i, cmd, el, cur;
		if (document.body)
			document.body.style.cursor = 'default';
		for (i = 0; i < cmds.length; i++) {
			cmd = cmds[i] || {};
			try {
				if (cmd.n === 'js')
					eval(cmd.data);
				else if (cmd.n === 'jc')
					callFn(cmd.t, asArgs(cmd.data));
				else if (cmd.n === 'as') {
					el = elById(cmd.t);
					if (el)
						setProp(el, cmd.p, cmd.data);
				} else if (cmd.n === 'ap') {
					el = elById(cmd.t);
					if (!el)
						continue;
					if (cmd.p === 'innerHTML')
						el.innerHTML += cmd.data == null ? '' : String(cmd.data);
					else {
						cur = el[cmd.p];
						setProp(el, cmd.p, (cur == null ? '' : cur) + cmd.data);
					}
				} else if (cmd.n === 'pp') {
					el = elById(cmd.t);
					if (!el)
						continue;
					if (cmd.p === 'innerHTML')
						el.innerHTML = String(cmd.data == null ? '' : cmd.data) + el.innerHTML;
					else {
						cur = el[cmd.p];
						setProp(el, cmd.p, String(cmd.data == null ? '' : cmd.data) + (cur == null ? '' : cur));
					}
				} else if (cmd.n === 'rm') {
					el = elById(cmd.t);
					if (el && el.parentNode)
						el.parentNode.removeChild(el);
				} else if (cmd.n === 'in' && cmd.data) {
					var s = document.createElement('script');
					s.src = String(cmd.data);
					document.getElementsByTagName('head')[0].appendChild(s);
				}
			} catch (e) {
				if (window.console && console.error)
					console.error('sb-api', cmd.n, cmd.t || cmd.p, e);
			}
		}
	}

	function send(action, args) {
		var uri = window.SB_AJAX_URI || 'index.php';
		var payload = { action: action, args: args || [], csrf: csrfToken() };
		var body = JSON.stringify(payload);
		var headers = {
			'Content-Type': 'application/json; charset=utf-8',
			'X-Requested-With': 'XMLHttpRequest'
		};
		if (payload.csrf)
			headers['X-SB-CSRF'] = payload.csrf;
		if (document.body)
			document.body.style.cursor = 'wait';

		function onText(status, text) {
			var data;
			try {
				data = JSON.parse(text);
			} catch (e) {
				failBox('Сервер вернул не JSON (HTTP ' + status + ')', text);
				return;
			}
			if (!data || typeof data !== 'object') {
				failBox('Пустой ответ', text);
				return;
			}
			if (data.ok === false && (!data.cmds || !data.cmds.length) && data.error)
				failBox(data.error, '');
			applyCmds(data);
		}

		if (typeof fetch === 'function') {
			fetch(uri, {
				method: 'POST',
				credentials: 'same-origin',
				headers: headers,
				body: body
			}).then(function (r) {
				return r.text().then(function (t) {
					onText(r.status, t);
				});
			}).catch(function () {
				failBox('Сеть: запрос не удался', '');
			});
			return true;
		}

		var xhr = new XMLHttpRequest();
		xhr.open('POST', uri, true);
		var h;
		for (h in headers) {
			if (headers.hasOwnProperty(h))
				xhr.setRequestHeader(h, headers[h]);
		}
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4)
				return;
			onText(xhr.status, xhr.responseText || '');
		};
		try {
			xhr.send(body);
		} catch (e) {
			failBox('Сеть: запрос не удался', String(e && e.message ? e.message : e));
			return false;
		}
		return true;
	}

	var api = {
		call: function (name, argsObj) {
			var args = [], i;
			if (argsObj && typeof argsObj.length === 'number') {
				for (i = 0; i < argsObj.length; i++)
					args.push(argsObj[i]);
			}
			return send(name, args);
		},
		register: function (names) {
			var i, n;
			if (!names || !names.length)
				return;
			for (i = 0; i < names.length; i++) {
				n = names[i];
				(function (action) {
					window['xajax_' + action] = function () {
						return api.call(action, arguments);
					};
				})(n);
			}
		}
	};

	window.sbApi = api;
})(window, document);
