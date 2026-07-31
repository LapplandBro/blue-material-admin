{if $dashboard_text == "<p><br></p>" || $dashboard_text == ""}
{if $dashboard_info_block == "1"}
	<div class="card banlist-panel dash-intro">
		<div class="card-header">
			<h1>{$header_title|escape:'html'}
				<small>Модерация и серверы</small>
			</h1>
		</div>
		<div class="card-body card-padding clearfix">
			<div class="col-sm-8">
				<div class="dash-intro-text">
					{if $dashboard_info_block_text != ""}{$dashboard_info_block_text}{else}<span class="parsec-muted">Информация пока не задана.</span>{/if}
				</div>
				{if $dashboard_info_block_text_p != ""}<p class="dash-intro-foot">{$dashboard_info_block_text_p}</p>{/if}
			</div>
			<div class="col-sm-4">
				<div class="dash-social-label">Соцсети</div>
				<div class="dash-social">
					{if $dashboard_info_steam !=""}
						<a href="{$dashboard_info_steam}" title="Steam" target="_blank" rel="noopener">
							<img src="themes/new_box/img/social/steam-128.png" width="40" height="40" alt="Steam" loading="lazy">
						</a>
					{/if}
					{if $dashboard_info_vk !=""}
						<a href="{$dashboard_info_vk}" title="ВКонтакте" target="_blank" rel="noopener">
							<img src="themes/new_box/img/social/vk-128.png" width="40" height="40" alt="VK" loading="lazy">
						</a>
					{/if}
					{if $dashboard_info_yout !=""}
						<a href="{$dashboard_info_yout}" title="YouTube" target="_blank" rel="noopener">
							<img src="themes/new_box/img/social/youtube-128.png" width="40" height="40" alt="YouTube" loading="lazy">
						</a>
					{/if}
					{if $dashboard_info_face !=""}
						<a href="{$dashboard_info_face}" title="Facebook" target="_blank" rel="noopener">
							<img src="themes/new_box/img/social/facebook-128.png" width="40" height="40" alt="Facebook" loading="lazy">
						</a>
					{/if}
				</div>
			</div>
		</div>
	</div>
{/if}
{else}
	<div class="card banlist-panel dash-intro">
		<div class="card-header">
			<h1>{$header_title|escape:'html'}
				<small>Правила и информация</small>
			</h1>
		</div>
		<div class="card-body card-padding">
			{if $dashboard_info_block == "1"}
			<ul class="tab-nav tn-justified tn-icon dash-intro-tabs" role="tablist">
				<li role="presentation" class="active">
					<a class="col-sx-4" href="#tab-home" aria-controls="tab-1" role="tab" data-toggle="tab" title="Правила">
						<i class="zmdi zmdi-home icon-tab"></i>
						<span class="sr-only">Правила</span>
					</a>
				</li>
				<li role="presentation">
					<a class="col-xs-4" href="#tab-rek" aria-controls="tab-2" role="tab" data-toggle="tab" title="Ещё">
						<i class="zmdi zmdi-info-outline icon-tab"></i>
						<span class="sr-only">Дополнительно</span>
					</a>
				</li>
			</ul>
			<div class="tab-content p-t-15">
				<div role="tabpanel" class="tab-pane animated fadeIn in active" id="tab-home">
					<div class="sb-rules-host">{$dashboard_text}</div>
				</div>
				<div role="tabpanel" class="tab-pane animated fadeIn clearfix" id="tab-rek">
					<div class="col-sm-8">
						<div class="dash-intro-text">
							{if $dashboard_info_block_text != ""}{$dashboard_info_block_text}{else}<span class="parsec-muted">Информация пока не задана.</span>{/if}
						</div>
						{if $dashboard_info_block_text_p != ""}<p class="dash-intro-foot">{$dashboard_info_block_text_p}</p>{/if}
					</div>
					<div class="col-sm-4">
						<div class="dash-social-label">Соцсети</div>
						<div class="dash-social">
							{if $dashboard_info_steam !=""}
								<a href="{$dashboard_info_steam}" title="Steam" target="_blank" rel="noopener">
									<img src="themes/new_box/img/social/steam-128.png" width="40" height="40" alt="Steam" loading="lazy">
								</a>
							{/if}
							{if $dashboard_info_vk !=""}
								<a href="{$dashboard_info_vk}" title="ВКонтакте" target="_blank" rel="noopener">
									<img src="themes/new_box/img/social/vk-128.png" width="40" height="40" alt="VK" loading="lazy">
								</a>
							{/if}
							{if $dashboard_info_yout !=""}
								<a href="{$dashboard_info_yout}" title="YouTube" target="_blank" rel="noopener">
									<img src="themes/new_box/img/social/youtube-128.png" width="40" height="40" alt="YouTube" loading="lazy">
								</a>
							{/if}
							{if $dashboard_info_face !=""}
								<a href="{$dashboard_info_face}" title="Facebook" target="_blank" rel="noopener">
									<img src="themes/new_box/img/social/facebook-128.png" width="40" height="40" alt="Facebook" loading="lazy">
								</a>
							{/if}
						</div>
					</div>
				</div>
			</div>
			{else}
				<div class="sb-rules-host">{$dashboard_text}</div>
			{/if}
		</div>
	</div>
{/if}

<div id="front-servers" class="login-content">
	{include file='page_servers.tpl'}
</div>

<div class="row dash-lists">

	{if $listing_block == "2" || $listing_block == "3"}
	<div class="col-sm-12">
		<div class="card banlist-panel">
			<div class="card-header">
				<h2>Муты и гаги
					<small>{$total_comms} · последние</small>
				</h2>
				<ul class="actions">
					<li><a href="commslist">Смотреть все</a></li>
				</ul>
			</div>
			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table dash-home-table">
					<thead>
						<tr>
							<th class="dash-col-icon text-center">Тип</th>
							<th class="dash-col-date">Дата</th>
							<th>Игрок</th>
							<th class="dash-col-length">Срок</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$players_commed item=player}
							<tr class="banlist-row admin-manage-row dash-home-row">
								<td class="dash-col-icon text-center">
									<a href="{$player.search_link|escape:'html'}" aria-label="Муты игрока {if empty($player.short_name)}без имени{else}{$player.short_name|escape:'html'}{/if}">
										{$player.type_html}
									</a>
								</td>
								<td class="dash-col-date">
									<a class="dash-home-link" href="{$player.search_link|escape:'html'}" title="{$player.created_info|escape:'html'}" aria-label="Дата мута: {$player.created_info|escape:'html'}">{$player.created}</a>
								</td>
								<td>
									<a class="dash-home-link" href="{$player.search_link|escape:'html'}" aria-label="Открыть муты игрока {if empty($player.short_name)}без имени{else}{$player.short_name|escape:'html'}{/if}">
										{if empty($player.short_name)}
											<span class="parsec-muted">без имени</span>
										{else}
											{$player.short_name|escape:'html'}
										{/if}
									</a>
								</td>
								<td class="dash-col-length">
									{if $player.unbanned}
										<span class="parsec-chip parsec-chip-done" title="Снято / истекло">{$player.length}</span>
									{else}
										<span class="parsec-chip parsec-chip-fp">{$player.length}</span>
									{/if}
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	</div>
	{/if}

	{if $listing_block == "1" || $listing_block == "3"}
	<div class="col-sm-6">
		<div class="card banlist-panel">
			<div class="card-header">
				<h2>Баны
					<small>{$total_bans} · последние</small>
				</h2>
				<ul class="actions">
					<li><a href="banlist">Смотреть все</a></li>
				</ul>
			</div>
			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table dash-home-table">
					<thead>
						<tr>
							<th class="dash-col-icon text-center">Игра</th>
							<th class="dash-col-date">Дата</th>
							<th>Игрок</th>
							<th class="dash-col-length">Срок</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$players_banned item=player}
							<tr class="banlist-row admin-manage-row dash-home-row">
								<td class="dash-col-icon text-center">
									<a href="{$player.search_link|escape:'html'}" aria-label="Бан игрока {if empty($player.short_name)}без имени{else}{$player.short_name|escape:'html'}{/if}">
										{$player.icon_html}
									</a>
								</td>
								<td class="dash-col-date">
									<a class="dash-home-link" href="{$player.search_link|escape:'html'}" title="{$player.created_info|escape:'html'}" aria-label="Дата бана: {$player.created_info|escape:'html'}">{$player.created}</a>
								</td>
								<td>
									<a class="dash-home-link" href="{$player.search_link|escape:'html'}" aria-label="Открыть бан игрока {if empty($player.short_name)}без имени{else}{$player.short_name|escape:'html'}{/if}">
										{if not $nocountryshow}{$player.country_icon} {/if}
										{if empty($player.short_name)}
											<span class="parsec-muted">без имени</span>
										{else}
											{$player.short_name|escape:'html'}
										{/if}
									</a>
								</td>
								<td class="dash-col-length">
									{if $player.unbanned}
										<span class="parsec-chip parsec-chip-done" title="Снято / истекло">{$player.length}</span>
									{else}
										<span class="parsec-chip parsec-chip-fp">{$player.length}</span>
									{/if}
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="col-sm-6">
		<div class="card banlist-panel">
			<div class="card-header">
				<h2>Блоки входа
					<small>{$total_blocked} · последние</small>
				</h2>
				<ul class="actions">
					<li><a href="banlist">Банлист</a></li>
				</ul>
			</div>
			<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table dash-home-table">
					<thead>
						<tr>
							<th class="dash-col-icon text-center"><span class="sr-only">Статус</span></th>
							<th class="dash-col-date">Дата</th>
							<th>Игрок</th>
						</tr>
					</thead>
					<tbody>
						{if $total_blocked == "0"}
							<tr class="banlist-row admin-manage-row">
								<td class="dash-col-icon"></td>
								<td colspan="2"><span class="parsec-muted">Пока пусто</span></td>
							</tr>
						{else}
							{foreach from=$players_blocked item=player}
								<tr class="banlist-row admin-manage-row dash-home-row" id="{$player.server}">
									<td class="dash-col-icon text-center">
										<a href="{$player.search_link|escape:'html'}" aria-label="Блок входа: {if empty($player.short_name)}без имени{else}{$player.short_name|escape:'html'}{/if}">
											<img class="sb-ico" src="images/icons/forbidden.svg" width="20" height="20" alt="Блок входа" loading="lazy">
										</a>
									</td>
									<td class="dash-col-date">
										<a class="dash-home-link" href="{$player.search_link|escape:'html'}" aria-label="Дата блока входа: {$player.date|escape:'html'}">{$player.date}</a>
									</td>
									<td>
										<a class="dash-home-link" href="{$player.search_link|escape:'html'}" aria-label="Открыть блок входа: {if empty($player.short_name)}без имени{else}{$player.short_name|escape:'html'}{/if}">
											{if empty($player.short_name)}
												<span class="parsec-muted">без имени</span>
											{else}
												{$player.short_name|escape:'html'}
											{/if}
										</a>
									</td>
								</tr>
							{/foreach}
						{/if}
					</tbody>
				</table>
			</div>
		</div>
	</div>
	{/if}

</div>

{if $stats}
<div class="row parsec-stats dash-stats">
	<div class="col-sm-3 col-xs-6">
		<a class="parsec-stat dash-stat-link" href="adminlist">
			<div class="parsec-stat-label">Админы</div>
			<div class="parsec-stat-value">{$total_admins}</div>
		</a>
	</div>
	<div class="col-sm-3 col-xs-6">
		<a class="parsec-stat dash-stat-link" href="banlist">
			<div class="parsec-stat-label">Баны</div>
			<div class="parsec-stat-value">{$total_bans}</div>
		</a>
	</div>
	<div class="col-sm-3 col-xs-6">
		<a class="parsec-stat dash-stat-link" href="servers">
			<div class="parsec-stat-label">Серверы</div>
			<div class="parsec-stat-value">{$total_servers}</div>
		</a>
	</div>
	<div class="col-sm-3 col-xs-6">
		<a class="parsec-stat dash-stat-link" href="commslist">
			<div class="parsec-stat-label">Муты / гаги</div>
			<div class="parsec-stat-value">{$total_comms}</div>
		</a>
	</div>
</div>
{/if}
