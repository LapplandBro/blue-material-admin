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

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS))
{
    $log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty('user') . " пытался загрузить демо, не имея на это прав.");
	sb_upload_access_denied('Нет доступа к загрузке демо');
}

$message = "";

if(isset($_POST['upload']))
{
	sb_upload_require_csrf();

	$orig = isset($_FILES['demo_file']['name']) ? (string)$_FILES['demo_file']['name'] : '';
	$tmp = isset($_FILES['demo_file']['tmp_name']) ? (string)$_FILES['demo_file']['tmp_name'] : '';
	$err = isset($_FILES['demo_file']['error']) ? (int)$_FILES['demo_file']['error'] : 4;

	if ($err !== 0 || $tmp === '' || !is_uploaded_file($tmp)) {
		$message = "<b>Не удалось получить файл.</b><br><br>";
	} elseif (!CheckExt(basename($orig), array("dem", "zip", "rar", "7z", "bz2", "gz"))
		|| strpos(pathinfo(basename($orig), PATHINFO_FILENAME), '.') !== false) {
		$message = "<b> Файл должен быть формата dem, zip, rar, 7z, bz2 или gz.</b><br><br>";
	} else {
		// На диске — случайное имя без расширения (демо не отдаются как PHP).
		$filename = md5(uniqid((string)mt_rand(), true));
		if (!@move_uploaded_file($tmp, rtrim(SB_DEMOS, '/\\') . DIRECTORY_SEPARATOR . $filename)) {
			$message = "<b>Не удалось сохранить файл.</b><br><br>";
		} else {
			@chmod(rtrim(SB_DEMOS, '/\\') . DIRECTORY_SEPARATOR . $filename, 0644);
			$message = "<script>window.opener.demo(" . json_encode($filename) . "," . json_encode($orig) . ");self.close()</script>";
			$log = new CSystemLog("m", "Демо загружено", "Новое демо было успешно загружено: " . htmlspecialchars($orig, ENT_QUOTES, 'UTF-8'));
		}
	}
}

$theme->assign("title", "Загрузить демо");
$theme->assign("message", $message);
$theme->assign("input_name", "demo_file");
$theme->assign("form_name", "demup");
$theme->assign("formats", "DEM, ZIP, RAR, 7Z, BZ2 или GZ");
$theme->assign("sb_csrf", function_exists('sb_csrf_token') ? sb_csrf_token() : '');

$theme->display('page_uploadfile.tpl');
