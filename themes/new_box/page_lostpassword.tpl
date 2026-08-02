<div class="card" id="lostpassword">
	{if $lostpass_confirm}
	<div class="form-horizontal" role="form" id="login-content">
		<div class="card-header">
			<h2>Подтверждение сброса пароля <small>Нажмите кнопку ниже, чтобы сгенерировать новый пароль и получить его на почту. Простой переход по ссылке пароль не меняет.</small></h2>
		</div>
		<form method="post" action="index.php?p=lostpassword" class="card-body card-padding text-center" id="loginSubmit">
			{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
			<input type="hidden" name="email" value="{$lostpass_email|escape}" />
			<input type="hidden" name="validation" value="{$lostpass_validation|escape}" />
			<input type="hidden" name="confirm_reset" value="1" />
			{sb_button text="Подтвердить сброс пароля" icon="<i class='zmdi zmdi-key'></i>" class="bgm-blue btn-icon-text" id=aconfirm submit=true}
		</form>
	</div>
	{else}
	<div class="form-horizontal" role="form" id="login-content">
		<div class="card-header">
			<h2>Восстановление пароля <small>Впишите в поле ваш E-mail, чтобы на него отправилось подтверждение о сбросе пароля.</small></h2>
		</div>
		<div class="alert alert-success" role="alert" id="msg-blue" style="display:none;">Если этот e-mail есть в системе, мы отправили письмо со ссылкой для сброса пароля. Проверьте также папку «Спам».</div>
		<div class="alert alert-danger" role="alert" id="msg-red" style="display:none;">Не удалось обработать запрос. Проверьте адрес и попробуйте позже.</div>
		<div class="card-body card-padding p-b-0">
			<div class="form-group m-b-5" id="loginPasswordDiv">
				<label for="email" class="col-sm-3 control-label">E-Mail</label>
				<div class="col-sm-9">
					<div class="fg-line">
						<input type="text" TABINDEX=1 class="form-control" id="email" name="email" placeholder="Введите данные" autocomplete="email">
					</div>
				</div>
			</div>
		</div>
		
		<div class="card-body card-padding text-center" id="loginSubmit">
			{sb_button text="Сбросить" onclick="xajax_LostPassword($('email').value);" icon="<i class='zmdi zmdi-key'></i>" class="bgm-blue btn-icon-text" id=alogin submit=false}
		</div>
		
	</div>
	{/if}
</div>
