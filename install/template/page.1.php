<?php
	if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
?>

<div class="card m-b-0" id="messages-main">
<?php $installStep = 1; include TEMPLATES_PATH . '/install-progress.php'; ?>
	<div class="ms-body">
		<div class="listview lv-message">
			<div class="lv-header-alt clearfix">
				<div class="lvh-label">
					<span class="c-black">Ознакомление</span>
				</div>
			</div>

			<div class="lv-body p-15">                                    
				Перед установкой этого программного обеспечения Вы должны прочесть и принять условия лицензии. Если Вы не согласны с условиями — не устанавливайте ПО.<br />
				Код панели: <code>LICENSE</code> (<a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener">GNU GPL v3</a>) — форк SourceBans / SourceBans++.
				Атрибуция и независимость форка: файл <code>NOTICE</code> в корне.
			</div>

			<div class="lv-header-alt clearfix">
				<div class="lvh-label">
					<span class="c-black">GNU GPLv3 (код) + NOTICE (тема / vendors)</span>
				</div>
			</div>
			<div class="lv-body p-15" id="submit-introduction">
				<form action="index.php?p=submit" method="POST" enctype="multipart/form-data">
					<div id="submit-main">
						<textarea class="form-control" id="license" cols="105" rows="15" name="license">
Blue Material Admin — самостоятельный форк SourceBans / SourceBans++.

ЛИЦЕНЗИЯ КОДА: GNU General Public License v3 (файл LICENSE в корне).
Можно запускать, изучать, менять и распространять код на условиях GPLv3
(или более поздней версии по вашему выбору). ПО без гарантий.

ЭТО ФОРК SOURCEBANS:
  SourceBans (GameConnect) — https://github.com/GameConnect/sourcebansv1
  SourceBans++ — https://github.com/sbpp/sourcebans-pp

ИДЕЯ ОФОРМЛЕНИЯ (дань уважения той работе, не копия чужого CSS):
  https://hlmod.net/threads/alpha-material-admin-refork-na-osnove-sb-1-5-4-7-bootstrap-3.36382/
  Kruzya (CrazyHackGUT) — https://github.com/CrazyHackGUT
  https://hlmod.net/members/kruzya.72654/

Этот репозиторий живёт своей жизнью и НЕ является официальным продолжением
SB-MaterialAdmin, сборок Kruzya или других Material Admin.

ОБОЛОЧКА themes/blue_v2 — Twig + Bootstrap 5, написана в этом форке (GPLv3).
Vendors — свои лицензии (MIT / BSD / LGPL / GPL). Подробности: NOTICE.

Кратко по GPLv3 (не заменяет LICENSE):
 • производные при распространении — под GPLv3;
 • исходники доступны получателям на условиях GPL.
						</textarea>
					</div>
				</form>

				<div class="col-sm-12 p-l-0 m-10">
					<div class="col-sm-6">
						<div class="checkbox m-b-15">
							<label for="accept">
								<input type="checkbox" name="accept" id="accept" />
								Я прочёл и принимаю условия
							</label>
						</div>
					</div>

					<div class="col-sm-6" align="right">
						<button type="button" onclick="checkAccept()" class="btn btn-primary" id="button" name="button">Принимаю</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
	
<script type="text/javascript">
function checkAccept() {
	var el = $id('accept');
	if (el && el.checked)
		window.location = 'index.php?step=2';
	else
		ShowBox('Ошибка', 'Нужно принять условия лицензии, чтобы продолжить.', 'red', '', true);
}
window.sbInstallEnter = checkAccept;
</script>
