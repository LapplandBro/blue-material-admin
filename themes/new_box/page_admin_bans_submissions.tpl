{if NOT $permissions_submissions}
	<div class="parsec-note parsec-note-warn">Нет доступа к заявкам.</div>
{else}
<div class="card banlist-panel admin-manage">
	<div class="card-header">
		<h2>Заявки на бан
			<small>{$submission_count} · клик по строке — детали</small>
		</h2>
		<ul class="actions" id="banlist-nav">
			{$submission_nav}
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
		{foreach from="$submission_list" item="sub"}
			<tr id="sid_{$sub.subid}" class="opener3 banlist-row admin-manage-row" {if $sub.hostname == ""}onclick="xajax_ServerHostPlayers('{$sub.server}', 'id', 'sub{$sub.subid}');"{/if}>
				<td>{$sub.name}</td>
				<td>{if $sub.SteamId!=""}{$sub.SteamId}{else}{$sub.sip}{/if}</td>
				<td class="text-right">
					<span class="admin-actions">
						<a class="admin-action" href="#" onclick="xajax_SetupBan({$sub.subid});return false;">Забанить</a>
						{if $permissions_editsub}
							<a class="admin-action admin-action--danger" href="#" onclick='RemoveSubmission({$sub.subid}, {$sub.name_js}, "1"); return false;'>Удалить</a>
						{/if}
						<a class="admin-action" href="index.php?p=admin&amp;c=bans&amp;o=email&amp;type=s&amp;id={$sub.subid}">Контакты</a>
					</span>
				</td>
			</tr>
			<tr id="sid_{$sub.subid}a" class="banlist-detail-row">
				<td colspan="3" class="banlist-detail-cell">
					<div class="opener3">
						<div class="admin-detail-head">
							<div>
								<div class="admin-detail-title">Детали заявки</div>
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
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Добавлено</label>
								<div class="col-sm-9">{$sub.submitted}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> SteamID</label>
								<div class="col-sm-9">
									{if $sub.SteamId == ""}
										<i>SteamID не предоставлен</i>
									{else}
										{$sub.SteamId}
									{/if}
								</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> IP адрес</label>
								<div class="col-sm-9">
									{if $sub.sip == ""}
										<i>IP адрес не предоставлен</i>
									{else}
										{$sub.sip}
									{/if}
								</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Причина</label>
								<div class="col-sm-9">{$sub.reason}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Сервер</label>
								<div class="col-sm-9" id="sub{$sub.subid}">{if $sub.hostname == ""}<i>Получаем имя сервера...</i>{else}{$sub.hostname}{/if}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> МОД</label>
								<div class="col-sm-9">{$sub.mod}</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Имя заявителя</label>
								<div class="col-sm-9">
									{if $sub.subname == ""}
										<i>Имя не предоставлено</i>
									{else}
										{$sub.subname}
									{/if}
								</div>
							</div>
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-circle-o text-left"></i> IP заявителя</label>
								<div class="col-sm-9">{$sub.ip}</div>
							</div>
							<hr />
							<div class="form-group col-sm-12 m-b-5">
								<label class="col-sm-3 control-label"><i class="zmdi zmdi-comment-text text-left"></i> Комментарии</label>
								<div class="col-sm-9">
								{if $sub.commentdata != "None"}
									<ul class="tvc-lists p-l-0" style="list-style:none;">
									{foreach from=$sub.commentdata item=commenta}
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
<script>InitAccordion('tr.opener3', 'div.opener3', 'mainwrapper');</script>
{/if}
