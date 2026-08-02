{if NOT $permission_addadmin}
	<div class="parsec-note parsec-note-warn">Нет доступа к переопределениям.</div>
{else}
	{if $overrides_error != ""}
		<script type="text/javascript">ShowBox("Ошибка", "{$overrides_error}", "red");</script>
	{/if}
	{if $overrides_save_success}
		<script type="text/javascript">ShowBox("Переопределения обновлены", "Изменения успешно сохранены.", "green", "index.php?p=admin&c=admins");</script>
	{/if}

	<form action="" method="post">
		<input type="hidden" name="sb_csrf" value="{$sb_csrf}" />
		<div class="card banlist-panel admin-manage">
			<div class="card-header">
				<h2>Переопределения
					<small>Флаги команд SourceMod без правки плагинов · пустое поле = удалить</small>
				</h2>
			</div>

			<div class="parsec-note parsec-note-muted m-b-0 admin-manage-hint">
				Справка —
				<a href="https://wiki.alliedmods.net/Ru:Overriding_Command_Access_(SourceMod)" target="_blank" rel="noopener">AlliedModders Wiki</a>
			</div>

			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table" id="overrides">
					<thead>
						<tr>
							<th width="22%">Тип</th>
							<th>Название</th>
							<th width="28%">Флаги</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$overrides_list item=override}
						<tr class="banlist-row admin-manage-row">
							<td>
								<select name="override_type[]" class="form-control">
									<option{if $override.type == "command"} selected="selected"{/if} value="command">Команда</option>
									<option{if $override.type == "group"} selected="selected"{/if} value="group">Группа</option>
								</select>
								<input type="hidden" name="override_id[]" value="{$override.id}" />
							</td>
							<td><input class="form-control" name="override_name[]" value="{$override.name|htmlspecialchars}" /></td>
							<td><input class="form-control" name="override_flags[]" value="{$override.flags|htmlspecialchars}" /></td>
						</tr>
						{/foreach}
						<tr class="banlist-row admin-manage-row">
							<td>
								<select class="form-control" name="new_override_type">
									<option value="command">Команда</option>
									<option value="group">Группа</option>
								</select>
							</td>
							<td><input class="form-control" name="new_override_name" placeholder="Новое…" /></td>
							<td><input class="form-control" name="new_override_flags" placeholder="Флаги" /></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="card-body card-padding text-center admin-manage-footer">
				{sb_button text="Сохранить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="oversave" submit=true}
				&nbsp;
				{sb_button text="Назад" onclick="sbAdminBack(0, 'admin/admins')" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="oback"}
			</div>
		</div>
	</form>
{/if}
