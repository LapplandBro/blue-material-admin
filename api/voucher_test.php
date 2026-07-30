<?php
/**
 * Тестер Voucher API.
 *
 * CLI:  php api/voucher_test.php
 *       php api/voucher_test.php --keep
 *
 * Браузер: только ADMIN_OWNER (куки сессии панели).
 *   api/voucher_test.php
 *   api/voucher_test.php?keep=1  — не удалять тестовый ключ
 */
require_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/system-functions.php';

$isCli = (php_sapi_name() === 'cli');
$keep = false;

if ($isCli) {
	global $argv;
	if (is_array($argv)) {
		foreach ($argv as $a) {
			if ($a === '--keep' || $a === '-k')
				$keep = true;
		}
	}
} else {
	header('Content-Type: text/html; charset=utf-8');
	global $userbank;
	if (!isset($userbank) || !is_object($userbank) || !$userbank->HasAccess(ADMIN_OWNER)) {
		http_response_code(403);
		echo 'Доступ только OWNER. Залогинься в панель и открой снова, либо: <code>php api/voucher_test.php</code>';
		exit;
	}
	$keep = !empty($_GET['keep']);
}

$result = sb_voucher_api_self_test($keep);

if ($isCli) {
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
	exit(!empty($result['ok']) ? 0 : 1);
}

$ok = !empty($result['ok']);
$color = $ok ? '#2e7d32' : '#c62828';
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Voucher API test</title>';
echo '<style>body{font-family:Consolas,monospace;background:#111;color:#eee;padding:24px}';
echo 'pre{background:#1e1e1e;padding:16px;border-radius:8px;overflow:auto;border:1px solid #333}';
echo 'a{color:#81d4fa}</style></head><body>';
echo '<h1 style="color:' . $color . '">' . ($ok ? 'OK — API работает' : 'FAIL — API не ответил как надо') . '</h1>';
echo '<p>URL: <code>' . htmlspecialchars(isset($result['url']) ? $result['url'] : '—', ENT_QUOTES, 'UTF-8') . '</code>';
echo ' · HTTP ' . (isset($result['http']) ? (int)$result['http'] : 0);
if (!empty($result['cleaned']))
	echo ' · тестовый ключ удалён из БД';
elseif ($ok && $keep)
	echo ' · ключ <b>оставлен</b> в БД (--keep)';
echo '</p>';
echo '<pre>' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '</pre>';
echo '<p><a href="voucher_test.php">Ещё раз (удалить ключ)</a> · <a href="voucher_test.php?keep=1">Ещё раз (оставить ключ)</a> · ';
echo '<a href="../index.php?p=admin&amp;c=pay_card">← Ваучеры</a></p>';
echo '</body></html>';
