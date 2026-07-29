<?php
// *************************************************************************
//  SourceBans++ installer bootstrap
// *************************************************************************

define('ROOT', dirname(__FILE__) . '/');
define('SCRIPT_PATH', ROOT . 'scripts');
define('TEMPLATES_PATH', ROOT . 'template');
define('INCLUDES_PATH', ROOT . 'includes');
define('IN_SB', true);
define('IN_INSTALL', true);

// Live-сайт читает ROOT/config.php (рядом с index.php), не data/config.php.
define('SB_CONFIG_PATH', dirname(ROOT) . '/config.php');

if (!isset($_SERVER['REQUEST_URI']) || trim($_SERVER['REQUEST_URI']) === '') {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
	if (!empty($_SERVER['QUERY_STRING']))
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
}
if (strstr($_SERVER['SCRIPT_NAME'], 'php.cgi'))
	unset($_SERVER['PATH_INFO']);
if (trim($_SERVER['PHP_SELF']) === '')
	$_SERVER['PHP_SELF'] = preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']);

if (!defined('SB_VERSION'))
	define('SB_VERSION', 'Установщик 1.6');

define('LOGIN_COOKIE_LIFETIME', (60 * 60 * 24 * 7) * 2);
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
define('COOKIE_SECURE', false);
define('SB_SALT', 'SourceBans');

ini_set('date.timezone', 'GMT');
if (function_exists('date_default_timezone_set'))
	date_default_timezone_set('GMT');

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);

define('EMAIL_FORMAT', "/^([a-zA-Z0-9])+([a-zA-Z0-9\\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\\._-]+)+$/");
define('URL_FORMAT', "/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}((:[0-9]{1,5})?\\/.*)?$/i");
define('STEAM_FORMAT', "/^STEAM_[0-9]:[0-9]:[0-9]+$/");

/**
 * Гарантирует существование каталогов, нужных панели после установки.
 */
function sb_install_ensure_dirs()
{
	$base = dirname(ROOT);
	$dirs = array(
		$base . '/demos',
		$base . '/themes_c',
		$base . '/images/games',
		$base . '/images/maps',
		$base . '/images/icons',
	);
	foreach ($dirs as $dir) {
		if (!is_dir($dir))
			@mkdir($dir, 0775, true);
	}
}

sb_install_ensure_dirs();
