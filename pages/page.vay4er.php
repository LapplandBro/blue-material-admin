<?php
if (!defined("IN_SB")) { echo "Ошибка доступа!"; die(); }
global $theme, $userbank;

if (!isset($GLOBALS['config']['page.vay4er']) || (string)$GLOBALS['config']['page.vay4er'] !== "1") {
	CreateRedBox("Ошибка", "Страница активации ваучеров отключена.");
	PageDie();
}

if (function_exists('sb_session_start'))
	sb_session_start();
elseif (session_status() === PHP_SESSION_NONE)
	@session_start();

$error_msg = '';
$vaxye_vso = "0";
$validation = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pay_v4'])) {
	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
		$error_msg = 'Сессия устарела. Обновите страницу и попробуйте снова.';
	} elseif (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('vay4er_form', 20, 900)) {
		$error_msg = 'Слишком много попыток. Подождите несколько минут.';
	} else {
		$kapcha = isset($_POST['kapcha']) ? trim((string)$_POST['kapcha']) : '';
		$expect = isset($_SESSION['rand_code']) ? (string)$_SESSION['rand_code'] : '';
		// одноразовый код
		unset($_SESSION['rand_code']);

		if ($expect === '' || !hash_equals(strtolower($expect), strtolower($kapcha))) {
			$error_msg = 'Проверочный код неверен. Обновите картинку и введите заново.';
		} else {
			$raw = (string)$_POST['pay_v4'];
			$validation = preg_replace('/[^0-9]/', '', function_exists('RemoveCode') ? RemoveCode($raw) : $raw);

			if (strlen($validation) !== 16) {
				$error_msg = 'Ваучер должен содержать 16 цифр.';
			} else {
				$row = $GLOBALS['db']->GetRow(
					"SELECT `activ`, `group_web`, `group_srv`, `days`, `servers` FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?",
					array($validation)
				);

				if (!$row || (string)$row['activ'] !== '1') {
					$error_msg = 'Ваучер не найден или уже активирован.';
				} else {
					$vaxye_vso = "1";
					$user_group_web = ($row['group_web'] === '' || $row['group_web'] === '0' || $row['group_web'] === null)
						? 'Не указана / нет группы'
						: $row['group_web'];
					$user_group_srv = ($row['group_srv'] === '' || $row['group_srv'] === '0' || $row['group_srv'] === null)
						? 'Не указана / нет группы'
						: $row['group_srv'];
					$pay_days = (string)$row['days'];
					$pay_days_t = ($pay_days === '0') ? 'Навсегда' : ($pay_days . ' дн.');

					$theme->assign('days', $pay_days_t);
					$theme->assign('gr_web', $user_group_web);
					$theme->assign('gr_srv', $user_group_srv);
					$theme->assign('klu4ik', $validation);
					$theme->assign('klu4ik_js', json_encode($validation));
					$theme->assign('servers', isset($row['servers']) ? $row['servers'] : '');
				}
			}
		}
	}
}

$servers = $GLOBALS['db']->GetAll("SELECT sid, ip, port FROM `" . DB_PREFIX . "_servers` WHERE enabled = 1 ORDER BY sid ASC");
$server_list = array();
$serverscript = '<script type="text/javascript">';
if (is_array($servers)) {
	foreach ($servers as $server) {
		$serverscript .= "xajax_ServerHostPlayers('" . (int)$server['sid'] . "', 'id', 'sa" . (int)$server['sid'] . "');";
		$server_list[] = array(
			'sid' => $server['sid'],
			'ip' => $server['ip'],
			'port' => $server['port'],
		);
	}
}
$serverscript .= '</script>';

$theme->assign('server_list', $server_list);
$theme->assign('server_script', $serverscript);
$theme->assign('param', $vaxye_vso);
$theme->assign('error_msg', $error_msg);
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
$theme->display('page_vay4er.tpl');
