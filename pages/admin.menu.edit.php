<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
global $userbank, $theme;

	if(!$userbank->HasAccess(ADMIN_OWNER))
		CreateRedBox("Доступ запрещен!", "У вас нету доступных привилегий на просмотр данной страницы.");
	else {
	
		if(isset($_POST['Link']))
		{ 
			if ($_POST['Link'] == "edit"){
				sb_menu_ensure_group_column();
				$on_act = (isset($_POST['on_link']) && $_POST['on_link'] == "on" ? 1 : 0);
				$system = $GLOBALS['db']->GetRow("SELECT url,system FROM `" . DB_PREFIX . "_menu` WHERE `id` = " . (int) $_GET['id']);
				$menu_icon = isset($_POST['menu_icon']) ? $_POST['menu_icon'] : '';
				$menu_group = isset($_POST['menu_group']) ? sb_menu_normalize_group($_POST['menu_group']) : '';
				$names_link = sb_menu_compose_title(isset($_POST['names_link']) ? $_POST['names_link'] : '', $menu_icon);
				
				// system=1: URL не меняем. system=0: берём из формы (раньше ternary был перевёрнут — URL кастомных ссылок не сохранялся).
				$url_to_save = ((int)$system['system'] === 1)
					? $system['url']
					: (isset($_POST['url_link']) ? $_POST['url_link'] : $system['url']);
				$add = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_menu` SET `text` = ?, `description` = ?, `url` = ?, `system` = ?, `enabled` = ?, `priority` = ?, `newtab` = ?, `menu_group` = ? WHERE `id` = ?", array($names_link, $_POST['des_link'], $url_to_save, $system['system'], $on_act, $_POST['priora_link'], ((isset($_POST['onNewTab']) && $_POST['onNewTab']=="on")?"1":"0"), $menu_group, (int) $_GET['id']));

				// БАГ-ФИКС: результат Execute() раньше не проверялся - "Успешно сохранена" писалось
				// всегда, даже если UPDATE реально провалился. Теперь при ошибке показываем её текст.
				if($add) {
					$log = new CSystemLog("m", "Пункт меню изменён", $userbank->GetProperty("user") . " изменил пункт меню \"" . htmlspecialchars(sb_menu_strip_icon($names_link)) . "\" (id " . (int)$_GET['id'] . ").");
					PushScriptToExecuteAfterLoadPage(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Успех!", "Ссылка успешно сохранена!", "green", "", true)));
				} else {
					$db_error = $GLOBALS['db']->ErrorMsg();
					PushScriptToExecuteAfterLoadPage(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Не удалось сохранить ссылку!" . (!empty($db_error) ? " (" . htmlspecialchars($db_error) . ")" : ""), "red", "", true)));
				}
				FatalRefresh("index.php?p=admin&c=menu");
			}
		}
			
			
		sb_menu_ensure_group_column();
		$list_menu = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_menu WHERE id = '".(int)$_GET['id']."';");
		if (count($list_menu) > 0) {
			$icon_sel = sb_menu_extract_icon($list_menu['text']);
			$group_sel = isset($list_menu['menu_group']) ? $list_menu['menu_group'] : '';
			$theme->assign('text', sb_menu_strip_icon($list_menu['text']));
			$theme->assign('url', $list_menu['url']);
			$theme->assign('des', $list_menu['description']);
			$theme->assign('prior', $list_menu['priority']);
			$theme->assign('enab', $list_menu['enabled']);
			$theme->assign('system', ($list_menu['system']==1));
			$theme->assign('menu_icon_picker', sb_menu_icon_picker_html($icon_sel));
			$theme->assign('menu_group_picker', sb_menu_group_picker_html($group_sel));
			$theme->left_delimiter = "{";
			$theme->right_delimiter = "}";
			$theme->display('page_admin_menu_edit.tpl');
			$en = (int)$list_menu['enabled'];
			$nt = (int)$list_menu['newtab'];
			echo "<script>(function(){function setChk(id,on){if(typeof sbSetChecked==='function'){sbSetChecked(id,!!on);return;}var el=document.getElementById(id);if(el)el.checked=!!on;}setChk('on_link',{$en});setChk('onNewTab',{$nt});})();</script>";
		}
	}
