<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
?>

<div class="perms-panel">
	<div class="perms-panel-head">
		<div class="perms-panel-title">{title}</div>
		<div class="perms-panel-sub">Серверные флаги SourceMod</div>
	</div>

	<div class="perms-section" id="srootcheckbox" name="srootcheckbox">
		<label class="perms-row perms-row--root">
			<span class="perms-row-text">
				<span class="perms-row-name">Полный доступ</span>
				<span class="perms-row-desc">Флаг <code>z</code> · все серверные права</span>
			</span>
			<input type="checkbox" name="s14" id="s14" />
		</label>
	</div>

	<div class="perms-section">
		<div class="perms-section-title">Основные</div>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Резерв</span><span class="perms-row-desc"><code>a</code> · слот</span></span>
			<input type="checkbox" name="s1" id="s1" value="1" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Админ</span><span class="perms-row-desc"><code>b</code> · базовый флаг</span></span>
			<input type="checkbox" name="s23" id="s23" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Кик</span><span class="perms-row-desc"><code>c</code></span></span>
			<input type="checkbox" name="s2" id="s2" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Бан</span><span class="perms-row-desc"><code>d</code></span></span>
			<input type="checkbox" name="s3" id="s3" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Разбан</span><span class="perms-row-desc"><code>e</code></span></span>
			<input type="checkbox" name="s4" id="s4" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Убить</span><span class="perms-row-desc"><code>f</code></span></span>
			<input type="checkbox" name="s5" id="s5" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Карты</span><span class="perms-row-desc"><code>g</code></span></span>
			<input type="checkbox" name="s6" id="s6" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Квары</span><span class="perms-row-desc"><code>h</code></span></span>
			<input type="checkbox" name="s7" id="s7" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Конфиг</span><span class="perms-row-desc"><code>i</code></span></span>
			<input type="checkbox" name="s8" id="s8" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Чат</span><span class="perms-row-desc"><code>j</code></span></span>
			<input type="checkbox" name="s9" id="s9" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Голосования</span><span class="perms-row-desc"><code>k</code></span></span>
			<input type="checkbox" name="s10" id="s10" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Пароль</span><span class="perms-row-desc"><code>l</code></span></span>
			<input type="checkbox" name="s11" id="s11" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">RCON</span><span class="perms-row-desc"><code>m</code></span></span>
			<input type="checkbox" name="s12" id="s12" />
		</label>
		<label class="perms-row">
			<span class="perms-row-text"><span class="perms-row-name">Читы</span><span class="perms-row-desc"><code>n</code> · sv_cheats</span></span>
			<input type="checkbox" name="s13" id="s13" />
		</label>
	</div>

	<div class="perms-section">
		<div class="perms-section-title">Иммунитет</div>
		<div class="perms-immunity">
			<label for="immunity">Уровень</label>
			<input type="text" name="immunity" id="immunity" class="form-control" placeholder="0" />
			<span class="parsec-muted">Чем выше — тем сильнее защита от чужих команд</span>
		</div>
	</div>

	<div class="perms-section">
		<div class="perms-section-title">Свои флаги</div>
		<label class="perms-row"><span class="perms-row-text"><span class="perms-row-name">Флаг o</span></span><input type="checkbox" name="s17" id="s17" /></label>
		<label class="perms-row"><span class="perms-row-text"><span class="perms-row-name">Флаг p</span></span><input type="checkbox" name="s18" id="s18" /></label>
		<label class="perms-row"><span class="perms-row-text"><span class="perms-row-name">Флаг q</span></span><input type="checkbox" name="s19" id="s19" /></label>
		<label class="perms-row"><span class="perms-row-text"><span class="perms-row-name">Флаг r</span></span><input type="checkbox" name="s20" id="s20" /></label>
		<label class="perms-row"><span class="perms-row-text"><span class="perms-row-name">Флаг s</span></span><input type="checkbox" name="s21" id="s21" /></label>
		<label class="perms-row"><span class="perms-row-text"><span class="perms-row-name">Флаг t</span></span><input type="checkbox" name="s22" id="s22" /></label>
	</div>
</div>
