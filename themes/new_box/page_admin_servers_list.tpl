{if NOT $permission_list}
	<div class="parsec-note parsec-note-warn">Нет доступа к списку серверов.</div>
{else}

<div class="card banlist-panel admin-manage">
	<div class="card-header">
		<h2>Серверы
			<small>{$server_count} шт. · статус подтягивается с игровых машин</small>
		</h2>
	</div>

	{if $permission_config}
	<div class="parsec-note parsec-note-muted m-b-0 admin-manage-hint">
		Конфиг SourceBans для серверов —
		<a href="index.php?p=admin&amp;c=servers&amp;o=dbsetup">открыть</a>
	</div>
	{/if}

	<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table admin-manage-table">
			<thead>
				<tr>
					<th width="6%" class="text-center">ID</th>
					<th>Сервер</th>
					<th width="12%" class="text-right">Игроки</th>
					<th width="8%" class="text-center">Мод</th>
					<th width="28%" class="text-right">Действия</th>
				</tr>
			</thead>
			<tbody>
				{foreach from="$server_list" item="server"}
				<script>xajax_ServerHostPlayers({$server.sid});</script>
				<tr id="sid_{$server.sid}" class="banlist-row admin-manage-row{if $server.enabled==0} admin-manage-row--off{/if}"{if $server.enabled==0} title="Отключён"{/if}>
					<td class="text-center">{$server.sid}</td>
					<td id="host_{$server.sid}"><span class="parsec-muted">Загрузка…</span></td>
					<td class="text-right" id="players_{$server.sid}"><span class="parsec-muted">—</span></td>
					<td class="text-center banlist-td-mod">
						{game_icon file=$server.icon size=18 alt="Мод"}
					</td>
					<td class="text-right">
						<span class="admin-actions">
							{if $server.rcon_access}
								<a class="admin-action" href="index.php?p=admin&amp;c=servers&amp;o=rcon&amp;id={$server.sid}">RCON</a>
							{/if}
							{if $permission_editserver}
								<a class="admin-action" href="index.php?p=admin&amp;c=servers&amp;o=edit&amp;id={$server.sid}">Изменить</a>
							{/if}
							{if $pemission_delserver}
								<a class="admin-action admin-action--danger" href="#" onclick="RemoveServer({$server.sid}, '{$server.ip}:{$server.port}'); return false;">Удалить</a>
							{/if}
						</span>
					</td>
				</tr>
				{/foreach}
			</tbody>
		</table>
	</div>

	{if $permission_addserver}
	<div class="card-body card-padding admin-manage-footer">
		<button type="button" class="btn bgm-bluegray btn-icon-text waves-effect" onclick="childWindow=open('pages/admin.uploadmapimg.php','upload','resizable=yes,scrollbars=yes,width=520,height=420');" id="upload">
			<i class="zmdi zmdi-upload"></i> Загрузить картинку карты
		</button>
		<div id="mapimg1.msg" class="contacts c-profile clearfix p-t-20 p-l-0" style="display:none;">
			<div class="col-md-3 col-sm-4 col-xs-6 p-l-0 p-r-0">
				<div class="c-item">
					<div href="#" class="ci-avatar text-center f-20 p-t-10">
						<i class="zmdi zmdi-balance-wallet zmdi-hc-fw"></i>
					</div>
					<div class="c-info">
						<strong id="mapimg.msg"></strong>
					</div>
					<div class="c-footer c-green f-700 text-center p-t-5 p-b-5">
						Успешно загружено
					</div>
				</div>
			</div>
		</div>
	</div>
	{/if}
</div>

{/if}
