{if NOT $permissions_submissions}
	<div class="parsec-note parsec-note-warn">Нет доступа к архиву заявок.</div>
{else}
<div class="card banlist-panel admin-manage">
	<div class="card-header">
		<h2>Архив заявок
			<small>{$submission_count_archiv} · клик по строке — детали</small>
		</h2>
		<ul class="actions" id="banlist-nav">
			{$asubmission_nav}
		</ul>
	</div>
	<div class="table-responsive">
	<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
		<thead>
			<tr>
				<th>Ник</th>
				<th>SteamID / IP</th>
				<th class="text-right" width="36%">Действия</th>
			</tr>
		</thead>
		<tbody>
		{foreach from="$submission_list_archiv" item="sub"}
			<tr id="asid_{$sub.subid}" class="opener4 banlist-row admin-manage-row" {if $sub.hostname == ""}onclick="xajax_ServerHostPlayers('{$sub.server}', 'id', 'suba{$sub.subid}');"{/if}>
				<td>{$sub.name}</td>
				<td>{if $sub.SteamId!=""}{$sub.SteamId}{else}{$sub.sip}{/if}</td>
				<td class="text-right">
					<span class="admin-actions">
						{if $sub.archiv != "2" and $sub.archiv != "3"}
							<a class="admin-action" href="#" onclick="xajax_SetupBan({$sub.subid}); return false;">Забанить</a>
							{if $permissions_editsub}
								<a class="admin-action" href="#" onclick="RemoveSubmission({$sub.subid}, {$sub.name_js}, '2'); return false;">Восстановить</a>
							{/if}
						{/if}
						{if $permissions_editsub}
							<a class="admin-action admin-action--danger" href="#" onclick="RemoveSubmission({$sub.subid}, {$sub.name_js}, '0'); return false;">Удалить</a>
						{/if}
						<a class="admin-action" href="index.php?p=admin&amp;c=bans&amp;o=email&amp;type=s&amp;id={$sub.subid}">Контакты</a>
					</span>
				</td>
			</tr>
			<tr id="asid_{$sub.subid}a" class="banlist-detail-row">
				<td colspan="3" class="banlist-detail-cell">
					<div class="opener4">
						<div class="admin-detail-head">
							<div>
								<div class="admin-detail-title">Детали заявки</div>
								<div class="admin-detail-meta">В архиве: {$sub.archive}</div>
							</div>
							<div class="admin-detail-actions">
								<span>{$sub.demo}</span>
								<span>{$sub.subaddcomment}</span>
							</div>
						</div>
						<div class="card-body card-padding admin-detail-body">
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Игрок</label>
								<div class="col-sm-9">{$sub.name}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Добавлено</label>
								<div class="col-sm-9">{$sub.submitted}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">SteamID</label>
								<div class="col-sm-9">
									{if $sub.SteamId == ""}
										<span class="parsec-muted">не указан</span>
									{else}
										{$sub.SteamId}
									{/if}
								</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">IP</label>
								<div class="col-sm-9">
									{if $sub.sip == ""}
										<span class="parsec-muted">не указан</span>
									{else}
										{$sub.sip}
									{/if}
								</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Причина</label>
								<div class="col-sm-9">{$sub.reason}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Сервер</label>
								<div class="col-sm-9" id="suba{$sub.subid}">{if $sub.hostname == ""}<span class="parsec-muted">Загрузка…</span>{else}{$sub.hostname}{/if}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Мод</label>
								<div class="col-sm-9">{$sub.mod}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Заявитель</label>
								<div class="col-sm-9">
									{if $sub.subname == ""}
										<span class="parsec-muted">не указан</span>
									{else}
										{$sub.subname}
									{/if}
								</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">IP заявителя</label>
								<div class="col-sm-9">{$sub.ip}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">В архив отправил</label>
								<div class="col-sm-9">
									{if !empty($sub.archivedby)}
										{$sub.archivedby}
									{else}
										<span class="parsec-muted">админ удалён</span>
									{/if}
								</div>
							</div>
							<hr />
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label">Комментарии</label>
								<div class="col-sm-9">
								{if $sub.commentdata != "None"}
									<ul class="tvc-lists p-l-0" style="list-style:none;">
									{foreach from=$sub.commentdata item=commenta}
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
<script>InitAccordion('tr.opener4', 'div.opener4', 'mainwrapper');</script>
{/if}
