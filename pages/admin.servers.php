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
?>

<div id="admin-page-content">
	<?php  
		if(!defined("IN_SB")){echo "Ошибка доступа!";die();} 
		global $userbank, $theme;
		
			$servers = $GLOBALS['db']->GetAll("SELECT srv.ip ip, srv.port port, srv.sid sid, srv.rcon rcon, mo.icon icon, srv.enabled enabled FROM `" . DB_PREFIX . "_servers` AS srv
											   LEFT JOIN `" . DB_PREFIX . "_mods` AS mo ON mo.mid = srv.modid
											   ORDER BY modid, sid");
			$server_count = $GLOBALS['db']->GetRow("SELECT COUNT(sid) AS cnt FROM `" . DB_PREFIX . "_servers`") ;

		
        // Как в SendRcon: веб-OWNER видит RCON на всех серверах.
        // Остальным — только серверы из admins_servers_groups + флаг SM_RCON/SM_ROOT.
        $is_owner = $userbank->HasAccess(ADMIN_OWNER);
        $has_rcon_flag = $userbank->HasAccess(SM_RCON . SM_ROOT);
        $server_access = array();
        if ($is_owner) {
            foreach ($servers as $s) {
                $server_access[] = (int)$s['sid'];
            }
        } elseif ($has_rcon_flag) {
            $servers2 = $GLOBALS['db']->GetAll("SELECT `server_id`, `srv_group_id` FROM ".DB_PREFIX."_admins_servers_groups WHERE admin_id = ". (int)$userbank->GetAid());
            foreach ($servers2 as $server) {
                $server_access[] = (int)$server['server_id'];
                if ($server['srv_group_id'] > 0) {
                    $servers_in_group = $GLOBALS['db']->GetAll("SELECT `server_id` FROM ".DB_PREFIX."_servers_groups WHERE group_id = ". (int)$server['srv_group_id']);
                    foreach ($servers_in_group as $servig) {
                        $server_access[] = (int)$servig['server_id'];
                    }
                }
            }
        }

        foreach ($servers as &$server) {
            $assigned = in_array((int)$server['sid'], $server_access, true);
            // Кнопка только если есть пароль RCON и доступ
            $server['rcon_access'] = ($is_owner || $has_rcon_flag) && $assigned && !empty($server['rcon']);
        }
        unset($server);
        
		// List mods
		$modlist = $GLOBALS['db']->GetAll("SELECT mid, name FROM `" . DB_PREFIX . "_mods` WHERE `mid` > 0 AND `enabled` = 1 ORDER BY name ASC");
		// List groups
		$grouplist = $GLOBALS['db']->GetAll("SELECT gid, name FROM `" . DB_PREFIX . "_groups` WHERE type = 3 ORDER BY name ASC");
		
		// Vars for server list
		$theme->assign('permission_list', $userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_SERVERS));
		$theme->assign('permission_config', $userbank->HasAccess(ADMIN_OWNER));
		$theme->assign('permission_editserver', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_SERVERS));
		$theme->assign('pemission_delserver', $userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_SERVERS));
		$theme->assign('server_count', $server_count['cnt']);
		$theme->assign('server_list', $servers);
		
		// Vars for add server
		$theme->assign('permission_addserver', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_SERVER));
		$theme->assign('modlist', 	$modlist);
		$theme->assign('grouplist', $grouplist);
        // set vars from edit form
        $theme->assign('edit_server', false);
        $theme->assign('ip', 	'');
        $theme->assign('port', 	'');
        $theme->assign('rcon', 	'');
        $theme->assign('modid', '');
		
		$theme->assign('submit_text', "Добавить сервер");
	?>
	
	
	<div id="0" style="display:none;">
		<?php $theme->display('page_admin_servers_list.tpl'); ?>
	</div>
	
	
	<div id="1" style="display:none;">
		<?php $theme->display('page_admin_servers_add.tpl'); ?>
	</div>

</div>
