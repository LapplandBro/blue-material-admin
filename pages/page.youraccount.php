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

global $userbank, $theme;

if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
if($userbank->GetAid() == -1){echo "Вы не должны быть здесь ><";die();}

$aid = (int)$userbank->GetAid();
$totp_msg = '';
$totp_msg_type = '';
$totp_recovery_once = null;
$totp_setup_secret = '';

if (function_exists('sb_session_start'))
	sb_session_start();
elseif (session_status() !== PHP_SESSION_ACTIVE)
	@session_start();

if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST' && isset($_POST['totp_action'])) {
	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
		$totp_msg = 'Сессия истекла. Обновите страницу.';
		$totp_msg_type = 'error';
	} else {
		$action = (string)$_POST['totp_action'];
		if ($action === 'start') {
			if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('totp_acct', 20, 900)) {
				$totp_msg = 'Слишком много попыток. Подождите.';
				$totp_msg_type = 'error';
			} else {
				$_SESSION['sb_totp_acct_secret'] = sb_totp_generate_secret();
				$_SESSION['sb_totp_acct_codes'] = sb_totp_generate_recovery_codes(8);
				$totp_setup_secret = $_SESSION['sb_totp_acct_secret'];
				$totp_msg = 'Отсканируйте секрет в приложении и подтвердите кодом.';
				$totp_msg_type = 'info';
			}
		} elseif ($action === 'confirm') {
			$secret = isset($_SESSION['sb_totp_acct_secret']) ? $_SESSION['sb_totp_acct_secret'] : '';
			$codes = isset($_SESSION['sb_totp_acct_codes']) ? $_SESSION['sb_totp_acct_codes'] : array();
			if ($secret === '' || !sb_totp_verify($secret, isset($_POST['code']) ? $_POST['code'] : '')) {
				$totp_msg = 'Неверный код подтверждения.';
				$totp_msg_type = 'error';
				$totp_setup_secret = $secret;
			} else {
				sb_totp_enable($aid, $secret, is_array($codes) ? $codes : array());
				$totp_recovery_once = is_array($codes) ? $codes : array();
				unset($_SESSION['sb_totp_acct_secret'], $_SESSION['sb_totp_acct_codes']);
				$totp_msg = '2FA включена. Сохраните recovery-коды — они больше не будут показаны.';
				$totp_msg_type = 'success';
				new CSystemLog("m", "2FA", "Администратор aid={$aid} включил TOTP.");
			}
		} elseif ($action === 'disable') {
			$pw = isset($_POST['password']) ? (string)$_POST['password'] : '';
			$code = isset($_POST['code']) ? (string)$_POST['code'] : '';
			$secret = sb_totp_secret_for_aid($aid);
			$ok_code = ($secret !== '' && sb_totp_verify($secret, $code)) || sb_totp_consume_recovery($aid, $code);
			if (!$userbank->verify_password($pw, $aid) || !$ok_code) {
				$totp_msg = 'Неверный пароль или код 2FA.';
				$totp_msg_type = 'error';
			} else {
				sb_totp_disable($aid);
				$totp_msg = '2FA отключена.';
				$totp_msg_type = 'success';
				new CSystemLog("m", "2FA", "Администратор aid={$aid} отключил TOTP.");
			}
		}
	}
}

if ($totp_setup_secret === '' && !empty($_SESSION['sb_totp_acct_secret']) && !sb_totp_is_enabled($aid))
	$totp_setup_secret = $_SESSION['sb_totp_acct_secret'];
		
$allow_change_infos = $GLOBALS['config']['config.changeadmininfos'];
$v2 = function_exists('sb_ui_v2_enabled') && sb_ui_v2_enabled();
if (!$v2) {
	$groupsTabMenu = new CTabsMenu();
	$groupsTabMenu->addMenuItem("Информация", 0);
	if($allow_change_infos)
		$groupsTabMenu->addMenuItem("Связь", 4);
	$groupsTabMenu->addMenuItem("Сменить пароль", 1);
	$groupsTabMenu->addMenuItem("Серверный пароль", 2);
	$groupsTabMenu->addMenuItem("Сменить E-mail", 3);
	$groupsTabMenu->addMenuItem("2FA", 5);
	$groupsTabMenu->outputMenu();
}

$res = $GLOBALS['db']->Execute("SELECT `srv_password`, `email` FROM `".DB_PREFIX."_admins` WHERE `aid` = '".$userbank->GetAid()."'");
$srvpwset = (!empty($res->fields['srv_password'])?true:false);

$user_time = $userbank->GetProperty("expired", $userbank->GetAid());
if($user_time == '' || $user_time == '0') {
	$user_time = "Навсегда";
} elseif($user_time > '0' && $user_time > time()) {
	$days_left = (int)round((($user_time - time()) / 86400), 0);
	$user_time = "ещё ".$days_left." дн. · до ".date('d.m.Y H:i', $user_time);
} else {
	$user_time = "Истекла";
}

if (!function_exists('sb_account_punish_item')) {
	function sb_account_punish_item($row, $listPage)
	{
		$item = array();
		$name = isset($row['name']) ? stripslashes((string)$row['name']) : '';
		$name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$item['name'] = $name;
		// CSS сам режет через ellipsis — здесь только мягкий лимит без битой UTF-8
		$item['short_name'] = function_exists('trunc') ? trunc($name, 40, false) : $name;
		$item['authid'] = isset($row['authid']) ? trim((string)$row['authid']) : '';
		$created = isset($row['created']) ? (int)$row['created'] : 0;
		$df = (isset($GLOBALS['config']['config.dateformat_ver2']) && $GLOBALS['config']['config.dateformat_ver2'] !== '')
			? $GLOBALS['config']['config.dateformat_ver2']
			: 'd.m.Y H:i';
		$item['created'] = function_exists('SBDate') ? SBDate($df, $created) : date('d.m.Y H:i', $created);
		$length = isset($row['length']) ? (int)$row['length'] : 0;
		$ends = isset($row['ends']) ? (int)$row['ends'] : 0;
		$remove = isset($row['RemoveType']) ? trim((string)$row['RemoveType']) : '';
		$expired = function_exists('sb_punish_is_inactive')
			? sb_punish_is_inactive($length, $ends, $remove, $created)
			: ($remove !== '' || ($length > 0 && $ends > 0 && $ends < time()));
		$item['unbanned'] = $expired;
		$item['perm'] = ($length === 0 && !$expired);
		$item['length'] = function_exists('sb_punish_length_label')
			? sb_punish_length_label($length, $expired, $remove)
			: ($length === 0 ? 'Навсегда' : (string)$length);
		$reason = isset($row['reason']) ? stripslashes((string)$row['reason']) : '';
		$reason = html_entity_decode($reason, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$item['reason'] = function_exists('trunc') ? trunc($reason, 80, true) : $reason;

		$bid = isset($row['bid']) ? (int)$row['bid'] : 0;
		$adv = $item['authid'];
		$advType = 'steamid';
		if ($adv === '' && $listPage === 'banlist' && !empty($row['ip'])) {
			$adv = (string)$row['ip'];
			$advType = 'ip';
		}
		// Пустой SteamID → иначе commslist&advSearch=&advType=steamid (пустая страница)
		if ($adv === '' && $bid > 0) {
			$adv = (string)$bid;
			$advType = 'bid';
		}
		if ($adv !== '')
			$item['search_link'] = 'index.php?p=' . $listPage . '&advSearch=' . rawurlencode($adv) . '&advType=' . $advType;
		else
			$item['search_link'] = 'index.php?p=' . $listPage;
		if ($listPage === 'commslist' && function_exists('sb_comms_type_icon_html'))
			$item['type_html'] = sb_comms_type_icon_html(isset($row['type']) ? $row['type'] : 1, 18);
		else
			$item['type_html'] = '';
		return $item;
	}
}

$my_bans = array();
$my_comms = array();
$my_bans_total = 0;
$my_bans_active = 0;
$my_comms_total = 0;
$my_comms_active = 0;
$my_bans_url = 'index.php?p=banlist&advSearch=' . (int)$aid . '&advType=admin';
$my_comms_url = 'index.php?p=commslist&advSearch=' . (int)$aid . '&advType=admin';
if (isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
	// Веб: aid. Игра (Material Admin): часто aid=0, админ в adminIp как SteamID.
	$myAuth = $userbank->GetProperty('authid', $aid);
	list($issuedWhere, $issuedParams) = function_exists('sb_admin_issued_where')
		? sb_admin_issued_where($aid, $myAuth)
		: array('aid = ?', array((int)$aid));
	$activeSql = function_exists('sb_punish_active_sql')
		? sb_punish_active_sql('')
		: "((RemoveType IS NULL OR RemoveType = '') AND (`length` = 0 OR `ends` > UNIX_TIMESTAMP()))";
	$banCounts = $GLOBALS['db']->GetRow(
		"SELECT COUNT(*) AS total, SUM(CASE WHEN ".$activeSql." THEN 1 ELSE 0 END) AS active FROM `".DB_PREFIX."_bans` WHERE ".$issuedWhere,
		$issuedParams
	);
	if (is_array($banCounts)) {
		$my_bans_total = isset($banCounts['total']) ? (int)$banCounts['total'] : (isset($banCounts[0]) ? (int)$banCounts[0] : 0);
		$my_bans_active = isset($banCounts['active']) ? (int)$banCounts['active'] : (isset($banCounts[1]) ? (int)$banCounts[1] : 0);
	}
	$commCounts = $GLOBALS['db']->GetRow(
		"SELECT COUNT(*) AS total, SUM(CASE WHEN ".$activeSql." THEN 1 ELSE 0 END) AS active FROM `".DB_PREFIX."_comms` WHERE ".$issuedWhere,
		$issuedParams
	);
	if (is_array($commCounts)) {
		$my_comms_total = isset($commCounts['total']) ? (int)$commCounts['total'] : (isset($commCounts[0]) ? (int)$commCounts[0] : 0);
		$my_comms_active = isset($commCounts['active']) ? (int)$commCounts['active'] : (isset($commCounts[1]) ? (int)$commCounts[1] : 0);
	}
	$banRows = $GLOBALS['db']->GetAll(
		"SELECT bid, name, authid, ip, type, created, length, ends, reason, RemoveType FROM `".DB_PREFIX."_bans` WHERE ".$issuedWhere." ORDER BY created DESC LIMIT 12",
		$issuedParams
	);
	if (is_array($banRows)) {
		foreach ($banRows as $banRow)
			$my_bans[] = sb_account_punish_item($banRow, 'banlist');
	}
	$commRows = $GLOBALS['db']->GetAll(
		"SELECT bid, name, authid, type, created, length, ends, reason, RemoveType FROM `".DB_PREFIX."_comms` WHERE ".$issuedWhere." ORDER BY created DESC LIMIT 12",
		$issuedParams
	);
	if (is_array($commRows)) {
		foreach ($commRows as $commRow)
			$my_comms[] = sb_account_punish_item($commProps, 'commslist');
	}
}

$theme->assign('allow_change_inf',		$allow_change_infos);
$theme->assign('srvpwset',				$srvpwset);
$theme->assign('email',					$res->fields['email']);
$theme->assign('vk',					$userbank->GetProperty("vk", $userbank->GetAid()));
$theme->assign('discord',				$userbank->GetProperty("discord", $userbank->GetAid()));
$theme->assign('user_aid',				$userbank->GetAid());
$theme->assign('user_name',				$userbank->GetProperty("user"));
$theme->assign('user_steam',			$userbank->GetProperty("authid"));
$theme->assign('expired_time',			$user_time);
$theme->assign('web_permissions',		BitToString($userbank->GetProperty("extraflags"), 0, false));
$theme->assign('server_permissions',	SmFlagsToSb($userbank->GetProperty("srv_flags"), false));
$theme->assign('min_pass_len',			MIN_PASS_LENGTH);
$theme->assign('totp_enabled',			sb_totp_is_enabled($aid));
$theme->assign('totp_msg',				$totp_msg);
$theme->assign('totp_msg_type',			$totp_msg_type);
$theme->assign('totp_recovery_once',	$totp_recovery_once);
$theme->assign('totp_setup_secret',		$totp_setup_secret);
$theme->assign('totp_otpauth',			$totp_setup_secret !== '' ? sb_totp_otpauth_uri($totp_setup_secret, $userbank->GetProperty("user"), sb_totp_issuer()) : '');
$theme->assign('sb_csrf',				function_exists('sb_csrf_token') ? sb_csrf_token() : '');

if ($v2) {
	$extra_js = '<script>if(typeof ProcessAdminTabs==="function"){ProcessAdminTabs();}</script>';
	if ($totp_msg !== '' || $totp_setup_secret !== '' || $totp_recovery_once) {
		$extra_js .= '<script>if(typeof SwapPane==="function"){SwapPane(5);}else if(window.location){window.location.hash="^5";}</script>';
	}
	sb_ui_v2_render('account.twig', array(
		'title' => 'Аккаунт — Blue Admin',
		'allow_change_inf' => $allow_change_infos,
		'srvpwset' => $srvpwset,
		'email' => $res->fields['email'],
		'vk' => $userbank->GetProperty("vk", $userbank->GetAid()),
		'discord' => $userbank->GetProperty("discord", $userbank->GetAid()),
		'user_aid' => $userbank->GetAid(),
		'user_name' => $userbank->GetProperty("user"),
		'user_steam' => $userbank->GetProperty("authid"),
		'expired_time' => $user_time,
		'web_permissions' => BitToString($userbank->GetProperty("extraflags"), 0, false),
		'server_permissions' => SmFlagsToSb($userbank->GetProperty("srv_flags"), false),
		'min_pass_len' => MIN_PASS_LENGTH,
		'totp_enabled' => sb_totp_is_enabled($aid),
		'totp_msg' => $totp_msg,
		'totp_msg_type' => $totp_msg_type,
		'totp_recovery_once' => $totp_recovery_once,
		'totp_setup_secret' => $totp_setup_secret,
		'totp_otpauth' => $totp_setup_secret !== '' ? sb_totp_otpauth_uri($totp_setup_secret, $userbank->GetProperty("user"), sb_totp_issuer()) : '',
		'sb_csrf' => function_exists('sb_csrf_token') ? sb_csrf_token() : '',
		'my_bans' => $my_bans,
		'my_comms' => $my_comms,
		'my_bans_total' => $my_bans_total,
		'my_bans_active' => $my_bans_active,
		'my_comms_total' => $my_comms_total,
		'my_comms_active' => $my_comms_active,
		'my_bans_url' => $my_bans_url,
		'my_comms_url' => $my_comms_url,
		'extra_js' => $extra_js,
	));
	return;
}

$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_youraccount.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
if ($totp_msg !== '' || $totp_setup_secret !== '' || $totp_recovery_once) {
	echo '<script>if(typeof SwapPane==="function"){SwapPane(5);}else if(window.location){window.location.hash="^5";}</script>';
}
