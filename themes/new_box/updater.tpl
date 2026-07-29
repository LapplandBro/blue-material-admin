<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Обновление SourceBans</title>
	<link rel="shortcut icon" href="../images/favicon.ico" />
	<link href="../themes/new_box/css/rubik.css" rel="stylesheet" />
	<link href="../themes/new_box/vendors/bower_components/animate.css/animate.min.css" rel="stylesheet" />
	<link href="../themes/new_box/vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.css" rel="stylesheet" />
	<link href="../themes/new_box/vendors/bower_components/material-design-iconic-font/dist/css/material-design-iconic-font.min.css" rel="stylesheet" />
	<link href="../themes/new_box/css/app.min.1.css" rel="stylesheet" />
	<link href="../themes/new_box/css/app.min.2.css" rel="stylesheet" />
	<link href="../themes/new_box/css/css_sup.css" rel="stylesheet" />
	<link href="../themes/new_box/css/dark-blue-theme.css" rel="stylesheet" />
</head>
<body class="toggled sw-toggled">
	<header id="header" class="clearfix" data-current-skin="blue">
		<ul class="header-inner">
			<li class="logo hidden-xs">
				<a href="../index.php">SourceBans :: Обновление</a>
			</li>
		</ul>
	</header>

	<section id="main" data-layout="layout-1">
		<aside id="sidebar" class="sidebar c-overflow">
			<div class="profile-menu">
				<a href="#">
					<div class="profile-pic">
						<img src="../themes/new_box/img/profile-pics/1.jpg" alt="" />
					</div>
					<div class="profile-info">Обновление</div>
				</a>
			</div>
			<ul class="main-menu">
				<li class="nonactive"><a href="https://sbpp.github.io/" target="_blank" rel="noopener"><i class="zmdi zmdi-globe"></i> SourceBans++</a></li>
				<li class="nonactive"><a href="https://www.sourcemod.net" target="_blank" rel="noopener"><i class="zmdi zmdi-flower-alt"></i> SourceMod</a></li>
			</ul>
		</aside>
		<section id="content">
			<div class="container">
				<div class="block-header">
					<h2 id="content_title">Обновление системы</h2>
				</div>
				<div class="card">
					<div class="card-header">
						<h2>Миграции базы данных</h2>
					</div>
					<div class="card-body card-padding">
						<p>{$setup}</p>
						{if $progress}<br /><p>{$progress}</p>{/if}
						<p class="m-t-20 c-gray">После успешного обновления удалите папки <code>install/</code> и <code>updater/</code>.</p>
					</div>
				</div>
			</div>
		</section>
	</section>

	<footer id="footer">
		<div id="sm">Создано <a class="footer_link" href="https://github.com/lapplandbro" target="_blank" rel="noopener">lapplandbro</a></div>
	</footer>

	<script src="../themes/new_box/vendors/bower_components/jquery/dist/jquery.min.js"></script>
	<script src="../themes/new_box/vendors/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
	<script src="../themes/new_box/vendors/bower_components/Waves/dist/waves.min.js"></script>
	<script src="../themes/new_box/js/functions.js"></script>
</body>
</html>
