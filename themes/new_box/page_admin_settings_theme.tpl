<form action="" method="post" id="theme_form">
	<input type="hidden" name="settingsGroup" value="mainsettings_themes" />
	<div class="card banlist-panel admin-form">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Уведомления
					<small>Тексты для главной, мониторинга и профиля. Пустое поле = уведомление выключено.</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0">
				<div class="form-group m-b-5">
					<label for="yvedom_1" class="col-sm-3 control-label">{help_icon title="Уведомление" message="Показывается при заходе на главную страницу."} На главной</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" class="form-control" id="yvedom_1" name="yvedom_1" {if $config_text_home != ""}value="{$config_text_home|escape}"{else}placeholder="Необязательно"{/if} />
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="yvedom_2" class="col-sm-3 control-label">{help_icon title="Уведомление" message="Показывается администратору на странице мониторинга."} В мониторинге</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" class="form-control" id="yvedom_2" name="yvedom_2" {if $config_text_mon != ""}value="{$config_text_mon|escape}"{else}placeholder="Необязательно"{/if} />
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="yvedom_3" class="col-sm-3 control-label">{help_icon title="Уведомление" message="Первое уведомление в профиле пользователя."} В профиле (1)</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" class="form-control" id="yvedom_3" name="yvedom_3" {if $config_text_acc != ""}value="{$config_text_acc|escape}"{else}placeholder="Необязательно"{/if} />
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="yvedom_4" class="col-sm-3 control-label">{help_icon title="Уведомление" message="Второе уведомление в профиле пользователя."} В профиле (2)</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" class="form-control" id="yvedom_4" name="yvedom_4" {if $config_text_acc2 != ""}value="{$config_text_acc2|escape}"{else}placeholder="Необязательно"{/if} />
						</div>
					</div>
				</div>
			</div>
			<div class="card-body card-padding text-center">
				{sb_button text="Сохранить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="tsettings" submit=true}
				&nbsp;
				{sb_button text="Назад" onclick="sbGo('index.php?p=admin&c=settings')" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="tback"}
			</div>
		</div>
	</div>
</form>
