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
	if ($res->fields['length'] == 0)
	{
		$info['perm'] = true;
		$info['unbanned'] = false;
	}
	else
	{
		$info['temp'] = true;
                $info['unbanned'] = false;
	}
	$info['name'] = stripslashes($res->fields[3]);
	//$info['created'] = SBDate($dateformat,$res->fields['created']);
	$info['created'] = SBDate($GLOBALS['config']['config.dateformat_ver2'],$res->fields['created']);
	$info['created_info'] = SBDate("Выдано ".$GLOBALS['config']['config.dateformat'],$res->fields['created']);
	$ltemp = explode(",",$res->fields[6] == 0 ? 'Навсегда' : SecondsToString(intval($res->fields[6])));
	$info['length'] = $ltemp[0];
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
			$info['country_icon'] = '<img src="images/country/' . strtolower($res->fields['country']) . '.gif" alt="' . $res->fields['country'] . '" class="flag-icon" loading="lazy">';
		}
		elseif (isset($GLOBALS['config']['banlist.nocountryfetch']) && $GLOBALS['config']['banlist.nocountryfetch'] == "0")
		{
			$home_ban_country = FetchIp($info['ip']);
			$GLOBALS['db']->Execute("UPDATE " . DB_PREFIX . "_bans SET country = ? WHERE bid = ?", array($home_ban_country, $res->fields['bid']));
			$info['country_icon'] = '<img src="images/country/' . strtolower($home_ban_country) . '.gif" alt="' . $home_ban_country . '" class="flag-icon" loading="lazy">';
		}
		else
		{
			$info['country_icon'] = '<img src="images/country/zz.gif" alt="Страна неизвестна" class="flag-icon" loading="lazy">';
		}
	}
	else
	{
		$info['country_icon'] = '<img src="images/country/zz.gif" alt="Страна неизвестна" class="flag-icon" loading="lazy">';
	}

	if($res->fields[15] == 1)
	{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['ip'] . "&advType=ip&Submit";
	}else{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
	}
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['short_name'] = trunc($info['name'], 25, false);
	
	if($res->fields[14] == 'D' || $res->fields[14] == 'U' || $res->fields[14] == 'E' || ($res->fields[6] && $res->fields[5] < time()))
	{
		$info['unbanned'] = true;
		
		if($res->fields[14] == 'D')
			$info['ub_reason'] = 'D';
		elseif($res->fields[14] == 'U')
			$info['ub_reason'] = 'U';
		else
			$info['ub_reason'] = 'E';
	}
	else
	{
		$info['unbanned'] = false;
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
	if ($res->fields['length'] == 0)
	{
		$info['perm'] = true;
		$info['unbanned'] = false;
	}
	else
	{
		$info['temp'] = true;
                $info['unbanned'] = false;
	}
	$info['name'] = stripslashes($res->fields[3]);
	//$info['created'] = SBDate($dateformat,$res->fields['created']);
	$info['created'] = SBDate($GLOBALS['config']['config.dateformat_ver2'],$res->fields['created']);
	$info['created_info'] = SBDate("Выдано ".$GLOBALS['config']['config.dateformat'],$res->fields['created']);
	$ltemp = explode(",",$res->fields[6] == 0 ? 'Навсегда' : ($res->fields[6] < 0 ? "Сессия" : SecondsToString(intval($res->fields[6]))));
	$info['length'] = $ltemp[0];
	$info['icon'] = empty($res->fields[13]) ? 'web.png' : $res->fields[13];
	$info['authid'] = $res->fields['authid'];
	$info['search_link'] = "index.php?p=commslist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['short_name'] = trunc($info['name'], 25, false);
	$info['type'] = (int)$res->fields['type'];
	$info['type_html'] = sb_comms_type_icon_html($info['type'], 20);
		
	if($res->fields[14] == 'D' || $res->fields[14] == 'U' || $res->fields[14] == 'E' || ($res->fields[6] && $res->fields[5] < time()))
	{
		$info['unbanned'] = true;
			
		if($res->fields[14] == 'D')
			$info['ub_reason'] = 'D';
		elseif($res->fields[14] == 'U')
			$info['ub_reason'] = 'U';
		else
			$info['ub_reason'] = 'E';
	}
	else
	{
		$info['unbanned'] = false;
	}
		
	array_push($comms,$info);
	$res->MoveNext();
}

$counts = $GLOBALS['db']->GetRow("SELECT 
         (SELECT COUNT(aid) FROM `" . DB_PREFIX . "_admins` WHERE aid > 0) AS admins,
         (SELECT COUNT(sid) FROM `" . DB_PREFIX . "_servers`) AS servers"); // +

		 
$theme->assign('total_admins', $counts['admins']); // +
$theme->assign('total_servers', $counts['servers']); // +
$theme->assign('nocountryshow', ($GLOBALS['config']['banlist.nocountryfetch'] == "1" && !$GLOBALS['userbank']->is_logged_in()));
$theme->assign('listing_block',  $GLOBALS['config']['config.home.comms']);

require(TEMPLATES_PATH . "/page.servers.php"); //Set theme vars from servers page

$theme->assign('dashboard_title',  stripslashes($GLOBALS['config']['dash.intro.title']));

$dashboard_text = stripslashes($GLOBALS['config']['dash.intro.text']);
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
$theme->assign('dashboard_info_block',  $GLOBALS['config']['dash.info_block']);
$theme->assign('dashboard_info_block_text',  $GLOBALS['config']['dash.info_block_text']);
$theme->assign('dashboard_info_block_text_p',  $GLOBALS['config']['dash.info_block_text_t']);
$theme->assign('dashboard_info_vk',  $GLOBALS['config']['dash.info_vk']);
$theme->assign('dashboard_info_steam',  $GLOBALS['config']['dash.info_steam']);
$theme->assign('dashboard_info_yout',  $GLOBALS['config']['dash.info_yout']);
$theme->assign('dashboard_info_face',  $GLOBALS['config']['dash.info_face']);
$theme->assign('players_blocked', $stopped);
$theme->assign('total_blocked', $totalstopped);

$theme->assign('players_banned', $bans);
$theme->assign('total_bans', $BanCount);

$theme->assign('total_comms', $CommCount);
$theme->assign('players_commed', $comms);

$theme->assign('stats', ($GLOBALS['config']['theme.home.stats'] == "1"));

$theme->display('page_dashboard.tpl');
