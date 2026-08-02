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
		<div class="totp-setup m-b-20">
			<p class="m-b-15">1. Отсканируйте QR в Google Authenticator / Aegis / 1Password.</p>
			<div id="totp-qr" class="totp-qr m-b-15" style="display:inline-block;padding:12px;background:#fff;border-radius:4px;" data-otpauth="{$totp_otpauth|escape}"></div>
			<p class="m-b-5 text-muted">Если камеры нет — введите секрет вручную:</p>
			<p class="m-b-10"><code id="totp-secret-text" style="font-size:1.05em;letter-spacing:.04em;word-break:break-all;">{$totp_secret|escape}</code></p>
			<p class="m-b-20"><a class="btn btn-link btn-sm" href="{$totp_otpauth|escape}">Открыть otpauth:// на этом устройстве</a></p>
		</div>
		<form method="post" action="login2fa" class="form-horizontal m-t-10">
			{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
			<input type="hidden" name="totp_enroll" value="1" />
			<div class="form-group">
				<label for="code" class="col-sm-3 control-label">2. Код подтверждения</label>
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
		<script src="./scripts/qrcode.min.js"></script>
		{literal}
		<script>
		(function () {
			var box = document.getElementById('totp-qr');
			if (!box || typeof QRCode === 'undefined') return;
			var uri = box.getAttribute('data-otpauth') || '';
			if (!uri) return;
			box.innerHTML = '';
			new QRCode(box, { text: uri, width: 192, height: 192, correctLevel: QRCode.CorrectLevel.M });
		})();
		</script>
		{/literal}
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
