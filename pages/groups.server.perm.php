<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
?>

<div class="perms-panel">
	<div class="perms-panel-head">
		<div class="perms-panel-title">{title}</div>
		<div class="perms-panel-sub">Флаги SourceMod · в середине — типичные команды базовых плагинов</div>
	</div>

	<div class="perms-section" id="srootcheckbox" name="srootcheckbox">
		<label class="perms-row perms-row--root">
			<span class="perms-row-name">Полный доступ <code>z</code></span>
			<span class="perms-row-cmds">все серверные команды, обход иммунитета</span>
			<input type="checkbox" name="s14" id="s14" />
		</label>
	</div>

	<div class="perms-section">
		<div class="perms-section-title">Основные</div>
		<label class="perms-row">
			<span class="perms-row-name">Резерв <code>a</code></span>
			<span class="perms-row-cmds">вход на полный сервер (слот)</span>
			<input type="checkbox" name="s1" id="s1" value="1" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Админ <code>b</code></span>
			<span class="perms-row-cmds"><code>sm_admin</code> <code>sm_who</code> <code>sm_help</code></span>
			<input type="checkbox" name="s23" id="s23" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Кик <code>c</code></span>
			<span class="perms-row-cmds"><code>sm_kick</code></span>
			<input type="checkbox" name="s2" id="s2" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Бан <code>d</code></span>
			<span class="perms-row-cmds"><code>sm_ban</code> <code>sm_banip</code> <code>sm_addban</code> <code>sm_reloadadmins</code></span>
			<input type="checkbox" name="s3" id="s3" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Разбан <code>e</code></span>
			<span class="perms-row-cmds"><code>sm_unban</code></span>
			<input type="checkbox" name="s4" id="s4" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Убить <code>f</code></span>
			<span class="perms-row-cmds"><code>sm_slay</code> <code>sm_slap</code></span>
			<input type="checkbox" name="s5" id="s5" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Карты <code>g</code></span>
			<span class="perms-row-cmds"><code>sm_map</code> <code>sm_nextmap</code></span>
			<input type="checkbox" name="s6" id="s6" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Квары <code>h</code></span>
			<span class="perms-row-cmds"><code>sm_cvar</code> <code>sm_resetcvar</code></span>
			<input type="checkbox" name="s7" id="s7" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Конфиг <code>i</code></span>
			<span class="perms-row-cmds"><code>sm_execcfg</code></span>
			<input type="checkbox" name="s8" id="s8" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Чат <code>j</code></span>
			<span class="perms-row-cmds"><code>sm_say</code> <code>sm_csay</code> <code>sm_psay</code> <code>sm_chat</code></span>
			<input type="checkbox" name="s9" id="s9" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Голосования <code>k</code></span>
			<span class="perms-row-cmds"><code>sm_vote</code> <code>sm_votekick</code> <code>sm_voteban</code> <code>sm_votemap</code></span>
			<input type="checkbox" name="s10" id="s10" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Пароль <code>l</code></span>
			<span class="perms-row-cmds"><code>sv_password</code></span>
			<input type="checkbox" name="s11" id="s11" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">RCON <code>m</code></span>
			<span class="perms-row-cmds"><code>sm_rcon</code></span>
			<input type="checkbox" name="s12" id="s12" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Читы <code>n</code></span>
			<span class="perms-row-cmds"><code>sv_cheats</code> · noclip, beacon, freeze</span>
			<input type="checkbox" name="s13" id="s13" />
		</label>
	</div>

	<div class="perms-section">
		<div class="perms-section-title">Иммунитет</div>
		<div class="perms-immunity">
			<label for="immunity">Уровень</label>
			<input type="text" name="immunity" id="immunity" class="form-control" placeholder="0" />
			<span class="parsec-muted">Выше число — чужие кик/бан/слэй не проходят</span>
		</div>
	</div>

	<div class="perms-section perms-section--grid">
		<div class="perms-section-title">Свои флаги</div>
		<label class="perms-row">
			<span class="perms-row-name">Флаг <code>o</code></span>
			<span class="perms-row-cmds">свои плагины</span>
			<input type="checkbox" name="s17" id="s17" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Флаг <code>p</code></span>
			<span class="perms-row-cmds">свои плагины</span>
			<input type="checkbox" name="s18" id="s18" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Флаг <code>q</code></span>
			<span class="perms-row-cmds">свои плагины</span>
			<input type="checkbox" name="s19" id="s19" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Флаг <code>r</code></span>
			<span class="perms-row-cmds">свои плагины</span>
			<input type="checkbox" name="s20" id="s20" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Флаг <code>s</code></span>
			<span class="perms-row-cmds">свои плагины</span>
			<input type="checkbox" name="s21" id="s21" />
		</label>
		<label class="perms-row">
			<span class="perms-row-name">Флаг <code>t</code></span>
			<span class="perms-row-cmds">свои плагины</span>
			<input type="checkbox" name="s22" id="s22" />
		</label>
	</div>
</div>
