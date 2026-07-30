<div class="card banlist-panel admin-form" id="voucher-activate">
	<div class="card-header">
		<h2>Активация ваучера
			<small>Ключ выдаёт администрация · после активации создаётся аккаунт админа</small>
		</h2>
	</div>

	{if $error_msg}
	<div class="alert alert-danger m-l-15 m-r-15" role="alert">{$error_msg}</div>
	{/if}

	{if $param == "0"}
	<form action="index.php?p=pay" method="post" class="form-horizontal" autocomplete="off">
		{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
		<div class="card-body card-padding">
			<div class="form-group m-b-15">
				<label for="pay_v4" class="col-sm-3 control-label">{help_icon title="Ваучер" message="16 цифр. Можно с дефисами: 2000-2098-0500-0188"} Код ваучера</label>
				<div class="col-sm-6">
					<div class="fg-line">
						<input type="text" class="form-control input-mask" id="pay_v4" name="pay_v4" data-mask="0000-0000-0000-0000" placeholder="2000-2098-0500-0188" required maxlength="19" />
					</div>
				</div>
			</div>
			<div class="form-group m-b-15">
				<label for="kapcha" class="col-sm-3 control-label">{help_icon title="Капча" message="Введите символы с картинки. Обновите картинку, если не читается."} Проверочный код</label>
				<div class="col-sm-6">
					<div class="voucher-captcha-row">
						<img id="voucher_captcha_img" class="voucher-captcha-img" src="includes/captcha/captcha.php?t={$smarty.now}" width="170" height="56" alt="Капча" />
						<button type="button" class="btn btn-default btn-icon waves-effect" title="Обновить код" onclick="var i=document.getElementById('voucher_captcha_img'); if(i) i.src='includes/captcha/captcha.php?t='+Date.now();">
							<i class="zmdi zmdi-refresh-alt"></i>
						</button>
						<div class="fg-line voucher-captcha-input">
							<input type="text" class="form-control" id="kapcha" name="kapcha" placeholder="Символы с картинки" required maxlength="8" autocomplete="off" />
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body card-padding text-center">
			{sb_button text="Проверить ваучер" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="pay_send" submit=true}
		</div>
	</form>

	{else}
	<div class="alert alert-info m-l-15 m-r-15" role="alert">
		Ваучер <b>{$klu4ik|escape}</b> принят. Заполните данные аккаунта и нажмите «Активировать».
		Срок: <b>{$days|escape}</b> · веб-группа: <b>{$gr_web|escape}</b> · серверная: <b>{$gr_srv|escape}</b>
	</div>
	<div class="form-horizontal" role="form">
		<div class="card-body card-padding p-b-0">
			<div class="form-group m-b-5">
				<label for="user_login" class="col-sm-3 control-label">{help_icon title="Логин" message="Логин для входа в веб-панель"} Логин</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="text" class="form-control" id="user_login" name="user_login" placeholder="Логин" autocomplete="username" /></div>
					<div id="name.msg" class="c-red"></div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="password" class="col-sm-3 control-label">{help_icon title="Пароль" message="Минимальная длина задаётся в настройках сайта"} Пароль</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="password" class="form-control" id="password" name="password" placeholder="Пароль" autocomplete="new-password" /></div>
					<div id="password.msg" class="c-red"></div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="password2" class="col-sm-3 control-label">{help_icon title="Подтверждение" message="Повторите пароль"} Пароль ещё раз</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="password" class="form-control" id="password2" name="password2" placeholder="Повтор пароля" autocomplete="new-password" /></div>
					<div id="password2.msg" class="c-red"></div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="user_steamid" class="col-sm-3 control-label">{help_icon title="SteamID" message="STEAM_0:X:Y или Community ID (7656…)"} SteamID</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="text" class="form-control" id="user_steamid" name="user_steamid" placeholder="STEAM_0:0:12345" /></div>
					<div id="steam.msg" class="c-red"></div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="user_email" class="col-sm-3 control-label">{help_icon title="E-mail" message="Нужен для восстановления пароля"} E-mail</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="email" class="form-control" id="user_email" name="user_email" placeholder="mail@example.com" autocomplete="email" /></div>
					<div id="email.msg" class="c-red"></div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="discord" class="col-sm-3 control-label">{help_icon title="Discord" message="Необязательно"} Discord</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="text" class="form-control" id="discord" name="discord" placeholder="name#0000 или username" /></div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="vk" class="col-sm-3 control-label">{help_icon title="VK" message="Только ID профиля, не полная ссылка"} VK (ID)</label>
				<div class="col-sm-9">
					<div class="fg-line"><input type="text" class="form-control" id="vk" name="vk" placeholder="123456789" /></div>
				</div>
			</div>
			{if $servers == ""}
			<div class="form-group m-b-5">
				<label class="col-sm-3 control-label">Серверы</label>
				<div class="col-sm-9">
					{foreach from=$server_list item=server}
					<div class="checkbox m-b-10">
						<label for="s_{$server.sid}">
							<input type="checkbox" name="servers[]" id="s_{$server.sid}" value="s{$server.sid}" />
							<i class="input-helper"></i>
							<span id="sa{$server.sid}"><i>{$server.ip}:{$server.port}</i></span>
						</label>
					</div>
					{/foreach}
					<p class="c-gray m-t-5">Отметьте серверы, на которых нужны права. Если список пуст — обратитесь к администрации.</p>
				</div>
			</div>
			{/if}
		</div>
	</div>
	{$server_script}
	{literal}
	<script>
	function AddAdmin_pay() {
		var g = function(id){ var e = document.getElementById(id); return e ? e.value : ''; };
		var svr = '';
		var el = document.getElementsByName('servers[]');
		for (var i = 0; i < el.length; i++) {
			if (el[i].checked) svr += ',' + el[i].value;
		}
		xajax_AddAdmin_pay('', '', g('user_login'), g('user_steamid'), g('user_email'),
			g('password'), g('password2'), '', '', '-1', 0, 0, '', svr,
			g('discord'), '', g('vk'), {/literal}{$klu4ik_js}{literal});
	}
	</script>
	{/literal}
	<div class="card-body card-padding text-center">
		{sb_button text="Активировать" onclick="AddAdmin_pay();" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="pay_send_activ" submit=false}
		&nbsp;
		<a class="btn bgm-bluegray btn-icon-text waves-effect" href="index.php?p=pay"><i class="zmdi zmdi-undo"></i> Другой ваучер</a>
	</div>
	{/if}
</div>
