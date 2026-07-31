<?php
/**
 * sitemap.xml + robots.txt под URL панели (SB_WP_URL).
 * PHP 8.5+.
 */
if (!defined('IN_SB') && !defined('IN_INSTALL')) {
	echo 'You should not be here. Only follow links!';
	die();
}

/**
 * @param string $url
 * @return string без завершающего /
 */
function sb_install_normalize_base_url($url)
{
	$url = trim((string)$url);
	if ($url === '') {
		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443');
		$scheme = $https ? 'https' : 'http';
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
		$url = $scheme . '://' . $host;
	}
	return rtrim($url, '/');
}

/**
 * @param string $siteRoot абсолютный путь к корню (рядом с index.php)
 * @param string $baseUrl  SB_WP_URL или пусто → угадать из запроса
 * @return array{ok:bool,files:array,error:?string}
 */
function sb_install_write_seo_files($siteRoot, $baseUrl = '')
{
	$siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
	$base = sb_install_normalize_base_url($baseUrl);

	if (!is_dir($siteRoot) || (!is_writable($siteRoot) && !is_writable($siteRoot . '/robots.txt') && !is_writable($siteRoot . '/sitemap.xml'))) {
		// ещё можно писать, если файлов нет, но каталог writable — проверка выше мягкая
		if (!is_writable($siteRoot)) {
			return array('ok' => false, 'files' => array(), 'error' => 'Корень сайта недоступен для записи SEO-файлов');
		}
	}

	$paths = array(
		array('loc' => $base . '/', 'changefreq' => 'daily', 'priority' => '1.0'),
		array('loc' => $base . '/servers', 'changefreq' => 'hourly', 'priority' => '0.9'),
		array('loc' => $base . '/banlist', 'changefreq' => 'hourly', 'priority' => '0.9'),
		array('loc' => $base . '/commslist', 'changefreq' => 'hourly', 'priority' => '0.8'),
		array('loc' => $base . '/adminlist', 'changefreq' => 'weekly', 'priority' => '0.7'),
	);

	$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ($paths as $u) {
		$loc = htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$xml .= "  <url>\n";
		$xml .= '    <loc>' . $loc . "</loc>\n";
		$xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
		$xml .= '    <priority>' . $u['priority'] . "</priority>\n";
		$xml .= "  </url>\n";
	}
	$xml .= "</urlset>\n";

	$robots = "# SourceBans — сгенерировано установщиком\n"
		. "# AI-боты\n"
		. "User-agent: GPTBot\nDisallow: /\n\n"
		. "User-agent: Amazonbot\nDisallow: /\n\n"
		. "User-agent: Bytespider\nDisallow: /\n\n"
		. "User-agent: CCBot\nDisallow: /\n\n"
		. "User-agent: ClaudeBot\nDisallow: /\n\n"
		. "# Мусорные краулеры\n"
		. "User-agent: DotBot\nDisallow: /\n\n"
		. "User-agent: MJ12bot\nDisallow: /\n\n"
		. "# SEO-аудиторы\n"
		. "User-agent: SemrushBot\nAllow: /\n\n"
		. "User-agent: AhrefsBot\nAllow: /\n\n"
		. "User-agent: DataForSeoBot\nAllow: /\n\n"
		. "User-agent: *\n"
		. "Allow: /\n"
		. "Disallow: /install/\n"
		. "Disallow: /updater/\n"
		. "Disallow: /themes_c/\n"
		. "Disallow: /data/\n"
		. "Disallow: /includes/\n\n"
		. 'Sitemap: ' . $base . "/sitemap.xml\n";

	$written = array();
	$sitemapPath = $siteRoot . '/sitemap.xml';
	$robotsPath = $siteRoot . '/robots.txt';

	if (@file_put_contents($sitemapPath, $xml) === false) {
		return array('ok' => false, 'files' => $written, 'error' => 'Не удалось записать sitemap.xml');
	}
	$written[] = 'sitemap.xml';

	if (@file_put_contents($robotsPath, $robots) === false) {
		return array('ok' => false, 'files' => $written, 'error' => 'Не удалось записать robots.txt');
	}
	$written[] = 'robots.txt';

	$og = sb_install_write_og_cover($siteRoot);
	if (!empty($og['ok'])) {
		$written[] = 'images/og-cover.jpg';
	} elseif (!empty($og['error'])) {
		// SEO без OG не считаем фатальным — файлы sitemap/robots уже ок
		return array('ok' => true, 'files' => $written, 'error' => $og['error']);
	}

	return array('ok' => true, 'files' => $written, 'error' => null);
}

/**
 * Заглушка Open Graph 1200×630 (Material Admin | SourceBans).
 * Свой баннер: просто замени images/og-cover.jpg или SB_OG_IMAGE в config.php.
 *
 * @param string $siteRoot
 * @return array{ok:bool,error:?string}
 */
function sb_install_write_og_cover($siteRoot)
{
	$siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
	$dir = $siteRoot . '/images';
	$path = $dir . '/og-cover.jpg';

	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		return array('ok' => false, 'error' => 'Нет папки images/ для og-cover.jpg');
	}
	if (!function_exists('imagecreatetruecolor')) {
		if (is_readable($path))
			return array('ok' => true, 'error' => null);
		return array('ok' => false, 'error' => 'Нет GD — положи images/og-cover.jpg вручную (1200×630)');
	}

	$w = 1200;
	$h = 630;
	$im = imagecreatetruecolor($w, $h);
	if ($im === false)
		return array('ok' => false, 'error' => 'GD: не удалось создать холст og-cover');

	for ($y = 0; $y < $h; $y++) {
		$t = $y / max(1, $h - 1);
		$r = (int)(10 + (26 - 10) * $t);
		$g = (int)(18 + (45 - 18) * $t);
		$b = (int)(37 + (66 - 37) * $t);
		$c = imagecolorallocate($im, $r, $g, $b);
		imageline($im, 0, $y, $w, $y, $c);
	}

	$blue = imagecolorallocate($im, 30, 144, 255);
	$white = imagecolorallocate($im, 232, 240, 255);
	$muted = imagecolorallocate($im, 126, 168, 212);
	$ink = imagecolorallocate($im, 10, 18, 37);
	imagefilledrectangle($im, 0, 0, 8, $h, $blue);

	$mx = 80;
	$my = 210;
	$ms = 180;
	imagefilledrectangle($im, $mx, $my, $mx + $ms, $my + $ms, $blue);
	$pts = array(
		$mx + 28, $my + 150,
		$mx + 28, $my + 40,
		$mx + 90, $my + 110,
		$mx + 152, $my + 40,
		$mx + 152, $my + 150,
		$mx + 128, $my + 150,
		$mx + 128, $my + 80,
		$mx + 90, $my + 120,
		$mx + 52, $my + 80,
		$mx + 52, $my + 150,
	);
	imagefilledpolygon($im, $pts, 10, $ink);

	$font = null;
	foreach (array(
		'C:/Windows/Fonts/arialbd.ttf',
		'C:/Windows/Fonts/arial.ttf',
		'C:/Windows/Fonts/segoeui.ttf',
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
	) as $f) {
		if (is_readable($f)) {
			$font = $f;
			break;
		}
	}
	if ($font) {
		imagettftext($im, 48, 0, 320, 280, $white, $font, 'Material Admin');
		imagettftext($im, 36, 0, 320, 350, $muted, $font, 'SourceBans');
		imagettftext($im, 22, 0, 320, 420, $muted, $font, 'Banlist · Servers · Moderation');
	} else {
		imagestring($im, 5, 320, 250, 'Material Admin | SourceBans', $white);
		imagestring($im, 4, 320, 280, 'Banlist / Servers / Moderation', $muted);
	}

	$ok = @imagejpeg($im, $path, 88);
	imagedestroy($im);
	if (!$ok)
		return array('ok' => false, 'error' => 'Не удалось записать images/og-cover.jpg');

	return array('ok' => true, 'error' => null);
}
