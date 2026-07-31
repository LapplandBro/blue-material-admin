<form action="" method="post">
	<div class="card banlist-panel admin-form" id="add-group">
		<div class="form-horizontal" role="form">
			<div class="card-header">
				<h2>Группы админа
					<small>Готовая группа или свои права ниже</small>
				</h2>
			</div>
			<div class="card-body card-padding p-b-0" id="group.details">
				<div class="form-group m-b-15">
					<label class="col-sm-3 control-label" for="sg">Серверная группа</label>
					<div class="col-sm-6">
						<select class="selectpicker" TABINDEX=11 onchange="update_server()" name="sg" id="sg">
							<option value="-1">Нет группы</option>
							<optgroup label="Группы" style="font-weight:bold;">
								{foreach from=$group_lst item=sg}
								<option value="{$sg.id}"{if $sg.id == $server_admin_group_id} selected="selected"{/if}>{$sg.name}</option>
								{/foreach}
							</optgroup>
						</select>
						<div id="sgroup.msg" class="badentry"></div>
					</div>
				</div>
				<div id="serverperm" class="m-b-15" style="display: none;"></div>
				<div class="form-group m-b-15">
					<label class="col-sm-3 control-label" for="wg">Веб-группа</label>
					<div class="col-sm-6">
						<select TABINDEX=9 onchange="update_web()" name="wg" id="wg" class="selectpicker">
							<option value="-1">Нет группы</option>
							<optgroup label="Группы" style="font-weight:bold;">
								{foreach from=$web_lst item=wg}
								<option value="{$wg.gid}"{if $wg.gid == $group_admin_id} selected="selected"{/if}>{$wg.name}</option>
								{/foreach}
							</optgroup>
						</select>
						<div id="wgroup.msg" class="badentry"></div>
					</div>
				</div>
				<div id="webperm" class="m-b-15" style="display: none;"></div>
			</div>
			<div class="card-body card-padding text-center admin-manage-footer">
				{sb_button text="Сохранить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="agroups" submit=true}
				&nbsp;
				{sb_button text="Назад" onclick="sbGo('admin/admins')" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-bluegray btn-icon-text" id="aback"}
			</div>
			{$server_script}
		</div>
	</div>
</form>
