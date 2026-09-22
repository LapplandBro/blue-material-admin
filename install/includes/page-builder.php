<?php
// *************************************************************************
//  Installer page router
// *************************************************************************

if (!isset($_SESSION['sb_install']) || !is_array($_SESSION['sb_install']))
	$_SESSION['sb_install'] = array();

$GLOBALS['sbInstallObLevel'] = ob_get_level();

if (!isset($_GET['step']) || $_GET['step'] === '' || $_GET['step'] === 'default')
	$_GET['step'] = '1';
else
	$_GET['step'] = (string)$_GET['step'];

/**
 * Редирект на шаг установщика. Пароль в URL не передаётся.
 */
function sb_install_redirect($step)
{
	$url = 'index.php?step=' . (int)$step;
	$floor = isset($GLOBALS['sbInstallObLevel']) ? (int)$GLOBALS['sbInstallObLevel'] : 0;
	while (ob_get_level() > $floor)
		ob_end_clean();
	if (!headers_sent()) {
		header('Location: ' . $url);
		exit;
	}
	$safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
	echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">';
	echo '<meta http-equiv="refresh" content="0;url=' . $safe . '">';
	echo '</head><body><p><a href="' . $safe . '">Продолжить</a></p></body></html>';
	exit;
}

/**
 * Соединение mysqli без DSN: при ошибке объект жив, ErrorNo() доступен.
 * Пустое имя базы — подключение к серверу без выбора базы.
 */
function sb_install_mysqli_connect($server, $username, $password, $port, $database)
{
	require_once ROOT . '../includes/adodb/adodb.inc.php';
	include_once ROOT . '../includes/adodb/adodb-errorhandler.inc.php';

	$fail = array(
		'ok' => false,
		'db' => null,
		'errno' => 0,
		'error' => 'Не удалось загрузить драйвер MySQLi.',
	);
	$db = ADONewConnection('mysqli');
	if (!$db)
		return $fail;

	$db->port = (int)$port;
	if ($db->port <= 0)
		$db->port = 3306;

	$ok = $db->Connect((string)$server, (string)$username, (string)$password, (string)$database);
	if (!$ok) {
		$errno = method_exists($db, 'ErrorNo') ? (int)$db->ErrorNo() : 0;
		$error = 'Ошибка соединения с сервером баз данных.';
		if (method_exists($db, 'ErrorMsg')) {
			$msg = $db->ErrorMsg();
			if (is_string($msg) && $msg !== '')
				$error = $msg;
		}
		return array('ok' => false, 'db' => $db, 'errno' => $errno, 'error' => $error);
	}
	return array('ok' => true, 'db' => $db, 'errno' => 0, 'error' => '');
}

function sb_install_db_error_text($conn)
{
	$msg = (isset($conn['error']) && is_string($conn['error']) && $conn['error'] !== '')
		? $conn['error']
		: 'Ошибка соединения с сервером баз данных.';
	$errno = isset($conn['errno']) ? (int)$conn['errno'] : 0;
	if ($errno)
		$msg .= ' (код ' . $errno . ')';
	return $msg;
}

/**
 * Сервер, база, порт или префикс отличаются от уже сохранённых.
 */
function sb_install_db_identity_changed($fields)
{
	if (!isset($_SESSION['sb_install']) || !is_array($_SESSION['sb_install']))
		return true;
	foreach (array('server', 'database', 'port', 'prefix') as $key) {
		$old = isset($_SESSION['sb_install'][$key]) ? (string)$_SESSION['sb_install'][$key] : '';
		$new = isset($fields[$key]) ? (string)$fields[$key] : '';
		if ($old !== $new)
			return true;
	}
	return false;
}

/**
 * Пишет параметры БД в сессию. Пароль только сюда, не в HTML.
 */
function sb_install_save_db($fields, $dbOk, $resetTables)
{
	if (!isset($_SESSION['sb_install']) || !is_array($_SESSION['sb_install']))
		$_SESSION['sb_install'] = array();
	foreach (array('server', 'username', 'password', 'database', 'port', 'prefix', 'apikey', 'sbwpurl') as $key) {
		if (array_key_exists($key, $fields))
			$_SESSION['sb_install'][$key] = (string)$fields[$key];
	}
	if ($dbOk)
		$_SESSION['sb_install']['db_ok'] = 1;
	else
		unset($_SESSION['sb_install']['db_ok']);
	if ($resetTables)
		unset($_SESSION['sb_install']['tables_ok']);
}

$stepRaw = (string)$_GET['step'];

if ($stepRaw === '1' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset($_POST['accept']) && !is_array($_POST['accept'])) {
	$_SESSION['sb_install']['license'] = 1;
	sb_install_redirect(2);
}

if (in_array($stepRaw, array('2', '3', '4', '5'), true) && empty($_SESSION['sb_install']['license']))
	sb_install_redirect(1);

if (in_array($stepRaw, array('3', '4'), true) && empty($_SESSION['sb_install']['db_ok']))
	sb_install_redirect(2);

switch ($stepRaw) {
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
