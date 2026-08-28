<?php
/**
 * SEO / Open Graph helpers (runtime + installer).
 * DB keys seo.* override SB_OG_* from config.php when non-empty.
 */
if (!defined('IN_SB') && !defined('IN_INSTALL')) {
	echo 'You should not be here. Only follow links!';
	die();
}

/** Max OG cover upload size (bytes). */
if (!defined('SB_SEO_OG_MAX_BYTES'))
	define('SB_SEO_OG_MAX_BYTES', 3 * 1024 * 1024);

/**
 * @param string $url
 * @return string without trailing slash
 */
function sb_normalize_base_url($url)
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
 * Read a settings key from $GLOBALS['config'].
 *
 * @param string $key
 * @param string $default
 * @return string
 */
function sb_seo_cfg($key, $default = '')
{
	if (!isset($GLOBALS['config']) || !is_array($GLOBALS['config']))
		return (string)$default;
	if (!array_key_exists($key, $GLOBALS['config']))
		return (string)$default;
	$v = $GLOBALS['config'][$key];
	if ($v === null)
		return (string)$default;
	return trim((string)$v);
}

/**
 * First non-empty string among candidates.
 *
 * @param string ...$candidates
 * @return string
 */
function sb_seo_first_nonempty()
{
	$args = func_get_args();
	foreach ($args as $v) {
		$v = trim((string)$v);
		if ($v !== '')
			return $v;
	}
	return '';
}

/**
 * Resolved Open Graph / SEO defaults: DB seo.* → SB_OG_* → heuristics.
 *
 * @param string $brandFallback site brand (template.title)
 * @return array{
 *   og_site_name:string,og_title:string,og_description:string,og_image:string,
 *   og_image_width:int,og_image_height:int,meta_description:string,site_base:string
 * }
 */
function sb_seo_og_bundle($brandFallback = '')
{
	$brand = trim(strip_tags((string)$brandFallback));
	if ($brand === '' && !empty($GLOBALS['config']['template.title']))
		$brand = trim(strip_tags(stripslashes((string)$GLOBALS['config']['template.title'])));
	if ($brand === '')
		$brand = 'SourceBans';

	$siteBase = '';
	if (defined('SB_WP_URL') && SB_WP_URL !== '')
		$siteBase = rtrim((string)SB_WP_URL, '/');
	if ($siteBase === '')
		$siteBase = sb_normalize_base_url('');

	$dbSite = sb_seo_cfg('seo.og_site_name');
	$dbTitle = sb_seo_cfg('seo.og_title');
	$dbDesc = sb_seo_cfg('seo.og_description');
	$dbImg = sb_seo_cfg('seo.og_image');
	$dbW = sb_seo_cfg('seo.og_image_width');
	$dbH = sb_seo_cfg('seo.og_image_height');
	$dbMeta = sb_seo_cfg('seo.meta_description');

	$cfgSite = (defined('SB_OG_SITE_NAME') && SB_OG_SITE_NAME !== '') ? (string)SB_OG_SITE_NAME : '';
	$cfgTitle = (defined('SB_OG_TITLE') && SB_OG_TITLE !== '') ? (string)SB_OG_TITLE : '';
	$cfgDesc = (defined('SB_OG_DESCRIPTION') && SB_OG_DESCRIPTION !== '') ? (string)SB_OG_DESCRIPTION : '';
	$cfgImg = (defined('SB_OG_IMAGE') && SB_OG_IMAGE !== '') ? (string)SB_OG_IMAGE : '';
	$cfgW = (defined('SB_OG_IMAGE_WIDTH') && (int)SB_OG_IMAGE_WIDTH > 0) ? (string)(int)SB_OG_IMAGE_WIDTH : '';
	$cfgH = (defined('SB_OG_IMAGE_HEIGHT') && (int)SB_OG_IMAGE_HEIGHT > 0) ? (string)(int)SB_OG_IMAGE_HEIGHT : '';

	$og_site_name = sb_seo_first_nonempty($dbSite, $cfgSite, $brand);
	$og_title = sb_seo_first_nonempty($dbTitle, $cfgTitle, $og_site_name . ' — игровые серверы');
	$og_description = sb_seo_first_nonempty(
		$dbDesc,
		$cfgDesc,
		'Онлайн, правила, банлист и админлист — ' . $og_site_name
	);
	$meta_description = sb_seo_first_nonempty($dbMeta, $og_description);

	$og_image = sb_seo_first_nonempty($dbImg, $cfgImg, 'images/og-cover.jpg');
	$og_image = trim($og_image);

	$w = (int)sb_seo_first_nonempty($dbW, $cfgW, '1200');
	$h = (int)sb_seo_first_nonempty($dbH, $cfgH, '630');
	if ($w <= 0)
		$w = 1200;
	if ($h <= 0)
		$h = 630;

	return array(
		'og_site_name' => $og_site_name,
		'og_title' => $og_title,
		'og_description' => $og_description,
		'og_image' => $og_image,
		'og_image_width' => $w,
		'og_image_height' => $h,
		'meta_description' => $meta_description,
		'site_base' => $siteBase,
	);
}

/**
 * Absolute public URL for an OG image path (relative or absolute).
 *
 * @param string $img
 * @param string $siteBase
 * @return string
 */
function sb_seo_absolute_image_url($img, $siteBase = '')
{
	$img = trim((string)$img);
	if ($img === '')
		$img = 'images/og-cover.jpg';
	if (preg_match('#^https?://#i', $img))
		return $img;
	$base = rtrim((string)$siteBase, '/');
	if ($base === '')
		$base = sb_normalize_base_url('');
	return $base . '/' . ltrim($img, '/');
}

/**
 * Write sitemap.xml + robots.txt. Optionally generate GD OG stub.
 *
 * @param string $siteRoot
 * @param string $baseUrl
 * @param array $options write_og_stub (bool, default false)
 * @return array{ok:bool,files:array,error:?string}
 */
function sb_write_seo_files($siteRoot, $baseUrl = '', $options = array())
{
	$writeOg = !empty($options['write_og_stub']);
	$siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
	$base = sb_normalize_base_url($baseUrl);

	if (!is_dir($siteRoot)) {
		return array('ok' => false, 'files' => array(), 'error' => 'Корень сайта не найден');
	}
	if (!is_writable($siteRoot)
		&& !(file_exists($siteRoot . '/robots.txt') && is_writable($siteRoot . '/robots.txt'))
		&& !(file_exists($siteRoot . '/sitemap.xml') && is_writable($siteRoot . '/sitemap.xml'))) {
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

	$robots = "# SourceBans — сгенерировано панелью\n"
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

	if ($writeOg) {
		$og = sb_write_og_cover_stub($siteRoot);
		if (!empty($og['ok'])) {
			$written[] = 'images/og-cover.jpg';
		} elseif (!empty($og['error'])) {
			return array('ok' => true, 'files' => $written, 'error' => $og['error']);
		}
	}

	return array('ok' => true, 'files' => $written, 'error' => null);
}

/**
 * GD stub Open Graph 1200×630 (installer / first run).
 *
 * @param string $siteRoot
 * @return array{ok:bool,error:?string}
 */
function sb_write_og_cover_stub($siteRoot)
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
		imagettftext($im, 52, 0, 320, 275, $white, $font, 'Blue Admin');
		imagettftext($im, 30, 0, 320, 340, $muted, $font, 'Панель управления');
		imagettftext($im, 22, 0, 320, 410, $muted, $font, 'SourceBans · Banlist · Servers');
	} else {
		imagestring($im, 5, 320, 250, 'Blue Admin', $white);
		imagestring($im, 4, 320, 280, 'Panel / SourceBans', $muted);
	}

	$ok = @imagejpeg($im, $path, 88);
	imagedestroy($im);
	if (!$ok)
		return array('ok' => false, 'error' => 'Не удалось записать images/og-cover.jpg');

	return array('ok' => true, 'error' => null);
}

/**
 * Save uploaded OG cover as images/og-cover.jpg (jpeg/png/webp → jpeg).
 *
 * @param array $file one $_FILES entry
 * @param string|null $siteRoot
 * @return array{ok:bool,path:?string,width:?int,height:?int,error:?string}
 */
function sb_seo_save_og_upload($file, $siteRoot = null)
{
	if ($siteRoot === null) {
		if (defined('ROOT'))
			$siteRoot = rtrim(str_replace('\\', '/', ROOT), '/');
		else
			$siteRoot = rtrim(str_replace('\\', '/', getcwd()), '/');
	} else {
		$siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
	}

	if (!is_array($file))
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Файл не передан');

	if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
		$code = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Ошибка загрузки (код ' . $code . ')');
	}

	$size = isset($file['size']) ? (int)$file['size'] : 0;
	if ($size <= 0 || $size > SB_SEO_OG_MAX_BYTES) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Файл пуст или больше 3 МБ');
	}

	$tmp = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
	if ($tmp === '' || !is_uploaded_file($tmp)) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Некорректная временная загрузка');
	}

	$info = @getimagesize($tmp);
	if ($info === false || empty($info[0]) || empty($info[1])) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Файл не является изображением');
	}

	$mime = isset($info['mime']) ? strtolower((string)$info['mime']) : '';
	$allowed = array('image/jpeg' => true, 'image/png' => true, 'image/webp' => true);
	if (!isset($allowed[$mime])) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Допустимы JPEG, PNG или WebP');
	}

	$dir = $siteRoot . '/images';
	$rel = 'images/og-cover.jpg';
	$path = $dir . '/og-cover.jpg';

	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Не удалось создать папку images/');
	}
	if (!is_writable($dir) && !(file_exists($path) && is_writable($path))) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Папка images/ недоступна для записи');
	}

	$w = (int)$info[0];
	$h = (int)$info[1];

	if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg') === false) {
		if (!@move_uploaded_file($tmp, $path)) {
			return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Не удалось сохранить og-cover.jpg');
		}
		@chmod($path, 0644);
		return array('ok' => true, 'path' => $rel, 'width' => $w, 'height' => $h, 'error' => null);
	}

	if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
		if ($mime === 'image/jpeg') {
			if (!@move_uploaded_file($tmp, $path)) {
				return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Не удалось сохранить og-cover.jpg');
			}
			@chmod($path, 0644);
			return array('ok' => true, 'path' => $rel, 'width' => $w, 'height' => $h, 'error' => null);
		}
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Нет GD для конвертации в JPEG');
	}

	$src = false;
	if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg'))
		$src = @imagecreatefromjpeg($tmp);
	elseif ($mime === 'image/png' && function_exists('imagecreatefrompng'))
		$src = @imagecreatefrompng($tmp);
	elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp'))
		$src = @imagecreatefromwebp($tmp);

	if ($src === false) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Не удалось прочитать изображение');
	}

	$dst = imagecreatetruecolor($w, $h);
	if ($dst === false) {
		imagedestroy($src);
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'GD: холст');
	}
	$bg = imagecolorallocate($dst, 255, 255, 255);
	imagefilledrectangle($dst, 0, 0, $w, $h, $bg);
	imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
	imagedestroy($src);

	$ok = @imagejpeg($dst, $path, 90);
	imagedestroy($dst);
	if (!$ok) {
		return array('ok' => false, 'path' => null, 'width' => null, 'height' => null, 'error' => 'Не удалось записать images/og-cover.jpg');
	}
	@chmod($path, 0644);

	return array('ok' => true, 'path' => $rel, 'width' => $w, 'height' => $h, 'error' => null);
}

// --- Installer aliases (compat with install/template/page.5.php) ---

if (!function_exists('sb_install_normalize_base_url')) {
	function sb_install_normalize_base_url($url)
	{
		return sb_normalize_base_url($url);
	}
}

if (!function_exists('sb_install_write_seo_files')) {
	function sb_install_write_seo_files($siteRoot, $baseUrl = '')
	{
		return sb_write_seo_files($siteRoot, $baseUrl, array('write_og_stub' => true));
	}
}

if (!function_exists('sb_install_write_og_cover')) {
	function sb_install_write_og_cover($siteRoot)
	{
		return sb_write_og_cover_stub($siteRoot);
	}
}
