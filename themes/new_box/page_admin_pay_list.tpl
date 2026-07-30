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
			<div class="alert alert-default m-t-10" role="alert">
				<p class="m-b-5"><b>API для магазина / бота</b>
					{if $voucher_api_enabled == "1"}
						<span class="c-green">· включён</span>
					{else}
						<span class="c-red">· выключен</span> (пустой <code>SB_VOUCHER_API_TOKEN</code> в <code>config.php</code>)
					{/if}
				</p>
				<p class="m-b-5">Эндпоинт: <code>{$voucher_api_url|escape}</code> · только <b>POST</b> · JSON или form · не пароль админа, а отдельный токен.</p>
				<p class="m-b-5">В <code>config.php</code>:</p>
				<pre class="m-b-5" style="white-space:pre-wrap;word-break:break-all;">define('SB_VOUCHER_API_TOKEN', 'сгенерируй_длинную_строку_32plus');
define('SB_VOUCHER_API_ALLOW_IPS', ''); // опционально: 1.2.3.4,5.6.7.8</pre>
				<p class="m-b-5">Поля: <code>days</code>, <code>group_web</code> (имя как в панели или <code>0</code>), <code>group_srv</code> (опц.), <code>servers</code> (<code>""</code> / <code>-1</code> / <code>s1,s2</code>). Токен: тело <code>token</code>, заголовок <code>Authorization: Bearer …</code> или <code>X-SB-Voucher-Token</code>.</p>
{literal}
				<pre class="m-b-5" style="white-space:pre-wrap;word-break:break-all;">curl -s -X POST '{/literal}{$voucher_api_url|escape}{literal}' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -d '{"days":30,"group_web":"Admin","group_srv":"","servers":""}'</pre>
{/literal}
				<p class="m-b-0">
					<a class="btn btn-default btn-icon-text waves-effect" href="api/voucher_test.php" target="_blank" rel="noopener"><i class="zmdi zmdi-flare"></i> Тестер API</a>
					<span class="c-gray m-l-10">или CLI: <code>php api/voucher_test.php</code></span>
				</p>
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
