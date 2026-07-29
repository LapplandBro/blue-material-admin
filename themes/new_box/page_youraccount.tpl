<div class="admin-content account-page" id="admin-page-content">

<div id="0">
	<div class="card banlist-panel admin-form account-card">
		<div class="card-header">
			<h1>Привилегии
				<small>Права веб-панели, серверные флаги и срок доступа</small>
			</h1>
		</div>
		<div class="card-body card-padding account-info-body">
			<div class="account-summary">
				<div class="account-summary-item">
					<span class="account-summary-label">Логин</span>
					<span class="account-summary-value">-{$user_name|escape}-</span>
				</div>
				<div class="account-summary-item">
					<span class="account-summary-label">SteamID</span>
					<span class="account-summary-value account-summary-mono">-{$user_steam|escape}-</span>
				</div>
				<div class="account-summary-item">
					<span class="account-summary-label">E-mail</span>
					<span class="account-summary-value">-{$email|escape}-</span>
				</div>
				<div class="account-summary-item">
					<span class="account-summary-label">Срок</span>
					<span class="account-summary-value">-{$expired_time}-</span>
				</div>
			</div>

			<div class="account-perms">
				<div class="account-perms-col">
					<div class="account-perms-label">Веб-права</div>
					<ul class="account-perm-list">-{$web_permissions}-</ul>
				</div>
				<div class="account-perms-col">
					<div class="account-perms-label">Серверные права</div>
					<ul class="account-perm-list">-{$server_permissions}-</ul>
				</div>
			</div>
		</div>
	</div>
</div>

-{if $allow_change_inf}-
<div id="4" style="display:none;">
	<div class="card banlist-panel admin-form account-card">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Связь
					<small>Контакты для связи с вами</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0" id="group.details">
				<div class="form-group m-b-5">
					<label for="current_vk" class="col-sm-3 control-label">ВКонтакте</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" class="form-control" id="current_vk" name="current_vk" -{if NOT $vk}-placeholder="ID или короткое имя, без https://vk.com/"-{else}-value="-{$vk|escape}-"-{/if}->
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="current_discord" class="col-sm-3 control-label">Discord</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" class="form-control" id="current_discord" name="current_discord" -{if NOT $discord}-placeholder="Логин Discord"-{else}-value="-{$discord|escape}-"-{/if}->
						</div>
					</div>
				</div>
				<div class="form-group m-b-5 account-form-actions">
					<label class="col-sm-3 control-label"></label>
					<div class="col-sm-9">
						<button type="button" onclick="xajax_ChangeAdminsInfos(-{$user_aid}-, $('current_vk').value, $('current_discord').value);" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-check-all"></i> Сохранить</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
-{/if}-

<div id="1" style="display:none;">
	<div class="card banlist-panel admin-form account-card">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Пароль аккаунта
					<small>Смена пароля входа в веб-панель</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0" id="group.details">
				<div class="form-group m-b-5">
					<label for="current" class="col-sm-3 control-label">Текущий пароль</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" onblur="xajax_CheckPassword(-{$user_aid}-, $('current').value);" class="form-control" id="current" name="current" placeholder="Текущий пароль" autocomplete="current-password">
						</div>
						<div id="current.msg" class="account-field-msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="pass1" class="col-sm-3 control-label">-{help_icon title="Новый пароль" message="Минимальная длина: $min_pass_len символов."}- Новый пароль</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" onkeyup="checkYourAcctPass();" class="form-control" id="pass1" name="pass1" placeholder="Новый пароль" autocomplete="new-password">
						</div>
						<div id="pass1.msg" class="account-field-msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="pass2" class="col-sm-3 control-label">-{help_icon title="Повторите пароль" message="Повторите новый пароль ещё раз."}- Повторите пароль</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" onkeyup="checkYourAcctPass();" class="form-control" id="pass2" name="pass2" placeholder="Повтор пароля" autocomplete="new-password">
						</div>
						<div id="pass2.msg" class="account-field-msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5 account-form-actions">
					<label class="col-sm-3 control-label"></label>
					<div class="col-sm-9">
						<button type="button" onclick="xajax_CheckPassword(-{$user_aid}-, $('current').value);dispatch();" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-check-all"></i> Сохранить</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="2" style="display:none;">
	<div class="card banlist-panel admin-form account-card">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Серверный пароль
					<small>Пароль для прав администратора на игровых серверах · <a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords" target="_blank" rel="noopener">документация SourceMod</a></small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0">
				-{if $srvpwset}-
				<div class="form-group m-b-5">
					<label for="scurrent" class="col-sm-3 control-label">-{help_icon title="Текущий пароль" message="Введите текущий серверный пароль."}- Текущий пароль</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" onblur="xajax_CheckSrvPassword(-{$user_aid}-, $('scurrent').value);" class="form-control" id="scurrent" name="scurrent" placeholder="Текущий серверный пароль" autocomplete="off">
						</div>
						<div id="scurrent.msg" class="account-field-msg"></div>
					</div>
				</div>
				-{/if}-

				<div class="form-group m-b-5">
					<label for="spass1" class="col-sm-3 control-label">-{help_icon title="Новый пароль" message="Минимальная длина: $min_pass_len символов."}- Новый пароль</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" onkeyup="checkYourSrvPass();" id="spass1" class="form-control" name="spass1" placeholder="Новый серверный пароль" autocomplete="off">
						</div>
						<div id="spass1.msg" class="account-field-msg"></div>
					</div>
				</div>

				<div class="form-group m-b-5">
					<label for="spass2" class="col-sm-3 control-label">-{help_icon title="Подтвердите пароль" message="Повторите новый серверный пароль."}- Подтверждение</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" onkeyup="checkYourSrvPass();" id="spass2" class="form-control" name="spass2" placeholder="Повтор пароля" autocomplete="off">
						</div>
						<div id="spass2.msg" class="account-field-msg"></div>
					</div>
				</div>

				-{if $srvpwset}-
				<div class="form-group m-b-5">
					<label for="delspass" class="col-sm-3 control-label">-{help_icon title="Удалить пароль" message="Снять серверный пароль с аккаунта."}- Удалить пароль</label>
					<div class="col-sm-9 p-t-10">
						<div class="checkbox">
							<label>
								<input type="checkbox" id="delspass" name="delspass">
								<i class="input-helper"></i>
								Снять серверный пароль
							</label>
						</div>
					</div>
				</div>
				-{/if}-

				<div class="form-group m-b-5 account-form-actions">
					<label class="col-sm-3 control-label"></label>
					<div class="col-sm-9">
						<button type="button" onclick="-{if $srvpwset}-xajax_CheckSrvPassword(-{$user_aid}-, $('scurrent').value);-{/if}-srvdispatch();" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-check-all"></i> Сохранить</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="3" style="display:none;">
	<div class="card banlist-panel admin-form account-card">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>E-mail
					<small>Нужен для восстановления доступа к аккаунту</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0">
				<div class="form-group m-b-5">
					<label class="col-sm-3 control-label">-{help_icon title="Текущий E-Mail" message="Текущий адрес электронной почты."}- Текущий E-mail</label>
					<div class="col-sm-9">
						<div class="account-email-current">-{$email|escape}-</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="emailpw" class="col-sm-3 control-label">-{help_icon title="Пароль" message="Пароль от аккаунта веб-панели."}- Пароль</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="password" id="emailpw" class="form-control" name="emailpw" placeholder="Пароль аккаунта" autocomplete="current-password">
						</div>
						<div id="emailpw.msg" class="account-field-msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="email1" class="col-sm-3 control-label">-{help_icon title="Новый E-mail" message="Новый адрес электронной почты."}- Новый E-mail</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="email" id="email1" class="form-control" name="email1" placeholder="Новый E-mail" autocomplete="email">
						</div>
						<div id="email1.msg" class="account-field-msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="email2" class="col-sm-3 control-label">-{help_icon title="Подтвердить E-mail" message="Повторите новый адрес."}- Подтвердить E-mail</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="email" id="email2" class="form-control" name="email2" placeholder="Повтор E-mail" autocomplete="email">
						</div>
						<div id="email2.msg" class="account-field-msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5 account-form-actions">
					<label class="col-sm-3 control-label"></label>
					<div class="col-sm-9">
						<button type="button" onclick="checkmail();" class="btn bgm-blue btn-icon-text waves-effect"><i class="zmdi zmdi-check-all"></i> Сохранить</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
var error = 0;
function set_error(count) {
	error = count;
}
function checkYourAcctPass() {
	var err = 0;
	if ($('pass1').value.length < -{$min_pass_len}-) {
		$('pass1.msg').setStyle('display', 'block');
		$('pass1.msg').setHTML('Пароль должен быть не менее -{$min_pass_len}- символов');
		err++;
	} else {
		$('pass1.msg').setStyle('display', 'none');
	}
	if ($('pass2').value != "" && $('pass2').value != $('pass1').value) {
		$('pass2.msg').setStyle('display', 'block');
		$('pass2.msg').setHTML('Пароли не совпадают');
		err++;
	} else {
		$('pass2.msg').setStyle('display', 'none');
	}
	if (err > 0) {
		set_error(1);
		return false;
	}
	set_error(0);
	return true;
}
function dispatch() {
	if ($('current.msg').innerHTML == "Неверный пароль.") {
		if (typeof sbSiteAlert === 'function') sbSiteAlert("Неверный пароль", "Ошибка", "red");
		else if (typeof ShowBox === 'function') ShowBox("Ошибка", "Неверный пароль", "red", "", true);
		else alert("Неверный пароль");
		return false;
	}
	if (checkYourAcctPass() && error == 0) {
		xajax_ChangePassword(-{$user_aid}-, $('pass2').value);
	}
}
function checkYourSrvPass() {
	if (!$('delspass') || $('delspass').checked == false) {
		var err = 0;
		if ($('spass1').value.length < -{$min_pass_len}-) {
			$('spass1.msg').setStyle('display', 'block');
			$('spass1.msg').setHTML('Пароль должен быть не менее -{$min_pass_len}- символов');
			err++;
		} else {
			$('spass1.msg').setStyle('display', 'none');
		}
		if ($('spass2').value != "" && $('spass2').value != $('spass1').value) {
			$('spass2.msg').setStyle('display', 'block');
			$('spass2.msg').setHTML('Пароли не совпадают');
			err++;
		} else {
			$('spass2.msg').setStyle('display', 'none');
		}
		if (err > 0) {
			set_error(1);
			return false;
		}
		set_error(0);
		return true;
	}
	set_error(0);
	return true;
}
function srvdispatch() {
	-{if $srvpwset}-
	if ($('scurrent.msg').innerHTML == "Неверный пароль.") {
		if (typeof sbSiteAlert === 'function') sbSiteAlert("Неверный пароль", "Ошибка", "red");
		else if (typeof ShowBox === 'function') ShowBox("Ошибка", "Неверный пароль", "red", "", true);
		else alert("Неверный пароль");
		return false;
	}
	-{/if}-
	if (checkYourSrvPass() && error == 0 && (!$('delspass') || $('delspass').checked == false)) {
		xajax_ChangeSrvPassword(-{$user_aid}-, $('spass2').value);
	}
	if ($('delspass') && $('delspass').checked == true) {
		xajax_ChangeSrvPassword(-{$user_aid}-, 'NULL');
	}
}
function checkmail() {
	var err = 0;
	if ($('email1').value == "") {
		$('email1.msg').setStyle('display', 'block');
		$('email1.msg').setHTML('Введите новый E-mail.');
		err++;
	} else {
		$('email1.msg').setStyle('display', 'none');
	}
	if ($('email2').value == "") {
		$('email2.msg').setStyle('display', 'block');
		$('email2.msg').setHTML('Подтвердите новый E-mail.');
		err++;
	} else {
		$('email2.msg').setStyle('display', 'none');
	}
	if (err == 0 && $('email2').value != $('email1').value) {
		$('email2.msg').setStyle('display', 'block');
		$('email2.msg').setHTML('Введённые E-mail адреса не совпадают.');
		err++;
	}
	if ($('emailpw').value == "") {
		$('emailpw.msg').setStyle('display', 'block');
		$('emailpw.msg').setHTML('Введите ваш пароль.');
		err++;
	} else {
		$('emailpw.msg').setStyle('display', 'none');
	}
	if (err > 0) {
		set_error(1);
		return false;
	}
	set_error(0);
	if (error == 0) {
		xajax_ChangeEmail(-{$user_aid}-, $('email2').value, $('emailpw').value);
	}
}
</script>
</div>
