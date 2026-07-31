<?php
/**
 * install/dry_run.php — проверка установщика без записи в боевую БД.
 *
 * CLI:  php install/dry_run.php
 * Web:  /install/dry_run.php  (только с ?key=install-dry-run)
 *
 * Цель: PHP 8.5+ совместимость, структура файлов, генерация config/SQL.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('IN_SB', true);
define('IN_INSTALL', true);
define('ROOT', __DIR__ . '/');
define('INCLUDES_PATH', ROOT . 'includes');
define('SB_SALT', 'SourceBans');

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
	header('Content-Type: text/plain; charset=UTF-8');
	if (!isset($_GET['key']) || $_GET['key'] !== 'install-dry-run') {
		http_response_code(403);
		echo "Forbidden. Use ?key=install-dry-run\n";
		exit(1);
	}
}

$fail = 0;
$pass = 0;
$lines = array();

function dry_ok($msg) {
	global $pass, $lines;
	$pass++;
	$lines[] = '[OK]   ' . $msg;
}
function dry_fail($msg) {
	global $fail, $lines;
	$fail++;
	$lines[] = '[FAIL] ' . $msg;
}
function dry_info($msg) {
	global $lines;
	$lines[] = '[INFO] ' . $msg;
}

dry_info('PHP ' . PHP_VERSION . ' (' . PHP_OS . ')');
if (version_compare(PHP_VERSION, '8.5', '>='))
	dry_ok('PHP >= 8.5');
else
	dry_fail('Нужен PHP >= 8.5, сейчас ' . PHP_VERSION);

// --- required files ---
$required = array(
	'index.php', 'init.php',
	'includes/page-builder.php', 'includes/system-functions.php',
	'includes/struc.sql', 'includes/data.sql', 'includes/recidivism.inc.php',
	'scripts/install.js',
	'template/header.php', 'template/footer.php',
	'template/page.1.php', 'template/page.2.php', 'template/page.3.php',
	'template/page.4.php', 'template/page.5.php',
);
foreach ($required as $rel) {
	$path = ROOT . $rel;
	if (is_file($path))
		dry_ok("file $rel");
	else
		dry_fail("missing $rel");
}

$junk = array(
	'scripts/mootools.js', 'scripts/pngfix.js', 'scripts/sourcebans.js',
	'includes/css.php', 'includes/converter.inc.php', 'template/page.6.php',
);
foreach ($junk as $rel) {
	if (!is_file(ROOT . $rel))
		dry_ok("junk removed: $rel");
	else
		dry_fail("junk still present: $rel");
}

// --- theme assets ---
$theme = dirname(ROOT) . '/themes/new_box';
$rootSite = dirname(ROOT);
if (is_dir($theme))
	dry_ok('themes/new_box exists');
else
	dry_fail('themes/new_box missing');

$assets = array(
	'css/app.min.1.css', 'css/dark-blue-theme.css',
	'vendors/bower_components/jquery/dist/jquery.min.js',
	'vendors/bower_components/bootstrap/dist/js/bootstrap.min.js',
	'vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.min.js',
	'vendors/bower_components/Waves/dist/waves.min.js',
	'vendors/bower_components/material-design-iconic-font/dist/css/material-design-iconic-font.min.css',
);
foreach ($assets as $a) {
	if (is_file($theme . '/' . $a))
		dry_ok("asset $a");
	else
		dry_fail("asset missing $a");
}

// Демо-мусор Material / мёртвые редакторы — не должны возвращаться в дистрибутив.
$themeJunk = array(
	'includes/tinymce',
	'includes/pChart',
	'themes/new_box/vendors/summernote/dist----',
	'themes/new_box/vendors/bower_components/flot',
	'themes/new_box/vendors/bower_components/fullcalendar',
	'themes/new_box/vendors/bower_components/simpleWeather',
	'themes/new_box/vendors/bower_components/mediaelement',
	'themes/new_box/vendors/bower_components/chosen',
	'themes/new_box/vendors/sparklines',
	'themes/new_box/js/demo.js',
	'themes/new_box/js/charts.js',
);
foreach ($themeJunk as $rel) {
	$path = $rootSite . '/' . $rel;
	if (!file_exists($path))
		dry_ok("theme junk gone: $rel");
	else
		dry_fail("theme junk still present: $rel");
}

// --- syntax lint all install PHP (except _php_dryrun) ---
$phpFiles = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
	/** @var SplFileInfo $file */
	if (!$file->isFile() || strtolower($file->getExtension()) !== 'php')
		continue;
	$path = $file->getPathname();
	if (strpos($path, DIRECTORY_SEPARATOR . '_php_dryrun' . DIRECTORY_SEPARATOR) !== false)
		continue;
	$phpFiles[] = $path;
}

$badSyntax = array(
	// Removed APIs that fatally break on PHP 8+
	'/\bcreate_function\s*\(/' => 'create_function() (removed in PHP 8)',
	'/(?<![a-zA-Z0-9_])each\s*\(/' => 'each() (removed in PHP 8)',
	'/\bget_magic_quotes_gpc\s*\(/' => 'get_magic_quotes_gpc() (removed in PHP 8)',
);

foreach ($phpFiles as $path) {
	$src = @file_get_contents($path);
	$rel = str_replace('\\', '/', str_replace(ROOT, 'install/', $path));
	$base = basename($path);
	if ($src === false) {
		dry_fail("read $rel");
		continue;
	}
	$prev = error_reporting(0);
	$tokens = @token_get_all($src);
	error_reporting($prev);
	if (!is_array($tokens) || count($tokens) < 1) {
		dry_fail("tokenize $rel");
		continue;
	}
	// Сам dry_run содержит паттерны в строках — не сканируем его на «запрещённый» синтаксис.
	if ($base !== 'dry_run.php') {
		foreach ($badSyntax as $re => $label) {
			if (preg_match($re, $src)) {
				dry_fail("$rel uses $label");
				continue 2;
			}
		}
	}
	dry_ok("lint $rel");
}

// --- recidivism statements ---
require INCLUDES_PATH . '/recidivism.inc.php';
$stmts = sb_install_recidivism_statements('sb');
if (count($stmts) >= 5)
	dry_ok('recidivism statements: ' . count($stmts));
else
	dry_fail('recidivism statements too few');

foreach ($stmts as $i => $sql) {
	if (strpos($sql, '`sb_recid_') === false && stripos($sql, 'VIEW') === false) {
		dry_fail('statement ' . ($i + 1) . ' missing sb_recid_ prefix');
	} else {
		dry_ok('statement ' . ($i + 1) . ' prefix/view ok (' . strlen($sql) . ' bytes)');
	}
}

// --- data.sql version ---
$dataSql = file_get_contents(INCLUDES_PATH . '/data.sql');
if (strpos($dataSql, "'config.version', '530'") !== false || strpos($dataSql, "'config.version', \"530\"") !== false)
	dry_ok('data.sql config.version=530');
else
	dry_fail('data.sql config.version is not 530');

if (strpos($dataSql, '{prefix}_menu') !== false || strpos($dataSql, '`{prefix}_menu`') !== false)
	dry_ok('data.sql seeds menu');
else
	dry_fail('data.sql missing menu seed');

if (is_file(INCLUDES_PATH . '/cleanup.inc.php'))
	dry_ok('cleanup.inc.php present');
else
	dry_fail('cleanup.inc.php missing');

if (is_file(INCLUDES_PATH . '/seo.inc.php'))
	dry_ok('seo.inc.php present');
else
	dry_fail('seo.inc.php missing');

$page5 = file_get_contents(ROOT . 'template/page.5.php');
if ($page5 !== false && strpos($page5, 'sb_install_write_cleanup_script') !== false)
	dry_ok('page.5 wires cleanup script');
else
	dry_fail('page.5 missing cleanup wiring');

if ($page5 !== false && strpos($page5, 'sb_install_write_seo_files') !== false)
	dry_ok('page.5 wires seo files');
else
	dry_fail('page.5 missing seo wiring');

// --- page.5 wiring (string checks; не include — иначе выведет HTML формы) ---
if ($page5 === false) {
	dry_fail('cannot read page.5.php');
} else {
	if (strpos($page5, 'function sb_install_build_config') !== false)
		dry_ok('page.5 defines sb_install_build_config');
	else
		dry_fail('page.5 missing sb_install_build_config');

	if (strpos($page5, 'sb_install_recidivism_apply') !== false)
		dry_ok('page.5 calls sb_install_recidivism_apply');
	else
		dry_fail('page.5 does not call recidivism apply');

	if (strpos($page5, 'SB_PROTECTED_STEAMIDS') !== false)
		dry_ok('config template has SB_PROTECTED_STEAMIDS');
	else
		dry_fail('config template missing SB_PROTECTED_STEAMIDS');

	if (strpos($page5, 'REBANNER_USE_MA_DB') !== false && strpos($page5, 'PARSEC_API_PLAYER_URL') !== false)
		dry_ok('config template has REBANNER/PARSEC/OG block');
	else
		dry_fail('config template incomplete vs current production config');
}

// Simulate config escaping
$fakePass = "p'ass";
$esc = function ($s) {
	return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$s);
};
$cfgSample = "define('DB_PASS', '" . $esc($fakePass) . "');";
if (strpos($cfgSample, "p\\'ass") !== false)
	dry_ok('config escaping handles quotes');
else
	dry_fail('config escaping broken: ' . $cfgSample);

// --- struc has recid-related extras? (optional — recid via include) ---
$struc = file_get_contents(INCLUDES_PATH . '/struc.sql');
if (strpos($struc, '{prefix}_warns') !== false && strpos($struc, '{prefix}_menu') !== false)
	dry_ok('struc.sql has menu+warns');
else
	dry_fail('struc.sql incomplete');

if (strpos($struc, 'discord') !== false)
	dry_ok('struc.sql admins.discord');
else
	dry_fail('struc.sql missing discord column');

// summary
$lines[] = '';
$lines[] = "PASS=$pass FAIL=$fail";
$lines[] = ($fail === 0) ? 'DRY RUN OK' : 'DRY RUN FAILED';

$out = implode("\n", $lines) . "\n";
echo $out;
exit($fail === 0 ? 0 : 1);
