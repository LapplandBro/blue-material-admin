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

if(!isset($_GET['id']))
{
	CreateRedBox("Ошибка", "ID администратора не указан");
	PageDie();
}
$_GET['id'] = (int)$_GET['id'];

if(!$userbank->GetProperty("user", $_GET['id']))
{
	$log = new CSystemLog("e", "Получение данных администратора не удалось", "Не могу найти данные для администратора с идентификатором '".$_GET['id']."'");
	CreateRedBox("Ошибка", "Ошибка получения текущих данных.");
	PageDie();
}


// OWNER — полный доступ. Иначе: свои детали или EDIT_ADMINS + sb_can_manage_admin (не OWNER/protected).
$editing_self = ((int)$_GET['id'] === (int)$userbank->GetAid());
if (!$userbank->HasAccess(ADMIN_OWNER)) {
	$can = $editing_self || ($userbank->HasAccess(ADMIN_EDIT_ADMINS) && function_exists('sb_can_manage_admin') && sb_can_manage_admin((int)$_GET['id']));
	if (!$can) {
		$log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty("user") . " пытался редактировать детали ".$userbank->GetProperty('user', $_GET['id']).", не имея на это прав.");
		CreateRedBox("Ошибка", "Вы не имеете прав редактирования других профилей.");
		PageDie();
	}
}

$errorScript = "";
$totp_admin_msg = '';

// OWNER / EDIT_ADMINS: сброс 2FA другому админу (lockout recovery).
if (isset($_POST['reset_totp']) && function_exists('sb_totp_disable')) {
	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	$can_reset = $userbank->HasAccess(ADMIN_OWNER)
		|| ($userbank->HasAccess(ADMIN_EDIT_ADMINS) && function_exists('sb_can_manage_admin') && sb_can_manage_admin((int)$_GET['id']));
	if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
		$totp_admin_msg = 'Неверный CSRF. Обновите страницу.';
	} elseif (!$can_reset) {
		$totp_admin_msg = 'Недостаточно прав для сброса 2FA.';
	} elseif ((int)$_GET['id'] === (int)$userbank->GetAid() && !$userbank->HasAccess(ADMIN_OWNER)) {
		$totp_admin_msg = 'Свой 2FA сбрасывайте в разделе «Аккаунт».';
	} else {
		sb_totp_disable((int)$_GET['id']);
		$totp_admin_msg = '2FA для этого администратора сброшена.';
		new CSystemLog("m", "2FA reset", $userbank->GetProperty("user") . " сбросил TOTP у aid=" . (int)$_GET['id']);
	}
}

// Form submitted?
if(isset($_POST['adminname']))
{
	$a_name = RemoveCode($_POST['adminname']);
	$a_steam = trim(RemoveCode($_POST['steam']));
	$a_email = trim(RemoveCode($_POST['email']));
	$a_serverpass = $_POST['a_useserverpass'] == "on";
	$pw_changed = false;
	$serverpw_changed = false;
	$a_period = false;
	$p_discord = true;
	$p_comment = true;
	$p_vk = true;
	
	// Form validation
	$error = 0;
	
	// ADM TIME //
	if(!empty($_POST['period'])) {
		
		$a_period = true;
		
		if (!preg_match("|^[\d]+$|",$_POST['period'])) {
			$error++;
			$errorScript .= "$('period.msg').innerHTML = 'Только число';";
			$errorScript .= "$('period.msg').setStyle('display', 'block');";
		}
	}
	if ($_POST['permaadmin'] == "true")
        $a_period = true;
	// ADM TIME //
	
	
	// Check name
	if(empty($a_name))
	{
		$error++;
		$errorScript .= "$('adminname.msg').innerHTML = 'Введите имя администратора.';";
		$errorScript .= "$('adminname.msg').setStyle('display', 'block');";
	}
	else{
        if(strstr($a_name, "'"))
		{
			$error++;
			$errorScript .= "$('adminname.msg').innerHTML = 'Имя администратора не может содержать \" \' \".';";
			$errorScript .= "$('adminname.msg').setStyle('display', 'block');";
		}
		else
		{
            if($a_name != $userbank->GetProperty('user', $_GET['id']) && is_taken("admins", "user", $a_name))
            {
                $error++;
				$errorScript .= "$('adminname.msg').innerHTML = 'Администратор с таким именем уже существует.';";
				$errorScript .= "$('adminname.msg').setStyle('display', 'block');";
            }
		}
	}
	
	// If they didnt type a steamid
	if((empty($a_steam) || strlen($a_steam) < 10))
	{
		$error++;
		$errorScript .= "$('steam.msg').innerHTML = 'Введите Steam ID или Community ID администратора.';";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
	}
	else
	{
		// Validate the steamid or fetch it from the community id
		if((!is_numeric($a_steam) 
		&& !validate_steam($a_steam))
		|| (is_numeric($a_steam) 
		&& (strlen($a_steam) < 15
		|| !validate_steam($a_steam = FriendIDToSteamID($a_steam)))))
		{
			$error++;
			$errorScript .= "$('steam.msg').innerHTML = 'Введите реальный Steam ID или Community ID.';";
			$errorScript .= "$('steam.msg').setStyle('display', 'block');";
		}
		else
		{
			// Is an other admin already registred with that steam id?
			if($a_steam != $userbank->GetProperty('authid', $_GET['id']) && is_taken("admins", "authid", $a_steam))
			{
				$admins = $userbank->GetAllAdmins();
				foreach($admins as $admin)
				{
					if($admin['authid'] == $a_steam)
					{
						$name = $admin['user'];
						break;
					}
				}
				$error++;
				$errorScript .= "$('steam.msg').innerHTML = 'Администратор ".htmlspecialchars(addslashes($name))." уже использует этот Steam ID.';";
				$errorScript .= "$('steam.msg').setStyle('display', 'block');";
			}
		}
	}
	
	// No email
	if(empty($a_email))
	{
		// Only required, if admin has web permissions.
		if($GLOBALS['userbank']->GetProperty('extraflags', $_GET['id']) != 0 || $GLOBALS['userbank']->GetProperty('gid', $_GET['id']) > 0)
		{
			$error++;
			$errorScript .= "$('email.msg').innerHTML = 'Введите e-mail.';";
			$errorScript .= "$('email.msg').setStyle('display', 'block');";
		}
	}
	else{
		// Is an other admin already registred with that email address?
		if($a_email != $userbank->GetProperty('email', $_GET['id']) && is_taken("admins", "email", $a_email))
		{
			$admins = $userbank->GetAllAdmins();
			foreach($admins as $admin)
			{
				if($admin['email'] == $a_email)
				{
					$name = $admin['user'];
					break;
				}
			}
			$error++;
			$errorScript .= "$('email.msg').innerHTML = 'Этот email уже используется ".htmlspecialchars(addslashes($name)).".';";
			$errorScript .= "$('email.msg').setStyle('display', 'block');";
		}
		/*else if(!validate_email($a_email))
			$error++;
			$errorScript .= "$('email.msg').innerHTML = 'Please enter a valid email address.';";
			$errorScript .= "$('email.msg').setStyle('display', 'block');";
		}*/
	}
	
	// Only validate passwords, if admin has access to edit it at all
	if($userbank->HasAccess(ADMIN_OWNER) || $_GET['id'] == $userbank->GetAid())
	{
		// Don't change the password, if not set
		if(!empty($_POST['password']))
		{
			$pw_changed = true;
			// DID type a password, so he wants to change it.
			// Password too short?
			if(strlen($_POST['password']) < MIN_PASS_LENGTH)
			{
				$error++;
				$errorScript .= "$('password.msg').innerHTML = 'Ваш пароль должен быть длиной по меньшей мере " . MIN_PASS_LENGTH . " символов.';";
				$errorScript .= "$('password.msg').setStyle('display', 'block');";
			}
			else 
			{
				// No confirmation typed
				if(empty($_POST['password2']))
				{
					$error++;
					$errorScript .= "$('password2.msg').innerHTML = 'подтвердите пароль.';";
					$errorScript .= "$('password2.msg').setStyle('display', 'block');";
				}
				// Passwords match?
				else if($_POST['password'] != $_POST['password2'])
				{
					$error++;
					$errorScript .= "$('password2.msg').innerHTML = 'Пароли не совпадают.';";
					$errorScript .= "$('password2.msg').setStyle('display', 'block');";
				}
			}
		}
		
		// Check for the serverpassword
		if($_POST['a_useserverpass'] == "on")
		{
			if(!empty($_POST['a_serverpass']))
				$serverpw_changed = true;
		
			// No password given and no set before?
			$srvpw = $userbank->GetProperty('srv_password', $_GET['id']);
			if(empty($_POST['a_serverpass']) && empty($srvpw))
			{
				$error++;
				$errorScript .= "$('a_serverpass.msg').innerHTML = 'Необходимо ввести пароль сервера или снимите флажок.';";
				$errorScript .= "$('a_serverpass.msg').setStyle('display', 'block');";
			}
			// Password too short?
			else if(strlen($_POST['a_serverpass']) < MIN_PASS_LENGTH)
			{
				$error++;
				$errorScript .= "$('a_serverpass.msg').innerHTML = 'Ваш пароль должен быть длиной по меньшей мере " . MIN_PASS_LENGTH . " символов.';";
				$errorScript .= "$('a_serverpass.msg').setStyle('display', 'block');";
			}
		}
	}
	
	// SteamID's listed in config.php's SB_PROTECTED_STEAMIDS must never be reassignable via the
	// admin panel - neither taken away from the protected admin, nor assigned to another account
	// (which would let an unprotected admin impersonate a protected identity).
	$protected_steamids = array_filter(array_map('trim', explode(',', defined('SB_PROTECTED_STEAMIDS') ? SB_PROTECTED_STEAMIDS : '')));
	$current_steam = $userbank->GetProperty('authid', $_GET['id']);
	if($error == 0 && !$userbank->HasAccess(ADMIN_OWNER) && ((!empty($current_steam) && in_array($current_steam, $protected_steamids)) || in_array($a_steam, $protected_steamids)))
	{
		$error++;
		$log = new CSystemLog("w", "Попытка изменения защищённого SteamID", $userbank->GetProperty("user") . " попытался изменить защищённый SteamID (SB_PROTECTED_STEAMIDS) администратора ".$_GET['id'].".");
		$errorScript .= "$('steam.msg').innerHTML = 'Этот Steam ID защищён и не может быть изменён.';";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
		if (function_exists('sb_tripwire_punish_actor')) {
			sb_tripwire_punish_actor(null, 'попытался изменить защищённый SteamID администратора ' . (int)$_GET['id']);
		}
	}

	// Only proceed, if there are no errors in the form
	if($error == 0)
	{
		// set the basic fields
		$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`user` = ?, `authid` = ?, `email` = ?
									WHERE `aid` = ?", array($a_name, $a_steam, $a_email, $_GET['id']));
		
		// Password changed?
		if($pw_changed)
		{
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`password` = ?
									WHERE `aid` = ?", array($userbank->hash_password($_POST['password']), $_GET['id']));
		}
		
		// ADM TIME //
		if($a_period)
		{
			if($_POST['permaadmin'] == 'true') {
				$a_period = 0;
			}
			else {
				$a_period = time() + intval($_POST['period']) * 86400;
			}
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`expired` = ?
									WHERE `aid` = ?", array($a_period, $_GET['id']));
		}
		// ADM TIME //
		
		// ADM discord //
		if($p_discord)
		{
			$discord_save = RemoveCode(isset($_POST['discord']) ? $_POST['discord'] : '');
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`discord` = ?
									WHERE `aid` = ?", array($discord_save, $_GET['id']));
		}
		// ADM discord //
		
		// ADM vk //
		if($p_vk)
		{
			$vk_save = RemoveCode(isset($_POST['vk']) ? $_POST['vk'] : '');
			$vk_save = str_replace(array('http://', 'https://', '/', 'vk.com', 'vk.ru'), '', $vk_save);
			$vk_save = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $vk_save);
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`vk` = ?
									WHERE `aid` = ?", array($vk_save, $_GET['id']));
		}
		// ADM vk //
		
		// ADM comment //
		if($p_comment)
		{
			// Без HTML: иначе stored XSS на банлисте / в карточке админа.
			$comment_save = RemoveCode(isset($_POST['comment']) ? $_POST['comment'] : '');
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`comment` = ?
									WHERE `aid` = ?", array($comment_save, $_GET['id']));
		}
		// ADM comment //
		
		// Server Admin Password changed?
		if($serverpw_changed)
		{
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`srv_password` = ?
									WHERE `aid` = ?", array($_POST['a_serverpass'], $_GET['id']));
		}
		// Remove the server password
		else if($_POST['a_useserverpass'] != "on")
		{
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
									`srv_password` = NULL
									WHERE `aid` = ?", array($_GET['id']));
		}
		
		// to prevent rehash window to error with "no access", cause pw doesn't match
		$ownpwchanged = false;
		// bcrypt каждый раз новый — сравнивать хэши бессмысленно; смена своего пароля = релогин.
		if($_GET['id']==$userbank->GetAid() && !empty($_POST['password']))
			$ownpwchanged = true;
		
		if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
		{
			// rehash the admins on the servers
			$serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `".DB_PREFIX."_servers` s
												LEFT JOIN `".DB_PREFIX."_admins_servers_groups` asg ON asg.admin_id = ".(int)$_GET['id']."
												LEFT JOIN `".DB_PREFIX."_servers_groups` sg ON sg.group_id = asg.srv_group_id
												WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
												OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
												AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
			$allservers = array();
			foreach($serveraccessq as $access) {
				if(!in_array($access['sid'], $allservers)) {
					$allservers[] = $access['sid'];
				}
			}
			$rehashing = true;
		}
		
		$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$_GET['id']));
		$log = new CSystemLog("m", "Обновление данных администратора", "Админ (" . $admname['user'] . ") изменил детали администратора");
		if($ownpwchanged)
			echo '<script>setTimeout(\'ShowBox("Обновление данных", "Обновление данных администратора...", "green", "index.php?p=admin&c=admins", true);TabToReload();\', 1200);</script>';
		else if(isset($rehashing))
			echo '<script>setTimeout(\'ShowRehashBox("'.implode(",", $allservers).'", "Обновление данных", "Обновление данных администратора...", "green", "index.php?p=admin&c=admins", true);TabToReload();\', 1200);</script>';
		else
			echo '<script>setTimeout(\'ShowBox("Обновление данных", "Обновление данных администратора...", "green", "index.php?p=admin&c=admins", true);TabToReload();\', 1200);</script>';
	}
}
// get current values
else
{
	$a_name = $userbank->GetProperty("user", $_GET['id']);
	$a_steam = trim($userbank->GetProperty("authid", $_GET['id']));
	$a_email = $userbank->GetProperty("email", $_GET['id']);
	$a_serverpass = $userbank->GetProperty("srv_password", $_GET['id']);
	$a_serverpass = !empty($a_serverpass);
	
	// Add discord //
	$a_discord = $userbank->GetProperty("discord", $_GET['id']);
	// Add discord //
	
	// Add comment //
	$a_comment = $userbank->GetProperty("comment", $_GET['id']);
	// Add comment //
	
	// Add vk //
	$a_vk = $userbank->GetProperty("vk", $_GET['id']);
	// Add vk //
	
	// ADM TIME //
	$a_expired = $userbank->GetProperty("expired", $_GET['id']);

	if($a_expired == 0) {
		$a_expired_text = 'Никогда';
	}
	elseif($a_expired < time()) {
		$a_expired_text = 'Истёк';
	}
	else{
		$a_expired_text = 'через&nbsp;'.round((($a_expired - time()) / 86400),0) . '&nbsp;дней';
	}
	// ADM TIME //
}

$theme->assign('change_pass', ($userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS) || $_GET['id'] == $userbank->GetAid()));
$theme->assign('user', $a_name);
$theme->assign('authid', $a_steam);
$theme->assign('email', $a_email);
// ADM TIME //
$theme->assign('expired_text', $a_expired_text);
// ADM TIME //
// ADM comment //
$theme->assign('comment', $a_comment);
// ADM comment //
// ADM vk //
$theme->assign('vk', $a_vk);
// ADM vk //
// ADM discord //
$theme->assign('discord', $a_discord);
// ADM discord //
$theme->assign('a_spass', $a_serverpass);
$theme->assign('totp_enabled_admin', function_exists('sb_totp_is_enabled') && sb_totp_is_enabled((int)$_GET['id']));
$theme->assign('totp_admin_msg', $totp_admin_msg);
$theme->assign('can_reset_totp', $userbank->HasAccess(ADMIN_OWNER) || ($userbank->HasAccess(ADMIN_EDIT_ADMINS) && function_exists('sb_can_manage_admin') && sb_can_manage_admin((int)$_GET['id'])));
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');

$theme->display('page_admin_edit_admins_details.tpl');
?>
<script type="text/javascript">window.addEvent('domready', function(){
<?php echo $errorScript; ?>
});
</script>
