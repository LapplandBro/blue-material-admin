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

include_once '../init.php';

if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
{
	echo "No Access";
	die();
}
require_once(INCLUDES_PATH . '/xajax.inc.php');
require_once(INCLUDES_PATH . '/system-functions.php');
$xajax = new xajax();
//$xajax->debugOn();
$xajax->setRequestURI("./admin.blockit.php");
$xajax->registerFunction("BlockPlayer");
$xajax->registerFunction("LoadServers2");
$xajax->processRequests();
$username = $userbank->GetProperty("user");

function LoadServers2($check, $type, $length) {
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался использовать блокировку, не имея на это прав.");
		return $objResponse;
	}
	// SECURITY FIX: $check уходит в RCON-команду и в JS-строку, поэтому пропускаем только
	// валидный SteamID2/SteamID3/Steam64 — без `;`, кавычек, пробелов и переводов строк.
	$check = sb_sanitize_steamid_for_rcon($check);
	if($check === false)
	{
		$objResponse->addAssign("srv_0", "innerHTML", "<span class='err'>некорректный SteamID</span>");
		$log = new CSystemLog("w", "Попытка взлома", $username . " передал некорректный идентификатор игрока в блокировку.");
		return $objResponse;
	}
	$type = (int)$type;
	$length = (int)$length;
	$id = 0;
	$servers = $GLOBALS['db']->Execute("SELECT sid, rcon FROM ".DB_PREFIX."_servers WHERE enabled = 1 ORDER BY modid, sid;");
	while(!$servers->EOF) {
		// SECURITY FIX: не отправляем RCON на серверы, к которым у админа нет доступа
		// (нумерация строк совпадает с фильтром в списке ниже).
		if(!sb_admin_has_server_access($servers->fields["sid"])) {
			$servers->MoveNext();
			continue;
		}
		//search for player
		if(!empty($servers->fields["rcon"])) {
			$text = '<span class="muted">поиск…</span>';
			$objResponse->addScript("xajax_BlockPlayer(".json_encode($check).", ".json_encode((string) $servers->fields["sid"]).", ".json_encode((string) $id).", ".json_encode((string) $type).", ".json_encode((string) $length).");");
		}
		else { //no rcon = servercount + 1 ;)
			$text = '<span class="muted">нет RCON</span>';
			$objResponse->addScript('set_counter(1);');
		}		
		$objResponse->addAssign("srv_".$id, "innerHTML", $text);
		$id++;
		$servers->MoveNext();
	}
	return $objResponse;
}

function BlockPlayer($check, $sid, $num, $type, $length) {
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$sid = (int)$sid;
	$length = (int)$length;

	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался обработать блокировку игрока, не имея на это прав.");
		return $objResponse;
	}
	// SECURITY FIX: доступ к конкретному серверу проверяем отдельно от права на бан.
	if(!sb_admin_has_server_access($sid))
	{
		$objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>нет доступа</span>");
		$objResponse->addScript('set_counter(1);');
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался выдать блокировку через RCON на sid=$sid без доступа к серверу.");
		return $objResponse;
	}
	// SECURITY FIX: идентификатор подставляется в RCON-команду — только валидный SteamID.
	$check = sb_sanitize_steamid_for_rcon($check);
	if($check === false)
	{
		$objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>некорректный SteamID</span>");
		$objResponse->addScript('set_counter(1);');
		$log = new CSystemLog("w", "Попытка взлома", $username . " передал некорректный идентификатор игрока в блокировку (sid=$sid).");
		return $objResponse;
	}
	
	//get the server data
	$sdata = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".$sid."';");
	
	//test if server is online
	if($test = @fsockopen($sdata['ip'], $sdata['port'], $errno, $errstr, 2)) {
		@fclose($test);
		require_once(INCLUDES_PATH . "/CServerControl.php");
		
		$r = new CServerControl();
		$r->Connect($sdata['ip'], $sdata['port']);

		if(!$r->AuthRcon($sdata['rcon'])) {
			$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".$sid."' LIMIT 1;");		
			$objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>неверный RCON</span>");
			$objResponse->addScript('set_counter(1);');
			return $objResponse;
		}
		$ret = $r->GetInfo();
		// БАГ-ФИКС: условие было инвертировано (`!$ret`), из-за чего этот блок реально
		// выполнялся только когда информация о сервере НЕ была получена, и тогда
		// $ret['HostName'] всегда обращался к несуществующему ключу false-значения.
		// SECURITY FIX: HostName приходит с игрового сервера (недоверенный источник) и
		// добавлен htmlspecialchars() перед вставкой в innerHTML во избежание XSS.
		if($ret)
			$objResponse->addAssign("srvip_$num", "innerHTML", "<span title='".htmlspecialchars($sdata['ip'].":".$sdata['port'], ENT_QUOTES)."'>".htmlspecialchars($ret['HostName'])."</span>");

		$response = null;
		$gothim = false;
		// БАГ-ФИКС: при типе 3 ("Чат & Микро") в БД всегда создаются ДВЕ отдельные записи
		// (type=1 - микрофон/mute, type=2 - чат/gag, см. AddBlock() в sb-callback.php), и снятие
		// блокировки (page.commslist.php) снимает по отдельности через ma_wb_unmute.
		// При type=3 в БД — две записи (mute+gag); на сервер шлём две команды.
		// Material Admin: ma_wb_mute <type> <time> <steam> <reason>  (не ma_wb_block!)
		$type = (int)$type;
		$blockTypes = ($type == 3) ? array(1, 2) : array($type);
		$wbReason = "Web";
		if ($GLOBALS['config']['feature.old_serverside'] == "1") {
			$ret = $r->SendCommand("status");
        	$search = preg_match_all(STATUS_PARSE, $ret, $matches, PREG_PATTERN_ORDER);
	        //search for the steamid on the server
    	    foreach ($matches[3] AS $match) {
    	        if (substr($match, 8) == substr($check, 8)) {
	                $gothim = true;
    	            foreach ($blockTypes as $blockType)
    	                $r->SendCommand("sc_fw_block " . $blockType . " " . $length . " " . $match);
    	        }
    	    }
		} else {
			$gothim = true;
			foreach ($blockTypes as $blockType)
				$gothim = $gothim && (strpos($r->SendCommand("ma_wb_mute ".$blockType." ".$length." ".$check." ".$wbReason), "ok") !== FALSE);
		}

		if ($gothim) {
            $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_comms` SET sid = ? WHERE authid = ? AND RemovedBy IS NULL;", array((int) $sid, $check));
			$requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], "pages/admin.blockit.php"));
			$objResponse->addAssign("srv_$num", "innerHTML", "<span class='ok'>найден, блокировка выдана</span>");
			$objResponse->addScript("set_counter('-1');");
			return $objResponse;
        }

		if(!$gothim) {
			$objResponse->addAssign("srv_$num", "innerHTML", "<span class='muted'>не в сети</span>");
			$objResponse->addScript('set_counter(1);');
			return $objResponse;
		}
	} else {
		$objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>нет связи</span>");
		$objResponse->addScript('set_counter(1);');
		return $objResponse;
	}
}
// SECURITY FIX: список должен совпадать с фильтром доступа в LoadServers2, иначе номера строк разъедутся.
$servers = $GLOBALS['db']->Execute("SELECT sid, ip, port, rcon FROM ".DB_PREFIX."_servers WHERE enabled = 1 ORDER BY modid, sid;");
$serverlinks = array();
$num = 0;
while(!$servers->EOF) {
	if(!sb_admin_has_server_access($servers->fields["sid"])) {
		$servers->MoveNext();
		continue;
	}
	$info = array();
	$info['num'] = $num;
	$info['ip'] = $servers->fields["ip"];
	$info['port'] = $servers->fields["port"];
	array_push($serverlinks, $info);
	$num++;
	$servers->MoveNext();
}
$theme->assign('total', $num);
$theme->assign('servers', $serverlinks);
$theme->assign('xajax_functions',  $xajax->printJavascript("../scripts", "xajax.js"));
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
// SECURITY FIX: в шаблон уходит только валидный SteamID (вставляется в JS-строку).
$checkParam = isset($_GET["check"]) ? sb_sanitize_steamid_for_rcon($_GET["check"]) : false;
$theme->assign('check', $checkParam === false ? '' : htmlspecialchars($checkParam));// steamid
$theme->assign('type', isset($_GET['type']) ? (int) $_GET['type'] : 0);
$theme->assign('length', isset($_GET['length']) ? (int) $_GET['length'] : 0);

$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_blockit.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
