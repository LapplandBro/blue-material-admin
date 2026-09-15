<?php
if (!defined("IN_SB")) { echo "Ошибка доступа!"; die(); }
global $theme, $userbank;

function sb_voucher_v2_on()
{
	return function_exists('sb_ui_v2_enabled') && sb_ui_v2_enabled() && function_exists('sb_ui_v2_render');
}

function sb_voucher_v2_render($extra)
{
	$base = array(
		'title' => 'Активация ваучера',
		'page_blocked' => false,
		'flash_type' => '',
		'flash_title' => '',
		'flash_html' => '',
		'server_list' => array(),
		'server_script' => '',
		'param' => '0',
		'error_msg' => '',
		'sb_csrf' => '',
		'days' => '',
		'gr_web' => '',
		'gr_srv' => '',
		'klu4ik' => '',
		'klu4ik_js' => '""',
		'servers' => '',
		'captcha_t' => time(),
		'form_action' => 'index.php?p=pay',
		'pay_url' => 'index.php?p=pay',
	);
	sb_ui_v2_render('voucher.twig', array_merge($base, $extra));
	return true;
}

if (!isset($GLOBALS['config']['page.vay4er']) || (string)$GLOBALS['config']['page.vay4er'] !== "1") {
	if (sb_voucher_v2_on()) {
		sb_voucher_v2_render(array(
			'page_blocked' => true,
			'flash_type' => 'error',
			'flash_title' => 'Ошибка',
			'flash_html' => 'Страница активации ваучеров отключена.',
		));
		return;
	}
	CreateRedBox("Ошибка", "Страница активации ваучеров отключена.");
	PageDie();
}

// Активация только для гостей: создаёт новый аккаунт админа. Залогиненный уже «в системе».
if (isset($userbank) && is_object($userbank) && method_exists($userbank, 'is_logged_in') && $userbank->is_logged_in()) {
	$msg = 'Ваучер активирует только гость (неавторизованный пользователь). Выйдите из аккаунта или откройте ссылку в режиме инкогнито, затем перейдите на страницу активации.';
	if (sb_voucher_v2_on()) {
		sb_voucher_v2_render(array(
			'page_blocked' => true,
			'flash_type' => 'error',
			'flash_title' => 'Активация недоступна',
			'flash_html' => $msg,
		));
		return;
	}
	CreateRedBox("Активация недоступна", $msg);
	PageDie();
}

if (function_exists('sb_session_start'))
	sb_session_start();
elseif (session_status() === PHP_SESSION_NONE)
	@session_start();

$error_msg = '';
$vaxye_vso = "0";
$validation = '';
$voucher_servers = '';

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
			$validation = function_exists('sb_voucher_normalize_key')
				? sb_voucher_normalize_key($raw)
				: strtolower(preg_replace('/[^0-9a-fA-F]/', '', $raw));

			if (!function_exists('sb_voucher_key_valid') || !sb_voucher_key_valid($validation)) {
				$error_msg = 'Ваучер должен быть 32 hex-символа (16 байт), например a1b2-c3d4-….';
			} else {
				$row = $GLOBALS['db']->GetRow(
					"SELECT `activ`, `group_web`, `group_srv`, `days`, `servers` FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?",
					array($validation)
				);

				if (!$row || (string)$row['activ'] !== '1') {
					$error_msg = 'Ваучер не найден или уже активирован.';
				} else {
					// Разблокировка шага 2: без неё xajax AddAdmin_pay отклонит код.
					if (function_exists('sb_voucher_unlock_set'))
						sb_voucher_unlock_set($validation);

					$vaxye_vso = "1";
					$user_group_web = ($row['group_web'] === '' || $row['group_web'] === '0' || $row['group_web'] === null)
						? 'Не указана / нет группы'
						: $row['group_web'];
					$user_group_srv = ($row['group_srv'] === '' || $row['group_srv'] === '0' || $row['group_srv'] === null)
						? 'Не указана / нет группы'
						: $row['group_srv'];
					$pay_days = (string)$row['days'];
					$pay_days_t = ($pay_days === '0') ? 'Навсегда' : ($pay_days . ' дн.');
					$display_key = function_exists('sb_voucher_format_key')
						? sb_voucher_format_key($validation)
						: $validation;
					$klu4ik_js = json_encode($validation);

					$theme->assign('days', $pay_days_t);
					$theme->assign('gr_web', $user_group_web);
					$theme->assign('gr_srv', $user_group_srv);
					$theme->assign('klu4ik', $display_key);
					$theme->assign('klu4ik_js', $klu4ik_js);
					$voucher_servers = isset($row['servers']) ? $row['servers'] : '';
					$theme->assign('servers', $voucher_servers);
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

if (sb_voucher_v2_on()) {
	sb_voucher_v2_render(array(
		'server_list' => $server_list,
		'server_script' => $serverscript,
		'param' => $vaxye_vso,
		'error_msg' => $error_msg,
		'sb_csrf' => function_exists('sb_csrf_token') ? sb_csrf_token() : '',
		'days' => isset($pay_days_t) ? $pay_days_t : '',
		'gr_web' => isset($user_group_web) ? $user_group_web : '',
		'gr_srv' => isset($user_group_srv) ? $user_group_srv : '',
		'klu4ik' => isset($display_key) ? $display_key : '',
		'klu4ik_js' => isset($klu4ik_js) ? $klu4ik_js : '""',
		'servers' => $voucher_servers,
	));
	return;
}
