<?php 
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

global $theme, $userbank;

/**
 * Проверка формата параметров ссылки сброса (без обращения к БД).
 * @return string|null текст ошибки или null если ок
 */
function sb_lostpass_validate_params($email, $validation)
{
	if (!is_string($email) || !is_string($validation))
		return 'Некорректный запрос.';
	$email = trim($email);
	$validation = trim($validation);
	if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL))
		return 'Некорректный запрос.';
	// Сырой токен из письма — ровно 64 hex-символа.
	if (!preg_match('/^[a-f0-9]{64}$/i', $validation))
		return 'Некорректный запрос.';
	return null;
}

function sb_lostpass_alert($type, $title, $text)
{
	$class = ($type === 'success') ? 'alert-success' : 'alert-danger';
	$id = ($type === 'success') ? 'msg-blue' : 'msg-red';
	echo '<div class="alert ' . $class . '" role="alert" id="' . $id . '"><h4>'
		. htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
		. '</h4><span class="p-l-10">'
		. $text
		. '</span></div>';
}

$confirm_get = (
	isset($_GET['validation'], $_GET['email'])
	&& $_GET['email'] !== ''
	&& $_GET['validation'] !== ''
	&& (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST')
);

$confirm_post = (
	isset($_SERVER['REQUEST_METHOD'])
	&& strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST'
	&& isset($_POST['confirm_reset'], $_POST['email'], $_POST['validation'])
);

if ($confirm_post) {
	$email = $_POST['email'];
	$validation = $_POST['validation'];

	$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
		sb_lostpass_alert('error', 'Ошибка!', 'Сессия истекла или неверный CSRF-токен. Откройте ссылку из письма ещё раз.');
		require(TEMPLATES_PATH . "/footer.php");
		exit();
	}

	if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('lostpass_confirm', 10, 900)) {
		sb_lostpass_alert('error', 'Ошибка!', 'Слишком много попыток. Подождите несколько минут.');
		require(TEMPLATES_PATH . "/footer.php");
		exit();
	}

	$param_err = sb_lostpass_validate_params($email, $validation);
	if ($param_err !== null) {
		new CSystemLog("w", "LostPassword confirm probe", "Malformed confirm from IP " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
		sb_lostpass_alert('error', 'Ошибка!', 'Строка проверки не соответствует адресу электронной почты для запроса на сброс.');
		require(TEMPLATES_PATH . "/footer.php");
		exit();
	}

	$email = trim($email);
	$validation = trim($validation);
	$token_hash = hash('sha256', $validation);

	$q = $GLOBALS['db']->GetRow(
		"SELECT `aid`, `user`, `validate` FROM `" . DB_PREFIX . "_admins` WHERE `email` = ? AND `validate` IS NOT NULL",
		array($email)
	);

	// Формат хранения: `hash:expiry`. Старый формат (просто hash без ":") - токены,
	// выданные до этого фикса, без срока годности - принимаем один раз для обратной
	// совместимости, затем в любом случае затираем `validate`.
	$aid = 0;
	if ($q && !empty($q['aid'])) {
		$stored = (string)$q['validate'];
		$sep = strrpos($stored, ':');
		if ($sep !== false) {
			$stored_hash = substr($stored, 0, $sep);
			$expiry = (int)substr($stored, $sep + 1);
			if ($expiry > time() && hash_equals($stored_hash, $token_hash))
				$aid = (int)$q['aid'];
		} elseif (hash_equals($stored, $token_hash)) {
			$aid = (int)$q['aid'];
		}
	}

	if ($aid > 0) {
		$newpass = generate_salt(MIN_PASS_LENGTH + 8);
		$GLOBALS['db']->Execute(
			"UPDATE `" . DB_PREFIX . "_admins` SET `password` = ?, `validate` = NULL WHERE `aid` = ?",
			array($userbank->hash_password($newpass), $aid)
		);
		// Сбрасываем активные web-сессии администратора - смена пароля должна выкидывать все входы.
		if (function_exists('sb_clear_web_session'))
			sb_clear_web_session($aid);
		$message = "Привет " . $q['user'] . ",\n\n";
		$message .= "Ваш пароль был успешно сброшен.\n";
		$message .= "Ваш пароль изменен на: ".$newpass."\n\n";
		$message .= "Войдите в ваш аккаунт SourceBans и смените пароль.\n";

		// From на основе SB_WP_URL, не HTTP_Host (header injection / spoof).
		$headers = 'From: SourceBans@' . sb_get_site_host() . "\n" .
		'X-Mailer: PHP/' . phpversion();
		EMail($email, "Сброс пароля SourceBans", $message, $headers);

		sb_lostpass_alert(
			'success',
			'Успешно!',
			'Ваш пароль был сброшен и отправлен вам на почту.<br />Проверьте папку "Спам" тоже.<br />Пожалуйста, войдите, используя этот пароль, затем смените пароль в вашей учетной записи на свой, нормальный :).'
		);
	} else {
		sb_lostpass_alert('error', 'Ошибка!', 'Строка проверки не соответствует адресу электронной почты для запроса на сброс.');
	}
	require(TEMPLATES_PATH . "/footer.php");
	exit();
}

if ($confirm_get) {
	$email = $_GET['email'];
	$validation = $_GET['validation'];

	// GET только показывает форму подтверждения — пароль НЕ меняем.
	// Иначе prefetch почтовых сканеров / антивирусов сжигает токен и сбрасывает пароль.
	$param_err = sb_lostpass_validate_params($email, $validation);
	if ($param_err !== null) {
		if (!is_string($email) || !is_string($validation)) {
			new CSystemLog("e", "Попытка взлома", "Произошла попытка взлома системы с использованием некорректно построенного запроса SQL.");
			sb_lostpass_alert('error', 'Ошибка', 'Была зафиксирована попытка взлома системы через некорректно построенный запрос. Данная попытка была записана в системный лог.');
		} else {
			sb_lostpass_alert('error', 'Ошибка!', 'Некорректная ссылка сброса пароля.');
		}
		require(TEMPLATES_PATH . "/footer.php");
		exit();
	}

	$theme->assign('lostpass_confirm', true);
	$theme->assign('lostpass_email', trim($email));
	$theme->assign('lostpass_validation', trim($validation));
	$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
	$theme->display('page_lostpassword.tpl');
} else {
	$theme->assign('lostpass_confirm', false);
	$theme->display('page_lostpassword.tpl');
}
