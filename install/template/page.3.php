<?php
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
$errors = 0;
$warnings = 0;

$sql_connected = false;
$sql_version = '';

if (isset($_POST['username'], $_POST['password'], $_POST['server'], $_POST['port'], $_POST['database'])) {
    require(ROOT . "../includes/adodb/adodb.inc.php");
    include_once(ROOT . "../includes/adodb/adodb-errorhandler.inc.php");
    $server = "mysqli://" . rawurlencode($_POST['username']) . ":" . rawurlencode($_POST['password']) . "@" . $_POST['server'] . ":" . $_POST['port'] . "/" . $_POST['database'];
    $db = ADONewConnection($server);
    if ($db) {
        $sql_connected = true;
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
    }
}

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
      'required'    => '7.4',
      'recommended' => '8.1+',

      'result'      => (version_compare(PHP_VERSION, '7.4', '>=')),
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
        : 'Нет соединения (вернитесь к шагу 2)'
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
                    $drawable = htmlspecialchars((string) $drawable, ENT_QUOTES, 'UTF-8');
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
						<?php /* WhiteWolf: This is a hack to make sure the user didn't refresh the page, in the future we should tell them what they did. */
							if(!isset($_POST['username'], $_POST['password'], $_POST['server'], $_POST['database'], $_POST['port'], $_POST['prefix'])) {
						?>
						<form action="index.php?step=2" method="post" name="send" id="send">
							<!-- We don't even include the body here, since the javascript shouldn't let them go forward -->
						</form>
						<form action="index.php?step=2" method="post" name="sendback" id="sendback">
						</form>
						<?php
						}
						else
						{
						?>
						<form action="index.php?step=4" method="post" name="send" id="send">
							<input type="hidden" name="username" value="<?php echo htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="server" value="<?php echo htmlspecialchars($_POST['server'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="database" value="<?php echo htmlspecialchars($_POST['database'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="port" value="<?php echo htmlspecialchars($_POST['port'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="prefix" value="<?php echo htmlspecialchars($_POST['prefix'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="apikey" value="<?php echo htmlspecialchars($_POST['apikey'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="sb-wp-url" value="<?php echo htmlspecialchars($_POST['sb-wp-url'], ENT_QUOTES, 'UTF-8')?>">
						</form>
						<form action="index.php?step=3" method="post" name="sendback" id="sendback">
							<input type="hidden" name="username" value="<?php echo htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="server" value="<?php echo htmlspecialchars($_POST['server'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="database" value="<?php echo htmlspecialchars($_POST['database'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="port" value="<?php echo htmlspecialchars($_POST['port'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="prefix" value="<?php echo htmlspecialchars($_POST['prefix'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="apikey" value="<?php echo htmlspecialchars($_POST['apikey'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="sb-wp-url" value="<?php echo htmlspecialchars($_POST['sb-wp-url'], ENT_QUOTES, 'UTF-8')?>">
						</form>
						<?php
						}
						?>
					</div>
					&nbsp;
					<div class="p-10" align="center">
						<button type="button" onclick="next()" class="btn btn-primary" name="button">Далее</button>
						<button type="button" onclick="$id('sendback').submit();" class="btn btn-info" name="button">Перепроверить</button>
					</div>
					<input type="hidden" name="postd" value="1">
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
