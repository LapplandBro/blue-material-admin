<?php
	if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
?>

<div class="card m-b-0" id="messages-main">
	<div class="ms-menu">
		<div class="ms-block p-10">
			<span class="c-black"><b>Процесс</b></span>
		</div>

		<div class="listview lv-user" id="install-progress">
			<div class="lv-item media active">
				<div class="lv-avatar bgm-red pull-left">1</div>
				<div class="media-body">
					<div class="lv-title">Шаг: Лицензия</div>
					<div class="lv-small"><i class="zmdi zmdi-badge-check zmdi-hc-fw c-green"></i> Текущий шаг</div>
				</div>
			</div>

			<div class="lv-item media">
				<div class="lv-avatar bgm-orange pull-left">2</div>
				<div class="media-body">
					<div class="lv-title">Шаг: База данных</div>
					<div class="lv-small"><i class="zmdi zmdi-time zmdi-hc-fw c-blue"></i> Следующий шаг</div>
				</div>
			</div>

			<div class="lv-item media">
				<div class="lv-avatar bgm-orange pull-left">3</div>
				<div class="media-body">
					<div class="lv-title">Шаг: Системные требования</div>
					<div class="lv-small"><i class="zmdi zmdi-time zmdi-hc-fw c-blue"></i> Следующий шаг</div>
				</div>
			</div>

			<div class="lv-item media">
				<div class="lv-avatar bgm-orange pull-left">4</div>
				<div class="media-body">
					<div class="lv-title">Шаг: Создание таблиц</div>
					<div class="lv-small"><i class="zmdi zmdi-time zmdi-hc-fw c-blue"></i> Следующий шаг</div>
				</div>
			</div>

			<div class="lv-item media">
				<div class="lv-avatar bgm-orange pull-left">5</div>
				<div class="media-body">
					<div class="lv-title">Шаг: Установка</div>
					<div class="lv-small"><i class="zmdi zmdi-time zmdi-hc-fw c-blue"></i> Следующий шаг</div>
				</div>
			</div>
		</div>
	</div>
	
	<div class="ms-body">
		<div class="listview lv-message">
			<div class="lv-header-alt clearfix">
				<div class="lvh-label">
					<span class="c-black">Ознакомление</span>
				</div>
			</div>

			<div class="lv-body p-15">                                    
				Перед установкой этого программного обеспечения Вы должны прочесть и принять условия лицензии. Если Вы не согласны с условиями — не устанавливайте ПО.<br />
				Код панели: <code>LICENSE</code> (<a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener">GNU GPL v3</a>).
				Слои и оговорка по UI-теме: файл <code>NOTICE</code> в корне.
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
Blue Material Admin — форк SourceBans++.

КОД ПАНЕЛИ (PHP, логика) — GNU General Public License v3 (файл LICENSE).
Вы можете распространять и изменять код на условиях GPLv3 (или более поздней
версии по вашему выбору). ПО без гарантий — см. полный текст GPL.

UI-ОБОЛОЧКА themes/new_box (кроме vendors/) — наследник Material Admin /
IceMan-адаптаций. Это НЕ «чистый GPL/MIT». Пока тема не заменена
(docs/THEME_MIGRATION.md), не заявляйте весь дистрибутив как чисто
коммерческий GPL-продукт. Подробности — файл NOTICE.

Vendors (Bootstrap 3 и др.) — свои лицензии, чаще MIT.

Кратко по GPLv3 (не заменяет LICENSE):
 • можно запускать, изучать, менять и распространять код;
 • производные при распространении — под GPLv3;
 • исходники доступны получателям на условиях GPL.

Upstream: SourceBans++ (GPLv3) — https://github.com/sbpp/sourcebans-pp
						</textarea>
					</div>
				</form>

				<div class="col-sm-12 p-l-0 m-10">
					<div class="col-sm-6">
						<div class="checkbox m-b-15">
							<label for="accept">
								<input type="checkbox" name="accept" id="accept" hidden="hidden" />
								<i class="input-helper"></i> Я прочёл и принимаю условия
							</label>
						</div>
					</div>

					<div class="col-sm-6" align="right">
						<button onclick="checkAccept()" class="btn btn-primary waves-effect" id="button" name="button">Принимаю</button>
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
