<?php
if (!defined("IN_SB")) { echo "You should not be here. Only follow links!"; die(); }

require_once INCLUDES_PATH . '/recidivism.inc.php';
require_once INCLUDES_PATH . '/cleanup.inc.php';
require_once INCLUDES_PATH . '/seo.inc.php';

/**
 * Собирает содержимое config.php в актуальном формате панели.
 */
function sb_install_build_config($vars)
{
	$host = isset($vars['sbwpurl']) ? parse_url($vars['sbwpurl'], PHP_URL_HOST) : '';
	if (!$host)
		$host = 'localhost';
	$siteLabel = strtoupper($host);

	$esc = function ($s) {
		return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$s);
	};

	$protected = isset($vars['protected']) ? $vars['protected'] : '';
	$parsecPass = isset($vars['parsec_pass']) ? $vars['parsec_pass'] : '';
	$dbcfgPass = isset($vars['dbcfg_pass']) ? $vars['dbcfg_pass'] : '';
	$hostingUrl = isset($vars['hosting_url']) ? $vars['hosting_url'] : '';
	$hostingLabel = isset($vars['hosting_label']) && $vars['hosting_label'] !== ''
		? $vars['hosting_label']
		: 'Оплатить хостинг';
	$hostingNewtab = !empty($vars['hosting_newtab']) ? '1' : '0';
	// Антифрод (LinkedAccounts / PARSEC) — общий API Sibnet, не api.<свой-хост>.
	$parsecApi = 'https://api.sibnet-software.ru/api/player/';

	return "<?php
/**
 * config.php — сгенерирован установщиком SourceBans.
 * Проверь STEAMAPIKEY, SB_PROTECTED_STEAMIDS и PARSEC_* перед продом.
 */
if (!defined('IN_SB')) { echo 'You should not be here. Only follow links!'; die(); }

define('DB_HOST', '" . $esc($vars['server']) . "');
define('DB_USER', '" . $esc($vars['username']) . "');
define('DB_PASS', '" . $esc($vars['password']) . "');
define('DB_NAME', '" . $esc($vars['database']) . "');
define('DB_PREFIX', '" . $esc($vars['prefix']) . "');
define('DB_PORT', '" . $esc($vars['port']) . "');
define('STEAMAPIKEY', '" . $esc($vars['apikey']) . "');
define('SB_WP_URL', '" . $esc($vars['sbwpurl']) . "');

//define('DEVELOPER_MODE', true);
define('SB_MEM', '512M');

/**
 * Защищённые SteamID: нельзя забанить / удалить / снять права через панель.
 * Через запятую, формат STEAM_0:X:YYYYYY
 */
define('SB_PROTECTED_STEAMIDS', '" . $esc($protected) . "');

/** Re-Banner fingerprint tables — та же БД, что и панель (rebanner_*). */
define('REBANNER_USE_MA_DB', true);

/** PARSEC public API (LinkedAccounts). Пустая строка отключает HTTP lookup. */
define('PARSEC_API_PLAYER_URL', '" . $esc($parsecApi) . "');
/** Токен доступа к приватным LinkedAccounts PARSEC API. Пусто = публичный режим. */
define('PARSEC_API_PLAYER_TOKEN', '');

/**
 * Панель admin&c=parsec — запись is_banned только для этих SteamID
 * (плюс OWNER / WEB_SETTINGS) после пароля и toggle «режим записи».
 */
define('PARSEC_PANEL_WRITE_STEAMIDS', '" . $esc($protected) . "');

/** Пароль разблокировки write-режима PARSEC (твинки). Пусто = задать позже. */
define('PARSEC_PANEL_WRITE_PASSWORD', '" . $esc($parsecPass) . "');

/**
 * Пароль просмотра databases.cfg в админке (Серверы → конфиг БД).
 * Отдельный от пароля веб-аккаунта и PARSEC_PANEL_WRITE_PASSWORD.
 */
define('SB_DBCFG_VIEW_PASSWORD', '" . $esc($dbcfgPass) . "');

/** Пункт меню «Оплатить хостинг» (пустой URL = скрыть). */
define('SB_HOSTING_PAY_URL', '" . $esc($hostingUrl) . "');
define('SB_HOSTING_PAY_LABEL', '" . $esc($hostingLabel) . "');
define('SB_HOSTING_PAY_NEWTAB', '" . $esc($hostingNewtab) . "');

/**
 * API выпуска ваучеров: api/voucher_create.php
 * Пустой токен = API выключен. Не пароль админа — отдельная длинная строка (≥32).
 * Опционально SB_VOUCHER_API_ALLOW_IPS: whitelist IP через запятую.
 */
define('SB_VOUCHER_API_TOKEN', '');
define('SB_VOUCHER_API_ALLOW_IPS', '');

/** Open Graph / Discord / Telegram / Twitter превью.
 *  Обложку меняй файлом images/og-cover.jpg (1200×630) или путём ниже. */
define('SB_OG_SITE_NAME', '" . $esc($siteLabel) . "');
define('SB_OG_TITLE', '" . $esc($siteLabel . ' — игровые серверы') . "');
define('SB_OG_DESCRIPTION', 'Онлайн, правила, банлист и админлист.');
define('SB_OG_IMAGE', 'images/og-cover.jpg');
define('SB_OG_IMAGE_WIDTH', 1200);
define('SB_OG_IMAGE_HEIGHT', 630);
";
}

/**
 * data/db.php для PDO-фреймворка (на будущее / совместимость).
 */
function sb_install_build_db_php($vars)
{
	$esc = function ($s) {
		return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$s);
	};
	$prefix = isset($vars['prefix']) ? (string)$vars['prefix'] : 'sb';
	if ($prefix !== '' && substr($prefix, -1) !== '_')
		$prefix .= '_';

	return "<?php
if (!defined('IN_SB')) exit();

/**
 * This file contains all database configurations for
 * using in SourceBans in new DB Framework.
 */
\\DatabaseManager::CreateConfig('SourceBans', [
  'dsn'     => 'mysql:dbname=" . $esc($vars['database']) . ";host=" . $esc($vars['server']) . ";charset=UTF8;port=" . $esc($vars['port']) . "',
  'user'    => '" . $esc($vars['username']) . "',
  'pass'    => '" . $esc($vars['password']) . "',
  'prefix'  => '" . $esc($prefix) . "',
  'options' => [
    \\PDO::ATTR_ERRMODE  => \\PDO::ERRMODE_EXCEPTION
  ]
]);
";
}

function sb_install_write_db_php($siteRoot, $vars)
{
	$dataDir = rtrim($siteRoot, '/\\') . DIRECTORY_SEPARATOR . 'data';
	if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true))
		return false;
	if (!file_exists($dataDir . DIRECTORY_SEPARATOR . '.htaccess'))
		@file_put_contents($dataDir . DIRECTORY_SEPARATOR . '.htaccess', "Deny from all\n");
	return @file_put_contents($dataDir . DIRECTORY_SEPARATOR . 'db.php', sb_install_build_db_php($vars)) !== false;
}

/**
 * Secure-кука только если этот запрос реально пришёл по HTTPS.
 * COOKIE_SECURE в install/init.php может быть false — его не используем.
 */
function sb_install_request_is_https()
{
	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		return true;
	if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
		return true;
	if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
		$xf = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
		if (strtolower(trim($xf[0])) === 'https')
			return true;
	}
	if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
		return true;
	return false;
}

$sbInstall = (isset($_SESSION['sb_install']) && is_array($_SESSION['sb_install'])) ? $_SESSION['sb_install'] : array();
if (empty($sbInstall['db_ok'])) {
	while (ob_get_level() > 0)
		ob_end_clean();
	header('Location: index.php?step=2');
	exit;
}
if (empty($sbInstall['tables_ok'])) {
	while (ob_get_level() > 0)
		ob_end_clean();
	header('Location: index.php?step=4');
	exit;
}

$cfgVars = array(
	'server' => isset($sbInstall['server']) ? (string)$sbInstall['server'] : '',
	'username' => isset($sbInstall['username']) ? (string)$sbInstall['username'] : '',
	'password' => isset($sbInstall['password']) ? (string)$sbInstall['password'] : '',
	'database' => isset($sbInstall['database']) ? (string)$sbInstall['database'] : '',
	'prefix' => isset($sbInstall['prefix']) ? (string)$sbInstall['prefix'] : 'sb',
	'port' => isset($sbInstall['port']) ? (string)$sbInstall['port'] : '3306',
	'apikey' => isset($sbInstall['apikey']) ? (string)$sbInstall['apikey'] : '',
	'sbwpurl' => isset($sbInstall['sbwpurl']) ? (string)$sbInstall['sbwpurl'] : '',
	'protected' => '',
);
$cfgVars['prefix'] = preg_replace('/[^a-zA-Z0-9_]/', '', $cfgVars['prefix']);
if ($cfgVars['prefix'] === '')
	$cfgVars['prefix'] = 'sb';

$srv_cfg = '"driver_default"		"mysql"

	"sourcebans"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		"port"				"{port}"
	}

	"sourcecomms"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		"port"				"{port}"
	}
';
$srv_cfg = str_replace(
	array('{server}', '{user}', '{pass}', '{db}', '{port}'),
	array($cfgVars['server'], $cfgVars['username'], 'ВСТАВЬТЕ_ПАРОЛЬ', $cfgVars['database'], $cfgVars['port']),
	$srv_cfg
);

$configPath = defined('SB_CONFIG_PATH') ? SB_CONFIG_PATH : (dirname(ROOT) . '/config.php');
$configDirWritable = is_writable(dirname($configPath));
$configFileWritable = file_exists($configPath) ? is_writable($configPath) : $configDirWritable;

$installError = '';
if (isset($_POST['postd']) && $_POST['postd']) {
	$dbcfgPassPost = isset($_POST['dbcfg_pass']) ? trim((string)$_POST['dbcfg_pass']) : '';
	if (empty($_POST['uname']) || empty($_POST['pass1']) || empty($_POST['pass2']) || empty($_POST['steam']) || empty($_POST['email']) || $dbcfgPassPost === '') {
		$installError = 'Все поля должны быть заполнены (включая пароль просмотра databases.cfg).';
	} elseif ($_POST['pass1'] !== $_POST['pass2']) {
		$installError = 'Пароли не совпадают.';
	} elseif (strlen($dbcfgPassPost) < 8) {
		$installError = 'Пароль просмотра databases.cfg — минимум 8 символов.';
	} elseif (!preg_match(STEAM_FORMAT, $_POST['steam'])) {
		$installError = 'Некорректный STEAM ID (формат STEAM_X:Y:Z).';
	} else {
		require ROOT . '../includes/adodb/adodb.inc.php';
		include_once ROOT . '../includes/adodb/adodb-errorhandler.inc.php';
		$dsn = 'mysqli://' . rawurlencode($cfgVars['username']) . ':' . rawurlencode($cfgVars['password']) . '@' . $cfgVars['server'] . ':' . $cfgVars['port'] . '/' . $cfgVars['database'];
		$db = ADONewConnection($dsn);
		if (!$db) {
			$installError = 'Нет соединения с БД. Проверьте данные.';
		} else {
			$GLOBALS['db'] = $db;
			$db->Execute('SET NAMES `utf8`');

			$prefix = $cfgVars['prefix'];
			$errors = 0;
			$aid = null;
			$passHash = sha1(sha1(SB_SALT . $_POST['pass1']));
			$extraflags = (1 << 24);
			$immunity = 100;
			$existing = $db->GetRow('SELECT `aid` FROM `' . $prefix . '_admins` WHERE `user` = ?', array($_POST['uname']));
			if (is_array($existing) && isset($existing['aid'])) {
				$aid = (int)$existing['aid'];
				$upd = $db->Prepare('UPDATE `' . $prefix . '_admins` SET `authid` = ?, `password` = ?, `gid` = ?, `email` = ?, `extraflags` = ?, `immunity` = ? WHERE `aid` = ?');
				if (!$db->Execute($upd, array(
					$_POST['steam'],
					$passHash,
					-1,
					$_POST['email'],
					$extraflags,
					$immunity,
					$aid
				))) {
					$errors++;
					$aid = null;
				}
			} elseif ($existing === false) {
				$errors++;
			} else {
				$admin = $db->Prepare('INSERT INTO `' . $prefix . '_admins` (`user`,`authid`,`password`,`gid`,`email`,`extraflags`,`immunity`,`expired`) VALUES (?,?,?,?,?,?,?,?)');
				if ($db->Execute($admin, array(
					$_POST['uname'],
					$_POST['steam'],
					$passHash,
					-1,
					$_POST['email'],
					$extraflags,
					$immunity,
					0
				))) {
					$aid = (int)$db->Insert_ID();
					if ($aid <= 0) {
						$errors++;
						$aid = null;
					}
				} else {
					$errors++;
				}
			}

			$hasWebSession = false;
			$webCol = $db->GetRow("SHOW COLUMNS FROM `" . $prefix . "_admins` WHERE Field = 'web_session'");
			if (is_array($webCol) && !empty($webCol)) {
				$hasWebSession = true;
			} elseif (is_array($webCol)) {
				if ($db->Execute("ALTER TABLE `" . $prefix . "_admins` ADD `web_session` VARCHAR(64) NULL DEFAULT NULL"))
					$hasWebSession = true;
				else
					$errors++;
			} else {
				$errors++;
			}

			$dataPath = INCLUDES_PATH . '/data.sql';
			$file = is_readable($dataPath) ? file_get_contents($dataPath) : false;
			if ($file === false) {
				$errors++;
			} else {
				$file = str_replace('{prefix}', $prefix, $file);
				foreach (explode(';', $file) as $q) {
					$q = trim($q);
					if (strlen($q) > 2) {
						if (!$db->Execute($q))
							$errors++;
					}
				}
			}

			$recid = sb_install_recidivism_apply($db, $prefix);
			$errors += (int)$recid['errors'];

			if ($aid !== null && $hasWebSession) {
				if (function_exists('random_bytes'))
					$installToken = bin2hex(random_bytes(32));
				else
					$installToken = bin2hex(openssl_random_pseudo_bytes(32));
				if ($db->Execute("UPDATE `" . $prefix . "_admins` SET `web_session` = ? WHERE `aid` = ?", array(hash('sha256', $installToken), $aid))) {
					$cookieSecure = sb_install_request_is_https();
					setcookie('aid', (string)$aid, time() + LOGIN_COOKIE_LIFETIME, COOKIE_PATH, COOKIE_DOMAIN, $cookieSecure, true);
					setcookie('password', 's.' . $installToken, time() + LOGIN_COOKIE_LIFETIME, COOKIE_PATH, COOKIE_DOMAIN, $cookieSecure, true);
				} else {
					$errors++;
				}
			}

			$parsecPass = isset($_POST['parsec_pass']) ? (string)$_POST['parsec_pass'] : '';
			$writeVars = array_merge($cfgVars, array(
				'prefix' => $prefix,
				'protected' => $_POST['steam'],
				'parsec_pass' => $parsecPass,
				'dbcfg_pass' => $dbcfgPassPost,
				'hosting_url' => isset($_POST['hosting_url']) ? trim((string)$_POST['hosting_url']) : '',
				'hosting_label' => isset($_POST['hosting_label']) ? trim((string)$_POST['hosting_label']) : 'Оплатить хостинг',
				'hosting_newtab' => (isset($_POST['hosting_newtab']) && $_POST['hosting_newtab'] === 'on') ? 1 : 0,
			));
			$web_cfg = sb_install_build_config($writeVars);
			$web_cfg_display = sb_install_build_config(array_merge($writeVars, array(
				'password' => 'ВСТАВЬТЕ_ПАРОЛЬ_MYSQL',
				'parsec_pass' => ($parsecPass !== '') ? 'ВСТАВЬТЕ_ПАРОЛЬ_ТВИНКОВ' : '',
				'dbcfg_pass' => 'ВСТАВЬТЕ_ПАРОЛЬ_DATABASES_CFG',
			)));
			$configWrote = false;
			if ($configFileWritable)
				$configWrote = (@file_put_contents($configPath, $web_cfg) !== false);

			$dbPhpWrote = sb_install_write_db_php(dirname(ROOT), array(
				'server' => $cfgVars['server'],
				'username' => $cfgVars['username'],
				'password' => $cfgVars['password'],
				'database' => $cfgVars['database'],
				'prefix' => $prefix,
				'port' => $cfgVars['port'],
			));

			$cleanup = sb_install_write_cleanup_script(dirname(ROOT));
			$seo = sb_install_write_seo_files(dirname(ROOT), $cfgVars['sbwpurl']);
			?>
			<div class="card m-b-0" id="messages-main">
				<?php $installStep = 5; $installFinished = true; include TEMPLATES_PATH . '/install-progress.php'; ?>
				<div class="ms-body">
					<div class="listview lv-message">
						<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Установка завершена</span></div></div>
						<div class="lv-body p-15">
							<?php if ($errors > 0): ?>
								<p class="c-red">Часть данных не записалась (ошибок: <?php echo (int)$errors; ?>). Проверьте лог MySQL.</p>
							<?php else: ?>
								<p>База заполнена, главный администратор создан, схема рецидивизма установлена<?php echo $configWrote ? ', <code>config.php</code> записан' : ''; ?><?php echo !empty($dbPhpWrote) ? ', <code>data/db.php</code> записан' : ''; ?><?php echo !empty($seo['ok']) ? ', SEO (<code>sitemap.xml</code>, <code>robots.txt</code>, <code>og-cover.jpg</code>) обновлены' : ''; ?>.</p>
							<?php endif; ?>
							<?php if (empty($seo['ok'])): ?>
								<p class="c-red m-t-10">SEO-файлы не записались<?php echo !empty($seo['error']) ? ': ' . htmlspecialchars($seo['error'], ENT_QUOTES, 'UTF-8') : ''; ?>. Поправь вручную <code>sitemap.xml</code> и <code>robots.txt</code>.</p>
							<?php endif; ?>
							<?php if (!empty($recid['logs'])): ?>
								<ul class="m-t-10">
									<?php foreach ($recid['logs'] as $line): ?>
										<li><code><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></code></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>

						<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">databases.cfg (игровой сервер)</span></div></div>
						<div class="lv-body p-15">
							<p>Вставьте в <code>addons/sourcemod/configs/databases.cfg</code>. Пароль в поле <code>pass</code> заменён на заглушку — это тот же пароль MySQL, который вы ввели на форме подключения к базе.</p>
							<textarea class="form-control" rows="16" readonly><?php echo htmlspecialchars($srv_cfg, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<?php if (!$configWrote): ?>
						<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">config.php вручную</span></div></div>
						<div class="lv-body p-15">
							<p>Файл <code>config.php</code> в корне сайта недоступен для записи. Создайте его сами. Пароль MySQL, пароль твинков и пароль databases.cfg в тексте ниже заменены на заглушки — подставьте те же значения, что вводили в формах установщика.</p>
							<textarea class="form-control" rows="22" readonly><?php echo htmlspecialchars($web_cfg_display, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>
						<?php endif; ?>

						<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Обязательно: удаление установщика</span></div></div>
						<div class="lv-body p-15">
							<p>Папку <code>install/</code> нельзя оставлять на сервере. Сначала скопируйте <code>config.php</code> и <code>databases.cfg</code> — удаление установщика только по кнопке ниже.</p>
							<?php if (!empty($cleanup['ok']) && !empty($cleanup['url'])): ?>
								<p class="m-t-10">
									<a class="btn bgm-red btn-lg" id="btn-remove-setup"
									   href="<?php echo htmlspecialchars($cleanup['url'], ENT_QUOTES, 'UTF-8'); ?>">
										Удалить установщик и продолжить
									</a>
								</p>
								<p class="c-gray m-t-10">Автоперехода нет: удаление запускается только этой кнопкой.</p>
							<?php else: ?>
								<p class="c-red">Не удалось создать <code>remove_setup.php</code><?php echo !empty($cleanup['error']) ? ': ' . htmlspecialchars($cleanup['error'], ENT_QUOTES, 'UTF-8') : ''; ?>.</p>
								<p>Удали вручную папку <code>install/</code> (и <code>updater/</code>, если есть), затем открой <a href="../index.php">сайт</a>.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
			if (strtolower($cfgVars['server']) === 'localhost' || $cfgVars['server'] === '127.0.0.1') {
				echo '<script>ShowBox("Локальный MySQL", "Если игровой сервер на другой машине — в databases.cfg замените localhost на IP веб-сервера.", "blue", "", true);</script>';
			}
			include TEMPLATES_PATH . '/footer.php';
			die();
		}
	}
}

$formPosted = isset($_POST['postd']) && $_POST['postd'];
$formUname = ($formPosted && isset($_POST['uname'])) ? (string)$_POST['uname'] : '';
$formSteam = ($formPosted && isset($_POST['steam'])) ? (string)$_POST['steam'] : '';
$formEmail = ($formPosted && isset($_POST['email'])) ? (string)$_POST['email'] : '';
$formHostingUrl = ($formPosted && isset($_POST['hosting_url'])) ? (string)$_POST['hosting_url'] : '';
$formHostingLabel = ($formPosted && isset($_POST['hosting_label'])) ? (string)$_POST['hosting_label'] : 'Оплатить хостинг';
$formHostingNewtab = !$formPosted || (isset($_POST['hosting_newtab']) && $_POST['hosting_newtab'] === 'on');
?>
<form action="" name="mfrm" id="mfrm" method="post">
	<div class="card m-b-0" id="messages-main">
		<?php $installStep = 5; include TEMPLATES_PATH . '/install-progress.php'; ?>
		<div class="ms-body">
			<div class="listview lv-message">
				<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Главный администратор</span></div></div>
				<div class="lv-body p-15">
					<?php if ($installError !== ''): ?>
						<p class="c-red m-b-15"><?php echo htmlspecialchars($installError, ENT_QUOTES, 'UTF-8'); ?></p>
						<script>ShowBox('Ошибка', <?php echo json_encode($installError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>, 'red', '', true);</script>
					<?php endif; ?>
					<p class="c-gray m-b-15">На этом шаге также будут установлены таблицы рецидивизма (<code><?php echo htmlspecialchars($cfgVars['prefix'], ENT_QUOTES, 'UTF-8'); ?>_recid_*</code>) и записан полный <code>config.php</code>.</p>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="uname"><?php echo HelpIcon('Имя', 'Логин владельца панели'); ?>Имя</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="uname" name="uname" placeholder="Логин" autocomplete="username" value="<?php echo htmlspecialchars($formUname, ENT_QUOTES, 'UTF-8'); ?>" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="pass1"><?php echo HelpIcon('Пароль', 'Пароль для входа в веб-панель'); ?>Пароль</label>
							<div class="col-sm-9"><div class="fg-line"><input type="password" class="form-control input-sm" id="pass1" name="pass1" placeholder="Пароль" autocomplete="new-password" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="pass2">Подтверждение</label>
							<div class="col-sm-9"><div class="fg-line"><input type="password" class="form-control input-sm" id="pass2" name="pass2" placeholder="Повтор пароля" autocomplete="new-password" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="steam"><?php echo HelpIcon('STEAM', 'Формат STEAM_0:X:YYYYYY — попадёт в SB_PROTECTED_STEAMIDS'); ?>STEAM ID</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="steam" name="steam" placeholder="STEAM_0:0:12345" value="<?php echo htmlspecialchars($formSteam, ENT_QUOTES, 'UTF-8'); ?>" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="email">E-mail</label>
							<div class="col-sm-9"><div class="fg-line"><input type="email" class="form-control input-sm" id="email" name="email" placeholder="admin@example.com" autocomplete="email" value="<?php echo htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8'); ?>" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="dbcfg_pass"><?php echo HelpIcon('Пароль databases.cfg', 'SB_DBCFG_VIEW_PASSWORD — отдельный пароль для просмотра конфига БД SourceMod в админке. Обязателен, минимум 8 символов. Не путать с паролем аккаунта и паролем твинков.'); ?>Пароль databases.cfg</label>
							<div class="col-sm-9"><div class="fg-line"><input type="password" class="form-control input-sm" id="dbcfg_pass" name="dbcfg_pass" placeholder="минимум 8 символов" autocomplete="new-password" required /></div></div>
						</div>
					</div>
					<p class="c-gray m-b-0">Этот пароль попадёт в <code>config.php</code> и понадобится OWNER, чтобы снова открыть блок <code>databases.cfg</code> в админке.</p>
				</div>

				<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Опционально — config.php</span></div></div>
				<div class="lv-body p-15">
					<p class="c-gray m-b-15">Можно оставить пустым и прописать позже в <code>config.php</code>.</p>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="parsec_pass"><?php echo HelpIcon('Пароль твинков', 'PARSEC_PANEL_WRITE_PASSWORD — пароль для write-режима панели твинков/связок. Пусто = отключено до ручной правки config.php.'); ?>Пароль твинков</label>
							<div class="col-sm-9"><div class="fg-line"><input type="password" class="form-control input-sm" id="parsec_pass" name="parsec_pass" placeholder="необязательно" autocomplete="new-password" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="hosting_url"><?php echo HelpIcon('Оплата хостинга', 'SB_HOSTING_PAY_URL — ссылка пункта меню. Пустой URL = пункт скрыт.'); ?>URL оплаты хостинга</label>
							<div class="col-sm-9"><div class="fg-line"><input type="url" class="form-control input-sm" id="hosting_url" name="hosting_url" placeholder="https://… (необязательно)" value="<?php echo htmlspecialchars($formHostingUrl, ENT_QUOTES, 'UTF-8'); ?>" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="hosting_label">Подпись пункта</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="hosting_label" name="hosting_label" value="<?php echo htmlspecialchars($formHostingLabel, ENT_QUOTES, 'UTF-8'); ?>" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="hosting_newtab">Открывать в новой вкладке</label>
							<div class="col-sm-9 p-t-10">
								<div class="checkbox m-b-15">
									<label>
										<input type="checkbox" name="hosting_newtab" id="hosting_newtab"<?php echo $formHostingNewtab ? ' checked="checked"' : ''; ?> />
										Да
									</label>
								</div>
							</div>
						</div>
					</div>

					<div class="p-10" align="center">
						<a href="index.php?step=4" class="btn btn-info">Назад</a>
						<button type="button" onclick="CheckInput();" class="btn btn-primary">Завершить установку</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" name="postd" value="1" />
</form>
<script>
function CheckInput() {
	var miss = 0;
	['uname','pass1','pass2','steam','email','dbcfg_pass'].forEach(function (id) {
		if (!$id(id) || !$id(id).value) miss++;
	});
	if (miss > 0) ShowBox('Ошибка', 'Все поля должны быть заполнены (включая пароль databases.cfg).', 'red', '', true);
	else if ($id('pass1').value !== $id('pass2').value) ShowBox('Ошибка', 'Пароли не совпадают.', 'red', '', true);
	else if ($id('dbcfg_pass').value.length < 8) ShowBox('Ошибка', 'Пароль просмотра databases.cfg — минимум 8 символов.', 'red', '', true);
	else $id('mfrm').submit();
}
window.sbInstallEnter = CheckInput;
</script>
