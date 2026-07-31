{if NOT $permission_listgroups}
	<div class="parsec-note parsec-note-warn">Нет доступа к списку групп.</div>
{else}

<div class="card banlist-panel admin-manage admin-groups">
	<div class="card-header">
		<h2>Группы
			<small>Клик по строке — права и состав</small>
		</h2>
	</div>

	<div class="card-body card-padding admin-groups-body">

		<section class="admin-hub-section">
			<h3 class="admin-hub-section-title">Веб-группы · {$web_group_count}</h3>
			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
					<thead>
						<tr>
							<th>Группа</th>
							<th width="16%" class="text-center">Админов</th>
							<th width="28%" class="text-right">Действия</th>
						</tr>
					</thead>
					<tbody>
						{foreach from="$web_group_list" item="group" name="web_group"}
						<tr id="gid_{$group.gid}" class="opener banlist-row admin-manage-row">
							<td><span class="admin-admins-name">{$group.name}</span></td>
							<td class="text-center">{$web_admins[$smarty.foreach.web_group.index]}</td>
							<td class="text-right">
								<span class="admin-actions">
									{if $permission_editgroup}
										<a class="admin-action" href="index.php?p=admin&amp;c=groups&amp;o=edit&amp;type=web&amp;id={$group.gid}">Изменить</a>
									{/if}
									{if $permission_deletegroup}
										<a class="admin-action admin-action--danger" href="#" onclick="RemoveGroup({$group.gid}, '{$group.name}', 'web'); return false;">Удалить</a>
									{/if}
								</span>
							</td>
						</tr>
						<tr class="banlist-detail-row">
							<td colspan="3" class="banlist-detail-cell">
								<div class="opener">
									<div class="admin-group-detail">
										<div class="admin-group-detail-col">
											<div class="admin-group-detail-label">Права</div>
											<div class="admin-group-detail-body">{$group.permissions}</div>
										</div>
										<div class="admin-group-detail-col">
											<div class="admin-group-detail-label">Кто в группе</div>
											<ul class="admin-group-members">
												{foreach from=$web_admins_list[$smarty.foreach.web_group.index] item="web_admin"}
												<li>
													{if $permission_editadmin}<a href="#admin_w{$web_admin.aid}" data-toggle="modal">{/if}
														{$web_admin.user}
													{if $permission_editadmin}</a>{/if}
												</li>
												{if $permission_editadmin}
												<div class="modal fade" id="admin_w{$web_admin.aid}" tabindex="-1" role="dialog" aria-hidden="true">
													<div class="modal-dialog modal-sm">
														<div class="modal-content">
															<div class="modal-header">
																<h4 class="modal-title">{$web_admin.user}</h4>
															</div>
															<div class="modal-body">
																<p class="m-b-10"><button type="button" class="btn btn-link btn-block" data-dismiss="modal" onclick='sbGo("index.php?p=admin&amp;c=admins&amp;o=editgroup&amp;id={$web_admin.aid}");'>Изменить группы</button></p>
																<p class="m-b-10"><button type="button" class="btn btn-link btn-block" data-dismiss="modal" onclick='sbGo("index.php?p=admin&amp;c=admins&amp;o=editgroup&amp;id={$web_admin.aid}&amp;wg=");'>Исключить из группы</button></p>
															</div>
														</div>
													</div>
												</div>
												{/if}
												{/foreach}
											</ul>
										</div>
									</div>
								</div>
							</td>
						</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		</section>

		<section class="admin-hub-section">
			<h3 class="admin-hub-section-title">Серверные группы админов · {$server_admin_group_count}</h3>
			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
					<thead>
						<tr>
							<th>Группа</th>
							<th width="16%" class="text-center">Админов</th>
							<th width="28%" class="text-right">Действия</th>
						</tr>
					</thead>
					<tbody>
						{foreach from="$server_group_list" item="group" name="server_admin_group"}
						<tr id="gid_{$group.id}" class="opener banlist-row admin-manage-row">
							<td><span class="admin-admins-name">{$group.name}</span></td>
							<td class="text-center">{$server_admins[$smarty.foreach.server_admin_group.index]}</td>
							<td class="text-right">
								<span class="admin-actions">
									{if $permission_editgroup}
										<a class="admin-action" href="index.php?p=admin&amp;c=groups&amp;o=edit&amp;type=srv&amp;id={$group.id}">Изменить</a>
									{/if}
									{if $permission_deletegroup}
										<a class="admin-action admin-action--danger" href="#" onclick="RemoveGroup({$group.id}, '{$group.name}', 'srv'); return false;">Удалить</a>
									{/if}
								</span>
							</td>
						</tr>
						<tr class="banlist-detail-row">
							<td colspan="3" class="banlist-detail-cell">
								<div class="opener">
									<div class="admin-group-detail admin-group-detail--3">
										<div class="admin-group-detail-col">
											<div class="admin-group-detail-label">Права</div>
											<div class="admin-group-detail-body">{$group.permissions}</div>
										</div>
										<div class="admin-group-detail-col">
											<div class="admin-group-detail-label">Кто в группе</div>
											<ul class="admin-group-members">
												{foreach from=$server_admins_list[$smarty.foreach.server_admin_group.index] item="server_admin"}
												<li>
													{if $permission_editadmin}<a href="#admin_s{$server_admin.aid}" data-toggle="modal">{/if}
														{$server_admin.user}
													{if $permission_editadmin}</a>{/if}
												</li>
												{if $permission_editadmin}
												<div class="modal fade" id="admin_s{$server_admin.aid}" tabindex="-1" role="dialog" aria-hidden="true">
													<div class="modal-dialog modal-sm">
														<div class="modal-content">
															<div class="modal-header">
																<h4 class="modal-title">{$server_admin.user}</h4>
															</div>
															<div class="modal-body">
																<p class="m-b-10"><button type="button" class="btn btn-link btn-block" data-dismiss="modal" onclick='sbGo("index.php?p=admin&amp;c=admins&amp;o=editgroup&amp;id={$server_admin.aid}");'>Изменить группы</button></p>
																<p class="m-b-10"><button type="button" class="btn btn-link btn-block" data-dismiss="modal" onclick='sbGo("index.php?p=admin&amp;c=admins&amp;o=editgroup&amp;id={$server_admin.aid}&amp;sg=");'>Исключить из группы</button></p>
															</div>
														</div>
													</div>
												</div>
												{/if}
												{/foreach}
											</ul>
										</div>
										<div class="admin-group-detail-col">
											<div class="admin-group-detail-label">Переопределения</div>
											<ul class="admin-group-members">
												{if $server_overrides_list[$smarty.foreach.server_admin_group.index]|@count > 0}
													{foreach from=$server_overrides_list[$smarty.foreach.server_admin_group.index] item="override"}
													<li>
														{if $override.access == "allow"}
															<span class="parsec-chip parsec-chip-fp">разрешён</span>
														{else}
															<span class="parsec-chip parsec-chip-esc">запрещён</span>
														{/if}
														{if $override.type == "command"}команда{else}группа команд{/if}
														<strong>{$override.name|htmlspecialchars}</strong>
													</li>
													{/foreach}
												{else}
													<li><span class="parsec-muted">Нет</span></li>
												{/if}
											</ul>
										</div>
									</div>
								</div>
							</td>
						</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		</section>

		<section class="admin-hub-section">
			<h3 class="admin-hub-section-title">Группы серверов · {$server_group_count}</h3>
			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
					<thead>
						<tr>
							<th>Группа</th>
							<th width="16%" class="text-center">Серверов</th>
							<th width="28%" class="text-right">Действия</th>
						</tr>
					</thead>
					<tbody>
						{foreach from="$server_list" item="group" name="servers_group"}
						<tr id="gid_{$group.gid}" class="opener banlist-row admin-manage-row">
							<td><span class="admin-admins-name">{$group.name}</span></td>
							<td class="text-center">{$server_list[$smarty.foreach.servers_group.index].servers|@count}</td>
							<td class="text-right">
								<span class="admin-actions">
									{if $permission_editgroup}
										<a class="admin-action" href="index.php?p=admin&amp;c=groups&amp;o=edit&amp;type=server&amp;id={$group.gid}">Изменить</a>
									{/if}
									{if $permission_deletegroup}
										<a class="admin-action admin-action--danger" href="#" onclick="RemoveGroup({$group.gid}, '{$group.name}', 'server'); return false;">Удалить</a>
									{/if}
								</span>
							</td>
						</tr>
						<tr class="banlist-detail-row">
							<td colspan="3" class="banlist-detail-cell">
								<div class="opener">
									<div class="admin-group-detail">
										<div class="admin-group-detail-col">
											<div class="admin-group-detail-label">Серверы в группе</div>
											<ul class="admin-group-members">
												{if $server_list[$smarty.foreach.servers_group.index].servers|@count > 0}
													{foreach from=$server_list[$smarty.foreach.servers_group.index].servers item="server"}
													<li id="servername_{$server[0]}"><span class="parsec-muted">Загрузка…</span></li>
													<script type="text/javascript">xajax_ServerHostProperty({$server[0]}, "servername_{$server[0]}", "innerHTML", 100);</script>
													{/foreach}
												{else}
													<li><span class="parsec-muted">Пусто</span></li>
												{/if}
											</ul>
										</div>
									</div>
								</div>
							</td>
						</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		</section>

	</div>
</div>

<script type="text/javascript">InitAccordion('tr.opener', 'div.opener', 'content');</script>
{/if}
