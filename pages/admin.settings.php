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
		
	if(isset($_POST['log_clear']) && $_POST['log_clear'] == "true")
	{
		$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
		if(!function_exists('sb_csrf_validate') || !sb_csrf_validate($csrf))
			sb_csrf_fail_page(true);
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
		$value = substr($GLOBALS['db']->qstr($_GET['advSearch'], false), 1, -1);
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
		$prev = CreateLinkR('<- пред', sb_url_query('admin', $searchlink . '&c=settings&page=' . ($page-1)) . '#^2');
	else 
		$prev = "";
		
	if($list_end < $log_count)
		$next = CreateLinkR('след ->', sb_url_query('admin', $searchlink . '&c=settings&page=' . ($page+1)) . '#^2');
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
		$advSearchJs = json_encode((string)$_GET['advSearch'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$advTypeJs = json_encode((string)$_GET['advType'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$page_numbers .= '&nbsp;<select onchange=\'changePage(this,"L",' . $advSearchJs . ',' . $advTypeJs . ');\'>';
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
		$log_item['function'] = function_exists('sb_log_plain_stack')
			? sb_log_plain_stack(isset($l['function']) ? $l['function'] : '')
			: (isset($l['function']) ? $l['function'] : '');
		$log_item['query'] = isset($l['query']) ? html_entity_decode(strip_tags((string)$l['query']), ENT_QUOTES, 'UTF-8') : '';
		$log_item['message'] = isset($l['message']) ? html_entity_decode(strip_tags((string)$l['message']), ENT_QUOTES, 'UTF-8') : '';
		array_push($log_list, $log_item);
	}
?>
<div id="admin-page-content">
<?php if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS))
{
	echo '<div id="0" class="admin-pane is-on">Доступ запрещен!</div>';
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
			sb_csrf_fail_page(true);
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

				$debugmode = 0;
				$summertime = 0;
				$hideadmname = (isset($_POST['banlist_hideadmname']) && $_POST['banlist_hideadmname'] == "on" ? 1 : 0);
                
				$hideplayerips = (isset($_POST['banlist_hideplayerips']) && $_POST['banlist_hideplayerips'] == "on" ? 1 : 0);
				
				$nocountryfetch = (isset($_POST['banlist_nocountryfetch']) && $_POST['banlist_nocountryfetch'] == "on" ? 1 : 0);
				
				$gendata = 0;
				
				$onlyinvolved = (isset($_POST['protest_emailonlyinvolved']) && $_POST['protest_emailonlyinvolved'] == "on" ? 1 : 0);
				
				$admin_list_en = (isset($_POST['admin_list_t']) && $_POST['admin_list_t'] == "on" ? 1 : 0);
				$vay4_en = (isset($_POST['vay4_t']) && $_POST['vay4_t'] == "on" ? 1 : 0);
				
				$customReasons = (isset($_POST['bans_customreason']) && is_array($_POST['bans_customreason']))
					? $_POST['bans_customreason']
					: array();
				$size = sizeof($customReasons);
				for($i=0;$i<$size;$i++) {
					if(empty($customReasons[$i]))
						unset($customReasons[$i]);
					else
						$customReasons[$i] = htmlspecialchars($customReasons[$i]);
				}
				if(sizeof($customReasons)!=0)
					$cureason = serialize($customReasons);
				else
					$cureason = "";

				$tz_string = $_POST['timezoneoffset'];

				// Логотип шапки зафиксирован: кастомный PNG/путь ломает Material Admin brand.
				$locked_logo = 'images/icons/logo-material-admin.svg';
				$dash_intro_safe = function_exists('sb_sanitize_admin_html')
					? sb_sanitize_admin_html(isset($_POST['dash_intro_text']) ? $_POST['dash_intro_text'] : '')
					: (isset($_POST['dash_intro_text']) ? $_POST['dash_intro_text'] : '');

				$GLOBALS['db']->StartTrans();
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
				$smtpEnabled = (isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] == "on") ? "1" : "0";
				$smtpUsername = isset($_POST['smtp_username']) ? $_POST['smtp_username'] : '';
				$smtpPort = isset($_POST['smtp_port']) ? $_POST['smtp_port'] : '';
				$smtpHost = isset($_POST['smtp_host']) ? $_POST['smtp_host'] : '';
				$smtpCharset = isset($_POST['smtp_charset']) ? $_POST['smtp_charset'] : '';
				$smtpFrom = isset($_POST['smtp_from']) ? $_POST['smtp_from'] : '';
				$smtpEdit = $GLOBALS['db']->Execute(sprintf("REPLACE INTO `%s_settings` (`value`, `setting`) VALUES
				('%s', 'smtp.enabled'),
				(%s, 'smtp.username'),
				(%s, 'smtp.port'),
				(%s, 'smtp.host'),
				(%s, 'smtp.charset'),
				(%s, 'smtp.from');", DB_PREFIX, $smtpEnabled, $GLOBALS['db']->qstr($smtpUsername), $GLOBALS['db']->qstr($smtpPort), $GLOBALS['db']->qstr($smtpHost), $GLOBALS['db']->qstr($smtpCharset), $GLOBALS['db']->qstr($smtpFrom)));
				// PASSWORD SMTP
				$passwordEdit = true;
				$smtpPassword = isset($_POST['smtp_password']) ? $_POST['smtp_password'] : '*Скрыт*';
				if ($smtpPassword != "*Скрыт*")
					$passwordEdit = (bool)$GLOBALS['db']->Execute(sprintf("REPLACE INTO `%s_settings` (`value`, `setting`) VALUES (%s, 'smtp.password');", DB_PREFIX, $GLOBALS['db']->qstr($smtpPassword)));
				$saveOk = (bool)$edit && (bool)$smtpEdit && $passwordEdit;
				$saveOk = (bool)$GLOBALS['db']->CompleteTrans($saveOk) && $saveOk;
				
				if ($saveOk) {
					?><script>setTimeout("ShowBox('Главные настройки изменены', 'Изменения были успешно применены!', 'green', 'index.php?p=admin&c=settings', false, 2500);", 1200);</script><?php
					$log = new CSystemLog("m", "Настройки изменены", $userbank->GetProperty("user") . " изменил главные настройки (mainsettings).");
				} else {
					CreateRedBox("Ошибка", "Не удалось полностью сохранить настройки: " . htmlspecialchars($GLOBALS['db']->ErrorMsg(), ENT_QUOTES, 'UTF-8'));
				}
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
			$twig_precompile = (isset($_POST['twig_precompile']) && $_POST['twig_precompile'] == "on" ? 1 : 0);
			
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
											(" . (int)$totp_enforce_owner . ", 'config.totp.enforce_owner'),
											(" . (int)$twig_precompile . ", 'config.twig.precompile');");

			if ($edit) {
				if ((int)$twig_precompile === 1 && function_exists('sb_ui_v2_twig_precompile_all')) {
					try {
						sb_ui_v2_twig_precompile_all();
					} catch (Throwable $e) {
					}
				} elseif ((int)$twig_precompile === 0 && function_exists('sb_ui_v2_twig_cache_clear')) {
					$twig_was_on = isset($GLOBALS['config']['config.twig.precompile']) && (string)$GLOBALS['config']['config.twig.precompile'] === '1';
					if ($twig_was_on) {
						try {
							sb_ui_v2_twig_cache_clear();
						} catch (Throwable $e) {
						}
					}
				}
				?><script>setTimeout("ShowBox('Настройки опций изменены', 'Изменения были успешно применены!', 'green', 'index.php?p=admin&c=settings#^3', false, 2500);", 1200);</script><?php
				$log = new CSystemLog("m", "Настройки изменены", $userbank->GetProperty("user") . " изменил настройки раздела \"Опции\" (features).");
			} else {
				CreateRedBox("Ошибка", "Не удалось сохранить настройки опций: " . htmlspecialchars($GLOBALS['db']->ErrorMsg(), ENT_QUOTES, 'UTF-8'));
			}
		}

		if ($_POST['settingsGroup'] == "seo")
		{
			$ogSite = isset($_POST['seo_og_site_name']) ? trim((string)$_POST['seo_og_site_name']) : '';
			$ogTitle = isset($_POST['seo_og_title']) ? trim((string)$_POST['seo_og_title']) : '';
			$ogDesc = isset($_POST['seo_og_description']) ? trim((string)$_POST['seo_og_description']) : '';
			$metaDesc = isset($_POST['seo_meta_description']) ? trim((string)$_POST['seo_meta_description']) : '';
			$ogImage = isset($_POST['seo_og_image']) ? trim((string)$_POST['seo_og_image']) : '';
			$ogW = isset($_POST['seo_og_image_width']) ? (int)$_POST['seo_og_image_width'] : 0;
			$ogH = isset($_POST['seo_og_image_height']) ? (int)$_POST['seo_og_image_height'] : 0;

			if ($ogImage !== '' && preg_match('#^https?://#i', $ogImage) === 0) {
				$ogImage = ltrim(str_replace('\\', '/', $ogImage), '/');
				if (strpos($ogImage, '..') !== false) {
					CreateRedBox("Ошибка", "Некорректный путь к обложке.");
					PageDie();
				}
			}
			if ($ogW < 0)
				$ogW = 0;
			if ($ogH < 0)
				$ogH = 0;
			if ($ogW > 4096)
				$ogW = 4096;
			if ($ogH > 4096)
				$ogH = 4096;

			$edit = $GLOBALS['db']->Execute(
				"REPLACE INTO " . DB_PREFIX . "_settings (`value`, `setting`) VALUES
					(?, 'seo.og_site_name'),
					(?, 'seo.og_title'),
					(?, 'seo.og_description'),
					(?, 'seo.meta_description'),
					(?, 'seo.og_image'),
					(?, 'seo.og_image_width'),
					(?, 'seo.og_image_height')",
				array(
					$ogSite,
					$ogTitle,
					$ogDesc,
					$metaDesc,
					$ogImage,
					$ogW > 0 ? (string)$ogW : '',
					$ogH > 0 ? (string)$ogH : '',
				)
			);

			if ($edit) {
				$GLOBALS['config']['seo.og_site_name'] = $ogSite;
				$GLOBALS['config']['seo.og_title'] = $ogTitle;
				$GLOBALS['config']['seo.og_description'] = $ogDesc;
				$GLOBALS['config']['seo.meta_description'] = $metaDesc;
				$GLOBALS['config']['seo.og_image'] = $ogImage;
				$GLOBALS['config']['seo.og_image_width'] = $ogW > 0 ? (string)$ogW : '';
				$GLOBALS['config']['seo.og_image_height'] = $ogH > 0 ? (string)$ogH : '';
				?><script>setTimeout("ShowBox('SEO сохранено', 'Параметры SEO записаны в базу.', 'green', 'index.php?p=admin&c=settings#^4', false, 2500);", 1200);</script><?php
				$log = new CSystemLog("m", "SEO настройки", $userbank->GetProperty("user") . " изменил SEO / Open Graph.");
			} else {
				CreateRedBox("Ошибка", "Не удалось сохранить SEO: " . htmlspecialchars($GLOBALS['db']->ErrorMsg(), ENT_QUOTES, 'UTF-8'));
			}
		}

		if ($_POST['settingsGroup'] == "seo_rebuild")
		{
			if (!function_exists('sb_write_seo_files')) {
				CreateRedBox("Ошибка", "Модуль SEO не загружен.");
			} else {
				$base = defined('SB_WP_URL') ? (string)SB_WP_URL : '';
				$res = sb_write_seo_files(rtrim(str_replace('\\', '/', ROOT), '/'), $base, array('write_og_stub' => false));
				if (!empty($res['ok'])) {
					$files = !empty($res['files']) ? implode(', ', $res['files']) : 'sitemap.xml, robots.txt';
					$msg = $files;
					if (!empty($res['error']))
						$msg .= ' (' . $res['error'] . ')';
					$msgJs = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
					?><script>setTimeout(function(){ ShowBox('SEO файлы', <?php echo $msgJs; ?>, 'green', 'index.php?p=admin&c=settings#^4', false, 2800); }, 800);</script><?php
					$log = new CSystemLog("m", "SEO файлы", $userbank->GetProperty("user") . " пересобрал sitemap.xml / robots.txt.");
				} else {
					$err = !empty($res['error']) ? $res['error'] : 'неизвестная ошибка';
					CreateRedBox("Ошибка", htmlspecialchars($err, ENT_QUOTES, 'UTF-8'));
					$log = new CSystemLog("w", "SEO файлы", $userbank->GetProperty("user") . " не смог пересобрать SEO-файлы: " . $err);
				}
			}
		}

		if ($_POST['settingsGroup'] == "seo_upload")
		{
			if (!function_exists('sb_seo_save_og_upload')) {
				CreateRedBox("Ошибка", "Модуль SEO не загружен.");
			} elseif (empty($_FILES['seo_og_file']) || !is_array($_FILES['seo_og_file'])) {
				CreateRedBox("Ошибка", "Выберите файл обложки.");
			} else {
				$up = sb_seo_save_og_upload($_FILES['seo_og_file'], rtrim(str_replace('\\', '/', ROOT), '/'));
				if (!empty($up['ok']) && !empty($up['path'])) {
					$wStr = !empty($up['width']) ? (string)(int)$up['width'] : '';
					$hStr = !empty($up['height']) ? (string)(int)$up['height'] : '';
					$GLOBALS['db']->Execute(
						"REPLACE INTO " . DB_PREFIX . "_settings (`value`, `setting`) VALUES
							(?, 'seo.og_image'),
							(?, 'seo.og_image_width'),
							(?, 'seo.og_image_height')",
						array($up['path'], $wStr, $hStr)
					);
					$GLOBALS['config']['seo.og_image'] = $up['path'];
					if ($wStr !== '')
						$GLOBALS['config']['seo.og_image_width'] = $wStr;
					if ($hStr !== '')
						$GLOBALS['config']['seo.og_image_height'] = $hStr;
					?><script>setTimeout("ShowBox('Обложка OG', 'Файл сохранён как <?php echo htmlspecialchars($up['path'], ENT_QUOTES, 'UTF-8'); ?>', 'green', 'index.php?p=admin&c=settings#^4', false, 2500);", 800);</script><?php
					$log = new CSystemLog("m", "SEO обложка", $userbank->GetProperty("user") . " загрузил " . $up['path'] . ".");
				} else {
					$err = !empty($up['error']) ? $up['error'] : 'загрузка не удалась';
					CreateRedBox("Ошибка", htmlspecialchars($err, ENT_QUOTES, 'UTF-8'));
					$log = new CSystemLog("w", "SEO обложка", $userbank->GetProperty("user") . " — ошибка загрузки: " . $err);
				}
			}
		}
	}

	$date_offs = $GLOBALS['config']['config.timezone'];

	$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');

	#########[Settings Page]###############
	echo '<div id="0" class="admin-pane is-on">';
		
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
		
		$theme->assign('bans_customreason', sb_unserialize_array(isset($GLOBALS['config']['bans.customreasons']) ? $GLOBALS['config']['bans.customreasons'] : '') ?: array());
		
		// SMTP Settings
		$theme->assign('smtp_enabled', ($GLOBALS['config']['smtp.enabled'] == "1"));
		$theme->assign('smtp_username', $GLOBALS['config']['smtp.username']);
		$theme->assign('smtp_port',     $GLOBALS['config']['smtp.port']);
		$theme->assign('smtp_host',     $GLOBALS['config']['smtp.host']);
		$theme->assign('smtp_charset',  $GLOBALS['config']['smtp.charset']);
		$theme->assign('smtp_from',     $GLOBALS['config']['smtp.from']);
		
		sb_ui_v2_theme_fragment('admin_settings_settings.twig');
	echo '</div>';
	#########/[Settings Page]###############

	#########[Features Page]###############
	echo '<div id="3" class="admin-pane">';
		$theme->assign('old_serverside', ($GLOBALS['config']['feature.old_serverside'] == "1"));
		// Настройка ещё не сохранялась ни разу -> считаем автозагрузку карт включённой по умолчанию.
		$theme->assign('map_autofetch', (!isset($GLOBALS['config']['feature.map_autofetch']) || $GLOBALS['config']['feature.map_autofetch'] == "1"));
		$theme->assign('totp_enforce_owner', (!empty($GLOBALS['config']['config.totp.enforce_owner']) && $GLOBALS['config']['config.totp.enforce_owner'] == "1"));
		$theme->assign('maxWarnings', $GLOBALS['config']['admin.warns.max']);
		$theme->assign('warnings_enabled', ($GLOBALS['config']['admin.warns'] == "1"));
		sb_ui_v2_theme_fragment('admin_settings_features.twig');
	echo '</div>';
	#########/[Features Page]###############

	#########[SEO Page]###############
	echo '<div id="4" class="admin-pane">';
		$seoBundle = function_exists('sb_seo_og_bundle')
			? sb_seo_og_bundle(isset($GLOBALS['config']['template.title']) ? $GLOBALS['config']['template.title'] : '')
			: array(
				'og_site_name' => '',
				'og_title' => '',
				'og_description' => '',
				'og_image' => 'images/og-cover.jpg',
				'og_image_width' => 1200,
				'og_image_height' => 630,
				'meta_description' => '',
				'site_base' => '',
			);
		$theme->assign('seo_og_site_name', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.og_site_name') : '');
		$theme->assign('seo_og_title', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.og_title') : '');
		$theme->assign('seo_og_description', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.og_description') : '');
		$theme->assign('seo_meta_description', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.meta_description') : '');
		$theme->assign('seo_og_image', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.og_image') : '');
		$theme->assign('seo_og_image_width', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.og_image_width') : '');
		$theme->assign('seo_og_image_height', function_exists('sb_seo_cfg') ? sb_seo_cfg('seo.og_image_height') : '');
		$theme->assign('seo_resolved', $seoBundle);
		$previewImg = function_exists('sb_seo_absolute_image_url')
			? sb_seo_absolute_image_url($seoBundle['og_image'], $seoBundle['site_base'])
			: '';
		if ($previewImg !== '' && is_readable(ROOT . 'images/og-cover.jpg')) {
			$previewImg .= (strpos($previewImg, '?') === false ? '?' : '&') . 'v=' . (int)@filemtime(ROOT . 'images/og-cover.jpg');
		}
		$theme->assign('seo_preview_image', $previewImg);
		sb_ui_v2_theme_fragment('admin_settings_seo.twig');
	echo '</div>';
	#########/[SEO Page]###############
	
	#########[Themes Page]###############
	echo '<div id="1" class="admin-pane">';
		$theme->assign('config_text_home', 			isset($GLOBALS['config']['config.text_home']) ? $GLOBALS['config']['config.text_home'] : '');
		$theme->assign('config_text_mon', 			isset($GLOBALS['config']['config.text_mon']) ? $GLOBALS['config']['config.text_mon'] : '');
		$theme->assign('config_text_acc', 			isset($GLOBALS['config']['config.text_acc']) ? $GLOBALS['config']['config.text_acc'] : '');
		$theme->assign('config_text_acc2', 			isset($GLOBALS['config']['config.text_acc2']) ? $GLOBALS['config']['config.text_acc2'] : '');

		sb_ui_v2_theme_fragment('admin_settings_theme.twig');
	echo '</div>';
	#########/[Settings Page]###############
	
	#########[Logs Page]###############
	echo '<div id="2" class="admin-pane">';
		if($userbank->HasAccess(ADMIN_OWNER))
			$theme->assign('clear_logs', "( <a href='javascript:ClearLogs();'>Очистить лог</a> )");
		$theme->assign('page_numbers', 			$page_numbers);
		$theme->assign('log_items',				$log_list);
		$theme->assign('admin_list', $GLOBALS['db']->GetAll("SELECT aid, user FROM `" . DB_PREFIX . "_admins` ORDER BY user ASC"));
		sb_ui_v2_theme_fragment('admin_settings_logs.twig');
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

	setChecked('enable_submit', <?php echo $sbCfgInt('config.enablesubmit'); ?>);
	setChecked('enable_protest', <?php echo $sbCfgInt('config.enableprotest'); ?>);
	setChecked('enable_kickit', <?php echo $sbCfgInt('config.enablekickit', 1); ?>);
	setChecked('twig_precompile', <?php echo $sbCfgInt('config.twig.precompile'); ?>);
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
})();

function MoreFields()
{
	var t = document.getElementById("custom.reasons");
	if (!t) return;
	var div_add = document.createElement("div");
	div_add.className = "mb-2";
	var input_add = document.createElement("input");
	input_add.className = "form-control";
	input_add.setAttribute("placeholder","Введите данные");
	input_add.setAttribute("type","text");
	input_add.setAttribute("name","bans_customreason[]");
	div_add.appendChild(input_add);
	t.appendChild(div_add);
}
</script>
