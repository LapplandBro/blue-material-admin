{if $IN_SERVERS_PAGE}

<div class="card banlist-panel servers-panel">
	<div class="card-header">
		<h1>Серверы
			<small>{$server_list|@count} шт. · нажми строку, чтобы открыть игроков и карту</small>
		</h1>
	</div>

	{if $access_bans}
	<div class="parsec-note parsec-note-muted m-b-0 servers-hint">
		У админа рядом с ником есть метка — открой меню: кик, бан, мут, сообщение.
	</div>
	{/if}

	<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table servers-table">
			<thead>
				<tr>
					<th width="48" class="text-center">Игра</th>
					<th width="48" class="text-center">ОС</th>
					<th width="48" class="text-center">VAC</th>
					<th>Сервер</th>
					<th width="10%" class="text-right">Игроки</th>
					<th width="18%">Карта</th>
					<th width="4%"></th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$server_list item=server}
					<tr id="opener_{$server.sid}" class="opener banlist-row servers-row">
						<td class="text-center banlist-td-mod">
							{$server.icon_html}
						</td>
						<td class="text-center servers-td-ico" id="os_{$server.sid}"></td>
						<td class="text-center servers-td-ico" id="vac_{$server.sid}"></td>
						<td class="servers-td-host" id="host_{$server.sid}">
							<span class="parsec-muted">Загрузка…</span>
						</td>
						<td class="text-right servers-td-players" id="players_{$server.sid}">
							<span class="parsec-muted">—</span>
						</td>
						<td class="servers-td-map" id="map_{$server.sid}">
							<span class="parsec-muted">—</span>
						</td>
						<td class="text-center servers-td-chevron">
							<i class="zmdi zmdi-chevron-down"></i>
						</td>
					</tr>
					<tr class="banlist-detail-row">
						<td colspan="7" class="banlist-detail-cell">
							<div id="serverpanel_{$server.sid}" class="opener servers-detail" style="visibility: hidden; zoom: 1; opacity: 0; height: 0px;">
								<div id="serverwindow_{$server.sid}" class="servers-window">
									<div class="servers-detail-grid">
										<div class="servers-players-col">
											<div id="sinfo_{$server.sid}">
												<table class="table table-striped parsec-table servers-players-table" width="100%" border="0" id="playerlist_{$server.sid}"></table>
											</div>
											<div id="noplayer_{$server.sid}" class="servers-empty" style="display:none;">
												<p class="servers-empty-text">Сейчас на сервере никого нет.</p>
											</div>
										</div>
										<aside class="servers-aside">
											<div class="servers-map-frame">
												<img id="mapimg_{$server.sid}" src="images/maps/nomap.jpg" alt="Карта" loading="lazy" onerror="if(this.getAttribute('data-nomap'))return;this.setAttribute('data-nomap','1');this.src='images/maps/nomap.jpg';">
											</div>
											<div class="servers-addr">
												<span class="parsec-muted">Адрес</span>
												<code>{$server.ip}:{$server.port}</code>
											</div>
											<div class="servers-actions">
												<button type="button" onclick="document.location = 'steam://connect/{$server.ip}:{$server.port}'" class="btn btn-icon-text waves-effect server-action-btn server-action-btn--connect">
													<i class="zmdi zmdi-input-hdmi"></i> Подключиться
												</button>
												<button type="button" onclick="ShowBox('Обновление','Обновляем статус сервера…', 'blue', '', true, 1000);xajax_RefreshServer({$server.sid});" class="btn btn-icon-text waves-effect server-action-btn server-action-btn--refresh">
													<i class="zmdi zmdi-refresh-alt"></i> Обновить
												</button>
											</div>
										</aside>
									</div>
								</div>
							</div>
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</div>

{else}

<div class="card banlist-panel servers-panel servers-panel-home">
	<div class="card-header">
		<h2>Серверы
			<small>Клик — открыть подробности</small>
		</h2>
	</div>
	<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table servers-table">
			<thead>
				<tr>
					<th width="48" class="text-center">Игра</th>
					<th width="48" class="text-center hidden-xs">ОС</th>
					<th width="48" class="text-center hidden-xs">VAC</th>
					<th>Сервер</th>
					<th width="12%" class="text-right">Игроки</th>
					<th width="20%" class="hidden-xs">Карта</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$server_list item=server}
					<tr id="opener_{$server.sid}" class="banlist-row servers-row" style="cursor:pointer;" onclick="{$server.evOnClick}">
						<td class="text-center banlist-td-mod">
							{$server.icon_html}
						</td>
						<td class="text-center hidden-xs servers-td-ico" id="os_{$server.sid}"></td>
						<td class="text-center hidden-xs servers-td-ico" id="vac_{$server.sid}"></td>
						<td class="servers-td-host" id="host_{$server.sid}"><span class="parsec-muted">Загрузка…</span></td>
						<td class="text-right servers-td-players" id="players_{$server.sid}"><span class="parsec-muted">—</span></td>
						<td class="hidden-xs servers-td-map" id="map_{$server.sid}"><span class="parsec-muted">—</span></td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</div>

{/if}

{if $IN_SERVERS_PAGE}
<script type="text/javascript">
	InitAccordion('tr.opener', 'div.opener', 'content');
</script>
{/if}
