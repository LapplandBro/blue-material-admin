{foreach from="$games" item="game"}
{if $game.servers > 0}
{assign var="currgame" value=$game.mid}
<div class="card banlist-panel adminlist-panel">
	<div class="card-header">
		<h2>
			{game_icon file=$game.icon size=22 alt=$game.name}
			{$game.name}
			<small>Админы по серверам · {$game.servers} {if $game.servers == 1}сервер{elseif $game.servers < 5}сервера{else}серверов{/if}</small>
		</h2>
	</div>

	<div class="adminlist-servers">
		{foreach from="$server_list" item="server"}
			{if $server.modid == $currgame && $server.admincount > 0}
			{assign var="adminlist" value=$server.adminlist}
			<div class="adminlist-server">
				<div class="adminlist-toggle opener1" id="s_{$server.sid}" role="button" tabindex="0">
					<div class="adminlist-map">
						<img id="mapimg_{$server.sid}" src="images/maps/nomap.jpg" alt="" onerror="if(this.getAttribute('data-nomap'))return;this.setAttribute('data-nomap','1');this.src='images/maps/nomap.jpg';" />
					</div>
					<div class="adminlist-server-meta">
						<div class="adminlist-server-name" id="host_{$server.sid}">
							<span class="parsec-muted">{$server.ip}:{$server.port}</span>
						</div>
						<div class="adminlist-server-addr" id="sa{$server.sid}">{$server.ip}:{$server.port}</div>
					</div>
					<span class="parsec-badge parsec-badge-ok adminlist-count">{$server.admincount} адм.</span>
					<i class="zmdi zmdi-chevron-down adminlist-chevron"></i>
				</div>

				<div id="adminpanel_{$server.sid}" class="opener adminlist-body" style="visibility: hidden; zoom: 1; opacity: 0;">
					<div class="table-responsive">
						<table class="table table-striped parsec-table banlist-table adminlist-table">
							<thead>
								<tr>
									<th>Админ</th>
									<th width="18%">Группа</th>
									<th>О себе</th>
									<th width="16%">Связь</th>
								</tr>
							</thead>
							<tbody>
								{foreach from="$adminlist" item="admin"}
								<tr class="banlist-row">
									<td class="adminlist-td-user">
										<a class="adminlist-user" href="https://steamcommunity.com/profiles/{$admin.authid}" target="_blank" rel="noopener">
											<img class="adminlist-avatar" src="{$admin.avatar}" alt="" />
											<span class="adminlist-nick">{$admin.user|escape:'html'}</span>
										</a>
									</td>
									<td>
										{if $admin.srv_group != ""}
											<span class="parsec-chip parsec-chip-fp">{$admin.srv_group|escape:'html'}</span>
										{else}
											<span class="parsec-chip parsec-chip-esc">свои права</span>
										{/if}
									</td>
									<td class="adminlist-td-about">
										{if $admin.comment != ""}
											{$admin.comment|escape:'html'}
										{else}
											<span class="parsec-muted">—</span>
										{/if}
									</td>
									<td class="adminlist-td-contacts">
										{if $admin.discord != "" || $admin.vk != ""}
											{if $admin.discord != ""}
												<span class="adminlist-contact" title="Discord">
													DC {$admin.discord|escape:'html'}
												</span>
											{/if}
											{if $admin.vk != ""}
												<a class="adminlist-contact" href="https://vk.com/{$admin.vk|escape:'html'}" target="_blank" rel="noopener" title="ВКонтакте">
													VK {$admin.vk|escape:'html'}
												</a>
											{/if}
										{else}
											<span class="parsec-muted">—</span>
										{/if}
									</td>
								</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<script>xajax_ServerHostPlayers({$server.sid}, 'servers', '', '0', '-1', '{$IN_HOME}', 70);</script>
			{/if}
		{/foreach}
	</div>
</div>
{/if}
{/foreach}

{$server_script}

<script type="text/javascript">
	InitAccordion('div.adminlist-toggle', 'div.adminlist-body', 'content');
</script>
