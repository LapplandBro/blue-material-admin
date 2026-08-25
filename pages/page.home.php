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

global $theme;
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
define('IN_HOME', true);

//$GLOBALS['TitleRewrite'] = "HOME";

$res = $GLOBALS['db']->Execute("SELECT count(name) FROM ".DB_PREFIX."_banlog");
$totalstopped = (int)$res->fields[0];

$res = $GLOBALS['db']->Execute("SELECT bl.name, time, bl.sid, bl.bid, b.type, b.authid, b.ip
								FROM ".DB_PREFIX."_banlog AS bl
								LEFT JOIN ".DB_PREFIX."_bans AS b ON b.bid = bl.bid
								ORDER BY time DESC LIMIT 10");

$GLOBALS['server_qry'] = "";
$stopped = array();
$blcount = 0;
while (!$res->EOF)
{
	$info = array();
	//$info['date'] = SBDate($dateformat,$res->fields[1]);
	$info['date'] = SBDate($GLOBALS['config']['config.dateformat_ver2'],$res->fields[1]);
	$info['name'] = stripslashes($res->fields[0]);
	$info['short_name'] = trunc($info['name'], 40, false);
	$info['auth'] = $res->fields['authid'];
	$info['ip'] = $res->fields['ip'];
	$info['server'] = "block_".$res->fields['sid']."_$blcount";
	if($res->fields['type'] == 1)
	{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['ip'] . "&advType=ip&Submit";
	}else{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['auth'] . "&advType=steamid&Submit";
	}
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['name'] = htmlspecialchars(addslashes($info['name']), ENT_QUOTES, 'UTF-8');
	$info['popup'] = "ShowBox('Заблокированный игрок: " . $info['name'] . "', '" . $info['name'] . " пытался зайти<br />' + (document.getElementById('".$info['server']."') ? document.getElementById('".$info['server']."').title : '') + '<br />" . $info['date'] . "<br /><div align=\"middle\"><a href=\"" . $info['search_link'] . "\">Открыть бан в списке</a></div>', 'red', '', true);";
		
    $GLOBALS['server_qry'] .= "xajax_ServerHostProperty(".$res->fields['sid'].", 'block_".$res->fields['sid']."_$blcount', 'title', 100);";
        
    array_push($stopped,$info);
	$res->MoveNext();
    ++$blcount;
}

$res = $GLOBALS['db']->Execute("SELECT count(bid) FROM ".DB_PREFIX."_bans");
$BanCount = (int)$res->fields[0];

$res = $GLOBALS['db']->Execute("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, CONCAT(se.ip,':',se.port), se.sid, mo.icon, ba.RemoveType, ba.type, ba.country
			    				FROM ".DB_PREFIX."_bans AS ba 
			    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
			    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
			    				LEFT JOIN ".DB_PREFIX."_mods AS mo ON mo.mid = se.modid
			    				ORDER BY created DESC LIMIT 10");
$bans = array();
while (!$res->EOF)
{
        $info = array();
	$blen = isset($res->fields['length']) ? (int)$res->fields['length'] : (int)$res->fields[6];
	$bends = isset($res->fields['ends']) ? (int)$res->fields['ends'] : (int)$res->fields[5];
	$bcreated = isset($res->fields['created']) ? (int)$res->fields['created'] : (int)$res->fields[4];
	$bremove = isset($res->fields['RemoveType']) ? (string)$res->fields['RemoveType'] : (string)$res->fields[14];
	$inactive = function_exists('sb_punish_is_inactive')
		? sb_punish_is_inactive($blen, $bends, $bremove, $bcreated)
		: ($bremove === 'D' || $bremove === 'U' || $bremove === 'E' || ($blen && $bends < time()));
	$info['unbanned'] = $inactive;
	$info['perm'] = ($blen === 0 && !$inactive);
	$info['temp'] = ($blen !== 0 && !$inactive);
	$info['name'] = stripslashes($res->fields[3]);
	//$info['created'] = SBDate($dateformat,$res->fields['created']);
	$info['created'] = SBDate($GLOBALS['config']['config.dateformat_ver2'],$res->fields['created']);
	$info['created_info'] = SBDate("Выдано ".$GLOBALS['config']['config.dateformat'],$res->fields['created']);
	$info['length'] = function_exists('sb_punish_length_label')
		? sb_punish_length_label($blen, $inactive, $bremove)
		: ($blen == 0 ? 'Навсегда' : SecondsToString($blen));
	if (!function_exists('sb_punish_length_label') && strpos($info['length'], ',') !== false) {
		$ltemp = explode(',', $info['length']);
		$info['length'] = $ltemp[0];
	}
	$info['icon'] = empty($res->fields[13]) ? 'web.png' : $res->fields[13];
	$info['icon_html'] = sb_game_icon_html($info['icon'], 'Игра', 20);
	$info['authid'] = $res->fields[2];
	$info['ip'] = $res->fields[1];

	// Значок страны (как на странице списка банов) - чтобы виджет "Последние баны" на
	// главной странице тоже показывал флаги, а не только голый ник/IP.
	if (!empty($info['ip']))
	{
		if (!empty($res->fields['country']) && $res->fields['country'] != ' ')
		{
			$ccLabel = htmlspecialchars((string)$res->fields['country'], ENT_QUOTES, 'UTF-8');
			$info['country_icon'] = '<img src="images/country/' . strtolower($res->fields['country']) . '.gif" alt="' . $ccLabel . '" title="' . $ccLabel . '" class="flag-icon" width="16" height="11" loading="lazy">';
		}
		elseif (isset($GLOBALS['config']['banlist.nocountryfetch']) && $GLOBALS['config']['banlist.nocountryfetch'] == "0")
		{
			$home_ban_country = FetchIp($info['ip']);
			$GLOBALS['db']->Execute("UPDATE " . DB_PREFIX . "_bans SET country = ? WHERE bid = ?", array($home_ban_country, $res->fields['bid']));
			$ccLabel = htmlspecialchars((string)$home_ban_country, ENT_QUOTES, 'UTF-8');
			$info['country_icon'] = '<img src="images/country/' . strtolower($home_ban_country) . '.gif" alt="' . $ccLabel . '" title="' . $ccLabel . '" class="flag-icon" width="16" height="11" loading="lazy">';
		}
		else
		{
			$info['country_icon'] = '<img src="images/country/zz.gif" alt="Страна неизвестна" title="Страна неизвестна" class="flag-icon" width="16" height="11" loading="lazy">';
		}
	}
	else
	{
		$info['country_icon'] = '<img src="images/country/zz.gif" alt="Страна неизвестна" title="Страна неизвестна" class="flag-icon" width="16" height="11" loading="lazy">';
	}

	if($res->fields[15] == 1)
	{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['ip'] . "&advType=ip&Submit";
	}else{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
	}
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['short_name'] = trunc($info['name'], 25, false);

	if ($inactive) {
		if ($bremove === 'D')
			$info['ub_reason'] = 'D';
		elseif ($bremove === 'U')
			$info['ub_reason'] = 'U';
		else
			$info['ub_reason'] = 'E';
	} else {
		$info['ub_reason'] = '';
	}
	
	array_push($bans,$info);
	$res->MoveNext();
}

$res = $GLOBALS['db']->Execute("SELECT count(bid) FROM ".DB_PREFIX."_comms");
$CommCount = (int)$res->fields[0];
	
$res = $GLOBALS['db']->Execute("SELECT bid, ba.authid, ba.type, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, CONCAT(se.ip,':',se.port), se.sid, mo.icon, ba.RemoveType, ba.type
				    				FROM ".DB_PREFIX."_comms AS ba 
				    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
				    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
				    				LEFT JOIN ".DB_PREFIX."_mods AS mo ON mo.mid = se.modid
				    				ORDER BY created DESC LIMIT 10");
$comms = array();
while (!$res->EOF)
{
        $info = array();
	$clen = isset($res->fields['length']) ? (int)$res->fields['length'] : (int)$res->fields[6];
	$cends = isset($res->fields['ends']) ? (int)$res->fields['ends'] : (int)$res->fields[5];
	$ccreated = isset($res->fields['created']) ? (int)$res->fields['created'] : (int)$res->fields[4];
	$cremove = isset($res->fields['RemoveType']) ? (string)$res->fields['RemoveType'] : (string)$res->fields[14];
	$inactive = function_exists('sb_punish_is_inactive')
		? sb_punish_is_inactive($clen, $cends, $cremove, $ccreated)
		: ($cremove === 'D' || $cremove === 'U' || $cremove === 'E' || ($clen && $cends < time()));
	$info['unbanned'] = $inactive;
	$info['perm'] = ($clen === 0 && !$inactive);
	$info['temp'] = ($clen !== 0 && !$inactive);
	$info['name'] = stripslashes($res->fields[3]);
	//$info['created'] = SBDate($dateformat,$res->fields['created']);
	$info['created'] = SBDate($GLOBALS['config']['config.dateformat_ver2'],$res->fields['created']);
	$info['created_info'] = SBDate("Выдано ".$GLOBALS['config']['config.dateformat'],$res->fields['created']);
	$info['length'] = function_exists('sb_punish_length_label')
		? sb_punish_length_label($clen, $inactive, $cremove)
		: ($clen == 0 ? 'Навсегда' : ($clen < 0 ? 'Сессия' : SecondsToString($clen)));
	if (!function_exists('sb_punish_length_label') && strpos($info['length'], ',') !== false) {
		$ltemp = explode(',', $info['length']);
		$info['length'] = $ltemp[0];
	}
	$info['icon'] = empty($res->fields[13]) ? 'web.png' : $res->fields[13];
	$info['authid'] = $res->fields['authid'];
	$info['search_link'] = "index.php?p=commslist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['short_name'] = trunc($info['name'], 25, false);
	$info['type'] = (int)$res->fields['type'];
	$info['type_html'] = sb_comms_type_icon_html($info['type'], 20);

	if ($inactive) {
		if ($cremove === 'D')
			$info['ub_reason'] = 'D';
		elseif ($cremove === 'U')
			$info['ub_reason'] = 'U';
		else
			$info['ub_reason'] = 'E';
	} else {
		$info['ub_reason'] = '';
	}

	array_push($comms,$info);
	$res->MoveNext();
}

$counts = $GLOBALS['db']->GetRow("SELECT 
         (SELECT COUNT(aid) FROM `" . DB_PREFIX . "_admins` WHERE aid > 0) AS admins,
         (SELECT COUNT(sid) FROM `" . DB_PREFIX . "_servers`) AS servers"); // +
if (!is_array($counts))
	$counts = array('admins' => 0, 'servers' => 0);
if (!isset($counts['admins']))
	$counts['admins'] = 0;
if (!isset($counts['servers']))
	$counts['servers'] = 0;

		 
$theme->assign('total_admins', $counts['admins']); // +
$theme->assign('total_servers', $counts['servers']); // +
$theme->assign('nocountryshow', (isset($GLOBALS['config']['banlist.nocountryfetch']) && $GLOBALS['config']['banlist.nocountryfetch'] == "1" && !$GLOBALS['userbank']->is_logged_in()));
$theme->assign('listing_block',  isset($GLOBALS['config']['config.home.comms']) ? $GLOBALS['config']['config.home.comms'] : '');

require(TEMPLATES_PATH . "/page.servers.php"); //Set theme vars from servers page

$dashIntroTitle = isset($GLOBALS['config']['dash.intro.title']) ? $GLOBALS['config']['dash.intro.title'] : '';
$theme->assign('dashboard_title',  stripslashes($dashIntroTitle));

$dashboard_text = stripslashes(isset($GLOBALS['config']['dash.intro.text']) ? $GLOBALS['config']['dash.intro.text'] : '');
if (function_exists('sb_sanitize_admin_html'))
	$dashboard_text = sb_sanitize_admin_html($dashboard_text);
// SEO: убрать вложенные теги/<br> из заголовков; сдвинуть иерархию (на странице уже будет H1 «Главная»).
$dashboard_text = preg_replace_callback(
	'/<h([1-6])(\s[^>]*)?>(.*?)<\/h\1>/is',
	function ($m) {
		$inner = preg_replace('/<br\s*\/?>/i', ' ', $m[3]);
		$inner = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));
		$level = intval($m[1]) + 1;
		if ($level > 6) {
			$level = 6;
		}
		return '<h'.$level.$m[2].'>'.$inner.'</h'.$level.'>';
	},
	$dashboard_text
);
$theme->assign('dashboard_text', $dashboard_text);
$theme->assign('dashboard_info_block',  isset($GLOBALS['config']['dash.info_block']) ? $GLOBALS['config']['dash.info_block'] : '');
$info_block_text = isset($GLOBALS['config']['dash.info_block_text']) ? stripslashes($GLOBALS['config']['dash.info_block_text']) : '';
$info_block_text_p = isset($GLOBALS['config']['dash.info_block_text_t']) ? stripslashes($GLOBALS['config']['dash.info_block_text_t']) : '';
if (function_exists('sb_sanitize_admin_html')) {
	$info_block_text = sb_sanitize_admin_html($info_block_text);
	$info_block_text_p = sb_sanitize_admin_html($info_block_text_p);
}
$theme->assign('dashboard_info_block_text',  $info_block_text);
$theme->assign('dashboard_info_block_text_p',  $info_block_text_p);
$dash_vk = isset($GLOBALS['config']['dash.info_vk']) ? $GLOBALS['config']['dash.info_vk'] : '';
$dash_steam = isset($GLOBALS['config']['dash.info_steam']) ? $GLOBALS['config']['dash.info_steam'] : '';
$dash_yout = isset($GLOBALS['config']['dash.info_yout']) ? $GLOBALS['config']['dash.info_yout'] : '';
$dash_face = isset($GLOBALS['config']['dash.info_face']) ? $GLOBALS['config']['dash.info_face'] : '';
if (function_exists('sb_safe_http_url')) {
	$dash_vk = sb_safe_http_url($dash_vk);
	$dash_steam = sb_safe_http_url($dash_steam);
	$dash_yout = sb_safe_http_url($dash_yout);
	$dash_face = sb_safe_http_url($dash_face);
}
$theme->assign('dashboard_info_vk',  $dash_vk);
$theme->assign('dashboard_info_steam',  $dash_steam);
$theme->assign('dashboard_info_yout',  $dash_yout);
$theme->assign('dashboard_info_face',  $dash_face);
$theme->assign('players_blocked', $stopped);
$theme->assign('total_blocked', $totalstopped);

$theme->assign('players_banned', $bans);
$theme->assign('total_bans', $BanCount);

$theme->assign('total_comms', $CommCount);
$theme->assign('players_commed', $comms);

$theme->assign('stats', (isset($GLOBALS['config']['theme.home.stats']) && $GLOBALS['config']['theme.home.stats'] == "1"));

if (function_exists('sb_ui_v2_enabled') && sb_ui_v2_enabled()) {
	$qry = isset($GLOBALS['server_qry']) ? (string)$GLOBALS['server_qry'] : '';
	$extra_js = "<script>\n"
		. "window.addEvent('domready', function(){ " . $qry . " });\n"
		. "</script>\n";
	$header_title = isset($GLOBALS['config']['template.title']) ? stripslashes($GLOBALS['config']['template.title']) : '';
	sb_ui_v2_render('dashboard.twig', array(
		'title' => ($header_title !== '' ? $header_title : 'Главная'),
		'header_title' => $header_title,
		'total_admins' => $counts['admins'],
		'total_servers' => $counts['servers'],
		'nocountryshow' => (isset($GLOBALS['config']['banlist.nocountryfetch']) && $GLOBALS['config']['banlist.nocountryfetch'] == '1' && !$GLOBALS['userbank']->is_logged_in()),
		'listing_block' => isset($GLOBALS['config']['config.home.comms']) ? $GLOBALS['config']['config.home.comms'] : '',
		'dashboard_title' => stripslashes($dashIntroTitle),
		'dashboard_text' => $dashboard_text,
		'dashboard_info_block' => isset($GLOBALS['config']['dash.info_block']) ? $GLOBALS['config']['dash.info_block'] : '',
		'dashboard_info_block_text' => $info_block_text,
		'dashboard_info_block_text_p' => $info_block_text_p,
		'dashboard_info_vk' => $dash_vk,
		'dashboard_info_steam' => $dash_steam,
		'dashboard_info_yout' => $dash_yout,
		'dashboard_info_face' => $dash_face,
		'players_blocked' => $stopped,
		'total_blocked' => $totalstopped,
		'players_banned' => $bans,
		'total_bans' => $BanCount,
		'total_comms' => $CommCount,
		'players_commed' => $comms,
		'stats' => ($GLOBALS['config']['theme.home.stats'] == '1'),
		'server_list' => isset($servers) ? $servers : array(),
		'extra_js' => $extra_js,
	));
	return;
}

$theme->display('page_dashboard.tpl');
