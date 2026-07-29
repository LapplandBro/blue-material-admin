<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
?>

<div class="perms-panel m-b-15">
	<div class="perms-panel-head">
		<div class="perms-panel-title">Новая группа</div>
		<div class="perms-panel-sub">Имя сохранится вместе с правами ниже</div>
	</div>
	<div class="perms-immunity" style="padding: 12px 14px;">
		<label for="{name}">Имя</label>
		<input type="text" class="form-control" id="{name}" name="{name}" placeholder="Название группы" />
		<div id="{name}_err" class="badentry"></div>
	</div>
</div>
