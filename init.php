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

// ---------------------------------------------------
//  Directories
// ---------------------------------------------------
define('ROOT', dirname(__FILE__) . "/");
define('SCRIPT_PATH', ROOT . 'scripts');
define('TEMPLATES_PATH', ROOT . 'pages');
define('INCLUDES_PATH', ROOT . 'includes');
define('SB_DEMO_LOCATION','demos');
define('SB_ICON_LOCATION','images/games');
define('SB_MAP_LOCATION', ROOT . 'images/maps');
define('SB_ICONS', ROOT . SB_ICON_LOCATION);
define('SB_DEMOS', ROOT . SB_DEMO_LOCATION);

define('SB_THEMES', ROOT . 'themes/');
define('SB_THEMES_COMPILE', ROOT . 'themes_c/');

define('IN_SB', true);
define('SB_AID', isset($_COOKIE['aid'])?$_COOKIE['aid']:null);
define('XAJAX_REQUEST_URI', './index.php');

include_once(INCLUDES_PATH . "/CSystemLog.php");
include_once(INCLUDES_PATH . "/CUserManager.php");
include_once(INCLUDES_PATH . "/CUI.php");
include_once("themes/new_box/theme.conf.php");
// ---------------------------------------------------
//  Fix some $_SERVER vars
// ---------------------------------------------------
// Fix for IIS, which doesn't set REQUEST_URI
if(!isset($_SERVER['REQUEST_URI']) || trim($_SERVER['REQUEST_URI']) == '') 
{ $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) 
    { $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING']; } 
} 
// Fix for Dreamhost and other PHP as CGI hosts
if(strstr($_SERVER['SCRIPT_NAME'], 'php.cgi')) unset($_SERVER['PATH_INFO']);
if(trim($_SERVER['PHP_SELF']) == '') $_SERVER['PHP_SELF'] = preg_replace("/(\?.*)?$/",'', $_SERVER["REQUEST_URI"]);

// ---------------------------------------------------
//  Are we installed?
// ---------------------------------------------------
/**
 * Локальная разработка (XAMPP и т.п.): localhost / 127.0.0.1 / ::1, с портом или без.
 */
function sb_is_local_host()
{
	$host = isset($_SERVER['HTTP_HOST']) ? strtolower((string)$_SERVER['HTTP_HOST']) : '';
	$host = preg_replace('/:\d+$/', '', $host);
	return ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1');
}

if (!file_exists(ROOT . '/config.php')) {
	// Нет конфига — это свежая установка. Ведём в /install/, а не «не установлен».
	if (is_dir(ROOT . '/install')) {
		header('Location: ./install/', true, 302);
		echo 'SourceBans не установлена. Перенаправление в установщик… <a href="./install/">install/</a>';
		die();
	}
	echo 'SourceBans не установлена: нет config.php и нет папки install/.';
	die();
}

if (!@include_once(ROOT . '/config.php')) {
	echo 'SourceBans: не удалось прочитать config.php.';
	die();
}

// Папка install на боевом хосте после установки — блок. На localhost/127.0.0.1 пропускаем.
if (!defined('DEVELOPER_MODE') && !defined('IS_UPDATE') && file_exists(ROOT . '/install')) {
	if (!sb_is_local_host()) {
		echo 'Из соображений безопасности удалите директорию /install/ с сервера перед работой с системой.';
		die();
	}
}

// ---------------------------------------------------
//  Initial setup
// ---------------------------------------------------

if(!defined('SB_VERSION')){
	define('SB_VERSION', '2.0.6');
}
define('LOGIN_COOKIE_LIFETIME', (60*60*24*7)*2);
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
// Куки авторизации должны уходить только по HTTPS, если сайт вообще доступен по HTTPS (см. SB_WP_URL в config.php).
define('COOKIE_SECURE', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443));
define('SB_SALT', 'SourceBans');

/**
 * Безопасно выставляет авторизационные куки (aid/password) с флагами
 * HttpOnly, Secure и SameSite=Lax (PHP 7.1: SameSite через суффикс path).
 */
function sb_set_auth_cookie($name, $value, $expire)
{
	$path = COOKIE_PATH;
	// PHP < 7.3 не знает параметр SameSite в setcookie()/session_set_cookie_params().
	if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 70300) {
		$path = rtrim($path, '/') . '/; samesite=Lax';
		if ($path === '/; samesite=Lax')
			$path = '/; samesite=Lax';
	}
	if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
		setcookie($name, $value, array(
			'expires' => $expire,
			'path' => COOKIE_PATH,
			'domain' => COOKIE_DOMAIN,
			'secure' => COOKIE_SECURE,
			'httponly' => true,
			'samesite' => 'Lax'
		));
	} else {
		setcookie($name, $value, $expire, $path, COOKIE_DOMAIN, COOKIE_SECURE, true);
	}
}

/**
 * Стартует PHP-сессию с безопасными флагами cookie (Secure/HttpOnly/SameSite=Lax).
 */
function sb_session_start()
{
	if (session_status() === PHP_SESSION_ACTIVE)
		return;

	$secure = defined('COOKIE_SECURE') ? COOKIE_SECURE : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443));
	$domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

	if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
		session_set_cookie_params(array(
			'lifetime' => 0,
			'path' => '/',
			'domain' => $domain,
			'secure' => $secure,
			'httponly' => true,
			'samesite' => 'Lax'
		));
	} else {
		// PHP 7.1: SameSite передаётся через path (поддерживается современными браузерами).
		session_set_cookie_params(0, '/; samesite=Lax', $domain, $secure, true);
	}
	session_start();
}

/**
 * Базовые security-заголовки из PHP (на случай, если nginx не читает .htaccess).
 */
function sb_send_security_headers()
{
	if (headers_sent())
		return;
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('X-XSS-Protection: 0');
	header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
	header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
	header('Cross-Origin-Resource-Policy: same-site');
	// CSP: сайт исторически опирается на inline JS/CSS (xajax, MooTools, Summernote) —
	// поэтому unsafe-inline/unsafe-eval необходимы, иначе админка развалится.
	header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https: http:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
}

/**
 * Возвращает доверенное доменное имя сайта (из SB_WP_URL в config.php), а не
 * $_SERVER['HTTP_HOST'] - заголовок Host полностью контролируется клиентом и
 * не должен использоваться при формировании заголовков email (From/Reply-To),
 * иначе специально сформированный запрос с "плохим" Host потенциально мог бы
 * повлиять на заголовки отправляемых писем.
 */
function sb_get_site_host()
{
	$host = parse_url(defined('SB_WP_URL') ? SB_WP_URL : '', PHP_URL_HOST);
	return $host ? $host : 'localhost';
}

/**
 * CSRF для xajax / форм.
 */
function sb_csrf_token()
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		if (function_exists('sb_session_start'))
			sb_session_start();
		else
			@session_start();
	}
	if (empty($_SESSION['sb_csrf']) || !is_string($_SESSION['sb_csrf']))
		$_SESSION['sb_csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
	return $_SESSION['sb_csrf'];
}

function sb_csrf_validate($token)
{
	if (!is_string($token) || $token === '')
		return false;
	$expect = isset($_SESSION['sb_csrf']) ? (string)$_SESSION['sb_csrf'] : '';
	return $expect !== '' && hash_equals($expect, $token);
}

/**
 * Проверка внешнего URL перед get_headers / редиректом (защита от SSRF).
 * Разрешены только http(s) на публичные IP.
 */
function sb_is_safe_external_url($url)
{
	if (!is_string($url) || $url === '' || strlen($url) > 2048)
		return false;
	$parts = @parse_url($url);
	if ($parts === false || empty($parts['scheme']) || empty($parts['host']))
		return false;
	$scheme = strtolower($parts['scheme']);
	if ($scheme !== 'http' && $scheme !== 'https')
		return false;
	if (isset($parts['user']) || isset($parts['pass']))
		return false;
	$host = strtolower($parts['host']);
	if ($host === 'localhost' || substr($host, -6) === '.local' || substr($host, -5) === '.internal')
		return false;
	$ips = array();
	if (filter_var($host, FILTER_VALIDATE_IP)) {
		$ips[] = $host;
	} else {
		$resolved = @gethostbynamel($host);
		if (!$resolved || !is_array($resolved))
			return false;
		$ips = $resolved;
	}
	foreach ($ips as $ip) {
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
			return false;
	}
	return true;
}

/**
 * Rate-limit по IP (файловый счётчик во временной папке).
 * @return true если запрос нужно отклонить
 */
function sb_rate_limit_hit($bucket, $max_attempts, $window_sec)
{
	$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '0';
	$dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sb_rl';
	if (!is_dir($dir))
		@mkdir($dir, 0700, true);
	$file = $dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $bucket) . '_' . hash('sha256', $ip) . '.json';
	$now = time();
	$data = array('t' => array());
	if (is_file($file)) {
		$raw = @file_get_contents($file);
		$decoded = $raw !== false ? @json_decode($raw, true) : null;
		if (is_array($decoded) && isset($decoded['t']) && is_array($decoded['t']))
			$data = $decoded;
	}
	$keep = array();
	foreach ($data['t'] as $ts) {
		$ts = (int)$ts;
		if ($ts > $now - (int)$window_sec)
			$keep[] = $ts;
	}
	if (count($keep) >= (int)$max_attempts) {
		$data['t'] = $keep;
		@file_put_contents($file, json_encode($data), LOCK_EX);
		return true;
	}
	$keep[] = $now;
	$data['t'] = $keep;
	@file_put_contents($file, json_encode($data), LOCK_EX);
	return false;
}

function sb_sanitize_comment_text($text)
{
	$text = trim((string)$text);
	// Комментарии — обычный текст; HTML не нужен (XSS stored).
	$text = strip_tags($text);
	if (strlen($text) > 5000)
		$text = substr($text, 0, 5000);
	return $text;
}

// ---------------------------------------------------
//  Setup PHP
// ---------------------------------------------------
ini_set('include_path', '.:/php/includes:' . INCLUDES_PATH .'/adodb');
ini_set('date.timezone', 'GMT');

if(defined("SB_MEM"))
	ini_set('memory_limit', SB_MEM);

// Ошибки PHP не должны показываться обычным посетителям на боевом сайте -
// это раскрывает пути на сервере, структуру БД и другую служебную информацию.
// Включаем вывод ошибок только в DEVELOPER_MODE (см. config.php), в остальных
// случаях ошибки логируются, но не выводятся в браузер.
ini_set('display_errors', defined('DEVELOPER_MODE') ? 1 : 0);
ini_set('log_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);


// ---------------------------------------------------
//  Setup our DB
// ---------------------------------------------------
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");
include_once(INCLUDES_PATH . "/adodb/adodb-errorhandler.inc.php");
$GLOBALS['db'] = ADONewConnection("mysqli://".DB_USER.':'.DB_PASS.'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);
$GLOBALS['log'] = new CSystemLog();

if( !is_object($GLOBALS['db']) )
				die();

// БАГ-ФИКС: adodb-errorhandler.inc.php подключался, но $db->raiseErrorFn никогда реально не
// назначался - поэтому ошибки запросов (Execute() вернувший false) проходили молча: ни в лог,
// ни на экран. Именно из-за этого, например, при сбое добавления пункта меню всегда показывалось
// "Успешно" - код просто не проверял результат, а узнать о реальной ошибке было неоткуда.
// Вешаем свой обработчик - пишет каждую ошибку ADOdb (Execute/Connect/...) в системный лог,
// не прерывая при этом выполнение скрипта (в отличие от дефолтного ADODB_Error_Handler,
// который по умолчанию кидает E_USER_ERROR и завершает страницу).
function sb_adodb_error_handler($dbms, $fn, $errno, $errmsg, $p1, $p2, &$thisConnection)
{
	static $inHandler = false;
	if ($inHandler)
		return; // защита от рекурсии, если сама запись лога тоже упадёт в БД-ошибку
	$inHandler = true;

	$details = "[" . $fn . "] (" . $errno . ") " . $errmsg;
	if ($fn == 'EXECUTE' && !empty($p1))
		$details .= " -- SQL: " . $p1;

	error_log("ADOdb error " . $details);
	if (class_exists('CSystemLog'))
		new CSystemLog("e", "Ошибка базы данных", $details);

	$inHandler = false;
}
$GLOBALS['db']->raiseErrorFn = 'sb_adodb_error_handler';
				
$mysql_server_info = $GLOBALS['db']->ServerInfo();
$GLOBALS['db_version'] = $mysql_server_info['version'];

// МИГРАЦИЯ: поле "skype" переименовано в "discord" (мессенджер связи с админами сменился).
// Переименовываем колонку "на лету", один раз, сохраняя тип/NULL/DEFAULT исходной колонки
// и все уже накопленные данные. После первого успешного запуска колонка "discord" уже
// существует, и эта проверка ничего не делает (дешёвый SHOW COLUMNS).
sb_migrate_skype_to_discord_column(DB_PREFIX . '_admins');
sb_migrate_skype_to_discord_column(DB_PREFIX . '_billing_adminpayments');
function sb_migrate_skype_to_discord_column($table)
{
	$col = @$GLOBALS['db']->GetRow("SHOW COLUMNS FROM `" . $table . "` LIKE 'skype'");
	if (!$col)
		return;
	if (@$GLOBALS['db']->GetOne("SHOW COLUMNS FROM `" . $table . "` LIKE 'discord'"))
		return;
	$null = (isset($col['Null']) && strtoupper($col['Null']) == 'YES') ? 'NULL' : 'NOT NULL';
	$default = '';
	if (isset($col['Default']) && $col['Default'] !== null)
		$default = 'DEFAULT ' . $GLOBALS['db']->qstr($col['Default']);
	$type = isset($col['Type']) ? $col['Type'] : 'VARCHAR(64)';
	@$GLOBALS['db']->Execute("ALTER TABLE `" . $table . "` CHANGE `skype` `discord` " . $type . " " . $null . " " . $default);
}

// password_hash (bcrypt ~60) / будущий argon2 не влезают в старый VARCHAR(40/64).
sb_migrate_admins_password_column();
function sb_migrate_admins_password_column()
{
	$col = @$GLOBALS['db']->GetRow("SHOW COLUMNS FROM `" . DB_PREFIX . "_admins` LIKE 'password'");
	if (!$col || empty($col['Type']))
		return;
	if (preg_match('/varchar\((\d+)\)/i', $col['Type'], $m) && (int)$m[1] >= 255)
		return;
	if (stripos($col['Type'], 'text') !== false)
		return;
	@$GLOBALS['db']->Execute("ALTER TABLE `" . DB_PREFIX . "_admins` MODIFY `password` VARCHAR(255) NOT NULL");
}

$debug = $GLOBALS['db']->Execute("SELECT value FROM `".DB_PREFIX."_settings` WHERE setting = 'config.debug';");
if($debug->fields['value']=="1") {
	define("DEVELOPER_MODE", true);
}
// Перепроверяем вывод ошибок теперь, когда DEVELOPER_MODE мог быть включён через настройки в БД.
ini_set('display_errors', defined('DEVELOPER_MODE') ? 1 : 0);

// ---------------------------------------------------
//  Setup our custom error handler
// ---------------------------------------------------
require_once(INCLUDES_PATH . '/CErrorHandler.php');
$GLOBALS['error_manager'] = new CErrorHandler();

// ---------------------------------------------------
//  Some defs
// ---------------------------------------------------
define('EMAIL_FORMAT', "/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/");
define('URL_FORMAT', "/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}((:[0-9]{1,5})?\/.*)?$/i");
define('STEAM_FORMAT', "/^STEAM_[0-9]:[0-9]:[0-9]+$/");
define('STATUS_PARSE', '/# +([0-9 ]+) +"(.+)" +(STEAM_[0-9]:[0-9]:[0-9]+|\[U:[0-9]:[0-9]+\]) +([0-9:]+) +([0-9]+) +([0-9]+) +([a-zA-Z]+) +([0-9.:]+)/');
define('IP_FORMAT', '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/');
define('SERVER_QUERY', 'http://www.sourcebans.net/public/query/');

// Web admin-flags
define('ADMIN_LIST_ADMINS', 	(1<<0));
define('ADMIN_ADD_ADMINS', 		(1<<1));
define('ADMIN_EDIT_ADMINS', 	(1<<2));
define('ADMIN_DELETE_ADMINS', 	(1<<3));

define('ADMIN_LIST_SERVERS', 	(1<<4));
define('ADMIN_ADD_SERVER', 		(1<<5));
define('ADMIN_EDIT_SERVERS', 	(1<<6));
define('ADMIN_DELETE_SERVERS', 	(1<<7));

define('ADMIN_ADD_BAN', 		(1<<8));
define('ADMIN_EDIT_OWN_BANS', 	(1<<10));
define('ADMIN_EDIT_GROUP_BANS', (1<<11));
define('ADMIN_EDIT_ALL_BANS', 	(1<<12));
define('ADMIN_BAN_PROTESTS', 	(1<<13));
define('ADMIN_BAN_SUBMISSIONS', (1<<14));
define('ADMIN_DELETE_BAN',		(1<<25));
define('ADMIN_UNBAN', 			(1<<26));
define('ADMIN_BAN_IMPORT',		(1<<27));
define('ADMIN_UNBAN_OWN_BANS',	(1<<30));
define('ADMIN_UNBAN_GROUP_BANS',(1<<31));

define('ADMIN_LIST_GROUPS', 	(1<<15));
define('ADMIN_ADD_GROUP', 		(1<<16));
define('ADMIN_EDIT_GROUPS', 	(1<<17));
define('ADMIN_DELETE_GROUPS', 	(1<<18));

define('ADMIN_WEB_SETTINGS', 	(1<<19));

define('ADMIN_LIST_MODS', 		(1<<20));
define('ADMIN_ADD_MODS', 		(1<<21));
define('ADMIN_EDIT_MODS', 		(1<<22));
define('ADMIN_DELETE_MODS', 	(1<<23));

define('ADMIN_NOTIFY_SUB',	(1<<28));
define('ADMIN_NOTIFY_PROTEST',	(1<<29));

define('ADMIN_OWNER', 			(1<<24));

// Server admin-flags
define('SM_RESERVED_SLOT', 		"a");
define('SM_GENERIC', 			"b");
define('SM_KICK', 				"c");
define('SM_BAN', 				"d");
define('SM_UNBAN', 				"e");
define('SM_SLAY', 				"f");
define('SM_MAP', 				"g");
define('SM_CVAR', 				"h");
define('SM_CONFIG', 			"i");
define('SM_CHAT', 				"j");
define('SM_VOTE',				"k");
define('SM_PASSWORD', 			"l");
define('SM_RCON', 				"m");
define('SM_CHEATS', 			"n");
define('SM_ROOT', 				"z");

define('SM_CUSTOM1', 			"o");
define('SM_CUSTOM2', 			"p");
define('SM_CUSTOM3', 			"q");
define('SM_CUSTOM4', 			"r");
define('SM_CUSTOM5', 			"s");
define('SM_CUSTOM6', 			"t");


define('ALL_WEB', ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|
				  ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|
				  ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|
				  ADMIN_DELETE_GROUPS|ADMIN_WEB_SETTINGS|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS|ADMIN_OWNER|
				  ADMIN_DELETE_BAN|ADMIN_UNBAN|ADMIN_BAN_IMPORT|ADMIN_UNBAN_OWN_BANS|ADMIN_UNBAN_GROUP_BANS|ADMIN_NOTIFY_SUB|ADMIN_NOTIFY_PROTEST);

define('ALL_SERVER', SM_RESERVED_SLOT.SM_GENERIC.SM_KICK.SM_BAN.SM_UNBAN.SM_SLAY.SM_MAP.SM_CVAR.SM_CONFIG.SM_VOTE.SM_PASSWORD.SM_RCON.
					 SM_CHEATS.SM_CUSTOM1.SM_CUSTOM2.SM_CUSTOM3. SM_CUSTOM4.SM_CUSTOM5.SM_CUSTOM6.SM_ROOT);

$GLOBALS['db']->Execute("SET NAMES utf8;");
					 
$res = $GLOBALS['db']->Execute("SELECT * FROM ".DB_PREFIX."_settings GROUP BY `setting`, `value`");
$GLOBALS['config'] = array();
while (!$res->EOF)
{
	$setting = array($res->fields['setting'] => $res->fields['value']);
	$GLOBALS['config'] = array_merge_recursive($GLOBALS['config'], $setting);
	$res->MoveNext();
}

define('SB_BANS_PER_PAGE', $GLOBALS['config']['banlist.bansperpage']);
define('MIN_PASS_LENGTH', $GLOBALS['config']['config.password.minlength']);
$dateformat = !empty($GLOBALS['config']['config.dateformat'])?$GLOBALS['config']['config.dateformat']:"m-d-y H:i";

if(version_compare(PHP_VERSION, "5") != -1)
{
    $offset = (empty($GLOBALS['config']['config.timezone'])?0:$GLOBALS['config']['config.timezone'])*3600;
    date_default_timezone_set("GMT");
    $abbrarray = timezone_abbreviations_list();
    foreach ($abbrarray as $abbr) {
        foreach ($abbr as $city) {
            if ($city['offset'] == $offset && $city['dst'] == $GLOBALS['config']['config.summertime']) {
                date_default_timezone_set($city['timezone_id']);
                break 2;
            }
        }
    }
}
else 
{
    if(empty($GLOBALS['config']['config.timezone']))
    {
        define('SB_TIMEZONE', 0);
    } else {
        define('SB_TIMEZONE', $GLOBALS['config']['config.timezone']);
    }
}

// if(empty($GLOBALS['config']['config.timezone']))
// {
	// date_default_timezone_set("Europe/London");
// }else{
	// date_default_timezone_set($GLOBALS['config']['config.timezone']);
// }


// ---------------------------------------------------
// Setup our templater
// ---------------------------------------------------
require(INCLUDES_PATH . '/smarty/Smarty.class.php');

global $theme, $userbank;

define('SB_THEME', 'new_box');

if(!@file_exists(SB_THEMES . SB_THEME . "/theme.conf.php"))
	die("<b>Ошибка шаблона</b>: Шаблон повреждён. Отсутствует файл <b>theme.conf.php</b>.");

if(!@is_writable(SB_THEMES_COMPILE))
	die("<b>Ошибка шаблона</b>: Папка <b>".SB_THEMES_COMPILE."</b> не перезаписываемая! Установите права 777 на папку через FTP-клиент.");

$theme = new Smarty();
$theme->error_reporting 	= 	E_ALL ^ E_NOTICE;
$theme->use_sub_dirs 		= 	false;
$theme->compile_id			= 	SB_THEME;
$theme->caching 			= 	false;
$theme->template_dir 		= 	SB_THEMES . SB_THEME;
$theme->compile_dir 		= 	SB_THEMES_COMPILE;

if ((isset($_GET['debug']) && $_GET['debug'] == 1) || defined("DEVELOPER_MODE") )
{
	$theme->force_compile = true;
}

// ---------------------------------------------------
// Setup our user manager
// ---------------------------------------------------
$l = '';
$p = '';
if (!defined('IS_UPDATE') && isset($_COOKIE['aid']))
    $l = $_COOKIE['aid'];
if (!defined('IS_UPDATE') && isset($_COOKIE['password']))
    $p = $_COOKIE['password'];

$userbank = new CUserManager($l, $p);

// Security-заголовки как можно раньше (до любого вывода).
if (!defined('IS_UPDATE') && !defined('IN_INSTALL') && php_sapi_name() !== 'cli') {
	sb_send_security_headers();
}
