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
	CreateRedBox("Ошибка", "ID администратора не указан.");
	PageDie();
}

$_GET['id'] = (int)$_GET['id'];
if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS) || !sb_can_manage_admin((int)$_GET['id']))
{
	$log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty("user") . " пытался изменить группу админу ".$userbank->GetProperty('user', $_GET['id']).". не имея на это прав.");
	CreateRedBox("Ошибка", "Вы не имеете прав изменения групп админов.");
	PageDie();
}

if(!$userbank->GetProperty("user", $_GET['id']))
{
	$log = new CSystemLog("e", "Получение данных администратора не удалось", "Не могу найти данные для администратора с идентификатором '".$_GET['id']."'");
	CreateRedBox("Ошибка", "Ошибка получения текущих данных.");
	PageDie();
}

// Только POST + CSRF (раньше принимали GET wg/sg — privilege escalation).
if($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['wg']) || isset($_POST['sg'])))
{
	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if(!function_exists('sb_csrf_validate') || !sb_csrf_validate($csrf))
	{
		CreateRedBox("Ошибка", "Неверный CSRF-токен. Обновите страницу и попробуйте снова.");
		PageDie();
	}

	$_POST['wg'] = isset($_POST['wg']) ? (int)$_POST['wg'] : -2;
	$_POST['sg'] = isset($_POST['sg']) ? (int)$_POST['sg'] : -2;

	// Users require a password and email to have web permissions
	$password = $GLOBALS['userbank']->GetProperty('password', $_GET['id']);
	$email = $GLOBALS['userbank']->GetProperty('email', $_GET['id']);
	if($_POST['wg'] > 0 && (empty($password) || empty($email)))
	{
		echo '<script>setTimeout(function() { ShowBox("Ошибка", "Администраторы должны иметь пароль и адрес электронной почты для того, чтобы получить веб-разрешения.<br /><a href=\"index.php?p=admin&c=admins&o=editdetails&id=' . $_GET['id'] . '\" title=\"Редактировать детали Администратора\">Измените детали</a> сначала и попробуйте снова.", "red"); }, 1350);</script>';
	}
	else if(!$userbank->HasAccess(ADMIN_OWNER) && $_POST['wg'] > 0 && function_exists('sb_web_group_has_owner') && sb_web_group_has_owner($_POST['wg']))
	{
		$log = new CSystemLog("w", "Ошибка доступа", $userbank->GetProperty("user") . " пытался назначить OWNER веб-группу #" . $_POST['wg'] . " админу #" . $_GET['id']);
		CreateRedBox("Ошибка", "Нельзя назначить группу с правами OWNER.");
		PageDie();
	}
	else
	{
		if(isset($_POST['wg']) && $_POST['wg'] != "-2")	{
			if($_POST['wg'] == "-1")
				$_POST['wg'] = 0;
			
			// Edit the web group
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
											`gid` = ?
											WHERE `aid` = ?;", array($_POST['wg'], $_GET['id']));
		}
		
		if(isset($_POST['sg']) && $_POST['sg'] != "-2") {
			// Edit the server admin group
			$group = "";
			if($_POST['sg'] != -1)
			{
				$grps = $GLOBALS['db']->GetRow("SELECT name FROM ".DB_PREFIX."_srvgroups WHERE id = ?;", array($_POST['sg']));
				if($grps)
					$group = $grps['name'];
			}
				
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
											`srv_group` = ?
											WHERE aid = ?", array($group, $_GET['id']));
			
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins_servers_groups SET
										`group_id` = ?
										WHERE admin_id = ?;", array($_POST['sg'], $_GET['id']));
				
		}
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
			echo '<script>setTimeout(\'ShowRehashBox("'.implode(",", $allservers).'", "Администратор обновлен", "Детали админа были успешно обновлены", "green", "index.php?p=admin&c=admins");TabToReload();\', 1350);</script>';
		}
		else
			echo '<script>setTimeout(\'ShowBox("Администратор обновлен", "Детали админа были успешно обновлены", "green", "index.php?p=admin&c=admins");TabToReload();\', 1350);</script>';
		
		$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$_GET['id']));
		$log = new CSystemLog("m", "Группа админа обновлена", "Группа админа (" . $admname['user'] . ") была обновлена");
	}
}

$wgroups = $GLOBALS['db']->GetAll("SELECT gid, name FROM ".DB_PREFIX."_groups WHERE type != 3");
$sgroups = $GLOBALS['db']->GetAll("SELECT id, name FROM ".DB_PREFIX."_srvgroups");

$server_admin_group = $userbank->GetProperty('srv_groups', $_GET['id']);
foreach($sgroups as $sg)
{
	if($sg['name'] == $server_admin_group)
	{
		$server_admin_group = (int)$sg['id'];
		break;
	}
}

$theme->assign('group_admin_name', $userbank->GetProperty("user", $_GET['id']));
$theme->assign('group_admin_id', $userbank->GetProperty("gid", $_GET['id']));
$theme->assign('group_lst',  $sgroups);
$theme->assign('web_lst',  $wgroups);
$theme->assign('server_admin_group_id',  $server_admin_group);
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');

$theme->display('page_admin_edit_admins_group.tpl');
