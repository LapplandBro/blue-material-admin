<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}

error_reporting(E_ALL & ~E_DEPRECATED);
global $userbank, $theme;
	
if($GLOBALS['config']['page.adminlist']!="1"){
    CreateRedBox("Ошибка", "Страница отключена.");
    PageDie();
}

function SteamIDToCommunityID($sid) {
    /**
     * Thanks Valve and AlliedModders!
     * https://developer.valvesoftware.com/wiki/SteamID#Steam_Community_ID_as_a_Steam_ID
     * https://forums.alliedmods.net/showthread.php?t=60899
     *
     * STEAM_X:Y:Z
     * V=76561197960265728
     * W=Z*2+V+Y
     */
    
    $result = array();
    $res = preg_match('/STEAM_[0-9]:(0|1):([0-9]{1,})/', $sid, $result);
    if ($res) return bcadd(bcadd(bcmul($result[2], '2'), '76561197960265728'), $result[1]);
    else return false;
}

function FindAdminById($adlist, $aid) {
    $countl = count($adlist);
    for ($i = 0; $i < $countl; $i++) {
        if ($adlist[$i]['aid'] == $aid) return $adlist[$i];
    }
    
    return false;
}

function FindModById($modlist, $mid) {
    $countl = count($modlist);
    for ($i = 0; $i < $countl; $i++) {
        if ($modlist[$i]['mid'] == $mid) return $modlist[$i];
    }
    
    return false;
}

function IsExpired($admin) {
    if ($admin) {
        $exp = $admin['expired'];
        if (($exp > 0 && $exp > time()) || $exp == '0' || $exp == '')
            return false;
    }
    
    return true;
}

$servers = array();
$admins  = array();
$mods    = array();

/* Request all data */
$servers = $GLOBALS['db']->GetAll(sprintf('SELECT sid,ip,port,modid FROM `%s_servers` WHERE enabled = 1', DB_PREFIX));
$mods = $GLOBALS['db']->GetAll(sprintf('SELECT mid,name,icon,modfolder FROM `%s_mods`', DB_PREFIX));
if (!is_array($servers))
	$servers = array();
if (!is_array($mods))
	$mods = array();
$admins = $GLOBALS['db']->GetAll(sprintf(
    "SELECT a.aid, a.user, a.authid, a.srv_group, a.expired, a.vk, a.discord, a.comment,
            CASE WHEN gr.server_id = -1 THEN sgrp.server_id ELSE gr.server_id END AS srv,
            a.immunity AS adm_immunity, sg.immunity AS sg_immunity
     FROM `%s_admins` a
     INNER JOIN `%s_admins_servers_groups` AS gr ON a.aid = gr.admin_id
     LEFT JOIN `%s_servers_groups` sgrp
       ON gr.server_id = -1 AND sgrp.group_id = gr.srv_group_id
     LEFT JOIN `%s_srvgroups` sg ON sg.name = a.srv_group",
    DB_PREFIX, DB_PREFIX, DB_PREFIX, DB_PREFIX
));
if (!is_array($admins))
	$admins = array();

foreach ($admins as &$admin) {
    $admin['aid'] = (int)$admin['aid'];
    $admImm = isset($admin['adm_immunity']) ? (int)$admin['adm_immunity'] : 0;
    $sgImm = isset($admin['sg_immunity']) ? (int)$admin['sg_immunity'] : 0;
    $admin['immunity'] = ($admImm > $sgImm) ? $admImm : $sgImm;
}
unset($admin);

/* Edit server data: add var 'adminlist' */
foreach ($servers as &$server)
    $server['adminlist'] = array();

/* Edit mod data: add var `servers` */
$iModCount = count($mods);
for ($i = 0; $i < $iModCount; $i++)
    $mods[$i]['servers'] = 0;
unset($iModCount);

$iServerCount = count($servers);
$iAdminCount  = count($admins);
for ($iServer = 0; $iServer < $iServerCount; $iServer++) {
    /* Admins */
    for ($iAdmin = 0; $iAdmin < $iAdminCount; $iAdmin++) {
        $administrator = $admins[$iAdmin];
        if ($administrator['srv'] == $servers[$iServer]['sid'] && !IsExpired($administrator)) {
            $administrator['avatar'] = GetUserAvatar($administrator['authid']);
            $administrator['authid'] = SteamIDToCommunityID($administrator['authid']);
            // Don't dump a wall of filler text into the UI
            $administrator['comment'] = trim((string)$administrator['comment']);
            $administrator['discord'] = trim((string)$administrator['discord']);
            $administrator['vk'] = trim((string)$administrator['vk']);
                
            $servers[$iServer]['adminlist'][$administrator['aid']] = $administrator;
        }
    }
    
    /* Other infornation */
    $servers[$iServer]['admincount'] = count($servers[$iServer]['adminlist']);
    uasort($servers[$iServer]['adminlist'], function ($a, $b) {
        $ia = isset($a['immunity']) ? (int)$a['immunity'] : 0;
        $ib = isset($b['immunity']) ? (int)$b['immunity'] : 0;
        if ($ia !== $ib)
            return ($ib - $ia);
        return strcasecmp((string)$a['user'], (string)$b['user']);
    });
    
    /* Games var */
    if ($servers[$iServer]['admincount']) {
        $countl = count($mods);
        for ($i = 0; $i < $countl; $i++) {
            if ($mods[$i]['mid'] == $servers[$iServer]['modid']) {
                $mods[$i]['servers']++;
                break;
            }
        }
    }
}

/* Optimization */
$countl = count($mods);
for ($i = 0; $i < $countl; $i++) {
    if ($mods[$i]['servers'] == 0) unset($mods[$i]);
}

/* App ID */
foreach ($mods as &$mod) {
	if ($mod['modfolder'] == 'tf') $mod['appid'] = 440;
	else $mod['appid'] = 0;
	$mod['icon_html'] = function_exists('sb_game_icon_html')
		? sb_game_icon_html(isset($mod['icon']) ? $mod['icon'] : '', isset($mod['name']) ? $mod['name'] : 'Игра', 22)
		: '';
}
unset($mod);

if (function_exists('sb_ui_v2_enabled') && sb_ui_v2_enabled()) {
	$qry = '';
	foreach ($servers as $server) {
		if (empty($server['admincount']))
			continue;
		$sid = (int)$server['sid'];
		$qry .= "xajax_ServerHostPlayers(" . $sid . ", 'servers', '', '0', '-1', '', 70);";
	}
	$extra_js = "<script>\n"
		. "window.addEvent('domready', function(){ " . $qry . " });\n"
		. "InitAccordion('div.adminlist-toggle', 'div.adminlist-body', 'content');\n"
		. "</script>\n";
	sb_ui_v2_render('adminlist.twig', array(
		'title' => 'Админы — Blue Admin',
		'games' => $mods,
		'server_list' => $servers,
		'extra_js' => $extra_js
	));
	return;
}
