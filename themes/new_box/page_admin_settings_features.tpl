<form action="" method="post">
    <input type="hidden" name="settingsGroup" value="features" />
    <div class="card banlist-panel admin-form" id="group.features">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Опции
					<small>Кик, групповые баны, рехеш и прочее · подсказки на «?»</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0">
			
				<div class="form-group m-b-5">
					<label for="export_public" class="col-sm-3 control-label">{help_icon title="Экспорт банов" message="Публичная выгрузка списка банов для скачивания."} Экспорт банов</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="export_public">
								<input type="checkbox" name="export_public" id="export_public" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="enable_kickit" class="col-sm-3 control-label">{help_icon title="Кик при бане" message="Кикать игрока с сервера сразу после добавления бана в базу."} Кик при бане</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="enable_kickit">
								<input type="checkbox" name="enable_kickit" id="enable_kickit" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="enable_groupbanning" class="col-sm-3 control-label">{help_icon title="Групповые баны" message="Бан целой Steam-группы игрока."} Групповые баны</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="enable_groupbanning">
								<input type="checkbox" name="enable_groupbanning" id="enable_groupbanning" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
							<div id="enable_groupbanning.msg"></div>
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="enable_friendsbanning" class="col-sm-3 control-label">{help_icon title="Баны друзей" message="Бан всех Steam-друзей выбранного игрока."} Баны друзей</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="enable_friendsbanning">
								<input type="checkbox" name="enable_friendsbanning" id="enable_friendsbanning" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
							<div id="enable_friendsbanning.msg"></div>
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="enable_adminrehashing" class="col-sm-3 control-label">{help_icon title="Авто-рехеш админов" message="Перезагружать права на серверах при любом изменении админов или групп."} Авто-рехеш админов</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="enable_adminrehashing">
								<input type="checkbox" name="enable_adminrehashing" id="enable_adminrehashing" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
							<div id="enable_adminrehashing.msg"></div>
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="enable_admininfo" class="col-sm-3 control-label">{help_icon title="Информация об Администраторе" message="Показывает информацию(discord, вк, STEAMID) о забанившем игрока Администраторе в банлисте или мут/гаг листе."} Информация об администраторе</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="enable_admininfo">
								<input type="checkbox" name="enable_admininfo" id="enable_admininfo" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
						</div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="allow_admininfo" class="col-sm-3 control-label">{help_icon title="Смена информации админом" message="Разрешить пользователю самому менять VK или Discord в своем профиле?"} Смена своей информации Админом</label>
					<div class="col-sm-9">
						<div class="checkbox m-b-15">
							<label for="allow_admininfo">
								<input type="checkbox" name="allow_admininfo" id="allow_admininfo" value="on" />
								<i class="input-helper"></i><span class="sr-only">вкл.</span>
							</label>
						</div>
					</div>
				</div>
				
				<div class="form-group m-b-5">
					<label for="moder_group_st" class="col-sm-3 control-label">{help_icon title="Модерирование группы" message="Система которая позволяет администратору редактировать вообще любые баны(при условии, что ему разрешено редактировать все баны), но только на том сервере, где у этого права администратора. Полезно будет тем, у кого есть такие должности как 'управляющий сервером', чтобы администраторы в этой группе могли редактировать только баны на тех серверах, где они управляют."} Модерировать группу</label>
					<div class="col-sm-3 p-t-5">
						<select class="selectpicker" name="moder_group_st" id="moder_group_st">
							<option value="0">Отключить</option>
							{foreach from=$wgroups item=gr}
								<option value="{$gr.gid}"{if $gr.gid == $config_modergroup} selected="selected"{/if}>{$gr.name}</option>
							{/foreach}
						</select>
					</div>
				</div>
				
				{display_material_checkbox name="old_serverside" help_title="Режим совместимости с плагинами SB" help_text="Переключает веб-панель в режим совместимости со старой серверной частью SourceBans."}
				
				{display_material_checkbox name="map_autofetch" help_title="Превью карт с GameTracker" help_text="Если у карты нет вручную загруженной иконки в images/maps, браузер подгружает превью напрямую с GameTracker (без кеша на диске сервера). Если картинки там нет — показывается локальный unknown map (nomap.jpg)."}

				{display_material_checkbox name="totp_enforce_owner" help_title="Обязательная 2FA для OWNER" help_text="Владельцы панели (флаг OWNER) не смогут завершить вход по паролю или Steam, пока не настроят TOTP. Остальные админы подключают 2FA добровольно в разделе «Аккаунт»."}
				
				<div class="form-group form-inline m-b-5">
					<label for="admin_warns" class="col-sm-3 control-label">{help_icon title="Предупреждения" message="Позволяет включить систему предупреждений для Администраторов."} Предупреждения</label>
					
					<div class="col-sm-1 p-t-10">
						<div class="toggle-switch p-b-5" data-ts-color="red">
							<input type="checkbox" id="admin_warns" name="admin_warns" value="on" /> 
							<label for="admin_warns" class="ts-helper checkbox-inline m-r-20" style="z-index:2;"></label>
						</div>
					</div>
					
					<div class="col-sm-3">
						<div class="fg-line">
							<input type="text" class="form-control" id="admin_warns_max" name="admin_warns_max" placeholder="Максимальное кол-во предупреждений" value="{$maxWarnings}" style="width: 100%;" />
						</div>
					</div>
				</div>
			</div>

			<div style="display:block; width:100%; height:20px; line-height:20px; font-size:1px;">&nbsp;</div>
			<div class="card-body card-padding text-center">
				{sb_button text="Сохранить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="fsettings" submit=true}
				&nbsp;
				{sb_button text="Назад" onclick="sbGo('index.php?p=admin&c=settings')" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="fback"}
			</div>
		</div>
	</div>
</form>

{if $old_serverside}<script>document.getElementById('old_serverside').checked = true;</script>{/if}
{if $map_autofetch}<script>document.getElementById('map_autofetch').checked = true;</script>{/if}
{if $totp_enforce_owner}<script>document.getElementById('totp_enforce_owner').checked = true;</script>{/if}
{if $warnings_enabled}<script>document.getElementById('admin_warns').checked = true;</script>{/if}

{literal}
<script>document.getElementById('admin_warns').onclick = function() {
    document.getElementById('admin_warns_max').disabled = !document.getElementById('admin_warns').checked;
}
document.getElementById('admin_warns').onclick();</script>
{/literal}
