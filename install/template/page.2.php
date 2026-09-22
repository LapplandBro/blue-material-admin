<?php
	if (!defined("IN_SB")) { echo "You should not be here. Only follow links!"; die(); }

	$dbError = '';
	$dbMissing = false;

	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$server = isset($_POST['server']) ? trim((string)$_POST['server']) : '';
		$port = isset($_POST['port']) ? trim((string)$_POST['port']) : '';
		$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
		$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
		$database = isset($_POST['database']) ? trim((string)$_POST['database']) : '';
		$prefix = isset($_POST['prefix']) ? trim((string)$_POST['prefix']) : '';
		$apikey = isset($_POST['apikey']) ? trim((string)$_POST['apikey']) : '';
		$sbwpurl = isset($_POST['sb-wp-url']) ? trim((string)$_POST['sb-wp-url']) : '';
		$creating = isset($_POST['create_db']) && !is_array($_POST['create_db']);

		if ($password === '' && isset($_SESSION['sb_install']['password']))
			$password = (string)$_SESSION['sb_install']['password'];

		if ($server === '' || $port === '' || $username === '' || $database === '' || $prefix === '') {
			$dbError = 'Заполните необходимые поля.';
		} elseif (!preg_match('/^[0-9]+$/', $port) || (int)$port < 1 || (int)$port > 65535) {
			$dbError = 'Порт сервера должен быть числом от 1 до 65535.';
		} elseif (strlen($prefix) > 9) {
			$dbError = 'Префикс таблиц не может быть длиннее 9 символов.';
		} elseif (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
			$dbError = 'Префикс может содержать только латинские буквы, цифры и подчёркивание.';
		} else {
			$fields = array(
				'server' => $server,
				'username' => $username,
				'password' => $password,
				'database' => $database,
				'port' => $port,
				'prefix' => $prefix,
				'apikey' => $apikey,
				'sbwpurl' => $sbwpurl,
			);
			$nameOk = (preg_match('/^[A-Za-z0-9_]+$/', $database) && strlen($database) <= 64);

			if ($creating) {
				if (!$nameOk) {
					$dbError = 'Имя базы может содержать только латинские буквы, цифры и подчёркивание.';
				} else {
					$conn = sb_install_mysqli_connect($server, $username, $password, $port, '');
					if (empty($conn['ok'])) {
						$dbError = sb_install_db_error_text($conn);
					} else {
						$db = $conn['db'];
						$created = $db->Execute('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
						if (!$created) {
							$errno = method_exists($db, 'ErrorNo') ? (int)$db->ErrorNo() : 0;
							if ($errno === 1007) {
								$created = true;
							} else {
								$msg = '';
								if (method_exists($db, 'ErrorMsg')) {
									$rawMsg = $db->ErrorMsg();
									if (is_string($rawMsg))
										$msg = $rawMsg;
								}
								$dbError = 'Не удалось создать базу данных.';
								if ($msg !== '')
									$dbError .= ' ' . $msg;
								$dbMissing = true;
							}
						}
						if ($created && $dbError === '') {
							$check = sb_install_mysqli_connect($server, $username, $password, $port, $database);
							if (empty($check['ok'])) {
								$dbError = 'База создана, но подключиться к ней не удалось. ' . sb_install_db_error_text($check);
								$dbMissing = ((int)$check['errno'] === 1049);
							} else {
								sb_install_save_db($fields, true, true);
								sb_install_redirect(3);
							}
						}
					}
				}
			} else {
				$conn = sb_install_mysqli_connect($server, $username, $password, $port, $database);
				if (!empty($conn['ok'])) {
					sb_install_save_db($fields, true, sb_install_db_identity_changed($fields));
					sb_install_redirect(3);
				} else {
					$errno = isset($conn['errno']) ? (int)$conn['errno'] : 0;
					$unknownDb = ($errno === 1049);
					if (!$unknownDb && isset($conn['error']) && is_string($conn['error']) && stripos($conn['error'], 'Unknown database') !== false)
						$unknownDb = true;
					if ($unknownDb) {
						sb_install_save_db($fields, false, true);
						$dbMissing = $nameOk;
						if ($nameOk)
							$dbError = 'База данных «' . $database . '» не найдена (MySQL 1049). Проверьте имя или нажмите «Создать базу».';
						else
							$dbError = 'База данных не найдена (MySQL 1049). Чтобы создать её автоматически, укажите имя только из латинских букв, цифр и подчёркивания.';
					} else {
						$dbError = sb_install_db_error_text($conn);
					}
				}
			}
		}
	}

	$valServer = 'localhost';
	if (isset($_POST['server']))
		$valServer = (string)$_POST['server'];
	elseif (isset($_SESSION['sb_install']['server']))
		$valServer = (string)$_SESSION['sb_install']['server'];

	$valPort = '3306';
	if (isset($_POST['port']))
		$valPort = (string)$_POST['port'];
	elseif (isset($_SESSION['sb_install']['port']))
		$valPort = (string)$_SESSION['sb_install']['port'];

	$valUser = '';
	if (isset($_POST['username']))
		$valUser = (string)$_POST['username'];
	elseif (isset($_SESSION['sb_install']['username']))
		$valUser = (string)$_SESSION['sb_install']['username'];

	$valDatabase = '';
	if (isset($_POST['database']))
		$valDatabase = (string)$_POST['database'];
	elseif (isset($_SESSION['sb_install']['database']))
		$valDatabase = (string)$_SESSION['sb_install']['database'];

	$valPrefix = 'sb';
	if (isset($_POST['prefix']))
		$valPrefix = (string)$_POST['prefix'];
	elseif (isset($_SESSION['sb_install']['prefix']))
		$valPrefix = (string)$_SESSION['sb_install']['prefix'];

	$valApikey = '';
	if (isset($_POST['apikey']))
		$valApikey = (string)$_POST['apikey'];
	elseif (isset($_SESSION['sb_install']['apikey']))
		$valApikey = (string)$_SESSION['sb_install']['apikey'];

	if (isset($_POST['sb-wp-url']))
		$valUrl = (string)$_POST['sb-wp-url'];
	elseif (isset($_SESSION['sb_install']['sbwpurl']))
		$valUrl = (string)$_SESSION['sb_install']['sbwpurl'];
	else
		$valUrl = (string)TryAutodetectURL();
?>

<div class="card m-b-0" id="messages-main">
	<form action="index.php?step=2" method="post" name="submit" id="submit" autocomplete="off">
<?php $installStep = 2; include TEMPLATES_PATH . '/install-progress.php'; ?>
		<div class="ms-body">
			<div class="listview lv-message">
				<div class="lv-header-alt clearfix">
					<div class="lvh-label">
						<span class="c-black">Информация</span>
					</div>
				</div>

				<div class="lv-body p-15">
					Наводите курсор мыши на иконку <i class="bi bi-info-circle" title="Подсказка у каждого поля формы"></i> для получения дополнительной информации.
				</div>

				<div class="lv-header-alt clearfix">
					<div class="lvh-label">
						<span class="c-black" id="submit-main-full">Информация MySQL</span>
					</div>
				</div>
				<div class="lv-body p-15" id="group.details">
					<div class="col-sm-12">
						<?php if ($dbError !== ''): ?>
						<p class="c-red"><?php echo htmlspecialchars($dbError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
						<?php endif; ?>
						<div class="form-group col-sm-12">
							<label for="server" class="col-sm-3 control-label"><?php echo HelpIcon("Сервер", "Введите IP или адрес сервера MySQL"); ?> Адрес сервера</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="server" name="server" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valServer, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="port" class="col-sm-3 control-label"><?php echo HelpIcon("Порт сервера", "Введите порт, на котором работает MySQL"); ?> Порт сервера</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="port" name="port" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valPort, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="username" class="col-sm-3 control-label"><?php echo HelpIcon("Имя пользователя", "Введите имя пользователя MySQL"); ?> Имя пользователя</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="username" name="username" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valUser, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="password" class="col-sm-3 control-label"><?php echo HelpIcon("Пароль", "Введите пароль пользователя MySQL"); ?> Пароль</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="password" class="form-control input-sm" id="password" name="password" autocomplete="new-password" placeholder="Введите данные" value="" />
									<?php if (isset($_SESSION['sb_install']['password'])): ?>
									<p class="c-gray m-b-0">Если пароль уже был принят, поле можно оставить пустым.</p>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="database" class="col-sm-3 control-label"><?php echo HelpIcon("База данных", "Введите имя базы данных"); ?> База данных</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="database" name="database" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valDatabase, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="prefix" class="col-sm-3 control-label"><?php echo HelpIcon("Префикс", "Введите префикс таблиц"); ?> Префикс таблиц</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="prefix" name="prefix" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valPrefix, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="apikey" class="col-sm-3 control-label"><?php echo HelpIcon("Steam API ключ", "Скопируйте и вставьте ваш Steam API ключ здесь. Он нужен для авторизации администраторов через Steam."); ?> Steam API ключ (необязательно)</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="apikey" name="apikey" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valApikey, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>

						<div class="form-group col-sm-12">
							<label for="sb-wp-url" class="col-sm-3 control-label"><?php echo HelpIcon("Адрес SourceBans", "Адрес установки системы SourceBans. Пример: http://mysite.com/bans/"); ?> Адрес SourceBans</label>
							<div class="col-sm-9">
								<div class="fg-line">
									<input type="text" class="form-control input-sm" id="sb-wp-url" name="sb-wp-url" autocomplete="off" placeholder="Введите данные" value="<?php echo htmlspecialchars($valUrl, ENT_QUOTES, 'UTF-8'); ?>" />
								</div>
							</div>
						</div>
						<div class="p-10" align="center">
							<button type="submit" class="btn btn-primary" id="button" name="button">Далее</button>
							<?php if ($dbMissing): ?>
							<button type="submit" class="btn btn-info" name="create_db" value="1">Создать базу</button>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<script type="text/javascript">
window.sbInstallEnter = function () {
	var f = $id('submit') || document.forms[0];
	if (!f)
		return;
	if (typeof f.requestSubmit === 'function')
		f.requestSubmit();
	else
		f.submit();
};
</script>
