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

global $theme, $userbank;

$sid = (int)$_GET['id'];

// Access on that server? OWNER — все серверы (как SendRcon).
$access = $userbank->HasAccess(ADMIN_OWNER);
if (!$access) {
	$servers = $GLOBALS['db']->GetAll("SELECT `server_id`, `srv_group_id` FROM ".DB_PREFIX."_admins_servers_groups WHERE admin_id = ". (int)$userbank->GetAid());
	if (!is_array($servers))
		$servers = array();
	foreach ($servers as $server) {
		if (!is_array($server))
			continue;
		if ((int)$server['server_id'] == $sid) {
			$access = true;
			break;
		}
		if ($server['srv_group_id'] > 0) {
			$servers_in_group = $GLOBALS['db']->GetAll("SELECT `server_id` FROM ".DB_PREFIX."_servers_groups WHERE group_id = ". (int)$server['srv_group_id']);
			if (!is_array($servers_in_group))
				$servers_in_group = array();
			foreach ($servers_in_group as $servig) {
				if (is_array($servig) && (int)$servig['server_id'] == $sid) {
					$access = true;
					break 2;
				}
			}
		}
	}
}

$theme->assign('id', $sid);
$theme->assign('permission_rcon', ($access && ($userbank->HasAccess(ADMIN_OWNER) || $userbank->HasAccess(SM_RCON . SM_ROOT))));
echo '<div id="admin-page-content">';
echo '<div id="0" class="admin-pane is-on">';
$_f = sb_ui_v2_theme_fragment('admin_servers_rcon.twig');
if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '</div>';
