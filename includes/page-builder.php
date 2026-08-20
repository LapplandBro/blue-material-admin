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

$page = '';
$pageNotFound = false;

$pRaw = isset($_GET['p']) ? trim((string)$_GET['p']) : '';
// Пустой / default → страница по настройкам. Иначе только [a-z0-9_], без «banlist/sosat».
$useDefault = ($pRaw === '' || strtolower($pRaw) === 'default');

if (!$useDefault && !preg_match('/^[a-zA-Z0-9_]+$/', $pRaw)) {
	$pageNotFound = true;
} elseif (!$useDefault) {
	$_GET['p'] = $pRaw;
	switch ($_GET['p'])
	{
		case "login":
			$page = TEMPLATES_PATH . "/page.login.php";
			break;
		case "logout":
			// Лог + flash пока $userbank ещё знает, кто выходит (до очистки кук/сессии).
			$logoutFlash = array(
				'title' => 'Выход',
				'msg' => 'Вы вышли из системы.',
				'color' => 'green',
				'timer' => 1600,
			);
			if (isset($GLOBALS['userbank']) && $GLOBALS['userbank']->GetAid() > 0)
			{
				$log = new CSystemLog("m", "Выход из системы", "Администратор '" . htmlspecialchars($GLOBALS['userbank']->GetProperty('user')) . "' вышел из системы.", false);
				$log->aid = (int)$GLOBALS['userbank']->GetAid();
				$log->WriteLog();
			}
			logout(); // session_destroy — flash нужно положить в НОВУЮ сессию
			if (function_exists('sb_session_start'))
				sb_session_start();
			elseif (session_status() === PHP_SESSION_NONE)
				@session_start();
			$_SESSION['sb_ui_flash'] = $logoutFlash;
			Header("Location: index.php");
			exit;
		case "admin":
			// Ранняя проверка c= — до BuildBreadcrumbs/UI, иначе unknown /admin/x
			// раньше мог «съесть» $_GET['c'] и показать хаб вместо 404.
			$adminCEarly = isset($_GET['c']) ? trim((string)$_GET['c']) : '';
			if ($adminCEarly !== '') {
				$adminKnownEarly = array(
					'groups', 'admins', 'servers', 'bans', 'comms', 'recidivism',
					'parsec', 'mods', 'settings', 'pay_card', 'menu',
				);
				if (!preg_match('/^[a-zA-Z0-9_]+$/', $adminCEarly) || !in_array($adminCEarly, $adminKnownEarly, true)) {
					$pageNotFound = true;
					break;
				}
				$_GET['c'] = $adminCEarly;
				// ЧПУ steam до любого HTML (header уже не отправится)
				if (($adminCEarly === 'recidivism' || $adminCEarly === 'parsec')
					&& function_exists('sb_canonical_admin_steam_redirect')) {
					if (function_exists('sb_apply_steam_path_param'))
						sb_apply_steam_path_param();
					sb_canonical_admin_steam_redirect($adminCEarly);
				}
			}
			$page = INCLUDES_PATH . "/admin.php";
			break;
		case "submit":
			RewritePageTitle("Submit a Ban");
			$page = TEMPLATES_PATH . "/page.submit.php";
			break;
		case "banlist":
			RewritePageTitle("Ban List");
			$page = TEMPLATES_PATH ."/page.banlist.php";
			break;
		case "commslist":
			RewritePageTitle("Communications Block List");
			$page = TEMPLATES_PATH ."/page.commslist.php";
			break;
		case "servers":
			RewritePageTitle("Server List");
			$page = TEMPLATES_PATH . "/page.servers.php";
			break;
		case "protest":
			RewritePageTitle("Protest a Ban");
			$page = TEMPLATES_PATH . "/page.protest.php";
			break;
		case "account":
			RewritePageTitle("Your Account");
			$page = TEMPLATES_PATH . "/page.youraccount.php";
			break;
		case "lostpassword":
			RewritePageTitle("Lost your password");
			$page = TEMPLATES_PATH . "/page.lostpassword.php";
			break;
		case "login2fa":
			RewritePageTitle("Two-factor authentication");
			$page = TEMPLATES_PATH . "/page.login2fa.php";
			break;
		case "home":
			RewritePageTitle("Dashboard");
			$page = TEMPLATES_PATH . "/page.home.php";
			break;
		case "search_bans":
			RewritePageTitle("Подробный поиск банов");
			$page = TEMPLATES_PATH . "/page.search_bans.php";
			break;
		case "search_comm":
			RewritePageTitle("Подробный поиск мутов");
			$page = TEMPLATES_PATH . "/page.search_comms.php";
			break;
		case "pay":
			RewritePageTitle("Активация");
			$page = TEMPLATES_PATH . "/page.vay4er.php";
			break;
		case "adminlist":
			RewritePageTitle("АдминЛист");
			$page = TEMPLATES_PATH . "/page.adminlist.php";
			break;
		case "ui_v2":
			RewritePageTitle("UI v2 preview");
			$page = TEMPLATES_PATH . "/page.ui_v2.php";
			break;
		default:
			// Неизвестный p=foo — НЕ подменять на главную/банлист
			$pageNotFound = true;
			break;
	}
} else {
	switch($GLOBALS['config']['config.defaultpage'])
	{
		case 1:
			RewritePageTitle("Ban List");
			$page = TEMPLATES_PATH . "/page.banlist.php";
			$_GET['p'] = "banlist";
			break;
		case 2:
			RewritePageTitle("Server Info");
			$page = TEMPLATES_PATH . "/page.servers.php";
			$_GET['p'] = "servers";
			break;
		case 3:
			RewritePageTitle("Submit a Ban");
			$page = TEMPLATES_PATH . "/page.submit.php";
			$_GET['p'] = "submit";
			break;
		case 4:
			RewritePageTitle("Protest a Ban");
			$page = TEMPLATES_PATH . "/page.protest.php";
			$_GET['p'] = "protest";
			break;
		default: //case 0:
			RewritePageTitle("Dashboard");
			$page = TEMPLATES_PATH . "/page.home.php";
			$_GET['p'] = "home";
			break;
	}
}

if ($pageNotFound) {
	if (function_exists('sb_send_static_404'))
		sb_send_static_404();
	http_response_code(404);
	exit;
}

$pUi = isset($_GET['p']) ? (string)$_GET['p'] : '';
$v2admin = ($pUi === 'admin');

if (!function_exists('sb_ui_v2_twig') || !sb_ui_v2_twig()) {
	if (!headers_sent())
		header('Content-Type: text/html; charset=utf-8');
	http_response_code(500);
	echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Blue V2</title></head><body>';
	echo '<p>Twig не загружен. Нужен <code>includes/Twig-3.28.0</code> (PHP ≥8.1), <code>includes/twig</code> (PHP 7.1) или <code>composer install</code>.</p>';
	echo '</body></html>';
	exit;
}

if (defined('INCLUDES_PATH') && is_readable(INCLUDES_PATH . '/CTabsMenu.php'))
	require_once INCLUDES_PATH . '/CTabsMenu.php';
ob_start();
if (!empty($page))
	include $page;
$buf = ob_get_clean();
if ($v2admin && stripos($buf, '<!DOCTYPE') === false && function_exists('sb_ui_v2_wrap')) {
	$textOnly = trim(strip_tags($buf));
	$hasAdminStructure = preg_match('/\b(admin-page-content|admin-pane|form-page|admin-manage)\b/i', $buf);
	if ($textOnly === '' && !$hasAdminStructure) {
		$section = isset($_GET['c']) ? htmlspecialchars((string)$_GET['c'], ENT_QUOTES, 'UTF-8') : 'admin';
		$buf = '<div class="form-page"><div class="form-flash form-flash--err">'
			. 'Раздел «' . $section . '» не сформировал содержимое. Проверьте журнал PHP и права доступа.'
			. '</div></div>';
	}
	sb_ui_v2_wrap($buf);
} else {
	echo $buf;
}
exit;
