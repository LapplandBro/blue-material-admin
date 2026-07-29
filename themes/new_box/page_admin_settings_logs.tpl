<div class="card banlist-panel admin-manage admin-logs">
	<div class="card-header">
		<h2>Системный лог {$clear_logs}
			<small>Клик по строке — детали события</small>
		</h2>
	</div>

	<div class="card-body card-padding admin-logs-search">
		{php} require (TEMPLATES_PATH . "/admin.log.search.php");{/php}
	</div>

	{if $page_numbers}
	<div class="card-body card-padding admin-logs-nav" id="banlist-nav">
		{$page_numbers}
	</div>
	{/if}

	<div class="table-responsive">
		<table class="table table-striped parsec-table banlist-table admin-manage-table admin-admins-table">
			<thead>
				<tr>
					<th width="8%" class="text-center">Тип</th>
					<th>Событие</th>
					<th width="22%">Пользователь</th>
					<th width="18%">Дата</th>
				</tr>
			</thead>
			<tbody>
				{foreach from="$log_items" item="log"}
				<tr class="opener banlist-row admin-manage-row">
					<td class="text-center">{$log.type_img}</td>
					<td>{$log.title}</td>
					<td>{$log.user}</td>
					<td>{$log.date_str}</td>
				</tr>
				<tr class="banlist-detail-row">
					<td colspan="4" class="banlist-detail-cell">
						<div class="opener" style="visibility: hidden; zoom: 1; opacity: 0;">
							<div class="admin-group-detail admin-log-detail">
								<div class="admin-group-detail-col">
									<div class="admin-group-detail-label">Детали</div>
									<div class="admin-group-detail-body">{$log.message|escape}</div>
								</div>
								<div class="admin-group-detail-col">
									<div class="admin-group-detail-label">Функция</div>
									<div class="admin-group-detail-body"><code class="admin-manage-code">{$log.function}</code></div>
									<div class="admin-group-detail-label m-t-10">Запрос</div>
									<div class="admin-group-detail-body admin-log-query">{textformat wrap=62 wrap_cut=true}{$log.query}{/textformat}</div>
									<div class="admin-group-detail-label m-t-10">IP</div>
									<div class="admin-group-detail-body">{$log.host}</div>
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
	InitAccordion('tr.opener', 'div.opener', 'content');
</script>
