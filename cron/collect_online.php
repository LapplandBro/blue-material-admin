<?php

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	echo 'Запрещено.';
	exit;
}

chdir(dirname(__DIR__));

if (!isset($_SERVER['REQUEST_URI']) || trim((string)$_SERVER['REQUEST_URI']) === '')
	$_SERVER['REQUEST_URI'] = '/cron/collect_online.php';

require dirname(__DIR__) . '/init.php';
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

echo 'Серверов: ' . (int)$r['servers'] . ', ответили: ' . (int)$r['ok'] . ', молчат: ' . (int)$r['down'] . "\n";
exit(0);
