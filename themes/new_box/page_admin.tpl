<div class="card banlist-panel admin-hub" id="cpanel">
	<div class="card-header">
		<h2>Панель управления
			<small>Модерация, серверы и настройки</small>
		</h2>
	</div>

	<div class="card-body card-padding admin-hub-body">

		{if $access_bans || $access_recidivism || $access_parsec}
		<section class="admin-hub-section">
			<h3 class="admin-hub-section-title">Модерация</h3>
			<div class="admin-hub-grid">
				{if $access_bans}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=bans">
					<span class="admin-hub-tile-title">Баны</span>
					<span class="admin-hub-tile-desc">Выдать, снять, протесты и заявки</span>
				</a>
				<div class="admin-hub-tile admin-hub-tile--with-extra">
					<a class="admin-hub-tile-main" href="index.php?p=admin&amp;c=comms">
						<span class="admin-hub-tile-title">Муты и гаги</span>
						<span class="admin-hub-tile-desc">Блок чата и микрофона</span>
					</a>
					<a class="admin-hub-tile-extra" href="index.php?p=commslist">Список</a>
				</div>
				{/if}
				{if $access_recidivism}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=recidivism">
					<span class="admin-hub-tile-title">Нарушения</span>
					<span class="admin-hub-tile-desc">Очки за баны, гаги и муты</span>
				</a>
				{/if}
				{if $access_parsec}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=parsec">
					<span class="admin-hub-tile-title">Твинки</span>
					<span class="admin-hub-tile-desc">Связанные аккаунты с одного ПК и из облака</span>
				</a>
				{/if}
			</div>
		</section>
		{/if}

		{if $access_servers || $access_mods}
		<section class="admin-hub-section">
			<h3 class="admin-hub-section-title">Серверы</h3>
			<div class="admin-hub-grid">
				{if $access_servers}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=servers">
					<span class="admin-hub-tile-title">Серверы</span>
					<span class="admin-hub-tile-desc">Список, RCON, добавление</span>
				</a>
				{/if}
				{if $access_mods}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=mods">
					<span class="admin-hub-tile-title">Моды</span>
					<span class="admin-hub-tile-desc">Игровые модификации</span>
				</a>
				{/if}
			</div>
		</section>
		{/if}

		{if $access_admins || $access_groups || $access_settings || $access_vouchers}
		<section class="admin-hub-section">
			<h3 class="admin-hub-section-title">Команда и система</h3>
			<div class="admin-hub-grid">
				{if $access_admins}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=admins">
					<span class="admin-hub-tile-title">Админы</span>
					<span class="admin-hub-tile-desc">Состав, права, переопределения</span>
				</a>
				{/if}
				{if $access_vouchers}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=pay_card">
					<span class="admin-hub-tile-title">Ваучеры</span>
					<span class="admin-hub-tile-desc">Выпуск ключей · активирует только гость (не залогиненный)</span>
				</a>
				{/if}
				{if $access_groups}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=groups">
					<span class="admin-hub-tile-title">Группы</span>
					<span class="admin-hub-tile-desc">Веб- и серверные группы</span>
				</a>
				{/if}
				{if $access_settings}
				<a class="admin-hub-tile" href="index.php?p=admin&amp;c=settings">
					<span class="admin-hub-tile-title">Настройки</span>
					<span class="admin-hub-tile-desc">Вид сайта, опции, системный лог</span>
				</a>
				{/if}
			</div>
		</section>
		{/if}

		<div class="admin-hub-stats row parsec-stats">
			<div class="col-sm-2 col-xs-4">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Админы</div>
					<div class="parsec-stat-value">{$total_admins}</div>
				</div>
			</div>
			<div class="col-sm-2 col-xs-4">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Баны</div>
					<div class="parsec-stat-value">{$total_bans}</div>
				</div>
			</div>
			<div class="col-sm-2 col-xs-4">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Серверы</div>
					<div class="parsec-stat-value">{$total_servers}</div>
				</div>
			</div>
			<div class="col-sm-2 col-xs-4">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Блоки входа</div>
					<div class="parsec-stat-value">{$total_blocks}</div>
				</div>
			</div>
			<div class="col-sm-2 col-xs-4">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Протесты</div>
					<div class="parsec-stat-value">{$total_protests}</div>
					<div class="parsec-stat-hint" title="В архиве: {$archived_protests}">архив {$archived_protests}</div>
				</div>
			</div>
			<div class="col-sm-2 col-xs-4">
				<div class="parsec-stat">
					<div class="parsec-stat-label">Заявки</div>
					<div class="parsec-stat-value">{$total_submissions}</div>
					<div class="parsec-stat-hint" title="В архиве: {$archived_submissions} · демо: {$demosize}">архив {$archived_submissions}</div>
				</div>
			</div>
		</div>

	</div>
</div>
