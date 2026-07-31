{if $comment}
<!--Код Комментариев-->
<div class="row">
	<div class="card">
		<div class="card-header">
			<h2>{$commenttype} комментарий</h2>
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
						<small class="c-gray">{$com.added}{if $com.editname != ''} · правлен {$com.edittime} ({$com.editname}){/if}</small>

						<div class="m-t-10">{$com.commenttxt|escape:'html'|nl2br}</div>

						</div>
					</li>
				{/foreach}
				<li class="p-20">
					<div class="fg-line">
						<textarea class="form-control auto-size" rows="5" placeholder="Ваш комментарий…" id="commenttext" name="commenttext">{$commenttext|escape:'html'}</textarea>
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
					{sb_button text="$commenttype комментарий" onclick="ProcessComment();" class="m-t-15 btn-primary btn-sm" id="acom" submit=false}&nbsp;
					{sb_button text="Назад" onclick="window.location.href='commslist'" class="m-t-15 btn btn-sm" id="aback"}
				</li>
			</ul>
		</div>
    </div>
</div>
<!--Код Комментариев-->
{else}
<div class="card banlist-panel">
	<div class="card-header">
		<h1>Муты и гаги
			<small>
				Всего {$total_bans}{$ban_nav_p}
			</small>
		</h1>
		
		<div class="actions" id="banlist-nav">
			{$ban_nav}
		</div>
	</div>
	{if $can_add_comms}
	<div class="card-header p-t-0 p-b-10 banlist-toolbar">
		<a href="admin/comms" class="btn bgm-blue btn-icon-text waves-effect">
			<i class="zmdi zmdi-plus"></i> Добавить мут / гаг
		</a>
	</div>
	{/if}
	{if $bstatus_aid}
	<div class="card-header p-t-0 p-b-10">
		<div class="btn-group" role="group">
			<a href="commslist?advSearch={$bstatus_aid}&amp;advType=admin" class="btn btn-sm waves-effect {if !$bstatus}btn-primary{else}btn-default{/if}">Все</a>
			<a href="commslist?advSearch={$bstatus_aid}&amp;advType=admin&amp;bstatus=active" class="btn btn-sm waves-effect {if $bstatus=='active'}btn-primary{else}btn-default{/if}">Активные</a>
			<a href="commslist?advSearch={$bstatus_aid}&amp;advType=admin&amp;bstatus=expired" class="btn btn-sm waves-effect {if $bstatus=='expired'}btn-primary{else}btn-default{/if}">Истёкшие</a>
			<a href="commslist?advSearch={$bstatus_aid}&amp;advType=admin&amp;bstatus=removed" class="btn btn-sm waves-effect {if $bstatus=='removed'}btn-primary{else}btn-default{/if}">Снятые</a>
		</div>
	</div>
	{/if}
	
	<div class="table-responsive">
	<table class="table table-striped parsec-table banlist-table">
		<thead>
			<tr>
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
					<td class="text-center banlist-td-mod">{$ban.mod_icon}</td>
					<td class="banlist-td-date">{$ban.ban_date_info}</td>
					<td class="banlist-td-player">
						<div class="banlist-player-main">
							<span class="banlist-flag" title="Тип блокировки">{$ban.type_icon_p}</span>
							{if empty($ban.player)}
								<span class="parsec-muted">имя не указано</span>
							{else}
								<strong>{$ban.player|escape:'html'|stripslashes}</strong>
							{/if}
							{if $ban.steam_profile}
								<a class="banlist-meta-ico" href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Профиль Steam" onclick="event.cancelBubble=true;"><i class="zmdi zmdi-steam"></i></a>
							{/if}
							{if $view_comments && $ban.commentdata != "Нет" && $ban.commentdata|@count > 0}
								<span class="banlist-meta-ico" title="Комментарии">{$ban.commentdata|@count} <i class="zmdi zmdi-comment-text"></i></span>
							{/if}
							{if $ban.counts}<span class="banlist-meta-ico" title="Другие блокировки">{$ban.counts}</span>{/if}
						</div>
					</td>
					{if $view_recidivism}
						<td class="banlist-td-recid" onclick="event.cancelBubble=true;">
							{if $ban.recid_url}
								<a class="banlist-recid-chip" href="{$ban.recid_url}" title="История нарушений">{$ban.recid_display|escape:'html'}</a>
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
							<span class="parsec-badge parsec-badge-ok">Снят</span>
						{elseif $ban.status_kind == 'perm'}
							<span class="parsec-badge parsec-badge-danger">Навсегда</span>
						{elseif $ban.status_kind == 'session'}
							<span class="parsec-badge parsec-badge-warn">Сессия</span>
						{else}
							<span class="parsec-badge parsec-badge-warn">{$ban.banlength|escape:'html'}</span>
						{/if}
					</td>
				</tr>
				<!-- ###############[ Start Sliding Panel ]################## -->
				<tr class="banlist-detail-row">
					<td colspan="8" class="banlist-detail-cell">
						<div class="opener"> 
								<div class="card-header banlist-detail-head">
									<h2>Карточка блокировки</h2>

									{if $view_bans}
									<ul class="actions actions-alt">
										<li class="dropdown">
											<a href="#" data-toggle="dropdown" aria-expanded="false">
												<i class="zmdi zmdi-more-vert"></i>
											</a>

											<ul class="dropdown-menu dropdown-menu-right">
												{if $ban.unbanned && $ban.reban_link != false}
												  <li>{$ban.reban_link}</li>
												{/if}
												{if $ban.recidivism_link}
												  <li>{$ban.recidivism_link}</li>
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
											</ul>
										</li>
									</ul>
									{/if}
								</div>
								<div class="card-body card-padding banlist-detail-body">
									<div class="form-group col-sm-7 banlist-detail-fields">
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Игрок</label>
											<div class="col-sm-8">
												{if empty($ban.player)}
													<span class="parsec-muted">имя не указано</span>
												{else}
													{$ban.player|escape:'html'|stripslashes}
												{/if}
											</div>
										</div>
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Steam ID</label>
											<div class="col-sm-8">
												{if empty($ban.steamid)}
													<span class="parsec-muted">не указан</span>
												{elseif $ban.steam_profile}
													<a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Открыть профиль Steam">{$ban.steamid}</a>
												{else}
													{$ban.steamid}
												{/if}
											</div>
										</div>
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Steam3 ID</label>
											<div class="col-sm-8">
												{if empty($ban.steamid)}
													<span class="parsec-muted">не указан</span>
												{elseif $ban.steam_profile}
													<a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Открыть профиль Steam">{$ban.steamid3}</a>
												{else}
													{$ban.steamid3}
												{/if}
											</div>
										</div>
										{if $ban.type == 0 || $ban.type == 1 || $ban.type == 2 || $ban.type == 3}
										{if $ban.communityid}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Steam Community</label>
											<div class="col-sm-8">
												{if $ban.steam_profile}
													<a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer" title="Открыть профиль Steam">{$ban.communityid}</a>
													{if $ban.steam_vanity}
														· <a href="{$ban.steam_profile|escape}" target="_blank" rel="noopener noreferrer">/id/{$ban.steam_vanity|escape}</a>
													{/if}
												{else}
													{$ban.communityid}
												{/if}
											</div>
										</div>
										{/if}
										{/if}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Выдан</label>
											<div class="col-sm-8">
												{$ban.ban_date}
											</div>
										</div>
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Срок</label>
											<div class="col-sm-8">
												{if $ban.ub_reason}<del>{$ban.banlength}</del> · {$ban.ub_reason}{else}{$ban.banlength}{/if}
											</div>
										</div>
										{if $ban.unbanned}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Причина снятия</label>
											<div class="col-sm-8">
												{if $ban.ureason == ""}
													<span class="parsec-muted">не указана</span>
												{else}
													{$ban.ureason}
												{/if}
											</div>
										</div>
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Снял</label>
											<div class="col-sm-8">
												 {if !empty($ban.removedby)}
													{$ban.removedby|escape:'html'}
												{else}
													<span class="parsec-muted">админ снят</span>
												{/if}
											</div>
										</div>
										{/if}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Истекает</label>
											<div class="col-sm-8">
												{if $ban.expires == "never" || $ban.expires == "Никогда"}
													<span class="parsec-muted">никогда</span>
												{else}
													{$ban.expires}
												{/if}
											</div>
										</div>
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Причина</label>
											<div class="col-sm-8">
												{$ban.reason|escape:'html'}
											</div>
										</div>
										{if !$hideadminname}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Админ</label>
											<div class="col-sm-8">
												{if !empty($ban.admin)}
													{$ban.admin|escape:'html'}
												{else}
													<span class="parsec-muted">админ снят</span>
												{/if}
											</div>
										</div>
										{/if}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Сервер</label>
											<div class="col-sm-8" id="ban_server_{$ban.ban_id}">
												{if $ban.server_id == 0}
													С сайта
												{else}
													Загрузка…
												{/if}
											</div>
										</div>
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Ранее</label>
											<div class="col-sm-8">
												{$ban.prevoff_link}
											</div>
										</div>
										{if $view_recidivism}
										<div class="form-group col-sm-12 m-b-5">
											<label class="col-sm-4 control-label"><i class="zmdi zmdi-circle-o text-left"></i> Нарушения</label>
											<div class="col-sm-8">
												{if $ban.recid_display}{$ban.recid_display|escape:'html'}{else}—{/if}
												{if $ban.recidivism_link}
													&nbsp;·&nbsp;{$ban.recidivism_link}
												{/if}
											</div>
										</div>
										{/if}
									</div>
									<div class="form-group col-sm-5">
										<div class="wall-comment-list">
										{if $view_comments}
											{if $ban.commentdata != "Нет"}
												<div class="wcl-list">
													{foreach from=$ban.commentdata item=commenta}
													<div class="media">
														<a href="#" class="pull-left">
															<img src="themes/new_box/img/profile-pics/4.jpg" alt="" class="lv-img-sm">
														</a>
								 
														<div class="media-body">
															<a href="#" class="a-title">{if !empty($commenta.comname)}{$commenta.comname|escape:'html'}{else}<span class="parsec-muted">админ удалён</span>{/if}</a>{if !empty($commenta.edittime)} <small class="c-gray m-l-10">правлен {$commenta.edittime}{if !empty($commenta.editname)} ({$commenta.editname}){/if}</small>{/if}
															<p class="m-t-5 m-b-0" style="white-space: nowrap;overflow: hidden;text-overflow: ellipsis;">{$commenta.commenttxt|escape:'html'}</p>
														</div>
														
														{if $commenta.editcomlink != ""}
															<ul class="actions">
																<li class="dropdown">
																	<a href="#" data-toggle="dropdown" aria-expanded="false">
																		<i class="zmdi zmdi-more-vert"></i>
																	</a>

																	<ul class="dropdown-menu dropdown-menu-right">
																		<li>{$commenta.editcomlink}</li>
																		<li>{$commenta.delcomlink}</li>
																	</ul>
																</li>
															</ul>
														{/if}
													</div>
													{/foreach}
												</div>
											{else}
												<div class="wcl-list">
													<div class="media">
														<div class="media-body">
															<p class="m-t-5 m-b-0 parsec-muted">Комментариев нет.</p>
														</div>
													</div>
												</div>
											{/if}
										{else}
											<div class="wcl-list">
												<div class="media">
													<div class="media-body">
														<p class="m-t-5 m-b-0 parsec-muted">Комментарии видят только авторизованные админы.</p>
													</div>
												</div>
											</div>
										{/if}
											<div class="wcl-form">
												<div class="wc-comment">
													{if $view_comments}
														<a href="{$ban.addcomment_link}">
															<div class="wcc-inner">
																Добавить комментарий…
															</div>
														</a>
													{else}
														<div class="wcc-inner">
															Нет доступа
														</div>
													{/if}
												</div>
											</div>
										</div>
									</div>
								</div>
						</div>
					</td>
				</tr>
				<!-- ###############[ End Sliding Panel ]################## -->
			{/foreach}
		</tbody>
	</table>
	</div>
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
