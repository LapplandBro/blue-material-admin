<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}


if(!isset($_GET['id']))
{
	echo '<div class="parsec-note parsec-note-danger">Нет доступа.</div>';
	die();
}

if(!isset($_GET['type']) || ($_GET['type'] != 'web' && $_GET['type'] != 'srv' && $_GET['type'] != 'server'))
{
	echo '<div class="parsec-note parsec-note-danger">Неверный тип группы.</div>';
	die();
}

$_GET['id'] = (int)$_GET['id'];

$web_group = $GLOBALS['db']->GetRow("SELECT flags, name FROM ".DB_PREFIX."_groups WHERE gid = {$_GET['id']}");
$srv_group = $GLOBALS['db']->GetRow("SELECT flags, name, immunity FROM ".DB_PREFIX."_srvgroups WHERE id = {$_GET['id']}");


$web_flags = intval($web_group[0]);
$srv_flags = isset($srv_group[0]) ? $srv_group[0] : '';

$name = $userbank->GetProperty("user", $_GET['id']);

$type_label = 'группа';
if($_GET['type'] == 'web') $type_label = 'веб-группа';
elseif($_GET['type'] == 'srv') $type_label = 'серверная группа админов';
elseif($_GET['type'] == 'server') $type_label = 'группа серверов';
?>
<div id="admin-page-content">
<div class="card banlist-panel admin-form" id="add-group">
	<div class="card-header">
		<h2>Группа
			<small><?php echo htmlspecialchars($type_label); ?> · имя и права</small>
		</h2>
	</div>
	<div class="card-body card-padding p-b-0 form-horizontal" role="form">
		<input type="hidden" id="group_id" value="<?php echo $_GET['id']?>" />
		<div class="form-group m-b-5">
			<label for="groupname" class="col-sm-3 control-label">Имя</label>
			<div class="col-sm-9">
				<div class="fg-line">
					<input type="text" tabindex="1" class="form-control" id="groupname" name="groupname" placeholder="Имя группы" />
				</div>
				<div id="groupname.msg" style="color:#f44336;"></div>
			</div>
		</div>
	</div>
<?php if($_GET['type'] == "web")
{?>
	<div class="card-body card-padding p-t-0">
		<?php echo str_replace("{title}", "Веб-права", @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php")) ;?>
	</div>
<?php }elseif($_GET['type'] == "srv"){
	$permissions = str_replace("{title}", "Серверные права", @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php")) ;
?>
	<div class="card-body card-padding p-t-0">
		<?php echo $permissions; ?>
	</div>

	<?php
	$overrides_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE group_id = ?", array($_GET['id']));
	?>
	<div class="card-body card-padding">
		<h3 class="admin-hub-section-title">Переопределения</h3>
		<div class="parsec-note parsec-note-muted m-b-15">
			Пустое имя + сохранение = удалить строку.
			<a href="http://wiki.alliedmods.net/Adding_Groups_%28SourceMod%29" target="_blank" rel="noopener">Wiki</a>
		</div>
		<form action="" method="post" name="group_overrides_form">
		<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table admin-manage-table" id="overrides">
			<thead>
			<tr>
				<th width="22%">Тип</th>
				<th>Имя</th>
				<th width="22%">Доступ</th>
			</tr>
			</thead>
			<tbody>
<?php
	foreach($overrides_list as $override) {
?>
			<tr class="banlist-row admin-manage-row">
				<td>
					<select name="override_type[]" class="form-control">
						<option value="command" <?php echo $override['type']=="command"?"selected=\"selected\"":""; ?>>Команда</option>
						<option value="group"<?php echo $override['type']=="group"?"selected=\"selected\"":""; ?>>Группа</option>
					</select>
					<input type="hidden" name="override_id[]" value="<?php echo $override['id']; ?>" />
				</td>
				<td><input class="form-control" name="override_name[]" value="<?php echo htmlspecialchars($override['name']); ?>" /></td>
				<td>
					<select name="override_access[]" class="form-control">
						<option value="allow"<?php echo $override['access']=="allow"?"selected=\"selected\"":""; ?>>Разрешить</option>
						<option value="deny"<?php echo $override['access']=="deny"?"selected=\"selected\"":""; ?>>Запретить</option>
					</select>
				</td>
			</tr>
<?php } ?>
			<tr class="banlist-row admin-manage-row">
				<td>
					<select id="new_override_type" class="form-control">
						<option value="command">Команда</option>
						<option value="group">Группа</option>
					</select>
				</td>
				<td><input id="new_override_name" class="form-control" placeholder="Новое…" /></td>
				<td>
					<select id="new_override_access" class="form-control">
						<option value="allow">Разрешить</option>
						<option value="deny">Запретить</option>
					</select>
				</td>
			</tr>
			</tbody>
		</table>
		</div>
		</form>
	</div>
<?php } ?>
	<div class="card-body card-padding text-center admin-manage-footer">
		<button type="button" onclick="ProcessEditGroup('<?php echo $_GET['type']?>', $('groupname').value);" name="editgroup" class="btn bgm-blue btn-icon-text waves-effect" id="editgroup"><i class="zmdi zmdi-check-all"></i> Сохранить</button>
		&nbsp;
		<button type="button" onclick="window.location.href='index.php?p=admin&c=groups'" name="back" class="btn bgm-bluegray btn-icon-text waves-effect" id="back"><i class="zmdi zmdi-undo"></i> Назад</button>
	</div>
</div>
</div>

<script>
<?php if($_GET['type'] == "web" || $_GET['type'] == "server"){?>
		$('groupname').value = "<?php echo addslashes($web_group['name'])?>";
<?php }?>
<?php if(!$userbank->HasAccess(ADMIN_OWNER)) { ?>
	if($("wrootcheckbox")) {
		$("wrootcheckbox").setStyle('display', 'none');
	}
	if($("srootcheckbox")) {
		$("srootcheckbox").setStyle('display', 'none');
	}
<?php } ?>
<?php if($_GET['type'] == "web"){?>
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

<?php }elseif($_GET['type'] == "srv"){?>
$('groupname').value = "<?php echo addslashes($srv_group['name'])?>";
$('s14').checked = <?php echo strstr($srv_flags, SM_ROOT) ? "true" : "false"?>;
$('s1').checked = <?php echo strstr($srv_flags, SM_RESERVED_SLOT) ? "true" : "false"?>;
$('s23').checked = <?php echo strstr($srv_flags, SM_GENERIC) ? "true" : "false"?>;
$('s2').checked = <?php echo strstr($srv_flags, SM_KICK) ? "true" : "false"?>;
$('s3').checked = <?php echo strstr($srv_flags, SM_BAN) ? "true" : "false"?>;
$('s4').checked = <?php echo strstr($srv_flags, SM_UNBAN) ? "true" : "false"?>;
$('s5').checked = <?php echo strstr($srv_flags, SM_SLAY) ? "true" : "false"?>;
$('s6').checked = <?php echo strstr($srv_flags, SM_MAP) ? "true" : "false"?>;
$('s7').checked = <?php echo strstr($srv_flags, SM_CVAR) ? "true" : "false"?>;
$('s8').checked = <?php echo strstr($srv_flags, SM_CONFIG) ? "true" : "false"?>;
$('s9').checked = <?php echo strstr($srv_flags, SM_CHAT) ? "true" : "false"?>;
$('s10').checked = <?php echo strstr($srv_flags, SM_VOTE) ? "true" : "false"?>;
$('s11').checked = <?php echo strstr($srv_flags, SM_PASSWORD) ? "true" : "false"?>;
$('s12').checked = <?php echo strstr($srv_flags, SM_RCON) ? "true" : "false"?>;
$('s13').checked = <?php echo strstr($srv_flags, SM_CHEATS) ? "true" : "false"?>;

$('s17').checked = <?php echo strstr($srv_flags, SM_CUSTOM1) ? "true" : "false"?>;
$('s18').checked = <?php echo strstr($srv_flags, SM_CUSTOM2) ? "true" : "false"?>;
$('s19').checked = <?php echo strstr($srv_flags, SM_CUSTOM3) ? "true" : "false"?>;
$('s20').checked = <?php echo strstr($srv_flags, SM_CUSTOM4) ? "true" : "false"?>;
$('s21').checked = <?php echo strstr($srv_flags, SM_CUSTOM5) ? "true" : "false"?>;
$('s22').checked = <?php echo strstr($srv_flags, SM_CUSTOM6) ? "true" : "false"?>;

$('immunity').value = <?php echo $srv_group['immunity'] ? (int)$srv_group['immunity'] : "0"?>;
<?php }?>
</script>
