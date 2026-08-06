<div id="0">
	<div class="card banlist-panel admin-manage">
		<div class="card-header">
		<h2>Меню
			<small>Ссылки в боковой панели</small>
		</h2>
		</div>

		<div class="table-responsive">
				<table class="table table-striped parsec-table banlist-table admin-manage-table">
					<thead>
						<tr>
							<th width="8%">Иконка</th>
							<th width="10%">Статус</th>
							<th width="12%">Раздел</th>
							<th>Заголовок</th>
							<th>Описание</th>
							<th>URL</th>
							<th width="28%" class="text-right">Действия</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$list item=menu}
							<tr class="banlist-row admin-manage-row">
								<td class="admin-menu-icon-cell">
									{if $menu.icon_class}
										<i class="{$menu.icon_class|escape}" title="{$menu.icon_class|escape}"></i>
									{else}
										<span class="parsec-muted" title="Авто">A</span>
									{/if}
								</td>
								<td>
									{if $menu.enabled == "1"}
										<span class="parsec-chip parsec-chip-fp">вкл</span>
									{else}
										<span class="parsec-chip parsec-chip-esc">выкл</span>
									{/if}
									<span class="parsec-muted" title="Приоритет">#{$menu.priority}</span>
								</td>
								<td><span class="parsec-muted">{$menu.group_label|escape}</span></td>
								<td>{$menu.text_plain|escape:'html'|stripslashes}</td>
								<td>{$menu.description|escape:'html'|stripslashes}</td>
								<td><a href="{$menu.url}">{$menu.url}</a></td>
								<td class="text-right">
									<span class="admin-actions">
										<a class="admin-action" href="admin/menu?o=edit&amp;id={$menu.id}">Изменить</a>
										{if $menu.enabled != "1"}
											<a class="admin-action" href="admin/menu?o=on&amp;id={$menu.id}">Вкл</a>
										{else}
											<a class="admin-action" href="admin/menu?o=off&amp;id={$menu.id}">Выкл</a>
										{/if}
										{if $menu.system != "1"}
											<a class="admin-action admin-action--danger" href="admin/menu?o=del&amp;id={$menu.id}">Удалить</a>
										{/if}
									</span>
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
		</div>
	</div>
</div>

<form action="" method="post">
	<div class="card banlist-panel admin-form" id="admin-page-content">
		<div id="1" style="display:none;">
		<input type="hidden" name="Link" value="add" />
		<div class="form-horizontal" role="form" id="add-group">
			<div class="card-header">
				<h2>Новая ссылка
					<small>Заголовок, URL и приоритет</small>
				</h2>
			</div>
			<div class="parsec-note parsec-note-muted m-b-0 admin-manage-hint">
				Иконку выбери кликом из списка ниже. «Авто» — подберётся сама по URL/названию.
			</div>
			<div class="card-body card-padding p-b-0" id="group.details">
				<div class="form-group m-b-5">
					<label class="col-sm-3 control-label">{help_icon title="Иконка" message="Выберите иконку из набора Material Design Iconic Font. «Авто» — иконка подставится сама."} Иконка</label>
					<div class="col-sm-9">
						{$menu_icon_picker}
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="menu_group" class="col-sm-3 control-label">{help_icon title="Раздел" message="В какой блок бокового меню попадет ссылка: Сайт, Инструменты, Сообщество, Админка."} Раздел</label>
					<div class="col-sm-9">
						{$menu_group_picker}
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="names_link" class="col-sm-3 control-label">{help_icon title="Заголовок" message="Введите заголовок названия ссылки. Грубо говоря 'Имя' ссылки."} Заголовок</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="names_link" name="names_link" placeholder="Введите данные" />
						</div>
						<div id="names_link.msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="des_link" class="col-sm-3 control-label">{help_icon title="Описание" message="Введите описание ссылки, которое вылазиет при наводе курсором мыши на ссылку."} Описание</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="des_link" name="des_link" placeholder="Введите данные" />
						</div>
						<div id="des_link.msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="url_link" class="col-sm-3 control-label">{help_icon title="Линк" message="Линк, на который переадресует пользователя, после нажатия на заголовок ссылки."} URL</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="url_link" name="url_link" placeholder="Введите данные" />
						</div>
						<div id="url_link.msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="priora_link" class="col-sm-3 control-label">{help_icon title="Приоритет" message="Приоритет ссылки, позволяет вставить ссылку в определенное место, тем самым сортируя показ ссылки в главном меню SourceBans."} Приоритет</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="priora_link" name="priora_link" placeholder="Введите данные" />
						</div>
						<div id="priora_link.msg"></div>
					</div>
				</div>
				{display_material_checkbox name="on_link" help_title="Статус" help_text="Показывать пункт в боковом меню SourceBans."}
				{display_material_checkbox name="onNewTab" help_title="Открывать в новой вкладке" help_text="При щелчке по пункту в меню, он будет открываться в новой вкладке браузера, если здесь установлена галочка."}
				
			</div>
			<div class="card-body card-padding text-center">
				{sb_button text="Добавить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" submit=true}
			    &nbsp;
			    {sb_button text="Назад" onclick="sbGo('admin/menu')" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="back" submit=false}
			</div>
		</div>
		</div>
	</div>
	
</form>
