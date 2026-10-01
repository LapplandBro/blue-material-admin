<?php

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	echo 'Запрещено.';
	exit;
}

@ini_set('default_socket_timeout', '3');
if (function_exists('set_time_limit'))
	@set_time_limit(120);

chdir(dirname(__DIR__));

if (!isset($_SERVER['REQUEST_URI']) || trim((string)$_SERVER['REQUEST_URI']) === '')
	$_SERVER['REQUEST_URI'] = '/cron/collect_online.php';

require dirname(__DIR__) . '/init.php';
require dirname(__DIR__) . '/includes/system-functions.php';
require dirname(__DIR__) . '/includes/sb-online.php';

$r = sb_online_collect();
if (!is_array($r) || empty($r['enabled'])) {
	echo "Сбор выключен.\n";
	exit(0);
}
if (!empty($r['busy'])) {
	echo "Сбор уже идёт.\n";
	exit(0);
}
if (!empty($r['error'])) {
	echo $r['error'] . "\n";
	exit(1);
}

echo 'Серверов: ' . (int)$r['servers'] . ', ответили: ' . (int)$r['ok'] . ', молчат: ' . (int)$r['down'] . ', записано: ' . (int)(isset($r['saved']) ? $r['saved'] : 0) . "\n";
exit(0);
