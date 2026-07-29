<div class="card banlist-panel admin-form">
	<div class="card-header">
		<h2>Конфиг БД
			<small>Вставить в databases.cfg на игровом сервере</small>
		</h2>
	</div>
	<div class="parsec-note parsec-note-muted m-b-0 admin-manage-hint">
		Путь: <code class="admin-manage-code">/[mod]/addons/sourcemod/configs/databases.cfg</code>
	</div>
	<div class="card-body card-padding">
		<div class="form-group m-b-0">
			<div class="fg-line">
				<textarea class="form-control" rows="23" readonly>{$conf}</textarea>
			</div>
		</div>
	</div>
	<div class="card-body card-padding text-center admin-manage-footer">
		{sb_button text="Назад" onclick="window.location.href='index.php?p=admin&c=servers'" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="aconf" submit=false}
	</div>
</div>
