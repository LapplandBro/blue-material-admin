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

$allowed_icon_types = array(
	IMAGETYPE_GIF => 'gif',
	IMAGETYPE_JPEG => 'jpg',
	IMAGETYPE_PNG => 'png',
);

$message = "";
if (isset($_POST['upload']))
{
	sb_upload_require_csrf();

	if (empty($_FILES['icon_file']) || !isset($_FILES['icon_file']['tmp_name'])) {
		$message = "<b>Файл не получен.</b><br><br>";
	} elseif ((int)$_FILES['icon_file']['error'] !== 0) {
		$message = "<b>Ошибка загрузки: " . htmlspecialchars(getReasonByCode((int)$_FILES['icon_file']['error'], "GIF, JPG, PNG"), ENT_QUOTES, 'UTF-8') . "</b><br><br>";
	} elseif ((int)$_FILES['icon_file']['size'] <= 0 || (int)$_FILES['icon_file']['size'] > MAX_GAMEICON_SIZE_BYTES) {
		$message = "<b>Файл слишком большой или пуст (макс. " . (int)(MAX_GAMEICON_SIZE_BYTES / 1024 / 1024) . " MB).</b><br><br>";
	} else {
		$tmp = (string)$_FILES['icon_file']['tmp_name'];
		$original = (string)$_FILES['icon_file']['name'];
		$check = @getimagesize($tmp);

		if ($check === false || empty($check[2]) || !isset($allowed_icon_types[(int)$check[2]])) {
			$message = "<b>Файл должен быть настоящим изображением формата gif, jpg или png.</b><br><br>";
			$log = new CSystemLog("w", "Подозрительная загрузка", "Иконка МОДа отклонена: " . $original);
		} elseif (!is_uploaded_file($tmp)) {
			$message = "<b>Некорректная временная загрузка.</b><br><br>";
		} else {
			$ext = $allowed_icon_types[(int)$check[2]];
			$original_basename = basename($original);
			$clean_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $original_basename);
			$stem = ($clean_name !== '' && $clean_name === $original_basename)
				? pathinfo($clean_name, PATHINFO_FILENAME)
				: '';

			if ($stem === '' || strpos($stem, '.') !== false) {
				$message = "<b>Недопустимое имя файла.</b><br><br>";
			} else {
				$icon_filename = $stem . '.' . $ext;
				$destination = rtrim(SB_ICONS, '/\\') . DIRECTORY_SEPARATOR . $icon_filename;

				if (file_exists($destination)) {
					$message = "<b>Файл с таким именем уже существует.</b><br><br>";
				} elseif (!@move_uploaded_file($tmp, $destination)) {
					$message = "<b>Не удалось сохранить файл.</b><br><br>";
				} else {
					@chmod($destination, 0644);
					$ok = true;
					// GIF не перекодируем через GD (ломает анимацию); JPEG/PNG — да.
					if (extension_loaded('gd') && (int)$check[2] !== IMAGETYPE_GIF) {
						if (!reencodeImage($destination, (int)$check[2])) {
							@unlink($destination);
							$message = "<b>Файл повреждён или содержит некорректные данные.</b><br><br>";
							$ok = false;
						}
					}
					if ($ok) {
						$message = "<script>window.opener.icon(" . json_encode($icon_filename) . ");self.close()</script>";
						$log = new CSystemLog("m", "Иконка МОДа загружена", "Новая иконка МОДа загружена: " . htmlspecialchars($icon_filename, ENT_QUOTES, 'UTF-8'));
					}
				}
			}
		}
	}
}

$theme->assign("title", "Загрузить иконку");
$theme->assign("message", $message);
$theme->assign("input_name", "icon_file");
$theme->assign("form_name", "iconup");
$theme->assign("formats", "GIF, PNG или JPG");
$theme->assign("sb_csrf", function_exists('sb_csrf_token') ? sb_csrf_token() : '');

$theme->display('page_uploadfile.tpl');
