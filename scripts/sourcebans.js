// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************


/**
 * Legacy SourceBans / MooTools: $('id') == getElementById.
 * After jQuery loads, $ is hijacked — theme/settings saves silently no-op.
 * Use $id() for DOM-by-id.
 */
function $id(id) {
	if (id === null || id === undefined)
		return null;
	id = String(id);
	if (id === '')
		return null;
	return document.getElementById(id);
}

function sbSetChecked(id, on) {
	var el = $id(id);
	if (el) el.checked = !!on;
}

function sbSetValue(id, value) {
	var el = $id(id);
	if (el) el.value = value;
}

/** onclick: if (sbBusy(this)) return; xajax_... */
function sbBusy(el) {
	if (!el)
		return false;
	if ((el.getAttribute && el.getAttribute('data-sb-busy')) || el.disabled)
		return true;
	el.disabled = true;
	if (el.setAttribute)
		el.setAttribute('data-sb-busy', '1');
	return false;
}

function sbIdle(el) {
	if (!el)
		return;
	el.disabled = false;
	if (el.removeAttribute)
		el.removeAttribute('data-sb-busy');
	var lab = el.getAttribute && el.getAttribute('data-sb-label');
	if (lab) {
		el.textContent = lab;
		el.removeAttribute('data-sb-label');
	}
	if (el.id === 'amaintenance') {
		var sel = document.getElementById('maintenance');
		if (sel) {
			sel.disabled = false;
			if (sel._sbWrap) {
				var sbtn = sel._sbWrap.querySelector('.sb-select-btn');
				if (sbtn) sbtn.disabled = false;
			}
		}
	}
}

function sbIdleLast() {
	var b = document.querySelector('[data-sb-busy="1"]');
	if (b)
		sbIdle(b);
}

function sbSetDisplay(id, on) {
	var el = document.getElementById(id);
	if (el)
		el.style.display = on ? 'block' : 'none';
}

/** Сообщение сайта вместо window.alert (fallback на alert, если ShowBox ещё нет). */
function sbSiteAlert(msg, title, color)
{
	title = title || 'Сообщение';
	color = color || 'blue';
	msg = (msg == null) ? '' : String(msg);
	if (typeof ShowBox === 'function') {
		ShowBox(title, msg.replace(/\n/g, '<br>'), color, '', true);
		return;
	}
	alert(msg);
}

var ADMIN_LIST_ADMINS = 	(1<<0);

var ADMIN_ADD_ADMINS = 		(1<<1);

var ADMIN_EDIT_ADMINS = 	(1<<2);
var ADMIN_DELETE_ADMINS = 	(1<<3);

var ADMIN_LIST_SERVERS = 	(1<<4);
var ADMIN_ADD_SERVER = 		(1<<5);
var ADMIN_EDIT_SERVERS = 	(1<<6);
var ADMIN_DELETE_SERVERS = 	(1<<7);

var ADMIN_ADD_BAN = 		(1<<8);
var ADMIN_EDIT_OWN_BANS = 	(1<<10);
var ADMIN_EDIT_GROUP_BANS = (1<<11);
var ADMIN_EDIT_ALL_BANS = 	(1<<12);
var ADMIN_BAN_PROTESTS = 	(1<<13);
var ADMIN_BAN_SUBMISSIONS = (1<<14);
var ADMIN_DELETE_BAN = 		(1<<25);
var ADMIN_UNBAN = 			(1<<26);
var ADMIN_BAN_IMPORT =		(1<<27);
var ADMIN_UNBAN_OWN_BANS =	(1<<30);
var ADMIN_UNBAN_GROUP_BANS =(1<<31);

var ADMIN_NOTIFY_SUB =		(1<<28);
var ADMIN_NOTIFY_PROTEST =	(1<<29);

var ADMIN_LIST_GROUPS = 	(1<<15);
var ADMIN_ADD_GROUP = 		(1<<16);
var ADMIN_EDIT_GROUPS = 	(1<<17);
var ADMIN_DELETE_GROUPS = 	(1<<18);

var ADMIN_WEB_SETTINGS = 	(1<<19);

var ADMIN_LIST_MODS = 		(1<<20);
var ADMIN_ADD_MODS = 		(1<<21);
var ADMIN_EDIT_MODS = 		(1<<22);
var ADMIN_DELETE_MODS = 	(1<<23);

var ADMIN_OWNER = 			(1<<24);

var accordion;
var accordionInstances = {};

/**
 * ЧПУ на Caddy без rewrite открывает сломанную Material-тему.
 * Всегда query-string: admin/bans?x#y → index.php?p=admin&c=bans&x#y
 */
function sbAdminQs(url) {
	var orig = (url == null) ? '' : String(url).trim();
	if (orig === '' || orig.charAt(0) === '#')
		return orig;
	if (/^[a-z][a-z0-9+.-]*:/i.test(orig) || orig.indexOf('index.php') !== -1)
		return orig;

	var hash = '';
	var path = orig;
	var hi = path.indexOf('#');
	if (hi !== -1) {
		hash = path.substring(hi);
		path = path.substring(0, hi);
	}
	var q = '';
	var qi = path.indexOf('?');
	if (qi !== -1) {
		q = path.substring(qi + 1);
		path = path.substring(0, qi);
	}
	path = path.replace(/^\.\//, '').replace(/^\/+/, '').replace(/\/+$/, '');

	var pageNames = {
		banlist: true,
		commslist: true,
		servers: true,
		login: true,
		logout: true,
		submit: true,
		protest: true,
		account: true,
		lostpassword: true,
		login2fa: true,
		search_bans: true,
		search_comm: true,
		pay: true,
		adminlist: true,
		home: true,
		admin: true
	};

	if (path === 'admin')
		return 'index.php?p=admin' + (q !== '' ? ('&' + q) : '') + hash;

	var adminMatch = path.match(/^admin\/([^\/?#]+)$/);
	if (adminMatch)
		return 'index.php?p=admin&c=' + encodeURIComponent(adminMatch[1]) + (q !== '' ? ('&' + q) : '') + hash;

	var pageMatch = path.match(/^([a-zA-Z0-9_]+)(?:\/(\d+))?$/);
	if (pageMatch && pageNames[pageMatch[1]]) {
		var out = 'index.php?p=' + encodeURIComponent(pageMatch[1]);
		if (pageMatch[2])
			out += '&page=' + encodeURIComponent(pageMatch[2]);
		return out + (q !== '' ? ('&' + q) : '') + hash;
	}

	return orig;
}

/**
 * Абсолютный URL относительно <base href>.
 * Важно: window.location = 'index.php?…' / 'admin/admins' НЕ учитывает <base>,
 * и с /admin/admins уезжает в /admin/admin/admins (или /admin/index.php) → «главная».
 */
function sbAbs(url) {
	url = (url == null) ? '' : String(url).trim();
	if (url === '' || url.charAt(0) === '#')
		return url;
	url = sbAdminQs(url);
	// Уже абсолютный (http:, https:, …)
	if (/^[a-z][a-z0-9+.-]*:/i.test(url))
		return url;
	var baseEl = document.getElementsByTagName('base')[0];
	var origin = window.location.protocol + '//' + window.location.host;
	var base = (baseEl && baseEl.href) ? baseEl.href : (origin + '/');
	try {
		if (url.charAt(0) === '/')
			return new URL(url, origin).href;
		return new URL(url, base).href;
	} catch (e) {
		try {
			var a = document.createElement('a');
			a.href = url;
			return a.href;
		} catch (e2) {
			return url;
		}
	}
}

/** Переход с учётом <base href> (для onclick кнопок «Назад» и т.п.). */
function sbGo(url) {
	window.location.href = sbAbs(url);
}

/**
 * «Назад» внутри вкладок админки (#^N): не уходим на тот же URL без хэша
 * (после reload ProcessAdminTabs/SwapPane легко оставляют пустой экран),
 * а просто переключаем pane + hash.
 * tabId — номер вкладки (обычно 0 = список). fallbackUrl — если pane нет на странице.
 */
function sbAdminBack(tabId, fallbackUrl) {
	tabId = (tabId === undefined || tabId === null || tabId === '') ? 0 : tabId;
	var pane = document.getElementById(String(tabId));
	var tab = document.getElementById('tab-' + tabId);
	if (pane && tab && typeof SwapPane === 'function') {
		SwapPane(tabId);
		try {
			var dest = window.location.pathname + window.location.search + '#^' + tabId;
			if (window.history && history.replaceState)
				history.replaceState(null, '', dest);
			else
				window.location.hash = '^' + tabId;
		} catch (err) {}
		return;
	}
	sbGo(fallbackUrl || 'index.php?p=admin');
}

/** URL страницы SourceBans через index.php?p=… с учётом <base href>. */
function sbLoc(page, q) {
	page = String(page);
	q = (q == null) ? '' : String(q).replace(/^[&?]+/, '');
	return sbAbs(page + (q !== '' ? ('?' + q) : ''));
}

// <base href> ломает якоря href="#^N": браузер ведёт на главную (/#^N).
// Ловим SourceBans-вкладки: голый #^N и path#^N (admin/admins#^1).
// Если path+query другие (пагинация ppage/spage) — явный переход через sbAbs.
(function () {
	if (typeof document === 'undefined' || !document.addEventListener)
		return;
	document.addEventListener('click', function (e) {
		var a = e.target;
		while (a && a.nodeName !== 'A')
			a = a.parentNode;
		if (!a || !a.getAttribute)
			return;
		var href = a.getAttribute('href');
		if (!href)
			return;
		var hashIdx = href.indexOf('#^');
		if (hashIdx < 0)
			return;
		var hash = href.substring(hashIdx); // #^N или #^N~…
		var pathPart = href.substring(0, hashIdx);
		if (pathPart !== '') {
			try {
				var destHref = (typeof sbAbs === 'function') ? sbAbs(pathPart) : pathPart;
				var dest = new URL(destHref, window.location.href);
				var cur = new URL(window.location.href);
				if (dest.pathname !== cur.pathname || dest.search !== cur.search) {
					e.preventDefault();
					window.location.href = dest.href.split('#')[0] + hash;
					return;
				}
			} catch (err) {
				return;
			}
		}
		e.preventDefault();
		try {
			if (window.history && history.replaceState)
				history.replaceState(null, '', window.location.pathname + window.location.search + hash);
			else
				window.location.hash = hash.substring(1);
		} catch (err2) {}
		var tabMatch = hash.match(/^#\^(\d+)/);
		if (tabMatch && typeof SwapPane === 'function')
			SwapPane(tabMatch[1]);
	}, true);

	// Голый href="#id" с <base href> уводит на главную (/#id). Модалки игроков и якоря
	// остаются на текущей странице; Bootstrap всё ещё получит click на bubble.
	document.addEventListener('click', function (e) {
		var a = e.target;
		while (a && a.nodeName !== 'A')
			a = a.parentNode;
		if (!a || !a.getAttribute)
			return;
		var href = a.getAttribute('href');
		if (!href || href.charAt(0) !== '#' || href === '#' || href.indexOf('#^') === 0)
			return;
		if (/^[a-z][a-z0-9+.-]*:/i.test(href))
			return;
		e.preventDefault();
	}, true);
})();

(function () {
	function sbFixEmptyFormActions() {
		var forms = document.getElementsByTagName('form');
		var here = window.location.pathname + window.location.search;
		var i;
		for (i = 0; i < forms.length; i++) {
			var act = forms[i].getAttribute('action');
			if (act === null || act === '')
				forms[i].setAttribute('action', here);
		}
	}
	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', sbFixEmptyFormActions);
	else
		sbFixEmptyFormActions();
})();

function ProcessAdminTabs()
{
	var url = window.location.toString();
	var tabNo = -1;
	var tabMatch = url.match(/#\^(\d+)/);
	if (tabMatch) {
		tabNo = tabMatch[1];
		// getElementById('0') is valid; tab-0 / pane 0 must not be treated as missing.
		if (tabNo !== '' && (document.getElementById('tab-' + tabNo) || document.getElementById(tabNo)))
			SwapPane(tabNo);
		else
			tabNo = -1;
	}

	if (tabNo === -1) {
		var current = document.querySelector('.admin-pane.is-on');
		var first = document.querySelector('.admin-embed-body .admin-pane')
			|| document.querySelector('.admin-pane')
			|| document.getElementById('0');
		if (current && String(current.id) !== '')
			SwapPane(current.id);
		else if (first && first.classList && first.classList.contains('admin-pane'))
			SwapPane(String(first.id) !== '' ? first.id : '0');
		else if (first)
			first.style.display = 'block';
	}

	var upos = url.indexOf('~');
	if (upos !== -1) {
		var utabType = url.charAt(upos + 1);
		var utabNo = url.charAt(upos + 2);
		Swap2ndPane(utabNo, utabType);
	}

	return tabNo;
}

function sbReduceMotion()
{
	return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function sbPaneEnter(el)
{
	if (!el || !el.classList)
		return;
	el.classList.remove('sb-pane-enter');
	if (sbReduceMotion())
		return;
	void el.offsetWidth;
	el.classList.add('sb-pane-enter');
}

function Swap2ndPane(id, ttype)
{
	// Примечание: переключение вкладок сделано на чистом DOM API (без MooTools $/setStyle),
	// т.к. в современных браузерах расширение нативных элементов методами MooTools 1.2
	// иногда не срабатывает и ломает отображение вкладок (элемент остаётся display:none).
	var i = 0;
	var i2 = 0;
	if(document.getElementById("utab-" + ttype + id))
	{
		var paneEl;
		while((paneEl = document.getElementById(ttype + i)))
		{
			paneEl.style.display = 'none';
			i++;
		}
		while(i2 < 50)
		{
			var utabEl = document.getElementById("utab-" + ttype + i2);
			if(utabEl)
			{
				utabEl.classList.remove('active');
				utabEl.classList.add('nonactive');
			}
			i2++;
		}
		document.getElementById("utab-" + ttype + id).classList.add('active');
		var shown2 = document.getElementById(ttype + id);
		if (shown2) {
			shown2.style.display = 'block';
			sbPaneEnter(shown2);
		}
	}
}

function SwapPane(id)
{
	id = (id === undefined || id === null) ? '0' : String(id);
	var all = document.querySelectorAll('.admin-pane');
	var root = document.getElementById('admin-page-wrap')
		|| document.querySelector('.admin-embed-body')
		|| document.getElementById('cpanel')
		|| document.getElementById('admin-page-content');
	var scoped = (root && root.querySelectorAll) ? root.querySelectorAll('.admin-pane') : [];
	var panes = scoped.length ? scoped : all;
	var i;
	var show = document.getElementById(id);
	if (!show || !show.classList || !show.classList.contains('admin-pane')) {
		show = null;
		for (i = 0; i < panes.length; i++) {
			if (String(panes[i].id) === id) {
				show = panes[i];
				break;
			}
		}
	}
	if (!show && panes.length)
		show = panes[0];
	if (!show && all.length)
		show = all[0];

	if (show && panes.length) {
		var inList = false;
		for (i = 0; i < panes.length; i++) {
			if (panes[i] === show) {
				inList = true;
				break;
			}
		}
		if (!inList)
			panes = all;
	}

	if (panes.length) {
		for (i = 0; i < panes.length; i++) {
			if (panes[i] === show) {
				var wasOn = panes[i].classList.contains('is-on') && panes[i].style.display !== 'none';
				panes[i].style.display = 'block';
				panes[i].classList.add('is-on');
				if (!wasOn)
					sbPaneEnter(panes[i]);
			} else {
				panes[i].style.display = 'none';
				panes[i].classList.remove('is-on');
				panes[i].classList.remove('sb-pane-enter');
			}
		}
	} else if (show) {
		show.style.display = 'block';
		if (show.classList)
			show.classList.add('is-on');
	}

	var check = panes.length ? panes : all;
	var anyOn = false;
	for (i = 0; i < check.length; i++) {
		if (check[i].classList && check[i].classList.contains('is-on')) {
			anyOn = true;
			break;
		}
	}
	if (!anyOn && check.length) {
		check[0].style.display = 'block';
		check[0].classList.add('is-on');
	}

	for (i = 0; i < 50; i++) {
		var tabEl = document.getElementById('tab-' + i);
		if (tabEl)
			tabEl.classList.remove('active');
	}
	var tab = document.getElementById('tab-' + id);
	if (tab)
		tab.classList.add('active');
}

function sbAccEl(el) {
	return el && (el.nodeType === 1 || el.style) ? el : (el && el.element) || null;
}

function sbAccReveal(el) {
	var n = sbAccEl(el);
	if (!n || !n.style)
		return n;
	n.style.visibility = 'visible';
	n.style.opacity = '1';
	return n;
}

function sbAccMeasure(el) {
	var n = sbAccReveal(el);
	if (!n)
		return 0;
	if (n.scrollHeight > 1)
		return n.scrollHeight;
	var prev = n.style.height;
	n.style.height = 'auto';
	var h = n.offsetHeight;
	n.style.height = prev;
	return h || 0;
}

function sbAccPanelClosed(node) {
	node = sbAccEl(node);
	if (!node || !node.style)
		return true;
	if (node.style.height === '0px' || node.style.height === '0')
		return true;
	if (node.classList && node.classList.contains('is-open'))
		return false;
	if (node.style.visibility === 'hidden' || node.style.display === 'none')
		return true;
	return !node.offsetHeight;
}

function sbAccPinHeight(el) {
	var n = sbAccEl(el);
	if (!n || !n.style)
		return;
	if (n.style.height !== 'auto' && n.style.height !== '')
		return;
	if (n.offsetHeight > 0)
		n.style.height = n.offsetHeight + 'px';
}

function InitAccordion(opener, element, container, num)
{
	var key = opener + '|' + element + '|' + container;
	if (accordionInstances[key]) {
		if (num != null && num != -1)
			accordionInstances[key].display(num);
		return accordionInstances[key];
	}

	if (window.addEventListener && document.readyState !== 'complete') {
		window.addEventListener('load', function () {
			InitAccordion(opener, element, container, num);
		}, false);
	}

	if (num == null)
		num = -1;
	var wrap = document.getElementById(container);
	if (!wrap)
		return null;
	var togglers, panels;
	if (typeof wrap.getElements === 'function') {
		togglers = wrap.getElements(opener);
		panels = wrap.getElements(element);
	} else if (wrap.querySelectorAll) {
		togglers = wrap.querySelectorAll(opener);
		panels = wrap.querySelectorAll(element);
	} else
		return null;

	function accOpenClass(toggler, panel, on) {
		var nodes = [sbAccEl(toggler), sbAccEl(panel)], i, n;
		for (i = 0; i < nodes.length; i++) {
			n = nodes[i];
			if (n && n.classList)
				n.classList[on ? 'add' : 'remove']('is-open');
		}
	}
	function accStop(el) {
		el = sbAccEl(el);
		if (el && el._sbHTimer) {
			clearTimeout(el._sbHTimer);
			el._sbHTimer = null;
		}
		return el;
	}
	function accAuto(el) {
		el = sbAccEl(el);
		if (!el || !el.style || el.style.height === 'auto' || sbAccPanelClosed(el))
			return;
		el.style.height = 'auto';
		el.style.visibility = 'visible';
	}

	var ExtendedAccordion = Accordion.extend({
		hideAll: function () {
			var obj = {};
			this.previous = -1;
			this.elements.each(function (el, i) {
				obj[i] = { height: 0 };
				this.fireEvent('onBackground', [this.togglers[i], el]);
			}, this);
			return this.start(obj);
		},
		display: function (index) {
			if (skipAcc)
				return this;
			index = ($type(index) == 'element') ? this.elements.indexOf(index) : index;
			if ((this.timer && this.options.wait) || (index === this.previous && !this.options.alwaysHide))
				return this;
			this.previous = index;
			var obj = {};
			this.elements.each(function (el, i) {
				var hide = (i != index) || (this.options.alwaysHide && el.offsetHeight > 0);
				this.fireEvent(hide ? 'onBackground' : 'onActive', [this.togglers[i], el]);
				obj[i] = { height: hide ? 0 : sbAccMeasure(el) };
			}, this);
			return this.start(obj);
		}
	});

	var skipAcc = false;
	if (wrap.addEventListener) {
		wrap.addEventListener('click', function (e) {
			skipAcc = false;
			var t = e.target;
			if (!t) return;
			if (t.nodeType === 3) t = t.parentNode;
			if (!t) return;
			var tag = (t.nodeName || '').toUpperCase();
			if (tag === 'INPUT' || tag === 'A' || tag === 'BUTTON' || tag === 'SELECT' || tag === 'TEXTAREA' || tag === 'LABEL')
				skipAcc = true;
			else if (t.closest && t.closest('input, a, button, select, textarea, label, .banlist-td-check, .banlist-check-hit, .commslist-td-check'))
				skipAcc = true;
			if (skipAcc)
				window.setTimeout(function () { skipAcc = false; }, 0);
		}, true);
	}

	accordion = new ExtendedAccordion(togglers, panels, {
		opacity: false,
		alwaysHide: true,
		display: false,
		show: false,
		transition: Fx.Transitions.Quart.easeOut,
		onActive: function (toggler, element) {
			if (toggler && toggler.style)
				toggler.style.cursor = 'pointer';
			var el = accStop(element);
			if (!el)
				return;
			sbAccReveal(el);
			accOpenClass(toggler, el, true);
			el._sbHTimer = window.setTimeout(function () {
				el._sbHTimer = null;
				accAuto(el);
			}, 550);
		},
		onBackground: function (toggler, element) {
			accStop(element);
			sbAccPinHeight(element);
			accOpenClass(toggler, element, false);
		},
		onComplete: function () {
			var acc = this;
			if (!acc.elements)
				return;
			acc.elements.each(function (el, i) {
				accStop(el);
				if (acc.previous == i)
					accAuto(el);
			});
		}
	});
	if (accordion && accordion.elements && accordion.elements.length) {
		var anyOpen = false;
		accordion.elements.each(function (el) {
			if (el && el.offsetHeight > 0)
				anyOpen = true;
		});
		if (anyOpen)
			accordion.hideAll();
	}
	if (num != null && num != -1 && accordion)
		accordion.display(num);

	accordionInstances[key] = accordion;
	return accordion;
}

function ScrollRcon()
{
	var objDiv = document.getElementById("rcon");
	objDiv.scrollTop = objDiv.scrollHeight;
	//alert(objDiv.scrollTop);
}

function Shrink(id, time, height)
{
	var myEffects = $(document.getElementById(id)).effects({duration: time, transition:Fx.Transitions.Bounce.easeOut});
	myEffects.start({'height': [height]});
}

function FadeElOut(id, time)
{
	var myEffects = $(id).effects({duration: time, transition:Fx.Transitions.Sine.easeOut});
	myEffects.start({'opacity': [0]});
	var d = id;
	setTimeout("$(document.getElementById('" + d + "')).setStyle('display', 'none');$(document.getElementById('" + d + "')).setOpacity(0);", time);
	
	return;
}
function FadeElIn(id, time)
{
	$(document.getElementById(id)).setStyle('display', 'block');
	var myEffects = $(id).effects({duration: time, transition:Fx.Transitions.Sine.easeIn});
	myEffects.start({'opacity': [1]});
	setTimeout("$(document.getElementById('" + id + "')).setOpacity(1);", time);
	return;
}
function FXShow(id)
{
	var el = document.getElementById(id);
	if (el) el.style.display = 'block';
}
function FXHide(id)
{
	var el = document.getElementById(id);
	if (el) el.style.display = 'none';
}
function DoLogin(redir)
{
	if (window._sbLoginBusy)
		return;

	function showLoginMsg(id, text, on) {
		var el = document.getElementById(id);
		if (!el)
			return;
		el.textContent = text || '';
		el.style.display = on ? 'block' : 'none';
	}

	var err = 0;
	var nopw = 0;
	var userEl = document.getElementById('loginUsername');
	var passEl = document.getElementById('loginPassword');
	if(!userEl || !userEl.value)
	{
		showLoginMsg('loginUsername.msg', 'Вы должны ввести логин!', true);
		err++;
	}else
	{
		showLoginMsg('loginUsername.msg', '', false);
	}

	if(!passEl || !passEl.value)
	{
		showLoginMsg('loginPassword.msg', 'Вы должны ввести пароль!', true);
		nopw = 1;
	}else
	{
		showLoginMsg('loginPassword.msg', '', false);
	}

	if(err)
		return 0;

	if(redir == "undefined")
		redir = "";

	window._sbLoginBusy = true;
	var btn = document.getElementById('alogin');
	if (btn) {
		btn.disabled = true;
		if (btn.tagName === 'INPUT')
			btn.value = 'Вход…';
		else if (btn.tagName === 'BUTTON')
			btn.textContent = 'Вход…';
	}

	var rem = document.getElementById('loginRememberMe');
	xajax_Plogin(userEl.value,
				passEl.value,
				 rem ? rem.checked : false,
				 redir,
				 nopw);
	return false;
}

function SlideUp(id)
{
	var slider = new Fx.Slide(id);
	slider.slideOut().chain(
						function(){
							$(id).remove();
						}
		);
}

function RemoveGroup(id, name, type)
{
	var noPerm = confirm("Вы уверены, что хотите удалить группу: '" + name +"'?");
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveGroup(id, type);
}

function sbDeleteAdmin(btn, ev)
{
	ev = ev || window.event;
	if (ev) {
		if (ev.stopPropagation)
			ev.stopPropagation();
		ev.cancelBubble = true;
		if (ev.preventDefault)
			ev.preventDefault();
	}
	if (!btn)
		return false;
	var id = parseInt(btn.getAttribute("data-aid"), 10);
	var name = btn.getAttribute("data-name") || "";
	var warn = btn.getAttribute("data-warn") || "";
	if (!id)
		return false;
	if (warn && !confirm(warn))
		return false;
	RemoveAdmin(id, name);
	return false;
}

function sbRemoveAdminRow(aid)
{
	aid = String(aid == null ? "" : aid);
	if (!aid)
		return;
	var ids = ["aid_" + aid, "aid_" + aid + "_detail"], i, el;
	for (i = 0; i < ids.length; i++) {
		el = document.getElementById(ids[i]);
		if (el && el.parentNode)
			el.parentNode.removeChild(el);
	}
}

function RemoveAdmin(id, name)
{
	var noPerm = confirm("Вы уверены, что хотите удалить '" + name +"'?");
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveAdmin(id);
}

function RemoveSubmission(id, name, archiv)
{
	if(archiv == '2') {
		var noPerm = confirm("Вы уверены, что хотите восстановить запрос на бан игрока '" + name + "' из архива?");
	}
	else if(archiv == '1') {
		var noPerm = confirm("Вы уверены, что хотите перенести запрос на бан игрока '" + name +"' в архив?");
	}
	else {
		var noPerm = confirm("Вы уверены, что хотите удалить запрос на бан игрока '" + name +"'?");
	}
	if(noPerm == false)
		return;
		
	xajax_RemoveSubmission(id, archiv);
}

function RemoveProtest(id, name, archiv)
{
	if(archiv == '2') {
		var noPerm = confirm("Вы уверены, что хотите восстановить протест бана игрока '" + name + "' из архива?");
	}
	else if(archiv == '1') {
		var noPerm = confirm("Вы уверены, что хотите перенести протест бана игрока '" + name +"' в архив?");
	}
	else {
		var noPerm = confirm("Вы уверены, что хотите удалить протест бана игрока '" + name +"'?");
	}
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveProtest(id, archiv);
}

function RemoveServer(id, name)
{
	var noPerm = confirm("Вы уверены, что хотите удалить сервер: '" + name +"'?");
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveServer(id);
}

function RemoveBan(id, key, page, name, confirm, bulk)
{
	if(confirm==0) {
		ShowBox('Удалить бан', '<p>Вы уверены, что хотите удалить бан '+(bulk=="true"?"выбранных игроков":"игрока \'"+ name +"\'")+'?</p>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="RemoveBan(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'1\''+(bulk=="true"?", \'true\'":"")+');document.getElementById(\'rban\').disabled = true;" name="rban" class="btn btn-accent" id="rban" value="Удалить бан" />');
	} else if(confirm==1) {
		if(page != "") 
			var pagelink = page;
		else
			var pagelink = "";
		window.location = sbLoc("banlist", pagelink + "&a=delete&id="+ id +"&key="+ key +(bulk=="true"?"&bulk=true":""));
	}
}

function UnbanBan(id, key, page, name, popup, bulk)
{
	if(popup==1) {
		ShowBox('Разбан', '<div class="form-field"><label class="form-label" for="inputWarning2">Пожалуйста, напишите краткий комментарий, почему вы собираетесь разбанить '+(bulk=="true"?"этих игроков":"игрока \'"+ name +"\'")+'!</label><input type="text" class="form-control" id="inputWarning2" name="ureason"><p class="msg-err" id="ureason.msg"></p></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="if (UnbanBan(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\''+(bulk=="true"?", \'true\'":"")+')) document.getElementById(\'uban\').disabled = true;" name="uban" class="btn btn-accent" id="uban" value="Разбанить" />');
	} else if(popup==0) {
		if(page != "") 
			var pagelink = page;
		else
			var pagelink = "";
		reason = $('inputWarning2').value;
		if(reason == "") {
			$('ureason.msg').setHTML("Пожалуйста, оставьте комментарий.");
			$('ureason.msg').setStyle('display', 'block');
			return false;
		} else {
			$('ureason.msg').setHTML('');
			$('ureason.msg').setStyle('display', 'none');
		}
		window.location = sbLoc("banlist", pagelink + "&a=unban&id="+ id +"&key="+ key +"&ureason="+ reason +(bulk=="true"?"&bulk=true":""));
	}
	return true;
}

function BoxToSrvMask()
{	
	var string = "";
	if(document.getElementById('s1'))
	{
		if(document.getElementById('s1').checked)
			string += "a";
		if(document.getElementById('s23').checked)
			string +=  "b";
		if(document.getElementById('s2').checked)
			string += "c";
		if(document.getElementById('s3').checked)
			string += "d";
		if(document.getElementById('s4').checked)
			string += "e";
		if(document.getElementById('s5').checked)
			string += "f";
		if(document.getElementById('s6').checked)
			string += "g";
		if(document.getElementById('s7').checked)
			string += "h";
		if(document.getElementById('s8').checked)
			string += "i";
		if(document.getElementById('s9').checked)
			string += "j";
		if(document.getElementById('s10').checked)
			string += "k";
		if(document.getElementById('s11').checked)
			string += "l";
		if(document.getElementById('s12').checked)
			string += "m";
		if(document.getElementById('s13').checked)
			string += "n";
		if(document.getElementById('s17').checked)
			string += "o";
		if(document.getElementById('s18').checked)
			string += "p";
		if(document.getElementById('s19').checked)
			string += "q";
		if(document.getElementById('s20').checked)
			string += "r";
		if(document.getElementById('s21').checked)
			string += "s";
		if(document.getElementById('s22').checked)
			string += "t";
		if(document.getElementById('s14').checked)
			string += "z";
		var imm = document.getElementById('immunity');
		if(imm && imm.value)
			string += "#" + imm.value;
	}
	return string;
}

function BoxToMask()
{
	var Mask = 0;
	if(document.getElementById('p4'))
	{
		if(document.getElementById('p4').checked)
			Mask |= ADMIN_LIST_ADMINS;
		if(document.getElementById('p5').checked)
			Mask |= ADMIN_ADD_ADMINS;
		if(document.getElementById('p6').checked)
			Mask |= ADMIN_EDIT_ADMINS;
		if(document.getElementById('p7').checked)
			Mask |= ADMIN_DELETE_ADMINS;
			
		if(document.getElementById('p9').checked)
			Mask |= ADMIN_LIST_SERVERS;
		if(document.getElementById('p10').checked)
			Mask |= ADMIN_ADD_SERVER;
		if(document.getElementById('p11').checked)
			Mask |= ADMIN_EDIT_SERVERS;
		if(document.getElementById('p12').checked)
			Mask |= ADMIN_DELETE_SERVERS;
			
		if(document.getElementById('p14').checked)
			Mask |= ADMIN_ADD_BAN;
		if(document.getElementById('p16').checked)
			Mask |= ADMIN_EDIT_OWN_BANS;
		if(document.getElementById('p17').checked)
			Mask |= ADMIN_EDIT_GROUP_BANS;
		if(document.getElementById('p18').checked)
			Mask |= ADMIN_EDIT_ALL_BANS;
		if(document.getElementById('p19').checked)
			Mask |= ADMIN_BAN_PROTESTS;
		if(document.getElementById('p20').checked)
			Mask |= ADMIN_BAN_SUBMISSIONS;
		if(document.getElementById('p38').checked)
			Mask |= ADMIN_UNBAN_OWN_BANS;
		if(document.getElementById('p39').checked)
			Mask |= ADMIN_UNBAN_GROUP_BANS;
		if(document.getElementById('p32').checked)
			Mask |= ADMIN_UNBAN;
		if(document.getElementById('p33').checked)
			Mask |= ADMIN_DELETE_BAN;
		if(document.getElementById('p34').checked)
			Mask |= ADMIN_BAN_IMPORT;

		if(document.getElementById('p36').checked)
			Mask |= ADMIN_NOTIFY_SUB;
		if(document.getElementById('p37').checked)
			Mask |= ADMIN_NOTIFY_PROTEST;

		if(document.getElementById('p22').checked)
			Mask |= ADMIN_LIST_GROUPS;
		if(document.getElementById('p23').checked)
			Mask |= ADMIN_ADD_GROUP;
		if(document.getElementById('p24').checked)
			Mask |= ADMIN_EDIT_GROUPS;
		if(document.getElementById('p25').checked)
			Mask |= ADMIN_DELETE_GROUPS;
			
		if(document.getElementById('p26').checked)
			Mask |= ADMIN_WEB_SETTINGS;
			
		if(document.getElementById('p28').checked)
			Mask |= ADMIN_LIST_MODS;
		if(document.getElementById('p29').checked)
			Mask |= ADMIN_ADD_MODS;
		if(document.getElementById('p30').checked)
			Mask |= ADMIN_EDIT_MODS;
		if(document.getElementById('p31').checked)
			Mask |= ADMIN_DELETE_MODS;
			
		if(document.getElementById('p2').checked)
			Mask |= ADMIN_OWNER;
	}
	return Mask;
}

function UpdateCheckBox(tgl, start, stop)
{
	for(var i=start;i<=stop;i++)
	{
		if($('p' + i))
		{
			if($('p' + tgl).checked == true)
				$('p' + i).checked = true;
			else
				$('p' + i).checked = false;
		}	
	}

	// Other Arguments is individual items not available in the range
	if (arguments.length > 3)
	{
		for(var lp = 4; lp <= arguments.length; lp++)
		{
			if ($('p' + arguments[lp - 1]))
			{
				$('p' + arguments[lp - 1]).checked = $('p' + tgl).checked;
			}
		}
	}
	if (typeof SyncWebPermissionGroups === 'function')
		SyncWebPermissionGroups();
}

/** Группы веб-флагов: родительская галка ↔ все дочерние. */
function WebPermGroupDefs()
{
	return [
		{ parent: 3, kids: [4, 5, 6, 7] },
		{ parent: 8, kids: [9, 10, 11, 12] },
		{ parent: 13, kids: [14, 16, 17, 18, 19, 20, 32, 33, 34, 38, 39] },
		{ parent: 21, kids: [22, 23, 24, 25] },
		{ parent: 35, kids: [36, 37] },
		{ parent: 27, kids: [28, 29, 30, 31] }
	];
}

function SyncWebPermissionGroups()
{
	var groups = WebPermGroupDefs();
	for (var g = 0; g < groups.length; g++) {
		var parent = document.getElementById('p' + groups[g].parent);
		if (!parent)
			continue;
		var kids = groups[g].kids;
		var all = true;
		var found = 0;
		for (var i = 0; i < kids.length; i++) {
			var el = document.getElementById('p' + kids[i]);
			if (!el)
				continue;
			found++;
			if (!el.checked)
				all = false;
		}
		if (found === 0)
			continue;
		parent.checked = all;
		parent.indeterminate = false;
	}
}

function BindWebPermissionGroupSync()
{
	if (!window._webPermSyncBound) {
		window._webPermSyncBound = true;
		document.addEventListener('change', function (e) {
			var t = e.target;
			if (!t || !t.id || !/^p\d+$/.test(t.id))
				return;
			SyncWebPermissionGroups();
		}, true);
	}
	SyncWebPermissionGroups();
}

function ProcessGroup()
{
	try {
		var Mask = BoxToMask();
		var Smask = BoxToSrvMask();
		var nameEl = document.getElementById('groupname');
		var typeEl = document.getElementById('grouptype');
		xajax_AddGroup(nameEl ? nameEl.value : '', typeEl ? typeEl.value : '0', Mask, Smask);
	} catch (e) {
		if (typeof sbIdleLast === 'function')
			sbIdleLast();
		if (typeof ShowBox === 'function')
			ShowBox('Ошибка', 'Не удалось собрать права группы. Обновите страницу и повторите.', 'red', '', true);
	}
}

function update_web()
{
	$('webperm').setHTML('');
	
	if(document.getElementById('webg').value == "c" || document.getElementById('webg').value == "n") {
		$('web.msg').setHTML('Ждите...');
		$('web.msg').setStyle('display', 'block');
	}
	
	if(document.getElementById('webg').value == "c"){
		var block_p = "block";
	}else if(document.getElementById('webg').value == "n"){
		var block_p = "block";
	}else
	{
		$('webperm').setHTML('');
		var block_p = "none";
	}
	$('webperm').setStyle('display', block_p);
	
	if(document.getElementById('webg').value == "c" || document.getElementById('webg').value == "n")
		setTimeout("xajax_UpdateAdminPermissions(1, document.getElementById('webg').value)",1000);
	else {
		$('web.msg').setHTML('');
		$('web.msg').setStyle('display', 'none');
	}
}

function update_server_groups()
{
	$('nsgroup').setHTML('');
	
	if(document.getElementById('serverg').value == "n")
	{
		$('group.msg').setHTML('Ждите...');
		$('group.msg').setStyle('display', 'block');
		var height = 50;
		Shrink('nsgroup', 500, height);
		setTimeout("xajax_AddServerGroupName()",500);
	}
	else
	{
		height = 5;
		Shrink('nsgroup', 500, height);
		$('group.msg').setHTML('');
		$('group.msg').setStyle('display', 'none');
	}
}

function ProcessAddAdmin()
{
	var Mask = BoxToMask();
	var srvMask = BoxToSrvMask();
	var server_a_pass = "-1";
	var period;
	
	var el = document.getElementsByName('group[]');
	var grp = "";
  	for(i=0;i<el.length;i++){
    	if(el[i].checked){
       		grp = grp + "," + el[i].value;
    	}
  	}
  	
  	var el = document.getElementsByName('servers[]');
	var svr = "";
  	for(i=0;i<el.length;i++){
    	if(el[i].checked){
       		svr = svr + "," + el[i].value;
    	}
  	}
  	
    var serverg = document.getElementById('serverg').value;
  	if(serverg == "-3")
  	{
  		srvMask = "";
  	}
    var webg = document.getElementById('webg').value;
  	if(webg == "-3")
  	{
  		Mask = 0;
  	}
	
	if($('a_foreverperiod').checked) {
		period = "0";
	} else {
		period = document.getElementById('a_period').value;
	}
  	
  	if(document.getElementById('a_useserverpass').checked)
  		server_a_pass = document.getElementById('a_serverpass').value;
  
	if(document.getElementById('webname') && !document.getElementById('servername'))
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg, 
					server_a_pass,
					document.getElementById('webname').value,
					0,
					grp,
					svr,
					period,
					document.getElementById('discord').value,
					document.getElementById('comment').value,
					document.getElementById('vk').value); //server / server group
	else if(!document.getElementById('webname') && document.getElementById('servername'))
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg, 
					server_a_pass,
					0,
					document.getElementById('servername').value,
					grp,
					svr,
					period,
					document.getElementById('discord').value,
					document.getElementById('comment').value,
					document.getElementById('vk').value);
	else if(document.getElementById('webname') && document.getElementById('servername'))
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg, 
					server_a_pass,
					document.getElementById('webname').value,
					document.getElementById('servername').value,
					grp,
					svr,
					period,
					document.getElementById('discord').value,
					document.getElementById('comment').value,
					document.getElementById('vk').value);
	else
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg, 
					server_a_pass,
					0,
					0,
					grp,
					svr,
					period,
					document.getElementById('discord').value,
					document.getElementById('comment').value,
					document.getElementById('vk').value);

					
}

function ProcessEditAdminPermissions()
{
	var Mask = BoxToMask();
	var srvMask = BoxToSrvMask();
	var aid = $('admin_id').value;

	if($('immunity'))
	{
	 	if(IsNumeric($('immunity').value))
			xajax_EditAdminPerms(aid, Mask, srvMask);
		else
			ShowBox("Ошибка", "Значение иммунитета должно быть числовым (0–9).", "red", "", true);
	}else
		xajax_EditAdminPerms(aid, Mask, srvMask);
}

function ProcessEditGroup(type, name)
{
	
	var Mask = BoxToMask();
	var srvMask = BoxToSrvMask();
	var group = $('group_id').value;
	
	if(name == "")
	{
		ShowBox("Ошибка", "Вы должны ввести имя группы.", "red", "", true);
		$('groupname.msg').innerHTML = 'Введите имя группы.';
		$('groupname.msg').setStyle('display', 'block');
		return;
	}
	else
	{
		$('groupname.msg').innerHTML = '';
		$('groupname.msg').setStyle('display', 'none');
	}
	
	if($('immunity') && !IsNumeric($('immunity').value))
	{
		ShowBox("Ошибка", "Значение иммунитета должно быть числовым (0–9).", "red", "", true);
		return;
	}
	
	var overrides = [];
	var new_override = {};
	
	// Handle group overrides
	if(type == "srv")
	{
		var override_id = document.group_overrides_form.elements["override_id[]"];
		// Are there any old overrides to change?
		if(override_id != null)
		{
			var override_type = document.group_overrides_form.elements["override_type[]"];
			var override_name = document.group_overrides_form.elements["override_name[]"];
			var override_access = document.group_overrides_form.elements["override_access[]"];

			// Make sure they're arrays!
			if($type(override_id) == "element")
				override_id = [override_id];
			if($type(override_type) == "element")
				override_type = [override_type];
			if($type(override_name) == "element")
				override_name = [override_name];
			if($type(override_access) == "element")
				override_access = [override_access];
			
			overrides = new Array(override_id.length);
			
			for(var i=0;i<override_id.length;i++)
			{
				overrides[i] = {'id': override_id[i].value, 'type': override_type[i][override_type[i].selectedIndex].value, 'name': override_name[i].value, 'access': override_access[i][override_access[i].selectedIndex].value};
			}
		}
		
		new_override = {'type': $('new_override_type')[$('new_override_type').selectedIndex].value, 'name': $('new_override_name').value, 'access': $('new_override_access')[$('new_override_access').selectedIndex].value};
	}
	
	xajax_EditGroup(group, Mask, srvMask, type, name, overrides, new_override);
}

function update_server()
{
	$('serverperm').setHTML('');
	
	if(document.getElementById('serverg').value == "c" || document.getElementById('serverg').value == "n") {
		$('server.msg').setHTML('Ждите...');
		$('server.msg').setStyle('display', 'block');
	}
	
	if(document.getElementById('serverg').value == "c"){
		var block_p = "block";
	}else if(document.getElementById('serverg').value == "n"){
		var block_p = "block";
	}else
	{
		$('serverperm').setHTML('');
		var block_p = "none";
	}
	$('serverperm').setStyle('display', block_p);
	
	if(document.getElementById('serverg').value == "c" || document.getElementById('serverg').value == "n") 
		setTimeout("xajax_UpdateAdminPermissions(2, document.getElementById('serverg').value)",1000);
	else {
		$('server.msg').setHTML('');
		$('server.msg').setStyle('display', 'none');
	}
}

function process_add_server()
{
	var el = document.getElementsByName('groups[]');
	var grp = "";
  	for(i=0;i<el.length;i++){
    	if(el[i].checked){
       		grp = grp + "," + el[i].value;
    	}
  	}
	xajax_AddServer(document.getElementById('address').value, 
				document.getElementById('port').value, 
				document.getElementById('rcon').value, 
				document.getElementById('rcon2').value, 
				document.getElementById('mod').value, 
				document.getElementById('enabled').checked,
				grp, 
				-1);
	
}

function process_edit_server()
{
    if($('rcon').value != $('rcon2').value)
    {
        $('rcon2.msg').innerHTML = 'Пароли не совпадают.';
        $('rcon2.msg').setStyle('display', 'block');
        sbIdleLast();
        return;
    }
    
    $('rcon2.msg').setStyle('display', 'none');
	document.forms.editserver.submit();
}

function search_bans()
{
	var type = "";
	var input = "";
	if($('name').checked)
	{
		type = "name";
		input = $('nick').value;
	}
	if($('steam_').checked)
	{
		type = (document.getElementById('steam_match').value == "1" ? "steam" : "steamid");
		input = $('steamid').value;
	}
	if($('ip_').checked)
	{
		type = "ip";
		input = $('ip').value;
	}
	if($('reason_').checked)
	{
		type = "reason";
		input = $('ban_reason').value;
	}
	if($('date').checked)
	{
		type = "date";
		input = $('day').value + "," + $('month').value + "," + $('year').value;
	}
	if($('length_').checked)
	{
		type = "length";
		if($('length').value=="other")
			var length = $('other_length').value;
		else
			var length = $('length').value
		input = $('length_type').value + "," + length;
	}
	if($('ban_type_').checked)
	{
		type = "btype";
		input = $('ban_type').value;
	}
	if($('bancount').checked)
	{
		type = "bancount";
		input = $('timesbanned').value;
	}
	if($('admin').checked)
	{
		type = "admin";
		input = $('ban_admin').value;
	}
	if($('where_banned').checked)
	{
		type = "where_banned";
		input = $('server').value;
	}
	if($('comment_').checked)
	{
		type = "comment";
		input = $('ban_comment').value;
	}
	if(type!="" && input!="")
		window.location = sbLoc("banlist", "advSearch=" + input + "&advType=" + type);
	else
		ShowBox('Поиск', 'Укажите значение для поиска', 'blue', '', true);
}
var webSelected = new Array();
var srvSelected = new Array();
function getMultiple(ob, type) {
	if(type==1) {
		while (ob.selectedIndex != -1) 
		{ 
			webSelected.push(ob.options[ob.selectedIndex].value); 
			ob.options[ob.selectedIndex].selected = false; 
		}
	}
	if(type==2) {
		while (ob.selectedIndex != -1) 
		{ 
			srvSelected.push(ob.options[ob.selectedIndex].value); 
			ob.options[ob.selectedIndex].selected = false; 
		}
	}
}
function search_admins(chek)
{
	var type = "";
	var input = "";
	if(chek){
		var add_search = '&showexpiredadmins=true';
	}else{
		var add_search = '';
	}
	if($('name_').checked)
	{
		type = "name";
		input = $('nick').value;
	}
	if($('steam_').checked)
	{
		type = (document.getElementById('steam_match').value == "1" ? "steam" : "steamid");
		input = $('steamid').value;
	}
	if($('admemail_').checked)
	{
		type = "admemail";
		input = $('admemail').value;
	}
	if($('webgroup_').checked)
	{
		type = "webgroup";
		input = $('webgroup').value;
	}
	if($('srvadmgroup_').checked)
	{
		type = "srvadmgroup";
		input = $('srvadmgroup').value;
	}
	if($('srvgroup_').checked)
	{
		type = "srvgroup";
		input = $('srvgroup').value;
	}
	if($('admwebflags_').checked)
	{
		type = "admwebflag";
		input = webSelected.toString();
	}
	if($('admsrvflags_').checked)
	{
		type = "admsrvflag";
		input = srvSelected.toString();
	}
	if($('admin_on_').checked)
	{
		type = "server";
		input = $('server').value;
	}
	if(type!="" && input!="")
		window.location = sbLoc("admin/admins", "advSearch=" + encodeURIComponent(input) + "&advType=" + encodeURIComponent(type) + add_search);
	else
		ShowBox('Поиск', 'Укажите значение для поиска', 'blue', '', true);
}

function search_log()
{
	var type = "";
	var input = "";
	if($('admin_').checked)
	{
		type = "admin";
		input = $('admin').value;
	}
	if($('message_').checked)
	{
		type = "message";
		input = $('message').value;
	}
	if($('date_').checked)
	{
		type = "date";
		input = $('day').value + "," + $('month').value + "," + $('year').value + "," + $('fhour').value + "," + $('fminute').value + "," + $('thour').value + "," + $('tminute').value;
	}
	if($('type_').checked)
	{
		type = "type";
		input = $('type').value;
	}
	if(type!="" && input!="")
		window.location = sbLoc("admin/settings", "advSearch=" + input + "&advType=" + type) + "#^2";
	else
		ShowBox('Поиск', 'Укажите значение для поиска', 'blue', '', true);
}
var icname = "";
function icon(name)
{
	$('icon.msg').setHTML("Загружено: <b>" + name + "</b>");
	icname = name;
	if($('icon_hid'))
		$('icon_hid').value = name;
}
function ProcessMod()
{
	var err = 0;
	if(!$('name').value)
	{
		$('name.msg').setHTML('Вы должны ввести имя для создаваемого мода.');
		$('name.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('name.msg').setHTML('');
		$('name.msg').setStyle('display', 'none');
	}
	
	if(!$('folder').value)
	{
		$('folder.msg').setHTML('Вы должны ввести имя папки мода.');
		$('folder.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('folder.msg').setHTML('');
		$('folder.msg').setStyle('display', 'none');
	}

	if(err) {
		sbIdleLast();
		return 0;
	}

	xajax_AddMod($('name').value,
				 $('folder').value,
				 icname,
				 $('steam_universe').value,
				 $('enabled').checked);
}
/** GET-навигация на текущий URL (не reload — иначе F5 после POST шлёт старый CSRF). */
function sbForceGetReload() {
	var url = window.location.pathname + window.location.search + window.location.hash;
	window.location.replace(url);
}

/** CSRF / долгая вкладка: модалка Blue Admin с кнопкой перезагрузки. */
function sbCsrfExpired(msg) {
	msg = msg || "Страница открыта слишком долго — защитный токен устарел. Данные не сохранены.";
	if (typeof swal === "function") {
		swal({
			title: "Сессия устарела",
			text: msg,
			type: "warning",
			html: true,
			confirmButtonText: "Открыть страницу заново",
			confirmButtonClass: "btn-accent",
			showConfirmButton: true,
			showCancelButton: true,
			cancelButtonText: "Закрыть",
			allowOutsideClick: true
		}, function (isConfirm) {
			if (isConfirm)
				sbForceGetReload();
		});
		return;
	}
	if (window.confirm(msg + "\n\nОткрыть страницу заново?"))
		sbForceGetReload();
}

/** Сессия уже истекла — только перезагрузка, без «Продлить». */
function sbSessionExpired(msg) {
	msg = msg || "Сеанс формы завершён. Откройте страницу заново, затем повторите действие.";
	if (window.SB_SESSION) {
		window.SB_SESSION._expired = true;
		window.SB_SESSION._warned = true;
	}
	if (sbSessionSchedule._timer)
		clearTimeout(sbSessionSchedule._timer);
	if (sbSessionSchedule._ping)
		clearTimeout(sbSessionSchedule._ping);
	if (sbSessionSchedule._expire)
		clearTimeout(sbSessionSchedule._expire);
	sbCsrfExpired(msg);
}

/** Применить CSRF + таймеры сессии после PingSession / загрузки. */
function sbSessionApply(meta) {
	if (window.SB_SESSION)
		window.SB_SESSION._extending = false;
	if (sbSessionExtend._watch)
		clearTimeout(sbSessionExtend._watch);
	if (!meta || meta.ok === false) {
		if (meta && meta.expired)
			sbSessionExpired();
		else if (window.SB_SESSION && window.SB_SESSION._lastChance)
			sbSessionExpired();
		else
			sbSessionDialogIdle();
		return;
	}
	if (window.SB_SESSION && window.SB_SESSION._expired)
		return;
	if (typeof meta.expires_in === "number" && meta.expires_in <= 0) {
		sbSessionExpired();
		return;
	}
	if (meta.csrf) {
		window.SB_CSRF = meta.csrf;
		try {
			var nodes = document.querySelectorAll('input[name="sb_csrf"], input[name="csrf"]');
			for (var i = 0; i < nodes.length; i++)
				nodes[i].value = meta.csrf;
		} catch (e) {}
	}
	window.SB_SESSION = window.SB_SESSION || {};
	if (typeof meta.ttl === "number") window.SB_SESSION.ttl = meta.ttl;
	if (typeof meta.expires_in === "number") window.SB_SESSION.expires_in = meta.expires_in;
	if (typeof meta.warn_before === "number") window.SB_SESSION.warn_before = meta.warn_before;
	if (typeof meta.server_now === "number") window.SB_SESSION.server_now = meta.server_now;
	window.SB_SESSION._localDeadline = Date.now() + (Math.max(0, Number(meta.expires_in) || 0) * 1000);
	window.SB_SESSION._warned = false;
	window.SB_SESSION._expired = false;
	window.SB_SESSION._lastChance = false;
	sbSessionDialogClose();
	sbSessionSchedule();
}

function sbSessionDialogClose() {
	var ids = ["sb-session-dialog", "sb-session-overlay"];
	for (var i = 0; i < ids.length; i++) {
		var el = document.getElementById(ids[i]);
		if (el && el.parentNode)
			el.parentNode.removeChild(el);
	}
}

function sbSessionDialogIdle() {
	var btn = document.getElementById("sb-session-extend");
	if (btn) {
		btn.disabled = false;
		btn.textContent = "Продлить";
	}
}

function sbSessionExtend(opts) {
	opts = opts || {};
	window.SB_SESSION = window.SB_SESSION || {};
	if (window.SB_SESSION._expired && !opts.lastChance) {
		sbSessionExpired();
		return;
	}
	window.SB_SESSION._extending = true;
	window.SB_SESSION._lastChance = !!opts.lastChance;
	if (sbSessionExtend._watch)
		clearTimeout(sbSessionExtend._watch);
	sbSessionExtend._watch = setTimeout(function () {
		if (!window.SB_SESSION || !window.SB_SESSION._extending)
			return;
		window.SB_SESSION._extending = false;
		sbSessionDialogIdle();
		if (window.SB_SESSION._lastChance)
			sbSessionExpired();
	}, 12000);
	var btn = document.getElementById("sb-session-extend");
	if (btn && !opts.silent) {
		btn.disabled = true;
		btn.textContent = "Продляем…";
	}
	var pinged = false;
	if (window.sbApi && typeof window.sbApi.call === "function")
		pinged = !!window.sbApi.call("PingSession", []);
	else if (typeof xajax_PingSession === "function") {
		xajax_PingSession();
		pinged = true;
	}
	if (!pinged) {
		window.SB_SESSION._extending = false;
		if (sbSessionExtend._watch)
			clearTimeout(sbSessionExtend._watch);
		if (opts.lastChance)
			sbSessionExpired();
		else if (!opts.silent)
			window.location.reload();
		else
			sbSessionDialogIdle();
	}
}

function sbSessionWarn() {
	if (window.SB_SESSION && window.SB_SESSION._expired)
		return;
	if (document.getElementById("sb-session-dialog"))
		return;
	if (window.SB_SESSION)
		window.SB_SESSION._warned = true;

	var ov = document.createElement("div");
	ov.id = "sb-session-overlay";
	var box = document.createElement("div");
	box.id = "sb-session-dialog";
	box.setAttribute("role", "dialog");
	box.setAttribute("aria-labelledby", "sb-session-title");

	var ico = document.createElement("div");
	ico.className = "sb-sess-ico";
	ico.setAttribute("aria-hidden", "true");
	ico.textContent = "!";
	var title = document.createElement("h2");
	title.className = "sb-sess-title";
	title.id = "sb-session-title";
	title.textContent = "Сессия истекает";
	var text = document.createElement("p");
	text.className = "sb-sess-text";
	text.textContent = "Сеанс формы скоро завершится. Нажмите «Продлить», чтобы сохранить возможность отправлять формы без перезагрузки.";
	var actions = document.createElement("div");
	actions.className = "sb-sess-actions";
	var later = document.createElement("button");
	later.type = "button";
	later.className = "sb-sess-later";
	later.textContent = "Позже";
	later.onclick = function () {
		sbSessionDialogClose();
		if (window.SB_SESSION)
			window.SB_SESSION._warned = false;
	};
	var extend = document.createElement("button");
	extend.type = "button";
	extend.id = "sb-session-extend";
	extend.className = "sb-sess-extend";
	extend.textContent = "Продлить";
	extend.onclick = function () {
		sbSessionExtend({ fromDialog: true });
	};
	actions.appendChild(later);
	actions.appendChild(extend);
	box.appendChild(ico);
	box.appendChild(title);
	box.appendChild(text);
	box.appendChild(actions);
	document.body.appendChild(ov);
	document.body.appendChild(box);
	extend.focus();
}

function sbSessionSchedule() {
	if (sbSessionSchedule._timer)
		clearTimeout(sbSessionSchedule._timer);
	if (sbSessionSchedule._ping)
		clearTimeout(sbSessionSchedule._ping);
	if (sbSessionSchedule._expire)
		clearTimeout(sbSessionSchedule._expire);
	if (window.SB_SESSION && window.SB_SESSION._expired)
		return;
	var s = window.SB_SESSION || {};
	var deadline = s._localDeadline;
	if (!deadline && typeof s.expires_in === "number")
		deadline = Date.now() + (s.expires_in * 1000);
	if (!deadline)
		return;
	var warnBefore = Math.max(60, Number(s.warn_before) || 180) * 1000;
	var remaining = deadline - Date.now();
	var untilPing = Math.max(60000, (Number(s.ttl) || 1440) * 1000 / 3);

	if (remaining <= 0) {
		sbSessionExtend({ lastChance: true, silent: true });
		return;
	}

	sbSessionSchedule._expire = setTimeout(function () {
		if (window.SB_SESSION && window.SB_SESSION._extending)
			return;
		sbSessionExtend({ lastChance: true, silent: !!document.getElementById("sb-session-dialog") });
	}, remaining);

	var untilWarn = remaining - warnBefore;
	if (untilWarn <= 0)
		sbSessionWarn();
	else {
		sbSessionSchedule._timer = setTimeout(function () {
			sbSessionWarn();
		}, untilWarn);
	}

	if (untilWarn > 8000) {
		var pingIn = Math.min(untilPing, untilWarn - 5000);
		sbSessionSchedule._ping = setTimeout(function () {
			if (document.hidden)
				sbSessionSchedule();
			else
				sbSessionExtend({ silent: true });
		}, Math.max(5000, pingIn));
	}
}

(function sbSessionBoot() {
	function start() {
		var s = window.SB_SESSION || {};
		if (typeof s.expires_in === "number")
			sbSessionApply({
				ok: true,
				csrf: window.SB_CSRF || s.csrf || "",
				ttl: s.ttl,
				expires_in: s.expires_in,
				warn_before: s.warn_before,
				server_now: s.server_now
			});
	}
	if (document.readyState === "loading")
		document.addEventListener("DOMContentLoaded", start);
	else
		start();
	document.addEventListener("visibilitychange", function () {
		if (document.hidden || !window.SB_SESSION || !window.SB_SESSION._localDeadline)
			return;
		if (window.SB_SESSION._expired)
			return;
		var left = window.SB_SESSION._localDeadline - Date.now();
		if (left <= 0)
			sbSessionExpired();
		else if (left < (Math.max(60, Number(window.SB_SESSION.warn_before) || 180) * 1000))
			sbSessionWarn();
	});
})();

function ShowBoxWait(title, msg)
{
	ShowBox._wait = true;
	ShowBox(title, msg, "blue", "", true);
}

function ShowBox(title, msg, color, redir, noclose, timer)
{
	var waiting = !!ShowBox._wait;
	ShowBox._wait = false;
	if (!waiting && typeof sbIdleLast === "function")
		sbIdleLast();

	var type = "info";
	if (color == "red")
		type = "warning";
	else if (color == "blue")
		type = "info";
	else if (color == "green")
		type = "success";

	if (redir && typeof sbAbs === "function")
		redir = sbAbs(redir);

	ShowBox._anim = new Date().getTime();

	var hasSrvFrame = (msg && String(msg).indexOf("srvkicker") !== -1);
	msg = msg || "";

	// Старые вызовы передавали задержку 5-м аргументом (noclose), а не 6-м (timer).
	// noclose=true значит «не редиректить», а не «без кнопки закрытия».
	if (timer == null && noclose != null && noclose !== false && noclose !== true && noclose !== "") {
		var asDelay = parseInt(noclose, 10);
		if (asDelay > 0 && String(asDelay) === String(noclose).replace(/^\s+|\s+$/g, "")) {
			timer = asDelay;
			noclose = false;
		}
	}

	if (typeof swal !== "function")
		return;

	var ctrlPre = document.querySelector(".sweet-alert #dialog-control");
	if (ctrlPre)
		ctrlPre.innerHTML = "";

	var opts = {
		title: title || "",
		text: "\u00a0",
		type: type,
		allowOutsideClick: !waiting,
		confirmButtonText: "ОК",
		showConfirmButton: !waiting,
		showCancelButton: false,
		closeOnConfirm: true,
		containerClass: ((hasSrvFrame ? "sweet-alert-srv" : "") + (waiting ? " sweet-alert-wait" : "")).replace(/^\s+/, "")
	};
	if (timer)
		opts.timer = timer;

	swal(opts);

	function contentP(box) {
		var ps = box.querySelectorAll("p");
		var i;
		for (i = 0; i < ps.length; i++) {
			if (!ps[i].querySelector("button.confirm, button.cancel"))
				return ps[i];
		}
		return null;
	}

	function ensureDialogControl(box) {
		var ctrl = document.getElementById("dialog-control");
		if (!ctrl) {
			ctrl = document.createElement("span");
			ctrl.id = "dialog-control";
		}
		if (!box)
			return ctrl;
		var ok = box.querySelector("button.confirm");
		var row = ok && ok.parentNode ? ok.parentNode : box;
		if (ctrl.parentNode !== row)
			row.insertBefore(ctrl, ok || null);
		return ctrl;
	}

	function mountBox() {
		var box = document.querySelector(".sweet-alert");
		if (!box)
			return;

		var ok = box.querySelector("button.confirm");
		var cancel = box.querySelector("button.cancel");
		var ctrl = ensureDialogControl(box);
		var hasCustom = !waiting && !!(ctrl && ctrl.querySelector("input, button"));

		if (waiting)
			box.classList.add("sweet-alert-wait");
		else
			box.classList.remove("sweet-alert-wait");

		box.setAttribute("data-has-confirm-button", (waiting || hasCustom) ? "false" : "true");
		box.setAttribute("data-has-cancel-button", hasCustom ? "true" : "false");
		if (ok)
			ok.style.display = (waiting || hasCustom) ? "none" : "inline-block";
		if (cancel) {
			cancel.style.display = hasCustom ? "inline-block" : "none";
			if (hasCustom)
				cancel.textContent = "Отмена";
		}

		var pane = contentP(box);
		var extra = box.querySelector(".sweet-alert-body");
		var rich = /<(textarea|input|iframe|table|div|form)\b/i.test(msg);

		if (rich) {
			if (!extra) {
				extra = document.createElement("div");
				extra.className = "sweet-alert-body";
				if (pane && pane.parentNode)
					pane.parentNode.insertBefore(extra, pane.nextSibling);
				else
					box.appendChild(extra);
			}
			if (pane) {
				pane.innerHTML = "";
				pane.style.display = "none";
			}
			extra.style.display = "block";
			extra.innerHTML = msg;
		} else {
			if (extra) {
				extra.innerHTML = "";
				extra.style.display = "none";
			}
			if (pane) {
				pane.style.display = "block";
				pane.innerHTML = msg;
			}
		}

		if (hasCustom) {
			ctrl.className = "dialog-control-inline";
			ctrl.style.display = "";
		} else if (ctrl) {
			ctrl.style.display = "none";
		}

		var ifr = box.querySelector("#srvkicker");
		if (ifr) {
			ifr.style.width = "100%";
			ifr.style.border = "0";
			ifr.style.background = "#0c1528";
			ifr.style.backgroundColor = "#0c1528";
			ifr.style.color = "#ddeeff";
			if (!ifr.getAttribute("height") || parseInt(ifr.getAttribute("height"), 10) < 120)
				ifr.style.minHeight = "220px";
		}
	}

	ShowBox._gen = (ShowBox._gen || 0) + 1;
	var boxGen = ShowBox._gen;
	function mountGen() {
		if (boxGen !== ShowBox._gen)
			return;
		mountBox();
	}
	mountGen();
	if (typeof window.requestAnimationFrame === "function")
		requestAnimationFrame(mountGen);
	else
		setTimeout(mountGen, 0);
	setTimeout(mountGen, 0);

	// Auto-redirect only for simple notices (not server-sync modals — they redirect themselves).
	// Same path+query (типично settings#^N после POST) — location= не перезагружает страницу,
	// а $GLOBALS['config'] на этом запросе ещё старый → галочки «откатываются» без reload.
	if (redir && !noclose && !hasSrvFrame) {
		var delay = (parseInt(timer, 10) > 0) ? parseInt(timer, 10) : 2500;
		setTimeout(function () { sbNavigateOrReload(redir); }, delay);
	}
}
/**
 * Navigate to redir. Same path+query (settings tabs #^N) must NOT use location.reload():
 * after a POST save, reload() re-submits the form → infinite save/reload loop.
 * Force a fresh GET instead (one-shot _sb= busts the "same URL" no-op).
 */
function sbNavigateOrReload(redir)
{
	if (!redir || redir === "undefined")
		return;
	try {
		var abs = (typeof sbAbs === "function") ? sbAbs(redir) : redir;
		var a = document.createElement("a");
		a.href = abs;
		var targetBase = a.href.split("#")[0];
		var curBase = window.location.href.split("#")[0];
		var hash = a.hash || "";
		if (targetBase === curBase) {
			var clean = targetBase.replace(/([?&])_sb=\d+/g, "$1").replace(/[?&]$/, "").replace(/\?&/, "?");
			if (clean.slice(-1) === "?") clean = clean.slice(0, -1);
			var sep = clean.indexOf("?") >= 0 ? "&" : "?";
			window.location.replace(clean + sep + "_sb=" + Date.now() + hash);
			return;
		}
		window.location.replace(a.href);
		return;
	} catch (e) {}
	if (typeof sbGo === "function")
		sbGo(redir);
	else
		window.location = redir;
}
// Убрать одноразовый _sb= из адресной строки после принудительного GET.
(function () {
	try {
		if (!/[?&]_sb=\d+/.test(window.location.search))
			return;
		var q = window.location.search.replace(/([?&])_sb=\d+/g, "$1").replace(/[?&]$/, "").replace(/\?&/, "?");
		if (q === "?") q = "";
		history.replaceState(null, "", window.location.pathname + q + window.location.hash);
	} catch (e) {}
})();
function closeMsg(redir)
{
	if(redir.toString().length > 0 && redir != "undefined")
		sbNavigateOrReload(redir);
	else
	{
		FadeElOut('dialog-placement', 750);
	}
}

function TabToReload()
{
	var url = window.location.toString();
	var dest = url.replace("#^" + url[url.length-1],"");
	var tab = document.getElementById('admin_tab_0');
	if (!tab)
		return;
	tab.onclick = function () {
		window.location = dest;
	};
}


function toggleMCE(id) {
	var elm = document.getElementById(id);
	if (tinyMCE.getInstanceById(id) == null)
		tinyMCE.execCommand('mceAddControl', false, id);
	else
		tinyMCE.execCommand('mceRemoveControl', false, id);
}


function urlRusLat_r(txt) {
	transliterate = (
		function() {
			var
				rus = "щ   ш  ч  ц  ю  я  ё  ж  ъ  ы  э  а б в г д е з и й к л м н о п р с т у ф х ь С - П Ш".split(/ +/g),
				eng = "shh sh ch cz yu ya yo zh `` y' e` a b v g d e z i j k l m n o p r s t u f x ` S - P SH".split(/ +/g)
			;
			return function(text, engToRus) {
				var x;
				for(x = 0; x < rus.length; x++) {
					text = text.split(engToRus ? eng[x] : rus[x]).join(engToRus ? rus[x] : eng[x]);
					text = text.split(engToRus ? eng[x].toUpperCase() : rus[x].toUpperCase()).join(engToRus ? rus[x].toUpperCase() : eng[x].toUpperCase());	
				}
				return text;
			}
		}
	)();
	return transliterate(transliterate(txt), true);
}

function urlRusLat_e(txt) {
	transliterate = (
		function() {
			var
				rus = "вв ш  ч  ю  я  ё  ж  Ъ Ы э  а Б В в г д е з и Й к л м н о п р с т у ф Х ь С - . ^ А С Т Р О Б С Ц Н И Щ  Е П ц й".split(/ +/g),
				eng = "w  sh ch yu ya yo zh C H e` a B V v g d e z i J c l m n o p r s t u f X ` S - . ^ A S T R O B S Z N I CH E P c j".split(/ +/g)
			;
			return function(text, engToRus) {
				var x;
				for(x = 0; x < rus.length; x++) {
					text = text.split(engToRus ? eng[x] : rus[x]).join(engToRus ? rus[x] : eng[x]);
					text = text.split(engToRus ? eng[x].toUpperCase() : rus[x].toUpperCase()).join(engToRus ? rus[x].toUpperCase() : eng[x].toUpperCase());	
				}
				return text;
			}
		}
	)();
	return transliterate(txt);
}

function CheckEmail(type, id)
{
	var err = 0;
	if($('subject').value == "") {
		$('subject.msg').setHTML("Вы должны ввести тему письма.");
		$('subject.msg').setStyle('display', 'block');
		err++;
	} else {
		$('subject.msg').setHTML('');
		$('subject.msg').setStyle('display', 'none');
	}
		
	if($('message').value == "") {
		$('message.msg').setHTML("Вы должны ввести текст сообщения.");
		$('message.msg').setStyle('display', 'block');
		err++;
	} else {
		$('message.msg').setHTML('');
		$('message.msg').setStyle('display', 'none');
	}
		
	if(err>0)
		return;
	xajax_SendMail($('subject').value, $('message').value, type, id);
}

function IsNumeric(sText)
{
   var ValidChars = "0123456789.";
   var IsNumber=true;
   var Char;
 
	for (i = 0; i < sText.length && IsNumber == true; i++) 
	{ 
		Char = sText.charAt(i); 
  		if (ValidChars.indexOf(Char) == -1) 
		{
			IsNumber = false;
     	}
  	}
   	return IsNumber;
}

function ButtonOver(el)
{
}

function ClearLogs()
{
	var noPerm = confirm("Вы уверены, что хотите удалить все записи в журнале?");
	if(noPerm == false)
	{
		return;
	}
	var f = document.createElement("form");
	f.method = "POST";
	f.action = sbLoc("admin/settings") + "#^2";
	var a = document.createElement("input");
	a.type = "hidden";
	a.name = "log_clear";
	a.value = "true";
	f.appendChild(a);
	var c = document.createElement("input");
	c.type = "hidden";
	c.name = "sb_csrf";
	c.value = (typeof window.SB_CSRF === "string") ? window.SB_CSRF : "";
	f.appendChild(c);
	document.body.appendChild(f);
	f.submit();
}

function RemoveMod(name, id)
{
	var noPerm = confirm("Вы уверены, что хотите удалить '" + name +"'?");
	if(noPerm == false)
		return;
	xajax_RemoveMod(id);
}

function UpdateGroupPermissionCheckBoxes()
{
	var saveBtn = document.getElementById('agroup');
	if (saveBtn && typeof sbIdle === 'function')
		sbIdle(saveBtn);
	$('perms').setHTML('');
	if(document.getElementById('grouptype').value != 3 && document.getElementById('grouptype').value != 0) {
		$('type.msg').setHTML('Ждите...');
		$('type.msg').setStyle('display', 'block');
	}
	if(document.getElementById('grouptype').value != 3 && document.getElementById('grouptype').value != 0)
		setTimeout("xajax_UpdateGroupPermissions(document.getElementById('grouptype').value)",1000);
}

function changePage(newPage, type, advSearch, advType)
{		
	nextPage = newPage.options[newPage.selectedIndex].value
	if(advSearch!="" && advType !="") { 
		var searchlink = "advSearch="+encodeURIComponent(advSearch)+"&advType="+encodeURIComponent(advType); 
	} else { 
		var searchlink =""; 
	}
	 if (nextPage != 0)
	 {
		var pageQ = (searchlink ? (searchlink + "&") : "") + "page=" + nextPage;
		if(type == "A")
            window.location.href = sbLoc("admin/admins", pageQ);
		if(type == "B")
            window.location.href = sbLoc("banlist", pageQ);
		if(type == "C")
            window.location.href = sbLoc("commslist", pageQ);
		if(type == "L")
            window.location.href = sbLoc("admin/settings", pageQ) + "#^2";
        if(type == "P")
            sbGo("admin/bans?ppage=" + nextPage + "#^1");
        if(type == "PA")
            sbGo("admin/bans?papage=" + nextPage + "#^1~p1");
        if(type == "S")
            sbGo("admin/bans?spage=" + nextPage + "#^2");
        if(type == "SA")
            sbGo("admin/bans?sapage=" + nextPage + "#^2~s1");
	 }
}

function ShowKickBox(check, type)
{
	ShowBox('Бан добавлен', 'Бан был успешно добавлен<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check='+check+'&type='+type+'"></iframe>', 'green', '', false);
}

function ShowRehashBox(servers, title, msg, color, redir)
{
	if (typeof redir === "undefined" || redir === null || redir === "undefined")
		redir = "";
	if (redir && typeof sbAbs === "function")
		redir = sbAbs(redir);
	// Нет серверов для RCON — просто уведомление и редирект (redir через sbAbs/sbGo).
	if (servers == "" || servers == null)
	{
		ShowBox(title, msg, color, redir, false);
		return;
	}
	msg = msg + '<br /><hr /><i>Обновление данных администратора и группы по всем связанным серверам...</i><div id="rehashDiv" name="rehashDiv" width="100%"></div>';
	ShowBox(title, msg, color, '', false);
	$('dialog-control').setStyle('display', 'none');
	xajax_RehashAdmins(servers, 0, redir);
}

function ShowRehashBox_pay(servers, title, msg, color, redir, card)
{
	// Don't show anything sm_rehash related, if there are no servers to rcon.
	if(servers == '')
	{
		ShowBox(title, msg, color, 'index.php?p=account', false);
		$('dialog-control').setStyle('display', 'none');
	}else{
		msg = msg + '<br /><hr /><i>Обновление данных администратора и группы по всем связанным серверам...</i><div id="rehashDiv" name="rehashDiv" width="100%"></div>';
		ShowBox(title, msg, color, '', false);
		$('dialog-control').setStyle('display', 'none');
		xajax_RehashAdmins_pay(servers, card, 0);
	}
}

function ProcessComment()
{
	var err = 0;
	if($('commenttext').value == "")
	{
		$('commenttext.msg').setHTML('Введите комментарий');
		$('commenttext.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('commenttext.msg').setHTML('');
		$('commenttext.msg').setStyle('display', 'none');
		err = 0;
	}
	
	if(err)
		return 0;
	
	if($('cid').value == -1)
	{
		xajax_AddComment($('bid').value,
					 $('ctype').value,
					 $('commenttext').value,
					 $('page').value);
	}
	else
	{
		xajax_EditComment($('cid').value,
					 $('ctype').value,
					 $('commenttext').value,
					 $('page').value);
	}
}

function RemoveComment(cid, type, page)
{
	var checkUp = confirm("Вы уверены, что хотите удалить этот комментарий?");
	if(checkUp == false)
		return;
	xajax_RemoveComment(cid, type, page);
}


// drag and drop function, make the dialog window movable!
var ns4=document.layers;
var ie4=document.all;
var ns6=document.getElementById&&!document.all;

//NS 4
var dragswitch=0;
var nsx;
var nsy;
var nstemp;
function drag_drop_ns(name)
{
	if(!ns4)
		return;
	temp=eval(name);
	temp.captureEvents(Event.MOUSEDOWN | Event.MOUSEUP);
	temp.onmousedown=gons;
	temp.onmousemove=dragns;
	temp.onmouseup=stopns;
}
function gons(e)
{
	temp.captureEvents(Event.MOUSEMOVE);
	nsx=e.x;
	nsy=e.y;
}
function dragns(e)
{
	if(dragswitch==1) {
		temp.moveBy(e.x-nsx,e.y-nsy);
		return false;
	}
}
function stopns()
{
	temp.releaseEvents(Event.MOUSEMOVE);
}

//IE4 || NS6
function drag_drop(e)
{
	if(ie4&&dragapproved) {
		crossobj.style.left=tempx+event.clientX-offsetx+'px';
		crossobj.style.top=tempy+event.clientY-offsety+'px';
		return false;
	}
	else if(ns6&&dragapproved) {
		crossobj.style.left=tempx+e.clientX-offsetx+'px';
		crossobj.style.top=tempy+e.clientY-offsety+'px';
		return false;
	}
}
function initializiere_drag(e)
{
	crossobj=ns6? document.getElementById("dialog-placement") : document.all["dialog-placement"];
	var firedobj=ns6? e.target : event.srcElement;
	var topelement=ns6? "HTML" : "BODY";

	while (firedobj!=null&&firedobj.tagName!=topelement&&firedobj.id!="dragbar") {
		firedobj=ns6? firedobj.parentNode : firedobj.parentElement;
	}
	if(firedobj!=null&&firedobj.id=="dragbar")
	{
		offsetx=ie4? event.clientX : e.clientX;
		offsety=ie4? event.clientY : e.clientY;
		tempx=parseInt(crossobj.style.left);
		tempy=parseInt(crossobj.style.top);
		dragapproved=true;
		document.onmousemove=drag_drop;
	}

}
document.onmousedown=initializiere_drag;
document.onmouseup=new Function("dragapproved=false");

function TickSelectAll()
{
	var i, sw = $('tickswitch');
	var selectAll = !sw || String(sw.value) !== '1';
	for (i = 0; $('chkb_' + i); i++)
		$('chkb_' + i).checked = selectAll;
	if (!sw)
		return;
	sw.value = selectAll ? 1 : 0;
	if (sw.setProperty)
		sw.setProperty('title', selectAll ? 'Снять все' : 'Выбрать все');
	else
		sw.title = selectAll ? 'Снять все' : 'Выбрать все';
	if ($('tickswitchlink')) {
		$('tickswitchlink').addClass('alert-success');
		$('tickswitchlink').innerHTML = selectAll
			? 'Все баны на текущей странице были выделены.'
			: 'Выделение банов на текущей странице снято.';
		$('tickswitchlink').style.display = 'block';
		setTimeout("$('tickswitchlink').style.display = 'none';", 2500);
	}
	if ($('tickswitchlink_1'))
		$('tickswitchlink_1').innerHTML = selectAll
			? 'Выбрать все баны на текущей странице.'
			: 'Снять выделение банов на текущей странице.';
}

function BanlistBulkAction(sel, bankey)
{
	var n = 0, i = 0;
	for (i = 0; $('chkb_' + i); i++) {
		if ($('chkb_' + i).checked) n++;
	}
	if (!n) {
		sel.selectedIndex = 0;
		if (typeof ShowBox === 'function')
			ShowBox('Ничего не выбрано', 'Отметьте игроков галочками в списке, затем выберите «Удалить» или «Разбан».', 'blue', '', true);
		return;
	}
	BulkEdit(sel, bankey);
	sel.selectedIndex = 0;
}

function BulkEdit(action, bankey)
{
	var option = action.options[action.selectedIndex].value;
	var ids = [];
	var i;
	for (i = 0; $('chkb_' + i); i++) {
		if ($('chkb_' + i).checked === true)
			ids.push($('chkb_' + i).value);
	}
	switch (option)
	{
		case "U":
			UnbanBan(ids, bankey, "", "Разбанить всех", "1", "true");
		break;
		case "D":
			RemoveBan(ids, bankey, "", "Удалить всех", "0", "true");
		break;
	}
}

function sbMassBanUi(title) {
	title = title || 'Массовый бан';
	if (!document.getElementById('ban_progress_overlay')) {
		var overlay = document.createElement('div');
		overlay.id = 'ban_progress_overlay';
		document.body.appendChild(overlay);
	}
	var box = document.getElementById('ban_progress');
	if (!box) {
		box = document.createElement('div');
		box.id = 'ban_progress';
		box.innerHTML =
			'<div id="ban_progress_title" class="ban-title"></div>' +
			'<div id="ban_progress_group" class="ban-sub">Подготовка…</div>' +
			'<div id="ban_progress_meta" class="ban-sub">Инициализация…</div>' +
			'<div class="ban-bar-wrap"><div id="ban_progress_bar" class="ban-bar"></div></div>' +
			'<div class="ban-status"><span id="ban_progress_err">Ошибок: 0</span><span id="ban_progress_pct">0%</span></div>';
		document.body.appendChild(box);
	}
	var t = document.getElementById('ban_progress_title');
	if (t) t.textContent = title;
}

function sbMassBanProgress(opts) {
	opts = opts || {};
	var g = document.getElementById('ban_progress_group');
	var m = document.getElementById('ban_progress_meta');
	var e = document.getElementById('ban_progress_err');
	var p = document.getElementById('ban_progress_pct');
	var b = document.getElementById('ban_progress_bar');
	if (g && opts.line1 != null) g.textContent = String(opts.line1);
	if (m && opts.line2 != null) m.textContent = String(opts.line2);
	if (e) e.textContent = 'Ошибок: ' + (opts.errors != null ? opts.errors : 0);
	if (p) p.textContent = opts.label != null ? String(opts.label) : ((opts.percent != null ? opts.percent : 0) + '%');
	if (b) b.style.width = Math.max(0, Math.min(100, Number(opts.percent) || 0)) + '%';
}

function sbMassBanClear() {
	var ids = ['ban_progress', 'ban_progress_overlay', 'ban_result', 'ban_result_overlay'];
	for (var i = 0; i < ids.length; i++) {
		var el = document.getElementById(ids[i]);
		if (el && el.parentNode) el.parentNode.removeChild(el);
	}
}

function sbMassBanDone(opts) {
	opts = opts || {};
	var progress = document.getElementById('ban_progress');
	var progressOv = document.getElementById('ban_progress_overlay');
	if (progress && progress.parentNode) progress.parentNode.removeChild(progress);
	if (progressOv && progressOv.parentNode) progressOv.parentNode.removeChild(progressOv);
	if (document.getElementById('ban_result')) document.getElementById('ban_result').parentNode.removeChild(document.getElementById('ban_result'));
	if (document.getElementById('ban_result_overlay')) document.getElementById('ban_result_overlay').parentNode.removeChild(document.getElementById('ban_result_overlay'));

	var overlay = document.createElement('div');
	overlay.id = 'ban_result_overlay';
	var box = document.createElement('div');
	box.id = 'ban_result';

	var title = document.createElement('div');
	title.className = 'br-title';
	title.textContent = opts.title || 'Готово';
	var sub = document.createElement('div');
	sub.className = 'br-group';
	sub.textContent = opts.subtitle || '';
	var stats = document.createElement('div');
	stats.className = 'br-stats';
	function card(num, lbl) {
		var c = document.createElement('div');
		c.className = 'br-card';
		var n = document.createElement('div');
		n.className = 'br-num';
		n.textContent = String(num);
		var l = document.createElement('div');
		l.className = 'br-lbl';
		l.textContent = lbl;
		c.appendChild(n);
		c.appendChild(l);
		return c;
	}
	stats.appendChild(card(opts.banned != null ? opts.banned : 0, 'Забанено'));
	stats.appendChild(card(opts.errors != null ? opts.errors : 0, 'Ошибок'));
	stats.appendChild(card(opts.total != null ? opts.total : 0, 'Обработано'));
	var time = document.createElement('div');
	time.className = 'br-time';
	time.textContent = 'Время выполнения: ' + (opts.time || '0 сек');
	var actions = document.createElement('div');
	actions.className = 'br-actions';
	var reload = document.createElement('button');
	reload.type = 'button';
	reload.className = 'br-btn';
	reload.textContent = 'Обновить страницу';
	reload.onclick = function () { location.reload(); };
	var close = document.createElement('button');
	close.type = 'button';
	close.className = 'br-btn';
	close.textContent = 'Закрыть';
	close.onclick = function () { sbMassBanClear(); };
	actions.appendChild(reload);
	actions.appendChild(close);
	box.appendChild(title);
	box.appendChild(sub);
	box.appendChild(stats);
	box.appendChild(time);
	box.appendChild(actions);
	document.body.appendChild(overlay);
	document.body.appendChild(box);
}

function BanFriendsProcess(fid, name)
{
	var checkUp = confirm("Вы уверены, что хотите забанить всех друзей игрока '"+name+"'?");
	if(checkUp == false)
		return;
	if (typeof sbMassBanUi === 'function')
		sbMassBanUi('Блокировка друзей');
	xajax_BanFriends(fid, name);
}

function OpenMessageBox(sid, name, popup)
{
	if(popup==1) {
		ShowBox('Отправить сообщение', '<p class="sb-modal-lead">Сообщение для <strong></strong></p><p class="sb-modal-hint">Нужен плагин basechat.smx (<i>sm_psay</i>).</p><textarea rows="3" name="ingamemsg" id="ingamemsg" class="sb-modal-text" placeholder="Текст сообщения"></textarea><div id="ingamemsg.msg" class="badentry"></div>', 'blue', '', true);
		var fillWho = function () {
			var who = document.querySelector('.sweet-alert .sb-modal-lead strong');
			if (who && !who.firstChild)
				who.appendChild(document.createTextNode(name));
		};
		fillWho();
		setTimeout(fillWho, 50);
		$('dialog-control').setHTML('<input type="button" name="ingmsg" class="btn btn-accent" onmouseover="ButtonOver(\'ingmsg\')" onmouseout="ButtonOver(\'ingmsg\')" id="ingmsg" value="Отправить" />');
		$('dialog-control').setStyle('display', 'inline-block');
		$('ingmsg').addEvent('click', function(){OpenMessageBox(sid, name, 0);});
	} else if(popup==0) {
		message = $('ingamemsg').value;
		if(message == "") {
			$('ingamemsg.msg').setHTML("Пожалуйста, введите сообщение.");
			$('ingamemsg.msg').setStyle('display', 'block');
			return;
		} else {
			$('ingamemsg.msg').setHTML('');
			$('ingamemsg.msg').setStyle('display', 'none');
		}
		$('dialog-control').setStyle('display', 'none');
		ShowBox('Отправка сообщения', 'Идёт отправка запроса...', 'blue', '', false);
		$('ingamemsg').readOnly = true;
		xajax_SendMessage(sid, name, message);
	}
}

function KickPlayerConfirm(sid, name, conf)
{
	if(conf==1 && KickPlayerConfirm._busy)
		return;
	if(conf==0)	{
		if (typeof swal === "function") {
			swal({
				title: "Кик игрока",
				text: "Кикнуть «" + String(name).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + "» с сервера?",
				html: true,
				type: "warning",
				showCancelButton: true,
				showConfirmButton: true,
				confirmButtonText: "Кикнуть",
				cancelButtonText: "Отмена",
				closeOnConfirm: false,
				allowOutsideClick: true
			}, function (ok) {
				if (ok) KickPlayerConfirm(sid, name, 1);
			});
			return;
		}
		ShowBox('Кик игрока', '<b>Вы уверены, что хотите кикнуть игрока <br>\''+name+'\'?</b>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" name="kbutton" class="btn btn-accent" onmouseover="ButtonOver(\'kbutton\')" onmouseout="ButtonOver(\'kbutton\')" id="kbutton" value="Да" /> ');
		$('dialog-control').setStyle('display', 'inline-block');
		$('kbutton').addEvent('click', function(){KickPlayerConfirm(sid, name, 1);});
	} else if(conf==1) {
		KickPlayerConfirm._busy = true;
		ShowBox._anim = 0;
		var waitName = String(name).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
		ShowBox("Кик игрока", "Кикаем «" + waitName + "»…", "blue", "", true);
		var dc = $id("dialog-control");
		if (dc) dc.style.display = "none";
		if (typeof xajax_KickPlayer === "function")
			xajax_KickPlayer(sid, name);
		else
			sbSiteAlert("Не удалось вызвать кик. Обновите страницу (Ctrl+F5) и войдите снова.", "Ошибка", "red");
	}
}

function mapimg(filename)
{
	$('mapimg.msg').setHTML("<b>" + filename + "</b>");
	$('mapimg1.msg').style.display = "block";
}

function selectLengthTypeReason(length, type, reason)
{
	var bl = $('banlength');
	var tp = $('type');
	var lr = $('listReason');
	if (!bl || !tp || !lr)
		return;
	var i;
	for (i = 0; i < bl.options.length; i++) {
		if (bl.options[i].value == (length / 60)) {
			bl.options[i].selected = true;
			break;
		}
	}
	if (tp.options[type])
		tp.options[type].selected = true;
	for (i = 0; i < lr.options.length; i++) {
		if (lr.options[i].innerHTML == reason) {
			lr.options[i].selected = true;
			break;
		}
		if (lr.options[i].value == 'other') {
			if ($('txtReason'))
				$('txtReason').value = reason;
			if ($('dreason'))
				$('dreason').style.display = 'block';
			lr.options[i].selected = true;
			break;
		}
	}
}

function ViewCommunityProfile(sid, name)
{
    ShowBox('Просмотр профиля Steam Community', 'Создаём ссылку на профиль Steam Community «'+name+'», ждите...', 'blue', '', false);
    $('dialog-control').setStyle('display', 'none');
    xajax_ViewCommunityProfile(sid, name);
}

// Thanks to http://phpjs.org/functions/addslashes:303
function addslashes (str)
{
	return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

function RemoveBlock(id, key, page, name, confirm)
{
	if(confirm==0) {
		ShowBox('Удалить блокировку', 'Вы уверены, что хотите удалить блокировку игрока '+ name + '?', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="RemoveBlock(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'1\''+');document.getElementById(\'rban\').disabled = true;" name="rban" class="btn btn-accent" id="rban" value="Удалить блокировку" />');
	} else if(confirm==1) {
		if(page != "") 
			var pagelink = page;
		else
			var pagelink = "";
		window.location = sbLoc("commslist", pagelink + "&a=delete&id="+ id +"&key="+ key);
	}
}

function UnGag(id, key, page, name, popup)
{
	if(popup==1) {
		ShowBox('Причина включения чата', '<div class="form-field"><label class="form-label" for="inputWarning2">Пожалуйста, оставьте короткий комментарий, почему вы хотите включить чат игроку \''+ name +'\'.</label><input type="text" class="form-control" id="inputWarning2" name="ureason"><p class="msg-err" id="ureason.msg"></p></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="if (UnGag(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\')) document.getElementById(\'uban\').disabled = true;" name="uban" class="btn btn-accent" id="uban" value="Вкл. чат" />');
	} else if(popup==0) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		var reasonEl = document.getElementById('inputWarning2') || document.getElementById('ureason');
		var reason = reasonEl ? reasonEl.value : '';
		if(reason == "") {
			var msg = document.getElementById('ureason.msg');
			if (msg) { msg.innerHTML = "Оставьте комментарий."; msg.style.display = "block"; }
			return false;
		} else {
			var msg2 = document.getElementById('ureason.msg');
			if (msg2) { msg2.innerHTML = ''; msg2.style.display = 'none'; }
		}
		window.location = sbLoc("commslist", pagelink + "&a=ungag&id="+ id +"&key="+ key +"&ureason="+ encodeURIComponent(reason));
	}
	return true;
}

function UnMute(id, key, page, name, popup)
{
	if(popup==1) {
		ShowBox('Причина включения микрофона', '<div class="form-field"><label class="form-label" for="inputWarning2">Пожалуйста, оставьте короткий комментарий, почему вы хотите включить микрофон игроку \''+ name +'\'.</label><input type="text" class="form-control" id="inputWarning2" name="ureason"><p class="msg-err" id="ureason.msg"></p></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="if (UnMute(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\')) document.getElementById(\'uban\').disabled = true;" name="uban" class="btn btn-accent" id="uban" value="Вкл. микро" />');
	} else if(popup==0) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		var reasonEl = document.getElementById('inputWarning2') || document.getElementById('ureason');
		var reason = reasonEl ? reasonEl.value : '';
		if(reason == "") {
			var msg = document.getElementById('ureason.msg');
			if (msg) { msg.innerHTML = "Оставьте комментарий."; msg.style.display = "block"; }
			return false;
		} else {
			var msg2 = document.getElementById('ureason.msg');
			if (msg2) { msg2.innerHTML = ''; msg2.style.display = 'none'; }
		}
		window.location = sbLoc("commslist", pagelink + "&a=unmute&id="+ id +"&key="+ key +"&ureason="+ encodeURIComponent(reason));
	}
	return true;
}

function search_blocks()
{
	var type = "";
	var input = "";
	if($('name').checked)
	{
		type = "name";
		input = $('nick').value;
	}
	if($('steam_').checked)
	{
		type = (document.getElementById('steam_match').value == "1" ? "steam" : "steamid");
		input = $('steamid').value;
	}
	if($('reason_').checked)
	{
		type = "reason";
		input = $('ban_reason').value;
	}
	if($('date').checked)
	{
		type = "date";
		input = $('day').value + "," + $('month').value + "," + $('year').value;
	}
	if($('length_').checked)
	{
		type = "length";
		if($('length').value=="other")
			var length = $('other_length').value;
		else
			var length = $('length').value
		input = $('length_type').value + "," + length;
	}
	if($('ban_type_').checked)
	{
		type = "btype";
		input = $('ban_type').value;
	}
	if($('bancount').checked)
	{
		type = "bancount";
		input = $('timesbanned').value;
	}
	if($('admin').checked)
	{
		type = "admin";
		input = $('ban_admin').value;
	}
	if($('where_banned').checked)
	{
		type = "where_banned";
		input = $('server').value;
	}
	if($('comment_').checked)
	{
		type = "comment";
		input = $('ban_comment').value;
	}
	if(type!="" && input!="")
		window.location = sbLoc("commslist", "advSearch=" + input + "&advType=" + type);
	else
		ShowBox('Поиск', 'Укажите значение для поиска', 'blue', '', true);
}

function ShowBlockBox(check, type, length)
{
	ShowBox('Блокировка добавлена', 'Блокировка была успешно добавлена<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.blockit.php?check='+check+'&type='+type+'&length='+length+'"></iframe>', 'green', 'index.php?p=admin&c=comms', true);
}

function removeExpiredAdmins() {
	if(confirm("Удалить все истёкшие админки?")) {
	if(!confirm("Вы уверены? Все истёкшие админы будут удалены!")) { return false; }
	}else{ return false; }
	
	xajax_removeExpiredAdmins();
}

function ConvertSteamID_3to2(field) {
	var f = document.getElementById(field);
	if (f == undefined || f == null || f.value.indexOf("U:1:") == -1)
		return;

	var SID = f.value.split(":");
	if (SID.length == 3) {
		SID = SID[2];
		SID = SID.replace("]", "");
		SID = parseInt(SID);
		var Ost = SID % 2;
		SID = "STEAM_0:" + Ost + ":" + (SID-Ost)/2;
		f.value = SID;
	}
}

function sbPlayerPayload(p) {
	if (!p)
		return null;
	if (p.name == null && p.Name == null && p[2] != null)
		p = {
			sid: p[0],
			pid: p[1],
			name: p[2],
			frags: p[3],
			time: p[4],
			manage: p[5],
			banUrl: p[6],
			muteUrl: p[7]
		};
	return {
		sid: p.sid,
		pid: p.pid,
		name: p.name != null ? p.name : (p.Name || ""),
		frags: p.frags != null ? p.frags : p.Frags,
		time: p.time != null ? p.time : (p.Time || ""),
		manage: p.manage === true || p.manage === 1 || p.manage === "1" || p.manage === "true",
		banUrl: p.banUrl || "",
		muteUrl: p.muteUrl || ""
	};
}

function sbClosePlayerSheet() {
	var sheet = document.getElementById("sb-player-sheet");
	if (!sheet)
		return;
	if (sheet._sbCloseTimer) {
		clearTimeout(sheet._sbCloseTimer);
		sheet._sbCloseTimer = null;
	}
	sheet.classList.remove("is-open");
	sheet.setAttribute("aria-hidden", "true");
	document.body.classList.remove("sb-player-sheet-open");
	sheet._sbCloseTimer = setTimeout(function () {
		sheet.hidden = true;
		sheet._sbCloseTimer = null;
	}, 340);
}

function sbEnsurePlayerSheet() {
	var sheet = document.getElementById("sb-player-sheet");
	if (sheet)
		return sheet;
	sheet = document.createElement("div");
	sheet.id = "sb-player-sheet";
	sheet.className = "sb-player-sheet";
	sheet.hidden = true;
	sheet.setAttribute("role", "dialog");
	sheet.setAttribute("aria-modal", "true");
	sheet.setAttribute("aria-hidden", "true");
	sheet.innerHTML = '<div class="sb-player-sheet-backdrop" data-sb-player-close="1"></div>'
		+ '<div class="sb-player-sheet-card">'
		+ '<div class="sb-player-sheet-head">'
		+ '<h4 class="sb-player-sheet-title"></h4>'
		+ '<button type="button" class="btn-close btn-close-white sb-player-sheet-x" data-sb-player-close="1" aria-label="Закрыть"></button>'
		+ '</div>'
		+ '<div class="sb-player-sheet-list"></div>'
		+ '</div>';
	document.body.appendChild(sheet);
	sheet.addEventListener("click", function (ev) {
		var t = ev.target;
		if (t && t.getAttribute && t.getAttribute("data-sb-player-close"))
			sbClosePlayerSheet();
	});
	if (!window._sbPlayerSheetEsc) {
		window._sbPlayerSheetEsc = true;
		document.addEventListener("keydown", function (ev) {
			if (ev.key === "Escape" || ev.keyCode === 27)
				sbClosePlayerSheet();
		});
	}
	return sheet;
}

function sbFillPlayerSheet(p) {
	var sheet = sbEnsurePlayerSheet();
	var title = sheet.querySelector(".sb-player-sheet-title");
	var list = sheet.querySelector(".sb-player-sheet-list");
	while (title.firstChild)
		title.removeChild(title.firstChild);
	title.appendChild(document.createTextNode(p.name || "Игрок"));
	while (list.firstChild)
		list.removeChild(list.firstChild);

	function actBtn(label, cls, onClick) {
		var b = document.createElement("button");
		b.type = "button";
		b.className = "player-act" + (cls ? " " + cls : "");
		b.appendChild(document.createTextNode(label));
		b.addEventListener("click", function () {
			sbClosePlayerSheet();
			if (onClick)
				onClick();
		});
		list.appendChild(b);
	}

	actBtn("Кикнуть", "player-act--warn", function () { KickPlayerConfirm(p.sid, p.name, 0); });
	actBtn("Профиль Steam", "", function () { ViewCommunityProfile(p.sid, p.name); });
	actBtn("Забанить", "", function () {
		if (p.banUrl && typeof sbGo === "function")
			sbGo(p.banUrl);
		else if (p.banUrl)
			window.location.href = p.banUrl;
	});
	actBtn("Заглушить", "", function () {
		if (p.muteUrl && typeof sbGo === "function")
			sbGo(p.muteUrl);
		else if (p.muteUrl)
			window.location.href = p.muteUrl;
	});
	actBtn("Сообщение на сервер", "", function () { OpenMessageBox(p.sid, p.name, 1); });
	return sheet;
}

function sbOpenPlayerMenu(ev, el) {
	if (ev) {
		if (ev.preventDefault)
			ev.preventDefault();
		if (ev.stopPropagation)
			ev.stopPropagation();
		if (ev.stopImmediatePropagation)
			ev.stopImmediatePropagation();
	}
	if (!el)
		return false;
	var p = null;
	try {
		p = JSON.parse(el.getAttribute("data-player") || "null");
	} catch (err) {
		p = null;
	}
	p = sbPlayerPayload(p);
	if (!p || !p.manage)
		return false;
	var sheet = sbFillPlayerSheet(p);
	if (sheet._sbCloseTimer) {
		clearTimeout(sheet._sbCloseTimer);
		sheet._sbCloseTimer = null;
	}
	sheet.hidden = false;
	sheet.removeAttribute("aria-hidden");
	document.body.classList.add("sb-player-sheet-open");
	// Force layout so opacity/transform transitions actually run.
	void sheet.offsetWidth;
	sheet.classList.add("is-open");
	return false;
}

function sbClearServerPlayers(sid) {
	var list = document.getElementById("playerlist_" + sid);
	if (list && list.parentNode) {
		var empty = list.cloneNode(false);
		list.parentNode.replaceChild(empty, list);
	}
	var old = document.querySelectorAll(".player-act-modal[data-sid=\"" + sid + "\"]");
	var i;
	for (i = 0; i < old.length; i++) {
		if (old[i].parentNode)
			old[i].parentNode.removeChild(old[i]);
	}
}

function sbRevealServerPlayers(sid) {
	var panel = document.getElementById("serverpanel_" + sid);
	if (!panel)
		return;
	if (sbAccPanelClosed(panel))
		return;
	if (panel._sbHTimer)
		return;
	if (panel.style.height === "auto")
		return;
	panel.style.height = "auto";
	panel.style.overflow = "visible";
	panel.style.visibility = "visible";
}

function sbAddServerPlayerHead(sid) {
	var e = document.getElementById("playerlist_" + sid);
	if (!e)
		return;
	var tr = e.insertRow(-1);
	tr.className = "servers-players-head";
	var labels = ["Игрок", "Счёт", "Время"];
	var i, td, b;
	for (i = 0; i < labels.length; i++) {
		td = tr.insertCell(-1);
		td.className = "servers-player-th";
		if (i === 0)
			td.setAttribute("width", "50%");
		else if (i === 1)
			td.setAttribute("width", "15%");
		b = document.createElement("b");
		b.appendChild(document.createTextNode(labels[i]));
		td.appendChild(b);
	}
}

function sbAddServerPlayer(raw) {
	var p = sbPlayerPayload(raw);
	if (!p)
		return;
	var e = document.getElementById("playerlist_" + p.sid);
	if (!e)
		return;
	var tr = e.insertRow(-1);
	tr.id = "player_s" + p.sid + "p" + p.pid;
	var td = tr.insertCell(-1);
	td.className = "servers-player-name " + (p.manage ? "p-l-5 " : "p-l-10 ");
	var nameNode = document.createTextNode(p.name || "");
	if (p.manage) {
		var btn = document.createElement("button");
		btn.type = "button";
		btn.className = "js-player-menu";
		btn.setAttribute("data-player", JSON.stringify(p));
		var ico = document.createElement("i");
		ico.className = "bi bi-three-dots-vertical";
		ico.setAttribute("aria-hidden", "true");
		btn.appendChild(ico);
		btn.appendChild(nameNode);
		td.appendChild(btn);
	} else {
		td.appendChild(nameNode);
	}
	var td2 = tr.insertCell(-1);
	td2.className = "servers-player-score";
	td2.appendChild(document.createTextNode(String(p.frags == null ? "" : p.frags)));
	var td3 = tr.insertCell(-1);
	td3.className = "servers-player-time";
	td3.appendChild(document.createTextNode(p.time || ""));
}

if (!window._sbPlayerMenuBound) {
	window._sbPlayerMenuBound = true;
	document.addEventListener("click", function (e) {
		var t = e.target;
		if (!t || !t.closest)
			return;
		var a = t.closest("a.js-player-menu, button.js-player-menu");
		if (!a)
			return;
		sbOpenPlayerMenu(e, a);
	}, true);
	document.addEventListener("keydown", function (e) {
		if (e.key === "Escape")
			sbClosePlayerSheet();
	});
}
