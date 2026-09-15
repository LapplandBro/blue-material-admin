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
// ВАЖНО: только для реальной GET-навигации. JSON AJAX (опрос серверов и т.п.)
// идёт POST на index.php — его нельзя редиректить на ЧПУ.
$sb_ctype = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
$sb_is_ajax =
	(isset($_SERVER['REQUEST_METHOD']) && strcasecmp($_SERVER['REQUEST_METHOD'], 'GET') !== 0)
	|| isset($_POST['sb_ajax']) || isset($_GET['sb_ajax'])
	|| stripos($sb_ctype, 'application/json') !== false
	|| (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0);

if (!$sb_is_ajax) {
	$req_path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
	$home_base = rtrim(defined('SB_WP_URL') ? SB_WP_URL : '', '/');
	if ($home_base === '') {
		$home_base = ((defined('COOKIE_SECURE') && COOKIE_SECURE) ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
	}
	// ЧПУ → query-string. Не новые rewrite-правила: если PHP уже видит /admin/bans или /banlist — уводим на index.php?p=.
	if (is_string($req_path) && !preg_match('#/index\.php$#i', $req_path)) {
		$q = $_GET;
		$target = '';
		if (preg_match('#/admin(?:/([a-zA-Z0-9_]+))?/?$#', $req_path, $am)) {
			$c = isset($am[1]) ? $am[1] : '';
			$pnow = isset($_GET['p']) ? (string)$_GET['p'] : '';
			if ($pnow === 'admin' || ($pnow === '' && $c !== '') || ($pnow === '' && $c === '' && preg_match('#/admin/?$#', $req_path))) {
				$q['p'] = 'admin';
				if ($c !== '')
					$q['c'] = $c;
				$target = 'admin';
			}
		} elseif (preg_match('#/(banlist|commslist|servers|login|logout|submit|protest|account|lostpassword|login2fa|search_bans|search_comm|pay|adminlist)(?:/(\d+))?/?$#', $req_path, $pm)) {
			$pname = $pm[1];
			$pnow = isset($_GET['p']) ? (string)$_GET['p'] : '';
			if ($pnow === $pname || $pnow === '') {
				$q['p'] = $pname;
				if (($pname === 'banlist' || $pname === 'commslist') && !empty($pm[2]) && (int)$pm[2] > 1)
					$q['page'] = (int)$pm[2];
				$target = $pname;
			}
		}
		if ($target !== '') {
			header('Location: ' . $home_base . '/index.php?' . http_build_query($q), true, 302);
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
