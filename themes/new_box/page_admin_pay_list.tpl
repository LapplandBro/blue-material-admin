	<div class="card">
		<div class="card-header">
		<h2>Ваучеры
			<small>Выпуск ключей · активирует только гость на <code>index.php?p=pay</code></small>
		</h2>
		</div>

		<div class="card-body card-padding p-b-0">
			<div class="alert alert-info" role="alert">
				<p class="m-b-5"><b>Как это работает</b></p>
				<ol class="m-b-0 p-l-20">
					<li>Владелец выпускает HEX-ключ на вкладке «Добавить» (срок, группы, серверы).</li>
					<li>Ключ отдаёте покупателю / новому админу.</li>
					<li>Он заходит <b>без авторизации</b> (гость) → «Активировать ваучер» → вводит ключ и данные аккаунта.</li>
					<li>Создаётся новый админ; ключ становится «Использованный».</li>
				</ol>
				<p class="m-t-10 m-b-0 c-gray">Залогиненный админ активировать ваучер не может — иначе получится путаница с уже существующим аккаунтом.</p>
			</div>
		</div>

		<div class="card-body table-responsive">
				<table width="100%" class="table" id="group.details">
					<thead>
						<tr>
							<th width="5%">№</th>
							<th width="12%">Статус</th>
							<th width="30%">Ключ</th>
							<th width="10%">Срок</th>
							<th width="10%">Группа(сервер)</th>
							<th width="10%">Группа(веб)</th>
							<th width="13%">Сервер</th>
							<th width="8%"></th>
						</tr>
					</thead>
					<tbody>
						{foreach from="$card_list" item="card"}
							<tr>
								<td>
									{$card.aid}
								</td>
								<td>
									{if $card.activ == "1"}<span class="c-green">Рабочий</span>{else}<span class="c-red">Использованный</span>{/if}
								</td>
								<td>
									{if $card.value != ""}{$card.value}{else}<span class="c-red">Нету ключа</span>{/if}
								</td>
								<td>
									{if $card.days == "0"}Навсегда{else}{$card.days} дн.{/if}
								</td>
								<td>
									{if $card.group_srv != ""}{$card.group_srv}{else}<span class="c-red">Нету группы</span>{/if}
								</td>
								<td>
									{if $card.group_web != "0"}{$card.group_web}{else}<span class="c-red">Нету группы</span>{/if}
								</td>
								<td>
									{if $card.servers == ""}
										<span class="c-green">Свободный выбор</span>
									{else}
										{if $card.servers == "-1"}
											<span class="c-red">Без доступа</span>
										{else}
											<span class="c-red">Выбор ограничен</span>
										{/if}
									{/if}
								</td>
								<td>
									<a href="index.php?p=admin&c=pay_card&o=del&id={$card.aid}">Удалить</a>
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>&nbsp;
		</div>
	</div>
