<div class="card">
	<div class="form-horizontal" role="form" id="submit-main">
		<div class="card-header">
			<h2>Пожаловаться на игрока</h2>
		</div>
		<div class="alert alert-info" role="alert">Здесь Вы можете подать заявку на бан игрока, нарушающего правила сервера. Когда подаёте заявку, заполняйте все поля, и донесите Ваш комментарий максимально информативно. Это послужит залогом скорейшего рассмотрения Вашей заявки.
		Краткая инструкция по записи демо <a href="javascript:void(0)" onclick="ShowBox('Как записать Демку?', 'В тот момент, когда Вы наблюдаете за нужным игроком, нажмите <b>~</b> (</b>`</b>/<b>Ё</b>) на Вашей клавиатуре. В открывшуюся консоль введите <b>record [demoname]</b> и нажмите <b>Enter</b>. Также пропишите команду <b>status</b> для получения дополнительной информации о сервере. Чтобы остановить запись, введите <b>stop</b>. Файл демки будет лежать в папке <b>cstrike</b>.', 'red', '', true);">здесь</a>
		</div>
			<form action="index.php?p=submit" method="post" enctype="multipart/form-data">
			<input type="hidden" name="subban" value="1">
			{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
		<div class="card-body card-padding p-b-0">
			<div class="form-group m-b-5">
				<label for="SteamID" class="col-sm-3 control-label">SteamID нарушителя:</label>
				<div class="col-sm-9">
					<div class="fg-line">
					<input type="text" class="form-control" id="SteamID" name="SteamID" value="{$STEAMID|escape}" placeholder="STEAM_0:…">
					</div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="BanIP" class="col-sm-3 control-label">IP нарушителя:</label>
				<div class="col-sm-9">
					<div class="fg-line">
						<input type="text" TABINDEX=1 class="form-control" id="BanIP" name="BanIP" value="{$ban_ip|escape}" placeholder="Введите данные">
					</div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="PlayerName" class="col-sm-3 control-label">Никнейм нарушителя<span class="mandatory">*</span>:</label>
				<div class="col-sm-9">
					<div class="fg-line">
					<input type="text" class="form-control" id="PlayerName" name="PlayerName" value="{$player_name|escape}" placeholder="Введите данные">
					</div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="BanReason" class="col-sm-3 control-label">Комментарий<span class="mandatory">*</span>:</label>
					<div class="col-sm-9">
					<div class="fg-line">
					<textarea class="form-control auto-size" id="BanReason" name="BanReason" placeholder="Пожалуйста, пишите информативные комментарии. Комментарии типа 'читер' не рассматриваются" style="overflow: hidden; word-wrap: break-word; height: 43px;">{$ban_reason|escape}</textarea>
					</div>
				</div>
			</div>
			<div class="form-group m-b-5">
					<label for="SubmitName" class="col-sm-3 control-label">Ваш ник:</label>
				<div class="col-sm-9">
					<div class="fg-line">
					<input type="text" class="form-control" id="SubmitName" name="SubmitName" value="{$subplayer_name|escape}" placeholder="Введите данные">
					</div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="EmailAddr" class="col-sm-3 control-label">Ваш Email<span class="mandatory">*</span>:</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="EmailAddr" name="EmailAddr" value="{$player_email|escape}" placeholder="Введите данные">
				</div>
			</div>
			<div class="form-group m-b-5">
				<label for="server" class="col-sm-3 control-label">Сервер<span class="mandatory">*</span>:</label>
				<div class="col-sm-3">
				<select class="selectpicker" id="server" name="server">
						<option value="-1">Выберите сервер</option>
					{foreach from=$server_list item=server}
						<option value="{$server.sid}" {if $server_selected == $server.sid}selected{/if}>{$server.HostName|escape}</option>
					{/foreach}
						<option value="0">Другой сервер, не представленный здесь</option>
					</select> 
				</div>
			</div>
			<div class="form-group m-b-5">
				<label class="col-sm-3 control-label">Загрузка демо:</label>
				<div class="fileinput fileinput-new" data-provides="fileinput">
					<span class="btn btn-primary btn-file m-r-10">
						<span class="fileinput-new">Выберите файл</span>
							<input type="file" name="demo_file">
					</span>
					<span class="fileinput-filename"></span>
					<a href="#" class="close fileinput-exists" data-dismiss="fileinput">&times;</a>
				</div>
			</div>
			<div class="form-group m-b-15">
				<label for="kapcha" class="col-sm-3 control-label">Проверочный код<span class="mandatory">*</span>:</label>
				<div class="col-sm-9">
					<div class="voucher-captcha-row">
						<img id="submit_captcha_img" class="voucher-captcha-img" src="includes/captcha/captcha.php?t={$smarty.now}" width="170" height="56" alt="Капча" />
						<button type="button" class="btn btn-default btn-icon waves-effect" title="Обновить код" onclick="var i=document.getElementById('submit_captcha_img'); if(i) i.src='includes/captcha/captcha.php?t='+Date.now();">
							<i class="zmdi zmdi-refresh-alt"></i>
						</button>
						<div class="fg-line voucher-captcha-input">
							<input type="text" class="form-control" id="kapcha" name="kapcha" placeholder="Символы с картинки" required maxlength="8" autocomplete="off" />
						</div>
					</div>
				</div>
			</div>
			<div class="card-body card-padding">
					<p>Примечание: Только форматы .dem, <a href="http://www.winzip.com" target="_blank">.zip</a>, <a href="http://www.rarlab.com" target="_blank">.rar</a>, <a href="http://www.7-zip.org" target="_blank">.7z</a>, <a href="http://www.bzip.org" target="_blank">.bz2</a> или <a href="http://www.gzip.org" target="_blank">.gz</a>.</p>
				<p><span class="mandatory">*</span> = Обязательные поля</p>
			</div>
		<div class="card-body card-padding text-center">
			{sb_button text=Отправить onclick="" icon="<i class='zmdi zmdi-shield-security'></i>" class="bgm-blue btn-icon-text" id=save submit=true}
		</div>
		</div>
		<div class="alert alert-info" role="alert">Что случится, если кто-то окажется забаненным?</b><br />
		Если кто-то получает бан, то его уникальный STEAMID или IP заносятся в Базу Данных SourceBans, и каждый раз, когда игрок попытается подключиться к серверу, он/она будут блокироваться с уведомлением о бане.
		</div>
	</div>
</div>
