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
	
	//Log stuff
	$logs = new CSystemLog();
	$page = 1;
	if (isset($_GET['page']) && $_GET['page'] > 0)
		$page = intval($_GET['page']);
		
	if(isset($_GET['log_clear']) && $_GET['log_clear'] == "true")
	{
		if($userbank->HasAccess(ADMIN_OWNER))
		{
			$clearing_admin = $userbank->GetProperty('user');
			$result = $GLOBALS['db']->Execute("TRUNCATE TABLE `".DB_PREFIX."_log`");
			// Пишем запись ПОСЛЕ очистки, чтобы она осталась первой в свежем логе -
			// иначе TRUNCATE стирал бы даже сам факт своей очистки (важно для аудита!).
			$log = new CSystemLog("w", "Лог очищен", $clearing_admin . " очистил системный лог полностью.");
		}
        else
        {
            $log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty('user') . " пытался очистить лог, не имея на это прав.");
        }
	}
	
	// search
	$where = "";
	if(isset($_GET['advSearch']))
	{
		// Escape the value, but strip the leading and trailing quote
		$value = substr($GLOBALS['db']->qstr($_GET['advSearch'], get_magic_quotes_gpc()), 1, -1);
		$type = $_GET['advType'];
		switch($type)
		{
			case "admin":
				$where = " WHERE l.aid = '" . $value . "'";
			break;
			case "message":
				$where = " WHERE l.message LIKE '%" . $value . "%' OR l.title LIKE '%" . $value . "%'";
			break;
			case "date":
				$date = explode(",", $value);
				$time = mktime($date[3],$date[4],0,$date[1],$date[0],$date[2]);
				$time2 = mktime($date[5],$date[6],59,$date[1],$date[0],$date[2]);
				$where = "WHERE l.created > '$time' AND l.created < '$time2'";
			break;
			case "type":
				$where = " WHERE l.type = '" . $value . "'";
			break;
			default:
				$_GET['advType'] = "";
				$_GET['advSearch'] = "";
				$where = "";
			break;
		}
		$searchlink = "&advSearch=".$_GET['advSearch']."&advType=".$_GET['advType'];
	}
	else
		$searchlink = "";
	
	$list_start = ($page-1) * intval($GLOBALS['config']['banlist.bansperpage']);
	$list_end = $list_start + intval($GLOBALS['config']['banlist.bansperpage']);
	
	$log_count = $logs->LogCount($where);
	$log = $logs->getAll($list_start, intval($GLOBALS['config']['banlist.bansperpage']), $where);
	if(($page > 1))
		$prev = CreateLinkR('<- пред',"index.php?p=admin&c=settings" . $searchlink . "&page=" .($page-1) . "#^2");
	else 
		$prev = "";
		
	if($list_end < $log_count)
		$next = CreateLinkR('след ->',"index.php?p=admin&c=settings" . $searchlink . "&page=" .($page+1)."#^2");
	else 
		$next = "";

		
	$pages = (round($log_count/intval($GLOBALS['config']['banlist.bansperpage']))==0)?1:round($log_count/intval($GLOBALS['config']['banlist.bansperpage']));
	if($pages>1)
		$page_numbers =  'Страница ' . $page . ' из ' . $pages . " - " . $prev . " | " . $next;
	else
		$page_numbers = 'Страница ' . $page . ' из ' . $pages;
		
		
	$pages = ceil($log_count/intval($GLOBALS['config']['banlist.bansperpage']));
	if($pages > 1) {
		if(!isset($_GET['advSearch']) || !isset($_GET['advType'])) {
			$_GET['advSearch'] = "";
			$_GET['advType'] = "";
		}
		$page_numbers .= '&nbsp;<select onchange="changePage(this,\'L\',\''.htmlspecialchars(addslashes($_GET['advSearch'])).'\',\''.htmlspecialchars(addslashes($_GET['advType'])).'\');">';
		for($i=1;$i<=$pages;$i++) {
			if(isset($_GET["page"]) && $i==$_GET["page"]) {
				$page_numbers .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
				continue;
			}
			$page_numbers .= '<option value="' . $i . '">' . $i . '</option>';
		}
		$page_numbers .= '</select>';
	}
	$log_list = array();
	foreach($log as $l)
	{
		$log_item = array();
		if($l['type'] == "m")
			$log_item['type_img'] = "<img class='sb-ico' src='images/icons/help.svg' width='16' height='16' alt='Info'>"; 
		elseif($l['type'] == "w")
			$log_item['type_img'] = "<img class='sb-ico' src='images/icons/warning.svg' width='16' height='16' alt='Warning'>"; 
		elseif($l['type'] == "e")
			$log_item['type_img'] = "<img class='sb-ico' src='images/icons/warning.svg' width='16' height='16' alt='Error'>"; 
		$log_item['user'] = !empty($l['user'])?$l['user']:'Guest';
		$log_item['date_str'] = SBDate($dateformat, $l['created']);
		$log_item = array_merge($l, $log_item);	
		array_push($log_list, $log_item);
	}
?>
<div id="admin-page-content">
<?php if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS))
{
	echo '<div id="0" style="display:none;">Доступ запрещен!</div>';
}
else
{
	if(isset($_POST['settingsGroup']))
	{
		$errors = "";

		// CSRF: обычные (не xajax) формы POST на эту же страницу.
		$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
		if(!function_exists('sb_csrf_validate') || !sb_csrf_validate($csrf))
		{
			CreateRedBox("Ошибка", "Неверный CSRF-токен. Обновите страницу и попробуйте снова.");
			PageDie();
		}

		if ($_POST['settingsGroup'] == "mainsettings_themes")
		{
			$edit = $GLOBALS['db']->Execute("REPLACE INTO ".DB_PREFIX."_settings (`value`, `setting`) VALUES
												(?, 'config.text_home'),
												(?, 'config.text_mon'),
												(?, 'config.text_acc'),
												(?, 'config.text_acc2')", array(
													isset($_POST['yvedom_1']) ? $_POST['yvedom_1'] : '',
													isset($_POST['yvedom_2']) ? $_POST['yvedom_2'] : '',
													isset($_POST['yvedom_3']) ? $_POST['yvedom_3'] : '',
													isset($_POST['yvedom_4']) ? $_POST['yvedom_4'] : ''
												));
			if (!$edit) {
				?><script>setTimeout("ShowBox('Ошибка', 'Не удалось сохранить уведомления в БД.', 'red', '', true);", 500);</script><?php
			} else {
				?><script>setTimeout("ShowBox('Уведомления', 'Изменения были успешно применены!', 'green', 'index.php?p=admin&c=settings#^1', false, 2500);", 1500);</script><?php
				$log = new CSystemLog("m", "Настройки изменены", $userbank->GetProperty("user") . " изменил уведомления (mainsettings_themes).");
			}
		}
		if ($_POST['settingsGroup'] == "mainsettings")
		{
			if(!is_numeric($_POST['config_password_minlength']))
				$errors .= "Минимальная длина пароля<br />";
			if(!is_numeric($_POST['banlist_bansperpage']))
				$errors .= "Количество банов на странице должно быть числом";
			if(empty($errors))
			{
				if(isset($_POST['enable_submit']) && $_POST['enable_submit'] == "on") {
					$submit = 1;
				} else {
					$submit = 0;
				}
				if(isset($_POST['enable_protest']) && $_POST['enable_protest'] == "on") {
					$protest = 1;
				} else {
					$protest = 0;
				}

				$debugmode = (isset($_POST['config_debug']) && $_POST['config_debug'] == "on" ? 1 : 0);
				
				$summertime = (isset($_POST['config_summertime']) && $_POST['config_summertime'] == "on" ? 1 : 0);
				
				$hideadmname = (isset($_POST['banlist_hideadmname']) && $_POST['banlist_hideadmname'] == "on" ? 1 : 0);
                
				$hideplayerips = (isset($_POST['banlist_hideplayerips']) && $_POST['banlist_hideplayerips'] == "on" ? 1 : 0);
				
				$nocountryfetch = (isset($_POST['banlist_nocountryfetch']) && $_POST['banlist_nocountryfetch'] == "on" ? 1 : 0);
				
				$gendata = (isset($_POST['footer_gendata']) && $_POST['footer_gendata'] == "on") ? 1 : 0;
				
				$onlyinvolved = (isset($_POST['protest_emailonlyinvolved']) && $_POST['protest_emailonlyinvolved'] == "on" ? 1 : 0);
				
				$admin_list_en = (isset($_POST['admin_list_t']) && $_POST['admin_list_t'] == "on" ? 1 : 0);
				$vay4_en = (isset($_POST['vay4_t']) && $_POST['vay4_t'] == "on" ? 1 : 0);
				
				$size = sizeof($_POST['bans_customreason']);
				for($i=0;$i<$size;$i++) {
					if(empty($_POST['bans_customreason'][$i]))
						unset($_POST['bans_customreason'][$i]);
					else
						$_POST['bans_customreason'][$i] = htmlspecialchars($_POST['bans_customreason'][$i]);
				}
				if(sizeof($_POST['bans_customreason'])!=0)
					$cureason = serialize($_POST['bans_customreason']);
				else
					$cureason = "";

				$tz_string = $_POST['timezoneoffset'];

				// Логотип шапки зафиксирован: кастомный PNG/путь ломает Material Admin brand.
				$locked_logo = 'images/icons/logo-material-admin.svg';
				$dash_intro_safe = function_exists('sb_sanitize_admin_html')
					? sb_sanitize_admin_html(isset($_POST['dash_intro_text']) ? $_POST['dash_intro_text'] : '')
					: (isset($_POST['dash_intro_text']) ? $_POST['dash_intro_text'] : '');

				$edit = $GLOBALS['db']->Execute("REPLACE INTO ".DB_PREFIX."_settings (`value`, `setting`) VALUES
												(?, 'template.title'),
												(?,'template.logo'),
												(" . (int)$_POST['config_password_minlength'] . ", 'config.password.minlength'),
												(" . $debugmode . ", 'config.debug'),
												(?, 'config.dateformat'),
												(?, 'config.dateformat_ver2'),
												(" . (int)$_POST['banlist_bansperpage'] . ", 'banlist.bansperpage'),
												(" . (int)$hideadmname . ", 'banlist.hideadminname'),
												(" . (int)$hideplayerips . ", 'banlist.hideplayerips'),
												(" . (int)$nocountryfetch . ", 'banlist.nocountryfetch'),
												(?, 'dash.intro.text'),
												(" . (int)$protest . ", 'config.enableprotest'),
												(" . (int)$submit . ", 'config.enablesubmit'),
												(" . (int)$onlyinvolved . ", 'protest.emailonlyinvolved'),
												(?, 'config.timezone'),
												(?, 'config.summertime'),
												(?, 'bans.customreasons'),
												(" . (int)$_POST['default_page'] . ", 'config.defaultpage'),
												(" . (int)$_POST['block_home'] . ", 'config.home.comms'),
												(".(int)$admin_list_en.", 'page.adminlist'),
												('".(int)$gendata."', 'page.footer.allow_show_data'),
												(".(int)$vay4_en.", 'page.vay4er')", array($_POST['template_title'], $locked_logo, $_POST['config_dateformat'], $_POST['config_dateformat2'], $dash_intro_safe, $tz_string, $summertime, $cureason));
				
				/* SMTP */
				$GLOBALS['db']->Execute(sprintf("REPLACE INTO `%s_settings` (`value`, `setting`) VALUES
				('%s', 'smtp.enabled'),
				(%s, 'smtp.username'),
				(%s, 'smtp.port'),
				(%s, 'smtp.host'),
				(%s, 'smtp.charset'),
				(%s, 'smtp.from');", DB_PREFIX, (($_POST['smtp_enabled']=="on")?"1":"0"), $GLOBALS['db']->qstr($_POST['smtp_username']), $GLOBALS['db']->qstr($_POST['smtp_port']), $GLOBALS['db']->qstr($_POST['smtp_host']), $GLOBALS['db']->qstr($_POST['smtp_charset']), $GLOBALS['db']->qstr($_POST['smtp_from'])));
				// PASSWORD SMTP
				if ($_POST['smtp_password'] != "*Скрыт*")
					$GLOBALS['db']->Execute(sprintf("REPLACE INTO `%s_settings` (`value`, `setting`) VALUES (%s, 'smtp.password');", DB_PREFIX, $GLOBALS['db']->qstr($_POST['smtp_password'])));
				
				?><script>setTimeout("ShowBox('Главные настройки изменены', 'Изменения были успешно применены!', 'green', 'index.php?p=admin&c=settings', false, 2500);", 1200);</script><?php 
				$log = new CSystemLog("m", "Настройки изменены", $userbank->GetProperty("user") . " изменил главные настройки (mainsettings).");
			}else{
				CreateRedBox("Ошибка", $errors); 
			}
		}
		
		if ($_POST['settingsGroup'] == "features")
		{
			$kickit = (isset($_POST['enable_kickit']) && $_POST['enable_kickit'] == "on" ? 1 : 0);

			$exportpub = (isset($_POST['export_public']) && $_POST['export_public'] == "on" ? 1 : 0);

			$groupban = (isset($_POST['enable_groupbanning']) && $_POST['enable_groupbanning'] == "on" ? 1 : 0);
			
			$friendsban = (isset($_POST['enable_friendsbanning']) && $_POST['enable_friendsbanning'] == "on" ? 1 : 0);
			
			$adminrehash = (isset($_POST['enable_adminrehashing']) && $_POST['enable_adminrehashing'] == "on" ? 1 : 0);
			
			$admininfos = (isset($_POST['enable_admininfo']) && $_POST['enable_admininfo'] == "on" ? 1 : 0);
			$alladmininfos = (isset($_POST['allow_admininfo']) && $_POST['allow_admininfo'] == "on" ? 1 : 0);

			$old_serverside = (isset($_POST['old_serverside']) && $_POST['old_serverside'] == "on" ? 1 : 0);
			
			$admin_warns = (isset($_POST['admin_warns']) && $_POST['admin_warns'] == "on" ? 1 : 0);
			
			$map_autofetch = (isset($_POST['map_autofetch']) && $_POST['map_autofetch'] == "on" ? 1 : 0);
			$totp_enforce_owner = (isset($_POST['totp_enforce_owner']) && $_POST['totp_enforce_owner'] == "on" ? 1 : 0);
			
			$edit = $GLOBALS['db']->Execute("REPLACE INTO ".DB_PREFIX."_settings (`value`, `setting`) VALUES
											(" . (int)$exportpub . ", 'config.exportpublic'),
											(" . (int)$kickit . ", 'config.enablekickit'),
											(" . (int)$groupban . ", 'config.enablegroupbanning'),
											(" . (int)$friendsban . ", 'config.enablefriendsbanning'),
											(" . (int)$_POST['moder_group_st'] . ", 'config.modgroup'),
											(" . (int)$admininfos . ", 'config.enableadmininfos'),
											(" . (int)$alladmininfos . ", 'config.changeadmininfos'),
											(" . (int)$adminrehash . ", 'config.enableadminrehashing'),
											(" . (int)$old_serverside . ", 'feature.old_serverside'),
											(" . (int)$admin_warns . ", 'admin.warns'),
											(" . (int)$_POST['admin_warns_max'] . ", 'admin.warns.max'),
											(" . (int)$map_autofetch . ", 'feature.map_autofetch'),
											(" . (int)$totp_enforce_owner . ", 'config.totp.enforce_owner');");

			?><script>setTimeout("ShowBox('Настройки опций изменены', 'Изменения были успешно применены!', 'green', 'index.php?p=admin&c=settings#^3', false, 2500);", 1200);</script><?php
			$log = new CSystemLog("m", "Настройки изменены", $userbank->GetProperty("user") . " изменил настройки раздела \"Опции\" (features).");
		}
	}

	$date_offs = $GLOBALS['config']['config.timezone'];

	$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');

	#########[Settings Page]###############
	echo '<div id="0" style="display:none;">';
		
		$wgroups = $GLOBALS['db']->GetAll("SELECT gid, name FROM ".DB_PREFIX."_groups WHERE type != 3");
		$theme->assign('wgroups', 				$wgroups);
		$theme->assign('config_modergroup', 		$GLOBALS['config']['config.modgroup']);
	
		$theme->assign('config_dateformat', 		$GLOBALS['config']['config.dateformat']);
		$theme->assign('config_dateformat_ver2', 	$GLOBALS['config']['config.dateformat_ver2']);
		$theme->assign('config_title',			$GLOBALS['config']['template.title']);
		$theme->assign('config_logo',			$GLOBALS['config']['template.logo']);
		$theme->assign('config_min_password', 	$GLOBALS['config']['config.password.minlength']);
		$theme->assign('config_time', 			$date_offs);
		$theme->assign('config_dash_text', 		stripslashes($GLOBALS['config']['dash.intro.text']));
		$theme->assign('config_bans_per_page',	$GLOBALS['config']['banlist.bansperpage']);
		
		$theme->assign('bans_customreason', ((isset($GLOBALS['config']['bans.customreasons'])&&$GLOBALS['config']['bans.customreasons']!="")?unserialize($GLOBALS['config']['bans.customreasons']):array()));
		
		// SMTP Settings
		$theme->assign('smtp_enabled', ($GLOBALS['config']['smtp.enabled'] == "1"));
		$theme->assign('smtp_username', $GLOBALS['config']['smtp.username']);
		$theme->assign('smtp_port',     $GLOBALS['config']['smtp.port']);
		$theme->assign('smtp_host',     $GLOBALS['config']['smtp.host']);
		$theme->assign('smtp_charset',  $GLOBALS['config']['smtp.charset']);
		$theme->assign('smtp_from',     $GLOBALS['config']['smtp.from']);
		
		$theme->display('page_admin_settings_settings.tpl');	
	echo '</div>';
	#########/[Settings Page]###############

	#########[Features Page]###############
	echo '<div id="3" style="display:none;">';
		$theme->assign('old_serverside', ($GLOBALS['config']['feature.old_serverside'] == "1"));
		// Настройка ещё не сохранялась ни разу -> считаем автозагрузку карт включённой по умолчанию.
		$theme->assign('map_autofetch', (!isset($GLOBALS['config']['feature.map_autofetch']) || $GLOBALS['config']['feature.map_autofetch'] == "1"));
		$theme->assign('totp_enforce_owner', (!empty($GLOBALS['config']['config.totp.enforce_owner']) && $GLOBALS['config']['config.totp.enforce_owner'] == "1"));
		$theme->assign('maxWarnings', $GLOBALS['config']['admin.warns.max']);
		$theme->assign('warnings_enabled', ($GLOBALS['config']['admin.warns'] == "1"));
		$theme->display('page_admin_settings_features.tpl');
	echo '</div>';
	#########/[Features Page]###############
	
	#########[Themes Page]###############
	echo '<div id="1" style="display:none;">';
		$theme->assign('config_text_home', 			isset($GLOBALS['config']['config.text_home']) ? $GLOBALS['config']['config.text_home'] : '');
		$theme->assign('config_text_mon', 			isset($GLOBALS['config']['config.text_mon']) ? $GLOBALS['config']['config.text_mon'] : '');
		$theme->assign('config_text_acc', 			isset($GLOBALS['config']['config.text_acc']) ? $GLOBALS['config']['config.text_acc'] : '');
		$theme->assign('config_text_acc2', 			isset($GLOBALS['config']['config.text_acc2']) ? $GLOBALS['config']['config.text_acc2'] : '');

		$theme->display('page_admin_settings_theme.tpl');	
	echo '</div>';
	#########/[Settings Page]###############
	
	#########[Logs Page]###############
	echo '<div id="2" style="display:none;">';
		if($userbank->HasAccess(ADMIN_OWNER))
			$theme->assign('clear_logs', "( <a href='javascript:ClearLogs();'>Очистить лог</a> )");
		$theme->assign('page_numbers', 			$page_numbers);
		$theme->assign('log_items',				$log_list);
				
		$theme->display('page_admin_settings_logs.tpl');	
	echo '</div>';
	#########/[Logs Page]###############
	
}

	$sbCfg = function ($key, $default = '') {
		return isset($GLOBALS['config'][$key]) ? $GLOBALS['config'][$key] : $default;
	};
	$sbCfgInt = function ($key, $default = 0) use ($sbCfg) {
		return (int)$sbCfg($key, $default);
	};
?>
<script>
(function () {
	// Fallback: на проде часто закеширован старый sourcebans.js без sbSetChecked.
	function setChecked(id, on) {
		if (typeof sbSetChecked === 'function') {
			sbSetChecked(id, on);
			return;
		}
		var el = document.getElementById(id);
		if (el) el.checked = !!on;
	}
	function setValue(id, value) {
		if (typeof sbSetValue === 'function') {
			sbSetValue(id, value);
			return;
		}
		var el = document.getElementById(id);
		if (el) el.value = value;
	}

	setChecked('vay4_t', <?php echo $sbCfgInt('page.vay4er'); ?>);
	setChecked('admin_list_t', <?php echo $sbCfgInt('page.adminlist'); ?>);

	setChecked('config_debug', <?php echo $sbCfgInt('config.debug'); ?>);
	setChecked('config_summertime', <?php echo $sbCfgInt('config.summertime'); ?>);
	setChecked('enable_submit', <?php echo $sbCfgInt('config.enablesubmit'); ?>);
	setChecked('enable_protest', <?php echo $sbCfgInt('config.enableprotest'); ?>);
	setChecked('enable_kickit', <?php echo $sbCfgInt('config.enablekickit', 1); ?>);
	setChecked('export_public', <?php echo $sbCfgInt('config.exportpublic'); ?>);
	setValue('default_page', <?php echo $sbCfgInt('config.defaultpage'); ?>);
	setValue('block_home', <?php echo $sbCfgInt('config.home.comms', 1); ?>);
	setChecked('protest_emailonlyinvolved', <?php echo $sbCfgInt('protest.emailonlyinvolved'); ?>);
	setChecked('banlist_hideadmname', <?php echo $sbCfgInt('banlist.hideadminname'); ?>);
	setChecked('banlist_nocountryfetch', <?php echo $sbCfgInt('banlist.nocountryfetch'); ?>);
	setChecked('banlist_hideplayerips', <?php echo $sbCfgInt('banlist.hideplayerips'); ?>);
	setChecked('enable_groupbanning', <?php echo $sbCfgInt('config.enablegroupbanning'); ?>);
	setChecked('enable_friendsbanning', <?php echo $sbCfgInt('config.enablefriendsbanning'); ?>);
	setChecked('enable_admininfo', <?php echo $sbCfgInt('config.enableadmininfos', 1); ?>);
	setChecked('allow_admininfo', <?php echo $sbCfgInt('config.changeadmininfos', 1); ?>);
	setChecked('enable_adminrehashing', <?php echo $sbCfgInt('config.enableadminrehashing', 1); ?>);
	setValue('moder_group_st', <?php echo json_encode((string)$sbCfg('config.modgroup', '0'), JSON_UNESCAPED_UNICODE); ?>);
	setChecked('footer_gendata', <?php echo $sbCfgInt('page.footer.allow_show_data'); ?>);
})();

function MoreFields()
{
	var t = document.getElementById("custom.reasons");
	if (!t) return;
	var div_add = document.createElement("div");
	div_add.className = "fg-line";
	var input_add = document.createElement("input");
	input_add.className = "form-control";
	input_add.setAttribute("placeholder","Введите данные");
	input_add.setAttribute("type","text");
	input_add.setAttribute("name","bans_customreason[]");
	div_add.appendChild(input_add);
	t.appendChild(div_add);
}
</script>
