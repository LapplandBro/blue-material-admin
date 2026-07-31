<?php
/**
 * Панель PARSEC / Re-Banner fingerprint.
 *
 *   index.php?p=admin&c=parsec
 *   index.php?p=admin&c=parsec&steam=STEAM_0:0:XXXXX
 *   index.php?p=admin&c=parsec&fp=<hex>
 *
 * READ: маска рецидива. WRITE: OWNER|WEB_SETTINGS + allowlist SteamID + пароль + toggle.
 */
if (!defined("IN_SB")) { echo "Ошибка доступа!"; die(); }

global $userbank, $theme;

if (function_exists('sb_apply_steam_path_param'))
	sb_apply_steam_path_param();
if (function_exists('sb_canonical_admin_steam_redirect'))
	sb_canonical_admin_steam_redirect('parsec');

if (!function_exists('ParsecPanelCanView') || !ParsecPanelCanView()) {
	CheckAdminAccess(function_exists('RecidivismAccessMask')
		? RecidivismAccessMask()
		: (ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS));
}

function ma_parsec_normalize_authid($input)
{
	$input = trim((string)$input);
	if ($input === '')
		return '';
	if (function_exists('validate_steam') && validate_steam($input))
		return ParsecPanelNormalizeSteam($input);
	if (strpos($input, '[U:') === 0) {
		$accountId = getAccountId($input);
		if ($accountId >= 0)
			return ParsecPanelNormalizeSteam(renderSteam2($accountId, 0));
	}
	if (ctype_digit($input) && strlen($input) >= 15) {
		$sid = FriendIDToSteamID($input);
		if (!empty($sid))
			return ParsecPanelNormalizeSteam($sid);
	}
	return ParsecPanelNormalizeSteam($input);
}

$flash_ok = '';
$flash_err = '';
$csrf = ParsecPanelEnsureCsrf();

// --- POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parsec_action'])) {
	$action = (string)$_POST['parsec_action'];
	$token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
	if (!ParsecPanelCheckCsrf($token)) {
		$flash_err = 'Неверный CSRF-токен. Обновите страницу.';
	} else {
		switch ($action) {
			case 'unlock':
				$pw = isset($_POST['panel_password']) ? (string)$_POST['panel_password'] : '';
				if (ParsecPanelTryUnlock($pw)) {
					$flash_ok = 'Можно менять данные 30 минут. Включите переключатель «Разрешить правки».';
					new CSystemLog('m', 'PARSEC panel unlock', 'by ' . ParsecPanelAdminSteam());
				} else {
					$flash_err = 'Неверный пароль или у вашего аккаунта нет права менять эти данные.';
				}
				break;

			case 'lock':
				ParsecPanelLockSession();
				$flash_ok = 'Правки снова закрыты.';
				break;

			case 'toggle_write':
				$on = !empty($_POST['write_mode']);
				if (ParsecPanelSetWriteMode($on)) {
					$flash_ok = $on
						? 'Правки включены. Меняйте только статус группы («забанена / не забанена»). Код отпечатка ПК не трогайте.'
						: 'Правки выключены.';
				} else {
					$flash_err = 'Сначала введите пароль панели.';
				}
				break;

			case 'clear_ban':
				$fp = isset($_POST['fingerprint']) ? trim((string)$_POST['fingerprint']) : '';
				if (!ParsecPanelCanWrite()) {
					$flash_err = 'Правки не включены.';
				} elseif (ParsecPanelClearFingerprintBan($fp)) {
					$flash_ok = 'Группа больше не помечена как забаненная. Обычные баны в списке при этом не снимаются.';
				} else {
					$flash_err = 'Не удалось снять пометку с группы.';
				}
				break;

			case 'mark_ban':
				$fp = isset($_POST['fingerprint']) ? trim((string)$_POST['fingerprint']) : '';
				if (!ParsecPanelCanWrite()) {
					$flash_err = 'Правки не включены.';
				} elseif (ParsecPanelMarkFingerprintBanned($fp)) {
					$flash_ok = 'Группа помечена как забаненная (навсегда). Это не создаёт отдельный бан в списке банов.';
				} else {
					$flash_err = 'Не удалось пометить группу.';
				}
				break;

			default:
				$flash_err = 'Неизвестное действие.';
		}
	}
	// Refresh CSRF after POST
	unset($_SESSION[PARSEC_PANEL_SESSION_CSRF]);
	$csrf = ParsecPanelEnsureCsrf();
}

$tables_ok = ParsecPanelTablesOk();
$can_write_eligible = ParsecPanelCanWriteEligible();
$session_unlocked = ParsecPanelSessionUnlocked();
$write_mode = ParsecPanelWriteModeOn();
$can_write = ParsecPanelCanWrite();

$steam_input = isset($_GET['steam']) ? trim((string)$_GET['steam']) : '';
$fp_input = isset($_GET['fp']) ? trim((string)$_GET['fp']) : '';
$authid = $steam_input !== '' ? ma_parsec_normalize_authid($steam_input) : '';

$error_msg = '';
$family = null;
$fp_meta = null;
$linked = array();
$api_player = null;
$self_card = null;

if ($steam_input !== '' && $authid === '') {
	$error_msg = 'Не удалось распознать SteamID.';
} elseif ($authid !== '' && !$tables_ok && (!defined('PARSEC_API_PLAYER_URL') || PARSEC_API_PLAYER_URL === '')) {
	$error_msg = 'База отпечатков ПК недоступна, облако выключено — искать негде.';
}

if ($authid !== '' && $error_msg === '') {
	$family = RecidivismResolveFamily($authid);
	$fpId = (string)$family['fingerprint_id'];
	$fp_meta = array(
		'fingerprint' => $fpId,
		'fingerprint_fmt' => ParsecPanelFormatFingerprint($fpId),
		'is_banned' => (int)$family['is_banned'],
		'banned_duration' => (int)$family['banned_duration'],
		'banned_duration_fmt' => ParsecPanelFormatDuration($family['banned_duration']),
		'banned_timestamp' => (int)$family['banned_timestamp'],
		'banned_at_fmt' => !empty($family['banned_timestamp'])
			? date('d.m.Y H:i', (int)$family['banned_timestamp'])
			: '—'
	);
	$linked = RecidivismBuildLinkedCards($authid);
	foreach ($linked as &$la) {
		$la['banlist_url'] = sb_url('banlist', array('searchText' => $la['authid']));
		$la['parsec_url'] = sb_url('admin', array('c' => 'parsec', 'steam' => $la['authid']));
		if (empty($la['view_url']))
			$la['view_url'] = sb_url('admin', array('c' => 'recidivism', 'steam' => $la['authid']));
		$src = isset($la['sources']) ? $la['sources'] : array();
		$labels = array();
		$labelTexts = array();
		if (in_array('fingerprint', $src, true)) {
			$labels[] = array('text' => 'Один ПК', 'class' => 'parsec-chip-fp');
			$labelTexts[] = 'Один ПК';
		}
		if (in_array('api', $src, true)) {
			$labels[] = array('text' => 'Облако', 'class' => 'parsec-chip-api');
			$labelTexts[] = 'Облако';
		}
		$la['source_chips'] = $labels;
		$la['source_label_ru'] = $labelTexts ? implode(' + ', $labelTexts) : '—';
	}
	unset($la);

	// self row for completeness
	$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sb';
	$selfName = @$GLOBALS['db']->GetOne(
		"SELECT `name` FROM `{$prefix}_bans` WHERE `authid` = ? ORDER BY `created` DESC LIMIT 1",
		array($authid)
	);
	$selfActive = (int)@$GLOBALS['db']->GetOne(
		"SELECT COUNT(*) FROM `{$prefix}_bans`
		 WHERE `authid` = ? AND `RemoveType` IS NULL AND (`length` = 0 OR `ends` > UNIX_TIMESTAMP())",
		array($authid)
	);
	$scores = array('ban' => 0.0, 'gag' => 0.0, 'mute' => 0.0);
	$srows = @$GLOBALS['db']->GetAll(
		"SELECT `track`, `score` FROM `{$prefix}_recid_scores` WHERE `authid` = ?",
		array($authid)
	);
	if (is_array($srows)) {
		foreach ($srows as $r) {
			$tr = strtolower($r['track']);
			if (isset($scores[$tr]))
				$scores[$tr] = round((float)$r['score'], 1);
		}
	}
	$self_card = array(
		'authid' => $authid,
		'name' => $selfName ? $selfName : '',
		'active_ban' => $selfActive > 0,
		'points_ban' => $scores['ban'],
		'points_gag' => $scores['gag'],
		'points_mute' => $scores['mute'],
		'points_display' => sprintf('B%s G%s M%s', $scores['ban'], $scores['gag'], $scores['mute']),
		'family_size' => isset($family['all']) ? count($family['all']) : 1,
		'recid_url' => sb_url('admin', array('c' => 'recidivism', 'steam' => $authid)),
		'banlist_url' => sb_url('banlist', array('searchText' => $authid))
	);
	$api_player = ParsecPanelFetchApiPlayer($authid);
} elseif ($fp_input !== '' && $tables_ok) {
	$row = ParsecPanelGetFingerprint($fp_input);
	if (!$row) {
		$error_msg = 'Такой отпечаток ПК в базе не найден.';
	} else {
		$fp_meta = array(
			'fingerprint' => $row['fingerprint'],
			'fingerprint_fmt' => ParsecPanelFormatFingerprint($row['fingerprint']),
			'is_banned' => !empty($row['is_banned']) ? 1 : 0,
			'banned_duration' => (int)$row['banned_duration'],
			'banned_duration_fmt' => ParsecPanelFormatDuration($row['banned_duration']),
			'banned_timestamp' => (int)$row['banned_timestamp'],
			'banned_at_fmt' => !empty($row['banned_timestamp'])
				? date('d.m.Y H:i', (int)$row['banned_timestamp'])
				: '—'
		);
		$firstSteam = '';
		if (!empty($row['steamid2'])) {
			foreach (explode(';', $row['steamid2']) as $p) {
				$n = ParsecPanelNormalizeSteam($p);
				if ($n !== '') {
					$firstSteam = $n;
					break;
				}
			}
		}
		if ($firstSteam !== '') {
			sb_redirect(sb_url('admin', array('c' => 'parsec', 'steam' => $firstSteam)));
		}
	}
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$banned_list = $tables_ok
	? ParsecPanelListBannedFingerprints($page, 25)
	: array('rows' => array(), 'total' => 0);
$banned_pages = $banned_list['total'] > 0
	? (int)ceil($banned_list['total'] / 25)
	: 1;
$page_links = array();
for ($i = 1; $i <= $banned_pages; $i++) {
	$page_links[] = array(
		'n' => $i,
		'current' => ($i === $page),
		'url' => sb_url('admin', array('c' => 'parsec', 'page' => $i))
	);
}

$theme->assign('permission_ok', true);
$theme->assign('tables_ok', $tables_ok);
$theme->assign('flash_ok', $flash_ok);
$theme->assign('flash_err', $flash_err);
$theme->assign('error_msg', $error_msg);
$theme->assign('steam_input', $steam_input);
$theme->assign('authid', $authid);
$theme->assign('family', $family);
$theme->assign('fp_meta', $fp_meta);
$theme->assign('linked_accounts', $linked);
$theme->assign('self_card', $self_card);
$theme->assign('api_player', $api_player);
$theme->assign('banned_rows', $banned_list['rows']);
$theme->assign('banned_total', $banned_list['total']);
$theme->assign('banned_page', $page);
$theme->assign('banned_pages', $banned_pages);
$theme->assign('page_links', $page_links);
$theme->assign('can_write_eligible', $can_write_eligible);
$theme->assign('session_unlocked', $session_unlocked);
$theme->assign('write_mode', $write_mode);
$theme->assign('can_write', $can_write);
$theme->assign('csrf', $csrf);
$theme->assign('admin_steam', ParsecPanelAdminSteam());
$pwCfg = defined('PARSEC_PANEL_WRITE_PASSWORD') ? (string)PARSEC_PANEL_WRITE_PASSWORD : '';
$theme->assign('password_configured', ($pwCfg !== '' && $pwCfg !== 'change-me-parsec-panel'));
$form_action = $authid !== ''
	? sb_url('admin', array('c' => 'parsec', 'steam' => $authid))
	: sb_url('admin', array('c' => 'parsec'));
$theme->assign('form_action', $form_action);

$theme->display('page_admin_parsec.tpl');
