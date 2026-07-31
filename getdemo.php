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

require_once("init.php");

/**
 * Content-Disposition filename (RFC 6266 / RFC 5987).
 * Без CR/LF/кавычек — иначе header injection через origname.
 */
function sb_content_disposition_attachment($origname)
{
	$name = (string)$origname;
	$name = str_replace(array("\0", "\r", "\n"), '', $name);
	$name = basename(str_replace('\\', '/', $name));
	if ($name === '' || $name === '.' || $name === '..')
		$name = 'demo.dem';
	// ASCII fallback для старых клиентов
	$ascii = preg_replace('/[^\x20-\x7E]/', '_', $name);
	$ascii = str_replace(array('"', '\\', ';', '='), '_', $ascii);
	if ($ascii === '' || $ascii === '.' || $ascii === '..')
		$ascii = 'demo.dem';
	return "attachment; filename=\"" . $ascii . "\"; filename*=UTF-8''" . rawurlencode($name);
}

if(!isset($_GET['id']) || !isset($_GET['type']))
  die('No id or type parameter.');

if(strcasecmp($_GET['type'], "U") != 0 && strcasecmp($_GET['type'], "B") != 0 && strcasecmp($_GET['type'], "S") != 0)
  die('Bad type');

$id = (int)$_GET['id'];
$type = strtoupper(substr((string)$_GET['type'], 0, 1));

$demo = $GLOBALS['db']->GetRow("SELECT filename, origname FROM `".DB_PREFIX."_demos` WHERE demtype=? AND demid=?;", array($type, $id));
//Official Fix: https://code.google.com/p/sourcebans/source/detail/r=165
if(!$demo)
{
  die('Demo not found.');
}

if((!in_array($demo['filename'], scandir(SB_DEMOS)) || !file_exists(SB_DEMOS . "/" . $demo['filename'])) && $type != "U")
{
  die('File not found.');
}

if($type != "U"){
$demo['filename'] = basename($demo['filename']);
header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: Binary');
header('Content-Disposition: ' . sb_content_disposition_attachment($demo['origname']));
header("Content-Length: " . filesize(SB_DEMOS . "/" . $demo['filename']));
readfile(SB_DEMOS . "/" . $demo['filename']);
}else{
  // SECURITY: раньше был open redirect на произвольный origname.
  // Внешнюю ссылку показываем только если URL безопасный; без Location-редиректа.
  $url = (string)$demo['origname'];
  if (!function_exists('sb_is_safe_external_url') || !sb_is_safe_external_url($url)) {
    die('External demo URL is not allowed.');
  }
  header('Content-Type: text/html; charset=utf-8');
  $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
  echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Демо</title></head><body>';
  echo '<p>Внешнее демо: <a href="'.$safe.'" rel="noopener noreferrer" referrerpolicy="no-referrer">'.$safe.'</a></p>';
  echo '</body></html>';
}
?>
