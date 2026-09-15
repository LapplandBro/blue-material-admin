/* SourceBans JSON AJAX: fetch/XHR + JSON. Замена xajax 0.2.5. */
(function (window, document) {
	'use strict';

	/* Схемы, из которых браузер выполняет код. Проверяем на копии без
	   пробелов/управляющих символов: "java\tscript:" браузер тоже выполнит. */
	var BAD_SCHEME = /^(?:javascript|vbscript|livescript|mocha):/;
	var SAFE_DATA = /^data:image\/(?:png|jpe?g|gif|webp|bmp);base64,/;
	var IDENT = /^[A-Za-z_$][A-Za-z0-9_$]*$/;
	var URL_PROPS = {
		src: 1, href: 1, action: 1, formaction: 1, poster: 1,
		background: 1, lowsrc: 1, data: 1, codebase: 1
	};
	var URL_ATTRS = ['href', 'src', 'action', 'formaction', 'poster', 'background', 'data', 'lowsrc', 'xlink:href'];
	var BAD_CSS = /expression\s*\(|(?:javascript|vbscript)\s*:|url\s*\(\s*["']?\s*(?:javascript|vbscript|data)\s*:/i;

	function escHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	/* Возвращает исходный URL либо null, если он исполняемый. */
	function safeUrl(value) {
		var raw = value == null ? '' : String(value);
		var probe = raw.replace(/[\u0000-\u0020\u00a0\u2028\u2029]/g, '').toLowerCase();
		if (BAD_SCHEME.test(probe))
			return null;
		if (probe.indexOf('data:') === 0 && !SAFE_DATA.test(probe))
			return null;
		return raw;
	}

	/* Исходный URL, если он ведёт на свой же origin, иначе null. Относительный
	   адрес проверяем относительно document.baseURI — в layout.twig есть
	   <base href>, и fetch/<script src> резолвят именно по нему. */
	function sameOriginUrl(value) {
		var raw = safeUrl(value);
		if (raw === null || raw === '')
			return null;
		if (typeof window.URL !== 'function')
			return raw;
		try {
			var u = new window.URL(raw, document.baseURI || window.location.href);
			if (u.protocol !== 'http:' && u.protocol !== 'https:')
				return null;
			if (u.host !== window.location.host)
				return null;
			return raw;
		} catch (e) {
			return null;
		}
	}

	function parseFragment(html) {
		var tpl = document.createElement('template');
		if ('content' in tpl) {
			tpl.innerHTML = html;
			return { root: tpl.content, out: function () { return tpl.innerHTML; } };
		}
		var box = document.createElement('div');
		box.innerHTML = html;
		return { root: box, out: function () { return box.innerHTML; } };
	}

	/* Разметку с сервера не режем (админка сама вставляет таблицы, формы,
	   inline-onclick из шаблонов прав) — убираем только исполняемые куски:
	   <script>, <base>, srcdoc и javascript:/data:text/html в ссылках.
	   Если ничего опасного не нашлось, отдаём исходную строку без пересборки. */
	function sanitizeHtml(html) {
		var str = html == null ? '' : String(html);
		if (!str || (str.indexOf('<') < 0 && str.indexOf('&') < 0))
			return str;
		try {
			var frag = parseFragment(str);
			var nodes = frag.root.querySelectorAll ? frag.root.querySelectorAll('*') : [];
			var changed = false;
			var i, a, el, tag;
			for (i = 0; i < nodes.length; i++) {
				el = nodes[i];
				tag = (el.tagName || '').toLowerCase();
				if (tag === 'script' || tag === 'base') {
					if (el.parentNode) {
						el.parentNode.removeChild(el);
						changed = true;
					}
					continue;
				}
				if (el.hasAttribute && el.hasAttribute('srcdoc')) {
					el.removeAttribute('srcdoc');
					changed = true;
				}
				for (a = 0; a < URL_ATTRS.length; a++) {
					if (!el.hasAttribute || !el.hasAttribute(URL_ATTRS[a]))
						continue;
					if (safeUrl(el.getAttribute(URL_ATTRS[a])) === null) {
						el.removeAttribute(URL_ATTRS[a]);
						changed = true;
					}
				}
			}
			return changed ? frag.out() : str;
		} catch (e) {
			return escHtml(str);
		}
	}

	/* Косвенный eval: PHP-сниппеты (addScript) выполняются в глобальной области,
	   как это делал xajax, и не видят внутренние переменные sb-api. */
	function runScript(code) {
		return (0, eval)(String(code == null ? '' : code));
	}

	function failBox(title, detail) {
		var msg = String(title || 'Ошибка запроса');
		var extra = String(detail || '');
		if (extra.length > 800)
			extra = extra.substr(0, 800) + '…';
		if (extra)
			msg += '\n' + extra;
		if (typeof ShowBox === 'function')
			ShowBox('AJAX ошибка', escHtml(msg).replace(/\n/g, '<br>'), 'red', '', true);
		else
			alert(msg);
		if (document.body)
			document.body.style.cursor = 'default';
		if (typeof sbIdleLast === 'function')
			sbIdleLast();
		window._sbLoginBusy = false;
		var loginBtn = document.getElementById('alogin');
		if (loginBtn) {
			loginBtn.disabled = false;
			if (loginBtn.tagName === 'BUTTON' && loginBtn.getAttribute('data-sb-login-label') !== '1')
				loginBtn.textContent = 'Войти';
		}
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
		var low = prop.toLowerCase();
		if (prop === 'innerHTML')
			el.innerHTML = sanitizeHtml(value);
		else if (prop === 'outerHTML')
			el.outerHTML = sanitizeHtml(value);
		else if (prop === 'value')
			el.value = value == null ? '' : value;
		else if (prop.indexOf('style.') === 0) {
			if (!BAD_CSS.test(String(value == null ? '' : value)))
				el.style[prop.slice(6)] = value;
		} else if (/^on[a-z]/.test(low) || low === 'srcdoc' || low === '__proto__' || low === 'constructor' || low === 'prototype')
			return;
		else if (URL_PROPS[low] === 1) {
			var url = safeUrl(value);
			if (url !== null)
				el[prop] = url;
		} else
			el[prop] = value;
	}

	function elById(id) {
		if (id == null || id === '')
			return null;
		return document.getElementById(String(id));
	}

	/* Имя функции разбираем по точкам вместо eval(name): 'sbSessionApply',
	   'setTimeout', 'a.b' работают, произвольный код — нет. */
	function resolveFn(name) {
		var path = String(name == null ? '' : name).split('.');
		var scope = window;
		var ctx = window;
		var i;
		if (!path.length)
			return null;
		for (i = 0; i < path.length; i++) {
			if (ctx == null || !IDENT.test(path[i]))
				return null;
			scope = ctx;
			ctx = ctx[path[i]];
		}
		return typeof ctx === 'function' ? { fn: ctx, scope: scope } : null;
	}

	function callFn(name, args) {
		args = args || [];
		var target = resolveFn(name);
		if (!target)
			return;
		/* massban шлёт setTimeout("xajax_BanMemberOfGroup()", 25) — строку
		   выполняем через тот же runScript, а не неявным eval таймера. */
		if ((target.fn === window.setTimeout || target.fn === window.setInterval) && typeof args[0] === 'string') {
			var code = args[0];
			return target.fn.call(window, function () {
				runScript(code);
			}, args[1]);
		}
		return target.fn.apply(target.scope, args);
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
		var cmds = (data && Object.prototype.toString.call(data.cmds) === '[object Array]') ? data.cmds : [];
		var i, cmd, el, cur;
		if (document.body)
			document.body.style.cursor = 'default';
		for (i = 0; i < cmds.length; i++) {
			cmd = cmds[i];
			if (!cmd || typeof cmd !== 'object' || typeof cmd.n !== 'string')
				continue;
			try {
				if (cmd.n === 'js')
					runScript(cmd.data);
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
						el.innerHTML += sanitizeHtml(cmd.data);
					else {
						cur = el[cmd.p];
						setProp(el, cmd.p, (cur == null ? '' : cur) + cmd.data);
					}
				} else if (cmd.n === 'pp') {
					el = elById(cmd.t);
					if (!el)
						continue;
					if (cmd.p === 'innerHTML')
						el.innerHTML = sanitizeHtml(cmd.data) + el.innerHTML;
					else {
						cur = el[cmd.p];
						setProp(el, cmd.p, String(cmd.data == null ? '' : cmd.data) + (cur == null ? '' : cur));
					}
				} else if (cmd.n === 'rm') {
					el = elById(cmd.t);
					if (el && el.parentNode)
						el.parentNode.removeChild(el);
				} else if (cmd.n === 'in' && cmd.data) {
					var src = sameOriginUrl(cmd.data);
					if (!src)
						continue;
					var s = document.createElement('script');
					s.src = src;
					(document.head || document.getElementsByTagName('head')[0]).appendChild(s);
				}
			} catch (e) {}
		}
	}

	var inflight = {};
	var waitCount = 0;

	function waitDelta(n) {
		waitCount += n;
		if (waitCount < 0)
			waitCount = 0;
		var root = document.documentElement;
		if (!root || !root.classList)
			return;
		if (waitCount)
			root.classList.add('sb-ajax-wait');
		else {
			root.classList.remove('sb-ajax-wait');
			if (document.body)
				document.body.style.cursor = 'default';
		}
	}

	function csrfToken() {
		return (typeof window.SB_CSRF === 'string') ? window.SB_CSRF : '';
	}

	function requestUri() {
		var raw = (typeof window.SB_AJAX_URI === 'string' && window.SB_AJAX_URI) ? window.SB_AJAX_URI : 'index.php';
		return sameOriginUrl(raw);
	}

	function send(action, args) {
		if (!IDENT.test(String(action == null ? '' : action)))
			return false;

		var uri = requestUri();
		if (!uri) {
			failBox('Небезопасный адрес AJAX-запроса', '');
			return false;
		}

		var key;
		try {
			key = String(action) + JSON.stringify(args || []);
		} catch (e) {
			key = String(action);
		}
		if (inflight[key] && (Date.now() - inflight[key]) < 8000)
			return false;
		inflight[key] = Date.now();

		var payload = { action: action, args: args || [], csrf: csrfToken() };
		var body = JSON.stringify(payload);
		var headers = {
			'Content-Type': 'application/json; charset=utf-8',
			'X-Requested-With': 'XMLHttpRequest'
		};
		if (payload.csrf)
			headers['X-SB-CSRF'] = payload.csrf;
		waitDelta(1);

		function finished() {
			if (!inflight[key])
				return;
			delete inflight[key];
			waitDelta(-1);
		}

		function onText(status, text) {
			try {
				var data;
				try {
					data = JSON.parse(text);
				} catch (e) {
					failBox('Сервер вернул не JSON (HTTP ' + status + ')', text);
					return;
				}
				if (!data || typeof data !== 'object' || Object.prototype.toString.call(data) === '[object Array]') {
					failBox('Пустой ответ', text);
					return;
				}
				if (typeof data.cmds !== 'undefined' && Object.prototype.toString.call(data.cmds) !== '[object Array]') {
					failBox('Некорректный ответ сервера', text);
					return;
				}
				if (data.ok === false && (!data.cmds || !data.cmds.length) && data.error)
					failBox(data.error, '');
				applyCmds(data);
			} finally {
				finished();
			}
		}

		if (typeof fetch === 'function') {
			fetch(uri, {
				method: 'POST',
				credentials: 'same-origin',
				mode: 'same-origin',
				headers: headers,
				body: body
			}).then(function (r) {
				return r.text().then(function (t) {
					onText(r.status, t);
				});
			}).catch(function () {
				failBox('Сеть: запрос не удался', '');
				finished();
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
			finished();
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
				if (typeof n !== 'string' || !IDENT.test(n))
					continue;
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
