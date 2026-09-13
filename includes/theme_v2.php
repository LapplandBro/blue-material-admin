<?php
/**
 * UI Blue V2: Twig + Bootstrap 5 — единственная оболочка (сайт и установщик).
 */

if (!defined('IN_SB')) {
	echo 'Ошибка доступа!';
	die();
}

function sb_ui_v2_notice_text($key)
{
	if (empty($GLOBALS['config'][$key]))
		return '';
	return trim(stripslashes((string)$GLOBALS['config'][$key]));
}

function sb_ui_v2_page_notices($page)
{
	$page = (string)$page;
	if ($page === '')
		$page = 'home';
	$out = array();
	if ($page === 'home') {
		$t = sb_ui_v2_notice_text('config.text_home');
		if ($t !== '')
			$out[] = array('text' => $t, 'tone' => 'home', 'place' => 'top-left', 'ms' => 3800);
	}
	if ($page === 'servers') {
		$t = sb_ui_v2_notice_text('config.text_mon');
		if ($t !== '')
			$out[] = array('text' => $t, 'tone' => 'info', 'place' => 'bottom-right', 'ms' => 4900);
	}
	if ($page === 'account') {
		$t = sb_ui_v2_notice_text('config.text_acc');
		if ($t !== '')
			$out[] = array('text' => $t, 'tone' => 'ok', 'place' => 'top-right', 'ms' => 4500);
		$t2 = sb_ui_v2_notice_text('config.text_acc2');
		if ($t2 !== '')
			$out[] = array('text' => $t2, 'tone' => 'info', 'place' => 'top-right', 'ms' => 5800);
	}
	return $out;
}

function sb_ui_v2_boot()
{
	// V2 по умолчанию: cookie / ?ui=legacy больше не переключают оболочку.
}

/**
 * Прокинуть SEO/OG в Twig: сначала из $theme (header.php), иначе sb_seo_og_bundle (DB → SB_OG_*).
 */
function sb_ui_v2_apply_seo_vars(array &$vars)
{
	global $theme;
	$seoKeys = array(
		'seo_title', 'seo_document_title', 'seo_description', 'seo_canonical', 'seo_image',
		'seo_site_url', 'seo_noindex', 'seo_jsonld',
		'og_site_name', 'og_title', 'og_description', 'og_image', 'og_image_alt',
		'og_image_width', 'og_image_height', 'og_image_type', 'base_href',
	);
	if (isset($theme) && is_object($theme)) {
		$tplVars = null;
		if (isset($theme->_tpl_vars) && is_array($theme->_tpl_vars))
			$tplVars = $theme->_tpl_vars;
		elseif (method_exists($theme, 'getTemplateVars'))
			$tplVars = $theme->getTemplateVars();
		elseif (method_exists($theme, 'get_template_vars'))
			$tplVars = $theme->get_template_vars();
		if (is_array($tplVars)) {
			foreach ($seoKeys as $sk) {
				if ((!array_key_exists($sk, $vars) || $vars[$sk] === '' || $vars[$sk] === null)
					&& array_key_exists($sk, $tplVars) && $tplVars[$sk] !== '' && $tplVars[$sk] !== null)
					$vars[$sk] = $tplVars[$sk];
			}
		}
	}

	$siteBase = '';
	if (!empty($vars['seo_site_url']))
		$siteBase = rtrim((string)$vars['seo_site_url'], '/');
	elseif (defined('SB_WP_URL') && SB_WP_URL !== '')
		$siteBase = rtrim((string)SB_WP_URL, '/');
	elseif (!empty($vars['asset_base']))
		$siteBase = rtrim((string)$vars['asset_base'], '/');

	$brand = '';
	if (!empty($GLOBALS['config']['template.title']))
		$brand = trim(strip_tags(stripslashes((string)$GLOBALS['config']['template.title'])));
	if ($brand === '')
		$brand = 'SourceBans';

	$bundle = function_exists('sb_seo_og_bundle')
		? sb_seo_og_bundle($brand)
		: null;

	if (empty($vars['og_site_name']))
		$vars['og_site_name'] = is_array($bundle) ? $bundle['og_site_name'] : $brand;
	if (empty($vars['og_title']))
		$vars['og_title'] = is_array($bundle)
			? $bundle['og_title']
			: ($vars['og_site_name'] . ' — игровые серверы');
	if (empty($vars['og_description']))
		$vars['og_description'] = is_array($bundle)
			? $bundle['og_description']
			: ('Онлайн, правила, банлист и админлист — ' . $vars['og_site_name']);
	if (empty($vars['seo_description']))
		$vars['seo_description'] = (is_array($bundle) && !empty($bundle['meta_description']))
			? $bundle['meta_description']
			: $vars['og_description'];
	if (empty($vars['seo_document_title']))
		$vars['seo_document_title'] = $vars['og_title'];
	if (empty($vars['seo_title']))
		$vars['seo_title'] = $brand;
	if (empty($vars['og_image_alt']))
		$vars['og_image_alt'] = $vars['og_title'];
	if (empty($vars['og_image_width']))
		$vars['og_image_width'] = is_array($bundle) ? (int)$bundle['og_image_width'] : 1200;
	if (empty($vars['og_image_height']))
		$vars['og_image_height'] = is_array($bundle) ? (int)$bundle['og_image_height'] : 630;
	if (empty($vars['og_image_type']))
		$vars['og_image_type'] = 'image/jpeg';

	if (empty($vars['og_image'])) {
		$img = is_array($bundle) ? $bundle['og_image'] : 'images/og-cover.jpg';
		if (function_exists('sb_seo_absolute_image_url'))
			$vars['og_image'] = sb_seo_absolute_image_url($img, $siteBase !== '' ? $siteBase : (is_array($bundle) ? $bundle['site_base'] : ''));
		else {
			if (!preg_match('#^https?://#i', $img))
				$img = ($siteBase !== '' ? $siteBase . '/' : '') . ltrim($img, '/');
			$vars['og_image'] = $img;
		}
	} elseif (!preg_match('#^https?://#i', (string)$vars['og_image']) && $siteBase !== '') {
		$vars['og_image'] = $siteBase . '/' . ltrim((string)$vars['og_image'], '/');
	}

	if (empty($vars['seo_canonical']))
		$vars['seo_canonical'] = ($siteBase !== '' ? $siteBase . '/' : '/');
	if (empty($vars['seo_site_url']))
		$vars['seo_site_url'] = ($siteBase !== '' ? $siteBase . '/' : '/');
	if (!array_key_exists('seo_noindex', $vars))
		$vars['seo_noindex'] = false;

	// Короткий title со страницы («Sibnet-Software.ru») всегда перекрываем SEO/OG.
	$vars['title'] = $vars['seo_document_title'];
}

function sb_ui_v2_enabled()
{
	return true;
}

function sb_ui_v2_force()
{
	if (isset($_GET['ui']) && strtolower(trim((string)$_GET['ui'])) === 'legacy'
		&& !(isset($_GET['p']) && $_GET['p'] === 'admin'))
		return;
	if (function_exists('sb_set_auth_cookie'))
		sb_set_auth_cookie('sb_ui', 'v2', time() + 86400 * 30);
	else
		@setcookie('sb_ui', 'v2', time() + 86400 * 30, defined('COOKIE_PATH') ? COOKIE_PATH : '/');
	$_COOKIE['sb_ui'] = 'v2';
}

function sb_ui_v2_theme_fragment($twigTemplate)
{
	global $theme;
	$vars = array();
	if (isset($theme) && is_object($theme) && isset($theme->_tpl_vars) && is_array($theme->_tpl_vars)) {
		foreach ($theme->_tpl_vars as $k => $v)
			$vars[$k] = $v;
	} elseif (isset($theme) && is_object($theme) && method_exists($theme, 'getTemplateVars')) {
		$all = $theme->getTemplateVars();
		if (is_array($all)) {
			foreach ($all as $k => $v)
				$vars[$k] = $v;
		}
	} elseif (isset($theme) && is_object($theme) && method_exists($theme, 'get_template_vars')) {
		$all = $theme->get_template_vars();
		if (is_array($all)) {
			foreach ($all as $k => $v)
				$vars[$k] = $v;
		}
	}
	echo sb_ui_v2_fragment($twigTemplate, $vars);
}

function sb_admin_echo_twig_fragment($template, $vars = array())
{
	$html = '';
	if (function_exists('sb_ui_v2_fragment'))
		$html = sb_ui_v2_fragment($template, is_array($vars) ? $vars : array());
	if (!is_string($html) || trim($html) === '') {
		$html = '<div class="form-page"><p class="form-flash form-flash--err">Не удалось показать «'
			. htmlspecialchars((string)$template, ENT_QUOTES, 'UTF-8')
			. '». Обновите страницу или проверьте журнал PHP.</p></div>';
	}
	echo $html;
}

function sb_ui_v2_register_twig_psr4($srcDir)
{
	spl_autoload_register(function ($class) use ($srcDir) {
		if (strncmp($class, 'Twig\\', 5) !== 0)
			return;
		$file = $srcDir . '/' . str_replace('\\', '/', substr($class, 5)) . '.php';
		if (is_file($file))
			require $file;
	});
}

function sb_ui_v2_autoload()
{
	static $ok = null;
	if ($ok !== null)
		return $ok;
	if (class_exists('Twig\\Environment', false)) {
		$ok = true;
		return true;
	}
	$root = rtrim(str_replace('\\', '/', defined('ROOT') ? ROOT : dirname(__DIR__) . '/'), '/') . '/';

	$composer = $root . 'vendor/autoload.php';
	if (is_readable($composer)) {
		require_once $composer;
		if (class_exists('Twig\\Environment', true)) {
			$ok = true;
			return true;
		}
	}

	// GitHub zip: includes/Twig-3.28.0 (PHP ≥8.1). Не грузить на 7.1 — parse fatal.
	if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100) {
		$twig3src = $root . 'includes/Twig-3.28.0/src';
		if (!is_dir($twig3src)) {
			$found = glob($root . 'includes/Twig-3.*/src');
			if (is_array($found) && isset($found[0]) && is_dir($found[0]))
				$twig3src = $found[0];
		}
		if (is_dir($twig3src)) {
			if (!function_exists('trigger_deprecation')) {
				function trigger_deprecation($package, $version, $message)
				{
					$args = func_get_args();
					array_shift($args);
					array_shift($args);
					array_shift($args);
					if ($args)
						$message = vsprintf($message, $args);
					@trigger_error($package . ' ' . $version . ': ' . $message, E_USER_DEPRECATED);
				}
			}
			sb_ui_v2_register_twig_psr4($twig3src);
			$resDir = $twig3src . '/Resources';
			foreach (array('core.php', 'debug.php', 'escaper.php', 'string_loader.php') as $res) {
				$f = $resDir . '/' . $res;
				if (is_readable($f))
					require_once $f;
			}
			$ok = class_exists('Twig\\Environment', true);
			return $ok;
		}
	}

	// Twig 1.44 is retained only for the PHP 7.1 legacy runtime. On modern PHP
	// it can fail during rendering; require Twig 3 (vendored or Composer).
	if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80200) {
		$ok = false;
		return false;
	}

	$twigRoot = $root . 'includes/twig';
	$src = $twigRoot . '/src';
	$lib = $twigRoot . '/lib';
	if (!is_dir($src)) {
		$ok = false;
		return false;
	}
	sb_ui_v2_register_twig_psr4($src);
	if (is_dir($lib)) {
		spl_autoload_register(function ($class) use ($lib) {
			if (strncmp($class, 'Twig_', 5) !== 0)
				return;
			$file = $lib . '/' . str_replace('_', '/', $class) . '.php';
			if (is_file($file))
				require $file;
		});
	}
	$ok = class_exists('Twig\\Environment', true);
	return $ok;
}

function sb_ui_v2_twig_root()
{
	return rtrim(str_replace('\\', '/', defined('ROOT') ? ROOT : dirname(__DIR__) . '/'), '/') . '/';
}

function sb_ui_v2_twig_precompile_enabled()
{
	if (empty($GLOBALS['config']) || !is_array($GLOBALS['config']))
		return false;
	if (!isset($GLOBALS['config']['config.twig.precompile']))
		return false;
	return (string)$GLOBALS['config']['config.twig.precompile'] === '1';
}

function sb_ui_v2_twig_cache_dir()
{
	return sb_ui_v2_twig_root() . 'cache/twig_predcompiled';
}

function sb_ui_v2_twig_cache_ensure()
{
	$dir = sb_ui_v2_twig_cache_dir();
	if (!is_dir($dir)) {
		if (!@mkdir($dir, 0755, true) && !is_dir($dir))
			return false;
	}
	return is_writable($dir);
}

/**
 * @return \Twig\Environment|null
 */
function sb_ui_v2_twig()
{
	static $twig = null;
	static $cacheKey = null;
	static $loggedUnwritable = false;

	$cacheDir = sb_ui_v2_twig_cache_dir();
	$enabled = sb_ui_v2_twig_precompile_enabled();
	$forceCache = !empty($GLOBALS['_sb_ui_v2_twig_force_cache']);
	$wantCache = $enabled || $forceCache;
	$writable = $wantCache ? sb_ui_v2_twig_cache_ensure() : false;
	if ($enabled && !$writable && !$loggedUnwritable) {
		$loggedUnwritable = true;
		@error_log('Blue V2 Twig cache: каталог недоступен для записи (' . $cacheDir . ')');
	}
	$useCache = $wantCache && $writable;
	$key = $useCache ? $cacheDir : '';

	if ($twig instanceof \Twig\Environment && $cacheKey === $key)
		return $twig;

	$twig = null;
	$cacheKey = null;
	if (!sb_ui_v2_autoload())
		return null;
	$root = sb_ui_v2_twig_root();
	try {
		$opts = array(
			'cache' => $useCache ? $cacheDir : false,
			'autoescape' => 'html',
			'strict_variables' => false,
		);
		if ($useCache)
			$opts['auto_reload'] = true;
		$loader = new \Twig\Loader\FilesystemLoader($root . 'themes/blue_v2/templates');
		$twig = new \Twig\Environment($loader, $opts);
		$cacheKey = $key;
	} catch (Throwable $e) {
		$GLOBALS['sb_ui_v2_twig_error'] = $e->getMessage();
		@error_log('Blue V2 Twig bootstrap: ' . $e->getMessage());
		return null;
	}
	return $twig;
}

/**
 * @return array{ok:int,fail:int,dir:string,errors:array}
 */
function sb_ui_v2_twig_precompile_all()
{
	$dir = sb_ui_v2_twig_cache_dir();
	$out = array(
		'ok' => 0,
		'fail' => 0,
		'dir' => $dir,
		'errors' => array(),
	);
	if (!sb_ui_v2_twig_cache_ensure()) {
		$out['fail'] = 1;
		$out['errors'][] = 'Каталог кеша недоступен для записи: ' . $dir;
		return $out;
	}

	$GLOBALS['_sb_ui_v2_twig_force_cache'] = true;
	$twig = sb_ui_v2_twig();
	unset($GLOBALS['_sb_ui_v2_twig_force_cache']);
	if (!$twig) {
		$out['fail'] = 1;
		$err = isset($GLOBALS['sb_ui_v2_twig_error']) ? (string)$GLOBALS['sb_ui_v2_twig_error'] : '';
		$out['errors'][] = $err !== '' ? $err : 'Twig не загружен.';
		return $out;
	}

	$tplDir = rtrim(str_replace('\\', '/', sb_ui_v2_twig_root() . 'themes/blue_v2/templates'), '/');
	if (!is_dir($tplDir)) {
		$out['fail'] = 1;
		$out['errors'][] = 'Каталог шаблонов не найден: ' . $tplDir;
		return $out;
	}

	$names = array();
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($tplDir, FilesystemIterator::SKIP_DOTS)
		);
		foreach ($it as $file) {
			if (!$file->isFile())
				continue;
			$name = $file->getFilename();
			if (strlen($name) < 6 || substr($name, -5) !== '.twig')
				continue;
			$full = str_replace('\\', '/', $file->getPathname());
			if (!is_readable($full))
				continue;
			$rel = substr($full, strlen($tplDir) + 1);
			if ($rel === false || $rel === '')
				continue;
			$names[] = str_replace('\\', '/', $rel);
		}
	} catch (Throwable $e) {
		$out['fail'] = 1;
		$out['errors'][] = $e->getMessage();
		return $out;
	}

	sort($names);
	foreach ($names as $rel) {
		try {
			$twig->load($rel);
			$out['ok']++;
		} catch (Throwable $e) {
			$out['fail']++;
			if (count($out['errors']) < 10)
				$out['errors'][] = $rel . ': ' . $e->getMessage();
		}
	}
	return $out;
}

/**
 * @return array{ok:bool,removed:int,dir:string}
 */
function sb_ui_v2_twig_cache_clear()
{
	$dir = sb_ui_v2_twig_cache_dir();
	$removed = 0;
	if (!is_dir($dir))
		return array('ok' => true, 'removed' => 0, 'dir' => $dir);

	$keep = array('.htaccess' => true, '.gitkeep' => true);
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $file) {
			$name = $file->getFilename();
			if (isset($keep[$name]))
				continue;
			$path = $file->getPathname();
			if ($file->isDir()) {
				@rmdir($path);
				continue;
			}
			if (@unlink($path))
				$removed++;
		}
	} catch (Throwable $e) {
		return array('ok' => false, 'removed' => $removed, 'dir' => $dir);
	}
	return array('ok' => true, 'removed' => $removed, 'dir' => $dir);
}

function sb_ui_v2_base_href()
{
	if (defined('SB_WP_URL') && SB_WP_URL !== '')
		return rtrim((string)SB_WP_URL, '/') . '/';
	$script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '/index.php';
	$dir = dirname($script);
	if ($dir === '/' || $dir === '.' || $dir === '\\')
		return '/';
	return rtrim($dir, '/') . '/';
}

function sb_ui_v2_fragment($template, array $vars = array())
{
	$twig = sb_ui_v2_twig();
	if (!$twig)
		return '<p class="form-flash form-flash--err">Twig не загружен, фрагмент «' . htmlspecialchars((string)$template, ENT_QUOTES, 'UTF-8') . '» не собран.</p>';
	$vars += array('asset_base' => sb_ui_v2_base_href());
	try {
		return $twig->render($template, $vars);
	} catch (Throwable $e) {
		return '<p class="form-flash form-flash--err">Шаблон «' . htmlspecialchars((string)$template, ENT_QUOTES, 'UTF-8') . '»: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
	}
}

function sb_ui_v2_page_select($page, $pages, $kind)
{
	$page = (int)$page;
	$pages = (int)$pages;
	if ($pages <= 1)
		return '';
	$kind = ($kind === 'C') ? 'C' : 'B';
	$advSearchJs = json_encode(isset($_GET['advSearch']) ? (string)$_GET['advSearch'] : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	$advTypeJs = json_encode(isset($_GET['advType']) ? (string)$_GET['advType'] : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	$html = '<label class="v2-page-label">Страница <select class="form-select form-select-sm v2-page-select" onchange=\'changePage(this,"' . $kind . '",' . $advSearchJs . ',' . $advTypeJs . ');\'>';
	for ($i = 1; $i <= $pages; $i++) {
		$sel = ($i === $page) ? ' selected="selected"' : '';
		$html .= '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
	}
	$html .= '</select> <span class="v2-page-of">из ' . $pages . '</span></label>';
	return $html;
}

function sb_ui_v2_page_arrows($listPage, $page, $end, $count, $advSearchString)
{
	$listPage = ($listPage === 'commslist') ? 'commslist' : 'banlist';
	$page = (int)$page;
	$q = '';
	if (isset($_GET['searchText']) && (string)$_GET['searchText'] !== '')
		$q .= '&amp;searchText=' . rawurlencode((string)$_GET['searchText']);
	$q .= htmlspecialchars((string)$advSearchString, ENT_QUOTES, 'UTF-8');
	$html = '<nav class="v2-page-arrows" aria-label="Страницы">';
	if ($page > 1)
		$html .= '<a class="v2-page-btn" href="index.php?p=' . $listPage . '&amp;page=' . ($page - 1) . $q . '"><i class="bi bi-chevron-left" aria-hidden="true"></i> Назад</a>';
	if ((int)$end < (int)$count)
		$html .= '<a class="v2-page-btn" href="index.php?p=' . $listPage . '&amp;page=' . ($page + 1) . $q . '">Вперёд <i class="bi bi-chevron-right" aria-hidden="true"></i></a>';
	$html .= '</nav>';
	return $html;
}

function sb_ui_v2_xajax_js()
{
	global $sbAjax, $xajax;
	$ajax = (isset($sbAjax) && is_object($sbAjax)) ? $sbAjax : ((isset($xajax) && is_object($xajax)) ? $xajax : null);
	if ($ajax && method_exists($ajax, 'printJavascript'))
		return $ajax->printJavascript('scripts', 'sb-api.js');
	return '';
}

function sb_ui_v2_render($template, array $vars)
{
	$twig = sb_ui_v2_twig();
	if (!$twig) {
		header('Content-Type: text/html; charset=utf-8');
		echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>UI v2</title></head><body>';
		echo '<p>Twig не найден. Нужен <code>includes/Twig-3.28.0</code> (PHP ≥8.1), <code>includes/twig</code> (PHP 7.1) или <code>composer install</code>.</p>';
		echo '</body></html>';
		return;
	}
	global $userbank;
	$logged = (isset($userbank) && is_object($userbank) && $userbank->is_logged_in());
	$voucherEnabled = isset($GLOBALS['config']['page.vay4er'])
		&& (string)$GLOBALS['config']['page.vay4er'] === '1';
	$avatar = 'images/default-avatar.jpg';
	if (function_exists('GetUserAvatar')) {
		try {
			$avatar = GetUserAvatar();
		} catch (Exception $e) {
			$avatar = 'images/default-avatar.jpg';
		}
	}
	$headerTitle = '';
	if (!empty($GLOBALS['config']['template.title']))
		$headerTitle = stripslashes((string)$GLOBALS['config']['template.title']);
	$brandPrimary = 'Blue Admin';
	$brandSecondary = 'SourceBans';
	if ($headerTitle !== '') {
		$parts = explode('|', $headerTitle, 2);
		$brandPrimary = trim($parts[0]);
		$brandSecondary = isset($parts[1]) ? trim($parts[1]) : '';
	}
	if ($brandPrimary === '' || strcasecmp($brandPrimary, 'Material Admin') === 0)
		$brandPrimary = 'Blue Admin';
	$vars += array(
		'asset_base' => sb_ui_v2_base_href(),
		'xajax_js' => sb_ui_v2_xajax_js(),
		'sb_js_ver' => (string)(@filemtime((defined('ROOT') ? ROOT : '') . 'scripts/sourcebans.js') ?: time()),
		'mt_js_ver' => (string)(@filemtime((defined('ROOT') ? ROOT : '') . 'scripts/mootools.js') ?: time()),
		'css_ver' => (string)max(
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/blue.css') ?: time()),
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/mobile.css') ?: 0),
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/wide.css') ?: 0),
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/admin_embed.css') ?: 0),
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/forms.css') ?: 0),
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/servers.css') ?: 0),
			(int)(@filemtime((defined('ROOT') ? ROOT : '') . 'themes/blue_v2/css/dashboard.css') ?: 0)
		),
		'sb_version' => defined('SB_VERSION') ? SB_VERSION : '',
		'nav_active' => isset($_GET['p']) ? (string)$_GET['p'] : '',
		'extra_js' => '',
		'logged_in' => $logged,
		'voucher_enabled' => $voucherEnabled,
		'username' => $logged ? (string)$userbank->GetProperty('user') : '',
		'avatar' => $avatar,
		'header_title' => $headerTitle,
		'brand_primary' => $brandPrimary,
		'brand_secondary' => $brandSecondary,
		'login_url' => 'index.php?p=login',
		'sb_csrf' => function_exists('sb_csrf_token') ? sb_csrf_token() : '',
		'sb_session' => function_exists('sb_session_client_meta') ? sb_session_client_meta() : array(),
		'nav_groups' => array(),
		'page_notices' => array(),
	);
	if (function_exists('sb_ui_v2_nav_groups')) {
		try {
			$navGroups = sb_ui_v2_nav_groups();
			if (is_array($navGroups))
				$vars['nav_groups'] = $navGroups;
		} catch (Throwable $e) {
			$vars['nav_groups'] = array();
		}
	}
	$vars['page_notices'] = sb_ui_v2_page_notices(isset($vars['nav_active']) ? $vars['nav_active'] : '');

	// SEO / Open Graph: $theme->_tpl_vars + fallback из SB_OG_* (config.php).
	// На проде layout уже новый, а без этих vars Discord/аудиторы видят пустой <head>.
	sb_ui_v2_apply_seo_vars($vars);

	$flash = '';
	if (function_exists('sb_consume_script_footer'))
		$flash .= (string)sb_consume_script_footer();
	if (function_exists('sb_ui_flash_script'))
		$flash .= (string)sb_ui_flash_script();
	if (function_exists('sb_list_action_flash_script'))
		$flash .= (string)sb_list_action_flash_script();
	if ($flash !== '')
		$vars['extra_js'] = (isset($vars['extra_js']) ? (string)$vars['extra_js'] : '') . $flash;
	try {
		echo $twig->render($template, $vars);
	} catch (Throwable $e) {
		header('Content-Type: text/html; charset=utf-8');
		echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Ошибка шаблона</title></head><body>';
		echo '<p>Не удалось собрать страницу «' . htmlspecialchars((string)$template, ENT_QUOTES, 'UTF-8') . '».</p>';
		echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
		echo '</body></html>';
	}
}

function sb_ui_v2_admin_href($url)
{
	$url = trim((string)$url);
	if ($url === '' || $url === '#')
		return $url;
	if (stripos($url, 'javascript:') === 0)
		return $url;
	if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) || strpos($url, 'index.php') === 0)
		return $url;
	if (isset($url[0]) && $url[0] === '/') {
		$hashAbs = '';
		$hashPosAbs = strpos($url, '#');
		if ($hashPosAbs !== false) {
			$hashAbs = substr($url, $hashPosAbs);
			$url = substr($url, 0, $hashPosAbs);
		}
		$queryAbs = '';
		$qPosAbs = strpos($url, '?');
		if ($qPosAbs !== false) {
			$queryAbs = substr($url, $qPosAbs + 1);
			$url = substr($url, 0, $qPosAbs);
		}
		if (preg_match('#^/admin(?:/([a-zA-Z0-9_]+))?(?:/(.*))?$#', rtrim($url, '/'), $mAbs)) {
			$hrefAbs = 'index.php?p=admin';
			if (!empty($mAbs[1]))
				$hrefAbs .= '&c=' . $mAbs[1];
			if (!empty($mAbs[2]))
				$hrefAbs .= '&o=' . rawurlencode($mAbs[2]);
			if ($queryAbs !== '')
				$hrefAbs .= '&' . $queryAbs;
			return $hrefAbs . $hashAbs;
		}
		return ($queryAbs !== '' ? ($url . '?' . $queryAbs) : $url) . $hashAbs;
	}

	$hash = '';
	$hashPos = strpos($url, '#');
	if ($hashPos !== false) {
		$hash = substr($url, $hashPos);
		$url = substr($url, 0, $hashPos);
	}
	$query = '';
	$qPos = strpos($url, '?');
	if ($qPos !== false) {
		$query = substr($url, $qPos + 1);
		$url = substr($url, 0, $qPos);
	}
	$url = preg_replace('#^\./#', '', $url);
	$url = rtrim($url, '/');

	if ($url === 'admin') {
		$href = 'index.php?p=admin';
		if ($query !== '')
			$href .= '&' . $query;
		return $href . $hash;
	}
	if (preg_match('#^admin/([a-zA-Z0-9_]+)(?:/(.*))?$#', $url, $m)) {
		$href = 'index.php?p=admin&c=' . $m[1];
		if (!empty($m[2]))
			$href .= '&o=' . rawurlencode($m[2]);
		if ($query !== '')
			$href .= '&' . $query;
		return $href . $hash;
	}
	return ($query !== '' ? ($url . '?' . $query) : $url) . $hash;
}

function sb_ui_v2_balance_admin_divs($html)
{
	$html = (string)$html;
	if ($html === '')
		return $html;
	if (stripos($html, '<div') === false && stripos($html, '</div>') === false)
		return $html;

	// Drop leftover CTabsMenu closers even when they are not at the very end
	// (a trailing <script> would hide them from a "$" regex). Unmatched </div>
	// would otherwise close wrap.twig's #admin-page-wrap and dump the body out
	// of .admin-embed-body — tabs stay, content vanishes.
	// Missing </div> (e.g. unclosed #admin-page-content) would pull <footer>
	// into #content and leave the site footer sitting under the last card.
	$len = strlen($html);
	$out = '';
	$depth = 0;
	$i = 0;
	while ($i < $len) {
		$lt = strpos($html, '<', $i);
		if ($lt === false) {
			$out .= substr($html, $i);
			break;
		}
		if ($lt > $i)
			$out .= substr($html, $i, $lt - $i);

		$head = substr($html, $lt, 16);

		if (substr($head, 0, 4) === '<!--') {
			$end = strpos($html, '-->', $lt + 4);
			if ($end === false) {
				$out .= substr($html, $lt);
				break;
			}
			$out .= substr($html, $lt, $end + 3 - $lt);
			$i = $end + 3;
			continue;
		}

		if (preg_match('/^<(script|style|textarea)\b/i', $head, $mRaw)) {
			$gt = strpos($html, '>', $lt);
			if ($gt === false) {
				$out .= substr($html, $lt);
				break;
			}
			$closePos = stripos($html, '</' . $mRaw[1], $gt + 1);
			if ($closePos === false) {
				$out .= substr($html, $lt);
				break;
			}
			$closeGt = strpos($html, '>', $closePos);
			if ($closeGt === false) {
				$out .= substr($html, $lt);
				break;
			}
			$out .= substr($html, $lt, $closeGt + 1 - $lt);
			$i = $closeGt + 1;
			continue;
		}

		if (preg_match('/^<\/div\s*>/i', $head, $mClose)) {
			if ($depth > 0) {
				$out .= $mClose[0];
				$depth--;
			}
			$i = $lt + strlen($mClose[0]);
			continue;
		}

		if (preg_match('/^<div\b/i', $head)) {
			$gt = strpos($html, '>', $lt);
			if ($gt === false) {
				$out .= substr($html, $lt);
				break;
			}
			$out .= substr($html, $lt, $gt + 1 - $lt);
			$depth++;
			$i = $gt + 1;
			continue;
		}

		$gt = strpos($html, '>', $lt);
		if ($gt === false) {
			$out .= substr($html, $lt);
			break;
		}
		$out .= substr($html, $lt, $gt + 1 - $lt);
		$i = $gt + 1;
	}
	if ($depth > 0)
		$out .= str_repeat('</div>', $depth);
	return $out;
}

function sb_ui_v2_mark_first_admin_pane($html)
{
	$html = (string)$html;
	if ($html === '' || strpos($html, 'admin-pane') === false)
		return $html;
	if (preg_match('/\badmin-pane\b[^>]*\bis-on\b|\bis-on\b[^>]*\badmin-pane\b/', $html))
		return $html;
	return preg_replace('/\bclass="([^"]*\badmin-pane\b[^"]*)"/', 'class="$1 is-on"', $html, 1);
}

function sb_ui_v2_fix_empty_form_actions($html)
{
	$html = (string)$html;
	if ($html === '' || (strpos($html, 'action=""') === false && strpos($html, "action=''") === false))
		return $html;
	$q = array();
	if (isset($_GET) && is_array($_GET)) {
		foreach ($_GET as $k => $v) {
			if (!is_string($k) || $k === 'ui')
				continue;
			if (is_array($v))
				continue;
			$q[$k] = (string)$v;
		}
	}
	if (!isset($q['p']) || $q['p'] === '')
		$q['p'] = 'admin';
	$action = 'index.php?' . http_build_query($q);
	$action = str_replace('&', '&amp;', $action);
	$html = str_replace('action=""', 'action="' . $action . '"', $html);
	$html = str_replace("action=''", "action='" . $action . "'", $html);
	return $html;
}

function sb_ui_v2_rewrite_legacy_hrefs($html)
{
	$html = (string)$html;
	if ($html === '' || strpos($html, 'admin') === false)
		return $html;
	$html = preg_replace_callback(
		'~\b(href|action)=([\'"])(/?admin(?:/[a-zA-Z0-9_]+)?(?:\?[^\'"]*)?(?:#[^\'"]*)?)\2~i',
		function ($m) {
			$href = sb_ui_v2_admin_href($m[3]);
			$href = str_replace('&', '&amp;', $href);
			return $m[1] . '=' . $m[2] . $href . $m[2];
		},
		$html
	);
	$html = preg_replace_callback(
		'~(sbGo|sbAdminBack)\(\s*(?:(\d+)\s*,\s*)?([\'"])(admin(?:/[a-zA-Z0-9_]+)?(?:\?[^\'"]*)?(?:#[^\'"]*)?)\3~',
		function ($m) {
			$prefix = $m[1] . '(';
			if (isset($m[2]) && $m[2] !== '')
				$prefix .= $m[2] . ', ';
			return $prefix . $m[3] . sb_ui_v2_admin_href($m[4]) . $m[3];
		},
		$html
	);
	return $html;
}

function sb_ui_v2_tab_href($url)
{
	$url = sb_ui_v2_admin_href($url);
	$url = trim((string)$url);
	if ($url === '' || $url === '#')
		return $url;
	if (stripos($url, 'javascript:') === 0 || strpos($url, 'index.php') === 0 || strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0)
		return $url;
	$hash = '';
	$hashPos = strpos($url, '#');
	if ($hashPos !== false) {
		$hash = substr($url, $hashPos);
		$url = substr($url, 0, $hashPos);
	}
	if (preg_match('#^[a-zA-Z0-9_]+$#', $url))
		return 'index.php?p=' . $url . $hash;
	return $url . $hash;
}

function sb_ui_v2_wrap($innerHtml, array $vars = array())
{
	$tabs = array();
	if (isset($GLOBALS['sb_v2_admin_tabs']) && is_array($GLOBALS['sb_v2_admin_tabs'])) {
		$i = 0;
		foreach ($GLOBALS['sb_v2_admin_tabs'] as $item) {
			if (!is_array($item) || empty($item['title']))
				continue;
			$id = isset($item['id']) ? (int)$item['id'] : $i;
			$external = !empty($item['external']);
			$url = isset($item['url']) ? sb_ui_v2_tab_href($item['url']) : '';
			$tabs[] = array(
				'id' => $id,
				'title' => (string)$item['title'],
				'external' => $external,
				'url' => $url,
				'active' => ($i === 0),
			);
			$i++;
		}
	}
	$c = isset($_GET['c']) ? (string)$_GET['c'] : '';
	$titleMap = array(
		'bans' => 'Баны',
		'comms' => 'Муты и гаги',
		'admins' => 'Админы',
		'servers' => 'Серверы',
		'groups' => 'Группы',
		'settings' => 'Настройки',
		'mods' => 'Моды',
		'pay_card' => 'Ваучеры',
		'menu' => 'Меню',
		'recidivism' => 'Нарушения',
		'parsec' => 'Твинки',
	);
	$crumb = isset($titleMap[$c]) ? $titleMap[$c] : 'Админка';
	$extra = isset($vars['extra_js']) ? (string)$vars['extra_js'] : '';
	$inner = sb_ui_v2_balance_admin_divs(sb_ui_v2_fix_empty_form_actions(sb_ui_v2_rewrite_legacy_hrefs((string)$innerHtml)));
	$inner = sb_ui_v2_mark_first_admin_pane($inner);
	$textOnly = trim(strip_tags($inner));
	$hasAdminStructure = preg_match('/\b(admin-page-content|admin-pane|form-page|admin-manage)\b/i', $inner);
	if ($textOnly === '' && !$hasAdminStructure) {
		$section = ($c !== '') ? htmlspecialchars($c, ENT_QUOTES, 'UTF-8') : 'admin';
		$inner = '<div class="form-page"><div class="form-flash form-flash--err">'
			. 'Раздел «' . $section . '» не сформировал содержимое. Проверьте журнал PHP и права доступа.'
			. '</div></div>';
	}
	$pageTitle = (isset($GLOBALS['TitleRewrite']) && (string)$GLOBALS['TitleRewrite'] !== '')
		? (string)$GLOBALS['TitleRewrite']
		: $crumb;
	$breadcrumb = array(
		array('label' => 'Главная', 'href' => 'index.php?p=home'),
		array('label' => 'Админка', 'href' => 'index.php?p=admin'),
	);
	if ($c !== '')
		$breadcrumb[] = array('label' => $pageTitle, 'href' => '');
	$vars['inner_html'] = $inner;
	$vars['admin_tabs'] = $tabs;
	$vars['title'] = isset($vars['title']) ? $vars['title'] : ($pageTitle . ' — Blue Admin');
	$vars['nav_active'] = 'admin';
	$vars['nav_c'] = $c;
	$vars['topbar_label'] = $pageTitle;
	$vars['breadcrumb'] = $breadcrumb;
	$vars['extra_js'] = $extra;
	sb_ui_v2_render('wrap.twig', $vars);
}
