{if NOT $permission_listmods}
	<div class="parsec-note parsec-note-warn">Нет доступа к списку модов.</div>
{else}

<div class="card banlist-panel admin-manage">
	<div class="card-header">
		<h2>Моды
			<small>{$mod_count} шт. · игры, привязанные к серверам</small>
		</h2>
	</div>

	<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table admin-manage-table">
			<thead>
				<tr>
					<th>Название</th>
					<th width="22%">Папка</th>
					<th width="8%" class="text-center">Иконка</th>
					<th width="12%" class="text-center">Steam universe</th>
					{if $permission_editmods || $permission_deletemods}
					<th width="22%" class="text-right">Действия</th>
					{/if}
				</tr>
			</thead>
			<tbody>
				{foreach from="$mod_list" item="mod" name="gaben"}
				<tr id="mid_{$mod.mid}" class="banlist-row admin-manage-row">
					<td>{$mod.name|htmlspecialchars}</td>
					<td><code class="admin-manage-code">{$mod.modfolder|htmlspecialchars}</code></td>
					<td class="text-center banlist-td-mod">
						{game_icon file=$mod.icon size=18 alt=$mod.name}
					</td>
					<td class="text-center">{$mod.steam_universe|htmlspecialchars}</td>
					{if $permission_editmods || $permission_deletemods}
					<td class="text-right">
						<span class="admin-actions">
							{if $permission_editmods}
								<a class="admin-action" href="index.php?p=admin&amp;c=mods&amp;o=edit&amp;id={$mod.mid}">Изменить</a>
							{/if}
							{if $permission_deletemods}
								<a class="admin-action admin-action--danger" href="#" onclick="RemoveMod('{$mod.name|escape:'quotes'|htmlspecialchars}', '{$mod.mid}'); return false;">Удалить</a>
							{/if}
						</span>
					</td>
					{/if}
				</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</div>

{/if}
