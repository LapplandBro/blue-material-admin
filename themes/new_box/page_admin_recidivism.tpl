{if NOT $permission_ok}
	<div id="msg-red">
		<i><img src="images/icons/warning.svg" alt="Внимание" /></i>
		<b>Ошибка</b><br />
		У вас нет доступа к истории нарушений.
	</div>
{else}

<div class="recid-panel">

<div class="card m-b-15">
	<div class="card-header">
		<h2>История нарушений
			<small>Очки за баны, гаги и муты за {$window_days} дн. · автоперм с порога {$thr_ban|string_format:"%.0f"} / {$thr_gag|string_format:"%.0f"} / {$thr_mute|string_format:"%.0f"}</small>
		</h2>
	</div>
	<div class="card-body card-padding">
		<form method="get" action="index.php" class="form-horizontal" role="form">
			<input type="hidden" name="p" value="admin" />
			<input type="hidden" name="c" value="recidivism" />
			<div class="form-group m-b-0">
				<label class="col-sm-3 control-label" for="steam">Игрок</label>
				<div class="col-sm-6">
					<div class="fg-line">
						<input type="text" class="form-control" id="steam" name="steam" value="{$steam_input|escape:'html'}" placeholder="STEAM_0:X:Y · [U:1:…] · Community ID" />
					</div>
				</div>
				<div class="col-sm-3">
					<button type="submit" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-search"></i> Открыть карточку</button>
				</div>
			</div>
		</form>
	</div>
</div>

{if $error_msg}
	<div class="parsec-note parsec-note-warn m-b-15">{$error_msg|escape:'html'}</div>
{/if}

{if $tables_ok && $player}
	<div class="card m-b-15">
		<div class="card-header">
			<h2>
				{if $player.name}{$player.name|escape:'html'}{else}Игрок без ника{/if}
				<small>{$player.authid|escape:'html'} · обновлено {$player.updated_fmt}</small>
			</h2>
		</div>
		<div class="card-body card-padding">
			{if $player.missing_row}
				<div class="parsec-note parsec-note-muted m-b-15">В кэше очков записей ещё нет — показываем нули. Плагин заполнит строки при первом нарушении.</div>
			{/if}

			<p class="parsec-muted m-b-10" style="font-size:13px;">
				Очки со временем чуть «съедаются» (за {$window_days} дней до нуля). Поэтому свежий бан на 4 может показывать 3.99 — так и задумано.
			</p>
			<div class="row recid-scores m-b-5">
				<div class="col-sm-4">
					<div class="recid-score-box recid-box-ban">
						<div class="recid-score-label">Бан</div>
						<div class="recid-score-value">{$player.points_ban}</div>
						<div class="recid-score-sub">порог {$thr_ban|string_format:"%.0f"}</div>
						<div class="recid-bar"><span style="width:{$player.pct_ban}%"></span></div>
						{if $player.escalated_ban}<div class="recid-score-note">авто-перм</div>{/if}
					</div>
				</div>
				<div class="col-sm-4">
					<div class="recid-score-box recid-box-gag">
						<div class="recid-score-label">Гаг</div>
						<div class="recid-score-value">{$player.points_gag}</div>
						<div class="recid-score-sub">порог {$thr_gag|string_format:"%.0f"}</div>
						<div class="recid-bar"><span style="width:{$player.pct_gag}%"></span></div>
						{if $player.escalated_gag}<div class="recid-score-note">авто-перм</div>{/if}
					</div>
				</div>
				<div class="col-sm-4">
					<div class="recid-score-box recid-box-mute">
						<div class="recid-score-label">Мут</div>
						<div class="recid-score-value">{$player.points_mute}</div>
						<div class="recid-score-sub">порог {$thr_mute|string_format:"%.0f"}</div>
						<div class="recid-bar"><span style="width:{$player.pct_mute}%"></span></div>
						{if $player.escalated_mute}<div class="recid-score-note">авто-пермамут</div>{/if}
					</div>
				</div>
			</div>

			<div class="row parsec-stats m-t-5 m-b-10">
				<div class="col-sm-3 col-xs-6">
					<div class="parsec-stat">
						<div class="parsec-stat-label">Сумма очков</div>
						<div class="parsec-stat-value">{$player.points_total}</div>
						<div class="parsec-stat-hint">все ветки</div>
					</div>
				</div>
				<div class="col-sm-3 col-xs-6">
					<div class="parsec-stat">
						<div class="parsec-stat-label">В группе</div>
						<div class="parsec-stat-value">{$player.family_size}</div>
						<div class="parsec-stat-hint">аккаунтов</div>
					</div>
				</div>
				<div class="col-sm-3 col-xs-6">
					<div class="parsec-stat">
						<div class="parsec-stat-label">Макс. в группе</div>
						<div class="parsec-stat-value">
							<div class="parsec-stat-bgm">
								<span><em>Бан</em><b>{$player.family_max_ban}</b></span>
								<span><em>Гаг</em><b>{$player.family_max_gag}</b></span>
								<span><em>Мут</em><b>{$player.family_max_mute}</b></span>
							</div>
						</div>
						<div class="parsec-stat-hint">для автоперма</div>
					</div>
				</div>
				<div class="col-sm-3 col-xs-6">
					<div class="parsec-stat">
						<div class="parsec-stat-label">Инциденты</div>
						<div class="parsec-stat-value">{$event_count}</div>
						<div class="parsec-stat-hint">за {$window_days} дн.</div>
					</div>
				</div>
			</div>

			{if $player.fingerprint_id}
			<div class="parsec-block m-b-15">
				<div class="parsec-block-title">Отпечаток ПК</div>
				<div class="parsec-fp" title="{$player.fingerprint_id|escape:'html'}">{$player.fingerprint_fmt|escape:'html'}</div>
				<div class="parsec-fp-meta m-t-10">
					{if $player.fp_is_banned}
						<span class="parsec-badge parsec-badge-danger">Группа: забанена</span>
					{else}
						<span class="parsec-badge parsec-badge-ok">Группа: не забанена</span>
					{/if}
					{if $player.family_size > 1}
						<span class="parsec-meta-item">В группе <strong>{$player.family_size}</strong> акк.</span>
					{/if}
				</div>
			</div>
			{/if}

			<p class="parsec-links m-b-0">
				<a href="{$player.community_url}" target="_blank">Профиль Steam</a>
				<span class="parsec-sep">·</span>
				<a href="{$player.banlist_url}">Баны</a>
				<span class="parsec-sep">·</span>
				<a href="{$player.commslist_url}">Муты / гаги</a>
				<span class="parsec-sep">·</span>
				<a href="{$player.parsec_url}">Связанные аккаунты</a>
			</p>
		</div>
	</div>

	{if $linked_accounts|@count > 0}
	<div class="card m-b-15">
		<div class="card-header">
			<h2>Связанные аккаунты
				<small>{$linked_accounts|@count} шт. · один ПК и/или облако</small>
			</h2>
		</div>
		<div class="table-responsive">
			<table cellspacing="0" cellpadding="0" class="table table-striped parsec-table">
				<tr>
					<th>Игрок</th>
					<th>SteamID</th>
					<th>Откуда связь</th>
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
						<a href="{$la.view_url}">Нарушения</a>
						<span class="parsec-sep">·</span>
						<a href="{$la.parsec_url}">Твинки</a>
						<span class="parsec-sep">·</span>
						<a href="{$la.banlist_url}">Баны</a>
					</td>
				</tr>
				{/foreach}
			</table>
		</div>
	</div>
	{/if}

	<div class="card m-b-15">
		<div class="card-header">
			<h2>Инциденты
				<small>{$event_count} за {$window_days} дней</small>
			</h2>
		</div>
		<div class="table-responsive">
			<table cellspacing="0" cellpadding="0" class="table table-striped parsec-table recid-events">
				<tr>
					<th>Когда</th>
					<th>Ветка</th>
					<th class="text-right">Очки</th>
					<th>Срок</th>
					<th>Категория</th>
					<th>Причина</th>
					<th>Админ</th>
					<th>Источник</th>
					<th>Статус</th>
				</tr>
				{if $event_count == 0}
					<tr>
						<td colspan="9" class="parsec-muted">За последние {$window_days} дней инцидентов нет.</td>
					</tr>
				{else}
					{foreach from=$events item=ev}
					<tr{if $ev.is_revoked} class="recid-row-revoked"{/if}>
						<td class="recid-td-date">{$ev.created_fmt}</td>
						<td><span class="recid-track {$ev.track_class}">{$ev.track_label}</span></td>
						<td class="text-right parsec-td-points" title="С учётом «съедания» со временем (вес {$ev.decay_weight})">
							<strong>{$ev.points_display}</strong>
							{if $ev.has_decay}<div class="recid-points-raw">из {$ev.points_raw_total}</div>{/if}
						</td>
						<td class="recid-td-len">{$ev.length_fmt|escape:'html'}</td>
						<td>{$ev.category_label}</td>
						<td class="recid-td-reason">{$ev.reason|escape:'html'}</td>
						<td>{$ev.admin_display|escape:'html'}</td>
						<td>
							{if $ev.source_link}
								<a href="{$ev.source_link}">{$ev.source_label}{if $ev.ma_bid} #{$ev.ma_bid}{/if}</a>
							{else}
								{$ev.source_label}{if $ev.ma_bid} #{$ev.ma_bid}{/if}
							{/if}
						</td>
						<td>
							{if $ev.is_revoked}
								<span class="parsec-badge parsec-badge-muted">Снято</span>
							{else}
								<span class="parsec-badge parsec-badge-ok">Активно</span>
							{/if}
						</td>
					</tr>
					{/foreach}
				{/if}
			</table>
		</div>
	</div>

{elseif $tables_ok && !$error_msg}
	<div class="card m-b-15 banlist-panel">
		<div class="card-header">
			<h2>Кто накопил больше всех
				<small>Топ по сумме очков · бан + гаг + мут</small>
			</h2>
		</div>
		<div class="table-responsive">
			<table cellspacing="0" cellpadding="0" class="table table-striped parsec-table banlist-table recid-top-table">
				<thead>
				<tr>
					<th>Игрок</th>
					<th>SteamID</th>
					<th class="text-right" width="8%">Всего</th>
					<th class="text-right" width="8%">Бан</th>
					<th class="text-right" width="8%">Гаг</th>
					<th class="text-right" width="8%">Мут</th>
					<th class="text-right" width="14%">Обновлено</th>
					<th class="text-right" width="16%"></th>
				</tr>
				</thead>
				<tbody>
				{if $recent_players|@count == 0}
					<tr>
						<td colspan="8" class="parsec-muted">Пока пусто — введите SteamID выше или дождитесь данных с сервера.</td>
					</tr>
				{else}
					{foreach from=$recent_players item=rp}
					<tr class="banlist-row">
						<td class="banlist-td-player">
							<div class="banlist-player-main">
								{if $rp.name}<strong>{$rp.name|escape:'html'}</strong>{else}<span class="parsec-muted">без ника</span>{/if}
								{if $rp.has_escalate}
									<span class="parsec-chip parsec-chip-esc" title="Уже был автоперм">автоперм</span>
								{/if}
							</div>
						</td>
						<td class="parsec-td-steam"><code>{$rp.authid|escape:'html'}</code></td>
						<td class="text-right"><strong>{$rp.points_total}</strong></td>
						<td class="text-right parsec-td-points">{$rp.points_ban}</td>
						<td class="text-right parsec-td-points">{$rp.points_gag}</td>
						<td class="text-right parsec-td-points">{$rp.points_mute}</td>
						<td class="text-right recid-td-date">{$rp.updated_fmt}</td>
						<td class="text-right parsec-td-actions">
							<a href="{$rp.view_url}">Нарушения</a>
							<span class="parsec-sep">·</span>
							<a href="{$rp.parsec_url}">Твинки</a>
						</td>
					</tr>
					{/foreach}
				{/if}
				</tbody>
			</table>
		</div>
	</div>
{/if}

</div>
{/if}
