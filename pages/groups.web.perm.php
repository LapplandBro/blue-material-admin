<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
?>

<div class="perms-panel">
	<div class="perms-panel-head">
		<div class="perms-panel-title">{title}</div>
		<div class="perms-panel-sub">Веб-права панели</div>
	</div>

	<div class="perms-section" id="wrootcheckbox" name="wrootcheckbox">
		<label class="perms-row perms-row--root">
			<span class="perms-row-text">
				<span class="perms-row-name">Полный доступ</span>
				<span class="perms-row-desc">Владелец · все веб-права</span>
			</span>
			<input type="checkbox" name="p2" id="p2" onclick="UpdateCheckBox(2, 3, 39);" value="1" />
		</label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Админы</span></span>
			<input type="checkbox" name="p3" id="p3" onclick="UpdateCheckBox(3, 4, 7);" />
		</label>
		<label class="perms-row"><span class="perms-row-text">Просмотр</span><input type="checkbox" name="p4" id="p4" /></label>
		<label class="perms-row"><span class="perms-row-text">Добавление</span><input type="checkbox" name="p5" id="p5" /></label>
		<label class="perms-row"><span class="perms-row-text">Изменение</span><input type="checkbox" name="p6" id="p6" /></label>
		<label class="perms-row"><span class="perms-row-text">Удаление</span><input type="checkbox" name="p7" id="p7" /></label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Серверы</span></span>
			<input type="checkbox" name="p8" id="p8" onclick="UpdateCheckBox(8, 9, 12);"/>
		</label>
		<label class="perms-row"><span class="perms-row-text">Просмотр</span><input type="checkbox" name="p9" id="p9" /></label>
		<label class="perms-row"><span class="perms-row-text">Добавление</span><input type="checkbox" name="p10" id="p10" /></label>
		<label class="perms-row"><span class="perms-row-text">Изменение</span><input type="checkbox" name="p11" id="p11" /></label>
		<label class="perms-row"><span class="perms-row-text">Удаление</span><input type="checkbox" name="p12" id="p12" /></label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Баны</span></span>
			<input type="checkbox" name="p13" id="p13" onclick="UpdateCheckBox(13, 14, 20, 32, 33, 34, 38, 39);"/>
		</label>
		<label class="perms-row"><span class="perms-row-text">Добавление</span><input type="checkbox" name="p14" id="p14" /></label>
		<label class="perms-row"><span class="perms-row-text">Свои — правка</span><input type="checkbox" name="p16" id="p16" /></label>
		<label class="perms-row"><span class="perms-row-text">Группы — правка</span><input type="checkbox" name="p17" id="p17" /></label>
		<label class="perms-row"><span class="perms-row-text">Все — правка</span><input type="checkbox" name="p18" id="p18" /></label>
		<label class="perms-row"><span class="perms-row-text">Протесты</span><input type="checkbox" name="p19" id="p19" /></label>
		<label class="perms-row"><span class="perms-row-text">Заявки</span><input type="checkbox" name="p20" id="p20" /></label>
		<label class="perms-row"><span class="perms-row-text">Свои — разбан</span><input type="checkbox" name="p38" id="p38" /></label>
		<label class="perms-row"><span class="perms-row-text">Группы — разбан</span><input type="checkbox" name="p39" id="p39" /></label>
		<label class="perms-row"><span class="perms-row-text">Все — разбан</span><input type="checkbox" name="p32" id="p32" /></label>
		<label class="perms-row"><span class="perms-row-text">Удаление</span><input type="checkbox" name="p33" id="p33" /></label>
		<label class="perms-row"><span class="perms-row-text">Импорт</span><input type="checkbox" name="p34" id="p34" /></label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Группы</span></span>
			<input type="checkbox" name="p21" id="p21" onclick="UpdateCheckBox(21, 22, 25);" />
		</label>
		<label class="perms-row"><span class="perms-row-text">Просмотр</span><input type="checkbox" name="p22" id="p22" /></label>
		<label class="perms-row"><span class="perms-row-text">Добавление</span><input type="checkbox" name="p23" id="p23" /></label>
		<label class="perms-row"><span class="perms-row-text">Изменение</span><input type="checkbox" name="p24" id="p24" /></label>
		<label class="perms-row"><span class="perms-row-text">Удаление</span><input type="checkbox" name="p25" id="p25" /></label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Почта</span></span>
			<input type="checkbox" name="p35" id="p35" onclick="UpdateCheckBox(35, 36, 37);"/>
		</label>
		<label class="perms-row"><span class="perms-row-text">Уведомления о заявках</span><input type="checkbox" name="p36" id="p36" /></label>
		<label class="perms-row"><span class="perms-row-text">Уведомления о протестах</span><input type="checkbox" name="p37" id="p37" /></label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Настройки сайта</span></span>
			<input type="checkbox" name="p26" id="p26" />
		</label>
	</div>

	<div class="perms-section">
		<label class="perms-row perms-row--group">
			<span class="perms-row-text"><span class="perms-row-name">Моды</span></span>
			<input type="checkbox" name="p27" id="p27" onclick="UpdateCheckBox(27, 28, 31);" />
		</label>
		<label class="perms-row"><span class="perms-row-text">Просмотр</span><input type="checkbox" name="p28" id="p28" /></label>
		<label class="perms-row"><span class="perms-row-text">Добавление</span><input type="checkbox" name="p29" id="p29" /></label>
		<label class="perms-row"><span class="perms-row-text">Изменение</span><input type="checkbox" name="p30" id="p30" /></label>
		<label class="perms-row"><span class="perms-row-text">Удаление</span><input type="checkbox" name="p31" id="p31" /></label>
	</div>
</div>
