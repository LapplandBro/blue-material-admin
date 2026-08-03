{if not $permission_listadmin}
	<div class="parsec-note parsec-note-warn">Нет доступа к списку администраторов.</div>
{else}
	<button onclick="sbGo('{$btn_href}')" class="btn btn-float btn-danger m-btn" {$btn_helpa}><i class="zmdi {$btn_icon}"></i></button>

	<div class="card banlist-panel admin-manage admin-admins">
		<div class="card-header">
			<h2>{if not $btn_rem}Админы{else}Истёкшие админы{/if}
				<small>
					<span id="admincount">{$admin_count}</span> · клик по строке — карточка{$admin_nav_p}
				</small>
			</h2>
			<ul class="actions" id="banlist-nav">
				{$admin_nav}
			</ul>
			{if $btn_rem}<div class="admin-admins-extra">{$btn_rem}</div>{/if}
		</div>

		{php} require (TEMPLATES_PATH . "/admin.admins.search.php");{/php}

		<div class="table-responsive" id="banlist">
			<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
				<thead>
					<tr>
						<th>Имя</th>
						<th>Серверная группа</th>
						<th>Веб-группа</th>
						<th class="text-right" width="16%">Срок</th>
					</tr>
				</thead>
				<tbody>
					{foreach from="$admins" item="admin"}
					<tr class="opener banlist-row admin-manage-row">
						<td>
							<span class="admin-admins-name">{$admin.user}</span>
							<span class="parsec-chip parsec-chip-fp" data-toggle="tooltip" data-placement="right" title="Иммунитет">{$admin.immunity}</span>
						</td>
						<td>{$admin.server_group}</td>
						<td>{$admin.web_group}</td>
						<td class="text-right">{$admin.expired_text}</td>
					</tr>
					<tr class="banlist-detail-row">
						<td colspan="4" class="banlist-detail-cell">
							<div class="opener" style="visibility: visible; zoom: 1; opacity: 1; height: 449px; padding-top: 0px; border-top-style: none; padding-bottom: 0px; border-bottom-style: none; overflow: hidden;">
								<div class="p-20">
								<div class="card" id="profile-main">
									<div class="pm-overview c-overflow">
										<div class="pmo-pic">
											<div class="p-relative">
												<a href="#">
													<img src="{$admin.avatar}" alt="">
												</a>
												<a href="http://steamcommunity.com/profiles/{$admin.communityid_profile}" class="pmop-edit" target="_blank" rel="noopener">
													<i class="zmdi zmdi-steam"></i> <span class="hidden-xs">Steam</span>
												</a>
											</div>
										</div>
										<div class="pmo-block pmo-contact hidden-xs p-t-0">
											<div style="text-align: center;padding-bottom: 20px;"></div>
											<h2>Связь</h2>
											<ul>
												<li><i class="zmdi zmdi-steam" data-toggle="tooltip" data-placement="top" title="Steam"></i> {$admin.steam_id_amd}</li>
												<li><i class="zmdi zmdi-account-box-o" data-toggle="tooltip" data-placement="top" title="Discord"></i> {$admin.sk_profile}</li>
												<li><i class="zmdi zmdi-email"></i> {$admin.email_profile} (<a href="mailto:{$admin.email_profile}">написать</a>)</li>
												<li><i class="zmdi zmdi-vk"></i> {$admin.vk_profile}</li>
											</ul>
										</div>

										<div class="pmo-block hidden-xs p-t-0">
											<h2>Права</h2>
											<a class="btn btn-primary btn-block waves-effect" data-toggle="modal" href="#modalWider_srv{$admin.aid}">Серверные</a>
											<br><br>
											<a class="btn btn-primary btn-block waves-effect" data-toggle="modal" href="#modalWider_web{$admin.aid}">Веб</a>
										</div>

										<div class="modal fade" id="modalWider_srv{$admin.aid}" tabindex="-1" role="dialog" aria-hidden="true">
											<div class="modal-dialog modal-sm">
												<div class="modal-content">
													<div class="modal-header">
														<h4 class="modal-title">Серверные права</h4>
													</div>
													<div class="modal-body">
														{$admin.server_flag_string}
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-link" data-dismiss="modal">Закрыть</button>
													</div>
												</div>
											</div>
										</div>

										<div class="modal fade" id="modalWider_web{$admin.aid}" tabindex="-1" role="dialog" aria-hidden="true">
											<div class="modal-dialog modal-sm">
												<div class="modal-content">
													<div class="modal-header">
														<h4 class="modal-title">Веб-права</h4>
													</div>
													<div class="modal-body">
														{$admin.web_flag_string}
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-link" data-dismiss="modal">Закрыть</button>
													</div>
												</div>
											</div>
										</div>
									</div>

									<div class="pm-body clearfix" id="accordionRed-one" role="tabpanel">
										{if $permission_editadmin}
											<ul class="tab-nav tn-justified admin-admins-tabs">
												<li class="waves-effect"><a href="index.php?p=admin&amp;c=admins&amp;o=editdetails&amp;id={$admin.aid}">Детали</a></li>
												<li class="waves-effect"><a href="index.php?p=admin&amp;c=admins&amp;o=editpermissions&amp;id={$admin.aid}">Привилегии</a></li>
												<li class="waves-effect"><a href="index.php?p=admin&amp;c=admins&amp;o=editservers&amp;id={$admin.aid}">Сервер</a></li>
												<li class="waves-effect"><a href="index.php?p=admin&amp;c=admins&amp;o=editgroup&amp;id={$admin.aid}">Группа</a></li>
												{if $allow_warnings}
													<li class="waves-effect admin-admins-tab-warn"><a href="index.php?p=admin&amp;c=admins&amp;o=warnings&amp;id={$admin.aid}">Предупр. ({$admin.warnings}/{$maxWarnings})</a></li>
												{/if}
												{if $permission_deleteadmin}
													<li class="waves-effect admin-admins-tab-danger"><a href="#" onclick="{$admin.del_link_d}; return false;">Удалить</a></li>
												{/if}
											</ul>
										{/if}

										<div class="pmb-block p-t-30">
											<div class="pmbb-header">
												<h2><i class="zmdi zmdi-comment m-r-5"></i> Комментарий</h2>
											</div>
											<div class="pmbb-body p-l-30">
												<div class="pmbb-view">
													{$admin.comment_profile|escape}
												</div>
											</div>
										</div>

										<div class="pmb-block p-t-10">
											<div class="pmbb-header">
												<h2><i class="zmdi zmdi-hourglass-alt m-r-5"></i> Визит и срок</h2>
											</div>
											<div class="pmbb-body p-l-30">
												<div class="pmbb-view">
													<dl class="dl-horizontal">
														<dt>Доступ</dt>
														<dd>{$admin.expired_cv}</dd>
													</dl>
													<dl class="dl-horizontal">
														<dt>Последний визит</dt>
														<dd>{$admin.lastvisit}</dd>
													</dl>
												</div>
											</div>
										</div>

										<div class="pmb-block p-t-10">
											<div class="pmbb-header">
												<h2><i class="zmdi zmdi-fire m-r-5"></i> Баны</h2>
											</div>
											<div class="pmbb-body p-l-30">
												<div class="pmbb-view">
													<dl class="dl-horizontal">
														<dt>Без демо</dt>
														<dd>{$admin.bancount} <a href="./index.php?p=banlist&amp;advSearch={$admin.aid}&amp;advType=admin">найти</a></dd>
													</dl>
													<dl class="dl-horizontal">
														<dt>С демо</dt>
														<dd>{$admin.nodemocount} <a href="./index.php?p=banlist&amp;advSearch={$admin.aid}&amp;advType=nodemo">найти</a></dd>
													</dl>
												</div>
											</div>
										</div>

										<div class="pmb-block p-t-10">
											<div class="pmbb-header">
												<h2><i class="zmdi zmdi-volume-off m-r-5"></i> Муты / гаги</h2>
											</div>
											<div class="pmbb-body p-l-30">
												<div class="pmbb-view">
													<dl class="dl-horizontal">
														<dt>Всего</dt>
														<dd>{$admin.commscount} <a href="./index.php?p=commslist&amp;advSearch={$admin.aid}&amp;advType=admin">найти</a></dd>
													</dl>
												</div>
											</div>
										</div>

										<div class="pmb-block p-t-10 m-b-0">
											<div class="pmbb-header">
												<h2>{help_icon title="Поддержка" message="Можете добавить данного администратора в список авторов рефорка (категория «Администраторы»)." style="padding-top: 3px;"} Support-List</h2>
											</div>
											<div class="pmbb-body p-l-30">
												<div class="pmbb-view">
													<dl class="dl-horizontal">
														<dt>В список?</dt>
														<dd>
															<div class="toggle-switch p-b-5" data-ts-color="red">
																<input type="checkbox" id="add_support_{$admin.aid}" name="add_support_{$admin.aid}" TABINDEX=9 onclick="xajax_AddSupport({$admin.aid});" hidden="hidden" />
																<label for="add_support_{$admin.aid}" class="ts-helper checkbox-inline m-r-20" style="z-index:2;"></label>
															</div>
														</dd>
													</dl>
												</div>
											</div>
										</div>
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

	<script type="text/javascript">
		{foreach from=$checked_if item=kek}
			$("add_support_{$kek.kid}").checked = 1;
		{/foreach}
	</script>
	<script type="text/javascript">InitAccordion('tr.opener', 'div.opener', 'content');</script>
{/if}
