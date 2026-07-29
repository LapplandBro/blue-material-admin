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
header("Content-Type: text/html; charset=utf-8");
include_once("../init.php");
include_once("../includes/system-functions.php");
global $theme, $userbank;

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_SERVER))
{
    $log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty('user') . " пытался загрузить изображение карты, не имея на это прав.");
	sb_upload_access_denied('Нет доступа к загрузке карты');
}

$message = sprintf("<br /><strong>Обратите внимание!</strong><br />Максимальный размер файла: %s<br />Максимальное кол-во файлов для загрузки: %s<br /><br />", ini_get('upload_max_filesize'), ini_get('max_file_uploads'));
if(isset($_POST['upload']))
{
	$fls = normalize_files_array($_FILES);

	// SECURITY: $curfile['type'] is a client-supplied Content-Type header and can be
	// spoofed by an attacker, so it must never be trusted as the sole extension check.
	// We additionally require a whitelisted extension (basename()'d to strip any path
	// traversal) with no secondary "hidden" extension (e.g. "shell.php.jpg").
	$msg_lines = array();
	$fcount = count($fls['mapimg_file']);
	foreach ($fls['mapimg_file'] as $curfile) {
		$safe_name = basename($curfile['name']);
		$has_valid_ext = CheckExt($safe_name, array("jpg", "jpeg"))
			&& strpos(pathinfo($safe_name, PATHINFO_FILENAME), '.') === false;

		if ($curfile['error'] != 0 || $curfile['type'] != "image/jpeg" || !$has_valid_ext || $safe_name === '') {
			$msg_lines[] = sprintf("Не удалось загрузить файл %s. Причина: %s.", $safe_name, getReasonByCode(($curfile['error'] != 0)?$curfile['error']:100500, "JPG"));
		} else {
			move_uploaded_file($curfile['tmp_name'], SB_MAP_LOCATION."/".$safe_name);
			$log = new CSystemLog("m", "Изображение карты загружено", "Новое изображение карты загружено: ".htmlspecialchars($safe_name));
			$msg_lines[] = sprintf("Файл %s загружен.", $safe_name);
		}
	}
	// Сообщение в карточке попапа (без window.alert); при успехе можно закрыть окно.
	$has_error = false;
	foreach ($msg_lines as $line) {
		if (stripos($line, 'Не удалось') !== false) {
			$has_error = true;
			break;
		}
	}
	$message = implode('<br>', array_map(function ($l) {
		return htmlspecialchars($l, ENT_QUOTES, 'UTF-8');
	}, $msg_lines));
	if (!$has_error && !empty($msg_lines)) {
		$message .= '<script>setTimeout(function(){ try{ if(window.opener&&!window.opener.closed) window.opener.focus(); }catch(e){} self.close(); }, 1200);</script>';
	}
}

$theme->assign("title", "Загрузить изображение карты");
$theme->assign("message", $message);
$theme->assign("input_name", "mapimg_file[]");
$theme->assign("form_name", "mapimgup");
$theme->assign("formats", "JPG");

$theme->display('page_uploadfile.tpl');
