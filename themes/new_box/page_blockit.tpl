<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
-{$xajax_functions}-
<script>window.SB_CSRF="-{$sb_csrf|escape:'javascript'}-";</script>
<style type="text/css">
html, body {
	margin: 0;
	padding: 0;
	background: transparent !important;
	color: #ddeeff;
	font-family: "Rubik", "Segoe UI", system-ui, -apple-system, sans-serif;
	font-size: 13px;
	line-height: 1.4;
}
.blockit-wrap {
	padding: 4px 2px 8px;
}
.blockit-title {
	margin: 0 0 10px;
	font-size: 13px;
	font-weight: 600;
	color: #7ea8d4;
}
.blockit-list {
	width: 100%;
	border-collapse: collapse;
}
.blockit-list tr + tr td {
	border-top: 1px solid rgba(30, 144, 255, 0.12);
}
.blockit-list td {
	padding: 7px 4px;
	vertical-align: top;
}
.blockit-host {
	color: #e8f0ff;
	font-size: 12px;
	font-weight: 500;
	word-break: break-word;
	padding-right: 10px;
}
.blockit-status {
	color: #7ea8d4;
	font-size: 12px;
	white-space: nowrap;
	text-align: right;
}
.blockit-status .ok { color: #81c784; font-weight: 600; }
.blockit-status .err { color: #ff8a80; }
.blockit-status .muted { color: #3d607f; }
.blockit-foot {
	margin-top: 12px;
	padding-top: 8px;
	border-top: 1px solid rgba(30, 144, 255, 0.15);
	font-size: 13px;
	font-weight: 600;
	color: #81c784;
	display: none;
}
</style>
<script type="text/javascript">
//<![CDATA[
window.onload = function() {xajax_LoadServers2('-{$check}-', '-{$type}-', '-{$length}-');}
var srvcount = 0;
function set_counter(count)
{
	srvcount += count;
	if(srvcount==-{$total}- || count=='-1') {
		var foot = document.getElementById('blockit-foot');
		if (foot) {
			foot.style.display = 'block';
			foot.textContent = (count == '-1') ? 'Игрок найден и блокировка применена.' : 'Обход серверов завершён.';
		}
		try {
			var dc = parent.document.getElementById('dialog-control');
			if (dc) {
				dc.innerHTML = '<span style="color:#81c784;font-weight:600;">Готово.</span>';
				dc.style.display = 'block';
			}
		} catch (e) {}
		setTimeout(function(){
			try {
				var dp = parent.document.getElementById('dialog-placement');
				if (dp) dp.style.display = 'none';
				if (typeof parent.swal === 'function') parent.swal.close();
			} catch (e2) {}
		}, 4500);
		setTimeout(function(){ window.location='../index.php?p=admin&c=comms'; }, 4500);
	}
	resizeFrame();
}
function resizeFrame()
{
	try {
		var box = document.getElementById('container');
		var ifr = parent.document.getElementById('srvkicker');
		if (box && ifr) {
			var h = Math.max(160, Math.min(box.scrollHeight + 8, 360));
			ifr.style.height = h + 'px';
			ifr.height = h;
		}
	} catch (e) {}
}
try {
	var dc0 = parent.document.getElementById('dialog-control');
	if (dc0) dc0.style.display = 'none';
} catch (e) {}
//]]>
</script>
</head>
<body onload="resizeFrame();">
<div id="container" class="blockit-wrap" name="container">
	<div class="blockit-title">Ищем игрока на серверах…</div>
	<table class="blockit-list" border="0">
	-{foreach from=$servers item=serv}-
	<tr>
		<td>
			<div class="blockit-host" id="srvip_-{$serv.num}-">-{$serv.ip}-:-{$serv.port}-</div>
		</td>
		<td>
			<div class="blockit-status" id="srv_-{$serv.num}-"><span class="muted">ждём…</span></div>
		</td>
	</tr>
	-{/foreach}-
	</table>
	<div id="blockit-foot" class="blockit-foot">Готово.</div>
</div>
<script type="text/javascript">resizeFrame();</script>
</body>
</html>
