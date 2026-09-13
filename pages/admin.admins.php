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
global $userbank, $ui, $theme;

// Note: admin deletion (RemoveAdmin() in includes/sb-callback.php) already enforces
// SB_PROTECTED_STEAMIDS, so no additional check is needed on this listing page.

if (!isset($page) || (int)$page < 1)
	$page = 1;
if (isset($_GET['page']) && $_GET['page'] > 0)
	$page = intval($_GET['page']);
if (!isset($AdminsPerPage) || (int)$AdminsPerPage < 1)
	$AdminsPerPage = defined('SB_BANS_PER_PAGE') ? max(1, (int)SB_BANS_PER_PAGE) : 30;
if (!isset($admins) || !is_array($admins))
	$admins = array();
if (!isset($admin_count))
	$admin_count = count($admins);
if (!isset($dateformat) || $dateformat === '')
	$dateformat = !empty($GLOBALS['config']['config.dateformat']) ? $GLOBALS['config']['config.dateformat'] : 'm-d-y H:i';

$AdminsStart = intval(($page-1) * $AdminsPerPage);
$AdminsEnd = intval($AdminsStart+$AdminsPerPage);
if ($AdminsEnd > $admin_count) $AdminsEnd = $admin_count;

if (!function_exists('SteamID2CommunityID')) {
function SteamID2CommunityID($steamid) 
{
	if (function_exists('GetCommunityIDFromSteamID2'))
		return GetCommunityIDFromSteamID2($steamid);
	$steamid = (string)$steamid;
	$parts = explode(':', str_replace('STEAM_', '' ,$steamid));
	if (!isset($parts[1], $parts[2]) || !function_exists('bcadd'))
		return '';
	return bcadd(bcadd('76561197960265728', (string)$parts[1]), bcmul((string)$parts[2], '2'));
}
} 

// List Page
$admin_list = array();
foreach($admins AS $admin)
{
	if (!is_array($admin) || !isset($admin['aid']))
		continue;
	$admin['immunity'] = $userbank->GetProperty("srv_immunity", $admin['aid']);
	$admin['web_group'] = $userbank->GetProperty("group_name", $admin['aid']);
	$admin['server_group'] = $userbank->GetProperty("srv_groups", $admin['aid']);
	
	// Add contakt
	$admin['vk_profile'] = $userbank->GetProperty("vk", $admin['aid']);
	if($admin['vk_profile'] == ""){
		$admin['vk_profile'] = "Нет данных";
	}else{
		$admin['vk_profile'] = htmlspecialchars($admin['vk_profile']);
		$admin['vk_profile'] = "<a href='https://vk.com/" .$admin['vk_profile'] . "'>" . $admin['vk_profile'] . "</a>";
	}
	
	$admin['sk_profile'] = $userbank->GetProperty("discord", $admin['aid']);
	if($admin['sk_profile'] == ""){
		$admin['sk_profile'] = "Нет данных";
	}else{
		$admin['sk_profile'] = htmlspecialchars($admin['sk_profile']);
	}
	
	$admin['comment_profile'] = $userbank->GetProperty("comment", $admin['aid']);
	if($admin['comment_profile'] == ""){
		$admin['comment_profile'] = "Нет доступных комментариев.";
	}
	
	$admin['email_profile'] = $userbank->GetProperty("email", $admin['aid']);
	$admin['communityid_profile'] = SteamID2CommunityID($userbank->GetProperty("authid", $admin['aid']));
	$admin['steam_id_amd'] = $userbank->GetProperty("authid", $admin['aid']);
	// Add contakt
	
	if(empty($admin['web_group']) || $admin['web_group']==" ")
	{
  		$admin['web_group'] = "Группа\индивид. права отсутствуют";
	}
	if(empty($admin['server_group']) || $admin['server_group']==" ")
	{
		$admin['server_group'] = "Группа\индивид. права отсутствуют";
	}
	$admAuth = isset($admin['authid']) ? $admin['authid'] : null;
	if (function_exists('sb_admin_issued_where')) {
		list($issuedSql, $issuedParams) = sb_admin_issued_where((int)$admin['aid'], $admAuth);
		list($issuedSqlB, $issuedParamsB) = sb_admin_issued_where((int)$admin['aid'], $admAuth, 'B');
	} else {
		$issuedSql = 'aid = ?';
		$issuedParams = array((int)$admin['aid']);
		$issuedSqlB = 'B.aid = ?';
		$issuedParamsB = array((int)$admin['aid']);
	}
	$num = $GLOBALS['db']->GetRow("SELECT count(authid) AS num FROM `" . DB_PREFIX . "_bans` WHERE ".$issuedSql, $issuedParams);
	$admin['bancount'] = (is_array($num) && isset($num['num'])) ? $num['num'] : 0;

	$nodem = $GLOBALS['db']->GetRow("SELECT count(B.bid) AS num FROM `" . DB_PREFIX . "_bans` AS B WHERE ".$issuedSqlB." AND NOT EXISTS (SELECT D.demid FROM `" . DB_PREFIX . "_demos` AS D WHERE D.demid = B.bid)", $issuedParamsB);
	$admin['aid'] = $admin['aid'];
	$admin['nodemocount'] = (is_array($nodem) && isset($nodem['num'])) ? $nodem['num'] : 0;

	// Кол-во блокировок (чат/микрофон), выданных этим админом - для ссылки "найти" в списке админов.
	$commsnum = $GLOBALS['db']->GetRow("SELECT count(bid) AS num FROM `" . DB_PREFIX . "_comms` WHERE ".$issuedSql, $issuedParams);
	$admin['commscount'] = (is_array($commsnum) && isset($commsnum['num'])) ? $commsnum['num'] : 0;

	$admin['name'] = stripslashes($admin['user']);
	$admin['server_flag_string'] = SmFlagsToSb($userbank->GetProperty("srv_flags",$admin['aid']));
	$admin['web_flag_string'] = BitToString($userbank->GetProperty("extraflags",$admin['aid']));
	

	$expired = isset($admin['expired']) ? (int)$admin['expired'] : 0;
	if($expired == 0) {
		$admin['expired_text'] = 'Никогда';
	}
	elseif($expired < time()) {
		$admin['expired_text'] = 'Истёк';
	}
	else{
		$admin['expired_text'] = 'Через&nbsp;'.round((($expired - time()) / 86400),0).'&nbsp;дн.';
	}
	$admin['del_warn'] = '';
	if($expired == 0) {
		$admin['expired_cv'] = 'Навсегда';
		$admin['del_warn'] = "У этого админа вечная админка.\nВы действительно хотите удалить его?";
	}
	elseif($expired < time()) {
		$admin['expired_cv'] = 'Уже <b>Истек</b>';
	} else {
		$admin['expired_cv'] = date('До d.m.Y в <b>H:i</b>',$expired);
		$admin['del_warn'] = "У этого админа не истёк срок админки.\nВы действительно хотите удалить его?";
	}
	
	$lastvisit = $userbank->GetProperty("lastvisit", $admin['aid']);
	if(!$lastvisit)
		$admin['lastvisit'] = "Никогда";
	else
		$admin['lastvisit'] = SBDate($dateformat,$userbank->GetProperty("lastvisit", $admin['aid']));
	$admin['avatar'] = GetUserAvatar($userbank->GetProperty('authid', $admin['aid']));

	$admin['warnings'] = $GLOBALS['db']->GetOne("SELECT COUNT(*) FROM `" . DB_PREFIX . "_warns` WHERE `expires` > " . time() . " AND `arecipient` = " . (int) $admin['aid'] . ";");
	array_push($admin_list, $admin);
}

$expiredQ = (isset($_GET['showexpiredadmins']) && $_GET['showexpiredadmins'] == 'true') ? '&showexpiredadmins=true' : '';
$adminsListBase = 'index.php?p=admin&c=admins';
if (isset($_GET['advSearch']) && isset($_GET['advType']))
	$adminsListBase .= '&advSearch=' . rawurlencode((string)$_GET['advSearch']) . '&advType=' . rawurlencode((string)$_GET['advType']);
$adminsListBase .= $expiredQ;

if ($page > 1)
{
	$prev = CreateLinkR('Назад', $adminsListBase . '&page=' . ($page-1));
}
else
{
	$prev = "";
}
if ($AdminsEnd < $admin_count)
{
	$next = CreateLinkR('Вперёд', $adminsListBase . '&page=' . ($page+1));
}
else
	$next = "";

$admin_nav_p = '';
$pages = ($AdminsPerPage > 0) ? ceil($admin_count/$AdminsPerPage) : 1;
if($pages > 1) {
	$pageHrefJs = json_encode($adminsListBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	$admin_nav_p = '<label class="v2-page-label">Страница <select class="form-select form-select-sm v2-page-select" id="PageChanger" onchange=\'window.location.href=' . $pageHrefJs . '+"&amp;page="+this.value;\'>';
	for($i=1;$i<=$pages;$i++) {
		$sel = (isset($_GET['page']) && (int)$_GET['page'] === $i) ? ' selected="selected"' : '';
		if ($sel === '' && $i === (int)$page)
			$sel = ' selected="selected"';
		$admin_nav_p .= '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
	}
	$admin_nav_p .= '</select> <span class="v2-page-of">из ' . (int)$pages . '</span></label>';
}

$admin_nav = '';
if (strlen($prev) > 0 || strlen($next) > 0)
{
	$admin_nav = '<nav class="v2-page-arrows" aria-label="Страницы">';
	if (strlen($prev) > 0)
		$admin_nav .= $prev;
	if (strlen($next) > 0)
		$admin_nav .= $next;
	$admin_nav .= '</nav>';
}

$show_expired_admins = (isset($_GET['showexpiredadmins']) && $_GET['showexpiredadmins'] == 'true');
if($show_expired_admins) {
	$btn_href = 'index.php?p=admin&c=admins';
	$btn_rem = '<button type="button" onclick="removeExpiredAdmins()" class="btn btn-outline-secondary btn-sm">Удалить всех истёкших админов</button>';
} else{
	$btn_href = 'index.php?p=admin&c=admins&showexpiredadmins=true';
	$btn_rem = '';
}

$res = $GLOBALS['db']->Execute("SELECT aid FROM `".DB_PREFIX."_admins` WHERE `support` = '1'");
$checked = array();
if (is_object($res))
{
	while (!$res->EOF)
	{
		$chek_in = array();
		$chek_in['kid'] = $res->fields['aid'];
		array_push($checked,$chek_in);
		$res->MoveNext();
	}
}


echo '<div id="admin-page-content">';
echo '<div id="0" class="admin-pane is-on">';
	$theme->assign('checked_if', $checked);
	$theme->assign('permission_listadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_ADMINS));
	$theme->assign('permission_editadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS));
	$theme->assign('permission_deleteadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS));
	$theme->assign('admin_count', $admin_count);
	$theme->assign('admin_nav', $admin_nav);
	$theme->assign('admin_nav_p', $admin_nav_p);
	$theme->assign('admins', $admin_list);
	$theme->assign('btn_rem', $btn_rem);
	$theme->assign('btn_href', $btn_href);
	$theme->assign('show_expired_admins', $show_expired_admins);
	$theme->assign('allow_warnings', (isset($GLOBALS['config']['admin.warns']) && $GLOBALS['config']['admin.warns'] == "1"));
	$theme->assign('maxWarnings', isset($GLOBALS['config']['admin.warns.max']) ? $GLOBALS['config']['admin.warns.max'] : 0);
	require TEMPLATES_PATH . "/admin.admins.search.php";
	$_f = sb_ui_v2_theme_fragment('admin_admins_list.twig');
	if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';




// Add Page
$group_list = 				$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type = '3'");
$servers = 					$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_servers`");
$server_admin_group_list = 	$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
$server_group_list = 		$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type != 3");
if (!is_array($group_list)) $group_list = array();
if (!is_array($servers)) $servers = array();
if (!is_array($server_admin_group_list)) $server_admin_group_list = array();
if (!is_array($server_group_list)) $server_group_list = array();
$server_list = array();
$serverscript = "<script type=\"text/javascript\">";
foreach($servers AS $server)
{
	if (!is_array($server) || !isset($server['sid']))
		continue;
	$serverscript .= "xajax_ServerHostPlayers('".$server['sid']."', 'id', 'sa".$server['sid']."');";
	$info = array();
	$info['sid'] = $server['sid'];
	$info['ip'] = isset($server['ip']) ? $server['ip'] : '';
	$info['port'] = isset($server['port']) ? $server['port'] : '';
	array_push($server_list, $info);
}
$serverscript .= "</script>";

echo '<div id="1" class="admin-pane">';
	$theme->assign('group_list', $group_list);
	$theme->assign('server_list', $server_list);
	$theme->assign('server_script', $serverscript);
	$theme->assign('server_admin_group_list', $server_admin_group_list);
	$theme->assign('server_group_list', $server_group_list);
	$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_ADMINS));
	$_f = sb_ui_v2_theme_fragment('admin_admins_add.twig');
	if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';




// Overrides

// Saving changed overrides
$overrides_error = "";
$overrides_save_success = false;
try
{
	if(isset($_POST['new_override_name']))
	{
		if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_ADMINS))
			throw new Exception("Нет доступа к переопределениям.");
		$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
		if(!function_exists('sb_csrf_validate') || !sb_csrf_validate($csrf))
			throw new Exception('__SB_CSRF__');

		// Handle old overrides, if there are any.
		if(isset($_POST['override_id']))
		{
			// Apply changes first
			$edit_errors = "";
			foreach($_POST['override_id'] as $index => $id)
			{
				// Skip invalid stuff?!
				if($_POST['override_type'][$index] != "command" && $_POST['override_type'][$index] != "group")
					continue;
			
				$id = (int)$id;
				// Wants to delete this override?
				if(empty($_POST['override_name'][$index]))
				{
					$GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_overrides` WHERE id = ?;", array($id));
					continue;
				}
				
				// Check for duplicates
				$chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides` WHERE name = ? AND type = ? AND id != ?", array($_POST['override_name'][$index], $_POST['override_type'][$index], $id));
				if(!empty($chk))
				{
					$edit_errors .= "&bull; Такое название уже существует \\\"" . htmlspecialchars(addslashes($_POST['override_name'][$index])) . "\\\".<br />";
					continue;
				}
				
				// Edit the override
				$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_overrides` SET name = ?, type = ?, flags = ? WHERE id = ?;", array($_POST['override_name'][$index], $_POST['override_type'][$index], trim($_POST['override_flags'][$index]), $id));
			}
			
			if(!empty($edit_errors))
				throw new Exception("Ошибки ваших изменений:<br /><br />" . $edit_errors);
		}
	
		// Add a new override
		if(!empty($_POST['new_override_name']))
		{
			if($_POST['new_override_type'] != "command" && $_POST['new_override_type'] != "group")
				throw new Exception("Неверный оверрайд.");
			
			// Check for duplicates
			$chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides` WHERE name = ? AND type = ?", array($_POST['new_override_name'], $_POST['new_override_type']));
			if(!empty($chk))
				throw new Exception("Оверрайд с таким именем уже существует.");
			
			// Insert the new override
			$GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_overrides` (type, name, flags) VALUES (?, ?, ?);", array($_POST['new_override_type'], $_POST['new_override_name'], trim($_POST['new_override_flags'])));
		}
		
		$overrides_save_success = true;
	}
} catch (Exception $e) {
	if ($e->getMessage() === '__SB_CSRF__') {
		sb_csrf_fail_page(true);
	}
	$overrides_error = $e->getMessage();
}

		$overrides_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides`;");
		if (!is_array($overrides_list))
			$overrides_list = array();

echo '<div id="2" class="admin-pane">';
	$theme->assign('overrides_list', $overrides_list);
	$theme->assign('overrides_error', $overrides_error);
	$theme->assign('overrides_save_success', $overrides_save_success);
	$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_ADMINS));
	$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
	$_f = sb_ui_v2_theme_fragment('admin_overrides.twig');
	if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '</div>';

