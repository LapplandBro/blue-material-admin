<?php
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
$errors = 0;
$warnings = 0;

if (!isset($_SESSION['sb_install']) || !is_array($_SESSION['sb_install']))
    $_SESSION['sb_install'] = array();

$sql_connected = false;
$sql_version = '';
$sql_error = '';

if (isset($_POST['apikey']))
    $_SESSION['sb_install']['apikey'] = (string)$_POST['apikey'];
if (isset($_POST['sb-wp-url']))
    $_SESSION['sb_install']['sbwpurl'] = (string)$_POST['sb-wp-url'];

$server = isset($_SESSION['sb_install']['server']) ? (string)$_SESSION['sb_install']['server'] : '';
$username = isset($_SESSION['sb_install']['username']) ? (string)$_SESSION['sb_install']['username'] : '';
$password = isset($_SESSION['sb_install']['password']) ? (string)$_SESSION['sb_install']['password'] : '';
$port = isset($_SESSION['sb_install']['port']) ? (string)$_SESSION['sb_install']['port'] : '';
$database = isset($_SESSION['sb_install']['database']) ? (string)$_SESSION['sb_install']['database'] : '';

if ($server !== '' && $username !== '' && $port !== '' && $database !== '') {
    $conn = sb_install_mysqli_connect($server, $username, $password, $port, $database);
    if (!empty($conn['ok']) && isset($conn['db']) && is_object($conn['db'])) {
        $sql_connected = true;
        $db = $conn['db'];
        $db->Execute("SET NAMES `utf8`");
        $row = $db->GetRow('SELECT VERSION() AS v');
        if ($row && isset($row['v'])) {
            $sql_version = (string) $row['v'];
        } else {
            $vars = $db->Execute('SHOW VARIABLES LIKE \'version\'');
            if ($vars && !$vars->EOF && isset($vars->fields['Value'])) {
                $sql_version = (string) $vars->fields['Value'];
            }
        }
    } else {
        $sql_error = sb_install_db_error_text($conn);
    }
}

$apikeyVal = isset($_SESSION['sb_install']['apikey']) ? (string)$_SESSION['sb_install']['apikey'] : '';
if (isset($_SESSION['sb_install']['sbwpurl']))
    $urlVal = (string)$_SESSION['sb_install']['sbwpurl'];
else
    $urlVal = (string)TryAutodetectURL();

// В дальнейшем, в установщик будет интегрироваться мульти-язычность.
// Потому эти переменные заведены под мульти-язычность. Здесь с течением времени, будут вызовы функций "переводчика".
$disabled   = 'Выкл.';
$enabled    = 'Вкл.';
$unknown    = '—';
$yes        = 'Да';
$no         = 'Нет';

// ['Папка для демок', 'data/demos', $yes, $unknown, $translations, false],
$gendirdata = function($dirname, $dirpath, $required, $recommended, $display, &$name, $is_warning = false) {
  $path = '../' . $dirpath;
  if (is_dir($path)) {
    $ok = is_writable($path);
  } elseif (file_exists($path)) {
    $ok = is_writable($path);
  } else {
    // Файл ещё не создан — достаточно прав на родительский каталог
    $ok = is_writable(dirname($path));
  }

  $data = [
    'required'    => $required,
    'recommended' => $recommended,
    'result'      => $ok,
    'display'     => $display
  ];

  if ($is_warning)
    $data['is_warning'] = true;

  $name = $dirname . ' (' . $dirpath . ')';
  return $data;
};

/**
 * MySQL 5.7+ или MariaDB 10.2+ (по строке VERSION()).
 */
function sb_install_mysql_version_ok($version)
{
  $version = strtolower(trim((string) $version));
  if ($version === '') {
    return false;
  }
  if (strpos($version, 'mariadb') !== false) {
    if (preg_match('/(\d+\.\d+\.\d+)/', $version, $m)) {
      return version_compare($m[1], '10.2', '>=');
    }
    return false;
  }
  if (preg_match('/(\d+\.\d+\.\d+)/', $version, $m)) {
    return version_compare($m[1], '5.7', '>=');
  }
  return version_compare($version, '5.7', '>=');
}

$requirements = [
  /**
   * О структуре массива
   *
   * Ключи в нём - это названия секций, которые проверяет установщик
   * Ключи в секциях - названия "параметров"
   */
  'Требования PHP' => [
    'Версия PHP'  =>  [
      'required'    => '8.0',
      'recommended' => '8.1+',

      'result'      => (version_compare(PHP_VERSION, '8.0', '>=')),
      /* Так же возможен ключ "is_warning", наличие которого заставляет установщик превратить "ошибку" в "предупреждение", в случае не успешной проверки */

      // Если является массивом, то:
      // - выводит первый ключ, если всё хорошо
      // - выводит второй ключ, если не всё так гладко
      //
      // Если является чем-то иным, то просто выводит, как строку
      'display'     => PHP_VERSION
    ],

    'Расширение BCMath' => [
      'required'    => $yes,
      'recommended' => $unknown,
      
      'result'      =>  function_exists('bcadd'),
      'display'     => [$yes, $no]
    ],

    'Расширение GMP / 64-битный PHP' => [
      'required'    => $yes,
      'recommended' => $unknown,

      'result'      => (extension_loaded('gmp') || getPhpArchitecture() == 'amd64'),
      'display'     => [$yes, $no]
    ],

    'Загрузка файлов' => [
      'required'    => $enabled,
      'recommended' => $unknown,

      'result'      => ini_get("file_uploads"),
      'display'     => [$enabled, $disabled]
    ],

    'Поддержка XML' => [
      'required'    => $enabled,
      'recommended' => $unknown,

      'result'      => extension_loaded('xml'),
      'display'     => [$enabled, $disabled]
    ],

    'Расширение MySQLi' => [
      'required'    => $yes,
      'recommended' => $unknown,

      'result'      => extension_loaded('mysqli'),
      'display'     => [$yes, $no]
    ],

    'Расширение mbstring' => [
      'required'    => $yes,
      'recommended' => $unknown,

      'result'      => extension_loaded('mbstring'),
      'display'     => [$yes, $no]
    ]
  ],

  'Требования MySQL'  => [
    'Версия сервера (MySQL / MariaDB)'  => [
      'required'      => 'MySQL 5.7+ или MariaDB 10.2+',
      'recommended'   => 'MySQL 8.0+ / MariaDB 10.6+',

      'result'        => ($sql_connected && $sql_version !== '' && sb_install_mysql_version_ok($sql_version)),
      'display'       => $sql_connected
        ? ($sql_version !== '' ? $sql_version : 'Не удалось определить')
        : ($sql_error !== '' ? $sql_error : 'Нет соединения (вернитесь к шагу 2)')
    ]
  ],

  'Требования ФС' => [] // это - динамически наполняемый массив. См. ниже.
];

// Наполняем "Требования ФС"...
if (!is_dir('../data')) {
  @mkdir('../data', 0755, true);
  if (is_dir('../data') && !file_exists('../data/.htaccess'))
    @file_put_contents('../data/.htaccess', "Deny from all\n");
}

$translations = [$yes, $no];
$fs = [
  ['Папка для демок',                   'demos',            $yes, $unknown, $translations, false],
  ['Папка иконок МОДов',                'images/games',     $unknown, $yes, $translations, true],
  ['Папка изображений карт',            'images/maps',      $unknown, $yes, $translations, true],
  ['Конфиг (корень сайта)',             'config.php',       $yes, $unknown, $translations, false],
  ['Папка data/ (фреймворк БД)',         'data',             $yes, $unknown, $translations, false],
];
$req_FS = &$requirements['Требования ФС'];
foreach ($fs as $f) {
  $name = '';
  $data = $gendirdata($f[0], $f[1], $f[2], $f[3], $f[4], $name, $f[5]);

  $req_FS[$name] = $data;
}

$req_FS['Тема Blue V2 (themes/blue_v2)'] = [
  'required' => $yes,
  'recommended' => $unknown,
  'result' => is_dir('../themes/blue_v2'),
  'display' => [$yes, $no]
];
?>
<div class="card m-b-0" id="messages-main">
<?php $installStep = 3; include TEMPLATES_PATH . '/install-progress.php'; ?>
		<div class="ms-body" id="submit-main-full">
			<div class="listview lv-message">
				<div class="lv-header-alt clearfix">
					<div class="lvh-label">
						<span class="c-black">Информация</span>
					</div>
				</div>

				<div class="lv-body p-15">
					Здесь перечислены обязательные и рекомендуемые параметры PHP, MySQL и файловой системы. Зелёная ячейка в колонке «Значение сервера» означает успешную проверку, красная — блокирующую ошибку, серая — предупреждение (установка возможна, но часть функций может не работать).
				</div>

        <!-- Installer Logic and Checks -->
<?php foreach ($requirements as $name => $data): ?>
				<div class="lv-header-alt clearfix">
					<div class="lvh-label">
						<span class="c-black"><?= $name ?></span>
					</div>
				</div>
				<div class="lv-body p-15">
					<div class="col-sm-12 install-req-scroll">
						<table class="table table-hover install-req-table">
							<thead>
								<tr>
									<th width="30%">Настройка</th>
									<th>Рекомендуется</th>
									<th>Требуется</th>
									<th width="30%">Значения сервера</th>
								</tr>
							</thead>
							<tbody>
<?php foreach ($data as $key => $values): ?>
								<tr>
									<td><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></td>
									<td class="req-muted"><?= htmlspecialchars($values['recommended'], ENT_QUOTES, 'UTF-8') ?></td>
									<td><?= htmlspecialchars($values['required'], ENT_QUOTES, 'UTF-8') ?></td>
									<?php
                    $class = '';
                    $drawable = $values['display'];
                    if ($values['result']) {
                      $class = 'success';
                      if (is_array($drawable)) {
                        $drawable = $drawable[0];
                      }
                    } elseif (isset($values['is_warning'])) {
                      $class = 'active';
                      $warnings++;
                      if (is_array($drawable)) {
                        $drawable = $drawable[1];
                      }
                    } else {
                      $class = 'danger';
                      $errors++;
                      if (is_array($drawable)) {
                        $drawable = $drawable[1];
                      }
                    }
                    $drawable = htmlspecialchars((string) $drawable, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
									?><td class="<?= $class ?>"><?= $drawable ?></td>
								</tr>
<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
<?php endforeach; ?>
				<div class="lv-body p-15">
					<div class="col-sm-12">
						<form action="index.php?step=4" method="post" name="send" id="send" autocomplete="off">
							<div class="form-group col-sm-12">
								<label for="apikey" class="col-sm-3 control-label"><?php echo HelpIcon("Steam API ключ", "Ключ нужен для авторизации администраторов через Steam. Можно оставить пустым и указать позже."); ?> Steam API ключ</label>
								<div class="col-sm-9">
									<div class="fg-line">
										<input type="text" class="form-control input-sm" id="apikey" name="apikey" autocomplete="off" value="<?php echo htmlspecialchars($apikeyVal, ENT_QUOTES, 'UTF-8'); ?>" />
									</div>
								</div>
							</div>
							<div class="form-group col-sm-12">
								<label for="sb-wp-url" class="col-sm-3 control-label"><?php echo HelpIcon("Адрес SourceBans", "Адрес установки. Пример: http://mysite.com/bans/"); ?> Адрес SourceBans</label>
								<div class="col-sm-9">
									<div class="fg-line">
										<input type="text" class="form-control input-sm" id="sb-wp-url" name="sb-wp-url" autocomplete="off" value="<?php echo htmlspecialchars($urlVal, ENT_QUOTES, 'UTF-8'); ?>" />
									</div>
								</div>
							</div>
							<div class="p-10" align="center">
								<button type="button" onclick="next()" class="btn btn-primary" name="button">Далее</button>
								<button type="submit" class="btn btn-info" name="recheck" value="1" formaction="index.php?step=3">Перепроверить</button>
								<a href="index.php?step=2" class="btn btn-info">Назад</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>

<script type="text/javascript">
<?php if ($errors > 0): ?>
ShowBox('Ошибки', 'Есть ошибки, из-за которых панель не установится. Устраните их и нажмите «Перепроверить».', 'red', '', true);
<?php elseif ($warnings > 0): ?>
ShowBox('Предупреждения', 'Есть предупреждения. Установка возможна, но часть функций может не работать.', 'blue', '', true);
<?php endif; ?>
function next() {
	var errors = <?php echo (int)$errors; ?>;
	if (errors > 0)
		ShowBox('Ошибки', 'Сначала устраните ошибки требований.', 'red', '', true);
	else
		$id('send').submit();
}
window.sbInstallEnter = next;
</script>
