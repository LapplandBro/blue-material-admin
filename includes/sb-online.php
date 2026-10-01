<?php

/**
 * Сбор онлайна по расписанию и чтение готовых рядов для админки.
 * Запись и опрос серверов — только sb_online_collect() из CLI.
 */

function sb_online_collect_enabled()
{
	if (!isset($GLOBALS['config']) || !is_array($GLOBALS['config']))
		return true;
	if (!array_key_exists('config.online_collect', $GLOBALS['config']))
		return true;
	$val = $GLOBALS['config']['config.online_collect'];
	if (is_array($val)) {
		$val = reset($val);
		if ($val === false)
			return true;
	}
	return trim((string)$val) !== '0';
}

function sb_online_ensure_tables()
{
	if (!isset($GLOBALS['db']) || !defined('DB_PREFIX'))
		return;
	$p = DB_PREFIX;
	$tables = array(
		"CREATE TABLE IF NOT EXISTS `{$p}_server_samples` (
			`sid` int(6) NOT NULL,
			`ts` int(11) NOT NULL,
			`players` smallint NOT NULL DEFAULT 0,
			`maxplayers` smallint NOT NULL DEFAULT 0,
			`map` varchar(64) NOT NULL DEFAULT '',
			`up` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`sid`, `ts`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8",
		"CREATE TABLE IF NOT EXISTS `{$p}_server_hourly` (
			`sid` int(6) NOT NULL,
			`hour_ts` int(11) NOT NULL,
			`samples` smallint NOT NULL DEFAULT 0,
			`players_sum` int NOT NULL DEFAULT 0,
			`players_max` smallint NOT NULL DEFAULT 0,
			`up_samples` smallint NOT NULL DEFAULT 0,
			PRIMARY KEY (`sid`, `hour_ts`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8",
		"CREATE TABLE IF NOT EXISTS `{$p}_server_hour_profile` (
			`sid` int(6) NOT NULL,
			`hour_of_day` tinyint NOT NULL,
			`avg_players` decimal(8,2) NOT NULL DEFAULT 0,
			`samples` int NOT NULL DEFAULT 0,
			PRIMARY KEY (`sid`, `hour_of_day`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8",
	);
	foreach ($tables as $sql)
		$GLOBALS['db']->Execute($sql);
}

function sb_online_status_path()
{
	$dir = (defined('ROOT') ? ROOT : dirname(__FILE__) . '/../') . 'data/cache';
	return $dir . '/online_collect_status.json';
}

function sb_online_status_read()
{
	$path = sb_online_status_path();
	if (!is_file($path))
		return null;
	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '')
		return null;
	$data = json_decode($raw, true);
	return is_array($data) ? $data : null;
}

function sb_online_status_write($message, $ok = true)
{
	$dir = dirname(sb_online_status_path());
	if (!is_dir($dir))
		@mkdir($dir, 0755, true);
	$payload = array(
		'ts' => time(),
		'ok' => $ok ? 1 : 0,
		'message' => (string)$message,
	);
	@file_put_contents(sb_online_status_path(), json_encode($payload, JSON_UNESCAPED_UNICODE));
}

function sb_online_status_text()
{
	$st = sb_online_status_read();
	if (!is_array($st) || empty($st['ts']))
		return 'Задание ещё ни разу не завершилось. Пустые клетки — это не ноль игроков: замера в базе нет.';
	$when = date('d.m.Y H:i', (int)$st['ts']);
	$msg = isset($st['message']) ? trim((string)$st['message']) : '';
	if ($msg === '')
		$msg = 'без подробностей';
	return 'Последний запуск задания: ' . $when . '. ' . $msg;
}

function sb_online_collect()
{
	$skip = array('enabled' => true, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => '');
	if (!sb_online_collect_enabled()) {
		sb_online_status_write('Сбор выключен галочкой в настройках.', false);
		return array('enabled' => false, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => '');
	}
	// Из браузера не опрашиваем и не пишем — только cron/collect_online.php.
	if (php_sapi_name() !== 'cli' || !isset($GLOBALS['db']) || !defined('DB_PREFIX'))
		return $skip;

	$dir = (defined('ROOT') ? ROOT : dirname(__FILE__) . '/../') . 'data/cache';
	if (!is_dir($dir))
		@mkdir($dir, 0755, true);
	$stamp = $dir . '/online_collect.stamp';
	if (is_file($stamp) && (time() - (int)@filemtime($stamp)) < 180) {
		$prev = sb_online_status_read();
		if (!is_array($prev) || empty($prev['ts']) || (time() - (int)$prev['ts']) > 180)
			sb_online_status_write('Предыдущий запуск ещё не закончился, новый пропущен.', false);
		return array('enabled' => true, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => '', 'busy' => true);
	}
	@file_put_contents($stamp, (string)getmypid());

	try {
		@ini_set('default_socket_timeout', '3');
		if (function_exists('set_time_limit'))
			@set_time_limit(120);
		if (function_exists('sb_db_ensure') && !sb_db_ensure()) {
			$msg = 'Нет соединения с базой, замеры не записаны.';
			sb_online_status_write($msg, false);
			return array('enabled' => true, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => $msg);
		}

		sb_online_ensure_tables();

		if (!function_exists('sb_server_a2s_info')) {
			$sf = (defined('INCLUDES_PATH') ? INCLUDES_PATH : dirname(__FILE__)) . '/system-functions.php';
			if (is_file($sf))
				require_once $sf;
		}
		if (!function_exists('sb_server_a2s_info')) {
			$msg = 'Не найдена функция опроса серверов (includes/system-functions.php).';
			sb_online_status_write($msg, false);
			return array('enabled' => true, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => $msg);
		}

		$rows = $GLOBALS['db']->GetAll(
			"SELECT `sid`, `ip`, `port` FROM `" . DB_PREFIX . "_servers` WHERE `enabled` = 1"
		);
		if (!is_array($rows))
			$rows = array();

		// Одна локальная пятиминутка и один локальный час на весь проход.
		$minute = (int)date('i');
		$minute = $minute - ($minute % 5);
		$ts = (int)strtotime(date('Y-m-d H:') . sprintf('%02d:00', $minute));
		$hourTs = (int)strtotime(date('Y-m-d H:00:00'));

		$ok = 0;
		$down = 0;
		$saved = 0;
		$writeError = '';
		$seen = array();
		foreach ($rows as $srv) {
			if (!is_array($srv))
				continue;
			$sid = isset($srv['sid']) ? (int)$srv['sid'] : 0;
			$ip = isset($srv['ip']) ? (string)$srv['ip'] : '';
			$port = isset($srv['port']) ? (int)$srv['port'] : 0;
			if ($sid <= 0)
				continue;

			$info = sb_server_a2s_info($ip, $port, 60, true, true);
			$players = 0;
			$maxplayers = 0;
			$map = '';
			$up = 0;
			if (is_array($info) && !empty($info['ok'])) {
				$players = isset($info['Players']) ? (int)$info['Players'] : 0;
				$maxplayers = isset($info['MaxPlayers']) ? (int)$info['MaxPlayers'] : 0;
				$map = isset($info['Map']) ? (string)$info['Map'] : '';
				$up = 1;
				$ok++;
			} else {
				$down++;
			}
			$players = sb_online_clamp_small($players);
			$maxplayers = sb_online_clamp_small($maxplayers);
			$map = sb_online_clip_map($map);
			$mapSql = $GLOBALS['db']->qstr($map);

			$wrote = $GLOBALS['db']->Execute(
				"INSERT INTO `" . DB_PREFIX . "_server_samples` (`sid`,`ts`,`players`,`maxplayers`,`map`,`up`) VALUES ("
				. $sid . "," . $ts . "," . $players . "," . $maxplayers . "," . $mapSql . "," . $up
				. ") ON DUPLICATE KEY UPDATE `players` = " . $players
				. ", `maxplayers` = " . $maxplayers
				. ", `map` = " . $mapSql
				. ", `up` = " . $up
			);
			if ($wrote === false) {
				if ($writeError === '' && method_exists($GLOBALS['db'], 'ErrorMsg'))
					$writeError = (string)$GLOBALS['db']->ErrorMsg();
				continue;
			}
			sb_online_refresh_hour($sid, $hourTs);
			$seen[$sid] = true;
			$saved++;
		}

		foreach (array_keys($seen) as $sid)
			sb_online_refresh_profile((int)$sid);

		$sampleCut = (int)strtotime('-7 days');
		$hourlyCut = (int)strtotime('-90 days');
		$GLOBALS['db']->Execute(
			"DELETE FROM `" . DB_PREFIX . "_server_samples` WHERE `ts` < " . $sampleCut
		);
		$GLOBALS['db']->Execute(
			"DELETE FROM `" . DB_PREFIX . "_server_hourly` WHERE `hour_ts` < " . $hourlyCut
		);

		$total = $ok + $down;
		if ($total === 0) {
			$msg = 'Включённых серверов нет, записывать нечего.';
			sb_online_status_write($msg, false);
			return array('enabled' => true, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => $msg);
		}
		if ($saved === 0) {
			$msg = 'Серверы опрошены, но в базу не записалось ни одного замера.';
			if ($writeError !== '')
				$msg .= ' ' . $writeError;
			sb_online_status_write($msg, false);
			return array('enabled' => true, 'servers' => $total, 'ok' => $ok, 'down' => $down, 'saved' => 0, 'error' => $msg);
		}

		$msg = 'Записано серверов: ' . $saved . ' из ' . $total . ', ответили: ' . $ok . ', молчат: ' . $down . '.';
		if ($writeError !== '')
			$msg .= ' Часть записей не легла: ' . $writeError;
		sb_online_status_write($msg, $writeError === '');
		return array(
			'enabled' => true,
			'servers' => $total,
			'ok' => $ok,
			'down' => $down,
			'saved' => $saved,
			'error' => $writeError,
		);
	} catch (Throwable $e) {
		$msg = 'Сбор оборвался: ' . $e->getMessage();
		sb_online_status_write($msg, false);
		return array('enabled' => true, 'servers' => 0, 'ok' => 0, 'down' => 0, 'saved' => 0, 'error' => $msg);
	} finally {
		@unlink($stamp);
	}
}

function sb_online_cached_name($ip, $port)
{
	$ip = strtolower(trim((string)$ip));
	$port = (int)$port;
	$dir = (defined('ROOT') ? ROOT : dirname(__FILE__) . '/../') . 'data/cache/a2s';
	$path = $dir . '/' . hash('sha256', $ip . ':' . $port) . '.json';
	if (!is_file($path))
		return '';
	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '')
		return '';
	$data = json_decode($raw, true);
	if (!is_array($data) || !isset($data['HostName']) || !is_string($data['HostName']))
		return '';
	return $data['HostName'];
}

function sb_online_admin_view($sid = 0)
{
	$empty = array(
		'ready' => false,
		'servers' => array(),
		'selected' => 0,
		'day' => sb_online_day_rows(array()),
		'profile' => sb_online_profile_rows(array()),
		'peak_today' => null,
		'status_text' => sb_online_status_text(),
	);
	if (!isset($GLOBALS['db']) || !defined('DB_PREFIX'))
		return $empty;

	$list = @$GLOBALS['db']->GetAll(
		"SELECT `sid`, `ip`, `port` FROM `" . DB_PREFIX . "_servers` ORDER BY `sid` ASC"
	);
	$servers = array();
	if (is_array($list)) {
		foreach ($list as $row) {
			if (!is_array($row))
				continue;
			$rowSid = isset($row['sid']) ? (int)$row['sid'] : 0;
			$ip = isset($row['ip']) ? (string)$row['ip'] : '';
			$port = isset($row['port']) ? (int)$row['port'] : 0;
			if ($rowSid <= 0)
				continue;
			$name = sb_online_cached_name($ip, $port);
			$servers[] = array(
				'sid' => $rowSid,
				'ip' => $ip,
				'port' => $port,
				'label' => ($name !== '') ? $name : ($ip . ':' . $port),
				'peak_today' => null,
			);
		}
	}

	$wanted = (int)$sid;
	$selected = 0;
	if ($servers) {
		$selected = $servers[0]['sid'];
		foreach ($servers as $srv) {
			if ($srv['sid'] === $wanted) {
				$selected = $wanted;
				break;
			}
		}
	}

	$hourlyTbl = DB_PREFIX . '_server_hourly';
	$profileTbl = DB_PREFIX . '_server_hour_profile';
	$hasHourly = (bool)@$GLOBALS['db']->GetOne("SHOW TABLES LIKE " . $GLOBALS['db']->qstr($hourlyTbl));
	$hasProfile = (bool)@$GLOBALS['db']->GetOne("SHOW TABLES LIKE " . $GLOBALS['db']->qstr($profileTbl));
	$ready = $hasHourly && $hasProfile;

	$day = sb_online_day_rows(array());
	$profile = sb_online_profile_rows(array());
	$peak = null;
	if ($ready && $selected > 0) {
		$current = (int)strtotime(date('Y-m-d H:00:00'));
		$from = (int)strtotime('-23 hours', $current);
		$hourRows = @$GLOBALS['db']->GetAll(
			"SELECT `hour_ts`, `samples`, `players_sum`, `players_max` FROM `" . $hourlyTbl . "` WHERE `sid` = "
			. $selected . " AND `hour_ts` >= " . $from . " AND `hour_ts` <= " . $current
		);
		$byTs = array();
		if (is_array($hourRows)) {
			foreach ($hourRows as $row) {
				if (!is_array($row) || !isset($row['hour_ts']))
					continue;
				$byTs[(int)$row['hour_ts']] = $row;
			}
		}
		$day = sb_online_day_rows($byTs);

		$profRows = @$GLOBALS['db']->GetAll(
			"SELECT `hour_of_day`, `avg_players`, `samples` FROM `" . $profileTbl . "` WHERE `sid` = " . $selected
		);
		$byHour = array();
		if (is_array($profRows)) {
			foreach ($profRows as $row) {
				if (!is_array($row) || !isset($row['hour_of_day']))
					continue;
				$byHour[(int)$row['hour_of_day']] = $row;
			}
		}
		$profile = sb_online_profile_rows($byHour);

		$peakRaw = @$GLOBALS['db']->GetOne(
			"SELECT MAX(`players_max`) FROM `" . $hourlyTbl . "` WHERE `sid` = " . $selected
			. " AND `hour_ts` >= " . (int)strtotime('today')
		);
		if ($peakRaw !== null && $peakRaw !== false && $peakRaw !== '')
			$peak = (int)$peakRaw;
	}

	return array(
		'ready' => $ready,
		'servers' => $servers,
		'selected' => $selected,
		'day' => $day,
		'profile' => $profile,
		'peak_today' => $peak,
		'status_text' => sb_online_status_text(),
	);
}

function sb_online_refresh_hour($sid, $hourTs)
{
	$sid = (int)$sid;
	$hourTs = (int)$hourTs;
	$agg = $GLOBALS['db']->GetRow(
		"SELECT COUNT(*) AS samples, COALESCE(SUM(`players`),0) AS players_sum, COALESCE(MAX(`players`),0) AS players_max, COALESCE(SUM(`up`),0) AS up_samples"
		. " FROM `" . DB_PREFIX . "_server_samples` WHERE `sid` = " . $sid
		. " AND `ts` >= " . $hourTs . " AND `ts` < " . ($hourTs + 3600)
	);
	if (!is_array($agg))
		return;
	$samples = sb_online_clamp_small(isset($agg['samples']) ? (int)$agg['samples'] : 0);
	$playersSum = isset($agg['players_sum']) ? (int)$agg['players_sum'] : 0;
	if ($playersSum < 0)
		$playersSum = 0;
	$playersMax = sb_online_clamp_small(isset($agg['players_max']) ? (int)$agg['players_max'] : 0);
	$upSamples = sb_online_clamp_small(isset($agg['up_samples']) ? (int)$agg['up_samples'] : 0);
	$GLOBALS['db']->Execute(
		"REPLACE INTO `" . DB_PREFIX . "_server_hourly` (`sid`,`hour_ts`,`samples`,`players_sum`,`players_max`,`up_samples`) VALUES ("
		. $sid . "," . $hourTs . "," . $samples . "," . $playersSum . "," . $playersMax . "," . $upSamples . ")"
	);
}

function sb_online_refresh_profile($sid)
{
	$sid = (int)$sid;
	$since = (int)strtotime('-14 days');
	$rows = $GLOBALS['db']->GetAll(
		"SELECT `hour_ts`, `samples`, `players_sum` FROM `" . DB_PREFIX . "_server_hourly`"
		. " WHERE `sid` = " . $sid . " AND `hour_ts` >= " . $since
	);
	if (!is_array($rows))
		return;

	$bucket = array();
	for ($h = 0; $h < 24; $h++)
		$bucket[$h] = array('players_sum' => 0, 'samples' => 0);
	foreach ($rows as $row) {
		if (!is_array($row) || !isset($row['hour_ts']))
			continue;
		$h = (int)date('G', (int)$row['hour_ts']);
		if ($h < 0 || $h > 23)
			continue;
		$bucket[$h]['players_sum'] += isset($row['players_sum']) ? (int)$row['players_sum'] : 0;
		$bucket[$h]['samples'] += isset($row['samples']) ? (int)$row['samples'] : 0;
	}

	for ($h = 0; $h < 24; $h++) {
		$samples = $bucket[$h]['samples'];
		if ($samples < 0)
			$samples = 0;
		$avg = ($samples > 0) ? ($bucket[$h]['players_sum'] / $samples) : 0.0;
		$GLOBALS['db']->Execute(
			"REPLACE INTO `" . DB_PREFIX . "_server_hour_profile` (`sid`,`hour_of_day`,`avg_players`,`samples`) VALUES ("
			. $sid . "," . $h . "," . sprintf('%.2f', round($avg, 2)) . "," . $samples . ")"
		);
	}
	$GLOBALS['db']->Execute(
		"DELETE FROM `" . DB_PREFIX . "_server_hour_profile` WHERE `sid` = " . $sid
		. " AND `hour_of_day` NOT IN (0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23)"
	);
}

function sb_online_day_rows($byTs)
{
	if (!is_array($byTs))
		$byTs = array();
	$current = (int)strtotime(date('Y-m-d H:00:00'));
	$day = array();
	for ($i = 23; $i >= 0; $i--) {
		$hts = ($i === 0) ? $current : (int)strtotime('-' . $i . ' hours', $current);
		$avg = null;
		$max = null;
		if (isset($byTs[$hts]) && is_array($byTs[$hts])) {
			$samples = isset($byTs[$hts]['samples']) ? (int)$byTs[$hts]['samples'] : 0;
			if ($samples > 0 && isset($byTs[$hts]['players_sum']))
				$avg = (float)round(((int)$byTs[$hts]['players_sum']) / $samples, 2);
			if (isset($byTs[$hts]['players_max']))
				$max = (int)$byTs[$hts]['players_max'];
		}
		$day[] = array(
			'hour' => (int)date('G', $hts),
			'label' => date('H', $hts),
			'avg' => $avg,
			'max' => $max,
		);
	}
	return $day;
}

function sb_online_profile_rows($byHour)
{
	if (!is_array($byHour))
		$byHour = array();
	$profile = array();
	for ($h = 0; $h < 24; $h++) {
		$avg = null;
		$samples = (isset($byHour[$h]) && is_array($byHour[$h]) && isset($byHour[$h]['samples']))
			? (int)$byHour[$h]['samples'] : 0;
		if ($samples > 0 && isset($byHour[$h]['avg_players']))
			$avg = (float)round((float)$byHour[$h]['avg_players'], 2);
		$profile[] = array(
			'hour' => $h,
			'label' => sprintf('%02d', $h),
			'avg' => $avg,
			'max' => null,
		);
	}
	return $profile;
}

function sb_online_clamp_small($n)
{
	$n = (int)$n;
	if ($n < 0)
		return 0;
	if ($n > 32767)
		return 32767;
	return $n;
}

function sb_online_clip_map($map)
{
	$map = str_replace("\0", '', (string)$map);
	if (function_exists('mb_substr')) {
		$clip = mb_substr($map, 0, 64, 'UTF-8');
		if (is_string($clip))
			return $clip;
	}
	return substr($map, 0, 64);
}
