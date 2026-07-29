<?php
// *************************************************************************
//  Installer page router
// *************************************************************************

$_GET['step'] = isset($_GET['step']) ? $_GET['step'] : 'default';

switch ($_GET['step']) {
	case '5':
		RewritePageTitle('Шаг 5 — Администратор и финиш');
		$page = TEMPLATES_PATH . '/page.5.php';
		break;
	case '4':
		RewritePageTitle('Шаг 4 — Создание таблиц');
		$page = TEMPLATES_PATH . '/page.4.php';
		break;
	case '3':
		RewritePageTitle('Шаг 3 — Системные требования');
		$page = TEMPLATES_PATH . '/page.3.php';
		break;
	case '2':
		RewritePageTitle('Шаг 2 — База данных');
		$page = TEMPLATES_PATH . '/page.2.php';
		break;
	default:
		RewritePageTitle('Шаг 1 — Лицензия');
		$page = TEMPLATES_PATH . '/page.1.php';
		break;
}

ob_start();
BuildPageHeader();
BuildPageTabs();
BuildSubMenu();
BuildContHeader();
if (!empty($page))
	include $page;
include_once TEMPLATES_PATH . '/footer.php';
ob_end_flush();
