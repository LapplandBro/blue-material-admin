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

global $userbank, $theme;
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}

if (!isset($userbank) || !is_object($userbank)) {
	echo '<div id="admin-page-content"><div id="0" class="admin-pane is-on"><div class="form-page"><p class="form-flash form-flash--err">Нет сессии администратора.</p></div></div></div>';
	return;
}
if(isset($GLOBALS['IN_ADMIN']) && !defined('CUR_AID'))
	define('CUR_AID', $userbank->GetAid());

if (!isset($theme) || !is_object($theme) || !method_exists($theme, 'assign')) {
	$theme = new class {
		public function assign($k, $v) {}
	};
}

if (!isset($dateformat) || $dateformat === '')
	$dateformat = !empty($GLOBALS['config']['config.dateformat']) ? $GLOBALS['config']['config.dateformat'] : 'm-d-y H:i';

// SECURITY FIX: this action processed the uploaded ban list unconditionally - any admin who could
// reach this section (e.g. one with only ADMIN_ADD_BAN, but without ADMIN_BAN_IMPORT) could import
// bans. The "permission_import" flag further below is only used for hiding the UI, not enforced here.
if(isset($_POST['action']) && $_POST['action'] == "importBans" && $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_IMPORT))
{
	$tmp = (isset($_FILES['importFile']) && isset($_FILES['importFile']['tmp_name']))
		? (string)$_FILES['importFile']['tmp_name']
		: '';
	$bannedcfg = ($tmp !== '' && is_readable($tmp)) ? @file($tmp) : false;
	if (!is_array($bannedcfg))
		$bannedcfg = array();
	$bancnt = 0;
	$importAid = isset($_COOKIE['aid']) ? $_COOKIE['aid'] : 0;
	$importIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

	// SteamID's protected via config.php's SB_PROTECTED_STEAMIDS must never be bannable (see
	// includes/group_ban_process.php for the reference implementation of this same protection).
	$protected_steamids = array_filter(array_map('trim', explode(',', defined('SB_PROTECTED_STEAMIDS') ? SB_PROTECTED_STEAMIDS : '')));

	foreach($bannedcfg AS $ban)
	{
		$line = explode(" ", trim($ban));
		if (!isset($line[1], $line[2]) || $line[1] != "0")
			continue;

		if(validate_ip($line[2])) // if its an banned_ip.cfg
		{
			$check = $GLOBALS['db']->Execute("SELECT ip FROM `" . DB_PREFIX . "_bans` WHERE ip = ? AND RemoveType IS NULL", array($line[2]));

			if(is_object($check) && $check->RecordCount() == 0)
			{
				$bancnt++;
				$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,authid,ip,name,ends,length,reason,aid,adminIp,type) VALUES
									(UNIX_TIMESTAMP(),?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?,?)");
				$GLOBALS['db']->Execute($pre, array("", $line[2], "Импортированный бан", 0, 0, "Импорт из banned_ip.cfg", $importAid, $importIp, 1));
			}
		} else { // if its an banned_user.cfg
			if (!validate_steam($line[2])) {
				if (($accountId = getAccountId($line[2])) !== -1) {
					$steam = renderSteam2($accountId, 0);
				} else {
					continue;
				}
			} else {
				$steam = $line[2];
			}
			if(in_array($steam, $protected_steamids))
			{
				continue;
			}
			$check = $GLOBALS['db']->Execute("SELECT authid FROM `" . DB_PREFIX . "_bans` WHERE authid = ? AND RemoveType IS NULL", array($steam));
			if(is_object($check) && $check->RecordCount() == 0)
			{
				if(!isset($_POST['friendsname']) || $_POST['friendsname'] != "on" || ($pname = GetCommunityName($steam)) == "")
					$pname = "Импортированный бан";
				
				$bancnt++;
				$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,authid,ip,name,ends,length,reason,aid,adminIp,type) VALUES
									(UNIX_TIMESTAMP(),?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?,?)");
				$GLOBALS['db']->Execute($pre, array($steam, "", $pname, 0, 0, "Импорт из banned_user.cfg", $importAid, $importIp, 0));
			}
		}
	}
	if($bancnt > 0)
		$log = new CSystemLog("m", "Баны импортированы", "$bancnt Бан(ы) импортированы");

	echo "<script>setTimeout(\"ShowBox('Импорт банов', '$bancnt бан".($bancnt!=1?"s have":" был")." импортирован.', 'green', '', false, 5000)\", 800);</script>";
}
elseif(isset($_POST['action']) && $_POST['action'] == "importBans")
{
	$log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty("user") . " пытался импортировать баны, не имея на это прав.");
}

if(isset($_GET["rebanid"]))
{
	echo '<script type="text/javascript">xajax_PrepareReban("'.(int)$_GET["rebanid"].'");</script>';
}
if((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && is_string($_GET['pName']) && isset($_GET['sid'])) {
	echo "<script type=\"text/javascript\">setTimeout(\"ShowBox('Загрузка..','<i>Ждите!</i>', 'blue', '', false, 5000);\", 800);xajax_PastePlayerData('".(int)$_GET['sid']."', '".htmlspecialchars(addslashes($_GET['pName']), ENT_QUOTES, 'UTF-8')."');</script>";
}

echo '<div id="admin-page-content">';
	echo '<div id="0" class="admin-pane is-on">';
		$canAddBan = $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN);
		$crRaw = isset($GLOBALS['config']['bans.customreasons']) ? $GLOBALS['config']['bans.customreasons'] : '';
		if (is_array($crRaw))
			$customreason = $crRaw;
		elseif (is_string($crRaw) && $crRaw !== '') {
			$crUn = @unserialize($crRaw);
			$customreason = is_array($crUn) ? $crUn : false;
		} else
			$customreason = false;
		sb_admin_echo_twig_fragment('admin_bans_add.twig', array(
			'permission_addban' => $canAddBan,
			'customreason' => $customreason,
		));
	echo '</div>';

	// Protests
	echo '<div id="1" class="admin-pane">';
	echo '<ul class="admin-embed-tabs admin-subtabs-nav">
		<li id="utab-p0" class="active">
			<a href="index.php?p=admin&amp;c=bans#^1~p0" id="admin_utab_p0" onclick="Swap2ndPane(0,\'p\');return false;">Активные</a>
		</li>
		<li id="utab-p1">
			<a href="index.php?p=admin&amp;c=bans#^1~p1" id="admin_utab_p1" onclick="Swap2ndPane(1,\'p\');return false;">Архив</a>
		</li>
	</ul>';
		// current protests
		echo '<div id="p0">';
        $ItemsPerPage = max(1, (int)SB_BANS_PER_PAGE);
        $page = 1;
        if (isset($_GET['ppage']) && $_GET['ppage'] > 0)
        {
            $page = intval($_GET['ppage']);
        }
        $protests = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0' ORDER BY pid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
        if (!is_array($protests))
            $protests = array();
        $protests_count = $GLOBALS['db']->GetRow("SELECT count(pid) AS count FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0' ORDER BY pid DESC");
        $page_count = (is_array($protests_count) && isset($protests_count['count'])) ? (int)$protests_count['count'] : 0;
        $PageStart = intval(($page-1) * $ItemsPerPage);
        $PageEnd = intval($PageStart+$ItemsPerPage);
        if ($PageEnd > $page_count) $PageEnd = $page_count;
        if ($page > 1)
        {
            $prev = CreateLinkR('<- Предыдущие', sb_url('admin', array('c' => 'bans', 'ppage' => ($page-1))) . '#^1');
        }
        else
        {
            $prev = "";
        }
        if ($PageEnd < $page_count)
        {
            $next = CreateLinkR('Следующие ->', sb_url('admin', array('c' => 'bans', 'ppage' => ($page+1))) . '#^1');
        }
        else
            $next = "";

        $page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

        if (strlen($prev) > 0)
            $page_nav .= ' | <b>'.$prev.'</b>';
        if (strlen($next) > 0)
            $page_nav .= ' | <b>'.$next.'</b>';

        $pages = ceil($page_count/$ItemsPerPage);
        if($pages > 1) {
            $page_nav .= '&nbsp;<select onchange=\'changePage(this,"P","","");\'>';
            for($i=1;$i<=$pages;$i++) {
                if($i==$page) {
                    $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
                    continue;
                }
                $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
            }
            $page_nav .= '</select>';
        }
        
        $delete = array();
		$protest_list = array();
		foreach($protests as $prot)
		{
			$prot['reason'] = wordwrap(htmlspecialchars(isset($prot['reason']) ? $prot['reason'] : ''), 55, "<br />\n", true);
			$protestb = $GLOBALS['db']->GetRow("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, email,ad.user, CONCAT(se.ip,':',se.port), se.sid
							    				FROM ".DB_PREFIX."_bans AS ba
							    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
							    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
							    				WHERE bid = \"". (int)$prot['bid'] . "\"");
			if(!$protestb)
			{
				$delete[] = $prot['bid'];
	    		continue;
			}

			$prot['name'] = $protestb[3];
			$prot['authid'] = $protestb[2];
			$prot['ip'] = $protestb['ip'];
			$protLabel = ($prot['authid'] != '') ? $prot['authid'] : $prot['ip'];
			$prot['label_js'] = json_encode((string)$protLabel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

			$prot['date'] = SBDate($dateformat, $protestb['created']);
			if ($protestb['ends'] == 'never')
	            $prot['ends'] = 'never';
			else
				$prot['ends'] = SBDate($dateformat, $protestb['ends']);
            $prot['ban_reason'] = htmlspecialchars($protestb['reason']);

            $prot['admin'] = $protestb[11];
            if(!$protestb[12])
                $prot['server'] = "ВЕБ бан";
            else
                $prot['server'] = $protestb[12];
			$prot['datesubmitted'] = SBDate($dateformat, $prot['datesubmitted']);
			//COMMENT STUFF
			//-----------------------------------
			$view_comments = true;
			$commentres = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
												(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
												(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
												FROM `".DB_PREFIX."_comments` AS C
												WHERE type = 'P' AND bid = '".(int)$prot['pid']."' ORDER BY added desc");

			if(is_object($commentres) && $commentres->RecordCount()>0) {
				$comment = array();
				$morecom = 0;
				while(!$commentres->EOF) {
					$cdata = array();
					$cdata['morecom'] = ($morecom==1?true:false);
					if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
						$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P&cid='.$commentres->fields['cid'],'Редактировать комментарий');
						if($userbank->HasAccess(ADMIN_OWNER)) {
							$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(".$commentres->fields['cid'].",'P',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
						}
					}
					else {
						$cdata['editcomlink'] = "";
						$cdata['delcomlink'] = "";
					}

					$cdata['comname'] = $commentres->fields['comname'];
					$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
                    $cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
					$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

					if(!empty($commentres->fields['edittime'])) {
						$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
						$cdata['editname'] = $commentres->fields['editname'];
					}
					else {
						$cdata['edittime'] = "";
						$cdata['editname'] = "";
					}

					$morecom = 1;
					array_push($comment,$cdata);
					$commentres->MoveNext();
				}
			}
			else
				$comment = "None";

			$prot['commentdata'] = $comment;
			$prot['protaddcomment'] = CreateLinkR('<img src="images/details.png" alt="" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P');
			//-----------------------------------------

            array_push($protest_list, $prot);

		}
		if(count($delete) > 0) {//time for protest cleanup
			$ids = rtrim(implode(',', $delete), ',');
			$cnt = count($delete);
			$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_protests SET archiv = '2' WHERE bid IN($ids) limit $cnt");
		}

		$theme->assign('permission_protests', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS));
		$theme->assign('permission_editban', 	$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
		$theme->assign('protest_nav', $page_nav);
		$theme->assign('protest_list', $protest_list);
		$theme->assign('protest_count', $page_count-(isset($cnt)?$cnt:0));
		sb_admin_echo_twig_fragment('admin_bans_protests.twig', array(
			'permission_protests' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS),
			'permission_editban' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS),
			'protest_nav' => $page_nav,
			'protest_list' => $protest_list,
			'protest_count' => $page_count-(isset($cnt)?$cnt:0),
		));
		echo '</div>';

		// archived protests
		echo '<div id="p1" style="display:none;">';
        
        $ItemsPerPage = max(1, (int)SB_BANS_PER_PAGE);
        $page = 1;
        if (isset($_GET['papage']) && $_GET['papage'] > 0)
        {
            $page = intval($_GET['papage']);
        }
        $protestsarchiv = $GLOBALS['db']->GetAll("SELECT p.*, (SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = p.archivedby) AS archivedby FROM `" . DB_PREFIX . "_protests` p WHERE archiv > '0' ORDER BY pid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
        if (!is_array($protestsarchiv))
            $protestsarchiv = array();
        $protestsarchiv_count = $GLOBALS['db']->GetRow("SELECT count(pid) AS count FROM `" . DB_PREFIX . "_protests` WHERE archiv > '0' ORDER BY pid DESC");
        $page_count = (is_array($protestsarchiv_count) && isset($protestsarchiv_count['count'])) ? (int)$protestsarchiv_count['count'] : 0;
        $PageStart = intval(($page-1) * $ItemsPerPage);
        $PageEnd = intval($PageStart+$ItemsPerPage);
        if ($PageEnd > $page_count) $PageEnd = $page_count;
        if ($page > 1)
        {
            $prev = CreateLinkR('<- предыдущая', sb_url('admin', array('c' => 'bans', 'papage' => ($page-1))) . '#^1~p1');
        }
        else
        {
            $prev = "";
        }
        if ($PageEnd < $page_count)
        {
            $next = CreateLinkR('следующая ->', sb_url('admin', array('c' => 'bans', 'papage' => ($page+1))) . '#^1~p1');
        }
        else
            $next = "";

        $page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

        if (strlen($prev) > 0)
            $page_nav .= ' | <b>'.$prev.'</b>';
        if (strlen($next) > 0)
            $page_nav .= ' | <b>'.$next.'</b>';

        $pages = ceil($page_count/$ItemsPerPage);
        if($pages > 1) {
            $page_nav .= '&nbsp;<select onchange=\'changePage(this,"PA","","");\'>';
            for($i=1;$i<=$pages;$i++) {
                if($i==$page) {
                    $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
                    continue;
                }
                $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
            }
            $page_nav .= '</select>';
        }

		$delete = array();
		$protest_list_archiv = array();
		foreach($protestsarchiv as $prot)
		{
			$prot['reason'] = wordwrap(htmlspecialchars(isset($prot['reason']) ? $prot['reason'] : ''), 55, "<br />\n", true);

			if($prot['archiv'] != "2") {
				$protestb = $GLOBALS['db']->GetRow("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, email,ad.user, CONCAT(se.ip,':',se.port), se.sid
								    				FROM ".DB_PREFIX."_bans AS ba
								    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
								    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
								    				WHERE bid = \"". (int)$prot['bid'] . "\"");
				if(!$protestb) {
					$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_protests` SET archiv = '2' WHERE pid = '". (int)$prot['pid'] . "';");
					$prot['archiv'] = "2";
					$prot['archive'] = "бан был удален.";
				} else {
					$prot['name'] = $protestb[3];
					$prot['authid'] = $protestb[2];
					$prot['ip'] = $protestb['ip'];
					$protLabel = ($prot['authid'] != '') ? $prot['authid'] : $prot['ip'];
					$prot['label_js'] = json_encode((string)$protLabel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

					$prot['date'] = SBDate($dateformat, $protestb['created']);
					if ($protestb['ends'] == 'never')
			            $prot['ends'] = 'never';
					else
						$prot['ends'] = SBDate($dateformat, $protestb['ends']);
                    $prot['ban_reason'] = htmlspecialchars($protestb['reason']);
                    $prot['admin'] = $protestb[11];
                    if(!$protestb[12])
                        $prot['server'] = "ВЕБ бан";
                    else
                        $prot['server'] = $protestb[12];
					if($prot['archiv'] == "1")
						$prot['archive'] = "протест отправлен в архив.";
					else if($prot['archiv'] == "3")
						$prot['archive'] = "срок бана истек.";
					else if($prot['archiv'] == "4")
						$prot['archive'] = "игрок был разбанен.";
				}
			} else {
				$prot['archive'] = "бан был удален.";
			}
			$prot['datesubmitted'] = SBDate($dateformat, $prot['datesubmitted']);
			//COMMENT STUFF
			//-----------------------------------
			$view_comments = true;
			$commentres = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
												(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
												(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
												FROM `".DB_PREFIX."_comments` AS C
												WHERE type = 'P' AND bid = '".(int)$prot['pid']."' ORDER BY added desc");

			if(is_object($commentres) && $commentres->RecordCount()>0) {
				$comment = array();
				$morecom = 0;
				while(!$commentres->EOF) {
					$cdata = array();
					$cdata['morecom'] = ($morecom==1?true:false);
					if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
						$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P&cid='.$commentres->fields['cid'],'Редактировать комментарий');
						if($userbank->HasAccess(ADMIN_OWNER)) {
							$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Удалить комментарий\" target=\"_self\" onclick=\"Удалить комментарий(".$commentres->fields['cid'].",'P',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
						}
					}
					else {
						$cdata['editcomlink'] = "";
						$cdata['delcomlink'] = "";
					}

					$cdata['comname'] = $commentres->fields['comname'];
					$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
					$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
					$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

					if(!empty($commentres->fields['edittime'])) {
						$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
						$cdata['editname'] = $commentres->fields['editname'];
					}
					else {
						$cdata['edittime'] = "";
						$cdata['editname'] = "";
					}

					$morecom = 1;
					array_push($comment,$cdata);
					$commentres->MoveNext();
				}
			}
			else
				$comment = "None";

			$prot['commentdata'] = $comment;
			$prot['protaddcomment'] = CreateLinkR('<img src="images/details.png" alt="" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P');
			//-----------------------------------------
			if (empty($prot['label_js'])) {
				$protLabel = !empty($prot['authid']) ? $prot['authid'] : (!empty($prot['ip']) ? $prot['ip'] : ('#'.$prot['pid']));
				$prot['label_js'] = json_encode((string)$protLabel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
			}

            array_push($protest_list_archiv, $prot);

		}

		$theme->assign('permission_protests', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS));
		$theme->assign('permission_editban', 	$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
		$theme->assign('aprotest_nav', $page_nav);
		$theme->assign('protest_list_archiv', $protest_list_archiv);
		$theme->assign('protest_count_archiv', $page_count);
		sb_admin_echo_twig_fragment('admin_bans_protests_archiv.twig', array(
			'permission_protests' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS),
			'permission_editban' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS),
			'aprotest_nav' => $page_nav,
			'protest_list_archiv' => $protest_list_archiv,
			'protest_count_archiv' => $page_count,
		));
		echo '</div>';
	echo '</div>';



	//Submissions page
	echo '<div id="2" class="admin-pane">';
	echo '<ul class="admin-embed-tabs admin-subtabs-nav">
		<li id="utab-s0" class="active">
			<a href="index.php?p=admin&amp;c=bans#^2~s0" id="admin_utab_s0" onclick="Swap2ndPane(0,\'s\');return false;">Активные</a>
		</li>
		<li id="utab-s1">
			<a href="index.php?p=admin&amp;c=bans#^2~s1" id="admin_utab_s1" onclick="Swap2ndPane(1,\'s\');return false;">Архив</a>
		</li>
	</ul>';
		echo '<div id="s0">'; // current submissions
            $ItemsPerPage = max(1, (int)SB_BANS_PER_PAGE);
            $page = 1;
            if (isset($_GET['spage']) && $_GET['spage'] > 0)
            {
                $page = intval($_GET['spage']);
            }
            $submissions = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0' ORDER BY subid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
            if (!is_array($submissions))
                $submissions = array();
            $submissions_count = $GLOBALS['db']->GetRow("SELECT count(subid) AS count FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0' ORDER BY subid DESC");
            $page_count = (is_array($submissions_count) && isset($submissions_count['count'])) ? (int)$submissions_count['count'] : 0;
            $PageStart = intval(($page-1) * $ItemsPerPage);
            $PageEnd = intval($PageStart+$ItemsPerPage);
            if ($PageEnd > $page_count) $PageEnd = $page_count;
            if ($page > 1)
            {
                $prev = CreateLinkR('<- предыдущая', sb_url('admin', array('c' => 'bans', 'spage' => ($page-1))) . '#^2');
            }
            else
            {
                $prev = "";
            }
            if ($PageEnd < $page_count)
            {
                $next = CreateLinkR('следующая ->', sb_url('admin', array('c' => 'bans', 'spage' => ($page+1))) . '#^2');
            }
            else
                $next = "";

            $page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

            if (strlen($prev) > 0)
                $page_nav .= ' | <b>'.$prev.'</b>';
            if (strlen($next) > 0)
                $page_nav .= ' | <b>'.$next.'</b>';

            $pages = ceil($page_count/$ItemsPerPage);
            if($pages > 1) {
                $page_nav .= '&nbsp;<select onchange=\'changePage(this,"S","","");\'>';
                for($i=1;$i<=$pages;$i++) {
                    if($i==$page) {
                        $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
                        continue;
                    }
                    $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
                }
                $page_nav .= '</select>';
            }
            
			$theme->assign('permissions_submissions', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS));
			$theme->assign('permissions_editsub', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
			$theme->assign('submission_count', $page_count);
			$submission_list = array();
			foreach($submissions AS $sub)
			{
				// name_js — для onclick (RemoveSubmission): НЕ htmlspecialchars.
				// Иначе &#039; в атрибуте декодируется браузером → breakout в JS (XSS → xajax_AddAdmin).
				$subName = isset($sub['name']) ? (string)$sub['name'] : '';
				$sub['name_js'] = json_encode($subName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                $sub['name'] = wordwrap(htmlspecialchars($subName), 55, "<br />", true);
                $sub['reason'] = wordwrap(htmlspecialchars(isset($sub['reason']) ? $sub['reason'] : ''), 55, "<br />", true);
            
				$dem = $GLOBALS['db']->GetRow("SELECT filename FROM " . DB_PREFIX . "_demos
												WHERE demtype = \"S\" AND demid = " .(int)$sub['subid']);

			    if($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename']))
			    	$sub['demo'] =  "<a href=\"getdemo.php?id=". $sub['subid'] . "&type=S\"><img src=\"images/demo.png\" alt=\"\" /> Получить демо</a>";
			    else
			    	$sub['demo'] = "<a href=\"#\" aria-disabled=\"true\"><img src=\"images/demo.png\" alt=\"\" /> Нет демо</a>";

			    $sub['submitted'] = SBDate($dateformat, $sub['submitted']);

				$mod = $GLOBALS['db']->GetRow("SELECT m.name FROM `".DB_PREFIX."_submissions` AS s
												LEFT JOIN `".DB_PREFIX."_mods` AS m ON m.mid = s.ModID
												WHERE s.subid = ".(int)$sub['subid']);
			    $sub['mod'] = (is_array($mod) && isset($mod['name'])) ? $mod['name'] : '';

				if(empty($sub['server']))
					$sub['hostname'] = '<i><font color="#677882">Другой сервер...</font></i>';
                else
                    $sub['hostname'] = "";
                    
				//COMMENT STUFF
				//-----------------------------------
				$view_comments = true;
					$commentres = $GLOBALS['db']->Execute(
														"SELECT cid, aid, commenttxt, added, edittime,
														(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
														(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
														FROM `".DB_PREFIX."_comments` AS C
														WHERE type = 'S' AND bid = '".(int)$sub['subid']."' ORDER BY added desc");

					if(is_object($commentres) && $commentres->RecordCount()>0) {
						$comment = array();
						$morecom = 0;
						while(!$commentres->EOF) {
							$cdata = array();
							$cdata['morecom'] = ($morecom==1?true:false);
							if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
								$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S&cid='.$commentres->fields['cid'],'Редактировать комментарий');
								if($userbank->HasAccess(ADMIN_OWNER)) {
									$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Удалить комментарий\" target=\"_self\" onclick=\"Удалить комментарий(".$commentres->fields['cid'].",'S',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
								}
							}
							else {
								$cdata['editcomlink'] = "";
								$cdata['delcomlink'] = "";
							}

							$cdata['comname'] = $commentres->fields['comname'];
							$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
							$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
                            $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

							if(!empty($commentres->fields['edittime'])) {
								$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
								$cdata['editname'] = $commentres->fields['editname'];
							}
							else {
								$cdata['edittime'] = "";
								$cdata['editname'] = "";
							}

							$morecom = 1;
							array_push($comment,$cdata);
							$commentres->MoveNext();
						}
					}
					else
						$comment = "None";

					$sub['commentdata'] = $comment;
					$sub['subaddcomment'] = CreateLinkR('<img src="images/details.png" alt="" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S');
				//----------------------------------------

			    array_push($submission_list, $sub);
			}
			$theme->assign('submission_nav', $page_nav);
			$theme->assign('submission_list', $submission_list);
			sb_admin_echo_twig_fragment('admin_bans_submissions.twig', array(
				'permissions_submissions' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS),
				'permissions_editsub' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS),
				'submission_count' => $page_count,
				'submission_nav' => $page_nav,
				'submission_list' => $submission_list,
			));
		echo '</div>';

		// submission archiv
		echo '<div id="s1" style="display:none;">';
            $ItemsPerPage = max(1, (int)SB_BANS_PER_PAGE);
            $page = 1;
            if (isset($_GET['sapage']) && $_GET['sapage'] > 0)
            {
                $page = intval($_GET['sapage']);
            }
            $submissionsarchiv = $GLOBALS['db']->GetAll("SELECT s.*, (SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = s.archivedby) AS archivedby FROM `" . DB_PREFIX . "_submissions` s WHERE archiv > '0' ORDER BY subid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
            if (!is_array($submissionsarchiv))
                $submissionsarchiv = array();
            $submissionsarchiv_count = $GLOBALS['db']->GetRow("SELECT count(subid) AS count FROM `" . DB_PREFIX . "_submissions` WHERE archiv > '0' ORDER BY subid DESC");
            $page_count = (is_array($submissionsarchiv_count) && isset($submissionsarchiv_count['count'])) ? (int)$submissionsarchiv_count['count'] : 0;
            $PageStart = intval(($page-1) * $ItemsPerPage);
            $PageEnd = intval($PageStart+$ItemsPerPage);
            if ($PageEnd > $page_count) $PageEnd = $page_count;
            if ($page > 1)
            {
                $prev = CreateLinkR('<- prev', sb_url('admin', array('c' => 'bans', 'sapage' => ($page-1))) . '#^2~s1');
            }
            else
            {
                $prev = "";
            }
            if ($PageEnd < $page_count)
            {
                $next = CreateLinkR('next ->', sb_url('admin', array('c' => 'bans', 'sapage' => ($page+1))) . '#^2~s1');
            }
            else
                $next = "";

            $page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

            if (strlen($prev) > 0)
                $page_nav .= ' | <b>'.$prev.'</b>';
            if (strlen($next) > 0)
                $page_nav .= ' | <b>'.$next.'</b>';

            $pages = ceil($page_count/$ItemsPerPage);
            if($pages > 1) {
                $page_nav .= '&nbsp;<select onchange=\'changePage(this,"SA","","");\'>';
                for($i=1;$i<=$pages;$i++) {
                    if($i==$page) {
                        $page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
                        continue;
                    }
                    $page_nav .= '<option value="' . $i . '">' . $i . '</option>';
                }
                $page_nav .= '</select>';
            }
            
			$theme->assign('permissions_submissions', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS));
			$theme->assign('permissions_editsub', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
			$theme->assign('submission_count_archiv', $page_count);
			$submission_list_archiv = array();
			foreach($submissionsarchiv AS $sub)
			{
				$subName = isset($sub['name']) ? (string)$sub['name'] : '';
				$sub['name_js'] = json_encode($subName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                $sub['name'] = wordwrap(htmlspecialchars($subName), 55, "<br />", true);
                $sub['reason'] = wordwrap(htmlspecialchars(isset($sub['reason']) ? $sub['reason'] : ''), 55, "<br />", true);
            
				$dem = $GLOBALS['db']->GetRow("SELECT filename FROM " . DB_PREFIX . "_demos
												WHERE demtype = \"S\" AND demid = " .(int)$sub['subid']);

			    if($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename']))
			    	$sub['demo'] =  "<a href=\"getdemo.php?id=". $sub['subid'] . "&type=S\"><img src=\"images/demo.png\" alt=\"\" /> Получить демо</a>";
			    else
			    	$sub['demo'] = "<a href=\"#\" aria-disabled=\"true\"><img src=\"images/demo.png\" alt=\"\" /> Нет демо</a>";

			    $sub['submitted'] = SBDate($dateformat, $sub['submitted']);

				$mod = $GLOBALS['db']->GetRow("SELECT m.name FROM `".DB_PREFIX."_submissions` AS s
												LEFT JOIN `".DB_PREFIX."_mods` AS m ON m.mid = s.ModID
												WHERE s.subid = ".(int)$sub['subid']);
			    $sub['mod'] = (is_array($mod) && isset($mod['name'])) ? $mod['name'] : '';
                if(empty($sub['server']))
                    $sub['hostname'] = '<i><font color="#677882">Другой сервер...</font></i>';
                else
                    $sub['hostname'] = "";
				if($sub['archiv'] == "3")
					$sub['archive'] = "игрок был забанен.";
				else if($sub['archiv'] == "2")
					$sub['archive'] = "жалоба была подтверждена.";
				else if($sub['archiv'] == "1")
					$sub['archive'] = "жалоба была отправлена в архив.";
				//COMMENT STUFF
				//-----------------------------------
				$view_comments = true;
					$commentres = $GLOBALS['db']->Execute(
														"SELECT cid, aid, commenttxt, added, edittime,
														(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
														(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
														FROM `".DB_PREFIX."_comments` AS C
														WHERE type = 'S' AND bid = '".(int)$sub['subid']."' ORDER BY added desc");

					if(is_object($commentres) && $commentres->RecordCount()>0) {
						$comment = array();
						$morecom = 0;
						while(!$commentres->EOF) {
							$cdata = array();
							$cdata['morecom'] = ($morecom==1?true:false);
							if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
								$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S&cid='.$commentres->fields['cid'],'Редактировать комментарий');
								if($userbank->HasAccess(ADMIN_OWNER)) {
									$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Удалить комментарий\" target=\"_self\" onclick=\"Удалить комментарий(".$commentres->fields['cid'].",'S',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
								}
							}
							else {
								$cdata['editcomlink'] = "";
								$cdata['delcomlink'] = "";
							}

							$cdata['comname'] = $commentres->fields['comname'];
							$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
							$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
                            $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

							if(!empty($commentres->fields['edittime'])) {
								$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
								$cdata['editname'] = $commentres->fields['editname'];
							}
							else {
								$cdata['edittime'] = "";
								$cdata['editname'] = "";
							}

							$morecom = 1;
							array_push($comment,$cdata);
							$commentres->MoveNext();
						}
					}
					else
						$comment = "None";

					$sub['commentdata'] = $comment;
					$sub['subaddcomment'] = CreateLinkR('<img src="images/details.png" alt="" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S');
				//----------------------------------------

			    array_push($submission_list_archiv, $sub);
			}
            $theme->assign('asubmission_nav', $page_nav);
			$theme->assign('submission_list_archiv', $submission_list_archiv);
			sb_admin_echo_twig_fragment('admin_bans_submissions_archiv.twig', array(
				'permissions_submissions' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS),
				'permissions_editsub' => $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS),
				'submission_count_archiv' => $page_count,
				'asubmission_nav' => $page_nav,
				'submission_list_archiv' => $submission_list_archiv,
			));
		echo '</div>';
	echo '</div>';

	echo '<div id="3" class="admin-pane">';
		$permImport = $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_IMPORT);
		$requirements = (ini_get('safe_mode') != 1);
		sb_admin_echo_twig_fragment('admin_bans_import.twig', array(
			'permission_import' => $permImport,
			'extreq' => $requirements,
		));
	echo '</div>';

	echo '<div id="4" class="admin-pane">';
		$permAddBan = $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN);
		$groupBanOn = (isset($GLOBALS['config']['config.enablegroupbanning']) && $GLOBALS['config']['config.enablegroupbanning'] == 1);
		sb_admin_echo_twig_fragment('admin_bans_groups.twig', array(
			'permission_addban' => $permAddBan,
			'groupbanning_enabled' => $groupBanOn,
			'list_steam_groups' => isset($_GET['fid']) ? $_GET['fid'] : false,
			'player_name' => '',
		));
	echo '</div>';
?>

<script type="text/javascript">
var did = 0;
var dname = "";
function demo(id, name)
{
	$('demo.msg').setHTML("<b>" + name + "</b>");
	$('demo1.msg').style.display = "block";
	did = id;
	dname = name;
}

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
		$('nick.msg').setHTML('Введите ник игрока, которому хотите дать бан');
		$('nick.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('nick.msg').setHTML('');
		$('nick.msg').setStyle('display', 'none');
	}

	if($('steam').value.length < 10 && !$('ip').value)
	{
		$('steam.msg').setHTML('Введите реальный STEAM ID или Community ID');
		$('steam.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('steam.msg').setHTML('');
		$('steam.msg').setStyle('display', 'none');
	}

	if($('ip').value.length < 7 && !$('steam').value)
	{
		$('ip.msg').setHTML('Введите реальный IP адрес');
		$('ip.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('ip.msg').setHTML('');
		$('ip.msg').setStyle('display', 'none');
	}


	if(!reason)
	{
		$('reason.msg').setHTML('Выберите причину бана.');
		$('reason.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('reason.msg').setHTML('');
		$('reason.msg').setStyle('display', 'none');
	}

	if(err)
		return 0;

	xajax_AddBan($('nickname').value,
				 $('type').value,
				 $('steam').value,
				 $('ip').value,
				 $('banlength').value,
				 did,
				 dname,
				 reason,
				 $('fromsub').value,
				 $('demo_link').value);
}
function ProcessGroupBan()
{
	if(!$('groupurl').value)
	{
		$('groupurl.msg').setHTML('Введите ссылку на группу, которую баните');
		$('groupurl.msg').setStyle('display', 'block');
	}else
	{
		$('groupurl.msg').setHTML('');
		$('groupurl.msg').setStyle('display', 'none');
		xajax_GroupBan($('groupurl').value, "no", "no", $('groupreason').value, "");
	}
}
function CheckGroupBan()
{
	var last = 0;
	for(var i=0;$('chkb_' + i);i++)
	{
		if($('chkb_' + i).checked == true)
			last = $('chkb_' + i).value;
	}
	for(var i=0;$('chkb_' + i);i++)
	{
		if($('chkb_' + i).checked == true)
			xajax_GroupBan($('chkb_' + i).value, "yes", "yes", $('groupreason').value, last);
	}
}
</script>
</div>
