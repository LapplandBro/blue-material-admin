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

$servers = $GLOBALS['db']->GetAll("SELECT srv.ip ip, srv.port port, srv.sid sid, srv.rcon rcon, mo.icon icon, srv.enabled enabled FROM `" . DB_PREFIX . "_servers` AS srv
								   LEFT JOIN `" . DB_PREFIX . "_mods` AS mo ON mo.mid = srv.modid
								   ORDER BY modid, sid");
if (!is_array($servers))
	$servers = array();
$server_count = $GLOBALS['db']->GetRow("SELECT COUNT(sid) AS cnt FROM `" . DB_PREFIX . "_servers`");
if (!is_array($server_count) || !isset($server_count['cnt']))
	$server_count = array('cnt' => count($servers));

// Как в SendRcon: веб-OWNER видит RCON на всех серверах.
// Остальным — только серверы из admins_servers_groups + флаг SM_RCON/SM_ROOT.
$is_owner = $userbank->HasAccess(ADMIN_OWNER);
$has_rcon_flag = $userbank->HasAccess(SM_RCON . SM_ROOT);
$server_access = array();
if ($is_owner) {
	foreach ($servers as $s) {
		if (is_array($s) && isset($s['sid']))
			$server_access[] = (int)$s['sid'];
	}
} elseif ($has_rcon_flag) {
	$servers2 = $GLOBALS['db']->GetAll("SELECT `server_id`, `srv_group_id` FROM ".DB_PREFIX."_admins_servers_groups WHERE admin_id = ". (int)$userbank->GetAid());
	if (!is_array($servers2))
		$servers2 = array();
	foreach ($servers2 as $server) {
		if (!is_array($server))
			continue;
		$server_access[] = (int)$server['server_id'];
		if ($server['srv_group_id'] > 0) {
			$servers_in_group = $GLOBALS['db']->GetAll("SELECT `server_id` FROM ".DB_PREFIX."_servers_groups WHERE group_id = ". (int)$server['srv_group_id']);
			if (!is_array($servers_in_group))
				$servers_in_group = array();
			foreach ($servers_in_group as $servig) {
				if (is_array($servig) && isset($servig['server_id']))
					$server_access[] = (int)$servig['server_id'];
			}
		}
	}
}

foreach ($servers as &$server) {
	if (!is_array($server))
		continue;
	$assigned = in_array((int)$server['sid'], $server_access, true);
	$server['rcon_access'] = ($is_owner || $has_rcon_flag) && $assigned && !empty($server['rcon']);
	$server['icon_html'] = function_exists('sb_game_icon_html')
		? sb_game_icon_html(isset($server['icon']) ? $server['icon'] : '', 'Мод', 18)
		: '';
}
unset($server);

$modlist = $GLOBALS['db']->GetAll("SELECT mid, name FROM `" . DB_PREFIX . "_mods` WHERE `mid` > 0 AND `enabled` = 1 ORDER BY name ASC");
if (!is_array($modlist))
	$modlist = array();
$grouplist = $GLOBALS['db']->GetAll("SELECT gid, name FROM `" . DB_PREFIX . "_groups` WHERE type = 3 ORDER BY name ASC");
if (!is_array($grouplist))
	$grouplist = array();

$theme->assign('permission_list', $userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_SERVERS));
$theme->assign('permission_config', $userbank->HasAccess(ADMIN_OWNER));
$theme->assign('permission_editserver', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_SERVERS));
$theme->assign('pemission_delserver', $userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_SERVERS));
$theme->assign('server_count', $server_count['cnt']);
$theme->assign('server_list', $servers);

$theme->assign('permission_addserver', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_SERVER));
$theme->assign('modlist', $modlist);
$theme->assign('grouplist', $grouplist);
$theme->assign('edit_server', false);
$theme->assign('ip', '');
$theme->assign('port', '');
$theme->assign('rcon', '');
$theme->assign('modid', '');
$theme->assign('submit_text', "Добавить сервер");

echo '<div id="admin-page-content">';
echo '<div id="0" class="admin-pane is-on">';
$_f = sb_ui_v2_theme_fragment('admin_servers_list.twig');
if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '<div id="1" class="admin-pane">';
$_f = sb_ui_v2_theme_fragment('admin_servers_add.twig');
if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '</div>';
