<?php
if (!defined("IN_SB")) {
	echo "Ошибка доступа!";
	die();
}

global $userbank, $theme;

$pending = function_exists('sb_mfa_pending') ? sb_mfa_pending() : null;
if (!$pending) {
	if (function_exists('sb_redirect'))
		sb_redirect(sb_url('login'));
	header('Location: login');
	exit;
}

$aid = (int)$pending['aid'];
$mode = $pending['mode'];
$row = sb_totp_row($aid);
if (!$row) {
	sb_mfa_clear();
	if (function_exists('sb_redirect'))
		sb_redirect(sb_url('login'));
	header('Location: login');
	exit;
}

$error = '';
$recovery_show = null;
$issuer = sb_totp_issuer();

// Forced enroll during login (OWNER + enforce).
if ($mode === 'enroll') {
	if (empty($_SESSION['sb_totp_pending_secret'])) {
		$_SESSION['sb_totp_pending_secret'] = sb_totp_generate_secret();
		$_SESSION['sb_totp_pending_codes'] = sb_totp_generate_recovery_codes(8);
	}
	$secret = $_SESSION['sb_totp_pending_secret'];
	$codes = $_SESSION['sb_totp_pending_codes'];

	if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && isset($_POST['totp_enroll'])) {
		$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
		if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
			$error = 'Сессия истекла. Обновите страницу.';
		} elseif (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('totp_enroll', 10, 900)) {
			$error = 'Слишком много попыток. Подождите.';
		} elseif (!sb_totp_verify($secret, isset($_POST['code']) ? $_POST['code'] : '')) {
			$error = 'Неверный код. Проверьте время на устройстве и попробуйте снова.';
		} else {
			sb_totp_enable($aid, $secret, $codes);
			$recovery_show = $codes;
			if ($userbank->login_by_aid($aid, !empty($pending['remember']))) {
				$log = new CSystemLog("m", "Успешный вход", "Администратор '" . htmlspecialchars($row['user']) . "' вошёл (2FA enroll).", false);
				$log->aid = $aid;
				$log->WriteLog();
			}
			sb_mfa_clear();
			$theme->assign('login2fa_mode', 'recovery');
			$theme->assign('recovery_codes', $recovery_show);
			$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
			$theme->display('page_login2fa.tpl');
			return;
		}
	}

	$theme->assign('login2fa_mode', 'enroll');
	$theme->assign('totp_secret', $secret);
	$theme->assign('totp_otpauth', sb_totp_otpauth_uri($secret, $row['user'], $issuer));
	$theme->assign('totp_user', $row['user']);
	$theme->assign('error', $error);
	$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
	$theme->display('page_login2fa.tpl');
	return;
}

// Challenge mode.
if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && isset($_POST['totp_challenge'])) {
	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
		$error = 'Сессия истекла. Обновите страницу.';
	} elseif (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('totp_challenge', 12, 900)) {
		$error = 'Слишком много попыток. Подождите.';
	} else {
		$code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
		$ok = false;
		$secret = sb_totp_secret_for_aid($aid);
		if ($secret !== '' && sb_totp_verify($secret, $code))
			$ok = true;
		elseif (sb_totp_consume_recovery($aid, $code)) {
			$ok = true;
			new CSystemLog("w", "2FA recovery", "Использован recovery-код для aid=" . $aid);
		}
		if ($ok) {
			if ($userbank->login_by_aid($aid, !empty($pending['remember']))) {
				$log = new CSystemLog("m", "Успешный вход", "Администратор '" . htmlspecialchars($row['user']) . "' вошёл (2FA).", false);
				$log->aid = $aid;
				$log->WriteLog();
			}
			sb_mfa_clear();
			if (function_exists('sb_redirect'))
				sb_redirect(sb_url('account'));
			header('Location: account');
			exit;
		}
		$error = 'Неверный код или recovery-код.';
	}
}

$theme->assign('login2fa_mode', 'challenge');
$theme->assign('totp_user', $row['user']);
$theme->assign('error', $error);
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
$theme->display('page_login2fa.tpl');
