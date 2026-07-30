<?php
/**
 * API выпуска ваучеров (для магазина / бота / биллинга).
 *
 * Включить: в config.php задать длинный SB_VOUCHER_API_TOKEN (не пароль админа).
 * Пустой токен = API выключен.
 *
 * POST JSON или form-urlencoded:
 *   token | Authorization: Bearer …
 *   days          — срок админки в днях (0 = навсегда)
 *   group_web     — имя веб-группы из панели (или "0" = без группы)
 *   group_srv     — имя серверной группы (опционально, пусто = без)
 *   servers       — "" свободный выбор | "-1" без сервера | "s1,s2"
 *
 * Ответ: application/json
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/system-functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function sb_voucher_api_json($http, $payload)
{
	http_response_code((int)$http);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	sb_voucher_api_json(204, array());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	sb_voucher_api_json(405, array('ok' => false, 'error' => 'method_not_allowed', 'hint' => 'Use POST'));
}

if (!function_exists('sb_voucher_api_enabled') || !sb_voucher_api_enabled()) {
	sb_voucher_api_json(503, array(
		'ok' => false,
		'error' => 'api_disabled',
		'hint' => 'Set SB_VOUCHER_API_TOKEN in config.php',
	));
}

if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('voucher_api', 30, 900)) {
	sb_voucher_api_json(429, array('ok' => false, 'error' => 'rate_limited'));
}

// IP whitelist (опционально)
if (defined('SB_VOUCHER_API_ALLOW_IPS') && SB_VOUCHER_API_ALLOW_IPS !== '') {
	$allow = array_filter(array_map('trim', explode(',', SB_VOUCHER_API_ALLOW_IPS)));
	$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
	$okIp = false;
	foreach ($allow as $a) {
		if ($a !== '' && hash_equals($a, $ip)) {
			$okIp = true;
			break;
		}
	}
	if (!$okIp) {
		sb_voucher_api_json(403, array('ok' => false, 'error' => 'ip_denied'));
	}
}

$raw = file_get_contents('php://input');
$json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
$input = is_array($json) ? $json : $_POST;

$token = '';
if (!empty($input['token']))
	$token = (string)$input['token'];
elseif (!empty($_SERVER['HTTP_X_SB_VOUCHER_TOKEN']))
	$token = (string)$_SERVER['HTTP_X_SB_VOUCHER_TOKEN'];
else {
	// Apache/CGI: Authorization часто в REDIRECT_HTTP_AUTHORIZATION
	$auth = '';
	if (!empty($_SERVER['HTTP_AUTHORIZATION']))
		$auth = (string)$_SERVER['HTTP_AUTHORIZATION'];
	elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
		$auth = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
	if ($auth !== '' && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m))
		$token = $m[1];
}

if ($token === '' || !hash_equals(SB_VOUCHER_API_TOKEN, $token)) {
	if (class_exists('CSystemLog'))
		new CSystemLog("w", "Voucher API", "Отклонён запрос: неверный токен (IP " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?') . ")");
	sb_voucher_api_json(401, array('ok' => false, 'error' => 'unauthorized'));
}

$days = isset($input['days']) ? $input['days'] : 0;
$group_web = isset($input['group_web']) ? $input['group_web'] : '';
$group_srv = isset($input['group_srv']) ? $input['group_srv'] : '';
$servers = isset($input['servers']) ? $input['servers'] : '';

$result = sb_voucher_create_record($days, $group_web, $group_srv, $servers);
if (empty($result['ok'])) {
	$map = array(
		'group_web_required' => array(400, 'Укажите group_web (имя группы или "0")'),
		'group_web_unknown' => array(400, 'Веб-группа не найдена — имя как в панели'),
		'group_srv_unknown' => array(400, 'Серверная группа не найдена — имя как в панели'),
		'insert_failed' => array(500, 'Ошибка записи в БД'),
		'key_collision' => array(500, 'Не удалось сгенерировать уникальный ключ'),
		'db' => array(500, 'Нет соединения с БД'),
	);
	$err = isset($result['error']) ? $result['error'] : 'unknown';
	$info = isset($map[$err]) ? $map[$err] : array(400, $err);
	sb_voucher_api_json($info[0], array('ok' => false, 'error' => $err, 'hint' => $info[1]));
}

$base = (defined('SB_WP_URL') && SB_WP_URL !== '') ? rtrim(SB_WP_URL, '/') : '';
$activate = ($base !== '' ? $base . '/' : '') . 'index.php?p=pay';

if (class_exists('CSystemLog')) {
	new CSystemLog(
		"m",
		"Voucher API",
		"Выпущен ваучер " . $result['key'] . " (дней: " . $result['days']
			. ", веб: " . $result['group_web']
			. ", srv: " . ($result['group_srv'] !== '' ? $result['group_srv'] : '—')
			. ", IP " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?') . ")"
	);
}

sb_voucher_api_json(200, array(
	'ok' => true,
	'key' => $result['key'],
	'key_fmt' => $result['key_fmt'],
	'days' => $result['days'],
	'group_web' => $result['group_web'],
	'group_srv' => $result['group_srv'],
	'servers' => $result['servers'],
	'activate_url' => $activate,
));
