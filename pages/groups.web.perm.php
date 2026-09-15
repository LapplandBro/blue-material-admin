<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
?>

<div class="perms-panel">
	<div class="perms-panel-head">
		<div class="perms-panel-title">{title}</div>
		<div class="perms-panel-sub">Веб-права панели · в середине — что открывает флаг</div>
	</div>

	<div class="perms-section" id="wrootcheckbox" name="wrootcheckbox">
		<label class="perms-row perms-row--root">
			<span class="perms-row-name">Полный доступ</span>
			<span class="perms-row-cmds">владелец · все разделы админки</span>
			<input type="checkbox" name="p2" id="p2" onclick="UpdateCheckBox(2, 3, 39);" value="1" />
		</label>
	</div>

	<div class="perms-section perms-section--grid">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Админы</span>
			<span class="perms-row-cmds">весь блок</span>
			<input type="checkbox" name="p3" id="p3" onclick="UpdateCheckBox(3, 4, 7);" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Просмотр</span>
			<span class="perms-row-cmds">список админов</span>
			<input type="checkbox" name="p4" id="p4" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Добавление</span>
			<span class="perms-row-cmds">новый админ</span>
			<input type="checkbox" name="p5" id="p5" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Изменение</span>
			<span class="perms-row-cmds">правка карточки</span>
			<input type="checkbox" name="p6" id="p6" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Удаление</span>
			<span class="perms-row-cmds">снять админку</span>
			<input type="checkbox" name="p7" id="p7" />
		</label>
	</div>

	<div class="perms-section perms-section--grid">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Серверы</span>
			<span class="perms-row-cmds">весь блок</span>
			<input type="checkbox" name="p8" id="p8" onclick="UpdateCheckBox(8, 9, 12);"/>
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Просмотр</span>
			<span class="perms-row-cmds">список серверов</span>
			<input type="checkbox" name="p9" id="p9" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Добавление</span>
			<span class="perms-row-cmds">новый сервер</span>
			<input type="checkbox" name="p10" id="p10" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Изменение</span>
			<span class="perms-row-cmds">IP, RCON, группы</span>
			<input type="checkbox" name="p11" id="p11" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Удаление</span>
			<span class="perms-row-cmds">убрать сервер</span>
			<input type="checkbox" name="p12" id="p12" />
		</label>
	</div>

	<div class="perms-section perms-section--grid">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Баны</span>
			<span class="perms-row-cmds">весь блок</span>
			<input type="checkbox" name="p13" id="p13" onclick="UpdateCheckBox(13, 14, 20, 32, 33, 34, 38, 39);"/>
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Добавление</span>
			<span class="perms-row-cmds">новый бан</span>
			<input type="checkbox" name="p14" id="p14" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Свои — правка</span>
			<span class="perms-row-cmds">только свои баны</span>
			<input type="checkbox" name="p16" id="p16" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Группы — правка</span>
			<span class="perms-row-cmds">баны своей группы</span>
			<input type="checkbox" name="p17" id="p17" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Все — правка</span>
			<span class="perms-row-cmds">любой бан</span>
			<input type="checkbox" name="p18" id="p18" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Протесты</span>
			<span class="perms-row-cmds">вкладка протестов</span>
			<input type="checkbox" name="p19" id="p19" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Заявки</span>
			<span class="perms-row-cmds">вкладка апелляций</span>
			<input type="checkbox" name="p20" id="p20" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Свои — разбан</span>
			<span class="perms-row-cmds">снять свой бан</span>
			<input type="checkbox" name="p38" id="p38" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Группы — разбан</span>
			<span class="perms-row-cmds">разбан группы</span>
			<input type="checkbox" name="p39" id="p39" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Все — разбан</span>
			<span class="perms-row-cmds">разбанить любого</span>
			<input type="checkbox" name="p32" id="p32" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Удаление</span>
			<span class="perms-row-cmds">стереть запись</span>
			<input type="checkbox" name="p33" id="p33" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Импорт</span>
			<span class="perms-row-cmds">загрузка списка</span>
			<input type="checkbox" name="p34" id="p34" />
		</label>
	</div>

	<div class="perms-section perms-section--grid">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Группы</span>
			<span class="perms-row-cmds">весь блок</span>
			<input type="checkbox" name="p21" id="p21" onclick="UpdateCheckBox(21, 22, 25);" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Просмотр</span>
			<span class="perms-row-cmds">список групп</span>
			<input type="checkbox" name="p22" id="p22" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Добавление</span>
			<span class="perms-row-cmds">новая группа</span>
			<input type="checkbox" name="p23" id="p23" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Изменение</span>
			<span class="perms-row-cmds">права группы</span>
			<input type="checkbox" name="p24" id="p24" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Удаление</span>
			<span class="perms-row-cmds">снести группу</span>
			<input type="checkbox" name="p25" id="p25" />
		</label>
	</div>

	<div class="perms-section perms-section--grid">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Почта</span>
			<span class="perms-row-cmds">весь блок</span>
			<input type="checkbox" name="p35" id="p35" onclick="UpdateCheckBox(35, 36, 37);"/>
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Заявки</span>
			<span class="perms-row-cmds">письмо о новой заявке</span>
			<input type="checkbox" name="p36" id="p36" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Протесты</span>
			<span class="perms-row-cmds">письмо о протесте</span>
			<input type="checkbox" name="p37" id="p37" />
		</label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Настройки сайта</span>
			<span class="perms-row-cmds">тема, почта, функции панели</span>
			<input type="checkbox" name="p26" id="p26" />
		</label>
	</div>

	<div class="perms-section perms-section--grid">
		<label class="perms-row perms-row--group">
			<span class="perms-row-name">Моды</span>
			<span class="perms-row-cmds">весь блок</span>
			<input type="checkbox" name="p27" id="p27" onclick="UpdateCheckBox(27, 28, 31);" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Просмотр</span>
			<span class="perms-row-cmds">список модов</span>
			<input type="checkbox" name="p28" id="p28" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Добавление</span>
			<span class="perms-row-cmds">новый мод</span>
			<input type="checkbox" name="p29" id="p29" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Изменение</span>
			<span class="perms-row-cmds">иконка, папка</span>
			<input type="checkbox" name="p30" id="p30" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Удаление</span>
			<span class="perms-row-cmds">убрать мод</span>
			<input type="checkbox" name="p31" id="p31" />
		</label>
	</div>
</div>
