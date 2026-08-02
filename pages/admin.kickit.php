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
    echo "Нет доступа";
    die();
}
require_once(INCLUDES_PATH . '/xajax.inc.php');
require_once(INCLUDES_PATH . '/system-functions.php');
$xajax = new xajax();
//$xajax->debugOn();
$xajax->setRequestURI("./admin.kickit.php");
$xajax->registerFunction("KickPlayer");
$xajax->registerFunction("LoadServers");
$xajax->processRequests();
$username = $userbank->GetProperty("user");

// SECURITY FIX: kickClient() ищет игрока по SteamID либо по IPv4 — всё остальное отклоняем,
// чтобы в RCON-команду и в JS-строку не попали `;`, кавычки, пробелы и переводы строк.
function kickit_validate_check($check) {
    $check = trim((string) $check);
    if (filter_var($check, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        return $check;
    return sb_sanitize_steamid_for_rcon($check);
}

function LoadServers($check) {
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
    {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $log = new CSystemLog("w", "Попытка взлома", $username . " пытался использовать кик, не имея на это прав.");
        return $objResponse;
    }
    $check = kickit_validate_check($check);
    if($check === false)
    {
        $objResponse->addAssign("srv_0", "innerHTML", "<span class='err'>некорректный SteamID</span>");
        $log = new CSystemLog("w", "Попытка взлома", $username . " передал некорректный идентификатор игрока в кик.");
        return $objResponse;
    }
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
            $objResponse->addScript("xajax_KickPlayer(".json_encode($check).", ".json_encode((string) $servers->fields["sid"]).", ".json_encode((string) $id).");");
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

function KickPlayer($check, $sid, $num) {
    $objResponse = new xajaxResponse();
    global $userbank, $username;
    $sid = (int)$sid;

    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
    {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $log = new CSystemLog("w", "Попытка взлома", $username . " пытался обработать кик игрока, не имея на это прав.");
        return $objResponse;
    }
    // SECURITY FIX: доступ к конкретному серверу проверяем отдельно от права на бан.
    if(!sb_admin_has_server_access($sid))
    {
        $objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>нет доступа</span>");
        $objResponse->addScript('set_counter(1);');
        $log = new CSystemLog("w", "Попытка взлома", $username . " пытался кикнуть игрока через RCON на sid=$sid без доступа к серверу.");
        return $objResponse;
    }
    // SECURITY FIX: идентификатор используется в RCON-поиске игрока — только SteamID или IPv4.
    $check = kickit_validate_check($check);
    if($check === false)
    {
        $objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>некорректный SteamID</span>");
        $objResponse->addScript('set_counter(1);');
        $log = new CSystemLog("w", "Попытка взлома", $username . " передал некорректный идентификатор игрока в кик (sid=$sid).");
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
        
        require_once(INCLUDES_PATH . '/system-functions.php');
        if (kickClient($r, $check)) {
            $objResponse->addAssign("srv_$num", "innerHTML", "<span class='ok'>найден и кикнут</span>");
            $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_bans` SET sid = ? WHERE authid = ? AND RemovedBy IS NULL;", array((int) $sid, $check));
            $objResponse->addScript("set_counter('-1');");
        } else
            $objResponse->addAssign("srv_$num", "innerHTML", "<span class='muted'>не в сети</span>");
    } else
        $objResponse->addAssign("srv_$num", "innerHTML", "<span class='err'>нет связи</span>");
    
    $objResponse->addScript('set_counter(1);');
    return $objResponse;
}
// SECURITY FIX: список должен совпадать с фильтром доступа в LoadServers, иначе номера строк разъедутся.
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
// SECURITY FIX: в шаблон уходит только валидный SteamID/IPv4 (вставляется в JS-строку).
$checkParam = isset($_GET["check"]) ? kickit_validate_check($_GET["check"]) : false;
$theme->assign('check', $checkParam === false ? '' : htmlspecialchars($checkParam));// steamid or ip address

$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_kickit.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
