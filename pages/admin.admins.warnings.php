<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
global $userbank, $theme;

// БАГ-ФИКС: раньше здесь не было проверки существования администратора вообще - при переходе
// по ссылке со старым/несуществующим ID (например, админ был удалён) страница молча показывала
// пустой список предупреждений и форму "Выдать предупреждение" для несуществующего ID, вместо
// понятного сообщения об ошибке (как это уже сделано в соседних admin.edit.*.php).
if(!isset($_GET['id']) || !$userbank->GetProperty("user", (int)$_GET['id']))
{
	CreateRedBox("Ошибка", "Администратор с указанным ID не найден.");
	PageDie();
}

$warnings = $GLOBALS['db']->GetAll("SELECT `id`, `reason`, `expires`, `user` AS `from` FROM `" . DB_PREFIX . "_warns` INNER JOIN `" . DB_PREFIX . "_admins` ON `" . DB_PREFIX . "_warns`.`afrom` = `" . DB_PREFIX . "_admins`.`aid` WHERE `arecipient` = " . (int) $_GET['id'] . ";");
if (!is_array($warnings))
	$warnings = array();
foreach ($warnings as &$warning) {
	$expires = (int) $warning['expires'];
	if ($expires > time()) {
		$warning['expires'] = "Через&nbsp;".round((($expires - time()) / 86400),0) . "&nbsp;дней&nbsp;(".date('До d.m.Y в <b>H:i</b>', $expires).")";
		$warning['expired'] = false;
	} else if ($warning['expires'] == -1) {
		$warning['expires'] = "Снят";
		$warning['expired'] = true;
	} else {
		$warning['expires'] = "Истёк";
		$warning['expired'] = true;
	}
}
$theme->assign('Warnings', $warnings);
$theme->assign('count', count($warnings));

$theme->assign('myId', $userbank->GetAid());
$theme->assign('thisId', (int) $_GET['id']);

echo '<div id="admin-page-content">';
echo '<div id="0" class="admin-pane is-on">';
$_f = sb_ui_v2_theme_fragment('admin_admins_warnings.twig');
if (is_string($_f) && $_f !== '') echo $_f;
echo '</div>';
echo '</div>';
