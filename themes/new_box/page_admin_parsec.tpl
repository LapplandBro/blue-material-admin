{if NOT $permission_ok}
	<div id="msg-red">
		<i><img src="images/icons/warning.svg" alt="Внимание" /></i>
		<b>Ошибка</b><br />
		У вас нет доступа к просмотру связанных аккаунтов.
	</div>
{else}

<div class="parsec-panel">

<div class="card m-b-15">
	<div class="card-header">
		<h2>Связанные аккаунты
			<small>Кто играет с того же ПК и кого связало облако антифрода</small>
		</h2>
	</div>
	<div class="card-body card-padding">
		<form method="get" action="index.php" class="form-horizontal" role="form">
			<input type="hidden" name="p" value="admin" />
			<input type="hidden" name="c" value="parsec" />
			<div class="form-group m-b-0">
				<label class="col-sm-3 control-label" for="steam">Игрок</label>
				<div class="col-sm-6">
					<div class="fg-line">
						<input type="text" class="form-control" id="steam" name="steam" value="{$steam_input|escape:'html'}" placeholder="STEAM_0:X:Y · [U:1:…] · Community ID" />
					</div>
				</div>
				<div class="col-sm-3">
					<button type="submit" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-search"></i> Найти твинки</button>
				</div>
			</div>
		</form>
		{if !$tables_ok}
			<div class="parsec-note parsec-note-warn m-t-15">
				База отпечатков ПК на сайте недоступна. Можно смотреть только связи из облака (если API включён).
			</div>
		{/if}
	</div>
</div>

{if $flash_ok}<div class="parsec-note parsec-note-ok m-b-15">{$flash_ok|escape:'html'}</div>{/if}
{if $flash_err}<div class="parsec-note parsec-note-danger m-b-15">{$flash_err|escape:'html'}</div>{/if}
{if $error_msg}<div class="parsec-note parsec-note-warn m-b-15">{$error_msg|escape:'html'}</div>{/if}

{if $can_write_eligible}
<div class="card m-b-15 parsec-write-card{if $write_mode} parsec-write-armed{/if}">
	<div class="card-header">
		<h2>Опасная зона
			<small>Только владелец · {$admin_steam|escape:'html'}</small>
		</h2>
	</div>
	<div class="card-body card-padding">
		<div class="parsec-note parsec-note-warn m-b-15">
			Здесь можно только пометить группу аккаунтов как «забанена» или снять эту пометку.
			<strong>Код отпечатка ПК не меняйте</strong> — иначе у игрока останется старый файл, и при заходе его кикнет с ошибкой файла.
			Снятие пометки <strong>не разбанивает</strong> игрока в обычном списке банов.
		</div>
		{if !$session_unlocked}
			<form method="post" action="{$form_action|escape:'html'}" class="form-horizontal" role="form">
				<input type="hidden" name="csrf" value="{$csrf|escape:'html'}" />
				<input type="hidden" name="parsec_action" value="unlock" />
				<div class="form-group m-b-0">
					<label class="col-sm-3 control-label" for="panel_password">Пароль</label>
					<div class="col-sm-5">
						<div class="fg-line">
							<input type="password" class="form-control" id="panel_password" name="panel_password" autocomplete="off" placeholder="Пароль панели" />
						</div>
					</div>
					<div class="col-sm-4">
						<button type="submit" class="btn bgm-bluegray btn-icon-text waves-effect">Разблокировать на 30 мин</button>
					</div>
				</div>
				{if !$password_configured}
					<p class="parsec-muted m-t-10 m-b-0">Сначала задайте пароль панели в настройках сайта (config.php).</p>
				{/if}
			</form>
		{else}
			<div class="parsec-write-bar">
				<form method="post" action="{$form_action|escape:'html'}" class="parsec-write-toggle" role="form">
					<input type="hidden" name="csrf" value="{$csrf|escape:'html'}" />
					<input type="hidden" name="parsec_action" value="toggle_write" />
					<label class="parsec-switch">
						<input type="checkbox" name="write_mode" value="1"{if $write_mode} checked="checked"{/if} onchange="this.form.submit()" />
						<span class="parsec-switch-ui"></span>
						<span class="parsec-switch-text">Разрешить правки {if $write_mode}<em>включено</em>{else}<em class="parsec-muted">выключено</em>{/if}</span>
					</label>
				</form>
				<form method="post" action="{$form_action|escape:'html'}" role="form">
					<input type="hidden" name="csrf" value="{$csrf|escape:'html'}" />
					<input type="hidden" name="parsec_action" value="lock" />
					<button type="submit" class="btn btn-default btn-sm waves-effect">Закрыть правки</button>
				</form>
			</div>
		{/if}
	</div>
</div>
{/if}

{if $authid && $self_card}
<div class="card m-b-15">
	<div class="card-header">
		<h2>
			{if $self_card.name}{$self_card.name|escape:'html'}{else}Игрок без ника{/if}
			<small>{$self_card.authid|escape:'html'}</small>
		</h2>
	</div>
	<div class="card-body card-padding">
		<div class="row parsec-stats m-b-15">
			<div class="col-sm-3 col-xs-6">
				<div class="parsec-stat">
					<div class="parsec-stat-label">В группе</div>
					<div class="parsec-stat-value">{$self_card.family_size}</div>
					<div class="parsec-stat-hint">аккаунтов</div>
				</div>
			</div>
			<div class="col-sm-3 col-xs-6">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Бан в списке</div>
					<div class="parsec-stat-value {if $self_card.active_ban}parsec-text-danger{else}parsec-text-ok{/if}">
						{if $self_card.active_ban}да{else}нет{/if}
					</div>
					<div class="parsec-stat-hint">на сайте</div>
				</div>
			</div>
			<div class="col-sm-3 col-xs-6">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Очки нарушений</div>
					<div class="parsec-stat-value">
						<div class="parsec-stat-bgm">
							<span><em>Бан</em><b>{$self_card.points_ban|escape:'html'}</b></span>
							<span><em>Гаг</em><b>{$self_card.points_gag|escape:'html'}</b></span>
							<span><em>Мут</em><b>{$self_card.points_mute|escape:'html'}</b></span>
						</div>
					</div>
					<div class="parsec-stat-hint">текущие очки</div>
				</div>
			</div>
			<div class="col-sm-3 col-xs-6">
				<div class="parsec-stat">
					<div class="parsec-stat-label">В облаке</div>
					{if $api_player}
						<div class="parsec-stat-value">
							<span class="parsec-badge {$api_player.state_class}">{$api_player.state_label|escape:'html'}</span>
						</div>
						<div class="parsec-stat-hint">твинков: {$api_player.linked_count}</div>
					{else}
						<div class="parsec-stat-value parsec-stat-value-sm parsec-muted">нет данных</div>
						<div class="parsec-stat-hint">нет ответа</div>
					{/if}
				</div>
			</div>
		</div>

		<p class="parsec-links m-b-20">
			<a href="{$self_card.recid_url}">История нарушений</a>
			<span class="parsec-sep">·</span>
			<a href="{$self_card.banlist_url}">Баны в списке</a>
		</p>

		{if $fp_meta && $fp_meta.fingerprint}
		<div class="parsec-block m-b-20">
			<div class="parsec-block-title">Отпечаток ПК</div>
			<p class="parsec-muted m-b-10" style="font-size:13px;line-height:1.45;">
				Это код компьютера игрока. Все аккаунты с одним кодом — одна группа (твинки с одного ПК).
			</p>
			<div class="parsec-fp" title="{$fp_meta.fingerprint|escape:'html'}">{$fp_meta.fingerprint_fmt|escape:'html'}</div>
			<div class="parsec-fp-raw">
				<span class="parsec-muted">Код целиком:</span>
				<code>{$fp_meta.fingerprint|escape:'html'}</code>
			</div>
			<div class="parsec-fp-meta">
				{if $fp_meta.is_banned}
					<span class="parsec-badge parsec-badge-danger">Группа: забанена</span>
					<span class="parsec-meta-item">Срок: <strong>{$fp_meta.banned_duration_fmt|escape:'html'}</strong></span>
					{if $fp_meta.banned_at_fmt != '—'}
						<span class="parsec-meta-item">С: <strong>{$fp_meta.banned_at_fmt|escape:'html'}</strong></span>
					{/if}
				{else}
					<span class="parsec-badge parsec-badge-ok">Группа: не забанена</span>
				{/if}
			</div>
			{if $can_write && $fp_meta.fingerprint}
			<form method="post" action="{$form_action|escape:'html'}" class="m-t-15" role="form" onsubmit="return confirm('Подтвердите смену статуса группы. Обычный бан в списке банов при этом не меняется.');">
				<input type="hidden" name="csrf" value="{$csrf|escape:'html'}" />
				<input type="hidden" name="fingerprint" value="{$fp_meta.fingerprint|escape:'html'}" />
				{if $fp_meta.is_banned}
					<input type="hidden" name="parsec_action" value="clear_ban" />
					<button type="submit" class="btn bgm-bluegray waves-effect">Снять пометку «группа забанена»</button>
				{else}
					<input type="hidden" name="parsec_action" value="mark_ban" />
					<button type="submit" class="btn bgm-bluegray waves-effect">Пометить группу: забанена (навсегда)</button>
				{/if}
			</form>
			{/if}
		</div>
		{elseif $tables_ok}
			<div class="parsec-note parsec-note-muted m-b-20">Для этого игрока отпечаток ПК ещё не записан — он появится после того, как игрок зайдёт на сервер с антифродом.</div>
		{/if}

		{if $api_player}
		<div class="parsec-block">
			<div class="parsec-block-title">Облачный антифрод</div>
			<div class="parsec-api-grid">
				<div>
					<span class="parsec-muted">Статус</span>
					<div><span class="parsec-badge {$api_player.state_class}">{$api_player.state_label|escape:'html'}</span></div>
				</div>
				<div>
					<span class="parsec-muted">Связанных аккаунтов</span>
					<div class="parsec-api-num">{$api_player.linked_count}</div>
				</div>
				{if $api_player.name}
				<div>
					<span class="parsec-muted">Ник в облаке</span>
					<div>{$api_player.name|escape:'html'}</div>
				</div>
				{/if}
				{if $api_player.ban_at_fmt}
				<div>
					<span class="parsec-muted">Бан с</span>
					<div>{$api_player.ban_at_fmt|escape:'html'}</div>
				</div>
				{/if}
				{if $api_player.unban_at_fmt}
				<div>
					<span class="parsec-muted">До</span>
					<div>{$api_player.unban_at_fmt|escape:'html'}</div>
				</div>
				{/if}
			</div>
			{if $api_player.ban_reason}
				<div class="parsec-api-reason m-t-10">{$api_player.ban_reason|escape:'html'}</div>
			{/if}
		</div>
		{/if}
	</div>
</div>

{if $linked_accounts|@count > 0}
<div class="card m-b-15">
	<div class="card-header">
		<h2>Твинки
			<small>{$linked_accounts|@count} шт. · кроме текущего аккаунта</small>
		</h2>
	</div>
	<div class="table-responsive">
		<table cellspacing="0" cellpadding="0" class="table table-striped parsec-table">
			<tr>
				<th>Игрок</th>
				<th>SteamID</th>
				<th>Как связаны</th>
				<th class="text-right">Очки</th>
				<th>Бан</th>
				<th class="text-right">Действия</th>
			</tr>
			{foreach from=$linked_accounts item=la}
			<tr>
				<td class="parsec-td-name">{if $la.name}<strong>{$la.name|escape:'html'}</strong>{else}<span class="parsec-muted">без ника</span>{/if}</td>
				<td class="parsec-td-steam"><code>{$la.authid|escape:'html'}</code></td>
				<td>
					{foreach from=$la.source_chips item=chip}
						<span class="parsec-chip {$chip.class}">{$chip.text|escape:'html'}</span>
					{/foreach}
					{if $la.source_chips|@count == 0}<span class="parsec-muted">—</span>{/if}
				</td>
				<td class="text-right parsec-td-points">{$la.points_display|escape:'html'}</td>
				<td>{if $la.active_ban}<span class="parsec-badge parsec-badge-danger">забанен</span>{else}<span class="parsec-muted">нет</span>{/if}</td>
				<td class="text-right parsec-td-actions">
					<a href="{$la.parsec_url}">Твинки</a>
					<span class="parsec-sep">·</span>
					<a href="{$la.view_url}">Нарушения</a>
					<span class="parsec-sep">·</span>
					<a href="{$la.banlist_url}">Баны</a>
				</td>
			</tr>
			{/foreach}
		</table>
	</div>
</div>
{elseif $authid}
<div class="parsec-note parsec-note-muted m-b-15">Других связанных аккаунтов не найдено — только этот SteamID.</div>
{/if}
{/if}

{if $tables_ok}
<div class="card m-b-15">
	<div class="card-header">
		<h2>Группы с пометкой «забанена»
			<small>всего {$banned_total}</small>
		</h2>
	</div>
	<div class="table-responsive">
		<table cellspacing="0" cellpadding="0" class="table table-striped parsec-table parsec-table-fp">
			<tr>
				<th>Отпечаток ПК</th>
				<th class="text-right">Аккаунтов</th>
				<th>Срок</th>
				<th>Когда</th>
				<th class="text-right"></th>
			</tr>
			{if $banned_total == 0}
				<tr><td colspan="5" class="parsec-muted">Сейчас нет групп с пометкой «забанена».</td></tr>
			{else}
				{foreach from=$banned_rows item=br}
				<tr>
					<td class="parsec-td-fp">
						<div class="parsec-fp parsec-fp-compact" title="{$br.fingerprint|escape:'html'}">{$br.fingerprint_fmt|escape:'html'}</div>
						{if $br.steams_preview}
							<div class="parsec-fp-steams parsec-muted">{$br.steams_preview|escape:'html'}</div>
						{/if}
					</td>
					<td class="text-right">{$br.steam_count}</td>
					<td>{$br.banned_duration_fmt|escape:'html'}</td>
					<td>{$br.banned_at_fmt|escape:'html'}</td>
					<td class="text-right"><a class="btn btn-default btn-xs waves-effect" href="{$br.open_url}">Открыть</a></td>
				</tr>
				{/foreach}
			{/if}
		</table>
	</div>
	{if $banned_pages > 1}
	<div class="card-body card-padding parsec-pager">
		{foreach from=$page_links item=pl name=plloop}
			{if $pl.current}<span class="parsec-pager-cur">{$pl.n}</span>{else}<a href="{$pl.url}">{$pl.n}</a>{/if}
			{if !$smarty.foreach.plloop.last}<span class="parsec-sep">·</span>{/if}
		{/foreach}
	</div>
	{/if}
</div>
{/if}

</div>
{/if}
