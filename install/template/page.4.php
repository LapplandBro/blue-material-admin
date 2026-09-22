<?php
	if (!defined("IN_SB")) { echo "You should not be here. Only follow links!"; die(); }

	if (!isset($_SESSION['sb_install']) || !is_array($_SESSION['sb_install']))
		$_SESSION['sb_install'] = array();

	if (isset($_POST['apikey']))
		$_SESSION['sb_install']['apikey'] = (string)$_POST['apikey'];
	if (isset($_POST['sb-wp-url']))
		$_SESSION['sb_install']['sbwpurl'] = (string)$_POST['sb-wp-url'];

	$errors = 0;
	$sqlErrors = array();

	$server = isset($_SESSION['sb_install']['server']) ? (string)$_SESSION['sb_install']['server'] : '';
	$username = isset($_SESSION['sb_install']['username']) ? (string)$_SESSION['sb_install']['username'] : '';
	$password = isset($_SESSION['sb_install']['password']) ? (string)$_SESSION['sb_install']['password'] : '';
	$port = isset($_SESSION['sb_install']['port']) ? (string)$_SESSION['sb_install']['port'] : '';
	$database = isset($_SESSION['sb_install']['database']) ? (string)$_SESSION['sb_install']['database'] : '';
	$prefixRaw = isset($_SESSION['sb_install']['prefix']) ? (string)$_SESSION['sb_install']['prefix'] : '';
	$safePrefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefixRaw);
	if (!is_string($safePrefix))
		$safePrefix = '';

	if ($server === '' || $username === '' || $port === '' || $database === '') {
		$errors++;
		$sqlErrors[] = 'Нет сохранённых данных подключения к базе. Вернитесь к шагу 2.';
	} elseif ($safePrefix === '' || strlen($safePrefix) > 9) {
		$errors++;
		$sqlErrors[] = 'Некорректный префикс таблиц.';
	} else {
		$_SESSION['sb_install']['prefix'] = $safePrefix;
		$conn = sb_install_mysqli_connect($server, $username, $password, $port, $database);
		if (empty($conn['ok']) || !isset($conn['db']) || !is_object($conn['db'])) {
			$errors++;
			$sqlErrors[] = sb_install_db_error_text($conn);
		} else {
			$db = $conn['db'];
			$db->Execute("SET NAMES `utf8`");
			$sqlPath = INCLUDES_PATH . '/struc.sql';
			$file = is_file($sqlPath) ? file_get_contents($sqlPath) : false;
			if ($file === false || $file === '') {
				$errors++;
				$sqlErrors[] = 'Не удалось прочитать файл структуры базы.';
			} else {
				$file = str_replace('{prefix}', $safePrefix, $file);
				$querys = explode(';', $file);
				$ran = 0;
				foreach ($querys as $q) {
					if (strlen($q) > 2) {
						$ran++;
						$res = $db->Execute(stripslashes($q) . ';');
						if (!$res) {
							$errno = method_exists($db, 'ErrorNo') ? (int)$db->ErrorNo() : 0;
							if ($errno === 1050)
								continue;
							$errors++;
							$msg = 'execute failed';
							if (method_exists($db, 'ErrorMsg')) {
								$rawMsg = $db->ErrorMsg();
								if (is_string($rawMsg) && $rawMsg !== '')
									$msg = $rawMsg;
							}
							$snippet = trim($q);
							if (strlen($snippet) > 120)
								$snippet = substr($snippet, 0, 117) . '...';
							$sqlErrors[] = $msg . ' — ' . $snippet;
						}
					}
				}
				if ($ran === 0) {
					$errors++;
					$sqlErrors[] = 'В файле структуры нет запросов.';
				}
			}
		}
	}

	if ($errors === 0)
		$_SESSION['sb_install']['tables_ok'] = 1;
	else
		unset($_SESSION['sb_install']['tables_ok']);
?>

<div class="card m-b-0" id="messages-main">
<?php $installStep = 4; include TEMPLATES_PATH . '/install-progress.php'; ?>
		<div class="ms-body" id="submit-main">
			<div class="listview lv-message">
				<div class="lv-header-alt clearfix">
					<div class="lvh-label">
						<span class="c-black">Информация</span>
					</div>
				</div>

				<div class="lv-body p-15">
					На этой странице создаются таблицы базы данных.
				</div>

				<div class="lv-header-alt clearfix">
					<div class="lvh-label">
						<span class="c-black">Установка таблиц</span>
					</div>
				</div>

				<div class="lv-body p-15">
					<div class="col-sm-12">
						<?php if ($errors > 0) { ?>
							<p class="c-red">Ошибка создания структуры базы данных:</p>
							<ul class="install-sql-errors">
								<?php foreach ($sqlErrors as $err): ?>
									<li><?php echo htmlspecialchars((string)$err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php } else { ?>
							<p>Таблицы успешно созданы.</p>
						<?php } ?>
					</div>
					<div class="p-10" align="center">
						<?php if ($errors > 0): ?>
						<button type="button" onclick="next()" name="button" class="btn btn-primary" id="button">ОК</button>
						<?php else: ?>
						<a href="index.php?step=5" class="btn btn-primary" id="button">ОК</a>
						<?php endif; ?>
						<a href="index.php?step=3" class="btn btn-info">Назад</a>
					</div>
				</div>
			</div>
		</div>
</div>

<script type="text/javascript">
function next() {
	var errors = <?php echo (int)$errors; ?>;
	if (errors > 0)
		ShowBox('Ошибки', 'Таблицы созданы с ошибками. Исправьте их перед продолжением.', 'red', '', true);
	else
		window.location = 'index.php?step=5';
}
window.sbInstallEnter = next;
</script>
