<div class="row">
<div class="card">
	<div class="card-header">
		{if $login2fa_mode == 'enroll'}
		<h1>Настройка 2FA <small>Для владельца панели обязательна двухфакторная аутентификация (TOTP).</small></h1>
		{elseif $login2fa_mode == 'recovery'}
		<h1>2FA включена <small>Сохраните recovery-коды — они показываются один раз.</small></h1>
		{else}
		<h1>Подтверждение входа <small>Введите код из приложения-аутентификатора для {$totp_user|escape}.</small></h1>
		{/if}
	</div>
	<div class="card-body card-padding">
		{if $error}
		<div class="alert alert-danger" role="alert">{$error|escape}</div>
		{/if}

		{if $login2fa_mode == 'recovery'}
		<div class="alert alert-warning" role="alert">
			<p>Запишите эти коды в надёжное место. Каждый код можно использовать один раз, если нет доступа к приложению.</p>
			<ul class="list-unstyled" style="font-family:monospace;font-size:1.1em;">
			{foreach from=$recovery_codes item=c}
				<li>{$c|escape}</li>
			{/foreach}
			</ul>
		</div>
		<p><a class="btn bgm-blue btn-icon-text waves-effect" href="account"><i class="zmdi zmdi-check"></i> Продолжить</a></p>
		{elseif $login2fa_mode == 'enroll'}
		<p>1. Добавьте аккаунт в Google Authenticator / Aegis / 1Password.</p>
		<p>Секрет (ручной ввод): <code style="font-size:1.15em;letter-spacing:.05em;">{$totp_secret|escape}</code></p>
		<p><a href="{$totp_otpauth|escape}">Открыть otpauth:// в приложении</a></p>
		<form method="post" action="login2fa" class="form-horizontal m-t-20">
			{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
			<input type="hidden" name="totp_enroll" value="1" />
			<div class="form-group">
				<label for="code" class="col-sm-3 control-label">Код подтверждения</label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="6 цифр" required>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-6">
					<button type="submit" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-shield-check"></i> Включить 2FA и войти</button>
				</div>
			</div>
		</form>
		{else}
		<form method="post" action="login2fa" class="form-horizontal">
			{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
			<input type="hidden" name="totp_challenge" value="1" />
			<div class="form-group">
				<label for="code" class="col-sm-3 control-label">Код 2FA</label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="16" placeholder="6 цифр или recovery-код" required autofocus>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-6">
					<button type="submit" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-long-arrow-tab"></i> Подтвердить</button>
					<a class="btn btn-link" href="login">Отмена</a>
				</div>
			</div>
		</form>
		{/if}
	</div>
</div>
</div>
