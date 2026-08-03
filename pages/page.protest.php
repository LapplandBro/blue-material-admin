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
if($GLOBALS['config']['config.enableprotest']!="1")
{
	CreateRedBox("Ошибка", "Страница отключена.");
	PageDie();
}
if(!defined("IN_SB")){echo "Ошибка доступа!"; die();}

if (function_exists('sb_session_start'))
	sb_session_start();
elseif (session_status() === PHP_SESSION_NONE)
	@session_start();

if (!isset($_POST['subprotest']) || $_POST['subprotest'] != 1)
{
	$Type = 0;
	$SteamID = "";
	$IP = "";
	$PlayerName = "";
	$UnbanReason = "";
	$Email = "";
}
else
{
	$Type = isset($_POST['Type']) ? (int)$_POST['Type'] : 0;
	$SteamID = htmlspecialchars(isset($_POST['SteamID']) ? $_POST['SteamID'] : '');
	$IP = htmlspecialchars(isset($_POST['IP']) ? $_POST['IP'] : '');
	$PlayerName = htmlspecialchars(isset($_POST['PlayerName']) ? $_POST['PlayerName'] : '');
	$UnbanReason = htmlspecialchars(isset($_POST['BanReason']) ? $_POST['BanReason'] : '');
	$Email = htmlspecialchars(isset($_POST['EmailAddr']) ? $_POST['EmailAddr'] : '');
	$validsubmit = true;
	$errors = "";
	$BanId = -1;

	if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
		$UnbanReason = stripslashes($UnbanReason);

	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
		$errors .= '* Сессия устарела. Обновите страницу и попробуйте снова.<br>';
		$validsubmit = false;
	} elseif (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('protest_ban', 8, 900)) {
		$errors .= '* Слишком много попыток. Подождите несколько минут.<br>';
		$validsubmit = false;
	} else {
		$kapcha = isset($_POST['kapcha']) ? trim((string)$_POST['kapcha']) : '';
		$expect = isset($_SESSION['rand_code']) ? (string)$_SESSION['rand_code'] : '';
		unset($_SESSION['rand_code']);
		if ($expect === '' || !hash_equals(strtolower($expect), strtolower($kapcha))) {
			$errors .= '* Проверочный код неверен. Обновите картинку и введите заново.<br>';
			$validsubmit = false;
		}
	}

	if($Type == 0 && !validate_steam($SteamID))
	{
		$errors .= '* Введите действительный STEAM ID.<br>';
		$validsubmit = false;
	}
	elseif($Type==0)
	{
		$pre = $GLOBALS['db']->Prepare("SELECT bid FROM ".DB_PREFIX."_bans WHERE authid=? AND RemovedBy IS NULL AND type=0;");
		$res = $GLOBALS['db']->Execute($pre,array($SteamID));
		if ($res->RecordCount() == 0)
		{
			$errors .=  '* Этот STEAM ID не забанен!<br>';
			$validsubmit = false;
		}
		else
		{
			$BanId = (int)$res->fields[0];
			$res = $GLOBALS['db']->Execute("SELECT pid FROM ".DB_PREFIX."_protests WHERE bid = ?", array($BanId));
			if ($res->RecordCount() > 0)
			{
				$errors .=  '* Бан этого STEAM ID уже был опротестован.<br>';
				$validsubmit = false;
			}
		}
	}
	if($Type == 1 && !validate_ip($IP))
	{
		$errors .= '* Введите действительный IP.<br>';
		$validsubmit = false;
	}
	elseif($Type==1)
	{
		$pre = $GLOBALS['db']->Prepare("SELECT bid FROM ".DB_PREFIX."_bans WHERE ip=? AND RemovedBy IS NULL AND type=1;");
		$res = $GLOBALS['db']->Execute($pre,array($IP));
		if ($res->RecordCount() == 0)
		{
			$errors .=  '* Этот IP не забанен!<br>';
			$validsubmit = false;
		}
		else
		{
			$BanId = (int)$res->fields[0];
			$res = $GLOBALS['db']->Execute("SELECT pid FROM ".DB_PREFIX."_protests WHERE bid = ?", array($BanId));
			if ($res->RecordCount() > 0)
			{
				$errors .=  '* Бан этого IP уже был опротестован.<br>';
				$validsubmit = false;
			}
		}
	}
	if (strlen($PlayerName) == 0)
	{
		$errors .=  '* Введите ник игрока<br>';
		$validsubmit = false;
	}
	elseif (strlen($PlayerName) > 128)
	{
		$errors .=  '* Ник игрока слишком длинный (макс. 128).<br>';
		$validsubmit = false;
	}
	if (strlen($UnbanReason) == 0)
	{
		$errors .=  '* Напишите пару строк коментария<br>';
		$validsubmit = false;
	}
	elseif (strlen($UnbanReason) > 512)
	{
		$errors .=  '* Комментарий слишком длинный (макс. 512).<br>';
		$validsubmit = false;
	}
	$rawProbe = (isset($_POST['PlayerName']) ? (string)$_POST['PlayerName'] : '')
		. (isset($_POST['BanReason']) ? (string)$_POST['BanReason'] : '');
	if ($validsubmit && preg_match('/xajax_|\bjavascript\s*:|<\s*script|on(?:error|load|click)\s*=/i', $rawProbe)) {
		$errors .=  '* Некорректные данные в форме.<br>';
		$validsubmit = false;
		if (class_exists('CSystemLog'))
			new CSystemLog('w', 'Подозрительный протест', 'Отклонён протест с признаками инъекции с IP ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
	}
	if (!check_email($Email))
	{
		$errors .=  '* Введите действительный адрес электронной почты<br>';
		$validsubmit = false;
	}

	if(!$validsubmit)
		CreateRedBox("Ошибка", $errors);

	if ($validsubmit && $BanId != -1)
	{
		$UnbanReason = trim($UnbanReason);
		$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_protests(bid,datesubmitted,reason,email,archiv,pip) VALUES (?,UNIX_TIMESTAMP(),?,?,0,?)");
		$res = $GLOBALS['db']->Execute($pre,array($BanId, $UnbanReason,$Email,$_SERVER['REMOTE_ADDR']));
        $protid = (int)$GLOBALS['db']->Insert_ID();
        $protadmin = $GLOBALS['db']->GetRow("SELECT ad.user FROM ".DB_PREFIX."_protests p, ".DB_PREFIX."_admins ad, ".DB_PREFIX."_bans b WHERE p.pid = ? AND b.bid = p.bid AND ad.aid = b.aid", array($protid));

		$Type = 0;
		$SteamID = "";
		$IP = "";
		$PlayerName = "";
		$UnbanReason = "";
		$Email = "";

		// Send an email when protest was posted
		// Используем доверенный домен из SB_WP_URL вместо $_SERVER['HTTP_HOST']
		// (заголовок Host полностью контролируется клиентом).
		$headers = 'From: protest@' . sb_get_site_host() . "\n" .
		'X-Mailer: PHP/' . phpversion();

		$emailinfo = $GLOBALS['db']->Execute("SELECT aid, user, email FROM `".DB_PREFIX."_admins` WHERE aid = (SELECT aid FROM `".DB_PREFIX."_bans` WHERE bid = ?)", array((int)$BanId));
        $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php")+4);
		if(isset($GLOBALS['config']['protest.emailonlyinvolved']) && $GLOBALS['config']['protest.emailonlyinvolved'] == 1 && !empty($emailinfo->fields['email']))
			$admins = array(array('aid' => $emailinfo->fields['aid'], 'user' => $emailinfo->fields['user'], 'email' => $emailinfo->fields['email']));
		else
			$admins = $userbank->GetAllAdmins();
		foreach($admins AS $admin)
		{
			$message = "";
			$message .= "Здравствуйте " . $admin['user'] . ",\n\n";
			$message .= "Новый протест бана был опубликован на вашей странице SourceBans.\n\n";
			$message .= "Игрок: ".$_POST['PlayerName']." (".$_POST['SteamID'].")\nЗабаненый: ".$protadmin['user']."\nСообщение: ".$_POST['BanReason']."\n\n";
			$message .= "Кликните по ссылке чтобы увидеть протест бана.\n\nhttp://" . sb_get_site_host() . $requri . "?p=admin&c=bans#^1";
			if($userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS, $admin['aid']) && $userbank->HasAccess(ADMIN_NOTIFY_PROTEST, $admin['aid']))
				EMail($admin['email'], "[SourceBans] Добавлен протест бана", $message, $headers);
		}

		CreateGreenBox("Успешно", "Ваш протест был отправлен.");
	}
}

$theme->assign('steam_id', $SteamID);
$theme->assign('ip', $IP);
$theme->assign('player_name', $PlayerName);
$theme->assign('reason', $UnbanReason);
$theme->assign('player_email', $Email);
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');

$theme->display('page_protestban.tpl');
?>
<script type="text/javascript">
function changeType(szListValue)
{
	$('steam.row').style.display = (szListValue == "0" ? "" : "none");
	$('ip.row').style.display    = (szListValue == "1" ? "" : "none");
}
$('Type').options[<?php echo $Type; ?>].selected = true;
changeType(<?php echo $Type; ?>);
</script>
