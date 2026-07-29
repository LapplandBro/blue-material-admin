{if NOT $permission_protests}
	<div class="parsec-note parsec-note-warn">Нет доступа к протестам.</div>
{else}
<div id="protests">
	<div class="card banlist-panel admin-manage">
		<div class="card-header">
			<h2>Протесты
				<small>{$protest_count} · клик по строке — детали и комментарий</small>
			</h2>
			<ul class="actions" id="banlist-nav">
				{$protest_nav}
			</ul>
		</div>
		<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
			<thead>
				<tr>
					<th>Ник</th>
					<th>SteamID / IP</th>
					<th class="text-right" width="28%">Действия</th>
				</tr>
			</thead>
			<tbody>
			{foreach from="$protest_list" item="protest"}
				<tr id="pid_{$protest.pid}" class="opener2 banlist-row admin-manage-row">
					<td class="toggler"><a href="./index.php?p=banlist&amp;advSearch={$protest.authid}&amp;advType=steamid" title="Показать бан">{$protest.name}</a></td>
					<td>{if $protest.authid!=""}{$protest.authid}{else}{$protest.ip}{/if}</td>
					<td class="text-right">
						<span class="admin-actions">
							{if $permission_editban}
								<a class="admin-action admin-action--danger" href="#" onclick="RemoveProtest('{$protest.pid}', '{if $protest.authid!=""}{$protest.authid}{else}{$protest.ip}{/if}', '1'); return false;">Удалить</a>
							{/if}
							<a class="admin-action" href="index.php?p=admin&amp;c=bans&amp;o=email&amp;type=p&amp;id={$protest.pid}">Контакты</a>
						</span>
					</td>
				</tr>
				<tr id="pid_{$protest.pid}a" class="banlist-detail-row">
					<td colspan="3" class="banlist-detail-cell">
						<div class="opener2">
							<div class="admin-detail-head">
								<div>
									<div class="admin-detail-title">Детали протеста</div>
								</div>
								<div class="admin-detail-actions">{$protest.protaddcomment}</div>
							</div>
							<div class="card-body card-padding admin-detail-body">
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Игрок</label>
									<div class="col-sm-9">{$protest.name}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> SteamID</label>
									<div class="col-sm-9">
										{if $protest.authid == ""}
											<i>SteamID не предоставлен</i>
										{else}
											{$protest.authid}
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> IP адрес</label>
									<div class="col-sm-9">
										{if $protest.ip == 'none' || $protest.ip == ''}
											<i>IP адрес не предоставлен</i>
										{else}
											{$protest.ip}
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Прислан</label>
									<div class="col-sm-9">{$protest.date}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Истекает</label>
									<div class="col-sm-9">
										{if $protest.ends == 'never'}
											<i>Никогда.</i>
										{else}
											{$protest.ends}
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Причина бана</label>
									<div class="col-sm-9">{$protest.ban_reason}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Забанен админом</label>
									<div class="col-sm-9">{$protest.admin}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Забанен на сервере</label>
									<div class="col-sm-9">{$protest.server}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> IP протестующего</label>
									<div class="col-sm-9">{$protest.pip}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Дата протеста</label>
									<div class="col-sm-9">{$protest.datesubmitted}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Сообщение</label>
									<div class="col-sm-9">{$protest.reason}</div>
								</div>
								<hr />
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label"><i class="zmdi zmdi-comment-text text-left"></i> Комментарии</label>
									<div class="col-sm-9">
									{if $protest.commentdata != "None"}
										<ul class="tvc-lists p-l-0" style="list-style:none;">
										{foreach from=$protest.commentdata item=commenta}
											<li class="media{if $commenta.morecom} p-t-10 m-t-10{/if}" style="{if $commenta.morecom}border-top:1px solid #e2e2e2;{/if}">
												<div class="media-body">
													<strong class="d-block">
														{if !empty($commenta.comname)}
															{$commenta.comname|escape:'html'}
														{else}
															<i>Админ удалён</i>
														{/if}
													</strong>
													<small class="c-gray">{$commenta.added}{if $commenta.editcomlink != ""} &middot; {$commenta.editcomlink} {$commenta.delcomlink}{/if}</small>
													<div class="m-t-5" style="word-break:break-all;word-wrap:break-word;">{$commenta.commenttxt|escape:'html'|nl2br}</div>
													{if !empty($commenta.edittime)}
														<small class="c-gray">Изменено {$commenta.edittime} ({if !empty($commenta.editname)}{$commenta.editname}{else}<i>Админ удалён</i>{/if})</small>
													{/if}
												</div>
											</li>
										{/foreach}
										</ul>
									{else}
										<i>Комментариев нет.</i>
									{/if}
									</div>
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
</div>
<script>InitAccordion('tr.opener2', 'div.opener2', 'protests');</script>
{/if}
