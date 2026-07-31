{if NOT $permission_addgroup}
	Доступ запрещён!
{else}
	<div class="card banlist-panel admin-form" id="add-group">
		<div class="form-horizontal" role="form" id="group.details">
			<div class="card-header">
				<h2>Новая группа
					<small>Веб, серверные админы или группа серверов</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0">
				<div class="form-group m-b-5">
					<label for="groupname" class="col-sm-3 control-label">{help_icon title="Имя группы" message="Введите имя для новой группы."} Имя группы</label>
					<div class="col-sm-9">
						<div class="fg-line">
							<input type="text" TABINDEX=1 class="form-control" id="groupname" name="groupname" placeholder="Введите данные">
						</div>
						<div id="name.msg"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="grouptype" class="col-sm-3 control-label">{help_icon title="Тип группы" message="Выберите тип группы. Это поможет идентифицировать и разделить группы по категориям."} Тип группы</label>
					<div class="col-sm-3 p-t-5">
						<select class="selectpicker" onchange="UpdateGroupPermissionCheckBoxes()" name="grouptype" id="grouptype">
							<option value="0">Выберите...</option>
							<option value="1">Веб-группа</option>
							<option value="2">Серверная группа админов</option>
							<option value="3">Группа серверов</option>
						</select>
						<div id="type.msg" class="badentry"></div>
					</div>
				</div>
				<div class="form-group m-b-5">
					<label for="grouptype" class="col-sm-3 control-label"></label>
					<div class="col-sm-9 p-t-5 p-l-0">
						<div id="perms"></div>
					</div>
				</div>
			</div>
				
			<div style="display:block; width:100%; height:20px; line-height:20px; font-size:1px;">&nbsp;</div>
			<div class="card-body card-padding text-center">
				{sb_button text="Сохранить" onclick="ProcessGroup();" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="agroup" submit=false}
				&nbsp;
				{sb_button text="Назад" onclick="sbAdminBack(0, 'admin/groups')" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="aback"}
			</div>
		</div>
	</div>
{/if}
