<?php
	if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
	$errors = 0;

	require(ROOT . "../includes/adodb/adodb.inc.php");
	include_once(ROOT . "../includes/adodb/adodb-errorhandler.inc.php");
	$server = "mysqli://" . rawurlencode($_POST['username']) . ":" . rawurlencode($_POST['password']) . "@" . $_POST['server'] . ":" . $_POST['port'] . "/" . $_POST['database'];
	$db = ADONewConnection($server);
	$sqlErrors = array();
	if (!$db) {
		$errors++;
		$sqlErrors[] = 'Нет соединения с сервером баз данных.';
	} else {
		$db->Execute("SET NAMES `utf8`");
		$safePrefix = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['prefix']);
		$file = file_get_contents(INCLUDES_PATH . "/struc.sql");
		$file = str_replace("{prefix}", $safePrefix, $file);
		$querys = explode(";", $file);
		foreach($querys AS $q)
		{
			if(strlen($q) > 2)
			{
				$res = $db->Execute(stripslashes($q) . ";");
				if(!$res)
				{
					$errors++;
					$msg = method_exists($db, 'ErrorMsg') ? $db->ErrorMsg() : 'execute failed';
					$snippet = trim($q);
					if (strlen($snippet) > 120)
						$snippet = substr($snippet, 0, 117) . '...';
					$sqlErrors[] = $msg . ' — ' . $snippet;
				}
			}
		}
	}
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
						<?php if($errors > 0){
							?>
							<p class="c-red">Ошибка создания структуры базы данных:</p>
							<ul class="install-sql-errors">
								<?php foreach ($sqlErrors as $err): ?>
									<li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
								<?php endforeach; ?>
							</ul>
							<?php
						}else{
							?>
							<p>Таблицы успешно созданы.</p>
							<?php
						}
						?>
						
						<form action="index.php?step=5" method="post" name="send" id="send">
							<input type="hidden" name="username" value="<?php echo htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="server" value="<?php echo htmlspecialchars($_POST['server'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="database" value="<?php echo htmlspecialchars($_POST['database'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="port" value="<?php echo htmlspecialchars($_POST['port'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="prefix" value="<?php echo htmlspecialchars($_POST['prefix'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="apikey" value="<?php echo htmlspecialchars($_POST['apikey'], ENT_QUOTES, 'UTF-8')?>">
							<input type="hidden" name="sb-wp-url" value="<?php echo htmlspecialchars($_POST['sb-wp-url'], ENT_QUOTES, 'UTF-8')?>">
						</form>
					</div>
					<div class="p-10" align="center">
						<button type="button" onclick="next()" name="button" class="btn btn-primary" id="button">ОК</button>
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
		$id('send').submit();
}
window.sbInstallEnter = next;
</script>
