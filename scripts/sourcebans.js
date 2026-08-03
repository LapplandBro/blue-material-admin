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
	if (id === null || id === undefined || id === '')
		return null;
	return document.getElementById(String(id));
}

function sbSetChecked(id, on) {
	var el = $id(id);
	if (el) el.checked = !!on;
}

function sbSetValue(id, value) {
	var el = $id(id);
	if (el) el.value = value;
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
 * Абсолютный URL относительно <base href>.
 * Важно: window.location = 'index.php?…' / 'admin/admins' НЕ учитывает <base>,
 * и с /admin/admins уезжает в /admin/index.php (кнопка «Назад» «ничего не делает»).
 */
function sbAbs(url) {
	url = (url == null) ? '' : String(url);
	if (url === '' || url.charAt(0) === '#' || /^[a-z][a-z0-9+.-]*:/i.test(url))
		return url;
	// Ведущий «/» = корень хоста, а не каталог панели в подпапке — снимаем, резолвим через <base>.
	if (url.charAt(0) === '/')
		url = url.substring(1);
	try {
		var a = document.createElement('a');
		a.href = url;
		return a.href;
	} catch (e) {
		return url;
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
	sbGo(fallbackUrl || 'admin');
}

/** ЧПУ: sbLoc('banlist', '&page=2&a=unban') → абсолютный …/banlist/2?a=unban */
function sbLoc(page, q) {
	page = String(page);
	q = (q == null) ? '' : String(q).replace(/^[&?]+/, '');
	if ((page === 'banlist' || page === 'commslist') && q !== '') {
		var pm = q.match(/(?:^|&)page=(\d+)(?=&|$)/);
		if (pm) {
			var pn = parseInt(pm[1], 10);
			q = q.replace(/(?:^|&)page=\d+/g, '').replace(/^&+/, '').replace(/&+/g, '&');
			if (pn > 1)
				page = page + '/' + pn;
		}
	}
	return sbAbs(page + (q !== '' ? ('?' + q) : ''));
}

// <base href> ломает якоря href="#^N": браузер ведёт на главную (/#^N).
// Ловим SourceBans-вкладки: голый #^N и path#^N (admin/admins#^1).
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
		// Чужие якоря не трогаем; path должен быть пустым или вести на текущую страницу.
		if (pathPart !== '') {
			try {
				var abs = sbAbs(pathPart);
				var cur = window.location.href.split('#')[0];
				if (abs.split('#')[0] !== cur)
					return; // другой URL — обычный переход
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
	}, true);
})();

function ProcessAdminTabs()
{
	var url = window.location.toString();
	var hashPos = url.indexOf('^');
	var tabNo = -1;
	if (hashPos !== -1) {
		tabNo = url.charAt(hashPos + 1);
		if (tabNo !== '' && document.getElementById('tab-' + tabNo))
			SwapPane(tabNo);
		else
			tabNo = -1;
	}

	// Нет #^N — показать первую существующую вкладку (иначе все pane остаются display:none).
	if (tabNo === -1 && document.getElementById('tab-0') && document.getElementById('0'))
		SwapPane(0);

	var upos = url.indexOf('~');
	if (upos !== -1) {
		var utabType = url.charAt(upos + 1);
		var utabNo = url.charAt(upos + 2);
		Swap2ndPane(utabNo, utabType);
	}

	return tabNo;
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
		document.getElementById(ttype + id).style.display = 'block';
	}
}

function SwapPane(id)
{
	var i = 0;
	var i2 = 0;
	if(document.getElementById("tab-" + id))
	{
		var paneEl;
		while((paneEl = document.getElementById(i)))
		{
			paneEl.style.display = 'none';
			i++;
		}
		while(i2 < 50)
		{
			var tabEl = document.getElementById("tab-" + i2);
			if(tabEl)
			{
				tabEl.classList.remove('active');
			}
			i2++;
		}
		document.getElementById("tab-" + id).classList.add('active');
		document.getElementById(id).style.display = 'block';
	}
}

function InitAccordion(opener, element, container, num)
{
	// BUG FIX: this used to unconditionally build a brand new Accordion widget on every
	// call, bound to the very same rows (via the "opener"/"element" selectors) every time.
	// It was called more than once for the same set of rows in two situations:
	//   1) the "window load" listener registered a few lines below re-ran InitAccordion
	//      with the exact same arguments once the page finished loading;
	//   2) on the servers list (index.php?p=servers&s=N), ServerHostPlayers() calls
	//      InitAccordion again (with a different "num") once the matching server's async
	//      status request comes back, purely to auto-expand that one server's panel.
	// Each extra call created ANOTHER independent Accordion instance on top of the old
	// one, and MooTools' Accordion binds its own click handler to every toggler row on
	// construction - so every row ended up with 2-3 competing click handlers/animations
	// fighting over the same DOM. That's exactly what caused the servers list to glitch:
	// clicking a server could visually open a different one, or flicker, depending on
	// which of the stacked handlers/animations "won" the race.
	// Fix: keep a single Accordion instance per unique (opener, element, container)
	// selector set and reuse it - if it already exists, just jump to the requested panel
	// instead of creating a competing instance.
	// "num" can be either a plain panel index (legacy callers) or an actual element
	// reference (e.g. $('serverpanel_123')) - Accordion.display() accepts both and
	// resolves an element to its current index itself, so no coercion is needed here.
	var key = opener + '|' + element + '|' + container;
	if (accordionInstances[key]) {
		if (num != null && num != -1)
			accordionInstances[key].display(num);
		return accordionInstances[key];
	}

	// IE6 got no window.addEventListener
	if (window.addEventListener) {
		window.addEventListener("load", function () {
				InitAccordion(opener, element, container, num);
		}, false);
	} else {
		window.attachEvent('onload', function () {
				InitAccordion(opener, element, container, num);
		});
	}

	if(num == null)
		num = -1;
	var ExtendedAccordion = Accordion.extend({
	showAll: function() {
		var obj = {};
		 this.previous = -1;
		this.elements.each(function(el, i){
			obj[i] = {};
			this.fireEvent('onActive', [this.togglers[i], el]);
			for (var fx in this.effects) obj[i][fx] = el[this.effects[fx]];
		}, this);
		return this.start(obj);
	},
	hideAll: function() {
		var obj = {};
		 this.previous = -1;
		this.elements.each(function(el, i){
			obj[i] = {};
			this.fireEvent('onBackground', [this.togglers[i], el]);
			for (var fx in this.effects) obj[i][fx] = 0;
		}, this);
		return this.start(obj);
	}
  }); 

	accordion = new ExtendedAccordion(opener, element, {
		opacity: true,
		alwaysHide: true,
		display: num,
		transition:Fx.Transitions.Quart.easeOut,
		onActive: function(toggler, element){
			toggler.setStyle('cursor', 'pointer');
			toggler.setStyle('background-color', '');
		},
	 
		onBackground: function(toggler, element){
			//toggler.setStyle('cursor', 'pointer');
			//toggler.setStyle('background-color', '');		
		}
	}, $(container));
	accordion.hideAll();

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
	$(document.getElementById(id)).setStyle('display', 'block');
}
function FXHide(id)
{
	$(document.getElementById(id)).setStyle('display', 'none');
}
function DoLogin(redir)
{
	var err = 0;
	var nopw = 0;
	if(!$('loginUsername').value)
	{
		$('loginUsername.msg').setHTML('Вы должны ввести логин!');
		$('loginUsername.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('loginUsername.msg').setHTML('');
		$('loginUsername.msg').setStyle('display', 'none');
	}
	
	if(!$('loginPassword').value)
	{
		$('loginPassword.msg').setHTML('Вы должны ввести пароль!');
		$('loginPassword.msg').setStyle('display', 'block');
		nopw = 1;
	}else
	{
		$('loginPassword.msg').setHTML('');
		$('loginPassword.msg').setStyle('display', 'none');
	}

	if(err)
		return 0;
		
	if(redir == "undefined")
		redir = "";
	xajax_Plogin(document.getElementById('loginUsername').value, 
				document.getElementById('loginPassword').value,
				 document.getElementById('loginRememberMe').checked,
				 redir,
				 nopw);
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
		ShowBox('Удалить бан', '<p class="c-black m-t-20 f-14">Вы уверены, что хотите удалить бан '+(bulk=="true"?"выбранных игроков":"игрока \'"+ name +"\'")+'?</p>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="RemoveBan(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'1\''+(bulk=="true"?", \'true\'":"")+');document.getElementById(\'rban\').disabled = true;" name="rban" class="btn btn-lg btn-primary waves-effect" id="rban" value="Удалить бан" />');
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
		ShowBox('Разбан', '<div class="form-group has-warning has-feedback"><label class="control-label f-14" for="inputWarning2">Пожалуйста, напишите краткий комментарий, почему вы собираетесь разбанить '+(bulk=="true"?"этих игроков":"игрока \'"+ name +"\'")+'!</label><div class="fg-line"><input type="text" class="form-control" id="inputWarning2" name="ureason"></div><span class="zmdi zmdi-alert-triangle form-control-feedback"></span><p class="help-block" id="ureason.msg"></p></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="if (UnbanBan(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\''+(bulk=="true"?", \'true\'":"")+')) document.getElementById(\'uban\').disabled = true;" name="uban" class="btn btn-lg btn-primary waves-effect" id="uban" value="Разбанить" />');
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
		if(document.getElementById('immunity').value)
			string += "#" + $('immunity').value;
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
}

function ProcessGroup()
{
	var Mask = BoxToMask();
	var Smask = BoxToSrvMask();
	xajax_AddGroup(document.getElementById('groupname').value, document.getElementById('grouptype').value, Mask, Smask);
}

function update_web()
{
	$('webperm').setHTML('');
	
	if(document.getElementById('webg').value == "c" || document.getElementById('webg').value == "n") {
		$('web.msg').setHTML('Ждите...');
		$('web.msg').setStyle('display', 'block');
	}
	
	if(document.getElementById('webg').value == "c"){
		//var height = 390;
		var block_p = "block";
	}else if(document.getElementById('webg').value == "n"){
		//var height = 410;
		var block_p = "block";
	}else
	{
		$('webperm').setHTML('');
		//var height = 1;
		var block_p = "none";
	}
	//Shrink('webperm', 1000, height);
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
  		//serverg = "c";
  		srvMask = "";
  	}
    var webg = document.getElementById('webg').value;
  	if(webg == "-3")
  	{
  		//webg = "c";
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

	if(err)
		return 0;

	xajax_AddMod($('name').value,
				 $('folder').value,
				 icname,
				 $('steam_universe').value,
				 $('enabled').checked);
}
function ShowBox(title, msg, color, redir, noclose, timer)
{
	var type = "info";
	if (color == "red")
		type = "warning";
	else if (color == "blue")
		type = "info";
	else if (color == "green")
		type = "success";

	// Legacy hooks for kickit/blockit iframes (they poke parent #dialog-control)
	function ensureDialogNode(id) {
		var el = document.getElementById(id);
		if (!el) {
			el = document.createElement("div");
			el.id = id;
			el.style.display = "none";
			document.body.appendChild(el);
		}
		return el;
	}
	ensureDialogNode("dialog-placement");
	ensureDialogNode("dialog-title");
	ensureDialogNode("dialog-content-text");
	var dControl = ensureDialogNode("dialog-control");

	var hasSrvFrame = (msg && String(msg).indexOf("srvkicker") !== -1);
	var opts = {
		title: title || "",
		text: msg || "",
		html: true,
		type: type,
		allowOutsideClick: true,
		customClass: hasSrvFrame ? "sweet-alert-srv" : ""
	};

	if (timer) {
		opts.showConfirmButton = false;
		opts.timer = timer;
	} else if (!noclose) {
		opts.confirmButtonText = "OK";
		opts.showConfirmButton = true;
	} else {
		opts.showCancelButton = true;
		opts.showConfirmButton = false;
		opts.cancelButtonText = "Закрыть";
	}

	if (typeof swal === "function")
		swal(opts);

	var dt = document.getElementById("dialog-title");
	var dct = document.getElementById("dialog-content-text");
	if (dt) dt.innerHTML = title || "";
	if (dct) dct.innerHTML = msg || "";

	setTimeout(function () {
		var pane = document.querySelector(".sweet-alert p");
		if (pane && dControl && hasSrvFrame) {
			dControl.className = "dialog-control-inline";
			dControl.style.display = "block";
			if (!pane.contains(dControl))
				pane.appendChild(dControl);
		}
		var ifr = document.getElementById("srvkicker");
		if (ifr) {
			ifr.style.width = "100%";
			ifr.style.border = "0";
			ifr.style.background = "transparent";
			if (!ifr.getAttribute("height") || parseInt(ifr.getAttribute("height"), 10) < 120)
				ifr.style.minHeight = "220px";
		}
	}, 40);

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
	var nurl = "window.location = '" + url.replace("#^" + url[url.length-1],"") + "'";
	$('admin_tab_0').setProperty('onclick', nurl);
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
	/* Коммент
	if($(el))
	{
		if($(el).hasClass('btn'))
		{
			$(el).removeClass('btn');
			$(el).addClass('btnhvr');
		}
		else
		{
			$(el).removeClass('btnhvr');
			$(el).addClass('btn');
		}
	}
	*/ 
}

function ClearLogs()
{
	var noPerm = confirm("Вы уверены, что хотите удалить все записи в журнале?");
	if(noPerm == false)
	{
		return;
	}
	window.location = sbLoc("admin/settings", "log_clear=true") + "#^2";
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
	$('perms').setHTML('');
	if(document.getElementById('grouptype').value != 3 && document.getElementById('grouptype').value != 0) {
		$('type.msg').setHTML('Ждите...');
		$('type.msg').setStyle('display', 'block');
	}
	/*if(document.getElementById('grouptype').value == 1)
	{
		var height = 285;
	}else if(document.getElementById('grouptype').value == 2)
	{
		var height = 435;
	}else
	{
		$('type.msg').setStyle('display', 'none');
		var height = 2;
	}
	Shrink('perms', 1000, height);
	*/
	if(document.getElementById('grouptype').value != 3 && document.getElementById('grouptype').value != 0)
		setTimeout("xajax_UpdateGroupPermissions(document.getElementById('grouptype').value)",1000);
}

function changePage(newPage, type, advSearch, advType)
{		
	nextPage = newPage.options[newPage.selectedIndex].value
	if(advSearch!="" && advType !="") { 
		var searchlink = "&advSearch="+advSearch+"&advType="+advType; 
	} else { 
		var searchlink =""; 
	}
	 if (nextPage != 0)
	 {
		if(type == "A")
            window.location = sbLoc("admin/admins", searchlink.replace(/^&/, "") + (searchlink ? "&" : "") + "page=" + nextPage);
		if(type == "B")
            window.location = sbLoc("banlist", searchlink + "&page=" + nextPage);
		if(type == "C")
            window.location = sbLoc("commslist", searchlink + "&page=" + nextPage);
		if(type == "L")
            window.location = sbLoc("admin/settings", searchlink + "&page=" + nextPage) + "#^2";
        if(type == "P")
            window.location = sbLoc("admin/bans", "ppage=" + nextPage) + "#^1";
        if(type == "PA")
            window.location = sbLoc("admin/bans", "papage=" + nextPage) + "#^1~p1";
        if(type == "S")
            window.location = sbLoc("admin/bans", "spage=" + nextPage) + "#^2";
        if(type == "SA")
            window.location = sbLoc("admin/bans", "sapage=" + nextPage) + "#^2~s1";
	 }
}

function ShowKickBox(check, type)
{
	//ShowBox('Бан добавлен', 'Бан был успешно добавлен<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check='+check+'&type='+type+'"></iframe>', 'green', 'index.php?p=admin&c=bans', true);
	ShowBox('Бан добавлен', 'Бан был успешно добавлен<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check='+check+'&type='+type+'"></iframe>', 'green', '', false);
}

function ShowRehashBox(servers, title, msg, color, redir)
{
	if (typeof redir === "undefined" || redir === null || redir === "undefined")
		redir = "";
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
		//xajax_RehashAdmins_pay(servers, card, 0);
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
	for(var i=0;$('chkb_' + i);i++)
	{
		if($('tickswitch').value==0){
			$('chkb_' + i).checked = true;
		}else{
			$('chkb_' + i).checked = false;
		}
	}
	if($('tickswitch').value==0) {
		$('tickswitch').value=1;
		$('tickswitch').setProperty('title','Снять все');
		$('tickswitchlink').addClass('alert-success');
		$('tickswitchlink').innerHTML = 'Все баны на текущей странице были выделены.';
		$('tickswitchlink_1').innerHTML = 'Выбрать все баны на текущей странице.';
		$('tickswitchlink').style.display = 'block';
		setTimeout("$('tickswitchlink').style.display = 'none';", 2500);
	} else {
		$('tickswitch').value=0;
		$('tickswitch').setProperty('title','Выбрать все');
		$('tickswitchlink').addClass('alert-success');
		$('tickswitchlink').innerHTML = 'Выделение банов на текущей странице снято.';
		$('tickswitchlink_1').innerHTML = 'Снять выделение банов на текущей странице.';
		$('tickswitchlink').style.display = 'block';
		setTimeout("$('tickswitchlink').style.display = 'none';", 2500);
	}
}

function BulkEdit(action, bankey)
{
	option = action.options[action.selectedIndex].value
	ids = new Array();
	for(var i=0;$('chkb_' + i);i++)
	{
		if($('chkb_' + i).checked===true)
			ids.push($('chkb_' + i).value);
	}
	switch(option)
	{
		case "U":
			UnbanBan(ids, bankey, "", "Разбанить всех", "1", "true");
		break;
		case "D":
			RemoveBan(ids, bankey, "", "Удалить всех", "0", "true");
		break;
	}
}

function BanFriendsProcess(fid, name)
{
	var checkUp = confirm("Вы уверены, что хотите забанить всех друзей игрока '"+name+"'?");
	if(checkUp == false)
		return;
	ShowBox("Бан друзей "+name, "Баним всех друзей игрока '"+name+"'.<br />Ждите...<br />Это может занять очень много времени — зависит от количества друзей.", 'blue', '', true);
	$('dialog-control').setStyle('display', 'none');
	xajax_BanFriends(fid, name);
}

function OpenMessageBox(sid, name, popup)
{
	if(popup==1) {
		ShowBox('Отправить сообщение', '<b>Пожалуйста, введите сообщение, которое вы хотите отправить <br>\''+name+'\'.</b><br>На сервере должен быть включён basechat.smx<br><i>&lt;sm_psay&gt;</i>.<br><textarea rows="3" cols="40" name="ingamemsg" id="ingamemsg" style="overflow:auto;"></textarea><br><div id="ingamemsg.msg" class="badentry"></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" name="ingmsg" class="btn btn-lg btn-primary waves-effect" onmouseover="ButtonOver(\'ingmsg\')" onmouseout="ButtonOver(\'ingmsg\')" id="ingmsg" value="Отправить" />');
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
	if(conf==0)	{
		ShowBox('Кик игрока', '<b>Вы уверены, что хотите кикнуть игрока <br>\''+name+'\'?</b>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" name="kbutton" class="btn btn-lg btn-primary waves-effect" onmouseover="ButtonOver(\'kbutton\')" onmouseout="ButtonOver(\'kbutton\')" id="kbutton" value="Да" /> ');
		$('dialog-control').setStyle('display', 'inline-block');
		$('kbutton').addEvent('click', function(){KickPlayerConfirm(sid, name, 1);});
	} else if(conf==1) {
		$('dialog-control').setStyle('display', 'none');
		xajax_KickPlayer(sid, name);
	}
}

function mapimg(filename)
{
	$('mapimg.msg').setHTML("<b>" + filename + "</b>");
	$('mapimg1.msg').style.display = "block";
}

function selectLengthTypeReason(length, type, reason)
{
	for(var i=0; i<=$('banlength').length ; i++) {
		if($('banlength').options[i].value == (length / 60)) {
			$('banlength').options[i].selected=true;
			break;
		}
	}
	$('type').options[type].selected = true;
	for(var i=0;i<=$('listReason').length;i++)	{
		if($('listReason').options[i].innerHTML == reason) {
			$('listReason').options[i].selected=true;
			break;
		}
		if($('listReason').options[i].value == 'other') {
			$('txtReason').value = reason;
			$('dreason').style.display = 'block';
			$('listReason').options[i].selected=true;
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
		$('dialog-control').setHTML('<input type="button" onclick="RemoveBlock(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'1\''+');document.getElementById(\'rban\').disabled = true;" name="rban" class="btn btn-lg btn-primary waves-effect" id="rban" value="Удалить блокировку" />');
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
		ShowBox('Причина включения чата', '<div class="form-group has-warning has-feedback"><label class="control-label f-14" for="inputWarning2">Пожалуйста, оставьте короткий комментарий, почему вы хотите включить чат игроку \''+ name +'\'.</label><div class="fg-line"><input type="text" class="form-control" id="inputWarning2" name="ureason"></div><span class="zmdi zmdi-alert-triangle form-control-feedback"></span><p class="help-block" id="ureason.msg"></p></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="if (UnGag(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\')) document.getElementById(\'uban\').disabled = true;" name="uban" class="btn btn-lg btn-primary waves-effect" id="uban" value="Вкл. чат" />');
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
		ShowBox('Причина включения микрофона', '<div class="form-group has-warning has-feedback"><label class="control-label f-14" for="inputWarning2">Пожалуйста, оставьте короткий комментарий, почему вы хотите включить микрофон игроку \''+ name +'\'.</label><div class="fg-line"><input type="text" class="form-control" id="inputWarning2" name="ureason"></div><span class="zmdi zmdi-alert-triangle form-control-feedback"></span><p class="help-block" id="ureason.msg"></p></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="if (UnMute(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\')) document.getElementById(\'uban\').disabled = true;" name="uban" class="btn btn-lg btn-primary waves-effect" id="uban" value="Вкл. микро" />');
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
