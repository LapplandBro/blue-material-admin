{if $comment}
<!--Код Комментариев-->
<div class="row">
	<div class="card">
		<div class="card-header">
			<h2>{$commenttype} Комментарий</h2>
		</div>
		<div class="tv-comments">
			<ul class="tvc-lists">
				{foreach from="$othercomments" item="com"}
					<li class="media">
						<a href="#" class="tvh-user pull-left">
							<img class="img-responsive" style="width: 46px;height: 46px;border-radius: 50%;" src="themes/new_box/img/profile-pics/1.jpg" alt="">
						</a>
						<div class="media-body">
						<strong class="d-block">{$com.comname}</strong>
						<small class="c-gray">{$com.added} {if $com.editname != ''}last edit {$com.edittime} by {$com.editname}{/if}</small>

						<div class="m-t-10">{$com.commenttxt|escape:'html'|nl2br}</div>

						</div>
					</li>
				{/foreach}
				<li class="p-20">
					<div class="fg-line">
						<textarea class="form-control auto-size" rows="5" placeholder="Ваш комментарий...." id="commenttext" name="commenttext">{$commenttext|escape:'html'}</textarea>
						<div id="commenttext.msg" class="badentry"></div>
					</div>
					<input type="hidden" name="bid" id="bid" value="{$comment}">
					<input type="hidden" name="ctype" id="ctype" value="{$ctype}">
					{if $cid != ""}
						<input type="hidden" name="cid" id="cid" value="{$cid}">
					{else}
						<input type="hidden" name="cid" id="cid" value="-1">
					{/if}
					<input type="hidden" name="page" id="page" value="{$page}">
					{sb_button text="$commenttype Комментарий" onclick="ProcessComment();" class="m-t-15 btn-primary btn-sm" id="acom" submit=false}&nbsp;
					{sb_button text="Назад" onclick="window.location.href='banlist'" class="m-t-15 btn btn-sm" id="aback"}
				</li>
			</ul>
		</div>
    </div>
</div>
<!--Код Комментариев-->
{else}
<div class="card banlist-panel">
	<div class="card-header">
		<h1>Список банов
			<small>
				Всего {$total_bans}{$ban_nav_p}
			</small>
		</h1>
		
		<div class="actions" id="banlist-nav">
			{$ban_nav}
		</div>
	</div>
	
	{if $hidetext_darf == '0'}
	<div class="parsec-note parsec-note-muted m-b-0" id="bans_hidden">Показаны только активные баны.</div>
	{/if}
	<div class="alert" role="alert" id="tickswitchlink" style="display:none;"></div>
	
	<div class="table-responsive">
	<table class="table table-striped parsec-table banlist-table">
		<thead>
			<tr>
				{if $view_bans}
					<th width="1%" title="Выбрать все" name="tickswitch" id="tickswitch" onclick="TickSelectAll()"></th>
				{/if}
				<th width="5%" class="text-center">Игра</th>
				<th width="12%">Дата</th>
				<th>Игрок</th>
				{if $view_recidivism}
					<th width="14%">Нарушения</th>
				{/if}
				{if !$hideadminname}
					<th width="14%">Админ</th>
				{/if}
				<th width="16%">Срок</th>  
			</tr>
		</thead>
		<tbody>
			{foreach from=$ban_list item=ban name=banlist}
				<tr class="opener banlist-row banlist-row-{$ban.status_kind}" {if $ban.server_id != 0}onclick="xajax_ServerHostPlayers({$ban.server_id}, {$ban.ban_id});"{/if}>
					{if $view_bans}
						<td class="banlist-td-check">
							<label class="checkbox checkbox-inline m-r-20" for="chkb_{$smarty.foreach.banlist.index}" onclick="event.cancelBubble = true;">
                                <input type="checkbox" name="chkb_{$smarty.foreach.banlist.index}" id="chkb_{$smarty.foreach.banlist.index}" value="{$ban.ban_id}" hidden="hidden" />
                                <i class="input-helper"></i>
                            </label>
						</td>
					{/if}
					<td class="text-center banlist-td-mod">{$ban.mod_icon}</td>
					<td class="banlist-td-date">{$ban.ban_date_info}</td>
					<td class="banlist-td-player">
						<div class="banlist-player-main">
							{if not $nocountryshow}<span class="banlist-flag">{$ban.country_icon}</span>{/if}
							{if empty($ban.player)}
								<span class="parsec-muted">имя не указано</span>
							{else}
								<strong>{$ban.player|escape:'html'|stripslashes}</strong>
							{/if}
							{if $ban.steam_profile}
								<a class="banlist-meta-ico" href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Профиль Steam" onclick="event.cancelBubble=true;"><i class="zmdi zmdi-steam"></i></a>
							{/if}
							{if $ban.demo_available}<span class="banlist-meta-ico" title="Есть демо"><i class="zmdi zmdi-videocam"></i></span>{/if}
							{if $view_comments && $ban.commentdata != "Нет" && $ban.commentdata|@count > 0}
								<span class="banlist-meta-ico" title="Комментарии">{$ban.commentdata|@count} <i class="zmdi zmdi-comment-text"></i></span>
							{/if}
						</div>
					</td>
					{if $view_recidivism}
						<td class="banlist-td-recid" onclick="event.cancelBubble=true;">
							{if $ban.recid_url}
								<a class="banlist-recid-chip" href="{$ban.recid_url}" title="Карточка рецидива">{$ban.recid_display|escape:'html'}</a>
							{elseif $ban.recid_display}
								<span class="banlist-recid-chip banlist-recid-mute">{$ban.recid_display|escape:'html'}</span>
							{else}
								<span class="parsec-muted">—</span>
							{/if}
						</td>
					{/if}
					{if !$hideadminname}
						<td class="banlist-td-admin">
							{if !empty($ban.admin)}
								{$ban.admin|escape:'html'}
							{else}
								<span class="parsec-muted">админ снят</span>
							{/if}
						</td>
					{/if}
					<td class="banlist-td-length">
						{if $ban.status_kind == 'expired'}
							<span class="parsec-badge parsec-badge-muted">Истёк</span>
						{elseif $ban.status_kind == 'deleted'}
							<span class="parsec-badge parsec-badge-muted">Удалён</span>
						{elseif $ban.status_kind == 'unbanned'}
							<span class="parsec-badge parsec-badge-ok">Разбанен</span>
						{elseif $ban.status_kind == 'perm'}
							<span class="parsec-badge parsec-badge-danger">Навсегда</span>
						{else}
							<span class="parsec-badge parsec-badge-warn">{$ban.banlength|escape:'html'}</span>
						{/if}
					</td>
				</tr>
				<!-- ###############[ Start Sliding Panel ]################## -->
				<tr class="banlist-detail-row">
					<td colspan="9" class="banlist-detail-cell">
						<div class="opener"> 
								<div class="card-header banlist-detail-head">
									<h2>Карточка бана</h2>

									<ul class="actions actions-alt">
										<li class="dropdown">
											<a href="#" data-toggle="dropdown" aria-expanded="false">
												<i class="zmdi zmdi-more-vert"></i>
											</a>

											<ul class="dropdown-menu dropdown-menu-right">
												{if $view_bans}
													{if $ban.unbanned && $ban.reban_link != false}
														<li>{$ban.reban_link}</li>
													{/if}
														<li>{$ban.blockcomm_link}</li>
													{if $ban.recidivism_link}
														<li>{$ban.recidivism_link}</li>
													{/if}
													{if $ban.demo_available}
														<li>{$ban.demo_link}</li>
													{/if}
													<li>{$ban.addcomment}</li>
													{if $ban.type == 0}
														{if $groupban}
															<li>{$ban.groups_link}</li>
														{/if}
														{if $friendsban}
															<li>{$ban.friend_ban_link}</li>
														{/if}
													{/if}
													{if ($ban.view_edit && !$ban.unbanned)} 
														<li>{$ban.edit_link}</li>
													{/if}
													{if ($ban.unbanned == false && $ban.view_unban)}
														<li>{$ban.unban_link}</li>
													{/if}
													{if $ban.view_delete}
														<li>{$ban.delete_link}</li>
													{/if}
												{else}
													<li>{$ban.demo_link}</li>
												{/if}
											</ul>
										</li>
									</ul>
								</div>
								<div class="card-body card-padding banlist-detail-body">
									<div class="ban-card">
										<div class="ban-card-main">
											<div class="ban-card-row">
												<span class="ban-card-label">Игрок</span>
												<span class="ban-card-value">
													{if empty($ban.player)}
														<span class="parsec-muted">имя не указано</span>
													{else}
														{$ban.player|escape:'html'|stripslashes}
													{/if}
													{if $ban.steam_profile}
														· <a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer">Профиль Steam</a>
													{/if}
												</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Steam ID</span>
												<span class="ban-card-value ban-card-mono">
													{if empty($ban.steamid)}
														<span class="parsec-muted">не указан</span>
													{elseif $ban.steam_profile}
														<a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Открыть профиль Steam">{$ban.steamid}</a>
													{else}
														{$ban.steamid}
													{/if}
												</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Steam3 ID</span>
												<span class="ban-card-value ban-card-mono">
													{if empty($ban.steamid)}
														<span class="parsec-muted">не указан</span>
													{elseif $ban.steam_profile}
														<a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Открыть профиль Steam">{$ban.steamid3}</a>
													{else}
														{$ban.steamid3}
													{/if}
												</span>
											</div>
											{if $ban.type == 0}
											<div class="ban-card-row">
												<span class="ban-card-label">Steam Community</span>
												<span class="ban-card-value ban-card-mono">
													{if $ban.steam_profile}
														<a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Открыть профиль Steam">{$ban.communityid}</a>
														{if $ban.steam_vanity}
															· <a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer">/id/{$ban.steam_vanity|escape}</a>
														{/if}
													{else}
														{$ban.communityid}
													{/if}
												</span>
											</div>
											{/if}
											{if !$hideplayerips}
												{if $ban.ip != "none"}
												<div class="ban-card-row">
													<span class="ban-card-label">IP адрес</span>
													<span class="ban-card-value ban-card-mono">{$ban.ip}</span>
												</div>
												{/if}
											{/if}
											<div class="ban-card-row">
												<span class="ban-card-label">Был выдан</span>
												<span class="ban-card-value">{$ban.ban_date}</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Длительность</span>
												<span class="ban-card-value">
													{if $ban.ub_reason}<del>{$ban.banlength}</del> {$ban.ub_reason}{else}{$ban.banlength}{/if}
												</span>
											</div>
											{if $ban.unbanned}
											<div class="ban-card-row">
												<span class="ban-card-label">Причина разбана</span>
												<span class="ban-card-value">
													{if $ban.ureason == ""}
														<span class="parsec-muted">не указана</span>
													{else}
														{$ban.ureason}
													{/if}
												</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Разбанен админом</span>
												<span class="ban-card-value">
													{if !empty($ban.removedby)}
														{$ban.removedby|escape:'html'}
													{else}
														<span class="parsec-muted">админ удалён</span>
													{/if}
												</span>
											</div>
											{/if}
											<div class="ban-card-row">
												<span class="ban-card-label">Будет снят</span>
												<span class="ban-card-value">
													{if $ban.expires == "never"}
														<span class="parsec-muted">Никогда</span>
													{else}
														{$ban.expires}
													{/if}
												</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Причина бана</span>
												<span class="ban-card-value">{$ban.reason|escape:'html'}</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Сервер</span>
												<span class="ban-card-value" id="ban_server_{$ban.ban_id}">
													{if $ban.server_id == 0}
														Веб-бан
													{else}
														<span class="parsec-muted">загрузка…</span>
													{/if}
												</span>
											</div>
											<div class="ban-card-row">
												<span class="ban-card-label">Предыдущие баны</span>
												<span class="ban-card-value">{$ban.prevoff_link}</span>
											</div>
											{if $view_recidivism}
											<div class="ban-card-row">
												<span class="ban-card-label">Нарушения</span>
												<span class="ban-card-value">
													{if $ban.recid_display}{$ban.recid_display|escape:'html'}{else}—{/if}
													{if $ban.recidivism_link}
														&nbsp;·&nbsp;{$ban.recidivism_link}
													{/if}
												</span>
											</div>
											{/if}
											<div class="ban-card-row">
												<span class="ban-card-label">Блокировок ({$ban.blockcount})</span>
												<span class="ban-card-value">
													{if $ban.banlog == ""}
														<span class="parsec-muted">никогда</span>
													{else}
														{if $ban.blockcount >= "5"}
															{help_icon title="Блокировки" message="У данного игрока было слишком много блокировок при входе на сервер, поэтому список был укомплектован."}
															<a data-toggle="modal" href="#block_spoiler_{$ban.ban_id}_ply">
																Показать все {$ban.blockcount} блокировок
															</a>
															<div class="modal fade" id="block_spoiler_{$ban.ban_id}_ply" tabindex="-1" role="dialog" aria-hidden="true">
																<div class="modal-dialog modal-lg">
																	<div class="modal-content">
																		<div class="modal-header">
																			<h4 class="modal-title">
																				Список блокировок:
																				{if empty($ban.player)}
																					<span class="parsec-muted">скрыто</span>
																				{else}
																					{$ban.player|escape:'html'|stripslashes}
																				{/if}
																			</h4>
																		</div>
																		<div class="modal-body">
																			<p>{$ban.banlog}</p>
																		</div>
																		<div class="modal-footer">
																			<button type="button" class="btn btn-link bgm-blue c-white waves-effect" data-dismiss="modal">Закрыть</button>
																		</div>
																	</div>
																</div>
															</div>
														{else}
															{$ban.banlog}
														{/if}
													{/if}
												</span>
											</div>
										</div>

										<div class="ban-card-side">
											{if !$hideadminname}
											<div class="ban-side-panel ban-issuer">
												<div class="ban-side-label">Блокировку выдал</div>
												<div class="ban-issuer-name">
													<i class="zmdi zmdi-star c-red"></i>
													{if !empty($ban.admin)}
														<strong>{$ban.admin|escape:'html'}</strong>
													{else}
														<strong>Админ удалён</strong>
													{/if}
												</div>
												{if $ban.admin != "CONSOLE"}
													{if !empty($ban.admin)}
														{if $admininfos}
														<ul class="ban-issuer-meta">
															<li>
																<i class="zmdi zmdi-steam"></i>
																{if !empty($ban.admin_authid)}
																	{if !empty($ban.admin_authid_link)}
																		<a href="https://steamcommunity.com/profiles/{$ban.admin_authid_link}" target="_blank" rel="noopener noreferrer" title="Профиль Steam админа">{$ban.admin_authid}</a>
																	{else}
																		{$ban.admin_authid}
																	{/if}
																{else}
																	<span class="parsec-muted">нет данных</span>
																{/if}
															</li>
															<li>
																<i class="zmdi zmdi-vk"></i>
																{if !empty($ban.admin_vk)}
																	<a href="https://vk.com/{$ban.admin_vk}" target="_blank" rel="noopener">ВКонтакте</a>
																{else}
																	<span class="parsec-muted">нет данных</span>
																{/if}
															</li>
															<li>
																<i class="zmdi zmdi-account-box-o"></i>
																{if !empty($ban.admin_discord)}
																	{$ban.admin_discord|escape}
																{else}
																	<span class="parsec-muted">нет данных</span>
																{/if}
															</li>
															<li class="ban-issuer-note">
																<i class="zmdi zmdi-info-outline"></i>
																{if !empty($ban.admin_comm)}
																	{$ban.admin_comm}
																{else}
																	Обычный рядовой, контролирует порядок на серверах.
																{/if}
															</li>
														</ul>
														{/if}
													{/if}
												{/if}
											</div>
											{/if}

											{if $view_comments}
											<div class="ban-side-panel ban-comments">
												<div class="ban-side-label">Комментарии</div>
												{if $ban.commentdata != "Нет"}
													<div class="ban-comments-list">
														{foreach from=$ban.commentdata item=commenta}
															<div class="ban-comment">
																<div class="ban-comment-head">
																	<span class="ban-comment-author">
																		{if !empty($commenta.comname)}
																			{$commenta.comname|escape:'html'}
																		{else}
																			<span class="parsec-muted">админ удалён</span>
																		{/if}
																	</span>
																	{if $commenta.edittime != "none"}
																		<span class="ban-comment-meta">
																			ред. {if $commenta.editname != "none"}{$commenta.editname}{else}админ удалён{/if} · {$commenta.edittime}
																		</span>
																	{/if}
																	{if $commenta.editcomlink != "none" || $commenta.delcomlink != "none"}
																	<ul class="actions actions-alt ban-comment-actions">
																		<li class="dropdown">
																			<a href="#" data-toggle="dropdown" aria-expanded="false"><i class="zmdi zmdi-more-vert"></i></a>
																			<ul class="dropdown-menu dropdown-menu-right">
																				{if $commenta.editcomlink != "none"}<li>{$commenta.editcomlink}</li>{/if}
																				{if $commenta.delcomlink != "none"}<li>{$commenta.delcomlink}</li>{/if}
																			</ul>
																		</li>
																	</ul>
																	{/if}
																</div>
																<div class="ban-comment-text">{$commenta.commenttxt|escape:'html'|nl2br}</div>
															</div>
														{/foreach}
													</div>
												{else}
													<div class="ban-comments-empty">Комментарии отсутствуют</div>
												{/if}
												<a class="ban-comment-add" href="{$ban.addcomment_link}">Добавить комментарий…</a>
											</div>
											{/if}
										</div>
									</div>
								</div>
						</div>
					</td>
				</tr>
				<!-- ###############[ End Sliding Panel ]################## -->
				<!-- 
				<div class="modal fade" id="mod_{$ban.admin_gid}_mod" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title"><b>{$ban.admin}</b></h4>
                                        </div>
                                        <div class="modal-body f-15">
                                            <p>Доступный список данных администратора:</p>
											<p>
												<ul class="clist clist-angle">
													{if !empty($ban.admin_discord)}<li>Discord: {$ban.admin_discord|escape}</li>{/if}
													{if !empty($ban.admin_vk)}<li>VK: <a href="https://vk.com/{$ban.admin_vk}">Линк</a></li>{/if}
												</ul>
											</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-link" data-dismiss="modal">Закрыть</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
				-->
			{/foreach}
		</tbody>
	</table>
	</div>
	{if $general_unban || $can_delete}
		<div class="card-body card-padding">
			<div class="col-sm-12 p-l-0">
				<div class="col-sm-2 p-0">
					<select class="selectpicker " name="bulk_action" id="bulk_action" onchange="BulkEdit(this,'{$admin_postkey}');">
						<option value="-1">Выберите</option>
						{if $general_unban}
						<option value="U">Разбан</option>
						{/if}
						{if $can_delete}
						<option value="D">Удалить</option>
						{/if}
					</select>
				</div>
				{if $can_export }
					<div class="col-sm-7 p-t-10 text-center">
						Скачать перманентные&nbsp;(&nbsp;<a href="./exportbans.php?type=steam" title="Экспорт перманентных SteamID банов">SteamID</a>&nbsp;/&nbsp;
						<a href="./exportbans.php?type=ip" title="Экспорт перманентных IP банов">IP</a>&nbsp;)&nbsp; баны.
					</div>
				{/if}
				<div class="col-sm-3 p-r-0 text-right" style="float:right;">
					<button class="btn bgm-bluegray waves-effect" onclick="window.location.href='banlist?hideinactive={if $hidetext_darf == '1'}true{else}false{/if}{$searchlink|htmlspecialchars}'">{$hidetext}&nbsp;баны</button>
				</div>
			</div>
		</div>&nbsp;
	{/if}
</div>

{literal}
<script type="text/javascript">window.addEvent('domready', function(){	
InitAccordion('tr.opener', 'div.opener', 'content');
{/literal}
{if $view_bans}
$('tickswitch').value=0;
{/if}
{literal}
}); 
</script>
{/literal}
{/if}
