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
global $userbank,$theme;

if (!isset($mod_list) || !is_array($mod_list))
	$mod_list = array();
if (!isset($mod_count))
	$mod_count = count($mod_list);
foreach ($mod_list as &$mod) {
	if (!is_array($mod))
		continue;
	$mod['icon_html'] = function_exists('sb_game_icon_html')
		? sb_game_icon_html(isset($mod['icon']) ? $mod['icon'] : '', isset($mod['name']) ? $mod['name'] : 'Мод', 18)
		: '';
}
unset($mod);
$theme->assign('mod_count', $mod_count);
$theme->assign('permission_listmods', $userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_MODS));
$theme->assign('permission_editmods', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_MODS));
$theme->assign('permission_deletemods', $userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_MODS));
$theme->assign('mod_list', $mod_list);
$theme->assign('permission_add', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_MODS));

echo '<div id="admin-page-content">';
echo '<div id="0" class="admin-pane is-on">';
$_f = sb_ui_v2_theme_fragment('admin_mods_list.twig');
if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '<div id="1" class="admin-pane">';
$_f = sb_ui_v2_theme_fragment('admin_mods_add.twig');
if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '</div>';
