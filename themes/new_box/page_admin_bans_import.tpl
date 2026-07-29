{if NOT $permission_import}
	Доступ запрещен!
{else}

<div class="card banlist-panel admin-form ban-import">
	<div class="form-horizontal" role="form">
		<div class="card-header">
			<h1>Импорт банов
				<small>Загрузка banned_users.cfg или banned_ip.cfg</small>
			</h1>
		</div>

		<div class="parsec-note parsec-note-muted m-b-0 admin-manage-hint">
			Подсказка у поля — наведи на знак вопроса. Импорт добавляет баны из конфига SourceMod / сервера.
		</div>

		<div class="card-body card-padding p-b-0" id="group.details">
			<form action="" method="post" enctype="multipart/form-data" class="ban-import-form">
				<input type="hidden" name="action" value="importBans" />

				<div class="form-group m-b-5">
					<label for="importFile" class="col-sm-3 control-label">{help_icon title="Файл" message="Выберите файл banned_users.cfg или banned_ip.cfg для загрузки и импорта банов."} Файл</label>
					<div class="col-sm-9">
						<div class="fileinput fileinput-new ban-import-file" data-provides="fileinput">
							<span class="btn bgm-blue btn-file waves-effect">
								<span class="fileinput-new"><i class="zmdi zmdi-upload"></i> Выбрать файл</span>
								<span class="fileinput-exists"><i class="zmdi zmdi-refresh"></i> Изменить</span>
								<input name="importFile" id="importFile" type="file" accept=".cfg,text/plain" />
							</span>
							<span class="fileinput-filename ban-import-filename"></span>
							<a href="#" class="close fileinput-exists" data-dismiss="fileinput" title="Убрать файл" aria-label="Убрать файл">×</a>
						</div>
						<div id="file.msg" class="badentry"></div>
						<p class="ban-import-hint">Обычно: <code>banned_users.cfg</code> или <code>banned_ip.cfg</code></p>
					</div>
				</div>

				<div class="form-group m-b-5">
					<label for="friendsname" class="col-sm-3 control-label">{help_icon title="Получить имена" message="Поставьте флажок, если хотите подтянуть ники из Steam-профилей. Работает с banned_users.cfg."} Получить имена</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="friendsname">
								<input type="checkbox" name="friendsname" id="friendsname" hidden="hidden" />
								<i class="input-helper"></i> Включить запрос ников из Steam
							</label>
						</div>
					</div>
				</div>

				<div class="form-group m-b-5 account-form-actions">
					<label class="col-sm-3 control-label"></label>
					<div class="col-sm-9">
						{sb_button text="Импортировать баны" icon="<i class='zmdi zmdi-upload'></i>" class="bgm-blue btn-icon-text" id="iban" submit=true}
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

{if !$extreq}
<script type="text/javascript">
	if ($('friendsname')) $('friendsname').disabled = true;
</script>
{/if}
{/if}
