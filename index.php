<?php 
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

//if ($_SERVER["SERVER_PORT"] != 443) {
//	header("Location: https://foxsys-tech.ru/index.php");
//	exit();
//}

// Шесть месяцев назад лишь двое знали, как это работает - я и Бог. Сейчас это знает уже только Бог.
include_once 'init.php';

// 301: дубли главной → canonical /
// ВАЖНО: только для реальной GET-навигации. AJAX/xajax-запросы (автообновление
// списка серверов и т.п.) идут в index.php?p=... — их нельзя редиректить, иначе
// XHR получает всю главную вместо фрагмента и дублирует страницу в саму себя.
// xajax 0.2.5 определяет запрос по параметру "xajax" (имя функции), метод POST/GET.
$sb_is_ajax =
	(isset($_SERVER['REQUEST_METHOD']) && strcasecmp($_SERVER['REQUEST_METHOD'], 'GET') !== 0)
	|| isset($_POST['xajax']) || isset($_GET['xajax'])
	|| isset($_POST['xajaxargs']) || isset($_GET['xajaxargs'])
	|| (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0);

if (!$sb_is_ajax) {
	// Только если в URL реально /index.php (не внутренний rewrite /banlist → index.php).
	// THE_REQUEST на части CGI/XAMPP пустой — смотрим REQUEST_URI.
	$req_path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
	$asked_index = is_string($req_path) && (bool)preg_match('#/index\.php$#i', $req_path);
	if (!$asked_index && !empty($_SERVER['THE_REQUEST']))
		$asked_index = (bool)preg_match('#\s/+index\.php[\s?]#i', (string)$_SERVER['THE_REQUEST']);
	if ($asked_index) {
		$query_string = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
		parse_str($query_string, $qparams);
		$page_param = isset($qparams['p']) ? preg_replace('/[^a-zA-Z0-9_]/', '', (string)$qparams['p']) : '';
		unset($qparams['p']);
		$home_base = rtrim(defined('SB_WP_URL') ? SB_WP_URL : '', '/');
		if ($home_base === '') {
			$home_base = ((defined('COOKIE_SECURE') && COOKIE_SECURE) ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
		}
		if (($page_param === '' || strcasecmp($page_param, 'home') === 0) && count($qparams) === 0) {
			header('Location: ' . $home_base . '/', true, 301);
			exit;
		}
		// index.php?p=banlist → /banlist (и /admin/bans для c=)
		$pretty_pages = array(
			'login', 'logout', 'admin', 'submit', 'banlist', 'commslist', 'servers',
			'protest', 'account', 'lostpassword', 'search_bans', 'search_comm',
			'pay', 'adminlist',
		);
		if ($page_param !== '' && in_array($page_param, $pretty_pages, true)) {
			$c = '';
			if ($page_param === 'admin' && !empty($qparams['c'])) {
				$c = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$qparams['c']);
				unset($qparams['c']);
			}
			$path = ($page_param === 'admin' && $c !== '') ? ('/admin/' . $c) : ('/' . $page_param);
			$qs = http_build_query($qparams);
			header('Location: ' . $home_base . $path . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
			exit;
		}
	}
}

include_once(INCLUDES_PATH . "/user-functions.php");
include_once(INCLUDES_PATH . "/system-functions.php");
include_once(INCLUDES_PATH . "/sb-callback.php");
$xajax->processRequests();
sb_session_start();
include_once(INCLUDES_PATH . "/page-builder.php");
