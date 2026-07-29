<form action="" method="post">
	<div class="card" id="admin-page-content">
		<input type="hidden" name="Link" value="edit" />
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Меню <small>Позволяет управлять ссылками в главном меню SourceBans.</small></h2>
			</div>
			<div class="alert alert-info" role="alert">Иконку выбери кликом из списка — не нужно вбивать классы вручную. «Авто» подставит иконку по URL/названию.
			{if $system}<br /><br />Ссылка <i>системная</i>: URL и удаление недоступны.{/if}</div>
			<div class="card-body card-padding p-b-0">
				<div class="form-group m-b-5">
					<label class="col-sm-3 control-label">{help_icon title="Иконка" message="Клик по иконке выбирает её для пункта меню."} Иконка</label>
					<div class="col-sm-9">
						{$menu_icon_picker}
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="menu_group" class="col-sm-3 control-label">{help_icon title="Раздел" message="В какой блок бокового меню попадет ссылка."} Раздел</label>
					<div class="col-sm-9">
						{$menu_group_picker}
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="names_link" class="col-sm-3 control-label">{help_icon title="Заголовок" message="Введите заголовок названия ссылки. Грубо говоря 'Имя' ссылки."} Заголовок</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="names_link" name="names_link" value="{$text|escape}" placeholder="Введите данные" />
						</div>
						<div id="names_link.msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="des_link" class="col-sm-3 control-label">{help_icon title="Описание" message="Введите описание ссылки, которое вылазиет при наводе курсором мыши на ссылку."} Описание</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="des_link" name="des_link" value="{$des|escape}" placeholder="Введите данные" />
						</div>
						<div id="des_link.msg"></div>
					</div>
				</div>
				{if $system}<input type="hidden" name="url_link" value="{$url|escape}" />{else}<div class="form-group m-b-5">
					<label for="url_link" class="col-sm-3 control-label">{help_icon title="Линк" message="Линк, на который переадресует пользователя, после нажатия на заголовок ссылки."} URL</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="url_link" name="url_link" value="{$url|escape}" placeholder="Введите данные" />
						</div>
						<div id="url_link.msg"></div>
					</div>
				</div>{/if}
				<div class="form-group m-b-5">
					<label for="priora_link" class="col-sm-3 control-label">{help_icon title="Приоритет" message="Приоритет ссылки, позволяет вставить ссылку в определенное место, тем самым сортируя показ ссылки в главном меню SourceBans."} Приоритет</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="priora_link" name="priora_link" value="{$prior|escape}" placeholder="Введите данные" />
						</div>
						<div id="priora_link.msg"></div>
					</div>
				</div>
				{display_material_checkbox name="on_link" help_title="Статус" help_text="Показывать пункт в боковом меню SourceBans."}
				{display_material_checkbox name="onNewTab" help_title="Открывать в новой вкладке" help_text="При щелчке по пункту в меню, он будет открываться в новой вкладке браузера, если здесь установлена галочка."}
				
			</div>
			<div class="card-body card-padding text-center">
				{sb_button text="Сохранить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" submit=true}
			    &nbsp;
			    {sb_button text="Назад" onclick="window.location.href='index.php?p=admin&c=menu'" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-lightblue btn-icon-text" id="back" submit=false}
			</div>
		</div>
	</div>
	
</form>
