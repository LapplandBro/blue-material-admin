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

if(isset($_GET['validation'],$_GET['email']) && !empty($_GET['email']) && !empty($_GET['validation']))
{  
	$email = $_GET['email'];
	$validation = $_GET['validation'];
	$tryHack = false;
	
	if (is_array($email) || is_array($validation))
		$tryHack = true;
	
	if ($tryHack) {
		CreateRedBox("Ошибка", "Была зафиксирована попытка взлома системы через некорректно построенный запрос. Данная попытка была записана в системный лог.");
		require(TEMPLATES_PATH . "/footer.php");
		$log = new CSystemLog("e", "Попытка взлома", "Произошла попытка взлома системы с использованием некорректно построенного запроса SQL.");
		exit();
	}
	
	preg_match("@^(?:http://)?([^/]+)@i", $_SERVER['HTTP_HOST'], $match);

	if($match[0] != $_SERVER['HTTP_HOST']) 
	{ 
		echo '<div class="alert alert-danger" role="alert" id="msg-red"><h4>Ошибка!</h4><span class="p-l-10">Произошла неизвестная ошибка.</span></div>';
	
		require(TEMPLATES_PATH . "/footer.php");
		$log = new CSystemLog("w", "Попытка взлома", "Попытка сброса пароля с использованием: " . $_SERVER['HTTP_HOST']);
		exit();
	}

	if(strlen($validation) < 60)
	{
		echo '<div class="alert alert-danger" role="alert" id="msg-red"><h4>Ошибка!</h4><span class="p-l-10">Строка проверки является слишком короткой.</span></div>';
	
		require(TEMPLATES_PATH . "/footer.php");
		exit();
	}
	
	$q = $GLOBALS['db']->GetRow("SELECT aid, user FROM `" . DB_PREFIX . "_admins` WHERE `email` = ? && `validate` IS NOT NULL && `validate` = ?", array($email, $validation));
	if($q)
	{
		$newpass = generate_salt(MIN_PASS_LENGTH+8);
		$query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `password` = ?, validate = NULL WHERE `aid` = ?", array($userbank->hash_password($newpass), $q['aid']));
		$message = "Привет " . $q['user'] . ",\n\n";
		$message .= "Ваш пароль был успешно сброшен.\n";
		$message .= "Ваш пароль изменен на: ".$newpass."\n\n";
		$message .= "Войдите в ваш аккаунт SourceBans и смените пароль.\n";

		// Раньше здесь использовался $_SERVER['HTTP_HOST'] (заголовок Host, полностью
		// подконтрольный клиенту) для построения адреса отправителя - это позволяло бы
		// внедрить произвольные заголовки письма при специально сформированном Host.
		// Используем вместо этого доверенный домен из SB_WP_URL (config.php).
		$headers = 'From: SourceBans@' . sb_get_site_host() . "\n" .
		'X-Mailer: PHP/' . phpversion();
		$m = EMail($email, "Сброс пароля SourceBans", $message, $headers);
		
		echo '<div class="alert alert-success" role="alert" id="msg-blue"><h4>Успешно!</h4><span class="p-l-10">Ваш пароль был сброшен и отправлен вам на почту.<br />Проверьте папку "Спам" тоже.<br />Пожалуйста, войдите, используя этот пароль, затем смените пароль в вашей учетной записи на свой, нормальный :).</span></div>';
	}
	else 
	{
		echo '<div class="alert alert-danger" role="alert" id="msg-red"><h4>Ошибка!</h4><span class="p-l-10">Строка проверки не соответствует адресу электронной почты для запроса на сброс.</span></div>';
	}
}else 
{
	$theme->display('page_lostpassword.tpl');
}
