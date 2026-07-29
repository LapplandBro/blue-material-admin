{if NOT $permission_protests}
	<div class="parsec-note parsec-note-warn">Нет доступа к архиву протестов.</div>
{else}
<div id="protests">
	<div class="card banlist-panel admin-manage">
		<div class="card-header">
			<h2>Архив протестов
				<small>{$protest_count_archiv} · клик по строке — детали</small>
			</h2>
			<ul class="actions" id="banlist-nav">
				{$aprotest_nav}
			</ul>
		</div>
		<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
			<thead>
				<tr>
					<th>Ник</th>
					<th>SteamID / IP</th>
					<th class="text-right" width="32%">Действия</th>
				</tr>
			</thead>
			<tbody>
			{foreach from="$protest_list_archiv" item="protest"}
				<tr id="apid_{$protest.pid}" class="opener5 banlist-row admin-manage-row">
					<td class="toggler">
						{if $protest.archiv!=2}
							<a href="./index.php?p=banlist{if $protest.authid!=""}&amp;advSearch={$protest.authid}&amp;advType=steamid{else}&amp;advSearch={$protest.ip}&amp;advType=ip{/if}" title="Показать бан">{$protest.name}</a>
						{else}
							<span class="parsec-muted">бан удалён</span>
						{/if}
					</td>
					<td>{if $protest.authid!=""}{$protest.authid}{else}{$protest.ip}{/if}</td>
					<td class="text-right">
						<span class="admin-actions">
							{if $permission_editban}
								<a class="admin-action" href="#" onclick="RemoveProtest('{$protest.pid}', '{if $protest.authid!=""}{$protest.authid}{else}{$protest.ip}{/if}', '2'); return false;">Восстановить</a>
								<a class="admin-action admin-action--danger" href="#" onclick="RemoveProtest('{$protest.pid}', '{if $protest.authid!=""}{$protest.authid}{else}{$protest.ip}{/if}', '0'); return false;">Удалить</a>
							{/if}
							<a class="admin-action" href="index.php?p=admin&amp;c=bans&amp;o=email&amp;type=p&amp;id={$protest.pid}">Контакты</a>
						</span>
					</td>
				</tr>
				<tr id="apid_{$protest.pid}a" class="banlist-detail-row">
					<td colspan="3" class="banlist-detail-cell">
						<div class="opener5">
							<div class="admin-detail-head">
								<div>
									<div class="admin-detail-title">Детали протеста</div>
									<div class="admin-detail-meta">В архиве: {$protest.archive}</div>
								</div>
								<div class="admin-detail-actions">{$protest.protaddcomment}</div>
							</div>
							<div class="card-body card-padding admin-detail-body">
								{if $protest.archiv!=2}
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Игрок</label>
									<div class="col-sm-9">{$protest.name}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">SteamID</label>
									<div class="col-sm-9">
										{if $protest.authid == ""}
											<span class="parsec-muted">не указан</span>
										{else}
											{$protest.authid}
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">IP</label>
									<div class="col-sm-9">
										{if $protest.ip == 'none' || $protest.ip == ''}
											<span class="parsec-muted">не указан</span>
										{else}
											{$protest.ip}
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Бан добавлен</label>
									<div class="col-sm-9">{$protest.date}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Истекает</label>
									<div class="col-sm-9">
										{if $protest.ends == 'never'}
											<span class="parsec-muted">никогда</span>
										{else}
											{$protest.ends}
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Причина бана</label>
									<div class="col-sm-9">{$protest.ban_reason}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Админ</label>
									<div class="col-sm-9">{$protest.admin}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Сервер</label>
									<div class="col-sm-9">{$protest.server}</div>
								</div>
								{/if}
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">В архив отправил</label>
									<div class="col-sm-9">
										{if !empty($protest.archivedby)}
											{$protest.archivedby}
										{else}
											<span class="parsec-muted">админ удалён</span>
										{/if}
									</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">IP протестующего</label>
									<div class="col-sm-9">{$protest.pip}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Дата протеста</label>
									<div class="col-sm-9">{$protest.datesubmitted}</div>
								</div>
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Сообщение</label>
									<div class="col-sm-9">{$protest.reason}</div>
								</div>
								<hr />
								<div class="form-group col-sm-12 m-b-5">
									<label class="col-sm-3 control-label">Комментарии</label>
									<div class="col-sm-9">
									{if $protest.commentdata != "None"}
										<ul class="tvc-lists p-l-0" style="list-style:none;">
										{foreach from=$protest.commentdata item=commenta}
											<li class="media{if $commenta.morecom} p-t-10 m-t-10{/if}">
												<div class="media-body">
													<strong class="d-block">
														{if !empty($commenta.comname)}
															{$commenta.comname|escape:'html'}
														{else}
															<span class="parsec-muted">админ удалён</span>
														{/if}
													</strong>
													<small class="c-gray">{$commenta.added}{if $commenta.editcomlink != ""} · {$commenta.editcomlink} {$commenta.delcomlink}{/if}</small>
													<div class="m-t-5" style="word-break:break-all;word-wrap:break-word;">{$commenta.commenttxt|escape:'html'|nl2br}</div>
													{if !empty($commenta.edittime)}
														<small class="c-gray">Изменено {$commenta.edittime} ({if !empty($commenta.editname)}{$commenta.editname}{else}<span class="parsec-muted">админ удалён</span>{/if})</small>
													{/if}
												</div>
											</li>
										{/foreach}
										</ul>
									{else}
										<span class="parsec-muted">Комментариев нет</span>
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
<script>InitAccordion('tr.opener5', 'div.opener5', 'protests');</script>
{/if}
