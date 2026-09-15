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

if(!defined("IN_SB")){echo "Ошибка доступа!";die();} 
global $userbank, $theme;

if(!$userbank->HasAccess(ADMIN_OWNER))
{
	echo "Доступ запрещен!";
}
else
{
	$unlocked = false;
	$flash_err = '';
	$password_configured = function_exists('SbDbcfgViewPasswordConfigured')
		? SbDbcfgViewPasswordConfigured()
		: false;
	$form_action = function_exists('sb_url')
		? sb_url('admin', array('c' => 'servers', 'o' => 'dbsetup'))
		: 'index.php?p=admin&c=servers&o=dbsetup';
	$back_url = function_exists('sb_url')
		? sb_url('admin', array('c' => 'servers'))
		: 'index.php?p=admin&c=servers';
	$csrf = function_exists('sb_csrf_token') ? sb_csrf_token() : '';

	$adminUser = (string)$userbank->GetProperty('user');
	$adminAid = (int)$userbank->GetAid();
	$adminSteam = (string)$userbank->GetProperty('authid');

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dbcfg_unlock'])) {
		$token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
		if (!function_exists('sb_csrf_validate') || !sb_csrf_validate($token)) {
			sb_csrf_fail_page(true);
		} elseif (!$password_configured) {
			$flash_err = 'Пароль не задан в config.php (SB_DBCFG_VIEW_PASSWORD).';
			new CSystemLog('w', 'Просмотр databases.cfg',
				$adminUser . ' (aid=' . $adminAid . ') пытался открыть конфиг БД, но SB_DBCFG_VIEW_PASSWORD не задан.');
		} else {
			$pass = isset($_POST['dbcfg_password']) ? (string)$_POST['dbcfg_password'] : '';
			if (function_exists('SbDbcfgViewPasswordVerify') && SbDbcfgViewPasswordVerify($pass)) {
				$unlocked = true;
				new CSystemLog('m', 'Просмотр databases.cfg',
					$adminUser . ' (aid=' . $adminAid . ', steam=' . $adminSteam . ') просмотрел конфиг БД SourceMod (databases.cfg).');
			} else {
				$flash_err = 'Неверный пароль.';
				new CSystemLog('w', 'Просмотр databases.cfg',
					$adminUser . ' (aid=' . $adminAid . ', steam=' . $adminSteam . ') ввёл неверный пароль просмотра конфига БД.');
			}
		}
	}

	$srv_cfg = '';
	if ($unlocked) {
		$srv_cfg = '"Databases"
{
	"driver_default"		"mysql"
	
	// Если вы используете старую серверную часть:
	"sourcebans"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		"port"				"{port}"
	}
	
	"sourcecomms"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		"port"				"{port}"
	}
	
	// Если вы используете новую серверную часть:
	"materialadmin"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		"port"				"{port}"
	}
}
';
		$srv_cfg = str_replace("{server}", DB_HOST, $srv_cfg);
		$srv_cfg = str_replace("{user}", DB_USER, $srv_cfg);
		$srv_cfg = str_replace("{pass}", DB_PASS, $srv_cfg);
		$srv_cfg = str_replace("{db}", DB_NAME, $srv_cfg);
		$srv_cfg = str_replace("{prefix}", DB_PREFIX, $srv_cfg);
		$srv_cfg = str_replace("{port}", DB_PORT, $srv_cfg);

		if (strtolower(DB_HOST) == "localhost") {
			ShowBox(
				"Предупреждение локального сервера",
				"Вы указали, что ваш сервер MySQL работает на той же машине, что и веб-сервер, это хорошо, но, возможно, потребуется изменить следующий конфигурационный файл, чтобы установить удаленный доступ к вашему серверу MySQL.",
				"blue",
				"",
				true
			);
		}
	}

	$theme->assign('unlocked', $unlocked);
	$theme->assign('conf', $srv_cfg);
	$theme->assign('flash_err', $flash_err);
	$theme->assign('password_configured', $password_configured);
	$theme->assign('form_action', $form_action);
	$theme->assign('back_url', $back_url);
	$theme->assign('csrf', $csrf);

	echo '<div id="admin-page-content">';
	echo '<div id="0" class="admin-pane is-on">';
	$_f = sb_ui_v2_theme_fragment('admin_servers_db.twig');
	if (is_string($_f) && $_f !== '') echo $_f;
	echo '</div>';
	echo '</div>';
}
