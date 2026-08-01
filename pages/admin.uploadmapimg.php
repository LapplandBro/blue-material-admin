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

$extensions = array_map('strtoupper', ALLOW_GAMEMAPS_EXT);
$extText = implode(', ', $extensions);
$message = sprintf(
	"<br /><strong>Обратите внимание!</strong><br />Форматы: %s<br />Максимальный размер файла: %s<br />Макс. разрешение: %dx%d<br /><br />",
	htmlspecialchars($extText, ENT_QUOTES, 'UTF-8'),
	ini_get('upload_max_filesize'),
	MAX_GAMEMAPS_WIDTH,
	MAX_GAMEMAPS_HEIGHT
);

if (isset($_POST['upload']))
{
	sb_upload_require_csrf();

	$fls = normalize_files_array($_FILES);
	$msg_lines = array();
	$allowed = ALLOWED_GAMEMAPS_TYPES;

	if (empty($fls['mapimg_file']) || !is_array($fls['mapimg_file'])) {
		$msg_lines[] = 'Файлы не получены.';
	} else {
		foreach ($fls['mapimg_file'] as $curfile) {
			$original = isset($curfile['name']) ? (string)$curfile['name'] : '';
			$safe_label = htmlspecialchars(basename($original), ENT_QUOTES, 'UTF-8');

			if (!isset($curfile['error']) || (int)$curfile['error'] !== 0) {
				$code = isset($curfile['error']) ? (int)$curfile['error'] : 4;
				$msg_lines[] = sprintf('Не удалось загрузить файл %s. Причина: %s.', $safe_label, getReasonByCode($code, $extText));
				continue;
			}

			if (!isset($curfile['size']) || (int)$curfile['size'] <= 0 || (int)$curfile['size'] > MAX_GAMEMAPS_SIZE_BYTES) {
				$msg_lines[] = sprintf('Файл %s превышает максимальный размер (%d MB) или пуст.', $safe_label, (int)(MAX_GAMEMAPS_SIZE_BYTES / 1024 / 1024));
				$log = new CSystemLog("w", "Слишком большой файл", "Попытка загрузить файл большого размера: " . $original);
				continue;
			}

			$tmp = isset($curfile['tmp_name']) ? (string)$curfile['tmp_name'] : '';
			if ($tmp === '' || !is_uploaded_file($tmp)) {
				$msg_lines[] = sprintf('Файл %s отклонён (некорректная временная загрузка).', $safe_label);
				continue;
			}

			// Тип по содержимому файла — не по клиентскому Content-Type (CVE-2026-30761).
			$check = @getimagesize($tmp);
			if ($check === false || empty($check[2])) {
				$msg_lines[] = sprintf('Файл %s не является допустимым изображением.', $safe_label);
				$log = new CSystemLog("w", "Подозрительная загрузка", "Не удалось определить тип файла: " . $original);
				continue;
			}

			$width = (int)$check[0];
			$height = (int)$check[1];
			$type = (int)$check[2];

			if ($width < 1 || $height < 1 || $width > MAX_GAMEMAPS_WIDTH || $height > MAX_GAMEMAPS_HEIGHT) {
				$msg_lines[] = sprintf(
					'Изображение %s слишком большое или некорректное (%dx%d). Максимум: %dx%d.',
					$safe_label,
					$width,
					$height,
					MAX_GAMEMAPS_WIDTH,
					MAX_GAMEMAPS_HEIGHT
				);
				continue;
			}

			if (!isset($allowed[$type])) {
				$msg_lines[] = sprintf('Файл %s не является изображением в формате %s.', $safe_label, htmlspecialchars($extText, ENT_QUOTES, 'UTF-8'));
				$log = new CSystemLog("w", "Подозрительная загрузка", "Попытка загрузить неподдерживаемый тип: " . $original);
				continue;
			}

			$ext = $allowed[$type];
			$original_basename = basename($original);
			$clean_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $original_basename);
			if ($clean_name === '' || $clean_name !== $original_basename) {
				$msg_lines[] = sprintf('Имя файла %s содержит недопустимые символы. Загрузка отклонена.', $safe_label);
				$log = new CSystemLog("w", "Подозрительное имя файла", "Попытка загрузить файл с недопустимыми символами: " . $original);
				continue;
			}

			$stem = pathinfo($clean_name, PATHINFO_FILENAME);
			if ($stem === '' || strpos($stem, '.') !== false) {
				$msg_lines[] = sprintf('Имя файла %s недопустимо (двойное расширение или пустое имя).', $safe_label);
				continue;
			}

			$filename = $stem . '.' . $ext;
			$destination = rtrim(SB_MAP_LOCATION, '/\\') . DIRECTORY_SEPARATOR . $filename;

			if (file_exists($destination)) {
				$msg_lines[] = sprintf('Файл с именем %s уже существует. Загрузка отклонена.', htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'));
				continue;
			}

			if (!@move_uploaded_file($tmp, $destination)) {
				$msg_lines[] = sprintf('Не удалось сохранить файл %s.', $safe_label);
				continue;
			}

			@chmod($destination, 0644);

			// Перекодировка срезает полиглоты; без GD принимаем только после getimagesize.
			if (extension_loaded('gd')) {
				if (!reencodeImage($destination, $type)) {
					@unlink($destination);
					$msg_lines[] = sprintf('Файл %s повреждён или содержит некорректные данные.', $safe_label);
					continue;
				}
			}

			$log = new CSystemLog("m", "Изображение карты загружено", "Новое изображение карты загружено: " . $filename);
			$msg_lines[] = sprintf('Файл %s загружен как %s.', $safe_label, htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'));
		}
	}

	$has_error = false;
	foreach ($msg_lines as $line) {
		if (stripos($line, 'Не удалось') !== false || stripos($line, 'отклон') !== false
			|| stripos($line, 'превышает') !== false || stripos($line, 'не является') !== false
			|| stripos($line, 'поврежд') !== false || stripos($line, 'недоступ') !== false
			|| stripos($line, 'пуст') !== false || stripos($line, 'существует') !== false
			|| stripos($line, 'некоррект') !== false || stripos($line, 'не получ') !== false) {
			$has_error = true;
			break;
		}
	}
	$message = implode('<br>', $msg_lines);
	if (!$has_error && !empty($msg_lines)) {
		$message .= '<script>setTimeout(function(){ try{ if(window.opener&&!window.opener.closed) window.opener.focus(); }catch(e){} self.close(); }, 1200);</script>';
	}
}

$theme->assign("title", "Загрузить изображение карты");
$theme->assign("message", $message);
$theme->assign("input_name", "mapimg_file[]");
$theme->assign("form_name", "mapimgup");
$theme->assign("formats", $extText);
$theme->assign("sb_csrf", function_exists('sb_csrf_token') ? sb_csrf_token() : '');

$theme->display('page_uploadfile.tpl');
