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

$cfgVars = array(
	'server' => isset($_POST['server']) ? $_POST['server'] : '',
	'username' => isset($_POST['username']) ? $_POST['username'] : '',
	'password' => isset($_POST['password']) ? $_POST['password'] : '',
	'database' => isset($_POST['database']) ? $_POST['database'] : '',
	'prefix' => isset($_POST['prefix']) ? $_POST['prefix'] : 'sb',
	'port' => isset($_POST['port']) ? $_POST['port'] : '3306',
	'apikey' => isset($_POST['apikey']) ? $_POST['apikey'] : '',
	'sbwpurl' => isset($_POST['sb-wp-url']) ? $_POST['sb-wp-url'] : '',
	'protected' => '',
);

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
	array($cfgVars['server'], $cfgVars['username'], $cfgVars['password'], $cfgVars['database'], $cfgVars['port']),
	$srv_cfg
);

$configPath = defined('SB_CONFIG_PATH') ? SB_CONFIG_PATH : (dirname(ROOT) . '/config.php');
$configDirWritable = is_writable(dirname($configPath));
$configFileWritable = file_exists($configPath) ? is_writable($configPath) : $configDirWritable;

if (isset($_POST['postd']) && $_POST['postd']) {
	if (empty($_POST['uname']) || empty($_POST['pass1']) || empty($_POST['pass2']) || empty($_POST['steam']) || empty($_POST['email'])) {
		echo "<script>setTimeout(function(){ ShowBox('Ошибка', 'Все поля должны быть заполнены.', 'red', '', true); }, 200);</script>";
	} elseif ($_POST['pass1'] !== $_POST['pass2']) {
		echo "<script>setTimeout(function(){ ShowBox('Ошибка', 'Пароли не совпадают.', 'red', '', true); }, 200);</script>";
	} elseif (!preg_match(STEAM_FORMAT, $_POST['steam'])) {
		echo "<script>setTimeout(function(){ ShowBox('Ошибка', 'Некорректный STEAM ID (формат STEAM_X:Y:Z).', 'red', '', true); }, 200);</script>";
	} else {
		require ROOT . '../includes/adodb/adodb.inc.php';
		include_once ROOT . '../includes/adodb/adodb-errorhandler.inc.php';
		$dsn = 'mysqli://' . $_POST['username'] . ':' . $_POST['password'] . '@' . $_POST['server'] . ':' . $_POST['port'] . '/' . $_POST['database'];
		$db = ADONewConnection($dsn);
		if (!$db) {
			echo "<script>setTimeout(function(){ ShowBox('Ошибка', 'Нет соединения с БД. Проверьте данные.', 'red', '', true); }, 200);</script>";
		} else {
			$GLOBALS['db'] = $db;
			$db->Execute('SET NAMES `utf8`');

			$prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['prefix']);
			$admin = $db->Prepare('INSERT INTO `' . $prefix . '_admins` (`user`,`authid`,`password`,`gid`,`email`,`extraflags`,`immunity`,`expired`) VALUES (?,?,?,?,?,?,?,?)');
			$db->Execute($admin, array(
				$_POST['uname'],
				$_POST['steam'],
				sha1(sha1(SB_SALT . $_POST['pass1'])),
				-1,
				$_POST['email'],
				(1 << 24),
				100,
				0
			));

			@$db->Execute("ALTER TABLE `" . $prefix . "_admins` ADD `web_session` VARCHAR(64) NULL DEFAULT NULL");
			if (function_exists('random_bytes'))
				$installToken = bin2hex(random_bytes(32));
			else
				$installToken = bin2hex(openssl_random_pseudo_bytes(32));
			$db->Execute("UPDATE `" . $prefix . "_admins` SET `web_session` = ? WHERE `aid` = 1", array(hash('sha256', $installToken)));
			setcookie('aid', '1', time() + LOGIN_COOKIE_LIFETIME, COOKIE_PATH, COOKIE_DOMAIN, false, true);
			setcookie('password', 's.' . $installToken, time() + LOGIN_COOKIE_LIFETIME, COOKIE_PATH, COOKIE_DOMAIN, false, true);

			$errors = 0;
			$file = file_get_contents(INCLUDES_PATH . '/data.sql');
			$file = str_replace('{prefix}', $prefix, $file);
			foreach (explode(';', $file) as $q) {
				$q = trim($q);
				if (strlen($q) > 2) {
					if (!$db->Execute($q))
						$errors++;
				}
			}

			$recid = sb_install_recidivism_apply($db, $prefix);
			$errors += (int)$recid['errors'];

			$cfgVars['protected'] = $_POST['steam'];
			$web_cfg = sb_install_build_config(array_merge($cfgVars, array(
				'server' => $_POST['server'],
				'username' => $_POST['username'],
				'password' => $_POST['password'],
				'database' => $_POST['database'],
				'prefix' => $prefix,
				'port' => $_POST['port'],
				'apikey' => isset($_POST['apikey']) ? $_POST['apikey'] : '',
				'sbwpurl' => isset($_POST['sb-wp-url']) ? $_POST['sb-wp-url'] : '',
				'protected' => $_POST['steam'],
				'parsec_pass' => isset($_POST['parsec_pass']) ? $_POST['parsec_pass'] : '',
				'hosting_url' => isset($_POST['hosting_url']) ? trim($_POST['hosting_url']) : '',
				'hosting_label' => isset($_POST['hosting_label']) ? trim($_POST['hosting_label']) : 'Оплатить хостинг',
				'hosting_newtab' => (isset($_POST['hosting_newtab']) && $_POST['hosting_newtab'] === 'on') ? 1 : 0,
			)));
			$configWrote = false;
			if ($configFileWritable)
				$configWrote = (@file_put_contents($configPath, $web_cfg) !== false);

			$dbPhpWrote = sb_install_write_db_php(dirname(ROOT), array(
				'server' => $_POST['server'],
				'username' => $_POST['username'],
				'password' => $_POST['password'],
				'database' => $_POST['database'],
				'prefix' => $prefix,
				'port' => $_POST['port'],
			));

			$cleanup = sb_install_write_cleanup_script(dirname(ROOT));
			$seo = sb_install_write_seo_files(
				dirname(ROOT),
				isset($_POST['sb-wp-url']) ? $_POST['sb-wp-url'] : ''
			);
			?>
			<div class="card m-b-0" id="messages-main">
				<div class="ms-menu">
					<div class="ms-block p-10"><span class="c-black"><b>Процесс</b></span></div>
					<div class="listview lv-user" id="install-progress">
						<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">1</div><div class="media-body"><div class="lv-title"><del>Лицензия</del></div></div></div>
						<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">2</div><div class="media-body"><div class="lv-title"><del>База данных</del></div></div></div>
						<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">3</div><div class="media-body"><div class="lv-title"><del>Требования</del></div></div></div>
						<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">4</div><div class="media-body"><div class="lv-title"><del>Таблицы</del></div></div></div>
						<div class="lv-item media active"><div class="lv-avatar bgm-red pull-left">5</div><div class="media-body"><div class="lv-title">Готово</div><div class="lv-small"><i class="zmdi zmdi-badge-check c-green"></i> Финиш</div></div></div>
					</div>
				</div>
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
							<p>Вставьте в <code>addons/sourcemod/configs/databases.cfg</code>:</p>
							<textarea class="form-control" rows="16" readonly><?php echo htmlspecialchars($srv_cfg, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<?php if (!$configWrote): ?>
						<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">config.php вручную</span></div></div>
						<div class="lv-body p-15">
							<p>Файл <code>config.php</code> в корне сайта недоступен для записи. Создайте его сами:</p>
							<textarea class="form-control" rows="22" readonly><?php echo htmlspecialchars($web_cfg, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>
						<?php endif; ?>

						<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Обязательно: удаление установщика</span></div></div>
						<div class="lv-body p-15">
							<p>Папку <code>install/</code> нельзя оставлять на сервере. Нажми кнопку ниже — она снесёт установщик и одноразовый скрипт.</p>
							<?php if (!empty($cleanup['ok']) && !empty($cleanup['url'])): ?>
								<p class="m-t-10">
									<a class="btn bgm-red waves-effect btn-lg" id="btn-remove-setup"
									   href="<?php echo htmlspecialchars($cleanup['url'], ENT_QUOTES, 'UTF-8'); ?>">
										Удалить установщик и продолжить
									</a>
								</p>
								<p class="c-gray m-t-10">Через 60 секунд удаление запустится автоматически…</p>
								<script>
								(function () {
									var url = <?php echo json_encode($cleanup['url'], JSON_UNESCAPED_UNICODE); ?>;
									setTimeout(function () { window.location.replace(url); }, 60000);
								})();
								</script>
							<?php else: ?>
								<p class="c-red">Не удалось создать <code>remove_setup.php</code><?php echo !empty($cleanup['error']) ? ': ' . htmlspecialchars($cleanup['error'], ENT_QUOTES, 'UTF-8') : ''; ?>.</p>
								<p>Удали вручную папку <code>install/</code> (и <code>updater/</code>, если есть), затем открой <a href="../index.php">сайт</a>.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
			if (strtolower($_POST['server']) === 'localhost' || $_POST['server'] === '127.0.0.1') {
				echo '<script>setTimeout(function(){ ShowBox("Локальный MySQL", "Если игровой сервер на другой машине — в databases.cfg замените localhost на IP веб-сервера.", "blue", "", true); }, 400);</script>';
			}
		}
	}
	include TEMPLATES_PATH . '/footer.php';
	die();
}

$web_cfg_preview = sb_install_build_config($cfgVars);
?>
<form action="" name="mfrm" id="mfrm" method="post">
	<div class="card m-b-0" id="messages-main">
		<div class="ms-menu">
			<div class="ms-block p-10"><span class="c-black"><b>Процесс</b></span></div>
			<div class="listview lv-user" id="install-progress">
				<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">1</div><div class="media-body"><div class="lv-title"><del>Лицензия</del></div></div></div>
				<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">2</div><div class="media-body"><div class="lv-title"><del>База данных</del></div></div></div>
				<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">3</div><div class="media-body"><div class="lv-title"><del>Требования</del></div></div></div>
				<div class="lv-item media"><div class="lv-avatar bgm-orange pull-left">4</div><div class="media-body"><div class="lv-title"><del>Таблицы</del></div></div></div>
				<div class="lv-item media active"><div class="lv-avatar bgm-red pull-left">5</div><div class="media-body"><div class="lv-title">Администратор</div><div class="lv-small"><i class="zmdi zmdi-badge-check c-green"></i> Текущий шаг</div></div></div>
			</div>
		</div>
		<div class="ms-body">
			<div class="listview lv-message">
				<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Главный администратор</span></div></div>
				<div class="lv-body p-15">
					<p class="c-gray m-b-15">На этом шаге также будут установлены таблицы рецидивизма (<code><?php echo htmlspecialchars($cfgVars['prefix'], ENT_QUOTES, 'UTF-8'); ?>_recid_*</code>) и записан полный <code>config.php</code>.</p>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="uname"><?php echo HelpIcon('Имя', 'Логин владельца панели'); ?>Имя</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="uname" name="uname" placeholder="Логин" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="pass1"><?php echo HelpIcon('Пароль', 'Пароль для входа в веб-панель'); ?>Пароль</label>
							<div class="col-sm-9"><div class="fg-line"><input type="password" class="form-control input-sm" id="pass1" name="pass1" placeholder="Пароль" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="pass2">Подтверждение</label>
							<div class="col-sm-9"><div class="fg-line"><input type="password" class="form-control input-sm" id="pass2" name="pass2" placeholder="Повтор пароля" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="steam"><?php echo HelpIcon('STEAM', 'Формат STEAM_0:X:YYYYYY — попадёт в SB_PROTECTED_STEAMIDS'); ?>STEAM ID</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="steam" name="steam" placeholder="STEAM_0:0:12345" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="email">E-mail</label>
							<div class="col-sm-9"><div class="fg-line"><input type="email" class="form-control input-sm" id="email" name="email" placeholder="admin@example.com" /></div></div>
						</div>
					</div>
				</div>

				<div class="lv-header-alt clearfix"><div class="lvh-label"><span class="c-black">Опционально — config.php</span></div></div>
				<div class="lv-body p-15">
					<p class="c-gray m-b-15">Можно оставить пустым и прописать позже в <code>config.php</code>.</p>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="parsec_pass"><?php echo HelpIcon('Пароль твинков', 'PARSEC_PANEL_WRITE_PASSWORD — пароль для write-режима панели твинков/связок. Пусто = отключено до ручной правки config.php.'); ?>Пароль твинков</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="parsec_pass" name="parsec_pass" placeholder="необязательно" autocomplete="off" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="hosting_url"><?php echo HelpIcon('Оплата хостинга', 'SB_HOSTING_PAY_URL — ссылка пункта меню. Пустой URL = пункт скрыт.'); ?>URL оплаты хостинга</label>
							<div class="col-sm-9"><div class="fg-line"><input type="url" class="form-control input-sm" id="hosting_url" name="hosting_url" placeholder="https://… (необязательно)" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="hosting_label">Подпись пункта</label>
							<div class="col-sm-9"><div class="fg-line"><input type="text" class="form-control input-sm" id="hosting_label" name="hosting_label" value="Оплатить хостинг" /></div></div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label class="col-sm-3 control-label" for="hosting_newtab">Открывать в новой вкладке</label>
							<div class="col-sm-9 p-t-10">
								<div class="checkbox m-b-15">
									<label for="hosting_newtab">
										<input type="checkbox" name="hosting_newtab" id="hosting_newtab" hidden="hidden" checked="checked" />
										<i class="input-helper"></i> Да
									</label>
								</div>
							</div>
						</div>
					</div>

					<div class="p-10" align="center">
						<button type="button" onclick="CheckInput();" class="btn btn-primary waves-effect">Завершить установку</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" name="postd" value="1" />
	<input type="hidden" name="username" value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="password" value="<?php echo htmlspecialchars(isset($_POST['password']) ? $_POST['password'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="server" value="<?php echo htmlspecialchars(isset($_POST['server']) ? $_POST['server'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="database" value="<?php echo htmlspecialchars(isset($_POST['database']) ? $_POST['database'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="port" value="<?php echo htmlspecialchars(isset($_POST['port']) ? $_POST['port'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="prefix" value="<?php echo htmlspecialchars(isset($_POST['prefix']) ? $_POST['prefix'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="apikey" value="<?php echo htmlspecialchars(isset($_POST['apikey']) ? $_POST['apikey'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="sb-wp-url" value="<?php echo htmlspecialchars(isset($_POST['sb-wp-url']) ? $_POST['sb-wp-url'] : '', ENT_QUOTES, 'UTF-8'); ?>" />
</form>
<script>
function CheckInput() {
	var miss = 0;
	['uname','pass1','pass2','steam','email'].forEach(function (id) {
		if (!$id(id) || !$id(id).value) miss++;
	});
	if (miss > 0) ShowBox('Ошибка', 'Все поля должны быть заполнены.', 'red', '', true);
	else if ($id('pass1').value !== $id('pass2').value) ShowBox('Ошибка', 'Пароли не совпадают.', 'red', '', true);
	else $id('mfrm').submit();
}
window.sbInstallEnter = CheckInput;
</script>
