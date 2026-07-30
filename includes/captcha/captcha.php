<?php
/**
 * PNG-капча для активации ваучера.
 * includes/ закрыт по HTTP — для этой папки разрешено (.htaccess).
 */
$root = dirname(dirname(__DIR__));

// Те же cookie-параметры сессии, что у панели (иначе код в $_SESSION не совпадёт).
if (is_readable($root . '/config.php')) {
	if (!defined('IN_SB'))
		define('IN_SB', true);
	require_once $root . '/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
	$secure = defined('COOKIE_SECURE')
		? COOKIE_SECURE
		: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443));
	$domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
	if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
		session_set_cookie_params(array(
			'lifetime' => 0,
			'path' => '/',
			'domain' => $domain,
			'secure' => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		));
	} else {
		session_set_cookie_params(0, '/; samesite=Lax', $domain, $secure, true);
	}
	@session_start();
}

if (!extension_loaded('gd')) {
	header('HTTP/1.1 503 Service Unavailable');
	header('Content-Type: text/plain; charset=utf-8');
	echo 'GD required';
	exit;
}

$chars = 'abcdefghjkmnpqrstuvwxyz23456789';
$code = '';
$max = strlen($chars) - 1;
for ($i = 0; $i < 5; $i++) {
	if (function_exists('random_int'))
		$code .= $chars[random_int(0, $max)];
	else
		$code .= $chars[mt_rand(0, $max)];
}
$_SESSION['rand_code'] = $code;

$w = 170;
$h = 56;
$image = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($image, 12, 21, 40);
$fg = imagecolorallocate($image, 30, 144, 255);
$noise = imagecolorallocate($image, 45, 80, 120);
imagefilledrectangle($image, 0, 0, $w, $h, $bg);

for ($i = 0; $i < 5; $i++)
	imageline($image, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $noise);
for ($i = 0; $i < 80; $i++)
	imagesetpixel($image, mt_rand(0, $w - 1), mt_rand(0, $h - 1), $noise);

$font = __DIR__ . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'verdana.ttf';
if (is_readable($font) && function_exists('imagettftext')) {
	@imagettftext($image, 22, mt_rand(-8, 8), 18, 38, $fg, $font, $code);
} else {
	imagestring($image, 5, 40, 20, $code, $fg);
}

while (ob_get_level() > 0)
	@ob_end_clean();

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
imagepng($image);
imagedestroy($image);
exit;
