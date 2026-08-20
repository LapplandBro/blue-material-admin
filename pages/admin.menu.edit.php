<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
global $userbank, $theme;

echo '<div id="admin-page-content">';
if(!$userbank->HasAccess(ADMIN_OWNER)) {
	CreateRedBox("Доступ запрещен!", "У вас нету доступных привилегий на просмотр данной страницы.");
} elseif (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
	CreateRedBox("Ошибка", "Пункт меню не указан.");
} else {
	$menuId = (int)$_GET['id'];
	$existingMenu = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_menu WHERE id = ?", array($menuId));
	if (!is_array($existingMenu) || empty($existingMenu)) {
		CreateRedBox("Ошибка", "Пункт меню не найден.");
	} else {
		if(isset($_POST['Link']) && $_POST['Link'] == "edit")
		{
			$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
			if(!function_exists('sb_csrf_validate') || !sb_csrf_validate($csrf))
			{
				CreateRedBox("Ошибка", "Неверный CSRF-токен. Обновите страницу и попробуйте снова.");
			} else {
				sb_menu_ensure_group_column();
				$on_act = (isset($_POST['on_link']) && $_POST['on_link'] == "on" ? 1 : 0);
				$system = $existingMenu;
				$menu_icon = isset($_POST['menu_icon']) ? $_POST['menu_icon'] : '';
				$menu_group = isset($_POST['menu_group']) ? sb_menu_normalize_group($_POST['menu_group']) : '';
				$names_link = sb_menu_compose_title(isset($_POST['names_link']) ? $_POST['names_link'] : '', $menu_icon);
				$des_link = isset($_POST['des_link']) ? $_POST['des_link'] : (isset($system['description']) ? $system['description'] : '');
				$priora_link = isset($_POST['priora_link']) ? $_POST['priora_link'] : (isset($system['priority']) ? $system['priority'] : 0);

				$url_to_save = ((int)$system['system'] === 1)
					? $system['url']
					: (isset($_POST['url_link']) ? $_POST['url_link'] : $system['url']);
				$add = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_menu` SET `text` = ?, `description` = ?, `url` = ?, `system` = ?, `enabled` = ?, `priority` = ?, `newtab` = ?, `menu_group` = ? WHERE `id` = ?", array($names_link, $des_link, $url_to_save, $system['system'], $on_act, $priora_link, ((isset($_POST['onNewTab']) && $_POST['onNewTab']=="on")?"1":"0"), $menu_group, $menuId));

				if($add) {
					$log = new CSystemLog("m", "Пункт меню изменён", $userbank->GetProperty("user") . " изменил пункт меню \"" . htmlspecialchars(sb_menu_strip_icon($names_link)) . "\" (id " . $menuId . ").");
					PushScriptToExecuteAfterLoadPage(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Успех!", "Ссылка успешно сохранена!", "green", "", true)));
				} else {
					$db_error = $GLOBALS['db']->ErrorMsg();
					PushScriptToExecuteAfterLoadPage(sprintf("setTimeout(function() { %s; }, 1350);", generateMsgBoxJS("Ошибка", "Не удалось сохранить ссылку!" . (!empty($db_error) ? " (" . htmlspecialchars($db_error) . ")" : ""), "red", "", true)));
				}
				FatalRefresh(sb_url('admin', array('c' => 'menu')));
			}
		}

		$list_menu = $existingMenu;
		$icon_sel = sb_menu_extract_icon(isset($list_menu['text']) ? $list_menu['text'] : '');
		$group_sel = isset($list_menu['menu_group']) ? $list_menu['menu_group'] : '';
		$theme->assign('menu_id', $menuId);
		$theme->assign('text', sb_menu_strip_icon(isset($list_menu['text']) ? $list_menu['text'] : ''));
		$theme->assign('url', isset($list_menu['url']) ? $list_menu['url'] : '');
		$theme->assign('des', isset($list_menu['description']) ? $list_menu['description'] : '');
		$theme->assign('prior', isset($list_menu['priority']) ? $list_menu['priority'] : '');
		$theme->assign('enab', isset($list_menu['enabled']) ? $list_menu['enabled'] : 0);
		$theme->assign('system', (isset($list_menu['system']) && $list_menu['system']==1));
		$theme->assign('menu_icon_picker', sb_menu_icon_picker_html($icon_sel));
		$theme->assign('menu_group_picker', sb_menu_group_picker_html($group_sel));
		$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
		sb_ui_v2_theme_fragment('admin_menu_edit.twig');
		$en = isset($list_menu['enabled']) ? (int)$list_menu['enabled'] : 0;
		$nt = isset($list_menu['newtab']) ? (int)$list_menu['newtab'] : 0;
		echo "<script>(function(){function setChk(id,on){if(typeof sbSetChecked==='function'){sbSetChecked(id,!!on);return;}var el=document.getElementById(id);if(el)el.checked=!!on;}setChk('on_link',{$en});setChk('onNewTab',{$nt});})();</script>";
	}
}
echo '</div>';
