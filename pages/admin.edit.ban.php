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

// Нельзя вызывать PageDie() внутри admin include: буфер ещё не обёрнут в wrap.twig —
// получается «белая страница» без темы. Вместо die — flash + return.
$sbBanEditFail = function ($msg, $redir = 'index.php?p=admin&c=bans') {
	$msgSafe = htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8');
	$redirJs = json_encode((string)$redir, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	echo '<div class="form-page admin-form"><div class="form-flash form-flash--err" role="alert">'
		. '<div class="form-flash-title">Ошибка</div><div class="form-flash-body">' . $msgSafe . '</div></div>'
		. '<div class="form-actions"><a class="btn btn-outline-secondary" href="index.php?p=admin&amp;c=bans">К банам</a></div></div>';
	echo '<script>setTimeout(function(){ if (typeof ShowBox === "function") ShowBox("Ошибка", '
		. json_encode((string)$msg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
		. ', "red", ' . $redirJs . '); }, 200);</script>';
};

$modGroup = isset($GLOBALS['config']['config.modgroup']) ? (string)$GLOBALS['config']['config.modgroup'] : '0';
if ($modGroup !== '0' && $modGroup !== '') {
	$gid_groups = $GLOBALS['db']->GetOne("SELECT `gid` FROM `" . DB_PREFIX . "_admins` WHERE `aid` = ?", array((int)$userbank->GetAid()));
	if ((string)$gid_groups === $modGroup) {
		$editId = isset($_GET['id']) ? (int)preg_replace('/[^0-9]/', '', (string)$_GET['id']) : 0;
		$srv_ban = (int)$GLOBALS['db']->GetOne("SELECT `sid` FROM `" . DB_PREFIX . "_bans` WHERE `bid` = ?", array($editId));
		// Веб-бан / без сервера (sid 0 или NULL) — не ограничивать списком серверов модератора.
		if ($srv_ban > 0) {
			$amd_access = (int)$GLOBALS['db']->GetOne(
				"SELECT `server_id` FROM `" . DB_PREFIX . "_admins_servers_groups` WHERE `admin_id` = ? AND `server_id` = ?",
				array((int)$userbank->GetAid(), $srv_ban)
			);
			if ($amd_access !== $srv_ban) {
				$sbBanEditFail('Вы имеете доступ только к редактированию банов на тех серверах, где у вас есть права управляющего!');
				return;
			}
		}
	}
}

if (!isset($_GET['key'], $_SESSION['banlist_postkey']) || $_GET['key'] !== $_SESSION['banlist_postkey'])
{
	$sbBanEditFail('Возможная попытка взлома (Несоответствие URL-ключа)!');
	return;
}
if (!isset($_GET['id']) || !is_numeric($_GET['id']))
{
	$sbBanEditFail('Нет бана!');
	return;
}
$_GET['id'] = (int)$_GET['id'];

$res = $GLOBALS['db']->GetRow("
    				SELECT ba.bid, ba.ip, ba.type, ba.authid, ba.name, ba.created, ba.ends, ba.length, ba.reason, ba.aid, ba.sid,
    					ad.user, ad.gid, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS server_sid, mo.icon, dm.origname
    				FROM ".DB_PREFIX."_bans AS ba
    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
    				LEFT JOIN ".DB_PREFIX."_demos AS dm ON dm.demid = ?
    				LEFT JOIN ".DB_PREFIX."_mods AS mo ON mo.mid = se.modid
    				WHERE ba.bid = ?", array((int)$_GET['id'], (int)$_GET['id']));

if (empty($res) || !isset($res['bid']))
{
	$sbBanEditFail('Бан не найден!');
	return;
}

$canEditBan = $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS)
	|| ($userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && (int)$res['aid'] === (int)$userbank->GetAid())
	|| ($userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && (int)$res['gid'] === (int)$userbank->GetProperty('gid'));
if (!$canEditBan)
{
	$sbBanEditFail('Вы не имеете доступ к этому!');
	return;
}

isset($_GET["page"])?$pagelink = "&page=".$_GET["page"]:$pagelink = "";

$errorScript = "";

if(isset($_POST['name']))
{
	$_POST['steam'] = trim($_POST['steam']);
	$_POST['type'] = (int)$_POST['type'];
	$demo_linker = $_POST['demo_link'];
	if($demo_linker != "")
		preg_match("@^(?:http://)?([^/]+)@i", $_SERVER['HTTP_HOST'], $demo_linker_dns);


	// Form Validation
	$error = 0;
	$steamResolveErr = '';
	if (!empty($_POST['steam']) && function_exists('sb_steam_resolve_to_steamid2'))
		$_POST['steam'] = sb_steam_resolve_to_steamid2($_POST['steam'], $steamResolveErr);
	// If they didn't type a steamid
	if ($steamResolveErr !== '' && $_POST['type'] == 0)
	{
		$error++;
		$errorScript .= "$('steam.msg').innerHTML = " . json_encode($steamResolveErr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ";";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
	}
	else if(empty($_POST['steam']) && $_POST['type'] == 0)
	{
		$error++;
		$errorScript .= "$('steam.msg').innerHTML = 'Введите Steam ID, Community ID или ссылку на профиль';";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
	}
	else if(($_POST['type'] == 0 
	&& !is_numeric($_POST['steam']) 
	&& !validate_steam($_POST['steam']))
	|| (is_numeric($_POST['steam']) 
	&& (strlen($_POST['steam']) < 15
	|| !validate_steam($_POST['steam'] = FriendIDToSteamID($_POST['steam'])))))
	{
		$error++;
		$errorScript .= "$('steam.msg').innerHTML = 'Введите реальный Steam ID, Community ID или ссылку (profiles/… или /id/…)';";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
	}
	// Didn't type an IP
	else if (empty($_POST['ip']) && $_POST['type'] == 1)
	{
		$error++;
		$errorScript .= "$('ip.msg').innerHTML = 'Введите IP';";
		$errorScript .= "$('ip.msg').setStyle('display', 'block');";
	}
	else if ($_POST['type'] == 1 && !validate_ip($_POST['ip']))
	{
		$error++; 
		$errorScript .= "$('ip.msg').innerHTML = 'Введите реальный IP';";
		$errorScript .= "$('ip.msg').setStyle('display', 'block');";
	}

	if($demo_linker != ""){
		// SSRF: только публичные http(s) URL.
		if(function_exists('sb_is_safe_external_url') && sb_is_safe_external_url($demo_linker) && @get_headers($demo_linker)){
			echo "";
		}else{
			$error++;
			$errorScript .= "$('demo_link.msg').innerHTML = 'Недействительный или недоступный публичный URL демо.';";
			$errorScript .= "$('demo_link.msg').setStyle('display', 'block');";
		}
	}
	// Didn't type a custom reason
	if($_POST['listReason'] == "other" && empty($_POST['txtReason']))
	{
		$error++;
		$errorScript .= "$('reason.msg').innerHTML = 'Введите причину';";
		$errorScript .= "$('reason.msg').setStyle('display', 'block');";
	}
	
	// prune any old bans
	PruneBans();
	
	if($error == 0)
	{
		// Check if the new steamid is already banned
		if($_POST['type'] == 0)
		{
			$chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM ".DB_PREFIX."_bans WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0' AND bid != ?", array($_POST['steam'], (int)$_GET['id']));

			if((int)$chk[0] > 0)
			{
				$error++;
				$errorScript .= "$('steam.msg').innerHTML = 'Этот SteamID уже забанен';";
				$errorScript .= "$('steam.msg').setStyle('display', 'block');";
			}
			else
			{
				// Check if player is immune
				$admchk = $userbank->GetAllAdmins();
				foreach($admchk as $admin)
				{
					if($admin['authid'] == $_POST['steam'] && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity'])
					{
						$error++;
						$errorScript .= "$('steam.msg').innerHTML = 'У админа ".$admin['user']." иммунитет';";
						$errorScript .= "$('steam.msg').setStyle('display', 'block');";
						break;
					}
				}
			}
		}
		// Check if the ip is already banned
		else if($_POST['type'] == 1)
		{
			$chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM ".DB_PREFIX."_bans WHERE ip = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1' AND bid != ?", array($_POST['ip'], (int)$_GET['id']));

			if((int)$chk[0] > 0)
			{
				$error++;
				$errorScript .= "$('ip.msg').innerHTML = 'Этот IP уже забанен';";
				$errorScript .= "$('ip.msg').setStyle('display', 'block');";
			}
		}
	}
	
	$_POST['name'] = RemoveCode($_POST['name']);
	$_POST['ip'] = preg_replace('#[^\d\.]#', '', $_POST['ip']);//strip ip of all but numbers and dots
	$_POST['dname'] = RemoveCode($_POST['dname']);
	$reason = RemoveCode(trim($_POST['listReason'] == "other"?$_POST['txtReason']:$_POST['listReason']));
	
	if(!$_POST['banlength'])
		$_POST['banlength'] = 0;
	else
		$_POST['banlength'] = (int)$_POST['banlength']*60;
	
	// Show the new values in the form
	$res['name'] = $_POST['name'];
	$res['authid'] = $_POST['steam'];
	$res['ip'] = $_POST['ip'];
	$res['length'] = $_POST['banlength'];
	$res['type'] = $_POST['type'];
	$res['reason'] = $reason;
	
	// Only process if there are still no errors
	if($error == 0)
	{
		// БАГ-ФИКС: раньше здесь запрашивались только length/authid, и лог писался ТОЛЬКО если
		// менялся срок бана - любое другое изменение (ник, причина, IP, SteamID) проходило
		// полностью бесследно. Забираем все поля, которые реально можно поменять в этой форме,
		// чтобы можно было залогировать любое изменение, а не только смену срока.
		$lengthrev = $GLOBALS['db']->Execute("SELECT length, authid, name, reason, ip, type FROM ".DB_PREFIX."_bans WHERE bid = ?", array((int)$_GET['id']));
		
		
		$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_bans SET
										`name` = ?, `type` = ?, `reason` = ?, `authid` = ?,
										`length` = ?,
										`ip` = ?,
										`country` = '',
										`ends` 	 =  `created` + ?
										WHERE bid = ?", array($_POST['name'], $_POST['type'], $reason, $_POST['steam'], $_POST['banlength'], $_POST['ip'], $_POST['banlength'], (int)$_GET['id']));
		
		// Set all submissions to archived for that steamid
		$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_submissions` SET archiv = '3', archivedby = '".$userbank->GetAid()."' WHERE SteamId = ?;", array($_POST['steam']));
				
		if(!empty($_POST['dname']) and !$demo_linker)
		{
			$didSafe = sb_demo_filename_safe(isset($_POST['did']) ? $_POST['did'] : '');
			if ($didSafe === '') {
				$sbBanEditFail('Недопустимое имя файла демо.');
				return;
			}
			$demoid = $GLOBALS['db']->GetRow("SELECT filename FROM `" . DB_PREFIX . "_demos` WHERE demid = ?", array((int)$_GET['id']));
			if (!empty($demoid['filename']))
				sb_unlink_demo($demoid['filename']);
			$edit = $GLOBALS['db']->Execute("REPLACE INTO ".DB_PREFIX."_demos
											(`demid`, `demtype`, `filename`, `origname`)
											VALUES
											(?,
											'b',
											?,
											?)", array((int)$_GET['id'], $didSafe, $_POST['dname']));
			$res['dname'] = RemoveCode($_POST['dname']);
		}
		
		if($demo_linker != "" && empty($_POST['dname'])){
				
			$edit = $GLOBALS['db']->Execute("REPLACE INTO ".DB_PREFIX."_demos
												(`demid`, `demtype`, `filename`, `origname`)
												VALUES
												(?,
												'U',
												?,
												?)", array((int)$_GET['id'], '', $demo_linker));
		}else{
			if($res['origname'])
				$edit = $GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_demos` WHERE `demid` = ?", array((int)$_GET['id']));
		}
		
		if($edit)
		{
			// БАГ-ФИКС: раньше запись в лог создавалась ТОЛЬКО при изменении срока бана - любое
			// другое изменение (ник, причина, IP, SteamID/тип) вообще не попадало в лог.
			// Теперь фиксируем факт редактирования всегда и перечисляем, что именно изменилось.
			$changes = array();
			if((int)$_POST['banlength'] != (int)$lengthrev->fields['length'])
				$changes[] = "срок: " . $lengthrev->fields['length'] . " -> " . $_POST['banlength'];
			if($_POST['name'] != $lengthrev->fields['name'])
				$changes[] = "ник: '" . htmlspecialchars($lengthrev->fields['name']) . "' -> '" . htmlspecialchars($_POST['name']) . "'";
			if((int)$_POST['type'] != (int)$lengthrev->fields['type'] || $_POST['steam'] != $lengthrev->fields['authid'])
				$changes[] = "SteamID: '" . htmlspecialchars($lengthrev->fields['authid']) . "' -> '" . htmlspecialchars($_POST['steam']) . "'";
			if($_POST['ip'] != $lengthrev->fields['ip'])
				$changes[] = "IP: '" . htmlspecialchars($lengthrev->fields['ip']) . "' -> '" . htmlspecialchars($_POST['ip']) . "'";
			if($reason != $lengthrev->fields['reason'])
				$changes[] = "причина: '" . htmlspecialchars($lengthrev->fields['reason']) . "' -> '" . htmlspecialchars($reason) . "'";

			$log = new CSystemLog("m", "Бан отредактирован", $userbank->GetProperty("user") . " отредактировал бан №" . (int)$_GET['id'] . (!empty($changes) ? " (" . implode("; ", $changes) . ")" : " (поля не изменились, изменена только демо-запись/прочее)") . ".");

			echo "<script>setTimeout(\"ShowBox('Бан обновлен', 'Бан был успешно обновлен', 'green', 'index.php?p=banlist".$pagelink."', false, 5000)\", 1000);</script>";
		}
		else
		{
			$db_error = $GLOBALS['db']->ErrorMsg();
			echo "<script>setTimeout(\"ShowBox('Ошибка', 'Не удалось обновить бан!" . (!empty($db_error) ? " (" . addslashes(htmlspecialchars($db_error)) . ")" : "") . "', 'red', 'index.php?p=banlist".$pagelink."', false, 5000)\", 1000);</script>";
		}
	}
}

if(!$res)
{
	echo "<script>setTimeout(\"ShowBox('Ошибка', 'Произошла ошибка получения деталей. Возможно, этот бан был удален?', 'red', 'index.php?p=banlist".$pagelink."', false, 5000)\", 1000);</script>";
}

$customReasons = false;
if (!empty($GLOBALS['config']['bans.customreasons'])) {
	$rawReasons = $GLOBALS['config']['bans.customreasons'];
	if (is_array($rawReasons)) {
		$customReasons = $rawReasons;
	} else {
		$decoded = sb_unserialize_array((string)$rawReasons);
		$customReasons = is_array($decoded) ? $decoded : false;
	}
}
$demoName = isset($res['origname']) ? $res['origname'] : '';
$banDemoHtml = ($demoName !== '' && $demoName !== null) ? ('<b>'.htmlspecialchars((string)$demoName, ENT_QUOTES, 'UTF-8').'</b>') : '';

$theme->assign('demo_link_val', $demoName);
$theme->assign('ban_name', $res['name']);
$theme->assign('ban_reason', $res['reason']);
$theme->assign('ban_authid', trim($res['authid']));
$theme->assign('ban_ip', $res['ip']);
$theme->assign('ban_demo', $banDemoHtml);
$theme->assign('customreason', $customReasons);

if (function_exists('sb_ui_v2_fragment')) {
	echo sb_ui_v2_fragment('admin_bans_edit.twig', array(
		'demo_link_val' => $demoName,
		'ban_name' => $res['name'],
		'ban_reason' => $res['reason'],
		'ban_authid' => trim($res['authid']),
		'ban_ip' => $res['ip'],
		'ban_demo' => $banDemoHtml,
		'customreason' => $customReasons,
	));
}
?>
<script type="text/javascript">window.addEvent('domready', function(){
<?php echo $errorScript; ?>
});
function changeReason(szListValue)
{
	$('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
selectLengthTypeReason(<?php echo (int)$res['length']; ?>, <?php echo (int)$res['type']; ?>, <?php echo json_encode((string)$res['reason'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
</script>
