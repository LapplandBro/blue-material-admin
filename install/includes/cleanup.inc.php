<?php
/**
 * Генерация одноразового remove_setup.php в корне сайта.
 * Удаляет install/, updater/, install_recidivism.* и себя.
 * PHP 8.5+.
 */
if (!defined('IN_SB') && !defined('IN_INSTALL')) {
	echo 'You should not be here. Only follow links!';
	die();
}

/**
 * @param string $siteRoot абсолютный путь к корню сайта (рядом с index.php)
 * @return array{ok:bool,url:?string,path:?string,error:?string}
 */
function sb_install_write_cleanup_script($siteRoot)
{
	$siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
	$path = $siteRoot . '/remove_setup.php';

	if (!is_writable($siteRoot) && !(file_exists($path) && is_writable($path))) {
		return array('ok' => false, 'url' => null, 'path' => null, 'error' => 'Корень сайта недоступен для записи');
	}

	if (function_exists('random_bytes')) {
		$token = bin2hex(random_bytes(16));
	} else {
		$token = sha1(uniqid((string)mt_rand(), true) . microtime(true));
	}

	$code = '<?php
/**
 * Одноразовое удаление install/updater. Сгенерировано установщиком.
 * Не оставляй этот файл на сервере.
 */
header(\'Content-Type: text/html; charset=utf-8\');
$token = ' . var_export($token, true) . ';
if (!isset($_GET[\'t\']) || !is_string($_GET[\'t\']) || !hash_equals($token, $_GET[\'t\'])) {
	http_response_code(403);
	echo \'Forbidden\';
	exit;
}

function sb_setup_rrmdir($dir)
{
	if (!is_dir($dir))
		return true;
	$items = @scandir($dir);
	if ($items === false)
		return false;
	foreach ($items as $item) {
		if ($item === \'.\' || $item === \'..\')
			continue;
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if (is_dir($path)) {
			if (!sb_setup_rrmdir($path))
				return false;
		} else {
			if (!@unlink($path))
				return false;
		}
	}
	return @rmdir($dir);
}

$base = __DIR__;
$targets = array(
	$base . DIRECTORY_SEPARATOR . \'install\',
	$base . DIRECTORY_SEPARATOR . \'updater\',
	$base . DIRECTORY_SEPARATOR . \'install_recidivism.php\',
	$base . DIRECTORY_SEPARATOR . \'install_recidivism.sql\',
);
$logs = array();
$failed = 0;
foreach ($targets as $t) {
	$name = basename($t);
	if (is_dir($t)) {
		if (sb_setup_rrmdir($t) && !is_dir($t))
			$logs[] = \'OK: папка \' . $name;
		else {
			$failed++;
			$logs[] = \'FAIL: папка \' . $name . \' (частично занята? удали вручную)\';
		}
	} elseif (is_file($t)) {
		if (@unlink($t) && !is_file($t))
			$logs[] = \'OK: файл \' . $name;
		else {
			$failed++;
			$logs[] = \'FAIL: файл \' . $name;
		}
	} else {
		$logs[] = \'SKIP: \' . $name . \' (нет)\';
	}
}

$self = __FILE__;
@unlink($self);

echo \'<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Очистка</title>\';
echo \'<meta http-equiv="refresh" content="15;url=index.php">\';
echo \'<style>body{font-family:system-ui,sans-serif;background:#111;color:#eee;padding:2rem}a{color:#6cf}.ok{color:#2ecc71}.err{color:#e74c3c}</style></head><body>\';
echo \'<h1>Очистка установщика</h1><ul>\';
foreach ($logs as $line) {
	$cls = (strpos($line, \'FAIL\') === 0) ? \'err\' : \'ok\';
	echo \'<li class="\' . $cls . \'">\' . htmlspecialchars($line, ENT_QUOTES, \'UTF-8\') . \'</li>\';
}
echo \'</ul>\';
if ($failed > 0) {
	echo \'<p class="err">Что-то не удалилось (часто Windows держит файл). Добей остатки вручную.</p>\';
} else {
	echo \'<p class="ok">Готово. Установщик удалён.</p>\';
}
echo \'<p>Через 15 секунд откроется сайт…</p>\';
echo \'<p><a href="index.php">На сайт сейчас</a> · <a href="index.php?p=login">Вход</a></p>\';
echo \'</body></html>\';
';

	if (@file_put_contents($path, $code) === false) {
		return array('ok' => false, 'url' => null, 'path' => null, 'error' => 'Не удалось записать remove_setup.php');
	}

	return array(
		'ok' => true,
		'url' => '../remove_setup.php?t=' . rawurlencode($token),
		'path' => $path,
		'error' => null,
	);
}
