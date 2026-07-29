<?php
// *************************************************************************
//  This file is part of SourceBans++.
// *************************************************************************

if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
global $userbank;

if(!isset($_GET['id']))
{
	echo '<div class="parsec-note parsec-note-danger">Нет доступа.</div>';
	PageDie();
}
$_GET['id'] = (int)$_GET['id'];
$admin = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_admins WHERE aid = ?", array($_GET['id']));


if(!$userbank->GetProperty("user", $_GET['id']))
{
	$log = new CSystemLog("e", "Получение данных администратора не удалось", "Не могу найти данные для администратора с идентификатором '".$_GET['id']."'");
	echo '<div class="parsec-note parsec-note-danger">Не удалось загрузить данные админа.</div>';
	PageDie();
}

$_GET['id'] = (int)$_GET['id'];
if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
{
	$log = new CSystemLog("w", "Попытка взлома", $userbank->GetProperty("user") . " пытался редактировать разрешения ".$userbank->GetProperty('user', $_GET['id'])." , не имея на это прав.");
	echo '<div class="parsec-note parsec-note-danger">Нет прав на изменение привилегий.</div>';
	PageDie();
}

$web_root = $userbank->HasAccess(ADMIN_OWNER, $_GET['id']);
$steam = trim($userbank->GetProperty("authid", $_GET['id']));
$web_flags = intval($userbank->GetProperty("extraflags", $_GET['id']));
$name = $userbank->GetProperty("user", $_GET['id']);
?>
<div id="admin-page-content">
<div class="card banlist-panel admin-form" id="add-group">
	<div class="card-header">
		<h2>Привилегии
			<small><?php echo htmlspecialchars($name); ?> · свои флаги вне групп</small>
		</h2>
	</div>
	<input type="hidden" id="admin_id" value="<?php echo $_GET['id']?>" />

	<div class="card-body card-padding">
		<?php echo str_replace("{title}", "Веб-права", file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php")) ;?>
	</div>
	<div class="card-body card-padding p-t-0">
		<?php echo str_replace("{title}", "Серверные права", file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php")) ;?>
	</div>

	<div class="card-body card-padding text-center admin-manage-footer">
		<button type="button" onclick="ProcessEditAdminPermissions();" class="btn bgm-blue btn-icon-text waves-effect" id="editadmingroup"><i class="zmdi zmdi-check-all"></i> Сохранить</button>
		&nbsp;
		<button type="button" onclick="window.location.href='index.php?p=admin&c=admins'" class="btn bgm-bluegray btn-icon-text waves-effect" id="back"><i class="zmdi zmdi-undo"></i> Назад</button>
	</div>
</div>

<script>
<?php if(!$userbank->HasAccess(ADMIN_OWNER)) { ?>
	if($("wrootcheckbox")) {
		$("wrootcheckbox").setStyle('display', 'none');
	}
	if($("srootcheckbox")) {
		$("srootcheckbox").setStyle('display', 'none');
	}
<?php } ?>
$('p2').checked = <?php echo check_flag($web_flags, ADMIN_OWNER) ? "true" : "false"?>;

$('p4').checked = <?php echo check_flag($web_flags, ADMIN_LIST_ADMINS) ? "true" : "false"?>;
$('p5').checked = <?php echo check_flag($web_flags, ADMIN_ADD_ADMINS) ? "true" : "false"?>;
$('p6').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_ADMINS) ? "true" : "false"?>;
$('p7').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_ADMINS) ? "true" : "false"?>;

$('p9').checked = <?php echo check_flag($web_flags, ADMIN_LIST_SERVERS) ? "true" : "false"?>;
$('p10').checked = <?php echo check_flag($web_flags, ADMIN_ADD_SERVER) ? "true" : "false"?>;
$('p11').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_SERVERS) ? "true" : "false"?>;
$('p12').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_SERVERS) ? "true" : "false"?>;

$('p14').checked = <?php echo check_flag($web_flags, ADMIN_ADD_BAN) ? "true" : "false"?>;
$('p16').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_OWN_BANS) ? "true" : "false"?>;
$('p17').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_GROUP_BANS) ? "true" : "false"?>;
$('p18').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_ALL_BANS) ? "true" : "false"?>;
$('p19').checked = <?php echo check_flag($web_flags, ADMIN_BAN_PROTESTS) ? "true" : "false"?>;
$('p20').checked = <?php echo check_flag($web_flags, ADMIN_BAN_SUBMISSIONS) ? "true" : "false"?>;
$('p33').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_BAN) ? "true" : "false"?>;
$('p32').checked = <?php echo check_flag($web_flags, ADMIN_UNBAN) ? "true" : "false"?>;
$('p34').checked = <?php echo check_flag($web_flags, ADMIN_BAN_IMPORT) ? "true" : "false"?>;
$('p38').checked = <?php echo check_flag($web_flags, ADMIN_UNBAN_OWN_BANS) ? "true" : "false"?>;
$('p39').checked = <?php echo check_flag($web_flags, ADMIN_UNBAN_GROUP_BANS) ? "true" : "false"?>;

$('p36').checked = <?php echo check_flag($web_flags, ADMIN_NOTIFY_SUB) ? "true" : "false"?>;
$('p37').checked = <?php echo check_flag($web_flags, ADMIN_NOTIFY_PROTEST) ? "true" : "false"?>;

$('p22').checked = <?php echo check_flag($web_flags, ADMIN_LIST_GROUPS) ? "true" : "false"?>;
$('p23').checked = <?php echo check_flag($web_flags, ADMIN_ADD_GROUP) ? "true" : "false"?>;
$('p24').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_GROUPS) ? "true" : "false"?>;
$('p25').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_GROUPS) ? "true" : "false"?>;

$('p26').checked = <?php echo check_flag($web_flags, ADMIN_WEB_SETTINGS) ? "true" : "false"?>;

$('p28').checked = <?php echo check_flag($web_flags, ADMIN_LIST_MODS) ? "true" : "false"?>;
$('p29').checked = <?php echo check_flag($web_flags, ADMIN_ADD_MODS) ? "true" : "false"?>;
$('p30').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_MODS) ? "true" : "false"?>;
$('p31').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_MODS) ? "true" : "false"?>;


$('s14').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_ROOT) ? "true" : "false"?>;
$('s1').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_RESERVED_SLOT) ? "true" : "false"?>;
$('s23').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_GENERIC) ? "true" : "false"?>;
$('s2').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_KICK) ? "true" : "false"?>;
$('s3').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_BAN) ? "true" : "false"?>;
$('s4').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_UNBAN) ? "true" : "false"?>;
$('s5').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_SLAY) ? "true" : "false"?>;
$('s6').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_MAP) ? "true" : "false"?>;
$('s7').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CVAR) ? "true" : "false"?>;
$('s8').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CONFIG) ? "true" : "false"?>;
$('s9').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CHAT) ? "true" : "false"?>;
$('s10').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_VOTE) ? "true" : "false"?>;
$('s11').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_PASSWORD) ? "true" : "false"?>;
$('s12').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_RCON) ? "true" : "false"?>;
$('s13').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CHEATS) ? "true" : "false"?>;

$('s17').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM1) ? "true" : "false"?>;
$('s18').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM2) ? "true" : "false"?>;
$('s19').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM3) ? "true" : "false"?>;
$('s20').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM4) ? "true" : "false"?>;
$('s21').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM5) ? "true" : "false"?>;
$('s22').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM6) ? "true" : "false"?>;

$('immunity').value = <?php echo $admin['immunity'] ? $admin['immunity'] : "0"?>;
</script>
</div>
