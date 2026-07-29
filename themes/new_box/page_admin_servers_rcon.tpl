-{if NOT $permission_rcon}-
	<div class="parsec-note parsec-note-warn">Нет доступа к RCON.</div>
-{else}-
<div class="card banlist-panel admin-form rcon-panel" id="admin-page-content">
	<div class="card-header">
		<h2>RCON
			<small>Команда + Enter · <code>clr</code> — очистить</small>
		</h2>
	</div>

	<div class="rcon-terminal" id="rcon">
		<div class="rcon-log" id="rcon_con">
			<div class="rcon-line rcon-line--sys">RCON-консоль готова. Введи команду ниже.</div>
		</div>
		<div class="rcon-input-bar">
			<span class="rcon-prompt" aria-hidden="true">›</span>
			<textarea id="cmd" rows="1" placeholder="status, sm plugins list…"></textarea>
			<button type="button" onclick="SendRcon();" id="rcon_btn" title="Отправить"><i class="zmdi zmdi-mail-send"></i></button>
		</div>
	</div>
</div>

<script>
$E('html').onkeydown = function(event){
	var event = new Event(event);
	if (event.key == 'enter') SendRcon();
};

function SendRcon()
{
	xajax_SendRcon('-{$id}-', $('cmd').value, true);
	$('cmd').value = 'Выполняю…';
	$('cmd').disabled = 'true';
	$('rcon_btn').disabled = 'true';
}

var scroll = {
	toBottom: function () {
		var el = document.getElementById('rcon_con');
		if (el) el.scrollTop = el.scrollHeight;
	}
};
</script>
-{/if}-
