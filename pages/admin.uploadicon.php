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

include_once("../init.php");
include_once("../includes/system-functions.php");
global $theme, $userbank;

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_MODS|ADMIN_ADD_MODS))
{
    $log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty('user') . " пытался загрузить иконку МОДа, не имея на это прав.");
	sb_upload_access_denied('Нет доступа к загрузке иконки');
}

$message = "";
if(isset($_POST['upload']))
{
	// SECURITY: basename() strips any directory-traversal component from the
	// user-supplied filename, and we reject filenames with a "hidden" extension
	// (e.g. "shell.php.gif") in addition to the plain extension whitelist check.
	$icon_filename = basename($_FILES['icon_file']['name']);
	$has_valid_ext = ($icon_filename !== '')
		&& (CheckExt($icon_filename, "gif") || CheckExt($icon_filename, "jpg") || CheckExt($icon_filename, "png"))
		&& strpos(pathinfo($icon_filename, PATHINFO_FILENAME), '.') === false;

	if($has_valid_ext)
	{
		move_uploaded_file($_FILES['icon_file']['tmp_name'],SB_ICONS."/".$icon_filename);
		// json_encode() safely escapes the filename for use inside the inline <script> block.
		$message =  "<script>window.opener.icon(" . json_encode($icon_filename) . ");self.close()</script>";
        $log = new CSystemLog("m", "Иконка МОДа загружена", "Новая иконка МОДа загружена: ".htmlspecialchars($icon_filename));
	}
	else 
	{
		$message =  "<b> Файл должен быть формата gif, jpg или png.</b><br><br>";
	}
}

$theme->assign("title", "Загрузить иконку");
$theme->assign("message", $message);
$theme->assign("input_name", "icon_file");
$theme->assign("form_name", "iconup");
$theme->assign("formats", "GIF, PNG или JPG");

$theme->display('page_uploadfile.tpl');
