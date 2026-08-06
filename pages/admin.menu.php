<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
global $userbank, $theme;

	if(!$userbank->HasAccess(ADMIN_OWNER))
		CreateRedBox("Доступ запрещен!", "У вас нету доступных привилегий на просмотр данной страницы.");
	else {
		if(!empty($_GET['o']) and !empty($_GET['id']) and !is_numeric($_GET['id'])){
			PageDie();
		}else{
			if(($_GET['o'] == "del") and !empty($_GET['o'])){
					$check_sys = $GLOBALS['db']->GetOne("SELECT system FROM `" . DB_PREFIX . "_menu` WHERE id = '".(int)$_GET['id']."'");
					if($check_sys != "1"){
						$gg_check_sys = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_menu` WHERE id = '".(int)$_GET['id']."'");
						if($gg_check_sys)
							AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Успех!", "Ссылка была успешно удалена!", "green", "", true)), sb_url('admin', array('c' => 'menu')));
						else
							AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Не удалось удалить ссылку! (" . htmlspecialchars($GLOBALS['db']->ErrorMsg()) . ")", "red", "", true)), sb_url('admin', array('c' => 'menu')));
					}else
						AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Системную ссылку удалить невозможно!", "red", "", true)), sb_url('admin', array('c' => 'menu')));
			}elseif(($_GET['o'] == "on") and !empty($_GET['o'])){
				$check_sys = $GLOBALS['db']->GetOne("SELECT enabled FROM `" . DB_PREFIX . "_menu` WHERE id = '".(int)$_GET['id']."'");
				if($check_sys == "0"){
					$gg_check_sys = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_menu` SET `enabled` = '1' WHERE id = '".(int)$_GET['id']."'");
					if($gg_check_sys)
						AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Успех!", "Ссылка была успешно добавлена в главное меню SourceBans!", "green", "", true)), sb_url('admin', array('c' => 'menu')));
					else
						AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Не удалось включить ссылку! (" . htmlspecialchars($GLOBALS['db']->ErrorMsg()) . ")", "red", "", true)), sb_url('admin', array('c' => 'menu')));
				}else
					AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Данная ссылка уже и так отключена!", "red", "", true)), sb_url('admin', array('c' => 'menu')));
			}elseif(($_GET['o'] == "off") and !empty($_GET['o'])){
				$check_sys = $GLOBALS['db']->GetOne("SELECT enabled FROM `" . DB_PREFIX . "_menu` WHERE id = '".(int)$_GET['id']."'");
				if($check_sys == "1"){
				$gg_check_sys = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_menu` SET `enabled` = '0' WHERE id = '".(int)$_GET['id']."'");
				if($gg_check_sys)
					AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Успех!", "Ссылка была успешно удалена из главного меню!", "green", "", true)), sb_url('admin', array('c' => 'menu')));
				else
					AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Не удалось отключить ссылку! (" . htmlspecialchars($GLOBALS['db']->ErrorMsg()) . ")", "red", "", true)), sb_url('admin', array('c' => 'menu')));
			}else
					AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Данная ссылка уже и так включена!", "red", "", true)), sb_url('admin', array('c' => 'menu')));
			}
		}
		if(isset($_POST['Link']))
		{ 
			if ($_POST['Link'] == "add"){
				sb_menu_ensure_group_column();
				$on_act = (isset($_POST['on_link']) && $_POST['on_link'] == "on" ? 1 : 0);
				$menu_icon = isset($_POST['menu_icon']) ? $_POST['menu_icon'] : '';
				$menu_group = isset($_POST['menu_group']) ? sb_menu_normalize_group($_POST['menu_group']) : '';
				$names_link = sb_menu_compose_title(isset($_POST['names_link']) ? $_POST['names_link'] : '', $menu_icon);
				$add = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_menu` (`text`, `description`, `url`, `system`, `enabled`, `priority`, `newtab`, `menu_group`) VALUES (?, ?, ?, 0, ?, ?, ?, ?);", array($names_link, $_POST['des_link'], $_POST['url_link'], $on_act, $_POST['priora_link'], (($_POST['onNewTab']=="on")?"1":"0"), $menu_group));
				// БАГ-ФИКС: раньше результат Execute() вообще не проверялся - сообщение "Успешно"
				// показывалось всегда, даже если INSERT реально провалился (например, ошибка БД).
				// Теперь при неудаче показываем реальную ошибку из ErrorMsg() вместо лживого "успеха".
				if($add) {
					AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Успех!", sprintf("Ссылка была успешно создана%s!", ($on_act==1)?" и добавлена в меню":""), "green", "", true)), sb_url('admin', array('c' => 'menu')));
					$log = new CSystemLog("m", "Пункт меню добавлен", $userbank->GetProperty("user") . " добавил пункт меню \"" . htmlspecialchars(sb_menu_strip_icon($names_link)) . "\".");
				} else {
					$db_error = $GLOBALS['db']->ErrorMsg();
					AddScriptWithReload(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Не удалось создать ссылку!" . (!empty($db_error) ? " (" . htmlspecialchars($db_error) . ")" : ""), "red", "", true)), sb_url('admin', array('c' => 'menu')));
				}
			}
		}
		
		sb_menu_ensure_group_column();
		$list_menus = $GLOBALS['db']->GetAll("SELECT * FROM ".DB_PREFIX."_menu ORDER BY `priority` DESC");
		$group_labels = sb_menu_group_choices();
		foreach ($list_menus as &$menu_row)
		{
			$menu_row['icon_class'] = sb_menu_extract_icon($menu_row['text']);
			$menu_row['text_plain'] = sb_menu_strip_icon($menu_row['text']);
			$forced = isset($menu_row['menu_group']) ? sb_menu_normalize_group($menu_row['menu_group']) : '';
			$resolved = sb_menu_resolve_group($menu_row);
			$menu_row['group_resolved'] = $resolved;
			$resolved_label = isset($group_labels[$resolved]) ? $group_labels[$resolved] : $resolved;
			$menu_row['group_label'] = ($forced === '') ? ('Авто → ' . $resolved_label) : $resolved_label;
		}
		unset($menu_row);
		$theme->assign('list', $list_menus);
		$theme->assign('menu_icon_picker', sb_menu_icon_picker_html(''));
		$theme->assign('menu_group_picker', sb_menu_group_picker_html(''));
		$theme->assign('test', $test);
		$theme->display('page_admin_menu.tpl');
	}
