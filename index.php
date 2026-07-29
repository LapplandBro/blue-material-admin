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
	$request_path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
	$query_string = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
	$script_base = basename($request_path === null ? '' : $request_path);
	if ($script_base === 'index.php') {
		parse_str($query_string, $qparams);
		$page_param = isset($qparams['p']) ? $qparams['p'] : '';
		unset($qparams['p']);
		if (($page_param === '' || strcasecmp($page_param, 'home') === 0) && count($qparams) === 0) {
			$home_base = rtrim(defined('SB_WP_URL') ? SB_WP_URL : '', '/');
			if ($home_base === '') {
				$home_base = ((defined('COOKIE_SECURE') && COOKIE_SECURE) ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
			}
			header('Location: ' . $home_base . '/', true, 301);
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
