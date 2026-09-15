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

global $userbank;
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}

if (!isset($userbank) || !is_object($userbank)) {
	echo '<div id="admin-page-content"><div id="0" class="admin-pane is-on"><div class="form-page"><p class="form-flash form-flash--err">Нет сессии администратора.</p></div></div></div>';
	return;
}
if(isset($GLOBALS['IN_ADMIN']) && !defined('CUR_AID'))
	define('CUR_AID', $userbank->GetAid());


if(isset($_GET["rebanid"]))
{
	echo '<script type="text/javascript">xajax_PrepareReblock("'.(int)$_GET["rebanid"].'");</script>';
}elseif(isset($_GET["blockfromban"]))
{
	echo '<script type="text/javascript">xajax_PrepareBlockFromBan("'.(int)$_GET["blockfromban"].'");</script>';
}elseif((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && is_string($_GET['pName']) && isset($_GET['sid'])) {
	echo "<script type=\"text/javascript\">setTimeout(function(){ ShowBox('Загрузка..','Подождите!', 'blue', '', false, 5000); }, 800);xajax_PastePlayerData('".(int)$_GET['sid']."', '".htmlspecialchars(addslashes($_GET['pName']), ENT_QUOTES, 'UTF-8')."');</script>";
}

echo '<div id="admin-page-content">';
	echo '<div id="0" class="admin-pane is-on">';
		$canAddComms = $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN);
		$crRaw = isset($GLOBALS['config']['bans.customreasons']) ? $GLOBALS['config']['bans.customreasons'] : '';
		if (is_array($crRaw))
			$customreason = $crRaw;
		elseif (is_string($crRaw) && $crRaw !== '') {
			$crUn = sb_unserialize_array($crRaw);
			$customreason = is_array($crUn) ? $crUn : false;
		} else
			$customreason = false;
		sb_admin_echo_twig_fragment('admin_comms_add.twig', array(
			'permission_addban' => $canAddComms,
			'customreason' => $customreason,
		));
	echo '</div>';
?>

<script type="text/javascript">
function changeReason(szListValue)
{
	$('dreason').style.display = (szListValue == "other" ? "block" : "none");
	$('txtReason').focus();
}
function ProcessBan()
{
	var err = 0;
	var reason = $('listReason')[$('listReason').selectedIndex].value;

	if (reason == "other")
		reason = $('txtReason').value;

	if(!$('nickname').value)
	{
		$('nick.msg').setHTML('Введите ник игрока, которому хотите добавить блокировку');
		$('nick.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('nick.msg').setHTML('');
		$('nick.msg').setStyle('display', 'none');
	}

	if($('steam').value.length < 10)
	{
		$('steam.msg').setHTML('Введите реальный STEAM ID или Community ID');
		$('steam.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('steam.msg').setHTML('');
		$('steam.msg').setStyle('display', 'none');
	}

	if(!reason)
	{
		$('reason.msg').setHTML('Выберите причину блокировки.');
		$('reason.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('reason.msg').setHTML('');
		$('reason.msg').setStyle('display', 'none');
	}

	if(err) {
		if (typeof sbIdleLast === 'function') sbIdleLast();
		return 0;
	}

	xajax_AddBlock($('nickname').value,
				 $('type').value,
				 $('steam').value,
				 $('banlength').value,
				 reason);
}
</script>
</div>
