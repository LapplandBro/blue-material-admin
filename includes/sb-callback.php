<?php

if (function_exists('sb_session_start')) {
	sb_session_start();
} else {
	session_start();
}

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


require_once('xajax.inc.php');
include_once('system-functions.php');
include_once('user-functions.php');
$xajax = new xajax();
//$xajax->debugOn();
$xajax->setRequestURI(XAJAX_REQUEST_URI);
global $userbank;

$methods = array('admin' => array('AddMod', 'RemoveMod', 'AddGroup', 'RemoveGroup', 'RemoveAdmin', 'RemoveSubmission', 'RemoveServer', 'UpdateGroupPermissions', 'UpdateAdminPermissions', 'AddAdmin', 'SetupEditServer', 'AddServerGroupName', 'AddServer', 'AddBan', 'RehashAdmins', 'EditGroup', 'RemoveProtest', 'SendRcon', 'EditAdminPerms', 'AddComment', 'EditComment', 'RemoveComment', 'PrepareReban', 'Maintenance', 'KickPlayer', 'GroupBan', 'BanMemberOfGroup', 'GetGroups', 'BanFriends', 'SendMessage', 'ViewCommunityProfile', 'SetupBan', 'CheckPassword', 'ChangePassword', 'CheckSrvPassword', 'ChangeSrvPassword', 'ChangeEmail', 'SendMail', 'AddBlock', 'PrepareReblock', 'PrepareBlockFromBan', 'removeExpiredAdmins', 'AddSupport', 'ChangeAdminsInfos', 'InstallMOD', 'PastePlayerData', 'AddWarning', 'RemoveWarning'), 'default' => array('Plogin', 'ServerHostPlayers', 'ServerHostProperty', 'ServerHostPlayers_list', 'ServerPlayers', 'LostPassword', 'RefreshServer', 'AddAdmin_pay', 'RehashAdmins_pay'));

if(isset($_COOKIE['aid'], $_COOKIE['password']) && $userbank->CheckLogin($_COOKIE['password'], $_COOKIE['aid']))
    foreach ($methods['admin'] as $method)
        $xajax->registerFunction($method);

foreach ($methods['default'] as $method)
    $xajax->registerFunction($method);

global $userbank;
$username = $userbank->GetProperty("user");

function InstallMOD($modfolder, $status = 0) {
    global $userbank;
    
    $objResponse = new xajaxResponse();
    $objResponse->addAlert("Выключено. Находится в стадии разработки");
    return $objResponse;
    
    /* TODO: Добавить загрузку данных из репозитория */
    $mapformat = str_replace('{%folder%}', $GameData['folder'], $RepoData['mapformat']);
    $PathIcon = sprintf('%s/%s', SB_ICON_LOCATION, $GameData['icon']);
    $PathMaps = sprintf('%s/%s', SB_MAP_LOCATION, $mapformat);
    
    if ($status == 0) {
        /* Build install dialog */
        $objResponse->addAssign("install_log", "innerHTML", "[".SBDate($GLOBALS['config']['config.dateformat'], time())."] Загрузка файлов с зеркала...");
        $objResponse->addAssign("install_current", "innerHTML", "Загрузка файлов с зеркала");
        $objResponse->addScript('xajax_InstallMOD("'.$modfolder.'", 1);');
    } else if ($status == 1) {
        /* Download files */
        file_put_contents($PathIcon, sprintf('%s%s%s', $RepoData['mirror'], $RepoData['icons_dir'], $GameData['icon']));
        file_put_contents($PathMaps, sprintf('%s%s%s', $RepoData['mirror'], $RepoData['maps_dir'], $mapformat));
        
        $objResponse->addAppend("install_log", "innerHTML", "<br />[".SBDate($GLOBALS['config']['config.dateformat'], time())."] Распаковка архива");
        $objResponse->addAssign("install_current", "innerHTML", "Распаковка архива");
        
        $objResponse->addScript('xajax_InstallMOD("'.$modfolder.'", 2);');
    } else if ($status == 2) {
        /* Decompress maps dir */
        decompress_tar($PathMaps, SB_MAP_LOCATION.'/'.$GameData['folder'].'/');
        
        $objResponse->addAppend("install_log", "innerHTML", "<br />[".SBDate($GLOBALS['config']['config.dateformat'], time())."] Удаление временных файлов");
        $objResponse->addAssign("install_current", "innerHTML", "Удаление временных файлов");
        
        $objResponse->addScript('xajax_InstallMOD("'.$modfolder.'", 3);');
    } else if ($status == 3) {
        /* Insert to DB */
        $GLOBALS['db']->Execute(sprintf("INSERT INTO `%s_mods` (`name`, `icon`, `modfolder`, `steam_universe`, `enabled`) VALUES (%s, %s, %s, %d, 1);", DB_PREFIX, $GLOBALS['db']->qstr($GameData['name']), $GLOBALS['db']->qstr($GameData['icon']), $GLOBALS['db']->qstr($GameData['folder']), (int) $GameData['steamcode']));
    
        $objResponse->addAppend("install_log", "innerHTML", "<br />[".SBDate($GLOBALS['config']['config.dateformat'], time())."] Завершено.");
        $objResponse->addAssign("install_current", "innerHTML", "Установка завершена.");
    }
    
    return $objResponse;
}

function AddSupport($aid)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	$aid = (int)$aid;
    if(!$userbank->is_logged_in())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытается назначить администратора ".$userbank->GetProperty('user', $aid)." в Support-List, не имея на это прав.");
		return $objResponse;
	}elseif(!$userbank->HasAccess(ADMIN_OWNER)){
		$objResponse->addScript('ShowBox("Ошибка!", "У Вас недостаточно прав для выполнения этой операции!", "red", "index.php");');
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался назначить администратора в Support-List, не имея на это прав.");
		return $objResponse;
	}
	

	$res = $GLOBALS['db']->GetOne("SELECT `support` FROM `".DB_PREFIX."_admins` WHERE `aid` = '".$aid."'");
	if($res == "1"){
		$chek = "0";
		$chek1 = "убран";
	}else{
		$chek = "1";
		$chek1 = "добавлен";
	}	
	$query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `support` = ? WHERE `aid` = '".$aid."'", array((int)$chek));
	if($query) {
		$objResponse->addScript('ShowBox("Support-List", "Администратор был '.$chek1.', обновите страницу, чтобы увидеть результат, либо продолжайте дальнейшую работу.", "blue", "", true);');
		$log = new CSystemLog("m", "Support-List изменён", $username . " " . $chek1 . " администратора (" . $userbank->GetProperty('user', $aid) . ") в Support-List.");
	}
	
	return $objResponse;
}
function removeExpiredAdmins()
{
	global $userbank, $username;
	$objResponse = new xajaxResponse();

	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS))
	{
		$objResponse->addScript('ShowBox("Ошибка!", "У Вас недостаточно прав для выполнения этой операции!.", "red", "index.php?p=admin&c=admins");');
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить истёкших админов, не имея на это прав.");
		return $objResponse;
	}
	if($GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_admins` WHERE `expired` < ".time()." AND `expired` <> 0")) {
		$objResponse->addScript('ShowBox("Успешно!", "Все истёкшие админки удалены.", "green", "index.php?p=admin&c=admins");');
		$log = new CSystemLog("m", "Удаление админов", $username . " удалил всех истёкших админов.");
	}
	else {
		$objResponse->addScript('ShowBox("Ошибка!", "Ошибка в удалении истёкших админок. <br /> Смотрите в системный лог для подробной информации.", "red", "index.php?p=admin&c=admins");');
		$log = new CSystemLog("w", "Удаление админов", "Ошибка удаления истёкших админок.");
	}
	
	return $objResponse;
}

function Plogin($username, $password, $remember, $redirect, $nopass)
{
	global $userbank;
	$objResponse = new xajaxResponse();
	if (empty($password)) {
		ShowBox_ajx("Информация", "Не введён пароль. Введите пароль, и повторите попытку ещё раз.", "blue", $objResponse, "", true);
		return $objResponse;
	}
	// Антибрутфорс: 8 попыток / 15 минут с одного IP.
	if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('plogin', 8, 900)) {
		ShowBox_ajx("Слишком много попыток", "Подождите несколько минут и попробуйте снова.", "red", $objResponse, "", true);
		return $objResponse;
	}
	$q = $GLOBALS['db']->GetRow("SELECT `aid`, `password`, `expired` FROM `" . DB_PREFIX . "_admins` WHERE `user` = ?", array($username));
	$aid = $q ? (int)$q[0] : 0;
	if($q && strlen($q[1]) == 0 && count($q) != 0)
	{
		$objResponse->addScript('ShowBox("Информация", "Вы не можете залогиниться. Не установлен пароль.", "blue", "", true);');
		return $objResponse;
	} else if(!$q || !$userbank->verify_password($password, $aid))
	{
		// БАГ-ФИКС: раньше неудачные попытки входа не логировались вообще - невозможно было
		// увидеть перебор паролей (брутфорс) или понять, кто и когда пытался зайти под чужим логином.
		$log = new CSystemLog("w", "Неудачный вход", "Неудачная попытка входа под логином '" . htmlspecialchars($username) . "' с IP " . $_SERVER["REMOTE_ADDR"] . ".");
		if($nopass!=1)
			$objResponse->addScript('ShowBox("Вход неудался", "Неверно введены имя пользователя или пароль.<br \> Если Вы забыли свой пароль, Используйте ссылку <a href=\"index.php?p=lostpassword\" title=\"Забыл пароль\">Забыл пароль.</a>", "red", "", true);');
		return $objResponse;
	}
	else if($q[2] > 0 && $q[2] < time())
	{
		$objResponse->addScript('ShowBox("Превышение полномочий", "Запись администратора истёкла, или сработала защита сайта, обратитесь к владельцу сайта.", "red", "", true);');
		return $objResponse;
	}
	else {
		$objResponse->addScript("$('msg-red').setStyle('display', 'none');");
	}

	$userbank->login($aid, $password, $remember);

	// БАГ-ФИКС: успешные входы тоже никогда не логировались. login() выше не обновляет
	// $userbank->aid в рамках текущего запроса (это происходит только при следующей загрузке
	// страницы через куки), поэтому $userbank->GetAid() внутри CSystemLog вернёт "-1" - явно
	// передаём $aid и пишем запись вручную (done=false), чтобы "кто вошёл" был указан верно.
	$log = new CSystemLog("m", "Успешный вход", "Администратор '" . htmlspecialchars($username) . "' вошёл в систему.", false);
	$log->aid = (int)$aid;
	$log->WriteLog();

	// Open-redirect: только безопасный query-string без протокола/хоста.
	$redirect = is_string($redirect) ? $redirect : '';
	if ($redirect === '' || stripos($redirect, 'validation') !== false || !preg_match('/^[a-zA-Z0-9_.=&%-]+$/', $redirect))
		$objResponse->addRedirect("?",  0);
	else
		$objResponse->addRedirect("?" . $redirect, 0);
	return $objResponse;
}

function LostPassword($email)
{
	$objResponse = new xajaxResponse();
	// Один и тот же ответ — без enumeration email.
	$generic_ok = "ShowBox('Проверьте почту', 'Если этот e-mail есть в системе, мы отправили письмо со ссылкой для сброса пароля.', 'blue', '', true);";
	$generic_err = "ShowBox('Ошибка', 'Введите корректный e-mail.', 'red', '', true);";
	$rate_err = "ShowBox('Слишком много попыток', 'Подождите несколько минут и попробуйте снова.', 'red', '', true);";

	// Rate-limit ДО любых обращений к БД (в т.ч. мусор от сканеров).
	if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('lostpass', 5, 900)) {
		$objResponse->addScript($rate_err);
		return $objResponse;
	}

	// Сканеры шлют email[] / nested array / serialize-мусор → раньше ADOdb клеил "Array" в SQL.
	if (!is_string($email)) {
		new CSystemLog("w", "LostPassword probe", "Non-string email argument from IP " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
		$objResponse->addScript($generic_err);
		return $objResponse;
	}
	$email = trim($email);
	if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$objResponse->addScript($generic_ok);
		return $objResponse;
	}

	// Отдельный лимит на адрес — защита от email-bombing жертвы с разных IP.
	if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('lostpass_mail_' . hash('sha256', strtolower($email)), 3, 3600)) {
		$objResponse->addScript($generic_ok);
		return $objResponse;
	}

	$q = $GLOBALS['db']->GetRow(
		"SELECT `aid`, `user`, `email` FROM `" . DB_PREFIX . "_admins` WHERE `email` = ?",
		array($email)
	);

	if (!$q || empty($q['aid'])) {
		$objResponse->addScript($generic_ok);
		return $objResponse;
	}

	$objResponse->addScript("$('msg-red').setStyle('display', 'none');");

	// В БД храним только хеш токена; в письме — сырой секрет (64 hex).
	$validation = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
	$GLOBALS['db']->Execute(
		"UPDATE `" . DB_PREFIX . "_admins` SET `validate` = ? WHERE `aid` = ?",
		array(hash('sha256', $validation), (int)$q['aid'])
	);
	$message = "";
	$message .= "Привет " . $q['user'] . "\n";
	$message .= "Вы запросили смену пароля в системе Sourcebans.\n";
	$message .= "Для завершения процедуры смены пароля перейдите по ссылке ниже и подтвердите сброс кнопкой на странице.\n";
	$message .= "ПРИМЕЧАНИЕ: если Вы не запрашивали смену пароля, просто проигнорируйте это сообщение.\n\n";

	// Ссылка для сброса пароля и заголовок From строятся на основе доверенного SB_WP_URL,
	// а не $_SERVER['HTTP_HOST']/REQUEST_URI: заголовок Host полностью контролируется
	// клиентом, и без этой правки был возможен классический "password reset poisoning" -
	// злоумышленник мог отправить запрос с поддельным Host и получить ссылку сброса пароля,
	// указывающую на его собственный (фишинговый) домен, но с валидным токеном жертвы.
	$message .= rtrim(SB_WP_URL, '/') . "/index.php?p=lostpassword&email=". rawurlencode(RemoveCode($email)) . "&validation=" . $validation;

	$headers = 'From: Sourcebans@' . sb_get_site_host() . "\n" .
    'X-Mailer: PHP/' . phpversion();
	$m = EMail($email, "Сброс пароля SourceBans", $message, $headers);

	// Не раскрываем, удалось ли найти/отправить — только generic (плюс лог при ошибке почты).
	if (!$m)
		new CSystemLog("w", "Сброс пароля", "Не удалось отправить письмо на e-mail для aid=".(int)$q['aid']);
	$objResponse->addScript($generic_ok);
	return $objResponse;
}

function CheckSrvPassword($aid, $srv_pass)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	$aid = (int)$aid;
    if(!$userbank->is_logged_in() || $aid != $userbank->GetAid())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытается проверить пароль сервера ".$userbank->GetProperty('user', $aid).", не имея на это прав.");
		return $objResponse;
	}
	$res = $GLOBALS['db']->Execute("SELECT `srv_password` FROM `".DB_PREFIX."_admins` WHERE `aid` = '".$aid."'");
	if($res->fields['srv_password'] != NULL && $res->fields['srv_password'] != $srv_pass)
	{
		$objResponse->addScript("$('scurrent.msg').setStyle('display', 'block');");
		$objResponse->addScript("$('scurrent.msg').setHTML('Неверный пароль.');");
		$objResponse->addScript("set_error(1);");

	}
	else
	{
		$objResponse->addScript("$('scurrent.msg').setStyle('display', 'none');");
		$objResponse->addScript("set_error(0);");
	}
	return $objResponse;
}

function ChangeSrvPassword($aid, $srv_pass)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
    $aid = (int)$aid;
    if(!$userbank->is_logged_in() || $aid != $userbank->GetAid())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытается изменить пароль сервера ".$userbank->GetProperty('user', $aid).", не имея на это прав.");
		return $objResponse;
	}
    
	if($srv_pass == "NULL")
		$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `srv_password` = NULL WHERE `aid` = '".$aid."'");
	else
		$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `srv_password` = ? WHERE `aid` = ?", array($srv_pass, $aid));
	$objResponse->addScript("ShowBox('Пароль сервера изменён', 'Пароль сервера был успешно изменён.', 'green', 'index.php?p=account', true);");
	$log = new CSystemLog("m", "Изменён пароль сервера", "Пароль сменил администратор (".$aid.")");
	return $objResponse;
}

function ChangeEmail($aid, $email, $password)
{
    global $userbank, $username;
	$objResponse = new xajaxResponse();
	$aid = (int)$aid;
    
    if(!$userbank->is_logged_in() || $aid != $userbank->GetAid())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался сменить e-mail ".$userbank->GetProperty('user', $aid).", не имея на это прав.");
		return $objResponse;
	}
    
    if(!$userbank->verify_password($password, $aid))
    {
        $objResponse->addScript("$('emailpw.msg').setStyle('display', 'block');");
		$objResponse->addScript("$('emailpw.msg').setHTML('Введённый пароль неверен.');");
		$objResponse->addScript("set_error(1);");
		return $objResponse;
	} else {
		$objResponse->addScript("$('emailpw.msg').setStyle('display', 'none');");
		$objResponse->addScript("set_error(0);");
	}
    
	if(!check_email($email)) {
		$objResponse->addScript("$('email1.msg').setStyle('display', 'block');");
		$objResponse->addScript("$('email1.msg').setHTML('Введите действительный адрес электронной почты.');");
		$objResponse->addScript("set_error(1);");
		return $objResponse;
	} else {
		$objResponse->addScript("$('email1.msg').setStyle('display', 'none');");
		$objResponse->addScript("set_error(0);");
	}

	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `email` = ? WHERE `aid` = ?", array($email, $aid));
	$objResponse->addScript("ShowBox('E-mail изменён', 'Ваш e-mail адрес успешно изменён.', 'green', 'index.php?p=account', true);");
	$log = new CSystemLog("m", "E-mail изменён", "E-mail изменил админ (".$aid.")");
	return $objResponse;
}

function AddGroup($name, $type, $bitmask, $srvflags)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_GROUP))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " попытался добавить группу, не имея на это прав.");
		return $objResponse;
	}

	$error = 0;
	$query = $GLOBALS['db']->GetRow("SELECT `gid` FROM `" . DB_PREFIX . "_groups` WHERE `name` = ?", array($name));
	$query2 = $GLOBALS['db']->GetRow("SELECT `id` FROM `" . DB_PREFIX . "_srvgroups` WHERE `name` = ?", array($name));
	if(strlen($name) == 0 || count($query) > 0 || count($query2) > 0)
	{
		if(strlen($name) == 0)
		{
			$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
			$objResponse->addScript("$('name.msg').setHTML('Введите имя для группы.');");
			$error++;
		}
		else if(strstr($name, ','))	{
			$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
			$objResponse->addScript("$('name.msg').setHTML('В имени группы не может быть запятой.');");
			$error++;
		}
		else if(count($query) > 0 || count($query2) > 0){
			$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
			$objResponse->addScript("$('name.msg').setHTML('Имя группы уже используется \'" . addslashes($name) . "\'');");
			$error++;
		}
		else {
			$objResponse->addScript("$('name.msg').setStyle('display', 'none');");
			$objResponse->addScript("$('name.msg').setHTML('');");
		}
	}
	if($type == "0")
	{
		$objResponse->addScript("$('type.msg').setStyle('display', 'block');");
		$objResponse->addScript("$('type.msg').setHTML('Выберите тип группы.');");
		$error++;
	}
	else {
		$objResponse->addScript("$('type.msg').setStyle('display', 'none');");
		$objResponse->addScript("$('type.msg').setHTML('');");
	}
	if($error > 0)
		return $objResponse;

	$query = $GLOBALS['db']->GetRow("SELECT MAX(gid) AS next_gid FROM `" . DB_PREFIX . "_groups`");
	if($type == "1")
	{
		// add the web group
		$query1 = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_groups` (`gid`, `type`, `name`, `flags`) VALUES (". (int)($query['next_gid']+1) .", '" . (int)$type . "', ?, '" . (int)$bitmask . "')", array($name));
	}
	elseif($type == "2")
	{
		if(strstr($srvflags, "#"))
		{
			$immunity = "0";
			$immunity = substr($srvflags, strpos($srvflags, "#")+1);
			$srvflags = substr($srvflags, 0, strlen($srvflags) - strlen($immunity)-1);
		}
		$immunity = (isset($immunity) && $immunity>0) ? $immunity : 0;
		$add_group = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_srvgroups(immunity,flags,name,groups_immune)
					VALUES (?,?,?,?)");
		$GLOBALS['db']->Execute($add_group,array($immunity, $srvflags, $name, " "));
	}
	elseif($type == "3")
	{
		// We need to add the server into the table
		$query1 = $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_groups` (`gid`, `type`, `name`, `flags`) VALUES (". ($query['next_gid']+1) .", '3', ?, '0')", array($name));
	}

	$log = new CSystemLog("m", "Группа создана", "Новая группа ($name) успешно создана");
    $objResponse->addScript("ShowBox('Группа создана', 'Группа была успешно создана.', 'green', 'index.php?p=admin&c=groups', true);");
    $objResponse->addScript("TabToReload();");
	return $objResponse;
}

function RemoveGroup($gid, $type)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_GROUPS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " попытался удалить группу, не имея на это прав.");
		return $objResponse;
	}

	$gid = (int)$gid;


	if($type == "web") {
		$query2 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET gid = -1 WHERE gid = ?", array($gid));
		$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_groups` WHERE gid = ?", array($gid));
	}
	else if($type == "server") {
		$query2 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_servers_groups` WHERE group_id = ?", array($gid));
		$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_groups` WHERE gid = ?", array($gid));
	}
	else {
		$query2 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET srv_group = NULL WHERE srv_group = (SELECT name FROM `" . DB_PREFIX . "_srvgroups` WHERE id = ?)", array($gid));
		$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_srvgroups` WHERE id = ?", array($gid));
		$query0 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE group_id = ?", array($gid));
	}
	
	if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
	{
		// rehash the settings out of the database on all servers
		$serveraccessq = $GLOBALS['db']->GetAll("SELECT sid FROM ".DB_PREFIX."_servers WHERE enabled = 1;");
		$allservers = array();
		foreach($serveraccessq as $access) {
			if(!in_array($access['sid'], $allservers)) {
				$allservers[] = $access['sid'];
			}
		}
		$rehashing = true;
	}

	$objResponse->addScript("SlideUp('gid_$gid');");
	if($query1)
	{
		if(isset($rehashing))
			$objResponse->addScript("ShowRehashBox('".implode(",", $allservers)."', 'Группа удалена', 'Выбранная группа была успешно удалена из базы данных', 'green', 'index.php?p=admin&c=groups', true);");
		else
			$objResponse->addScript("ShowBox('Группа удалена', 'Выбранная группа была успешно удалена из базы данных', 'green', 'index.php?p=admin&c=groups', true);");
		$log = new CSystemLog("m", "Группа удалена", "Группа (" . $gid . ") удалена");
	}
	else
		$objResponse->addScript("ShowBox('Ошибка', 'Не получилось удалить группу из базы данных. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=groups', true);");

	return $objResponse;
}

function RemoveSubmission($sid, $archiv)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить предложение бана, не имея на это прав.");
		return $objResponse;
	}
	$sid = (int)$sid;
	if($archiv == "1") { // move submission to archiv
		$query1 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '1', archivedby = ? WHERE subid = ?", array($userbank->GetAid(), $sid));
		$query = $GLOBALS['db']->GetRow("SELECT count(subid) AS cnt FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0'", array());
		$objResponse->addScript("$('subcount').setHTML('" . $query['cnt'] . "');");

		$objResponse->addScript("SlideUp('sid_$sid');");
		$objResponse->addScript("SlideUp('sid_" . $sid . "a');");

		if($query1)
		{
			$objResponse->addScript("ShowBox('Заявка отправлена в архив', 'Выбранная заявка была перемещена в архив!', 'green', 'index.php?p=admin&c=bans', true);");
			$log = new CSystemLog("m", "Заявка отправлена в архив", "Заявка (" . $sid . ") была перемещена в архив");
		}
		else
			$objResponse->addScript("ShowBox('Ошибка', 'Не получилось переместить заявку. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=bans', true);");
	} else if($archiv == "0") { // delete submission
		$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_submissions` WHERE subid = ?", array($sid));
		$query2 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_demos` WHERE demid = ? AND demtype = 'S'", array($sid));
		$query = $GLOBALS['db']->GetRow("SELECT count(subid) AS cnt FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '1'", array());
		$objResponse->addScript("$('subcountarchiv').setHTML('" . $query['cnt'] . "');");

		$objResponse->addScript("SlideUp('asid_$sid');");
		$objResponse->addScript("SlideUp('asid_" . $sid . "a');");

		if($query1)
		{
			$objResponse->addScript("ShowBox('Заявка удалена', 'Выбранная заявка была удалена из базы данных', 'green', 'index.php?p=admin&c=bans', true);");
			$log = new CSystemLog("m", "Заявка удалена", "Заявка (" . $sid . ") была удалена");
		}
		else
			$objResponse->addScript("ShowBox('Ошибка', 'Не получилось удалить заявку. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=bans', true);");
	} else if($archiv == "2") { // restore the submission
		$query1 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '0', archivedby = NULL WHERE subid = ?", array($sid));
		$query = $GLOBALS['db']->GetRow("SELECT count(subid) AS cnt FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0'", array());
		$objResponse->addScript("$('subcountarchiv').setHTML('" . $query['cnt'] . "');");

		$objResponse->addScript("SlideUp('asid_$sid');");
		$objResponse->addScript("SlideUp('asid_" . $sid . "a');");

		if($query1)
		{
			$objResponse->addScript("ShowBox('Заявка восстановлена', 'Выбранная заявка была восстановлена из архива!', 'green', 'index.php?p=admin&c=bans', true);");
			$log = new CSystemLog("m", "Заявка восстановлена", "Заявка (" . $sid . ") была восстановлена из архива");
		}
		else
			$objResponse->addScript("ShowBox('Ошибка', 'Не получилось восстановить заявку. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=bans', true);");
	}
	return $objResponse;
}

function RemoveProtest($pid, $archiv)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить протест, не имея на это прав.");
		return $objResponse;
	}
	$pid = (int)$pid;
	if($archiv == '0') { // delete protest
		$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_protests` WHERE pid = ?", array($pid));
		$query2 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_comments` WHERE type = 'P' AND bid = ?;", array($pid));
		$query = $GLOBALS['db']->GetRow("SELECT count(pid) AS cnt FROM `" . DB_PREFIX . "_protests` WHERE archiv = '1'", array());
		$objResponse->addScript("$('protcountarchiv').setHTML('" . $query['cnt'] . "');");
		$objResponse->addScript("SlideUp('apid_$pid');");
		$objResponse->addScript("SlideUp('apid_" . $pid . "a');");

		if($query1)
		{
			$objResponse->addScript("ShowBox('Протест удалён', 'Выбранный протест был удалён из базы данных', 'green', 'index.php?p=admin&c=bans', true);");
			$log = new CSystemLog("m", "Протест удалён", "Протест (" . $pid . ") был удалён");
		}
		else
			$objResponse->addScript("ShowBox('Ошибка', 'Не получилось удалить протест. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=bans', true);");
	} else if($archiv == '1') { // move protest to archiv
		$query1 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_protests` SET archiv = '1', archivedby = ? WHERE pid = ?", array($userbank->GetAid(), $pid));
		$query = $GLOBALS['db']->GetRow("SELECT count(pid) AS cnt FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0'", array());
		$objResponse->addScript("$('protcount').setHTML('" . $query['cnt'] . "');");
		$objResponse->addScript("SlideUp('pid_$pid');");
		$objResponse->addScript("SlideUp('pid_" . $pid . "a');");

		if($query1)
		{
			$objResponse->addScript("ShowBox('Протест отправлен в архив', 'Выбранный протест был отправлен в архив.', 'green', 'index.php?p=admin&c=bans', true);");
			$log = new CSystemLog("m", "Протест в архиве", "Протест (" . $pid . ") был отправлен в архив.");
		}
		else
			$objResponse->addScript("ShowBox('Ошибка', 'Не получилось отправить в архив протест. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=bans', true);");
	} else if($archiv == '2') { // restore protest
		$query1 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_protests` SET archiv = '0', archivedby = NULL WHERE pid = ?", array($pid));
		$query = $GLOBALS['db']->GetRow("SELECT count(pid) AS cnt FROM `" . DB_PREFIX . "_protests` WHERE archiv = '1'", array());
		$objResponse->addScript("$('protcountarchiv').setHTML('" . $query['cnt'] . "');");
		$objResponse->addScript("SlideUp('apid_$pid');");
		$objResponse->addScript("SlideUp('apid_" . $pid . "a');");

		if($query1)
		{
			$objResponse->addScript("ShowBox('Протест восстановлен', 'Выбранный протест был успешно восстановлен из архива.', 'green', 'index.php?p=admin&c=bans', true);");
			$log = new CSystemLog("m", "Протест восстановлен", "Протест (" . $pid . ") был восстановлен из архива.");
		}
		else
			$objResponse->addScript("ShowBox('Ошибка', 'Не получилось восстановить протест из архива. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=bans', true);");
	}
	return $objResponse;
}

function RemoveServer($sid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_SERVERS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить сервер, не имея на это прав.");
		return $objResponse;
	}
	$sid = (int)$sid;
	$objResponse->addScript("SlideUp('sid_$sid');");
	$servinfo = $GLOBALS['db']->GetRow("SELECT ip, port FROM `" . DB_PREFIX . "_servers` WHERE sid = ?", array($sid));
	$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_servers` WHERE sid = ?", array($sid));
	$query2 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_servers_groups` WHERE server_id = ?", array($sid));
	$query3 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins_servers_groups` SET server_id = -1 WHERE server_id = ?", array($sid));

	$query = $GLOBALS['db']->GetRow("SELECT count(sid) AS cnt FROM `" . DB_PREFIX . "_servers`", array());
	$objResponse->addScript("$('srvcount').setHTML('" . $query['cnt'] . "');");


	if($query1)
	{
		$objResponse->addScript("ShowBox('Сервер удалён', 'Сервер успешно удалён из базы данных.', 'green', 'index.php?p=admin&c=servers', true);");
		$log = new CSystemLog("m", "Сервер удалён", "Сервер (" . $servinfo['ip'] . ":" . $servinfo['port'] . ") был удалён из базы данных.");
	}
	else
		$objResponse->addScript("ShowBox('Ошибка', 'Не получилось удалить сервер. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=servers', true);");
	return $objResponse;
}

function RemoveMod($mid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_MODS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить мод, не имея на это прав.");
		return $objResponse;
	}
	$mid = (int)$mid;
	$objResponse->addScript("SlideUp('mid_$mid');");

	$modicon = $GLOBALS['db']->GetRow("SELECT icon, name FROM `" . DB_PREFIX . "_mods` WHERE mid = ?;", array($mid));
	// basename() confines deletion to SB_ICONS, guarding against a path-traversal payload stored in `icon`
	if(!empty($modicon['icon']))
		@unlink(SB_ICONS."/".basename($modicon['icon']));

	$query1 = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_mods` WHERE mid = ?", array($mid));

	if($query1)
	{
		$objResponse->addScript("ShowBox('МОД удалён', 'Выбранный МОД был удалён из базы данных', 'green', 'index.php?p=admin&c=mods', true);");
		$log = new CSystemLog("m", "МОД удалён", "МОД (" . $modicon['name'] . ") был удалён");
	}
	else
		$objResponse->addScript("ShowBox('Ошибка', 'Не получилось удалить МОД. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=mods', true);");
	return $objResponse;
}

function RemoveAdmin($aid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить админа, не имея на это прав.");
		return $objResponse;
	}
	$aid = (int)$aid;
	$gid = $GLOBALS['db']->GetRow("SELECT gid, authid, extraflags, user FROM `" . DB_PREFIX . "_admins` WHERE aid = ?", array($aid));
	if(!is_array($gid) || empty($gid))
	{
		$objResponse->addAlert("Ошибка: Админ не найден.");
		return $objResponse;
	}
	if((intval($gid['extraflags']) & ADMIN_OWNER) != 0)
	{
		$objResponse->addAlert("Ошибка: Вы не можете удалить владельца.");
		return $objResponse;
	}

	// Защита из конфига: SteamID в SB_PROTECTED_STEAMIDS нельзя удалить из админов
	$protected_steamids = array_filter(array_map('trim', explode(',', defined('SB_PROTECTED_STEAMIDS') ? SB_PROTECTED_STEAMIDS : '')));
	if(!empty($gid['authid']) && in_array($gid['authid'], $protected_steamids))
	{
		$objResponse->addAlert("Ошибка: Этот администратор защищён в конфиге (SB_PROTECTED_STEAMIDS). Удаление запрещено.");
		$log = new CSystemLog("w", "Попытка удаления защищённого админа", $username . " попытался удалить защищённый SteamID: " . $gid['authid']);
		// Наказание: виновник (не из защищённых) — срок админки = истёк, доступ заблокирован
		sb_tripwire_punish_actor($objResponse, 'попытался удалить защищённого админа');
		return $objResponse;
	}

	$delquery = $GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_admins` WHERE aid = ? LIMIT 1", DB_PREFIX), array($aid));
	if($delquery) {
		if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
		{
			$serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `".DB_PREFIX."_servers` s
												LEFT JOIN `".DB_PREFIX."_admins_servers_groups` asg ON asg.admin_id = ?
												LEFT JOIN `".DB_PREFIX."_servers_groups` sg ON sg.group_id = asg.srv_group_id
												WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
												OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
												AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1", array((int)$aid));
			$allservers = array();
			foreach($serveraccessq as $access) {
				if(!in_array($access['sid'], $allservers)) {
					$allservers[] = $access['sid'];
				}
			}
			$rehashing = true;
		}

		$GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_admins_servers_groups` WHERE admin_id = ?", DB_PREFIX), array($aid));
 	}

	$query = $GLOBALS['db']->GetRow("SELECT count(aid) AS cnt FROM `" . DB_PREFIX . "_admins`");
	$objResponse->addScript("SlideUp('aid_$aid');");
	$objResponse->addScript("$('admincount').setHTML('" . $query['cnt'] . "');");
	if($delquery)
	{
		if(isset($rehashing))
			$objResponse->addScript("ShowRehashBox('".implode(",", $allservers)."', 'Админ удалён', 'Выбранный админ был удалён из базы данных', 'green', 'index.php?p=admin&c=admins', true);");
		else
			$objResponse->addScript("ShowBox('Админ удалён', 'Выбранный админ был удалён из базы данных', 'green', 'index.php?p=admin&c=admins', true);");
		$log = new CSystemLog("m", "Админ удалён", "Админ (" . $gid['user'] . ") был удалён");
	}
	else
		$objResponse->addScript("ShowBox('Ошибка', 'Не получилось удалить админа. Смотрите системный лог для дополнительной информации', 'red', 'index.php?p=admin&c=admins', true);");
	return $objResponse;
}

function AddServer($ip, $port, $rcon, $rcon2, $mod, $enabled, $group, $group_name)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_SERVER))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался добавить сервер, не имея на это прав.");
		return $objResponse;
	}
	$ip = RemoveCode($ip);
	$group_name = RemoveCode($group_name);

	$error = 0;
	// ip
	if((empty($ip)))
	{
		$error++;
		$objResponse->addAssign("address.msg", "innerHTML", "Введите адрес сервера.");
		$objResponse->addScript("$('address.msg').setStyle('display', 'block');");
	}
	else
	{
		$objResponse->addAssign("address.msg", "innerHTML", "");
		if(!validate_ip($ip) && !is_string($ip))
		{
			$error++;
			$objResponse->addAssign("address.msg", "innerHTML", "Введите действительный IP сервера.");
			$objResponse->addScript("$('address.msg').setStyle('display', 'block');");
		}
		else
			$objResponse->addAssign("address.msg", "innerHTML", "");
	}
	// Port
	if((empty($port)))
	{
		$error++;
		$objResponse->addAssign("port.msg", "innerHTML", "Введите порт сервера.");
		$objResponse->addScript("$('port.msg').setStyle('display', 'block');");
	}
	else
	{
		$objResponse->addAssign("port.msg", "innerHTML", "");
		if(!is_numeric($port))
		{
			$error++;
			$objResponse->addAssign("port.msg", "innerHTML", "Введите действительный порт <b>цифрами</b>.");
			$objResponse->addScript("$('port.msg').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addScript("$('port.msg').setStyle('display', 'none');");
			$objResponse->addAssign("port.msg", "innerHTML", "");
		}
	}
	// rcon
	if(!empty($rcon) && $rcon != $rcon2)
	{
		$error++;
		$objResponse->addAssign("rcon2.msg", "innerHTML", "Пароли не совпадают.");
		$objResponse->addScript("$('rcon2.msg').setStyle('display', 'block');");
	}
	else
		$objResponse->addAssign("rcon2.msg", "innerHTML", "");

	// Please Select
	if($mod == -2)
	{
		$error++;
		$objResponse->addAssign("mod.msg", "innerHTML", "Выберите МОД сервера.");
		$objResponse->addScript("$('mod.msg').setStyle('display', 'block');");
	}
	else
		$objResponse->addAssign("mod.msg", "innerHTML", "");

	if($group == -2)
	{
		$error++;
		$objResponse->addAssign("group.msg", "innerHTML", "Вы должны выбрать опцию.");
		$objResponse->addScript("$('group.msg').setStyle('display', 'block');");
	}
	else
		$objResponse->addAssign("group.msg", "innerHTML", "");

	if($error)
		return $objResponse;
	
	// Check for dublicates afterwards
	$chk = $GLOBALS['db']->GetRow('SELECT sid FROM `'.DB_PREFIX.'_servers` WHERE ip = ? AND port = ?;', array($ip, (int)$port));
	if($chk)
	{
		$objResponse->addScript("ShowBox('Ошибка', 'Введённый сервер уже существует в базе.', 'red');");
		return $objResponse;
	}

	// ##############################################################
	// ##                     Start adding to DB                   ##
	// ##############################################################
	//they wanna make a new group
	$gid = -1;
	$sid = nextSid();
	
	$enable = ($enabled=="true"?1:0);

	// Add the server
	$addserver = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_servers (`sid`, `ip`, `port`, `rcon`, `modid`, `enabled`)
										  VALUES (?,?,?,?,?,?)");
	$GLOBALS['db']->Execute($addserver,array($sid, $ip, (int)$port, $rcon, $mod, $enable));

	// Add server to each group specified
	$groups = explode(",", $group);
	$addtogrp = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_servers_groups (`server_id`, `group_id`) VALUES (?,?)");
	foreach($groups AS $g)
	{
		if($g)
			$GLOBALS['db']->Execute($addtogrp,array($sid, $g));
	}


	$objResponse->addScript("ShowBox('Сервер добавлен', 'Ваш сервер был успешно создан.', 'green', 'index.php?p=admin&c=servers');");
    $objResponse->addScript("TabToReload();");
    $log = new CSystemLog("m", "Сервер добавлен", "Сервер (" . $ip . ":" . $port . ") добавлен");
	return $objResponse;
}


function UpdateGroupPermissions($gid)
{
	$objResponse = new xajaxResponse();
	global $userbank;
	$gid = (int)$gid;
	if($gid == 1)
	{
		$permissions = @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php");
		$permissions = str_replace("{title}", "Веб-права", $permissions);
	}
	elseif($gid == 2)
	{
		$permissions = @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php");
		$permissions = str_replace("{title}", "Серверные права", $permissions);
	}
	elseif($gid == 3)
		$permissions = "";

	$objResponse->addAssign("perms", "innerHTML", $permissions);
	if(!$userbank->HasAccess(ADMIN_OWNER))
		$objResponse->addScript('if($("wrootcheckbox")) { 
									$("wrootcheckbox").setStyle("display", "none");
								}
								if($("srootcheckbox")) { 
									$("srootcheckbox").setStyle("display", "none");
								}');
	$objResponse->addScript("$('type.msg').setHTML('');");
	$objResponse->addScript("$('type.msg').setStyle('display', 'none');");
	return $objResponse;
}

function UpdateAdminPermissions($type, $value)
{
	$objResponse = new xajaxResponse();
	global $userbank;
	$type = (int)$type;
	if($type == 1)
	{
		$id = "web";
		if($value == "c")
		{
			$permissions = @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php");
			$permissions = str_replace("{title}", "Веб-права", $permissions);
		}
		elseif($value == "n")
		{
			$permissions = @file_get_contents(TEMPLATES_PATH . "/group.name.php") . @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php");
			$permissions = str_replace("{name}", "webname", $permissions);
			$permissions = str_replace("{title}", "Новая веб-группа", $permissions);
		}
		else
			$permissions = "";
	}
	if($type == 2)
	{
		$id = "server";
		if($value == "c")
		{
			$permissions = file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php");
			$permissions = str_replace("{title}", "Серверные права", $permissions);
		}
		elseif($value == "n")
		{
			$permissions = @file_get_contents(TEMPLATES_PATH . "/group.name.php") . @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php");
			$permissions = str_replace("{name}", "servername", $permissions);
			$permissions = str_replace("{title}", "Новая серверная группа", $permissions);
		}
		else
			$permissions = "";
	}

	$objResponse->addAssign($id."perm", "innerHTML", $permissions);
	if(!$userbank->HasAccess(ADMIN_OWNER))
		$objResponse->addScript('if($("wrootcheckbox")) { 
									$("wrootcheckbox").setStyle("display", "none");
								}
								if($("srootcheckbox")) { 
									$("srootcheckbox").setStyle("display", "none");
								}');
	$objResponse->addAssign($id.".msg", "innerHTML", "");
	return $objResponse;

}

function AddServerGroupName()
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_GROUPS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался изменить имя группы, не имея на это прав.");
		return $objResponse;
	}
	$inject = '<td valign="top"><div class="rowdesc">' . HelpIcon("Имя группы серверов", "Введите имя новой группы.") . 'Имя группы </div></td>';
	$inject .= '<td><div align="left">
        <input type="text" style="border: 1px solid #000000; width: 105px; font-size: 14px; background-color: rgb(215, 215, 215);width: 200px;" id="sgroup" name="sgroup" />
      </div>
        <div id="group_name.msg" style="color:#CC0000;width:195px;display:none;"></div></td>
  ';
	$objResponse->addAssign("nsgroup", "innerHTML", $inject);
	$objResponse->addAssign("group.msg", "innerHTML", "");
	return $objResponse;

}

function AddAdmin_pay($mask, $srv_mask, $a_name, $a_steam, $a_email, $a_password, $a_password2,	$a_sg, $a_wg, $a_serverpass, $a_webname, $a_servername, $server, $singlesrv, $discord, $comment, $vk, $a_code)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;

	if (isset($userbank) && is_object($userbank) && method_exists($userbank, 'is_logged_in') && $userbank->is_logged_in()) {
		$objResponse->addScript("ShowBox('Активация недоступна', 'Ваучер может активировать только гость. Выйдите из аккаунта и откройте страницу активации снова.', 'red', 'index.php', true);");
		return $objResponse;
	}

	if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('vay4er', 10, 900)) {
		$objResponse->addScript("ShowBox('Слишком много попыток', 'Подождите несколько минут и попробуйте снова.', 'red', '', true);");
		return $objResponse;
	}
	
	$mask = "";
	$srv_mask = "";
	$a_sg = "";
	$a_wg = "";
	$a_serverpass = "-1";
	$a_webname = "0";
	$a_servername = "0";
	$server = "";
	$comment = "";
	
	$vk = RemoveCode($vk);
	$vk = str_replace(array("http://","https://","/","vk.com"), "", $vk);
	$discord = RemoveCode($discord);
	$a_code = function_exists('sb_voucher_normalize_key')
		? sb_voucher_normalize_key($a_code)
		: strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$a_code));

	if (!function_exists('sb_voucher_key_valid') || !sb_voucher_key_valid($a_code)) {
		$objResponse->addScript("ShowBox('Активация', 'Некорректный код ваучера.', 'red', 'index.php?p=pay', true);");
		return $objResponse;
	}

	// Нельзя скипнуть капчу/шаг 1 прямым вызовом xajax.
	if (!function_exists('sb_voucher_unlock_check') || !sb_voucher_unlock_check($a_code)) {
		$objResponse->addScript("ShowBox('Активация', 'Сначала пройдите проверку ваучера и капчу на странице активации.', 'red', 'index.php?p=pay', true);");
		return $objResponse;
	}
	
	$srv_sql_val = $GLOBALS['db']->GetOne("SELECT `servers` FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?", array($a_code));
	if($srv_sql_val == "-1"){
		$singlesrv = "";
	}elseif((stristr($srv_sql_val, ',') && stristr($srv_sql_val, 's')) == TRUE){
		$singlesrv = $srv_sql_val;
	}
	
	$qwe = $GLOBALS['db']->GetOne("SELECT `activ` FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?", array($a_code));
	if($qwe == "0" || $qwe != "1"){
		$objResponse->addScript("ShowBox('Активация', 'Ваш ваучер уже был успешно активирован! Повторная активация - невозможна. Переадресация...', 'red', 'index.php', false);");
		$log = new CSystemLog("w", "Ваучер", $a_name . " пытался активировать ваучер повторно.");
		return $objResponse;
	}
	
	$pay_days_sql = $GLOBALS['db']->GetOne("SELECT `days` FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?", array($a_code));
	if ((string)$pay_days_sql !== "0" && $pay_days_sql !== null && $pay_days_sql !== '') {
		$pay_days_sql = (time() + ((int)$pay_days_sql) * 86400);
	} else {
		$pay_days_sql = 0;
	}
	$a_name = RemoveCode($a_name);
	$a_steam = RemoveCode($a_steam);
	$a_email = RemoveCode($a_email);
	$a_servername = ($a_servername=="0" ? null : RemoveCode($a_servername));
	$a_webname = RemoveCode($a_webname);
	$mask = (int)$mask;

	$error=0;
	
    //No name
	if(empty($a_name))
	{
		$error++;
		$objResponse->addAssign("name.msg", "innerHTML", "Введите имя админа.");
		$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
	}
	else{
		if(strstr($a_name, "'"))
		{
			$error++;
			$objResponse->addAssign("name.msg", "innerHTML", "Имя админа не должно содержать символы \" ' \".");
			$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
		}
		else
		{
			if(is_taken("admins", "user", $a_name))
			{
					$error++;
					$objResponse->addAssign("name.msg", "innerHTML", "Администратор с таким именем уже существует");
					$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
			}
			else
			{
					$objResponse->addAssign("name.msg", "innerHTML", "");
					$objResponse->addScript("$('name.msg').setStyle('display', 'none');");
			}
		}
	}
	// If they didnt type a steamid
	if((empty($a_steam) || strlen($a_steam) < 10))
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", "Введите ваш Steam ID или Community ID. Его можно найти в консоле, написав <b>status</b>.");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else
	{
		// Validate the steamid or fetch it from the community id
		if((!is_numeric($a_steam) 
		&& !validate_steam($a_steam))
		|| (is_numeric($a_steam) 
		&& (strlen($a_steam) < 15
		|| !validate_steam($a_steam = FriendIDToSteamID($a_steam)))))
		{
			$error++;
			$objResponse->addAssign("steam.msg", "innerHTML", "Введите действительный Steam ID или Community ID.");
			$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
		}
		else
		{
			if(is_taken("admins", "authid", $a_steam))
			{
				$admins = $userbank->GetAllAdmins();
				foreach($admins as $admin)
				{
					if($admin['authid'] == $a_steam)
					{
						$name = $admin['user'];
						break;
					}
				}
				$error++;
				$objResponse->addAssign("steam.msg", "innerHTML", "Этот Steam ID уже используется одним из администраторов!");
				$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
			}
			else
			{
				$objResponse->addAssign("steam.msg", "innerHTML", "");
				$objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
			}
		}
	}
	
	// No email
	if(empty($a_email))
	{
		// An E-Mail address is only required for users with web permissions.
		$error++;
		$objResponse->addAssign("email.msg", "innerHTML", "Введите адрес e-mail.");
		$objResponse->addScript("$('email.msg').setStyle('display', 'block');");
	}
	else{
		// Is an other admin already registred with that email address?
		if(is_taken("admins", "email", $a_email))
		{
			$admins = $userbank->GetAllAdmins();
			foreach($admins as $admin)
			{
				if($admin['email'] == $a_email)
				{
					$name = $admin['user'];
					break;
				}
			}
			$error++;
			$objResponse->addAssign("email.msg", "innerHTML", "Этот e-mail уже используется одним из администраторов!");
			$objResponse->addScript("$('email.msg').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("email.msg", "innerHTML", "");
			$objResponse->addScript("$('email.msg').setStyle('display', 'none');");
		}
	}
	
	// no pass
	if(empty($a_password))
	{
		// A password is only required for users with web permissions.
		$error++;
		$objResponse->addAssign("password.msg", "innerHTML", "Введите пароль.");
		$objResponse->addScript("$('password.msg').setStyle('display', 'block');");
	}
	// Password too short?
	else if(strlen($a_password) < MIN_PASS_LENGTH)
	{
		$error++;
		$objResponse->addAssign("password.msg", "innerHTML", "Длина пароля должна быть не менее " . MIN_PASS_LENGTH . " символов.");
		$objResponse->addScript("$('password.msg').setStyle('display', 'block');");
	}
	else 
	{
		$objResponse->addAssign("password.msg", "innerHTML", "");
		$objResponse->addScript("$('password.msg').setStyle('display', 'none');");
		
		// No confirmation typed
		if(empty($a_password2))
		{
			$error++;
			$objResponse->addAssign("password2.msg", "innerHTML", "Подтвердите пароль");
			$objResponse->addScript("$('password2.msg').setStyle('display', 'block');");
		}
		// Passwords match?
		else if($a_password != $a_password2)
		{
			$error++;
			$objResponse->addAssign("password2.msg", "innerHTML", "Пароли не совпадают");
			$objResponse->addScript("$('password2.msg').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("password2.msg", "innerHTML", "");
			$objResponse->addScript("$('password2.msg').setStyle('display', 'none');");
		}
	}

	// Choose to use a server password
	if($a_serverpass != "-1")
	{
		// No password given?
		if(empty($a_serverpass))
		{
			$error++;
			$objResponse->addAssign("a_serverpass.msg", "innerHTML", "Введите пароль сервера, либо снимите галочку.");
			$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'block');");
		}
		// Password too short?
		else if(strlen($a_serverpass) < MIN_PASS_LENGTH)
		{
			$error++;
			$objResponse->addAssign("a_serverpass.msg", "innerHTML", "Длина пароля должна быть не менее " . MIN_PASS_LENGTH . " символов.");
			$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'block');");
		}
		else 
		{
			$objResponse->addAssign("a_serverpass.msg", "innerHTML", "");
			$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'none');");
		}
	}
	else
	{
		$objResponse->addAssign("a_serverpass.msg", "innerHTML", "");
		$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'none');");
		// Don't set "-1" as password ;)
		$a_serverpass = "";
	}
	
    // didn't choose a server group
    if($a_sg == "-2")
    {
        $error++;
        $objResponse->addAssign("server.msg", "innerHTML", "Выберите группу.");
        $objResponse->addScript("$('server.msg').setStyle('display', 'block');");
    }
    else
    {
        $objResponse->addAssign("server.msg", "innerHTML", "");
        $objResponse->addScript("$('server.msg').setStyle('display', 'none');");
    }
	
	// chose to create a new server group
	if($a_sg == 'n')
	{
		// didn't type a name
		if(empty($a_servername))
		{
			$error++;
			$objResponse->addAssign("servername_err", "innerHTML", "Введите имя новой группы.");
			$objResponse->addScript("$('servername_err').setStyle('display', 'block');");
		}
		// Group names can't contain ,
		else if(strstr($a_servername, ','))
		{
			$error++;
			$objResponse->addAssign("servername_err", "innerHTML", "Имя группы не может содержать запятую.");
			$objResponse->addScript("$('servername_err').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("servername_err", "innerHTML", "");
			$objResponse->addScript("$('servername_err').setStyle('display', 'none');");
		}
	}
	
	// didn't choose a web group
    if($a_wg == "-2")
	{
        $error++;
        $objResponse->addAssign("web.msg", "innerHTML", "Выберите группу.");
        $objResponse->addScript("$('web.msg').setStyle('display', 'block');");
    }
    else
    {
        $objResponse->addAssign("web.msg", "innerHTML", "");
        $objResponse->addScript("$('web.msg').setStyle('display', 'none');");
    }
    
	// Choose to create a new webgroup
	if($a_wg == 'n')
	{
		// But didn't type a name
		if(empty($a_webname))
		{
			$error++;
			$objResponse->addAssign("webname_err", "innerHTML", "Введите имя новой группы.");
			$objResponse->addScript("$('webname_err').setStyle('display', 'block');");
		}
		// Group names can't contain ,
		else if(strstr($a_webname, ','))
		{
			$error++;
			$objResponse->addAssign("webname_err", "innerHTML", "Имя группы не может содержать запятую.");
			$objResponse->addScript("$('webname_err').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("webname_err", "innerHTML", "");
			$objResponse->addScript("$('webname_err').setStyle('display', 'none');");
		}
	}
	
	
	// Ohnoes! something went wrong, stop and show errs
	if($error)
	{
		ShowBox_ajx("Ошибка", "Допущены ошибки. Пожалуйста, исправьте их.", "red", $objResponse, "", true);
		return $objResponse;
	}

// ##############################################################
// ##                     Start adding to DB                   ##
// ##############################################################
	
	$gid = 0;
	$groupID = 0;
	$inGroup = false;
	$wgid = NextAid();
	$immunity = 0;
	
	
	// Extract immunity from server mask string
	if(strstr($srv_mask, "#"))
	{
		$immunity = "0";
		$immunity = substr($srv_mask, strpos($srv_mask, "#")+1);
		$srv_mask = substr($srv_mask, 0, strlen($srv_mask) - strlen($immunity)-1);
	}
	
	// Avoid negative immunity
	$immunity = ($immunity>0) ? $immunity : 0;
	
	// Handle Webpermissions
	// Chose to create a new webgroup
	if($a_wg == 'n')
	{
		$add_webgroup = $GLOBALS['db']->Execute("INSERT INTO ".DB_PREFIX."_groups(type, name, flags)
										VALUES (?,?,?)", array(1, $a_webname, $mask));
		$web_group = (int)$GLOBALS['db']->Insert_ID();
		
		// We added those permissons to the group, so don't add them as custom permissions again
		$mask = 0;
	}
	// Chose an existing group
	else if($a_wg != 'c' && $a_wg > 0)
	{
		$web_group = (int)$a_wg;
	}
	// Custom permissions -> no group
	else
	{
		$web_group = -1;
	}
	
	// Handle Serverpermissions
	// Chose to create a new server admin group
	if($a_sg == 'n')
	{
		$add_servergroup = $GLOBALS['db']->Execute("INSERT INTO ".DB_PREFIX."_srvgroups(immunity, flags, name, groups_immune)
					VALUES (?,?,?,?)", array($immunity, $srv_mask, $a_servername, " "));
		
		$server_admin_group = $a_servername;
		$server_admin_group_int = (int)$GLOBALS['db']->Insert_ID();
		
		// We added those permissons to the group, so don't add them as custom permissions again
		$srv_mask = "";
	}
	// Chose an existing group
	else if($a_sg != 'c' && $a_sg > 0)
	{
		$server_admin_group = $GLOBALS['db']->GetOne("SELECT `name` FROM ".DB_PREFIX."_srvgroups WHERE id = '" . (int)$a_sg . "'");
		$server_admin_group_int = (int)$a_sg;
	}
	// Custom permissions -> no group
	else
	{
		$server_admin_group = "";
		$server_admin_group_int = -1;
	}

	// Сначала читаем группы, потом создаём админа, потом гасим ваучер (иначе при ошибке ключ сгорает зря).
	$web_gruop_id = $GLOBALS['db']->GetOne("SELECT `group_web` FROM ".DB_PREFIX."_vay4er WHERE `value` = ? AND `activ` = '1'", array($a_code));
	$web_gruop_sql = $GLOBALS['db']->GetOne("SELECT `gid` FROM ".DB_PREFIX."_groups WHERE `name` = ?", array($web_gruop_id));
	if ($web_gruop_id == "" || $web_gruop_sql == "") {
		$web_gruop_sql = "0";
	}
	$server_admin_group = $GLOBALS['db']->GetOne("SELECT `group_srv` FROM ".DB_PREFIX."_vay4er WHERE `value` = ? AND `activ` = '1'", array($a_code));
	if ($server_admin_group == "") {
		$server_admin_group = "";
	}

	$aid = $userbank->AddAdmin($a_name, $a_steam, $a_password, $a_email, $web_gruop_sql, $mask, $server_admin_group, $srv_mask, $immunity, $a_serverpass, $pay_days_sql, $discord, '', $vk);
	if ($aid > -1)
	{
		$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_vay4er` SET `activ` = '0' WHERE `value` = ? AND `activ` = '1'", array($a_code));
		if (function_exists('sb_voucher_unlock_clear'))
			sb_voucher_unlock_clear();
		if (function_exists('sb_voucher_rehash_set'))
			sb_voucher_rehash_set($a_code);
		sb_set_auth_cookie("aid", $aid, time()+LOGIN_COOKIE_LIFETIME);
		sb_set_auth_cookie("password", $GLOBALS['db']->GetOne("SELECT `password` FROM `".DB_PREFIX."_admins` WHERE `aid` = ?", array((int)$aid)), time()+LOGIN_COOKIE_LIFETIME);

		// Grant permissions to the selected server groups
		$srv_groups = explode(",", $server);
		$addtosrvgrp = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_admins_servers_groups(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)");
		foreach($srv_groups AS $srv_group)
		{
			if(!empty($srv_group))
				$GLOBALS['db']->Execute($addtosrvgrp,array($aid, $server_admin_group_int, substr($srv_group, 1), '-1'));
		}
		
		// Grant permissions to individual servers
		$srv_arr = explode(",", $singlesrv);
		$addtosrv = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_admins_servers_groups(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)");
		foreach($srv_arr AS $server)
		{
			if(!empty($server))
				$GLOBALS['db']->Execute($addtosrv,array($aid, $server_admin_group_int, '-1', substr($server, 1)));
		}
		$safe_name = json_encode((string)$a_name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		$safe_code = json_encode((string)$a_code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
		{
			// rehash the admins on the servers
			$serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `".DB_PREFIX."_servers` s
												LEFT JOIN `".DB_PREFIX."_admins_servers_groups` asg ON asg.admin_id = '".(int)$aid."'
												LEFT JOIN `".DB_PREFIX."_servers_groups` sg ON sg.group_id = asg.srv_group_id
												WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
												OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
												AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
			$allservers = array();
			foreach($serveraccessq as $access) {
				if(!in_array($access['sid'], $allservers)) {
					$allservers[] = (int)$access['sid'];
				}
			}
			$objResponse->addScript("ShowRehashBox_pay('".implode(",", $allservers)."','Активация', 'Ваш ваучер был успешно активирован! Администратор (' + ".$safe_name." + ') был успешно добавлен!', 'green', 'index.php?p=account', ".$safe_code.");TabToReload();");
		} else
			$objResponse->addScript("ShowBox('Активация', 'Ваш ваучер был успешно активирован! Администратор (' + ".$safe_name." + ') был успешно добавлен!', 'green', 'index.php');TabToReload();");
		
		$log = new CSystemLog("m", "Ваучер", "Ваучер ".$a_code." был успешно активирован! Администратор (" . $a_name . ") был успешно добавлен!");
		return $objResponse;
	}
	else
	{
		$objResponse->addScript("ShowBox('Ваучер', 'Ошибка при активации ваучера. Свяжитесь с главной администрацией, для проверки лога на наличие SQL ошибок.', 'red', 'index.php');");
	}
}


function AddAdmin($mask, $srv_mask, $a_name, $a_steam, $a_email, $a_password, $a_password2,	$a_sg, $a_wg, $a_serverpass, $a_webname, $a_servername, $server, $singlesrv, $a_period, $discord, $comment, $vk)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_ADMINS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался добавить админа, не имея на то прав.");
		return $objResponse;
	}
	$vk = str_replace(array("http://","https://","/","vk.com"), "", $vk);
	$a_name = RemoveCode($a_name);
	$a_steam = RemoveCode($a_steam);
	$a_email = RemoveCode($a_email);
	$a_servername = ($a_servername=="0" ? null : RemoveCode($a_servername));
	$a_webname = RemoveCode($a_webname);
	$mask = (int)$mask;

	$error=0;
	
    //No name
	if(empty($a_name))
	{
		$error++;
		$objResponse->addAssign("name.msg", "innerHTML", "Введите имя админа.");
		$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
	}
	else{
		if(strstr($a_name, '/'))
		{
			$error++;
			$objResponse->addAssign("name.msg", "innerHTML", "Имя админа не должно содержать символы \" / \".");
			$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
		}
		elseif(strstr($a_name, "'"))
		{
			$error++;
			$objResponse->addAssign("name.msg", "innerHTML", "Имя админа не должно содержать символы \" ' \".");
			$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
		}
		else
		{
			if(is_taken("admins", "user", $a_name))
			{
					$error++;
					$objResponse->addAssign("name.msg", "innerHTML", "Администратор с таким именем уже существует");
					$objResponse->addScript("$('name.msg').setStyle('display', 'block');");
			}
			else
			{
					$objResponse->addAssign("name.msg", "innerHTML", "");
					$objResponse->addScript("$('name.msg').setStyle('display', 'none');");
			}
		}
	}
	// If they didnt type a steamid
	if((empty($a_steam) || strlen($a_steam) < 10))
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", "Введите Steam ID или Community ID админа.");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else
	{
		// Validate the steamid or fetch it from the community id
		if((!is_numeric($a_steam) 
		&& !validate_steam($a_steam))
		|| (is_numeric($a_steam) 
		&& (strlen($a_steam) < 15
		|| !validate_steam($a_steam = FriendIDToSteamID($a_steam)))))
		{
			$error++;
			$objResponse->addAssign("steam.msg", "innerHTML", "Введите действительный Steam ID или Community ID.");
			$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
		}
		else
		{
			if(is_taken("admins", "authid", $a_steam))
			{
				$admins = $userbank->GetAllAdmins();
				foreach($admins as $admin)
				{
					if($admin['authid'] == $a_steam)
					{
						$name = $admin['user'];
						break;
					}
				}
				$error++;
				$objResponse->addAssign("steam.msg", "innerHTML", "Этот Steam ID уже используется админом ".htmlspecialchars(addslashes($name)).".");
				$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
			}
			else
			{
				$objResponse->addAssign("steam.msg", "innerHTML", "");
				$objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
			}
		}
	}
	
	// No email
	if(empty($a_email))
	{
		// An E-Mail address is only required for users with web permissions.
		if($mask != 0)
		{
			$error++;
			$objResponse->addAssign("email.msg", "innerHTML", "Введите адрес e-mail.");
			$objResponse->addScript("$('email.msg').setStyle('display', 'block');");
		}
	}
	else{
		// Is an other admin already registred with that email address?
		if(is_taken("admins", "email", $a_email))
		{
			$admins = $userbank->GetAllAdmins();
			foreach($admins as $admin)
			{
				if($admin['email'] == $a_email)
				{
					$name = $admin['user'];
					break;
				}
			}
			$error++;
			$objResponse->addAssign("email.msg", "innerHTML", "Этот e-mail уже используется админом ".htmlspecialchars(addslashes($name)).".");
			$objResponse->addScript("$('email.msg').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("email.msg", "innerHTML", "");
			$objResponse->addScript("$('email.msg').setStyle('display', 'none');");
		/*	if(!validate_email($a_email))
			{
				$error++;
				$objResponse->addAssign("email.msg", "innerHTML", "Please enter a valid email address.");
				$objResponse->addScript("$('email.msg').setStyle('display', 'block');");
			}
			else
			{
				$objResponse->addAssign("email.msg", "innerHTML", "");
				$objResponse->addScript("$('email.msg').setStyle('display', 'none');");

			}*/
		}
	}
	
	// no pass
	if(empty($a_password))
	{
		// A password is only required for users with web permissions.
		if($mask != 0)
		{
			$error++;
			$objResponse->addAssign("password.msg", "innerHTML", "Введите пароль.");
			$objResponse->addScript("$('password.msg').setStyle('display', 'block');");
		}
	}
	// Password too short?
	else if(strlen($a_password) < MIN_PASS_LENGTH)
	{
		$error++;
		$objResponse->addAssign("password.msg", "innerHTML", "Длина пароля должна быть не менее " . MIN_PASS_LENGTH . " символов.");
		$objResponse->addScript("$('password.msg').setStyle('display', 'block');");
	}
	else 
	{
		$objResponse->addAssign("password.msg", "innerHTML", "");
		$objResponse->addScript("$('password.msg').setStyle('display', 'none');");
		
		// No confirmation typed
		if(empty($a_password2))
		{
			$error++;
			$objResponse->addAssign("password2.msg", "innerHTML", "Подтвердите пароль");
			$objResponse->addScript("$('password2.msg').setStyle('display', 'block');");
		}
		// Passwords match?
		else if($a_password != $a_password2)
		{
			$error++;
			$objResponse->addAssign("password2.msg", "innerHTML", "Пароли не совпадают");
			$objResponse->addScript("$('password2.msg').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("password2.msg", "innerHTML", "");
			$objResponse->addScript("$('password2.msg').setStyle('display', 'none');");
		}
	}

	// Choose to use a server password
	if($a_serverpass != "-1")
	{
		// No password given?
		if(empty($a_serverpass))
		{
			$error++;
			$objResponse->addAssign("a_serverpass.msg", "innerHTML", "Введите пароль сервера, либо снимите галочку.");
			$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'block');");
		}
		// Password too short?
		else if(strlen($a_serverpass) < MIN_PASS_LENGTH)
		{
			$error++;
			$objResponse->addAssign("a_serverpass.msg", "innerHTML", "Длина пароля должна быть не менее " . MIN_PASS_LENGTH . " символов.");
			$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'block');");
		}
		else 
		{
			$objResponse->addAssign("a_serverpass.msg", "innerHTML", "");
			$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'none');");
		}
	}
	else
	{
		$objResponse->addAssign("a_serverpass.msg", "innerHTML", "");
		$objResponse->addScript("$('a_serverpass.msg').setStyle('display', 'none');");
		// Don't set "-1" as password ;)
		$a_serverpass = "";
	}
	
    // didn't choose a server group
    if($a_sg == "-2")
    {
        $error++;
        $objResponse->addAssign("server.msg", "innerHTML", "Выберите группу.");
        $objResponse->addScript("$('server.msg').setStyle('display', 'block');");
    }
    else
    {
        $objResponse->addAssign("server.msg", "innerHTML", "");
        $objResponse->addScript("$('server.msg').setStyle('display', 'none');");
    }
	
	// chose to create a new server group
	if($a_sg == 'n')
	{
		// didn't type a name
		if(empty($a_servername))
		{
			$error++;
			$objResponse->addAssign("servername_err", "innerHTML", "Введите имя новой группы.");
			$objResponse->addScript("$('servername_err').setStyle('display', 'block');");
		}
		// Group names can't contain ,
		else if(strstr($a_servername, ','))
		{
			$error++;
			$objResponse->addAssign("servername_err", "innerHTML", "Имя группы не может содержать запятую.");
			$objResponse->addScript("$('servername_err').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("servername_err", "innerHTML", "");
			$objResponse->addScript("$('servername_err').setStyle('display', 'none');");
		}
	}
	
	// didn't choose a web group
    if($a_wg == "-2")
	{
        $error++;
        $objResponse->addAssign("web.msg", "innerHTML", "Выберите группу.");
        $objResponse->addScript("$('web.msg').setStyle('display', 'block');");
    }
    else
    {
        $objResponse->addAssign("web.msg", "innerHTML", "");
        $objResponse->addScript("$('web.msg').setStyle('display', 'none');");
    }
    
	// Choose to create a new webgroup
	if($a_wg == 'n')
	{
		// But didn't type a name
		if(empty($a_webname))
		{
			$error++;
			$objResponse->addAssign("webname_err", "innerHTML", "Введите имя новой группы.");
			$objResponse->addScript("$('webname_err').setStyle('display', 'block');");
		}
		// Group names can't contain ,
		else if(strstr($a_webname, ','))
		{
			$error++;
			$objResponse->addAssign("webname_err", "innerHTML", "Имя группы не может содержать запятую.");
			$objResponse->addScript("$('webname_err').setStyle('display', 'block');");
		}
		else
		{
			$objResponse->addAssign("webname_err", "innerHTML", "");
			$objResponse->addScript("$('webname_err').setStyle('display', 'none');");
		}
	}
	
	// Проверка срока админки
	if(!preg_match("#^([0-9]+)$#i",$a_period))
	{
		$error++;
		$objResponse->addAssign("a_period.msg", "innerHTML", "Только цифры.");
		$objResponse->addScript("$('a_period.msg').setStyle('display', 'block');");
	}
	else 
	{
		$objResponse->addAssign("a_period.msg", "innerHTML", "");
		$objResponse->addScript("$('a_period.msg').setStyle('display', 'none');");
	}
	
	// Ohnoes! something went wrong, stop and show errs
	if($error)
	{
		ShowBox_ajx("Ошибка", "Допущены ошибки. Пожалуйста, исправьте их.", "red", $objResponse, "", true);
		return $objResponse;
	}

// ##############################################################
// ##                     Start adding to DB                   ##
// ##############################################################
	
	$gid = 0;
	$groupID = 0;
	$inGroup = false;
	$wgid = NextAid();
	$immunity = 0;
	$a_period = intval($a_period);
	
	// Extract immunity from server mask string
	if(strstr($srv_mask, "#"))
	{
		$immunity = "0";
		$immunity = substr($srv_mask, strpos($srv_mask, "#")+1);
		$srv_mask = substr($srv_mask, 0, strlen($srv_mask) - strlen($immunity)-1);
	}
	
	// Avoid negative immunity
	$immunity = ($immunity>0) ? $immunity : 0;
	
	// Handle Webpermissions
	// Chose to create a new webgroup
	if($a_wg == 'n')
	{
		$add_webgroup = $GLOBALS['db']->Execute("INSERT INTO ".DB_PREFIX."_groups(type, name, flags)
										VALUES (?,?,?)", array(1, $a_webname, $mask));
		$web_group = (int)$GLOBALS['db']->Insert_ID();
		
		// We added those permissons to the group, so don't add them as custom permissions again
		$mask = 0;
	}
	// Chose an existing group
	else if($a_wg != 'c' && $a_wg > 0)
	{
		$web_group = (int)$a_wg;
	}
	// Custom permissions -> no group
	else
	{
		$web_group = -1;
	}
	
	// Handle Serverpermissions
	// Chose to create a new server admin group
	if($a_sg == 'n')
	{
		$add_servergroup = $GLOBALS['db']->Execute("INSERT INTO ".DB_PREFIX."_srvgroups(immunity, flags, name, groups_immune)
					VALUES (?,?,?,?)", array($immunity, $srv_mask, $a_servername, " "));
		
		$server_admin_group = $a_servername;
		$server_admin_group_int = (int)$GLOBALS['db']->Insert_ID();
		
		// We added those permissons to the group, so don't add them as custom permissions again
		$srv_mask = "";
	}
	// Chose an existing group
	else if($a_sg != 'c' && $a_sg > 0)
	{
		$server_admin_group = $GLOBALS['db']->GetOne("SELECT `name` FROM ".DB_PREFIX."_srvgroups WHERE id = '" . (int)$a_sg . "'");
		$server_admin_group_int = (int)$a_sg;
	}
	// Custom permissions -> no group
	else
	{
		$server_admin_group = "";
		$server_admin_group_int = -1;
	}
	
	// Срок админки
	if($a_period == 0) {
		$period = 0;
	}
	else {
		$period = $a_period * 86400 + time();
	}

	
	// Add the admin
	$aid = $userbank->AddAdmin($a_name, $a_steam, $a_password, $a_email, $web_group, $mask, $server_admin_group, $srv_mask, $immunity, $a_serverpass, $period, $discord, $comment, $vk);
	
	if($aid > -1)
	{
		// Grant permissions to the selected server groups
		$srv_groups = explode(",", $server);
		$addtosrvgrp = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_admins_servers_groups(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)");
		foreach($srv_groups AS $srv_group)
		{
			if(!empty($srv_group))
				$GLOBALS['db']->Execute($addtosrvgrp,array($aid, $server_admin_group_int, substr($srv_group, 1), '-1'));
		}
		
		// Grant permissions to individual servers
		$srv_arr = explode(",", $singlesrv);
		$addtosrv = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_admins_servers_groups(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)");
		foreach($srv_arr AS $server)
		{
			if(!empty($server))
				$GLOBALS['db']->Execute($addtosrv,array($aid, $server_admin_group_int, '-1', substr($server, 1)));
		}
		if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
		{
			// rehash the admins on the servers
			$serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `".DB_PREFIX."_servers` s
												LEFT JOIN `".DB_PREFIX."_admins_servers_groups` asg ON asg.admin_id = '".(int)$aid."'
												LEFT JOIN `".DB_PREFIX."_servers_groups` sg ON sg.group_id = asg.srv_group_id
												WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
												OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
												AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
			$allservers = array();
			foreach($serveraccessq as $access) {
				if(!in_array($access['sid'], $allservers)) {
					$allservers[] = $access['sid'];
				}
			}
			$objResponse->addScript("ShowRehashBox('".implode(",", $allservers)."','Админ добавлен', 'Админ успешно добавлен', 'green', 'index.php?p=admin&c=admins');TabToReload();");
		} else
			$objResponse->addScript("ShowBox('Админ добавлен', 'Админ успешно добавлен', 'green', 'index.php?p=admin&c=admins');TabToReload();");
		
		$log = new CSystemLog("m", "Админ добавлен", "Админ (" . $a_name . ") добавлен");
		return $objResponse;
	}
	else
	{
		$objResponse->addScript("ShowBox('Пользователь не добавлен', 'Ошибка при добавлении админа в базу данных. Проверьте лог на наличие SQL ошибок.', 'red', 'index.php?p=admin&c=admins');");
	}
}

function ServerHostPlayers($sid, $type="servers", $obId="", $tplsid="", $open="", $inHome=false, $trunchostname=48)
{
	$objResponse = new xajaxResponse();
	global $userbank;
	require INCLUDES_PATH.'/CServerControl.php';
	
	$sid = (int)$sid;

	//$res = $GLOBALS['db']->GetRow("SELECT sid, ip, port FROM ".DB_PREFIX."_servers WHERE sid = $sid");
	$res = $GLOBALS['db']->GetRow("SELECT se.sid, se.ip, se.port, se.modid, md.modfolder FROM ".DB_PREFIX."_servers se LEFT JOIN ".DB_PREFIX."_mods md ON md.mid=se.modid WHERE se.sid = $sid");
	if(empty($res[1]) || empty($res[2]))
		return $objResponse;
	$info = array();
	$sinfo = new CServerControl();
	$sinfo->Connect($res[1], $res[2]);
	$info = $sinfo->GetInfo();
	// SECURITY FIX: $info['HostName'] приходит как есть из ответа A2S_INFO игрового сервера
	// (полностью подконтролен тому, кто администрирует/настраивает этот сервер) и раньше
	// вставлялся как сырой innerHTML здесь и во всех остальных вызовах
	// ServerHostPlayers*/ServerHostProperty без htmlspecialchars() - это давало сохранённый
	// XSS, если когда-либо будет добавлен вредоносный/скомпрометированный игровой сервер.
	// Экранируем значение перед выводом.
	if($type == "servers") {
		if($info) {
			$objResponse->addAssign("host_$sid", "innerHTML", htmlspecialchars(trunc($info['HostName'], $trunchostname, false)));
			$objResponse->addAssign("players_$sid", "innerHTML", $info['Players'] . "/" . $info['MaxPlayers']);
			$os_key = !empty($info['Os']) ? $info['Os'] : 'server_small';
			$objResponse->addAssign("os_$sid", "innerHTML", sb_os_icon_html($os_key, 24));
			$objResponse->addAssign("vac_$sid", "innerHTML", sb_vac_icon_html(!empty($info['Secure']), 24));
			$objResponse->addAssign("map_$sid", "innerHTML", basename($info['Map'])); // Strip Steam Workshop folder
			if(!$inHome) {
				$mapBase = basename($info['Map']);
				$mapSrc = GetMapImage($mapBase, $res[4]);
				$objResponse->addScript(
					"(function(){var el=document.getElementById('mapimg_".$sid."');if(!el)return;"
					."el.removeAttribute('data-nomap');"
					."el.onerror=function(){if(this.getAttribute('data-nomap'))return;this.setAttribute('data-nomap','1');this.src='images/maps/nomap.jpg';};"
					."el.src=".json_encode($mapSrc).";"
					."el.alt=".json_encode($mapBase).";"
					."el.title=".json_encode($mapBase).";})();"
				);
				if($info['Players'] == 0) {
					$objResponse->addScript("$('sinfo_$sid').setStyle('display', 'none');");
					$objResponse->addScript("$('noplayer_$sid').setStyle('display', 'block');");
					$objResponse->addScript("if($('serverwindow_$sid'))$('serverwindow_$sid').setStyle('height', 'auto');");
				} else {
					$objResponse->addScript("$('sinfo_$sid').setStyle('display', 'block');");
					$objResponse->addScript("$('noplayer_$sid').setStyle('display', 'none');");
					if(!defined('IN_HOME')) {
						$players = $sinfo->GetPlayers();
						if ($players !== false) {
							// remove childnodes
							$objResponse->addScript('var toempty = document.getElementById("playerlist_'.$sid.'");
							var empty = toempty.cloneNode(false);
							toempty.parentNode.replaceChild(empty,toempty);');
							//draw table headlines
							$objResponse->addScript('var e = document.getElementById("playerlist_'.$sid.'");
							var tr = e.insertRow("-1");
								// Name Top TD
								var td = tr.insertCell("-1");
									td.setAttribute("width","50%");
									td.className = "servers-player-th";
										var b = document.createElement("b");
										var txt = document.createTextNode("Игрок");
										b.appendChild(txt);
									td.appendChild(b);
								// Score Top TD
								var td = tr.insertCell("-1");
									td.setAttribute("width","15%");
									td.className = "servers-player-th";
										var b = document.createElement("b");
										var txt = document.createTextNode("Счёт");
										b.appendChild(txt);
									td.appendChild(b);
								// Time Top TD
								var td = tr.insertCell("-1");
									td.className = "servers-player-th";
										var b = document.createElement("b");
										var txt = document.createTextNode("Время");
										b.appendChild(txt);
									td.appendChild(b);');
							// add players
							$playercount = 0;
							
							$needAddPlayerManaging = (($userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN) && $GLOBALS['db']->GetOne(sprintf("SELECT COUNT(*) FROM `%s_admins_servers_groups` WHERE `admin_id` = %d AND `server_id` = %d", DB_PREFIX, $userbank->GetAid(), (int)$sid)) == 1) || $userbank->HasAccess(ADMIN_OWNER));
							
							if($needAddPlayerManaging) {
								$dl = "a";
								$dl2 = 'var i_i = document.createElement("i");
										i_i.className = "zmdi zmdi-label c-lightblue p-r-10 p-l-5";
										i_i.style = "font-size: 17px;";
										//img.style.width = "20px";
										//img.style.height = "20px";
										a.appendChild(i_i);
										td.appendChild(a);
										';
								$dl_fix = 'p-l-5 ';
							}else{
								$dl = "span";
								$dl2 = "";
								$dl_fix = 'p-l-10 ';
							}
							$id = 0;
							foreach($players as $player) {
								if (empty($player['Name'])) continue;
								$id++;
								// Player names come from the game server and are attacker-controllable,
								// and can legitimately contain quotes/apostrophes. The name is embedded
								// below in several different contexts, each of which needs its OWN kind
								// of escaping:
								//  1) plain HTML text (modal title)                 -> htmlspecialchars()
								//  2) a JS string literal inside a *plain* JS statement (document.
								//     createTextNode) that is executed directly, not through the HTML
								//     parser                                        -> plain JS escaping
								//  3) a JS string argument inside onclick="...('NAME')" that is assigned
								//     via innerHTML. This is the tricky one: the browser's HTML parser
								//     decodes entities in attribute values (e.g. &#039; -> ') BEFORE the
								//     onclick JS source is compiled, so HTML-escaping a quote does NOT
								//     protect the JS string - it comes back as a literal quote and breaks
								//     out, corrupting the name. It must instead be JS-escaped (\' and \\,
								//     which pass through HTML-decoding unchanged since backslash isn't an
								//     HTML entity), then HTML-escaped for the remaining unsafe characters.
								//  4) a query-string value (?pName=...)             -> urlencode()
								// A previous "fix" stripped quote characters from the name outright to
								// dodge all of this, but that silently corrupted the name used to match
								// against the RCON "status" output: any player whose real nickname
								// contains a quote/apostrophe could never be found by Kick/Ban/Mute from
								// the player list ("Игрок покинул сервер!" even though he's clearly on).
								$safePlayerName = htmlspecialchars($player['Name'], ENT_QUOTES);
								$jsPlayerName = str_replace(array('\\', '"'), array('\\\\', '\\"'), $player['Name']);
								$onclickPlayerName = htmlspecialchars(str_replace(array('\\', "'"), array('\\\\', "\\'"), $player['Name']), ENT_COMPAT);
								$urlPlayerName = urlencode($player['Name']);
								$objResponse->addScript('var e = document.getElementById("playerlist_'.$sid.'");
														var tr = e.insertRow("-1");
														tr.id = "player_s'.$sid.'p'.$id.'";
															// Name TD
															var td = tr.insertCell("-1");
																td.className = "servers-player-name '.$dl_fix.'";
																	var txt = document.createTextNode("'.$jsPlayerName.'");
																	var a = document.createElement("'.$dl.'");
																	a.href = "#player_s' . $sid . 'p' . $id . '_t";
																	var att = document.createAttribute("data-toggle");
																	att.value = "modal"; 
																	a.setAttributeNode(att);
																	'.$dl2.'
																td.appendChild(txt);
															// Score TD
															var td = tr.insertCell("-1");
																td.className = "servers-player-score";
																var txt = document.createTextNode("'.$player["Frags"].'");
																td.appendChild(txt);
															// Time TD
															var td = tr.insertCell("-1");
																td.className = "servers-player-time";
																var txt = document.createTextNode("'.SecondsToString($player['Time']).'");
																td.appendChild(txt);
															');
								if($needAddPlayerManaging) {
									$objResponse->addScript('
										var div = document.createElement("div");
										div.className = "modal fade";
										div.id = "player_s' . $sid . 'p' . $id . '_t";
										var att = document.createAttribute("tabindex");
										var att1 = document.createAttribute("role");
										var att2 = document.createAttribute("aria-hidden");
										att.value = "-1"; 
										att1.value = "dialog"; 
										att2.value = "true"; 
										div.setAttributeNode(att);   
										div.setAttributeNode(att1);   
										div.setAttributeNode(att2);   
										div.innerHTML = "\
											<div class=\'modal-dialog modal-sm\'>\
												<div class=\'modal-content\'>\
													<div class=\'modal-header\'>\
														<h4 class=\'modal-title\'>'.$safePlayerName.'</h4>\
													</div>\
													<div class=\'modal-body\'>\
													<p class=\"m-b-10\"><button class=\"btn btn-link btn-block\" data-dismiss=\"modal\" onclick=\"KickPlayerConfirm('.$sid.', \''.$onclickPlayerName.'\', 0);\">Кикнуть</button></p>\
													<p class=\"m-b-10\"><button class=\"btn btn-link btn-block\" href=\"#\" data-dismiss=\'modal\' onclick=\"ViewCommunityProfile('.$sid.', \''.$onclickPlayerName.'\');\">Профиль</button></p>\
													<p class=\"m-b-10\"><a href=\"index.php?p=admin&c=bans&action=pasteBan&sid='.$sid.'&pName='.$urlPlayerName.'\"><button class=\"btn btn-link btn-block\">Бан</button></a></p>\
													<p class=\"m-b-10\"><a href=\"index.php?p=admin&c=comms&action=pasteBan&sid='.$sid.'&pName='.$urlPlayerName.'\"><button class=\"btn btn-link btn-block\">Заглушить</button></a></p>\
													<p class=\"m-b-10\"><button class=\"btn btn-link btn-block\" href=\"#\" data-dismiss=\'modal\' onclick=\"OpenMessageBox('.$sid.', \''.$onclickPlayerName.'\', 1);\">Отправить сообщение</button></p>\
													</div>\
													<!--<div class=\'modal-footer\'>\
														<button type=\'button\' class=\'btn btn-link\' data-dismiss=\'modal\'>Отмена</button>\
													</div>-->\
												</div>\
											</div>\
										";

										document.body.appendChild(div);');
								}
								$playercount++;
							}
						}
					}
					if($playercount>15)
						$height = 329 + 16 * ($playercount-15) + 4 * ($playercount-15) . "px";
					else
						$height = 329 . "px";
					//$objResponse->addScript("$('serverwindow_$sid').setStyle('height', '".$height."');");
				}
			}
		}else{
			if($userbank->HasAccess(ADMIN_OWNER))
				$objResponse->addAssign("host_$sid", "innerHTML", "<b>Ошибка соединения</b> (<i>" . $res[1] . ":" . $res[2]. "</i>) <small><a href=\"http://hlmod.ru/posts/290247/\" title=\"Какие порты должны быть открыты в ВЕБ панели SourceBans?\">Помощь</a></small>");
			else
				$objResponse->addAssign("host_$sid", "innerHTML", "<b>Ошибка соединения</b> (<i>" . $res[1] . ":" . $res[2]. "</i>)");
			$objResponse->addAssign("players_$sid", "innerHTML", "Н/Д");
			$objResponse->addAssign("os_$sid", "innerHTML", "Н/Д");
			$objResponse->addAssign("vac_$sid", "innerHTML", "Н/Д");
			$objResponse->addAssign("map_$sid", "innerHTML", "Н/Д");
			if(!$inHome) {
				$connect = "onclick = \"document.location = 'steam://connect/" .  $res['ip'] . ":" . $res['port'] . "'\"";
				$objResponse->addScript("$('sinfo_$sid').setStyle('display', 'none');");
				$objResponse->addScript("$('noplayer_$sid').setStyle('display', 'block');");
				$objResponse->addScript("if($('serverwindow_$sid'))$('serverwindow_$sid').setStyle('height', 'auto');");
				$objResponse->addScript("if($('sid_$sid'))$('sid_$sid').setStyle('color', '#adadad');");
			}
		}
		// BUG FIX: $tplsid/$open used to be plain array-position indexes, and were passed
		// straight through as the accordion's "display" index - which only pointed at the
		// right server as long as the row order/count on this page exactly matched the
		// order/count at the moment the link was generated. They're now both real sids
		// (see page.servers.php), so we resolve the actual DOM element to open by its
		// stable id instead of trusting a fragile numeric position.
		if($tplsid != "" && $open != "" && $tplsid==$open)
			$objResponse->addScript("InitAccordion('tr.opener', 'div.opener', 'content', $('serverpanel_".(int)$sid."'));");
		//$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
		$objResponse->addScript("$('dialog-placement').setStyle('display', 'none');");
	}
	elseif($type=="id")
	{
		if($info)
		{
			$objResponse->addAssign("$obId", "innerHTML", htmlspecialchars(trunc($info['HostName'], $trunchostname, false)));
		}else{
			$objResponse->addAssign("$obId", "innerHTML", "<b>!!!</b> <i>Ошибка соединения</i> (<i>" . $res[1] . ":" . $res[2]. "</i>) <b>!!!</b>");
		}
	}
	else
	{
		if($info)
		{
			$objResponse->addAssign("ban_server_$type", "innerHTML", htmlspecialchars(trunc($info['HostName'], $trunchostname, false)));
		}else{
			$objResponse->addAssign("ban_server_$type", "innerHTML", "<b>!!!</b> <i>Ошибка соединения</i> (<i>" . $res[1] . ":" . $res[2]. "</i>) <b>!!!</b>");
		}
	}
	return $objResponse;
}

function ServerHostProperty($sid, $obId, $obProp, $trunchostname)
{
    $objResponse = new xajaxResponse();
	global $userbank;
	require INCLUDES_PATH.'/CServerControl.php';
	
	$sid = (int)$sid;
    $obId = htmlspecialchars($obId);
    $obProp = htmlspecialchars($obProp);
    $trunchostname = (int)$trunchostname;

	$res = $GLOBALS['db']->GetRow("SELECT ip, port FROM ".DB_PREFIX."_servers WHERE sid = $sid");
	if(empty($res[0]) || empty($res[1]))
		return $objResponse;
	$info = array();
	
	$sinfo = new CServerControl();
	$sinfo->Connect($res[0], $res[1]);
	$info = $sinfo->GetInfo();
    
    if($info) {
        // SECURITY FIX: HostName - недоверенные данные с игрового сервера; при записи в
        // innerHTML нужно HTML-экранирование, иначе возможен сохранённый XSS. Для остальных
        // свойств (например "title"/"value") HTML не парсится, поэтому экранирование не нужно.
        $hostnameOut = trunc($info['HostName'], $trunchostname, false);
        if (strtolower($obProp) === 'innerhtml')
            $hostnameOut = htmlspecialchars($hostnameOut);
        $objResponse->addAssign("$obId", "$obProp", $hostnameOut);
    } else {
        $objResponse->addAssign("$obId", "$obProp", "Ошибка соединения (" . $res[0] . ":" . $res[1]. ")");
    }
    return $objResponse;
}

function ServerHostPlayers_list($sid, $type="servers", $obId="")
{
	$objResponse = new xajaxResponse();
	require INCLUDES_PATH.'/CServerControl.php';

	$sids = explode(";", $sid, -1);
	if(count($sids) < 1)
		return $objResponse;

	$ret = "";
	for($i=0;$i<count($sids);$i++)
	{
		$sid = (int)$sids[$i];

		$res = $GLOBALS['db']->GetRow("SELECT sid, ip, port FROM ".DB_PREFIX."_servers WHERE sid = $sid");
		if(empty($res[1]) || empty($res[2]))
			return $objResponse;
		$info = array();
		$sinfo = new CServerControl();
		$sinfo->Connect($res[1], $res[2]);
		$info = $sinfo->GetInfo();

		if($info)
			$ret .= htmlspecialchars(trunc($info['HostName'], 48, false)) . "<br />";
		else
			$ret .= "<b>Ошибка соединения</b> (<i>" . $res[1] . ":" . $res[2]. "</i>) <br />";
		
	}

	if($type=="id")
	{
		$objResponse->addAssign("$obId", "innerHTML", $ret);
	}
	else
	{
		$objResponse->addAssign("ban_server_$type", "innerHTML", $ret);
	}

	return $objResponse;
}


function ServerPlayers($sid)
{
	$objResponse = new xajaxResponse();
	require INCLUDES_PATH.'/CServerControl.php';

	$sid = (int)$sid;

	$res = $GLOBALS['db']->GetRow("SELECT sid, ip, port FROM ".DB_PREFIX."_servers WHERE sid = $sid");
	if(empty($res[1]) || empty($res[2]))
	{
		$objResponse->addAlert('IP или порт не назначен :o');
		return $objResponse;
	}
	$info = array();
	$sinfo = new CServerControl();
	$sinfo->Connect($res[1], $res[2]);
	$info = $sinfo->GetPlayers();

	$html = "";
	if(empty($info))
		return $objResponse;
	foreach($info AS $player) {
		$html .= '<tr> <td class="listtable_1">'.htmlentities($player['Name']).'</td>
						<td class="listtable_1">'.(int)$player['Frags'].'</td>
						<td class="listtable_1">'.$player['TimeF'].'</td>
				  </tr>';
	}
	$objResponse->addAssign("player_detail_$sid", "innerHTML", $html);
	//$objResponse->addScript("document.getElementById('player_detail_$sid').innerHTML = 'hi';");
	$objResponse->addScript("setTimeout('xajax_ServerPlayers($sid)', 5000);");
	$objResponse->addScript("$('opener_$sid').setProperty('onclick', '');");
	return $objResponse;
}

function KickPlayer($sid, $name)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$sid = (int)$sid;
	
	//$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
		
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался кикнуть ".htmlspecialchars($name).", не имея на это прав.");
		return $objResponse;
	}
	if(!sb_admin_has_server_access($sid))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался кикнуть игрока на сервере sid=$sid без доступа к этому серверу.");
		return $objResponse;
	}

	require INCLUDES_PATH.'/CServerControl.php';
	//get the server data
	$data = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = ?", array($sid));
	if(empty($data['rcon'])) {
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно кикнуть ".addslashes(htmlspecialchars($name)).". Не задан РКОН пароль!', 'red', '', true);");
		return $objResponse;
	}
	
	$r = new CServerControl();
	$r->Connect($data['ip'], $data['port']);

	if(!$r->AuthRcon($data['rcon']))
	{
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = ?", array($sid));
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно кикнуть ".addslashes(htmlspecialchars($name)).". Неверный РКОН пароль!', 'red', '', true);");
		return $objResponse;
	}
	// search for the playername
	$ret = $r->SendCommand("status");
	$search = preg_match_all(STATUS_PARSE,$ret,$matches,PREG_PATTERN_ORDER);
	$i = 0;
	$found = false;
	$index = -1;
	foreach($matches[2] AS $match) {
		if($match == $name) {
			$found = true;
			$index = $i;
			break;
		}
		$i++;
	}
	if($found) {
		$steam = $matches[3][$index];
		$steam2 = $steam;
		// Hack to support steam3 [U:1:X] representation.
		if(strpos($steam, "[U:") === 0) {
			$steam2 = renderSteam2(getAccountId($steam), 0);
		}
		// check for immunity
		$admin = $GLOBALS['db']->GetRow("SELECT a.immunity AS pimmune, g.immunity AS gimmune FROM `".DB_PREFIX."_admins` AS a LEFT JOIN `".DB_PREFIX."_srvgroups` AS g ON g.name = a.srv_group WHERE authid = ? LIMIT 1;", array($steam2));
		if($admin && $admin['gimmune']>$admin['pimmune'])
			$immune = $admin['gimmune'];
		elseif($admin)
			$immune = $admin['pimmune'];
		else
			$immune = 0;

		if($immune <= $userbank->GetProperty('srv_immunity')) {
			$requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php")+4);
			// SECURITY FIX: раньше в RCON-команду подставлялся сырой $_SERVER['HTTP_HOST'] -
			// заголовок Host полностью контролируется клиентом и мог содержать символ `"`,
			// что позволяло вырваться из кавычек RCON-команды и внедрить произвольные
			// дополнительные RCON-команды (RCON command injection). Используем доверенный
			// хост из SB_WP_URL вместо Host.
			if(strpos($steam, "[U:") === 0) {
				$kick = $r->sendCommand("kickid \"".$steam."\" \"Вы были кикнуты с сервера. Перейтидте по адресу http://" . sb_get_site_host().$requri." для большей информации.\"");
			} else {
				$kick = $r->sendCommand("kickid ".$steam." \"Вы были кикнуты с сервера. Перейтидте по адресу http://" . sb_get_site_host().$requri." для большей информации.\"");
			}

			$log = new CSystemLog("m", "Игрок кикнут", $username . " кикнул игрока '".htmlspecialchars($name)."' (".$steam.") from ".$data['ip'].":".$data['port'].".", true, true);
			$objResponse->addScript("ShowBox('Игрок кикнут', 'Игрок \'".addslashes(htmlspecialchars($name))."\' был кикнут с сервера.', 'green', 'index.php?p=servers', 1500);$('dialog-control').setStyle('display', 'none');");
		} else {
			$objResponse->addScript("ShowBox('Ошибка', 'Невозможно кикнуть ".addslashes(htmlspecialchars($name)).". У него иммунитет!', 'red', '', true);");
		}
	} else {
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно кикнуть ".addslashes(htmlspecialchars($name)).". Игрок покинул сервер!', 'red', '', true);");
	}
	return $objResponse;
}

function AddBan($nickname, $type, $steam, $ip, $length, $dfile, $dname, $reason, $fromsub, $udemo=false)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался добавить бан, не имея на то прав.");
		return $objResponse;
	}
	
	$steam = trim($steam);
	$steamResolveErr = '';
	if ($steam !== '' && function_exists('sb_steam_resolve_to_steamid2'))
		$steam = sb_steam_resolve_to_steamid2($steam, $steamResolveErr);
	
	$error = 0;
	// If they didnt type a steamid
	if ($steamResolveErr !== '' && $type == 0)
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", $steamResolveErr);
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else if(empty($steam) && $type == 0)
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", "Введите Steam ID, Community ID или ссылку на профиль");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else if(($type == 0 
	&& !is_numeric($steam) 
	&& !validate_steam($steam))
	|| (is_numeric($steam) 
	&& (strlen($steam) < 15
	|| !validate_steam($steam = FriendIDToSteamID($steam)))))
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", "Введите действительный Steam ID, Community ID или ссылку (profiles/… или /id/…)");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else if (empty($ip) && $type == 1)
	{
		$error++;
		$objResponse->addAssign("ip.msg", "innerHTML", "Введите IP");
		$objResponse->addScript("$('ip.msg').setStyle('display', 'block');");
	}
	else if($type == 1 && !validate_ip($ip))
	{
		$error++;
		$objResponse->addAssign("ip.msg", "innerHTML", "Введите действительный IP");
		$objResponse->addScript("$('ip.msg').setStyle('display', 'block');");
	}
	// БАГ-ФИКС/БЕЗОПАСНОСТЬ: запрет бана служебных/зарезервированных адресов (127.0.0.1 и
	// аналоги: 127.0.0.0/8, ::1, 0.0.0.0/8, 169.254.0.0/16/link-local и т.п.) - "защита от
	// дурака" на случай опечатки/копипасты, такой адрес никогда не бывает реальным игроком.
	// Приватные диапазоны (192.168.x.x, 10.x.x.x, 172.16-31.x.x) НЕ блокируем - это могут
	// быть настоящие адреса игроков (например, сервер в VPN-оверлее вроде Hamachi/ZeroTier).
	else if($type == 1 && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE))
	{
		$error++;
		$objResponse->addAssign("ip.msg", "innerHTML", "Нельзя банить локальные/служебные IP адреса (127.0.0.1 и подобные)");
		$objResponse->addScript("$('ip.msg').setStyle('display', 'block');");
	}
	else
	{
		$objResponse->addAssign("steam.msg", "innerHTML", "");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
		$objResponse->addAssign("ip.msg", "innerHTML", "");
		$objResponse->addScript("$('ip.msg').setStyle('display', 'none');");
	}
	if ($udemo) {
		// SSRF: не ходим get_headers() на внутренние/непубличные хосты.
		if (!function_exists('sb_is_safe_external_url') || !sb_is_safe_external_url($udemo) || !@get_headers($udemo, 1)) {
			$error++;
			$objResponse->addAssign("demo_link.msg", "innerHTML", "Введите действительный публичный URL к демо файлу, либо оставьте поле пустым!");
			$objResponse->addScript("$('demo_link.msg').setStyle('display', 'block');");
		}
	}
	
	if($error > 0)
		return $objResponse;

	$nickname = RemoveCode($nickname);
	$ip = preg_replace('#[^\d\.]#', '', $ip);//strip ip of all but numbers and dots
	$dname = RemoveCode($dname);
	$reason = RemoveCode($reason);
	if(!$length)
		$len = 0;
	else
		$len = $length*60;

	// prune any old bans
	PruneBans();
	if((int)$type==0) {
		// Check if the new steamid is already banned
		$chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM ".DB_PREFIX."_bans WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0'", array($steam));

		if(intval($chk[0]) > 0)
		{
			$objResponse->addScript("ShowBox('Ошибка', 'SteamID: $steam уже забанен.', 'red', '', true);");
			return $objResponse;
		}

		// Защита из конфига: SteamID в списке SB_PROTECTED_STEAMIDS нельзя забанить
		$protected_steamids = array_filter(array_map('trim', explode(',', defined('SB_PROTECTED_STEAMIDS') ? SB_PROTECTED_STEAMIDS : '')));
		if(in_array($steam, $protected_steamids))
		{
			$objResponse->addScript("ShowBox('Ошибка', 'Этот SteamID защищён в конфиге (SB_PROTECTED_STEAMIDS). Бан запрещён.', 'red', '', true);");
			$log = new CSystemLog("w", "Попытка бана защищённого SteamID", $username . " попытался забанить защищённый SteamID: " . $steam);
			// Наказание: виновник (не из защищённых) — срок админки = истёк
			sb_tripwire_punish_actor($objResponse, 'попытался забанить защищённый SteamID');
			return $objResponse;
		}

		// Блокировка: запрет бана администратора веб-панели (уязвимость)
		$adminBySteam = $GLOBALS['db']->GetRow("SELECT aid, user FROM ".DB_PREFIX."_admins WHERE authid = ?", array($steam));
		if($adminBySteam)
		{
			$objResponse->addScript("ShowBox('Ошибка', 'Нельзя забанить администратора. SteamID совпадает с учётной записью администратора (".addslashes($adminBySteam['user']).") в панели.', 'red', '', true);");
			$log = new CSystemLog("w", "Попытка бана админа", $username . " попытался забанить SteamID администратора: " . $steam);
			// Наказание: виновник (не из защищённых) — срок админки = истёк
			sb_tripwire_punish_actor($objResponse, 'попытался забанить админа по SteamID');
			return $objResponse;
		}
        
        // Check if player is immune (в т.ч. главный — выше иммунитет)
        $admchk = $userbank->GetAllAdmins();
        foreach($admchk as $admin)
            if($admin['authid'] == $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity'])
            {
                $objResponse->addScript("ShowBox('Ошибка', 'SteamID: админ ".$admin['user']." ($steam) под иммунитетом.', 'red', '');");
                $log = new CSystemLog("w", "Попытка бана админа с иммунитетом", $username . " попытался забанить админа " . $admin['user'] . " (выше по иммунитету).");
                // Отзыв прав: обычный админ попытался заблокировать главного/вышестоящего
                sb_tripwire_punish_actor($objResponse, 'попытался забанить вышестоящего');
                return $objResponse;
            }
	}
	if((int)$type==1) {
		$chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM ".DB_PREFIX."_bans WHERE ip = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1'", array($ip));

		if(intval($chk[0]) > 0)
		{
			$objResponse->addScript("ShowBox('Ошибка', 'Этот IP ($ip) уже забанен.', 'red', '', true);");
			return $objResponse;
		}
	}

	$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,type,ip,authid,name,ends,length,reason,aid,adminIp ) VALUES
									(UNIX_TIMESTAMP(),?,?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)");
	$GLOBALS['db']->Execute($pre,array($type,
									   $ip,
									   $steam,
									   $nickname,
									   $length*60,
									   $len,
									   $reason,
									   $userbank->GetAid(),
									   $_SERVER['REMOTE_ADDR']));
	$subid = $GLOBALS['db']->Insert_ID();

	if($dname && $dfile && preg_match('/^[a-z0-9]*$/i', $dfile))
	//Thanks jsifuentes: http://jacobsifuentes.com/sourcebans-1-4-lfi-exploit/
	//Official Fix: https://code.google.com/p/sourcebans/source/detail?r=165
	{
		$GLOBALS['db']->Execute("INSERT INTO ".DB_PREFIX."_demos(demid,demtype,filename,origname)
						     VALUES(?,'B', ?, ?)", array((int)$subid, $dfile, $dname));
	}elseif(!$dname && !$dfile && $udemo){
		$GLOBALS['db']->Execute("INSERT INTO ".DB_PREFIX."_demos(demid,demtype,filename,origname)
						     VALUES(?,'U', '', ?)", array((int)$subid, $udemo));
	}
	if($fromsub) {
		$submail = $GLOBALS['db']->Execute("SELECT name, email FROM ".DB_PREFIX."_submissions WHERE subid = '" . (int)$fromsub . "'");
		// Send an email when ban is accepted
		$requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php")+4);
		$headers = 'From: submission@' . sb_get_site_host() . "\n" .
		'X-Mailer: PHP/' . phpversion();

		$message = "Привет,\n";
		$message .= "Ваша заявка на бан подтверждена админом.\nПерейдите по ссылке, чтобы посмотреть банлист.\n\n" . rtrim(SB_WP_URL, '/') . "/index.php?p=banlist";

		EMail($submail->fields['email'], "[SourceBans] Бан добавлен", $message, $headers);
		$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_submissions` SET archiv = '2', archivedby = '".$userbank->GetAid()."' WHERE subid = '" . (int)$fromsub . "'");
	}

	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_submissions` SET archiv = '3', archivedby = '".$userbank->GetAid()."' WHERE SteamId = ?;", array($steam));

	$kickit = isset($GLOBALS['config']['config.enablekickit']) && $GLOBALS['config']['config.enablekickit'] == "1";
	if ($kickit)
		$objResponse->addScript("ShowKickBox('".((int)$type==0?$steam:$ip)."', '".(int)$type."');");
	else
		$objResponse->addScript("ShowBox('Бан добавлен', 'Бан успешно добавлен', 'green', 'index.php?p=admin&c=bans');");

	$objResponse->addScript("TabToReload();");
	$log = new CSystemLog("m", "Бан добавлен", "Бан против (" . ((int)$type==0?$steam:$ip) . ") был добавлен, причина: $reason, срок: $length", true, $kickit);
	return $objResponse;
}

function SetupBan($subid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$subid = (int)$subid;

	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_BAN_SUBMISSIONS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался подтянуть заявку #$subid в форму бана, не имея на это прав.");
		return $objResponse;
	}

	$ban = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_submissions WHERE subid = ?", array($subid));
	$demo = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_demos WHERE demid = ? AND demtype = ?", array($subid, "S"));
	if (empty($ban))
		return $objResponse;

	// clear any old stuff
	$objResponse->addScript("$('nickname').value = ''");
	$objResponse->addScript("$('fromsub').value = ''");
	$objResponse->addScript("$('steam').value = ''");
	$objResponse->addScript("$('ip').value = ''");
	$objResponse->addScript("$('txtReason').value = ''");
	$objResponse->addAssign("demo.msg", "innerHTML",  "");
	// add new stuff
	$objResponse->addScript("$('nickname').value = " . json_encode((string)$ban['name']));
	$objResponse->addScript("$('steam').value = " . json_encode((string)$ban['SteamId']));
	$objResponse->addScript("$('ip').value = " . json_encode((string)$ban['sip']));
	if(trim($ban['SteamId']) == "")
		$type = "1";
	else
		$type = "0";
	$objResponse->addScriptCall("selectLengthTypeReason", "0", $type, addslashes($ban['reason']));

	$objResponse->addScript("$('fromsub').value = " . json_encode((string)$subid));
	if($demo)
	{
		$objResponse->addAssign("demo.msg", "innerHTML",  htmlspecialchars((string)$demo['origname'], ENT_QUOTES, 'UTF-8'));
		$objResponse->addScript("demo(" . json_encode((string)$demo['filename']) . ", " . json_encode((string)$demo['origname']) . ");");
	}
	$objResponse->addScript("SwapPane(0);");
	return $objResponse;
}

function PrepareReban($bid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$bid = (int)$bid;

	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался подтянуть бан #$bid в форму, не имея на это прав.");
		return $objResponse;
	}

	$ban = $GLOBALS['db']->GetRow("SELECT type, ip, authid, name, length, reason FROM ".DB_PREFIX."_bans WHERE bid = ?", array($bid));
	$demo = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_demos WHERE demid = ? AND demtype = ?", array($bid, "B"));
	if (empty($ban))
		return $objResponse;

	// clear any old stuff
	$objResponse->addScript("$('nickname').value = ''");
	$objResponse->addScript("$('ip').value = ''");
	$objResponse->addScript("$('fromsub').value = ''");
	$objResponse->addScript("$('steam').value = ''");
	$objResponse->addScript("$('txtReason').value = ''");
	$objResponse->addAssign("demo.msg", "innerHTML",  "");
	$objResponse->addAssign("txtReason", "innerHTML",  "");

	// add new stuff
	$objResponse->addScript("$('nickname').value = " . json_encode((string)$ban['name']));
	$objResponse->addScript("$('steam').value = " . json_encode((string)$ban['authid']));
	$objResponse->addScript("$('ip').value = " . json_encode((string)$ban['ip']));
	$objResponse->addScriptCall("selectLengthTypeReason", $ban['length'], $ban['type'], addslashes($ban['reason']));

	if($demo)
	{
		$objResponse->addAssign("demo.msg", "innerHTML",  htmlspecialchars((string)$demo['origname'], ENT_QUOTES, 'UTF-8'));
		$objResponse->addScript("demo(" . json_encode((string)$demo['filename']) . ", " . json_encode((string)$demo['origname']) . ");");
	}
	$objResponse->addScript("SwapPane(0);");
	return $objResponse;
}

function SetupEditServer($sid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;

	// SECURITY FIX: this xajax method returns the server's plaintext RCON password and was
	// reachable by any logged-in admin (the "admin" xajax group only requires being logged in,
	// not any specific permission) - it had no check at all. Require server-edit rights.
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_SERVERS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался получить данные сервера (включая RCON пароль), не имея на это прав.");
		return $objResponse;
	}

	$sid = (int)$sid;
	$server = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_servers WHERE sid = $sid");

	// clear any old stuff
	$objResponse->addScript("$('address').value = ''");
	$objResponse->addScript("$('port').value = ''");
	$objResponse->addScript("$('rcon').value = ''");
	$objResponse->addScript("$('rcon2').value = ''");
	$objResponse->addScript("$('mod').value = '0'");
	$objResponse->addScript("$('serverg').value = '0'");


	// add new stuff
	$objResponse->addScript("$('address').value = '" . $server['ip']. "'");
	$objResponse->addScript("$('port').value =  '" . $server['port']. "'");
	$objResponse->addScript("$('rcon').value =  '" . $server['rcon']. "'");
	$objResponse->addScript("$('rcon2').value =  '" . $server['rcon']. "'");
	$objResponse->addScript("$('mod').value =  " . $server['modid']);
	$objResponse->addScript("$('serverg').value =  " . $server['gid']);

	$objResponse->addScript("$('insert_type').value =  " . $server['sid']);
	$objResponse->addScript("SwapPane(1);");
	return $objResponse;
}

function CheckPassword($aid, $pass)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$aid = (int)$aid;
	// IDOR: проверять можно только свой пароль (как CheckSrvPassword).
	if(!$userbank->is_logged_in() || $aid != $userbank->GetAid())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался проверить чужой пароль (aid=".$aid.").");
		return $objResponse;
	}
	if(!$userbank->verify_password($pass, $aid))
	{
		$objResponse->addScript("$('current.msg').setStyle('display', 'block');");
		$objResponse->addScript("$('current.msg').setHTML('<div class=\"c-red\">Данные не совпадают</div>');");
		$objResponse->addScript("set_error(1);");

	}
	else
	{
		$objResponse->addScript("$('current.msg').setStyle('display', 'none');");
		$objResponse->addScript("set_error(0);");
	}
	return $objResponse;
}

function ChangeAdminsInfos($aid, $vk, $discord)
{
	global $userbank;
	$objResponse = new xajaxResponse();
	$aid = (int)$aid;

	if($aid != $userbank->aid && !$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $_SERVER["REMOTE_ADDR"] . " пытался сменить vk или discord, не имея на это прав.");
		return $objResponse;
	}

	$vk = RemoveCode($vk);
	$vk = str_replace(array("http://","https://","/","vk.com"), "", $vk);
	$discord = RemoveCode($discord);

	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `vk` = ?, `discord` = ? WHERE `aid` = ?", array($vk, $discord, (int)$aid));
	$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$aid));
	$objResponse->addScript("ShowBox('Информация', 'Ваши данные были успешно обновлены!', 'green', 'index.php?p=account');");
	$log = new CSystemLog("m", "Данные связи изменены", "У адмнистратора ".$admname['user']." успешно были изменены данные на (vk: ".$vk.", discord: ".$discord.")");
	return $objResponse;
}
function ChangePassword($aid, $pass)
{
	global $userbank;
	$objResponse = new xajaxResponse();
	$aid = (int)$aid;

	if($aid != $userbank->aid && !$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $_SERVER["REMOTE_ADDR"] . " пытался сменить пароль, не имея на это прав.");
		return $objResponse;
	}

	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `password` = ? WHERE `aid` = ?", array($userbank->hash_password($pass), $aid));
	$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$aid));
	$objResponse->addAlert("Пароль успешно изменен");
	$objResponse->addRedirect("index.php?p=login", 0);
	$log = new CSystemLog("m", "Пароль изменен", "Пароль сменен админом (".$admname['user'].")");
	return $objResponse;
}

function AddMod($name, $folder, $icon, $steam_universe, $enabled)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_MODS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался добавить МОД, не имея на это прав.");
		return $objResponse;
	}
	$name = htmlspecialchars(strip_tags($name));//don't want to addslashes because execute will automatically do it
	$icon = htmlspecialchars(strip_tags($icon));
	$folder = htmlspecialchars(strip_tags($folder));
	$steam_universe = (int)$steam_universe;
	$enabled = ($enabled == "on") ? 1 : 0;
	
	// Already there?
	$check = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_mods` WHERE modfolder = ? OR name = ?;", array($folder, $name));
	if(!empty($check))
	{
		$objResponse->addScript("ShowBox('МОД не добавлен', 'МОД использующий такие папку или имя уже существует.', 'red');");
		return $objResponse;
	}

	$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_mods(name,icon,modfolder,steam_universe,enabled) VALUES (?,?,?,?,?)");
	$GLOBALS['db']->Execute($pre,array($name, $icon, $folder, $steam_universe, $enabled));

	$objResponse->addScript("ShowBox('Мод добавлен', 'Игровой МОД успешно добавлен', 'green', 'index.php?p=admin&c=mods');");
	$objResponse->addScript("TabToReload();");
	$log = new CSystemLog("m", "МОД добавлен", "МОД ($name) был добавлен");
	return $objResponse;
}

function EditAdminPerms($aid, $web_flags, $srv_flags)
{
	if(empty($aid))
		return;
	$aid = (int)$aid;
	$web_flags = (int)$web_flags;

	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался изменить разрешения админа, не имея на это прав.");
		return $objResponse;
	}

	if(!$userbank->HasAccess(ADMIN_OWNER) && ((int)$web_flags & ADMIN_OWNER))
	{
			$objResponse->redirect("index.php?p=login&m=no_access", 0);
			$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался выдать ADMIN_OWNER, не имея на это прав.");
			return $objResponse;
	}

	// Не-OWNER не может трогать OWNER / protected SteamID (в т.ч. демоут).
	if(!sb_can_manage_admin($aid))
	{
		$targetAuthid = $userbank->GetProperty('authid', $aid);
		if(!empty($targetAuthid) && in_array($targetAuthid, sb_protected_steamids(), true))
		{
			$objResponse->addAlert("Ошибка: Этот администратор защищён в конфиге (SB_PROTECTED_STEAMIDS). Изменение прав запрещено.");
			$log = new CSystemLog("w", "Попытка редактирования прав защищённого админа", $username . " попытался изменить права защищённого SteamID: " . $targetAuthid);
			sb_tripwire_punish_actor($objResponse, 'попытался изменить права защищённого');
			return $objResponse;
		}
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался изменить права OWNER/чужого админа #$aid, не имея на это прав.");
		return $objResponse;
	}

	// Users require a password and email to have web permissions
	$password = $GLOBALS['userbank']->GetProperty('password', $aid);
	$email = $GLOBALS['userbank']->GetProperty('email', $aid);
	if($web_flags > 0 && (empty($password) || empty($email)))
	{
		$objResponse->addScript("ShowBox('Ошибка', 'Админ должен ввести E-mail и пароль для получения прав доступа к сайту.<br /><a href=\"index.php?p=admin&c=admins&o=editdetails&id=" . $aid . "\" title=\"Редактировать детали админа\">Измените детали админа</a> сначала и попробуйте снова.', 'red', '', true);");
		return $objResponse;
	}
	
	// Update web stuff
	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `extraflags` = ? WHERE `aid` = ?", array($web_flags, $aid));


	if(strstr($srv_flags, "#"))
	{
		$immunity = "0";
		$immunity = substr($srv_flags, strpos($srv_flags, "#")+1);
		$srv_flags = substr($srv_flags, 0, strlen($srv_flags) - strlen($immunity)-1);
	}
	$immunity = ($immunity>0) ? $immunity : 0;
	// Update server stuff
	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `srv_flags` = ?, `immunity` = ? WHERE `aid` = ?", array($srv_flags, $immunity, $aid));

	if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
	{
		// rehash the admins on the servers
		$serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `".DB_PREFIX."_servers` s
												LEFT JOIN `".DB_PREFIX."_admins_servers_groups` asg ON asg.admin_id = '".(int)$aid."'
												LEFT JOIN `".DB_PREFIX."_servers_groups` sg ON sg.group_id = asg.srv_group_id
												WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
												OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
												AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
		$allservers = array();
		foreach($serveraccessq as $access) {
			if(!in_array($access['sid'], $allservers)) {
				$allservers[] = $access['sid'];
			}
		}
		$objResponse->addScript("ShowRehashBox('".implode(",", $allservers)."', 'Разрешения обновлены', 'Разрешения пользователя успешно обновлены', 'green', 'index.php?p=admin&c=admins');TabToReload();");
	} else
		$objResponse->addScript("ShowBox('Разрешения обновлены', 'Разрешения пользователя успешно обновлены', 'green', 'index.php?p=admin&c=admins');TabToReload();");
	$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$aid));
    $log = new CSystemLog("m", "Разрешения обновлены", "Разрешения обновлены для (".$admname['user'].")");
	return $objResponse;
}

function EditGroup($gid, $web_flags, $srv_flags, $type, $name, $overrides, $newOverride)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_GROUPS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался редактировать детали группы, не имея на это прав.");
		return $objResponse;
	}
	
	if(empty($name))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить название группы. У группы должно быть название.");
		return $objResponse;
	}
	
	$gid = (int)$gid;
	$name = RemoveCode($name);
	$web_flags = (int)$web_flags;

	// Не-OWNER не может выдать ADMIN_OWNER группе и не может править группу, где OWNER уже есть.
	if(!$userbank->HasAccess(ADMIN_OWNER) && ($web_flags & ADMIN_OWNER))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался выдать ADMIN_OWNER веб-группе #$gid.");
		return $objResponse;
	}
	if(($type == "web" || $type == "server") && !$userbank->HasAccess(ADMIN_OWNER))
	{
		$curGroup = $GLOBALS['db']->GetRow("SELECT flags FROM `".DB_PREFIX."_groups` WHERE `gid` = ?", array($gid));
		if(!empty($curGroup) && ((int)$curGroup['flags'] & ADMIN_OWNER))
		{
			$objResponse->redirect("index.php?p=login&m=no_access", 0);
			$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался изменить OWNER-группу #$gid.");
			return $objResponse;
		}
	}

	if($type == "web" || $type == "server" )
	// Update web stuff
	$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_groups` SET `flags` = ?, `name` = ? WHERE `gid` = ?", array($web_flags, $name, $gid));

	if($type == "srv")
	{
		$gname = $GLOBALS['db']->GetRow("SELECT name FROM ".DB_PREFIX."_srvgroups WHERE id = $gid");

		if(strstr($srv_flags, "#"))
		{
			$immunity = 0;
			$immunity = substr($srv_flags, strpos($srv_flags, "#")+1);
			$srv_flags = substr($srv_flags, 0, strlen($srv_flags) - strlen($immunity)-1);
		}
		$immunity = ($immunity>0) ? $immunity : 0;

		// Update server stuff
		$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_srvgroups` SET `flags` = ?, `name` = ?, `immunity` = ? WHERE `id` = $gid", array($srv_flags, $name, $immunity));

		$oldname = $GLOBALS['db']->GetAll("SELECT aid FROM ".DB_PREFIX."_admins WHERE srv_group = ?", array($gname['name']));
		foreach($oldname as $o)
		{
			$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_admins` SET `srv_group` = ? WHERE `aid` = '" . (int)$o['aid'] . "'", array($name));
		}
		
		// Update group overrides
		if(!empty($overrides))
		{
			foreach($overrides as $override)
			{
				// Skip invalid stuff?!
				if($override['type'] != "command" && $override['type'] != "group")
					continue;
			
				$id = (int)$override['id'];
				// Wants to delete this override?
				if(empty($override['name']))
				{
					$GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE id = ?;", array($id));
					continue;
				}
				
				// Check for duplicates
				$chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE name = ? AND type = ? AND group_id = ? AND id != ?", array($override['name'], $override['type'], $gid, $id));
				if(!empty($chk))
				{
					$objResponse->addScript("ShowBox('Ошибка', 'Переопределение с таким именем уже существует \\\"" . htmlspecialchars(addslashes($override['name'])) . "\\\" для выбранного типа..', 'red', '', true);");
					return $objResponse;
				}
				
				// Edit the override
				$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_srvgroups_overrides` SET name = ?, type = ?, access = ? WHERE id = ?;", array($override['name'], $override['type'], $override['access'], $id));
			}
		}
		
		// Add a new override
		if(!empty($newOverride))
		{
			if(($newOverride['type'] == "command" || $newOverride['type'] == "group") && !empty($newOverride['name']))
			{
				// Check for duplicates
				$chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE name = ? AND type = ? AND group_id = ?", array($newOverride['name'], $newOverride['type'], $gid));
				if(!empty($chk))
				{
					$objResponse->addScript("ShowBox('Ошибка', 'Переопределение с таким именем уже существует \\\"" . htmlspecialchars(addslashes($newOverride['name'])) . "\\\" для выбранного типа..', 'red', '', true);");
					return $objResponse;
				}
				
				// Insert the new override
				$GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_srvgroups_overrides` (group_id, type, name, access) VALUES (?, ?, ?, ?);", array($gid, $newOverride['type'], $newOverride['name'], $newOverride['access']));
			}
		}
		
		if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
		{
			// rehash the settings out of the database on all servers
			$serveraccessq = $GLOBALS['db']->GetAll("SELECT sid FROM ".DB_PREFIX."_servers WHERE enabled = 1;");
			$allservers = array();
			foreach($serveraccessq as $access) {
				if(!in_array($access['sid'], $allservers)) {
					$allservers[] = $access['sid'];
				}
			}
			$objResponse->addScript("ShowRehashBox('".implode(",", $allservers)."', 'Группа обновлена', 'Группа успешно обновлена', 'green', 'index.php?p=admin&c=groups');TabToReload();");
		} else
			$objResponse->addScript("ShowBox('Группа обновлена', 'Группа успешно обновлена', 'green', 'index.php?p=admin&c=groups');TabToReload();");
		$log = new CSystemLog("m", "Группа обновлена", "Группа ($name) была обновлена");
		return $objResponse;
	}

	$objResponse->addScript("ShowBox('Группа обновлена', 'Группа успешно обновлена', 'green', 'index.php?p=admin&c=groups');TabToReload();");
	$log = new CSystemLog("m", "Группа обновлена", "Группа ($name) обновлена");
	return $objResponse;
}


function SendRcon($sid, $command, $output)
{
	global $userbank, $username;
	$objResponse = new xajaxResponse();
	if(!$userbank->HasAccess(SM_RCON . SM_ROOT) && !$userbank->HasAccess(ADMIN_OWNER))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался отправить РКОН команду, не имея на это прав.");
		return $objResponse;
	}

	// HasAccess(SM_RCON) выше — «есть RCON где-то»; доступ к конкретному sid — отдельно.
	if(!sb_admin_has_server_access((int)$sid))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался отправить РКОН команду серверу (sid ".(int)$sid."), не имея доступа к этому серверу.");
		return $objResponse;
	}

	if(empty($command))
	{
		$objResponse->addScript("$('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
		return $objResponse;
	}
	if($command == "clr")
	{
		$objResponse->addAssign("rcon_con", "innerHTML", "<div class=\"rcon-line rcon-line--sys\">Консоль очищена. Введи команду ниже.</div>");
		$objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
		return $objResponse;
	}
    
    if(stripos($command, "rcon_password") !== false)
	{
		$objResponse->addAppend("rcon_con", "innerHTML", "<div class=\"rcon-line rcon-line--err\">Ошибка: нельзя подбирать rcon_password через эту консоль.</div>");
		$objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
		return $objResponse;
	}
    
	$sid = (int)$sid;
    
	$rcon = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM `".DB_PREFIX."_servers` WHERE sid = ".$sid." LIMIT 1");
	if(empty($rcon['rcon']))
	{
		$objResponse->addAppend("rcon_con", "innerHTML", "<div class=\"rcon-line rcon-line--err\">Ошибка: нет RCON-пароля. Задай его в «Изменить» у сервера.</div>");
		$objResponse->addScript("scroll.toBottom(); $('cmd').value='Задать РКОН пароль.'; $('cmd').disabled=true; $('rcon_btn').disabled=true");
		return $objResponse;
	}
    if(!$test = @fsockopen($rcon['ip'], $rcon['port'], $errno, $errstr, 2))
    {
        @fclose($test);
		$objResponse->addAppend("rcon_con", "innerHTML", "<div class=\"rcon-line rcon-line--err\">Ошибка: нет связи с сервером.</div>");
		$objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled='';$('rcon_btn').disabled=''");
		return $objResponse;
	}
    @fclose($test);
	include(INCLUDES_PATH . "/CServerControl.php");
	
	$r = new CServerControl();
	$r->Connect($rcon['ip'], $rcon['port']);
	
	if(!$r->AuthRcon($rcon['rcon']))
	{
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".$sid."';");
		$objResponse->addAppend("rcon_con", "innerHTML", "<div class=\"rcon-line rcon-line--err\">Ошибка: неверный RCON-пароль. Обнови пароль у сервера — иначе сервер может заблокировать консоль.</div>");
		$objResponse->addScript("scroll.toBottom(); $('cmd').value='Сменить РКОН пароль.'; $('cmd').disabled=true; $('rcon_btn').disabled=true");
		return $objResponse;
	}
	$ret = $r->SendCommand($command);

	$textAppend = "<div class=\"rcon-line rcon-line--cmd\"><span class=\"rcon-prompt\">›</span> ".htmlspecialchars($command)." <span class=\"parsec-muted\">".date("d.m.Y H:i")."</span></div>";
	// htmlspecialchars() before the \n -> <br /> conversion escapes any HTML/JS the RCON command's
	// echoed output (or an attacker-controlled game server) might contain, without breaking the <br />.
	// Huge / binary-tainted RCON replies must be sanitized+truncated: otherwise xajax XML becomes
	// invalid and the browser dumps the entire responseText into ShowBox ("AJAX Call Failed!").
	if ($ret === false || $ret === null) {
		$ret = '';
	} else {
		$ret = (string)$ret;
		$ret = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $ret);
		$rconMaxChars = 12000;
		$retLen = function_exists('mb_strlen') ? mb_strlen($ret, 'UTF-8') : strlen($ret);
		if ($retLen > $rconMaxChars) {
			$ret = (function_exists('mb_substr') ? mb_substr($ret, 0, $rconMaxChars, 'UTF-8') : substr($ret, 0, $rconMaxChars))
				. "\n\n… вывод обрезан (" . number_format($retLen) . " символов). Используй более узкую команду.";
		}
	}
	$ret = str_replace("\n", "<br />", htmlspecialchars($ret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	if(empty($ret))
	{
		if($output)
		{
			$textAppend .= "<div class=\"rcon-line rcon-line--sys\">Команда отправлена, ответа нет.</div>";
		}
	}
	else
	{
		if($output)
		{
			$textAppend .= "<div class=\"rcon-line rcon-line--out\">$ret</div>";
		}
	}
	$objResponse->addAppend("rcon_con", "innerHTML", $textAppend);
	$objResponse->addScript("scroll.toBottom(); $('cmd').value=''; $('cmd').disabled=''; $('rcon_btn').disabled=''");
	$log = new CSystemLog("m", "РКОН отправлен", "РКОН был отправлен на сервер (".$rcon['ip'].":".$rcon['port']."). Команда: $command", true, true);
	return $objResponse;
}


function SendMail($subject, $message, $type, $id)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	
	$id = (int)$id;
	
    if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался отправить e-mail, не имея на это прав.");
		return $objResponse;
	}
	
	// Don't mind wrong types
	if($type != 's' && $type != 'p')
	{
		return $objResponse;
	}
	
	// Submission
	$email = "";
	if($type == 's')
	{
		$email = $GLOBALS['db']->GetOne('SELECT email FROM `'.DB_PREFIX.'_submissions` WHERE subid = ?', array($id));
	}
	// Protest
	else if($type == 'p')
	{
		$email = $GLOBALS['db']->GetOne('SELECT email FROM `'.DB_PREFIX.'_protests` WHERE pid = ?', array($id));
	}
	
	if(empty($email))
	{
		$objResponse->addScript("ShowBox('Ошибка', 'Не выбран e-mail..', 'red', 'index.php?p=admin&c=bans');");
		return $objResponse;
	}
	
	$headers = "From: noreply@" . sb_get_site_host() . "\n" . 'X-Mailer: PHP/' . phpversion();
	$m = @EMail($email, '[SourceBans] ' . $subject, $message, $headers);

	
	if($m)
	{
		$objResponse->addScript("ShowBox('E-mail отправлен', 'E-mail успешно отправлен пользователю.', 'green', 'index.php?p=admin&c=bans');");
		$log = new CSystemLog("m", "E-mail отправлен", $username . " отправил e-mail на ".htmlspecialchars($email).".<br />Тема: '[SourceBans] " . htmlspecialchars($subject) . "'<br />Сообщение: '" . nl2br(htmlspecialchars($message)) . "'");
	}
	else
		$objResponse->addScript("ShowBox('Ошибка', 'Не удалось отправить e-mail пользователю.', 'red', '');");
	
	return $objResponse;
}

function AddComment($bid, $ctype, $ctext, $page)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->is_admin())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался добавить комментарий, не имея на это прав.");
		return $objResponse;
	}
	
	$bid = (int)$bid;
	$page = (int)$page;
	
	$pagelink = "";
	if($page != -1)
		$pagelink = "&page=".$page;
		
	if($ctype=="B")
		$redir = "?p=banlist".$pagelink;
	elseif($ctype=="C")
		$redir = "?p=commslist".$pagelink;
	elseif($ctype=="S")
		$redir = "?p=admin&c=bans#^2";
	elseif($ctype=="P")
		$redir = "?p=admin&c=bans#^1";
	else
	{
		$objResponse->addScript("ShowBox('Ошибка', 'Плохой тип комментария.', 'red');");
		return $objResponse;
	}

	$ctext = function_exists('sb_sanitize_comment_text') ? sb_sanitize_comment_text($ctext) : trim(strip_tags((string)$ctext));
	if ($ctext === '') {
		$objResponse->addScript("ShowBox('Ошибка', 'Комментарий пустой.', 'red');");
		return $objResponse;
	}

	$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_comments(bid,type,aid,commenttxt,added) VALUES (?,?,?,?,UNIX_TIMESTAMP())");
	$GLOBALS['db']->Execute($pre,array($bid,
									   $ctype,
									   $userbank->GetAid(),
									   $ctext));

	$objResponse->addScript("ShowBox('Комментарий добавлен', 'Комментарий успешно опубликован', 'green', 'index.php$redir');");
	$objResponse->addScript("TabToReload();");
	$log = new CSystemLog("m", "Комментарий добавлен", $username." добавил комментарий к бану №".$bid);
	return $objResponse;
}

function EditComment($cid, $ctype, $ctext, $page)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->is_admin())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался редактировать комментарий, не имея на это прав.");
		return $objResponse;
	}

	$cid = (int)$cid;
	$page = (int)$page;
	
	$pagelink = "";
	if($page != -1)
		$pagelink = "&page=".$page;
	
	if($ctype=="B")
		$redir = "?p=banlist".$pagelink;
	elseif($ctype=="C")
		$redir = "?p=commslist".$pagelink;
	elseif($ctype=="S")
		$redir = "?p=admin&c=bans#^2";
	elseif($ctype=="P")
		$redir = "?p=admin&c=bans#^1";
	else
	{
		$objResponse->addScript("ShowBox('Ошибка', 'Плохой тип комментария.', 'red');");
		return $objResponse;
	}

	$ctext = function_exists('sb_sanitize_comment_text') ? sb_sanitize_comment_text($ctext) : trim(strip_tags((string)$ctext));
	if ($ctext === '') {
		$objResponse->addScript("ShowBox('Ошибка', 'Комментарий пустой.', 'red');");
		return $objResponse;
	}

	$pre = $GLOBALS['db']->Prepare("UPDATE ".DB_PREFIX."_comments SET `commenttxt` = ?, `editaid` = ?, `edittime`= UNIX_TIMESTAMP() WHERE cid = ?");
	$GLOBALS['db']->Execute($pre,array($ctext,
									   $userbank->GetAid(),
									   $cid));

	$objResponse->addScript("ShowBox('Комментарий отредактирован', 'Комментарий №".$cid." успешно отредактирован', 'green', 'index.php$redir');");
	$objResponse->addScript("TabToReload();");
	$log = new CSystemLog("m", "Комментарий отредактирован", $username." отредактировал комментарий №".$cid);
	return $objResponse;
}

function RemoveComment($cid, $ctype, $page)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if (!$userbank->HasAccess(ADMIN_OWNER))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался удалить комментарий, не имея на это прав.");
		return $objResponse;
	}

	$cid = (int)$cid;
	$page = (int)$page;
	
	$pagelink = "";
	if($page != -1)
		$pagelink = "&page=".$page;

	$res = $GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_comments` WHERE `cid` = ?",
								array( $cid ));
	if($ctype=="B")
		$redir = "?p=banlist".$pagelink;
	elseif($ctype=="C")
		$redir = "?p=commslist".$pagelink;
	else
		$redir = "?p=admin&c=bans";
	if($res)
	{
		$objResponse->addScript("ShowBox('Комментарий удалён', 'Комментарий был успешно удалён из базы данных', 'green', 'index.php$redir', true);");
		$log = new CSystemLog("m", "Комментарий удален", $username." удалил комментарий №".$cid);
	}
	else
		$objResponse->addScript("ShowBox('Ошибка', 'Ошибка удаления комментария из базы данных. Смотрите лог для дополнительной информации', 'red', 'index.php$redir', true);");
	return $objResponse;
}

function Maintenance($type) {
    global $userbank, $username, $theme;
    
    $objResponse = new xajaxResponse();
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS)) {
        ShowBox_ajx("Ошибка", "Вы не имеете прав для выполнения данного действия!", "red", $objResponse, "", true);
        new CSystemLog("w", "Ошибка доступа", $username . " пытался произвести операцию по обслуживанию системы, не имея на это прав.");
        return $objResponse;
    }
    
    switch($type) {
        case "themecache": {
            $theme->clear_compiled_tpl();
            ShowBox_ajx("Успех", "Кеш шаблона очищен успешно.", "green", $objResponse, "", true);
            break;
        }
        
        case "avatarcache": {
            $GLOBALS['db']->Execute(sprintf("TRUNCATE `%s_avatars`", DB_PREFIX));
            ShowBox_ajx("Успех", "Кеш аватарок очищен успешно.", "green", $objResponse, "", true);
            break;
        }
        
        case "bansexpired": {
            $GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_bans` WHERE `RemoveType` IS NOT NULL", DB_PREFIX));
            ShowBox_ajx("Успех", "Истёкшие баны удалены успешно.", "green", $objResponse, "", true);
            break;
        }
        
        case "commsexpired": {
            $GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_comms` WHERE `RemoveType` IS NOT NULL", DB_PREFIX));
            ShowBox_ajx("Успех", "Истёкшие муты удалены успешно.", "green", $objResponse, "", true);
            break;
        }

        case "adminsexpired": {
            // БАГ-ФИКС: в выпадающем списке "Обслуживание системы" (page_admin_settings_settings.tpl)
            // есть пункт adminsexpired, дёргающий xajax_Maintenance('adminsexpired'), но для этого
            // значения не было case - срабатывал default ("Неизвестная операция"). Используем ту же
            // логику удаления, что и в отдельной функции removeExpiredAdmins().
            $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_admins` WHERE `expired` < " . time() . " AND `expired` <> 0");
            ShowBox_ajx("Успех", "Все истёкшие администраторы удалены успешно.", "green", $objResponse, "", true);
            break;
        }

        case "optimizebd": {
            // Раньше: SHOW TABLES по всей схеме → OPTIMIZE и hlstats_* (миллионы строк).
            //
            // Почему Apache «сдыхал» при CPU 0 / MariaDB 0:
            // 1) OPTIMIZE TABLE берёт exclusive metadata lock. Пока ждёт (или пока
            //    идёт rebuild), новые SELECT/INSERT к этой таблице встают в очередь MDL.
            // 2) HLStats-демон постоянно пишет в event-таблицы → OPTIMIZE может ждать
            //    lock почти вечно (lock_wait_timeout по умолчанию в MariaDB = 1 ГОД).
            // 3) PHP max_execution_time / set_time_limit НЕ прерывают блокирующий
            //    mysqli_query() — воркер Apache спит в syscall, CPU=0.
            // 4) Все свободные воркеры заполняются такими же ожидающими запросами →
            //    сайт мёртв, пока не убьёшь httpd/php или запрос в MariaDB.
            //
            // Фикс: только таблицы SourceBans + короткий lock wait + лимит statement.
            if (function_exists('session_write_close'))
                @session_write_close();
            @set_time_limit(300);

            // Не висеть год на MDL; на MariaDB ещё режем долгий statement.
            @$GLOBALS['db']->Execute("SET SESSION lock_wait_timeout = 10");
            @$GLOBALS['db']->Execute("SET SESSION max_statement_time = 120");

            $prefix = DB_PREFIX . '_';
            $prefixLen = strlen($prefix);
            $optimized = 0;
            $failed = 0;
            $isMaria = (!empty($GLOBALS['db_version']) && stripos($GLOBALS['db_version'], 'mariadb') !== false);
            $tables = $GLOBALS['db']->GetAll("SHOW TABLE STATUS");
            if (is_array($tables)) {
                foreach ($tables as $table) {
                    $name = isset($table['Name']) ? $table['Name'] : (isset($table[0]) ? $table[0] : '');
                    if ($name === '' || strncmp($name, $prefix, $prefixLen) !== 0)
                        continue;
                    // VIEW / системные объекты без движка не трогаем
                    if (isset($table['Engine']) && ($table['Engine'] === null || $table['Engine'] === ''))
                        continue;
                    $safe = str_replace('`', '``', $name);
                    // WAIT 10 — синтаксис MariaDB 10.3+: не ждать lock дольше 10с
                    $sql = $isMaria
                        ? sprintf("OPTIMIZE TABLE `%s` WAIT 10", $safe)
                        : sprintf("OPTIMIZE TABLE `%s`", $safe);
                    $ok = $GLOBALS['db']->Execute($sql);
                    if ($ok === false)
                        $failed++;
                    else
                        $optimized++;
                }
            }

            $msg = "Оптимизация таблиц SourceBans завершена.<br />Успешно: <b>" . (int)$optimized . "</b>";
            if ($failed > 0)
                $msg .= ", пропущено/ошибка (lock/timeout): <b>" . (int)$failed . "</b>";
            $msg .= " (префикс <code>" . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . "*</code>). HLStats и прочие таблицы не затрагивались.";
            ShowBox_ajx("Успех", $msg, "green", $objResponse, "", true);
            break;
        }
        
        case "cleancountrycache": {
            $GLOBALS['db']->Execute("UPDATE `sb_bans` SET `country` = NULL;");
            ShowBox_ajx("Успех", "Кеш стран банлиста очищен успешно.<br /><br /><span style=\"color: #f00;\">Внимание!</span> Это может отрицательно сказаться на первой загрузке каждой страницы Вашего банлиста. Рекомендуем произвести операцию \"Обновить кеш стран в банлисте\".", "green", $objResponse, "", true);
            break;
        }
        
        case "rehashcountries": {
            $bans = $GLOBALS['db']->GetAll("SELECT `bid`, `ip` FROM `" . DB_PREFIX . "_bans` WHERE `country` IS NULL or `country` = 'zz'");
            foreach ($bans as $ban) {
                $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_bans` SET `country` = " . $GLOBALS['db']->qstr(FetchIp($ban['ip'])) . " WHERE `bid` = " . (int)$ban['bid'] . ";");
            }
            
            ShowBox_ajx("Успех", "Операция обновлений стран в кеше завершена.", "green", $objResponse, "", true);
            break;
        }
        
        case "updatecountries": {
            // БАГ-ФИКС: старый источник http://software77.net/geo-ip/ полностью прекратил
            // работу (сайт не существует уже несколько лет). file_get_contents() возвращал
            // false, zlib_decode(false) тоже false, а file_put_contents(...,false) молча
            // затирал рабочий файл базы GeoIP пустым содержимым - именно поэтому обновление
            // "не выполнялось" и определение страны переставало работать вообще (FetchIp()
            // после этого всегда возвращал "zz"). Переключаемся на актуально поддерживаемый,
            // ежедневно обновляемый источник (проект ip-location-db, раздаётся через CDN
            // jsDelivr по HTTPS) и добавляем проверку содержимого перед перезаписью файла,
            // чтобы недоступность источника больше никогда не портила рабочую базу.
            $CountryFile = INCLUDES_PATH . '/IpToCountry.csv';
            $canWrite = file_exists($CountryFile) ? @is_writable($CountryFile) : @is_writable(dirname($CountryFile));
            if (!$canWrite) {
                ShowBox_ajx("Ошибка", "Невозможно произвести обновление GeoIP базы: запись в файл <em>/includes/IpToCountry.csv</em> запрещена. Установите права <b>777</b> на файл (или папку) <em>/includes/</em>", "red", $objResponse, "", true);
                break;
            }

            $sourceUrl = "https://cdn.jsdelivr.net/npm/@ip-location-db/geo-whois-asn-country/geo-whois-asn-country-ipv4-num.csv";
            $context = @stream_context_create(array(
                'http' => array(
                    'method'  => 'GET',
                    'header'  => "User-Agent: SourceBans-GeoIP-Updater/1.0\r\n",
                    'timeout' => 30,
                ),
            ));
            $data = @file_get_contents($sourceUrl, false, $context);

            // На случай, если источник когда-либо начнёт отдавать gzip - определяем по
            // магическим байтам и распаковываем, вместо того чтобы жёстко требовать сжатие.
            if ($data !== false && substr($data, 0, 2) === "\x1f\x8b" && function_exists('gzdecode')) {
                $decoded = @gzdecode($data);
                if ($decoded !== false)
                    $data = $decoded;
            }

            // Формат новой базы: "ip_range_start,ip_range_end,country_code" (числа - десятичное
            // представление IPv4). Проверяем, что скачалось что-то похожее на настоящую базу,
            // ПЕРЕД тем как трогать существующий рабочий файл.
            $looksValid = $data !== false
                && strlen($data) > 100000
                && preg_match('/^\d{1,10},\d{1,10},[A-Z]{2}\b/m', $data);

            if (!$looksValid) {
                ShowBox_ajx("Ошибка", "Не удалось загрузить актуальную базу GeoIP с сервера-источника (сервер недоступен или вернул некорректные данные). Текущий файл базы НЕ изменён.", "red", $objResponse, "", true);
                break;
            }

            // Атомарная запись через временный файл и rename() - чтобы обрыв соединения или
            // нехватка места на диске посередине записи не оставили файл базы в битом
            // /пустом состоянии, как это уже произошло со старым кодом.
            $tmpFile = $CountryFile . '.tmp';
            if (@file_put_contents($tmpFile, $data) === false || !@rename($tmpFile, $CountryFile)) {
                @unlink($tmpFile);
                ShowBox_ajx("Ошибка", "Невозможно произвести обновление GeoIP базы: не удалось записать файл <em>/includes/IpToCountry.csv</em>.", "red", $objResponse, "", true);
                break;
            }

            ShowBox_ajx("Успех", "Файл GeoIP базы обновлён.", "green", $objResponse, "", true);
            break;
        }
        
        case "warningsexpired": {
            $GLOBALS['db']->Execute(sprintf("DELETE FROM `%s_warns` WHERE `expires` < %d", DB_PREFIX, time()));
            ShowBox_ajx("Успех", "Все истёкшие и снятые предупреждения были успешно удалены.", "green", $objResponse, "", true);
            break;
        }
        
        case "avatarupdate": {
            Maintenance("avatarcache");
            $users = $GLOBALS['db']->GetAll(sprintf("SELECT `authid` FROM `%s_admins`", DB_PREFIX));
            foreach ($users as &$user)
                GetUserAvatar($user['authid']);
            ShowBox_ajx("Успех", "Кеш аватаров Администраторов обновлён.", "green", $objResponse, "", true);
            break;
        }
        
        case "commentsclean": {
            $GLOBALS['db']->Execute(sprintf("TRUNCATE `%s_comments`;", DB_PREFIX));
            ShowBox_ajx("Успех", "Все комментарии были успешно удалены.", "green", $objResponse, "", true);
            break;
        }
        
        case "banlogclean": {
            $GLOBALS['db']->Execute(sprintf("TRUNCATE `%s_banlog`;", DB_PREFIX));
            ShowBox_ajx("Успех", "История заблокированных соединений к серверам успешно очищена.", "green", $objResponse, "", true);
            break;
        }
        
        case "protests": {
            $GLOBALS['db']->Execute(sprintf("TRUNCATE `%s_protests`;", DB_PREFIX));
            ShowBox_ajx("Успех", "Протесты успешно удалены.", "green", $objResponse, "", true);
            break;
        }
        
        case "reports": {
            $GLOBALS['db']->Execute(sprintf("TRUNCATE `%s_submissions`;", DB_PREFIX));
            ShowBox_ajx("Успех", "Предложения бана (репорты) успешно удалены.", "green", $objResponse, "", true);
            break;
        }

        case "demosclean": {
            // Очистка каталога demos/ (демки и медиафайлы, приложенные к банам/заявкам через
            // admin.uploaddemo.php и page.submit.php) + связанных записей в БД. Раньше эти
            // файлы никогда не подчищались централизованно - только частично при удалении
            // конкретного бана, из-за чего каталог demos/ бесконтрольно рос годами.
            $removed = 0;
            $freed = 0;
            if (is_dir(SB_DEMOS) && ($dh = @opendir(SB_DEMOS))) {
                while (($file = readdir($dh)) !== false) {
                    // .htaccess внутри demos/ запрещает выполнение PHP - его не трогаем.
                    if ($file === '.' || $file === '..' || $file === '.htaccess')
                        continue;
                    $path = SB_DEMOS . '/' . $file;
                    if (is_file($path)) {
                        $freed += (int)@filesize($path);
                        if (@unlink($path))
                            $removed++;
                    }
                }
                closedir($dh);
            }

            $GLOBALS['db']->Execute(sprintf("TRUNCATE `%s_demos`;", DB_PREFIX));

            new CSystemLog("m", "Обслуживание системы", $username . " очистил все демки и медиафайлы пользователей (удалено файлов: " . $removed . ", освобождено: " . sizeFormat($freed) . ").");
            ShowBox_ajx("Успех", "Удалено файлов: <b>" . $removed . "</b>, освобождено места: <b>" . sizeFormat($freed) . "</b>. Записи о демо/медиафайлах в базе очищены.", "green", $objResponse, "", true);
            break;
        }
        
        default: {
            ShowBox_ajx("Ошибка", "Неизвестная операция", "red", $objResponse, "", true);
            break;
        }
    }
    
    return $objResponse;
}

function RefreshServer($sid)
{
	$objResponse = new xajaxResponse();
	$sid = (int)$sid;
	if (function_exists('sb_session_start')) {
		sb_session_start();
	} else {
		session_start();
	}
	$data = $GLOBALS['db']->GetRow("SELECT ip, port FROM `".DB_PREFIX."_servers` WHERE sid = ?;", array($sid));
	if (isset($_SESSION['getInfo.' . $data['ip'] . '.' . $data['port']]) && is_array($_SESSION['getInfo.' . $data['ip'] . '.' . $data['port']]))
		unset($_SESSION['getInfo.' . $data['ip'] . '.' . $data['port']]);
	$objResponse->addScript("xajax_ServerHostPlayers('".$sid."');");
	return $objResponse;
}

function RehashAdmins_pay($server, $card, $do=0)
{
	if (function_exists('sb_rate_limit_hit') && sb_rate_limit_hit('vay4er_rehash', 10, 900)) {
		exit();
	}
	$card = function_exists('sb_voucher_normalize_key')
		? sb_voucher_normalize_key($card)
		: strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$card));

	// Ваучер к моменту rehash уже activ=0. Разрешение — только сессионный токен
	// сразу после успешной AddAdmin_pay (нельзя дергать RCON чужим ключом).
	if ($card === '' || !function_exists('sb_voucher_rehash_check') || !sb_voucher_rehash_check($card)) {
		exit();
	}
	$wfr = $GLOBALS['db']->GetRow("SELECT `value` FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?", array($card));
	if (!$wfr) {
		exit();
	}
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	$do = (int)$do;

	$servers = explode(",",$server);
	if(sizeof($servers)>0) {
		if(sizeof($servers)-1 > $do)
			$objResponse->addScriptCall("xajax_RehashAdmins_pay", $server, $card, $do+1);
		else if (function_exists('sb_voucher_rehash_clear'))
			sb_voucher_rehash_clear();

		$serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = ?", array((int)$servers[$do]));
		if(empty($serv['rcon'])) {
			$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='red'>Ошибка: не задан РКОН пароль</font>.<br />");
			if($do >= sizeof($servers)-1) {
				$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
				$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
				$objResponse->addScript("setTimeout(\"window.location = 'index.php';\", 1800);");
			}
			return $objResponse;
		}

		$test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
		if(!$test) {
			$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='red'>Ошибка: нет соединения</font>.<br />");
			if($do >= sizeof($servers)-1) {
				$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
				$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
				$objResponse->addScript("setTimeout(\"window.location = 'index.php';\", 1800);");
			}
			return $objResponse;
		}

		require INCLUDES_PATH.'/CServerControl.php';
		
		$r = new CServerControl();
		$r->Connect($serv['ip'], $serv['port']);
		
		if(!$r->AuthRcon($serv['rcon']))
		{
			$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".$serv['sid']."';");
			$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='red'>Ошибка: неверный РКОН пароль</font>.<br />");
			if($do >= sizeof($servers)-1) {
				$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
				$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
				$objResponse->addScript("setTimeout(\"window.location = 'index.php';\", 1800);");
			}
			return $objResponse;
		}

		if ($GLOBALS['config']['feature.old_serverside'] == "1") {
			$r->SendCommand("sm_rehash");
			$r->SendCommand("sm_reloadadmins");
		} else
			$r->SendCommand("ma_wb_rehashadm");

		$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='green'>успешно</font>.<br />");
		if($do >= sizeof($servers)-1) {
			$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
			$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
			$objResponse->addScript("setTimeout(\"window.location = 'index.php';\", 1800);");
		}
	} else {
		$objResponse->addAppend("rehashDiv", "innerHTML", "Не выбран сервер.");
		$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
	}
	return $objResponse;
}

function RehashAdmins($server, $do=0)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	$do = (int)$do;
	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS|ADMIN_EDIT_GROUPS|ADMIN_ADD_ADMINS))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался обновить админов, не имея на это прав.");
		return $objResponse;
	}
	$servers = explode(",",$server);
	if(sizeof($servers)>0) {
		if(sizeof($servers)-1 > $do)
			$objResponse->addScriptCall("xajax_RehashAdmins", $server, $do+1);

		$serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".(int)$servers[$do]."';");
		if(empty($serv['rcon'])) {
			$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='red'>Ошибка: не задан РКОН пароль</font>.<br />");
			if($do >= sizeof($servers)-1) {
				$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
				$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
				$objResponse->addScript("setTimeout(\"window.location = 'index.php?p=admin&c=admins';\", 1800);");
			}
			return $objResponse;
		}

		$test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
		if(!$test) {
			$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='red'>Ошибка: нет соединения</font>.<br />");
			if($do >= sizeof($servers)-1) {
				$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
				$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
				$objResponse->addScript("setTimeout(\"window.location = 'index.php?p=admin&c=admins';\", 1800);");
			}
			return $objResponse;
		}

		require INCLUDES_PATH.'/CServerControl.php';
		
		$r = new CServerControl();
		$r->Connect($serv['ip'], $serv['port']);
		
		if(!$r->AuthRcon($serv['rcon']))
		{
			$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".$serv['sid']."';");
			$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='red'>Ошибка: неверный РКОН пароль</font>.<br />");
			if($do >= sizeof($servers)-1) {
				$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
				$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
				$objResponse->addScript("setTimeout(\"window.location = 'index.php?p=admin&c=admins';\", 1800);");
			}
			return $objResponse;
		}

		if ($GLOBALS['config']['feature.old_serverside'] == "1") {
			$r->SendCommand("sm_rehash");
			$r->SendCommand("sm_reloadadmins");
		} else
			$r->SendCommand("ma_wb_rehashadm");

		$objResponse->addAppend("rehashDiv", "innerHTML", "".$serv['ip'].":".$serv['port']." (".($do+1)."/".sizeof($servers).") <font color='green'>успешно</font>.<br />");
		if($do >= sizeof($servers)-1) {
			$objResponse->addAppend("rehashDiv", "innerHTML", "<b>Выполнено, переадресация....</b>");
			$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
			$objResponse->addScript("setTimeout(\"window.location = 'index.php?p=admin&c=admins';\", 1800);");
		}
	} else {
		$objResponse->addAppend("rehashDiv", "innerHTML", "Не выбран сервер.");
		$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
	}
	return $objResponse;
}

function GroupBan($groupuri, $isgrpurl="no", $queue="no", $reason="", $last="")
{
	$objResponse = new xajaxResponse();
	
	// Проверки доступа и конфигурации
	if(!$GLOBALS['config']['config.enablegroupbanning'] || !$GLOBALS['userbank']->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
		if(!$GLOBALS['config']['config.enablegroupbanning']) return $objResponse;
		
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		new CSystemLog("w", "Ошибка доступа", $GLOBALS['username'] . " пытался забанить группу '".htmlspecialchars(addslashes(trim($groupuri)))."', не имея на это прав.");
		return $objResponse;
	}
	
	// Извлечение имени группы (urldecode нужен для кириллических/Unicode URL вида %D0%A0%D1%83...)
	$grpname = ($isgrpurl=="yes") ? $groupuri : urldecode(basename(parse_url($groupuri, PHP_URL_PATH)));
	// Убираем трейлинг-слеш на случай https://steamcommunity.com/groups/name/
	$grpname = rtrim($grpname, '/');
	
	if(empty($grpname)) {
		$objResponse->addAssign("groupurl.msg", "innerHTML", "Ошибка преобразования URL группы.");
		$objResponse->addScript("$('groupurl.msg').setStyle('display', 'block');");
		return $objResponse;
	}
	
	$objResponse->addScript("$('groupurl.msg').setStyle('display', 'none'); $('dialog-control').setStyle('display', 'none');");
	
	// Создание прогресс-бара (deep-blue оформление, без изменения логики)
	$objResponse->addScript("
		if (!document.getElementById('ban_ui_styles')) {
			var styleTag = document.createElement('style');
			styleTag.id = 'ban_ui_styles';
			styleTag.textContent =
				'#ban_progress_overlay{position:fixed;inset:0;background:rgba(3,8,18,.72);backdrop-filter:blur(2px);z-index:9998;}' +
				'#ban_progress{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;max-width:92vw;background:#0c1528;border:1px solid #1a2d50;border-radius:12px;padding:18px 18px 14px;box-shadow:0 14px 34px rgba(0,0,0,.48);color:#ddeeff;font-family:Arial,sans-serif;z-index:9999;}' +
				'#ban_progress .ban-title{font-size:15px;font-weight:700;color:#e8f0ff;margin:0 0 10px;}' +
				'#ban_progress .ban-sub{font-size:13px;color:#8eb4dc;margin:0 0 8px;line-height:1.45;}' +
				'#ban_progress .ban-bar-wrap{width:100%;height:14px;background:#0a1225;border:1px solid #1a2d50;border-radius:999px;overflow:hidden;margin:10px 0 8px;}' +
				'#ban_progress .ban-bar{width:0%;height:100%;background:linear-gradient(90deg,#1e90ff 0%,#4ea8ff 65%,#7cc1ff 100%);transition:width .18s ease;}' +
				'#ban_progress .ban-status{display:flex;justify-content:space-between;gap:10px;font-size:12px;color:#7ea8d4;}' +
				'#ban_result_overlay{position:fixed;inset:0;background:rgba(3,8,18,.72);backdrop-filter:blur(2px);z-index:9999;}' +
				'#ban_result{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:560px;max-width:94vw;background:#0c1528;border:1px solid #1a2d50;border-radius:14px;box-shadow:0 16px 40px rgba(0,0,0,.55);color:#ddeeff;padding:20px;z-index:10000;font-family:Arial,sans-serif;}' +
				'#ban_result .br-title{font-size:20px;font-weight:700;color:#bfe0ff;margin:0 0 8px;text-align:center;}' +
				'#ban_result .br-group{font-size:13px;color:#8eb4dc;background:#0f1f38;border:1px solid #1a2d50;padding:8px 10px;border-radius:8px;text-align:center;margin-bottom:14px;}' +
				'#ban_result .br-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px;}' +
				'#ban_result .br-card{background:#0f1f38;border:1px solid #1a2d50;border-radius:10px;padding:12px;text-align:center;}' +
				'#ban_result .br-num{font-size:24px;font-weight:700;color:#dcecff;line-height:1.1;}' +
				'#ban_result .br-lbl{font-size:12px;color:#7ea8d4;margin-top:4px;}' +
				'#ban_result .br-time{background:#0f1f38;border:1px solid #1a2d50;border-radius:8px;padding:10px;text-align:center;color:#8eb4dc;font-size:13px;margin-bottom:14px;}' +
				'#ban_result .br-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}' +
				'#ban_result .br-btn{border:1px solid #1a4a7a;background:#16314a;color:#dcecff;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;}' +
				'#ban_result .br-btn:hover{background:#1d3f60;color:#ecf5ff;}';
			document.head.appendChild(styleTag);
		}

		if (!document.getElementById('ban_progress_overlay')) {
			var overlayDiv = document.createElement('div');
			overlayDiv.id = 'ban_progress_overlay';
			document.body.appendChild(overlayDiv);
		}

		if (!document.getElementById('ban_progress')) {
			var progressDiv = document.createElement('div');
			progressDiv.id = 'ban_progress';
			progressDiv.innerHTML =
				'<div class=\"ban-title\">Блокировка участников группы</div>' +
				'<div id=\"ban_progress_group\" class=\"ban-sub\">Подготовка...</div>' +
				'<div id=\"ban_progress_meta\" class=\"ban-sub\">Инициализация...</div>' +
				'<div class=\"ban-bar-wrap\"><div id=\"ban_progress_bar\" class=\"ban-bar\"></div></div>' +
				'<div class=\"ban-status\"><span id=\"ban_progress_err\">Ошибок: 0</span><span id=\"ban_progress_pct\">0%</span></div>';
			document.body.appendChild(progressDiv);
		}
	");
	
	// Инициализация состояния.
	// page_members хранит только ТЕКУЩУЮ страницу Steam (не всё сразу).
	// Для группы 2500+ уч. loadGroupMembers делал 50 file_get_contents подряд —
	// веб-сервер убивал запрос по таймауту -> HTTP 500.
	// Теперь каждый xajax-вызов делает максимум 1 HTTP-запрос к Steam.
	$_SESSION['group_ban_state'] = [
	    'grpname'         => $grpname,
	    'queue'           => $queue,
	    'reason'          => $reason,
	    'last'            => $last,
	    'steam_page'      => 1,
	    'page_members'    => [],
	    'page_offset'     => 0,
	    'processed_count' => 0,
	    'error_count'     => 0,
	    'start_time'      => time(),
	    'banned_steamids' => [],
	    'cache_loaded'    => false,
	];
	
	$objResponse->addScriptCall("xajax_BanMemberOfGroup");
	return $objResponse;
}

function BanMemberOfGroup()
{
	set_time_limit(30); // Достаточно для 1 HTTP-запроса к Steam + 1 INSERT
	$objResponse = new xajaxResponse();

	if (!$GLOBALS['config']['config.enablegroupbanning'] || !isset($_SESSION['group_ban_state'])) {
		$objResponse->addScript("
			if (document.getElementById('ban_progress')) document.getElementById('ban_progress').remove();
			if (document.getElementById('ban_progress_overlay')) document.getElementById('ban_progress_overlay').remove();
			ShowBox('Ошибка', 'Процесс бана группы не инициализирован или завершён.', 'red', '', true);
		");
		return $objResponse;
	}

	// Проверка доступа
	if (!$GLOBALS['userbank']->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		new CSystemLog("w", "Ошибка доступа", $GLOBALS['username'] . " пытался забанить группу '".$_SESSION['group_ban_state']['grpname']."', не имея на это прав.");
		unset($_SESSION['group_ban_state']);
		return $objResponse;
	}

	$state = &$_SESSION['group_ban_state'];

	// Один раз грузим кэш уже забаненных из БД (не HTTP — быстро)
	if (!$state['cache_loaded']) {
		$state['banned_steamids'] = getBannedSteamIds();
		$state['cache_loaded'] = true;
	}

	// Если текущая страница закончилась — грузим следующую страницу Steam.
	// Один file_get_contents per xajax-вызов — в таймаут не попадаем.
	if ($state['page_offset'] >= count($state['page_members'])) {
		$new_members = loadGroupPage($state['grpname'], $state['steam_page']);

		if (empty($new_members)) {
			// Страниц больше нет — завершаем
			finishBanning($objResponse, $state);
			unset($_SESSION['group_ban_state']);
			return $objResponse;
		}

		$state['page_members'] = $new_members;
		$state['page_offset']  = 0;
		$state['steam_page']++;
	}

	// Обрабатываем одного участника с текущей позиции
	$member = $state['page_members'][$state['page_offset']];
	if (!processMember($member, $state)) {
		$state['error_count']++;
	}
	$state['page_offset']++;
	$state['processed_count']++;

	// Обновляем прогресс и запускаем следующую итерацию
	updateProgress($objResponse, $state);
	$objResponse->addScriptCall("setTimeout", "xajax_BanMemberOfGroup()", 25);

	return $objResponse;
}

// Вспомогательные функции для оптимизации

// Загружает ОДНУ страницу участников группы Steam.
// Вызывается по одному разу за xajax-запрос — не блокирует веб-сервер.
function loadGroupPage($grpname, $page) {
	// User-Agent обязателен: без него Steam отдаёт 403
	$ctx = stream_context_create([
		'http' => [
			'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'timeout'    => 15,
		],
	]);

	$page_url = "https://steamcommunity.com/groups/" . rawurlencode($grpname) . "/members" . ($page > 1 ? "?p={$page}" : "");
	$raw = @file_get_contents($page_url, false, $ctx);
	if (!$raw) return [];

	$doc = new DOMDocument();
	// Принудительно UTF-8 — иначе DOMDocument ломает кириллику/китайский
	@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $raw);

	$members = [];
	foreach ($doc->getElementsByTagName('a') as $tag) {
		$href = $tag->getAttribute('href');
		if ((strpos($href, 'https://steamcommunity.com/id/') === 0
			|| strpos($href, 'https://steamcommunity.com/profiles/') === 0)
			&& $tag->hasChildNodes()
			&& $tag->childNodes->length == 1
			&& $tag->childNodes->item(0)->nodeValue != ""
		) {
			$members[] = [
				'href' => $href,
				'name' => $tag->childNodes->item(0)->nodeValue,
			];
		}
	}

	return $members;
}

function getBannedSteamIds() {
	// Только SteamID-баны (type=0), LIKE фильтрует IP-баны с пустым authid.
	// CAST AS CHAR нужен чтобы PHP получал строки, а не int64 — иначе in_array() ломается на больших числах.
	$bans = $GLOBALS['db']->GetAll(
		"SELECT CAST(CAST(MID(authid,9,1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(authid,11,10) AS UNSIGNED) * 2 AS CHAR) AS community_id " .
		"FROM " . DB_PREFIX . "_bans " .
		"WHERE RemoveType IS NULL AND type = 0 AND authid LIKE 'STEAM\\_%'"
	);
	return array_column($bans, 'community_id');
}

function processMember($member, &$state) {
    $url_parts = explode("/", parse_url($member['href'], PHP_URL_PATH));
    $profile_id = $url_parts[2];
    
    $steamid = null;
    $community_id = null;
    
    if(strpos($member['href'], 'https://steamcommunity.com/id/') === 0) {
        // Custom ID - получаем friend ID
        $friend_id = GetFriendIDFromCommunityID($profile_id);
        if(!$friend_id) return false;
        
        $community_id = $friend_id; // Для custom ID community_id = friend_id
        $steamid = FriendIDToSteamID($friend_id);
    } else {
        // Обычный friend ID
        $community_id = $profile_id;
        $steamid = FriendIDToSteamID($profile_id);
    }
    
    if (!$steamid) return false;

    // Проверяем, не забанен ли уже (строковое сравнение — MySQL может вернуть int или string)
    if (in_array((string)$community_id, $state['banned_steamids'])) {
        return true; // Уже забанен — считаем успехом
    }

    // Дополнительная проверка в базе данных (на случай если кэш устарел)
    $existing_ban = $GLOBALS['db']->GetRow("SELECT bid FROM ".DB_PREFIX."_bans WHERE authid = ? AND RemoveType IS NULL AND type = 0", [$steamid]);
    if ($existing_ban) {
        // Добавляем в кэш для будущих проверок
        $state['banned_steamids'][] = (string)$community_id;
        return true;
    }

    // Защита: не баним собственных администраторов веб-панели
    $admin_check = $GLOBALS['db']->GetRow("SELECT aid FROM ".DB_PREFIX."_admins WHERE authid = ? LIMIT 1", [$steamid]);
    if ($admin_check) {
        return true; // Пропускаем молча (считаем "успехом" чтобы не ломать счётчик)
    }

    // Выполняем бан
    $pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,type,ip,authid,name,ends,length,reason,aid,adminIp) VALUES (UNIX_TIMESTAMP(),?,?,?,?,UNIX_TIMESTAMP(),?,?,?,?)");

    // utf8_encode() НЕЛЬЗЯ использовать — оно ломает UTF-8 строки из DOMDocument (двойное кодирование).
    // sanitizeForMysql() убирает 4-байтовые символы (эмодзи и спец.символы), которые не влезают в utf8 (не utf8mb4).
    $name = sanitizeForMysql($member['name']);
    // Дополнительно: если после очистки строка всё ещё битая — fallback
    if (!mb_check_encoding($name, 'UTF-8') || $name === '') {
        $name = 'UNKNOWN NICKNAME';
    }

    $reason = "Steam Community Group Ban (" . $state['grpname'] . ") " . $state['reason'];

    $success = $GLOBALS['db']->Execute($pre, [0, "", $steamid, $name, 0, $reason, $GLOBALS['userbank']->GetAid(), $_SERVER['REMOTE_ADDR']]);

    // Если всё равно ошибка кодировки (1366) — повторяем с нейтральным именем
    if (!$success && $GLOBALS['db']->ErrorNo() == 1366) {
        $success = $GLOBALS['db']->Execute($pre, [0, "", $steamid, "UNKNOWN NICKNAME", 0, $reason, $GLOBALS['userbank']->GetAid(), $_SERVER['REMOTE_ADDR']]);
    }

    // Если бан успешен, добавляем в кэш
    if ($success) {
        $state['banned_steamids'][] = (string)$community_id;
    }

    return $success;
}
function updateProgress($objResponse, $state) {
	// total_members неизвестен заранее (грузим постранично), показываем счётчик + страницу
	$grpname_js   = json_encode($state['grpname'], JSON_UNESCAPED_UNICODE);
	$steam_page   = (int)$state['steam_page'] - 1; // steam_page уже инкрементирован после загрузки
	$processed    = (int)$state['processed_count'];
	$error_count  = (int)$state['error_count'];

	$objResponse->addScript("
		var grpName = ".$grpname_js.";
		if (document.getElementById('ban_progress_group')) {
			document.getElementById('ban_progress_group').textContent = 'Группа: ' + grpName;
		}
		if (document.getElementById('ban_progress_meta')) {
			document.getElementById('ban_progress_meta').textContent = 'Обработано: {$processed} участников (стр. {$steam_page})';
		}
		if (document.getElementById('ban_progress_bar')) {
			// Анимируем полосу — пульсирует пока идёт обработка (total неизвестен)
			var pct = ({$processed} % 100);
			document.getElementById('ban_progress_bar').style.width = pct + '%';
		}
		if (document.getElementById('ban_progress_err')) {
			document.getElementById('ban_progress_err').textContent = 'Ошибок: {$error_count}';
		}
		if (document.getElementById('ban_progress_pct')) {
			document.getElementById('ban_progress_pct').textContent = '{$processed} участников';
		}
	");
}

function finishBanning($objResponse, $state) {
	$objResponse->addScript("
		if (document.getElementById('ban_progress')) document.getElementById('ban_progress').remove();
		if (document.getElementById('ban_progress_overlay')) document.getElementById('ban_progress_overlay').remove();
	");

	$total_processed = (int)$state['processed_count'];
	$banned_count    = $total_processed - (int)$state['error_count'];
	$elapsed_time    = time() - $state['start_time'];
	$time_str        = formatTime($elapsed_time);
	$grpname_js      = json_encode($state['grpname'], JSON_UNESCAPED_UNICODE);
	$time_str_js     = json_encode($time_str, JSON_UNESCAPED_UNICODE);

	// Создание итогового окна
	$objResponse->addScript("
		var grpName     = ".$grpname_js.";
		var timeStr     = ".$time_str_js.";
		var bannedCount = ".$banned_count.";
		var errorCount  = ".(int)$state['error_count'].";
		var totalCount  = ".$total_processed.";

		if (document.getElementById('ban_result_overlay')) document.getElementById('ban_result_overlay').remove();
		if (document.getElementById('ban_result')) document.getElementById('ban_result').remove();

		var overlay = document.createElement('div');
		overlay.id = 'ban_result_overlay';

		var resultDiv = document.createElement('div');
		resultDiv.id = 'ban_result';
		resultDiv.innerHTML =
			'<div class=\"br-title\">Группа успешно забанена</div>' +
			'<div class=\"br-group\">Группа: ' + grpName + '</div>' +
			'<div class=\"br-stats\">' +
				'<div class=\"br-card\"><div class=\"br-num\">' + bannedCount + '</div><div class=\"br-lbl\">Забанено</div></div>' +
				'<div class=\"br-card\"><div class=\"br-num\">' + errorCount + '</div><div class=\"br-lbl\">Ошибок</div></div>' +
				'<div class=\"br-card\"><div class=\"br-num\">' + totalCount + '</div><div class=\"br-lbl\">Обработано</div></div>' +
			'</div>' +
			'<div class=\"br-time\">Время выполнения: ' + timeStr + '</div>' +
			'<div class=\"br-actions\">' +
				'<button class=\"br-btn\" onclick=\"location.reload();\">Обновить страницу</button>' +
				'<button class=\"br-btn\" onclick=\"document.getElementById(\\'ban_result\\').remove(); document.getElementById(\\'ban_result_overlay\\').remove();\">Закрыть</button>' +
			'</div>';

		document.body.appendChild(overlay);
		document.body.appendChild(resultDiv);
	");

	// Обработка очереди и логирование
	if ($state['queue'] == "yes") {
		$objResponse->addScript("$('steamGroupStatus').setStyle('display', 'block');");
		$objResponse->addAppend("steamGroupStatus", "innerHTML", "<p>Забанено {$banned_count} из {$total_processed} участников группы '{$state['grpname']}'. <br/>Ошибок: {$state['error_count']}.</p>");

		if ($state['grpname'] == $state['last']) {
			$objResponse->addScript("setTimeout(function() { location.reload(); }, 8000);");
			$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
		}
	} else {
		$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
	}

	new CSystemLog("m", "Группа забанена", "Забанено {$banned_count} из {$total_processed} обработанных участников группы '{$state['grpname']}'.<br>Ошибок: {$state['error_count']}. Время: {$time_str}");
}

function formatTime($seconds) {
	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;
	return $minutes > 0 ? "{$minutes} мин {$seconds} сек" : "{$seconds} сек";
}

function sanitizeForMysql($string) {
    // Удаляем 4-байтовые UTF-8 символы (эмодзи и специальные символы)
    return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $string);
}

function hasProblematicChars($string) {
    // Проверяем наличие 4-байтовых UTF-8 символов (эмодзи, математические символы и т.д.)
    if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $string)) {
        return true;
    }
    // Проверяем другие проблемные символы
    if (preg_match('/[\x{1D400}-\x{1D7FF}]/u', $string)) { // Mathematical symbols
        return true;
    }
    // Проверяем символы которые могут вызвать проблемы с кодировкой
    if (!mb_check_encoding($string, 'UTF-8')) {
        return true;
    }
    return false;
}

function BanFriends($friendid, $name)
{
	set_time_limit(0);
	$objResponse = new xajaxResponse();
	if($GLOBALS['config']['config.enablefriendsbanning']==0 || !is_numeric($friendid))
		return $objResponse;
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		return $objResponse;
	}
	$bans = $GLOBALS['db']->GetAll(
		"SELECT CAST(CAST(MID(authid,9,1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(authid,11,10) AS UNSIGNED) * 2 AS CHAR) AS community_id " .
		"FROM " . DB_PREFIX . "_bans " .
		"WHERE RemoveType IS NULL AND type = 0 AND authid LIKE 'STEAM\\_%'"
	);
	$already = [];
	foreach($bans as $ban) {
		$already[] = (string)$ban["community_id"];
	}
	$doc = new DOMDocument();
	$ctx = stream_context_create([
		'http' => [
			'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'timeout'    => 15,
		],
	]);
	$result = get_headers("https://steamcommunity.com/profiles/".$friendid."/", 1);
	$raw = @file_get_contents(($result["Location"]!=""?$result["Location"]:"https://steamcommunity.com/profiles/".$friendid."/")."friends", false, $ctx);
	@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $raw);
	$divs = $doc->getElementsByTagName('div');
	$friends = array();
	foreach ($divs as $div) {
		$class = $div->getAttribute('class');
		if (strpos($class, 'friend_block_v2') !== false) {
			// Ссылка на профиль
			$profile_url = '';
			$links = $div->getElementsByTagName('a');
			foreach ($links as $a) {
				$href = $a->getAttribute('href');
				if (strpos($href, 'steamcommunity.com/profiles/') !== false || strpos($href, 'steamcommunity.com/id/') !== false) {
					$profile_url = $href;
					break;
				}
			}
			// Имя друга
			$name = '';
			$contentDivs = $div->getElementsByTagName('div');
			foreach ($contentDivs as $cdiv) {
				if ($cdiv->getAttribute('class') === 'friend_block_content') {
					$name = trim($cdiv->nodeValue);
					break;
				}
			}
			if ($profile_url) {
				$friends[] = array('url' => $profile_url, 'name' => $name);
			}
		}
	}

	$total = 0;
	$bannedbefore = 0;
	$error = 0;

	if (empty($friends)) {
		$objResponse->addScript("ShowBox('Ошибка выборки друзей', 'Не удалось найти друзей в профиле STEAM. Возможно, Steam изменил структуру страницы или у пользователя нет друзей.', 'red', 'index.php?p=banlist', true);");
		$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
		return $objResponse;
	}

	foreach ($friends as $friend) {
		$total++;
		if (empty($friend['url'])) {
			$error++;
			continue;
		}
		$url = parse_url($friend['url'], PHP_URL_PATH);
		$url = explode("/", $url);
		if (!isset($url[2])) {
			$error++;
			continue;
		}
		if (in_array((string)$url[2], $already)) {
			$bannedbefore++;
			continue;
		}
		if (strpos($friend['url'], "steamcommunity.com/id/") !== false) {
			// we don't have the friendid as this player is using a custom id :S need to get the friendid
			if ($tfriend = GetFriendIDFromCommunityID($url[2])) {
				if (in_array((string)$tfriend, $already)) {
					$bannedbefore++;
					continue;
				}
				$cust = $url[2];
				$steamid = FriendIDToSteamID($tfriend);
				$urltag = $tfriend;
			} else {
				$error++;
				continue;
			}
		} else {
			// just a normal friendid profile =)
			$cust = NULL;
			$steamid = FriendIDToSteamID($url[2]);
			$urltag = $url[2];
		}
		// Sanitize name: убираем 4-байтовые символы (эмодзи и т.п.) — не влезают в utf8 (не utf8mb4)
		// str_replace "&#13;" убирает CR-символы которые Steam иногда вставляет
		$friendName = sanitizeForMysql(trim(str_replace("&#13;", "", $friend['name'])));
		if (!mb_check_encoding($friendName, 'UTF-8') || $friendName === '') {
			$friendName = 'UNKNOWN NICKNAME';
		}

		$banReason = "Steam Community Friend Ban (" . $friendName . ")";

		$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,type,ip,authid,name,ends,length,reason,aid,adminIp) VALUES (UNIX_TIMESTAMP(),?,?,?,?,UNIX_TIMESTAMP(),?,?,?,?)");
		$success = $GLOBALS['db']->Execute($pre, array(0, "", $steamid, $friendName, 0, $banReason, $userbank->GetAid(), $_SERVER['REMOTE_ADDR']));

		// Fallback на случай если sanitizeForMysql не уберёг от 1366 (битые байты из DOMDocument)
		if (!$success && $GLOBALS['db']->ErrorNo() == 1366) {
			$GLOBALS['db']->Execute($pre, array(0, "", $steamid, 'UNKNOWN NICKNAME', 0, $banReason, $userbank->GetAid(), $_SERVER['REMOTE_ADDR']));
		}

	}

	if($total==0) {
		$objResponse->addScript("ShowBox('Ошибка выборки друзей', 'Ошибка выборки друзей из профиля STEAM. Возможно его профиль скрыт, или у него нет друзей!', 'red', 'index.php?p=banlist', true);");
		$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
		return $objResponse;
	}
	$objResponse->addScript("ShowBox('Друзья были забанены', 'Забанено ".($total-$bannedbefore-$error)." из ".$total." друзей у ".htmlspecialchars($name).".<br>".$bannedbefore." были забанены до этого.<br>И ".$error." ошибок.', 'green', 'index.php?p=banlist', true);");
	$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
	return $objResponse;
}

function ViewCommunityProfile($sid, $name)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	if(!$userbank->is_admin())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался посмотреть профиль '".htmlspecialchars($name)."', не имея на это прав.");
		return $objResponse;
	}
	$sid = (int)$sid;
	if(!sb_admin_has_server_access($sid))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался смотреть профиль через RCON на sid=$sid без доступа к серверу.");
		return $objResponse;
	}
  
	require INCLUDES_PATH.'/CServerControl.php';
	//get the server data
	$data = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = ?", array($sid));
	if(empty($data['rcon'])) {
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно получить информацию о игроке ".addslashes(htmlspecialchars($name)).". Не задан РКОН пароль!', 'red', '', true);");
		return $objResponse;
	}
	
	$r = new CServerControl();
	$r->Connect($data['ip'], $data['port']);

	if(!$r->AuthRcon($data['rcon']))
	{
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = ?", array($sid));
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно получить информацию о игроке ".addslashes(htmlspecialchars($name)).". Неверный РКОН пароль!', 'red', '', true);");
		return $objResponse;
	}
	// search for the playername
	$ret = $r->SendCommand("status");
	$search = preg_match_all(STATUS_PARSE,$ret,$matches,PREG_PATTERN_ORDER);
	$i = 0;
	$found = false;
	$index = -1;
	foreach($matches[2] AS $match) {
		if($match == $name) {
			$found = true;
			$index = $i;
			break;
		}
		$i++;
	}
	if($found) {
		$steam = $matches[3][$index];
		// Hack to support steam3 [U:1:X] representation.
		if(strpos($steam, "[U:") === 0) {
			$steam = renderSteam2(getAccountId($steam), 0);
		}
        $objResponse->addScript("ShowBox('Profile', 'Ссылка на игрока \"".addslashes(htmlspecialchars($name))."\", была успешно создана: <a href=\"http://www.steamcommunity.com/profiles/".SteamIDToFriendID($steam)."/\" title=\"".addslashes(htmlspecialchars($name))."\'s Profile\" target=\"_blank\">Открыть</a>', 'green', '', true);");
		$objResponse->addScript("window.open('http://www.steamcommunity.com/profiles/".SteamIDToFriendID($steam)."/', 'Community_".$steam."');");
	} else {
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно получить информацию о игроке ".addslashes(htmlspecialchars($name)).". Игрок ушёл с сервера!', 'red', '', true);");
	}
	return $objResponse;
}

function SendMessage($sid, $name, $message)
{
	$objResponse = new xajaxResponse();
    global $userbank, $username;
	if(!$userbank->is_admin())
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался отправить сообщение '".addslashes(htmlspecialchars($name))."' (\"".RemoveCode($message)."\"), не имея на это прав.");
		return $objResponse;
	}
	$sid = (int)$sid;
	if(!sb_admin_has_server_access($sid))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Попытка взлома", $username . " пытался отправить сообщение через RCON на sid=$sid без доступа к серверу.");
		return $objResponse;
	}
	require INCLUDES_PATH.'/CServerControl.php';
	//get the server data
	$data = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = ?", array($sid));
	if(empty($data['rcon'])) {
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно отправить сообщение для ".addslashes(htmlspecialchars($name)).". Не задан РКОН пароль!', 'red', '', true);");
		return $objResponse;
	}
	
	$r = new CServerControl();
	$r->Connect($data['ip'], $data['port']);
	
	if(!$r->AuthRcon($data['rcon']))
	{
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = ?", array($sid));
		$objResponse->addScript("ShowBox('Ошибка', 'Невозможно отправить сообщение для ".addslashes(htmlspecialchars($name)).". Неверноый РКОН пароль!', 'red', '', true);");
		return $objResponse;
	}
	$ret = $r->SendCommand('sm_psay "'.addslashes($name).'" "'.addslashes($message).'"');
	new CSystemLog("m", "Сообщение отправлено", "Данное сообщение было отправлено " . addslashes(htmlspecialchars($name)) . " on server " . $data['ip'] . ":" . $data['port'] . ": " . RemoveCode($message));
	$objResponse->addScript("ShowBox('Сообщение отправлено', 'Сообщение для игрока \'".addslashes(htmlspecialchars($name))."\' успешно отправлено!', 'green', '', true);$('dialog-control').setStyle('display', 'none');");
	return $objResponse;
}
function AddBlock($nickname, $type, $steam, $length, $reason)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался добавить блокировку, не имея на это прав.");
		return $objResponse;
	}
	
	$steam = trim($steam);
	$steamResolveErr = '';
	if ($steam !== '' && function_exists('sb_steam_resolve_to_steamid2'))
		$steam = sb_steam_resolve_to_steamid2($steam, $steamResolveErr);
	
	$error = 0;
	// If they didnt type a steamid
	if ($steamResolveErr !== '')
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", $steamResolveErr);
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else if(empty($steam))
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", "Введите Steam ID, Community ID или ссылку на профиль");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else if((!is_numeric($steam) 
	&& !validate_steam($steam))
	|| (is_numeric($steam) 
	&& (strlen($steam) < 15
	|| !validate_steam($steam = FriendIDToSteamID($steam)))))
	{
		$error++;
		$objResponse->addAssign("steam.msg", "innerHTML", "Введите действительный Steam ID, Community ID или ссылку (profiles/… или /id/…)");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'block');");
	}
	else
	{
		$objResponse->addAssign("steam.msg", "innerHTML", "");
		$objResponse->addScript("$('steam.msg').setStyle('display', 'none');");
	}
	
	if($error > 0)
		return $objResponse;

	$nickname = RemoveCode($nickname);
	$reason = RemoveCode($reason);
	if(!$length)
		$len = 0;
	else
		$len = $length*60;

	// prune any old bans
	PruneComms();

	$typeW = "";
	switch ((int)$type)
	{
		case 1:
			$typeW = "type = 1";
			break;
		case 2:
			$typeW = "type = 2";
			break;
		case 3:
			$typeW = "(type = 1 OR type = 2)";
			break;
		default:
			// Невалидный тип блокировки — прерываем, не делаем ничего молча
			$objResponse->addScript("ShowBox('Ошибка', 'Неверный тип блокировки.', 'red', '', true);");
			return $objResponse;
	}

	// Check if the new steamid is already banned
	$chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM ".DB_PREFIX."_comms WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND ".$typeW, array($steam));
	
	if(intval($chk[0]) > 0)
	{
		$objResponse->addScript("ShowBox('Ошибка', 'SteamID: $steam уже заблокирован.', 'red', '');");
		return $objResponse;
	}

	// Check if player is immune
	$admchk = $userbank->GetAllAdmins();
	foreach($admchk as $admin)
	if($admin['authid'] == $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity'])
		{
			$objResponse->addScript("ShowBox('Ошибка', 'SteamID: Админ ".$admin['user']." ($steam) имеет иммунитет.', 'red', '');");
			return $objResponse;
		}

	if((int)$type == 1 || (int)$type == 3)
	{
		$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_comms(created,type,authid,name,ends,length,reason,aid,adminIp ) VALUES
									  (UNIX_TIMESTAMP(),1,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)");
		$GLOBALS['db']->Execute($pre,array($steam,
										   $nickname,
										   $length*60,
										   $len,
										   $reason,
										   $userbank->GetAid(),
										   $_SERVER['REMOTE_ADDR']));
	}
	if ((int)$type == 2 || (int)$type ==3)
	{
		$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_comms(created,type,authid,name,ends,length,reason,aid,adminIp ) VALUES
									  (UNIX_TIMESTAMP(),2,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)");
		$GLOBALS['db']->Execute($pre,array($steam,
										   $nickname,
										   $length*60,
										   $len,
										   $reason,
										   $userbank->GetAid(),
										   $_SERVER['REMOTE_ADDR']));
	}

	$objResponse->addScript("ShowBlockBox('".$steam."', '".(int)$type."', '".(int)$len."');");
	$objResponse->addScript("TabToReload();");
	$log = new CSystemLog("m", "Блок добавлен", "Блок (" . $steam . ") был добавлен, причина: $reason, срок: $length", true, $kickit);
	return $objResponse;
}

function PrepareReblock($bid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$bid = (int)$bid;

	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался подтянуть блок #$bid в форму, не имея на это прав.");
		return $objResponse;
	}

	$ban = $GLOBALS['db']->GetRow("SELECT name, authid, type, length, reason FROM ".DB_PREFIX."_comms WHERE bid = ?", array($bid));
	if (empty($ban))
		return $objResponse;

	// clear any old stuff
	$objResponse->addScript("$('nickname').value = ''");
	$objResponse->addScript("$('steam').value = ''");
	$objResponse->addScript("$('txtReason').value = ''");
	$objResponse->addAssign("txtReason", "innerHTML",  "");

	// add new stuff
	$objResponse->addScript("$('nickname').value = " . json_encode((string)$ban['name']));
	$objResponse->addScript("$('steam').value = " . json_encode((string)$ban['authid']));
	$objResponse->addScriptCall("selectLengthTypeReason", $ban['length'], $ban['type']-1, addslashes($ban['reason']));

	$objResponse->addScript("SwapPane(0);");
	return $objResponse;
}

function PrepareBlockFromBan($bid)
{
	$objResponse = new xajaxResponse();
	global $userbank, $username;
	$bid = (int)$bid;

	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN))
	{
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		$log = new CSystemLog("w", "Ошибка доступа", $username . " пытался подтянуть бан #$bid в форму блока, не имея на это прав.");
		return $objResponse;
	}

	// clear any old stuff
	$objResponse->addScript("$('nickname').value = ''");
	$objResponse->addScript("$('steam').value = ''");
	$objResponse->addScript("$('txtReason').value = ''");	
	$objResponse->addAssign("txtReason", "innerHTML",  "");

	$ban = $GLOBALS['db']->GetRow("SELECT name, authid FROM ".DB_PREFIX."_bans WHERE bid = ?", array($bid));
	if (empty($ban))
		return $objResponse;

	// add new stuff
	$objResponse->addScript("$('nickname').value = " . json_encode((string)$ban['name']));
	$objResponse->addScript("$('steam').value = " . json_encode((string)$ban['authid']));
	
	$objResponse->addScript("SwapPane(0);");
	return $objResponse;
}

function PastePlayerData($sid, $name) {
    global $userbank, $username;
    $objResponse = new xajaxResponse();

    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $log = new CSystemLog("w", "Ошибка доступа", $username . " пытался получить данные об игроке для добавления бана/блока , не имея на это прав.");
        return $objResponse;
    }

    $sid = (int) $sid;
    if (!sb_admin_has_server_access($sid)) {
        $objResponse->redirect("index.php?p=login&m=no_access", 0);
        $log = new CSystemLog("w", "Попытка взлома", $username . " пытался получить данные игрока с sid=$sid без доступа к серверу.");
        return $objResponse;
    }
    
    sleep(1); // костыль против быстрого "пролёта" окошка о том, что игрок не найден
    $data = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = ?;", array($sid));
    if (empty($data['rcon'])) {
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("ShowBox('Ошибка', 'Нет РКОН пароля сервера <b>".$data['ip'].":".$data['port']."</b>! Получение данных об игроке невозможно!', 'red', '', true);");
        return $objResponse;
    }
    
    require(INCLUDES_PATH . '/CServerControl.php');
    $CSInstance = new CServerControl();
    $CSInstance->Connect($data['ip'], $data['port']);
    if (!$CSInstance->AuthRcon($data['rcon'])) {
        $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = ?;", array($sid));
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        $objResponse->addScript("ShowBox('Ошибка', 'Неверный РКОН пароль сервера ".$data['ip'].":".$data['port']."!', 'red', '', true);");
        return $objResponse;
    }
    
    $client = getClientByName($CSInstance, $name);
    if (!$client) {
        $objResponse->addScript("ShowBox('Ошибка', 'Нельзя получить информацию о игроке ".addslashes(htmlspecialchars($name)).". Игрок ушел с сервера! (".$data['ip'].":".$data['port'].") ', 'red', '', true);");
        $objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
        return $objResponse;
    }
    
    // nickname, steam, ip
    $objResponse->addAssign("nickname", "value", $client['name']);
    $objResponse->addAssign("steam",    "value", $client['steam']);
    $objResponse->addAssign("ip",       "value", $client['ip']);
    $objResponse->addScript("swal.close();");
    
    return $objResponse;
}

function AddWarning($id, $days, $reason) {
	global $userbank, $username;

	$objResponse = new xajaxResponse();
	$id = (int) $id;
	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS) || $userbank->GetProperty("srv_immunity", $id) > $userbank->GetProperty("srv_immunity")) {
		ShowBox_ajx("Ошибка", "Отказано в доступе.", "red", $objResponse, "", true);
		new CSystemLog("w", "Попытка несанкционированного доступа", "Администратор пытался выдать предупреждение, не имея на это прав.");
		return $objResponse;
	}
	
	if ((int) $days <= 0) {
        ShowBox_ajx("Ошибка", "Пожалуйста, введите число дней более нуля.", "red", $objResponse, "", true);
        return $objResponse;
	}

	// Защита из конфига: SteamID в SB_PROTECTED_STEAMIDS нельзя предупреждать/отстранять от должности
	$protected_steamids = array_filter(array_map('trim', explode(',', defined('SB_PROTECTED_STEAMIDS') ? SB_PROTECTED_STEAMIDS : '')));
	$targetAuthid = $userbank->GetProperty('authid', $id);
	if(!empty($targetAuthid) && in_array($targetAuthid, $protected_steamids))
	{
		ShowBox_ajx("Ошибка", "Этот администратор защищён в конфиге (SB_PROTECTED_STEAMIDS). Предупреждение запрещено.", "red", $objResponse, "", true);
		new CSystemLog("w", "Попытка предупреждения защищённого админа", $username . " попытался выдать предупреждение защищённому SteamID: " . $targetAuthid);
		sb_tripwire_punish_actor($objResponse, "попытался предупредить защищённого SteamID: " . $targetAuthid);
		return $objResponse;
	}

	$removedAccess = false;

	$GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_warns` (`arecipient`, `afrom`, `expires`, `reason`) VALUES(" . (int) $id . ", " . (int) $userbank->GetAid() . ", " . (time() + (86400 * (int) $days)) . ", " . $GLOBALS['db']->qstr($reason) . ");");
	new CSystemLog("m", "Предупреждение выдано", "Администратор выдал предупреждение Администратору " . $userbank->getProperty('user', $id));

	// Только активные варны (истёкшие / снятые expires=-1 не считаем)
	if ($GLOBALS['db']->GetOne("SELECT COUNT(*) FROM `" . DB_PREFIX . "_warns` WHERE `arecipient` = " . (int) $id . " AND `expires` > " . time()) >= (int) $GLOBALS['config']['admin.warns.max']) {
		$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `expired` = 1 WHERE `aid` = " . (int) $id . ";");
		new CSystemLog("m", "Аккаунт администратора деактивирован", "По причине превышения лимита максимально активных предупреждений, Администратор " . $userbank->getProperty('user', $id) . " отстраняется от Должности.");
		$removedAccess = true;
	}
	$msg = "Предупреждение с причиной \"<em>".htmlspecialchars($reason)."</em>\" выдано сроком на ".(int)$days." дней.";
	if ($removedAccess)
		$msg .= "<br /><br />Поскольку Администратор превысил лимит максимально активных предупреждений, он <span style=\"color: #f00;\">отстранён от должности</span>.";

	ShowBox_ajx("Успех", $msg, "green", $objResponse, "", true);
	return $objResponse;
}

function RemoveWarning($warningId) {
    global $userbank;

    $objResponse = new xajaxResponse();
    if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS)) {
        ShowBox_ajx("Ошибка", "Отказано в доступе.", "red", $objResponse, "", true);
        new CSystemLog("w", "Попытка несанкционированного доступа", "Администратор пытался снять предупреждение, не имея на это прав.");
        return $objResponse;
    }

    if ((int) $GLOBALS['db']->GetOne("SELECT COUNT(*) FROM `" . DB_PREFIX . "_warns` WHERE `expires` > " . time() . " AND `id` = ". (int) $warningId) == 1) {
        ShowBox_ajx("Успех", "Предупреждение снято", "green", $objResponse, "", true);
        new CSystemLog("m", "Предупреждение снято", "Администратор снял предупреждение Администратору " . $userbank->getProperty('user', $GLOBALS['db']->GetOne("SELECT `arecipient` FROM `" . DB_PREFIX . "_warns` WHERE `id` = " . (int) $warningId)) . " с идентификатором " . $warningId);
        $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_warns` SET `expires` = -1 WHERE `id` = " . (int) $warningId);
    } else
        ShowBox_ajx("Ошибка", "Действующее предупреждение с идентификатором " . $warningId . " не найдено. Может быть, оно уже истекло?", "red", $objResponse, "", true);
    
    return $objResponse;
}
