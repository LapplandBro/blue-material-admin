<?php
/**
 * Рецидив / история нарушений игрока (Ban / Gag / Mute).
 *
 * Открыть только через админку:
 *   index.php?p=admin&c=recidivism
 *   index.php?p=admin&c=recidivism&steam=STEAM_0:0:XXXXX
 *
 * Доступ: CheckAdminAccess в includes/admin.php + повторная проверка здесь
 * (никаких SQL до подтверждения прав).
 */
if (!defined("IN_SB")) { echo "Ошибка доступа!"; die(); }

global $userbank, $theme;

$recidMask = function_exists('RecidivismAccessMask')
	? RecidivismAccessMask()
	: (ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS);

// Defense-in-depth: роут уже зовёт CheckAdminAccess, но страница не должна
// отдавать данные, если её когда-либо include'нут мимо admin.php.
if (!$userbank || !$userbank->HasAccess($recidMask)) {
	CheckAdminAccess($recidMask); // redirect + die
}

/**
 * Приводит ввод (Steam2 / Steam3 / CommunityID) к STEAM_0:X:Y.
 */
function ma_recidivism_normalize_authid($input)
{
	$input = trim((string)$input);
	if ($input === '')
		return '';

	if (function_exists('validate_steam') && validate_steam($input))
		return $input;

	if (strpos($input, '[U:') === 0) {
		$accountId = getAccountId($input);
		if ($accountId >= 0)
			return renderSteam2($accountId, 0);
	}

	if (ctype_digit($input) && strlen($input) >= 15) {
		$sid = FriendIDToSteamID($input);
		if (!empty($sid) && function_exists('validate_steam') && validate_steam($sid))
			return $sid;
	}

	return $input;
}

function ma_recidivism_track_label($track)
{
	switch (strtolower((string)$track)) {
		case 'ban':  return 'Бан';
		case 'gag':  return 'Гаг';
		case 'mute': return 'Мут';
		default:     return htmlspecialchars((string)$track, ENT_QUOTES, 'UTF-8');
	}
}

function ma_recidivism_category_label($cat)
{
	switch (strtolower((string)$cat)) {
		case 'tox':   return 'Tox';
		case 'grief': return 'Grief';
		case 'voice': return 'Voice';
		case 'other': return 'Другое';
		default:      return $cat !== '' ? htmlspecialchars((string)$cat, ENT_QUOTES, 'UTF-8') : '—';
	}
}

function ma_recidivism_source_label($src)
{
	$map = array(
		'ban' => 'Бан',
		'gag' => 'Гаг',
		'mute' => 'Мут',
		'silence' => 'Silence',
		'warn' => 'Варн',
		'auto_escalate' => 'Автоэскалация',
		'manual' => 'Вручную',
		'revoke' => 'Снятие',
		'legacy_import' => 'Импорт'
	);
	$src = strtolower((string)$src);
	return isset($map[$src]) ? $map[$src] : htmlspecialchars((string)$src, ENT_QUOTES, 'UTF-8');
}

$pfx = DB_PREFIX;
$tblScores = $pfx . '_recid_scores';
$tblEvents = $pfx . '_recid_events';
$tblIncidents = $pfx . '_recid_incidents';
$tblConfig = $pfx . '_recid_config';

$tablesOk = true;
foreach (array($tblScores, $tblEvents, $tblIncidents) as $t) {
	$chk = @$GLOBALS['db']->GetOne("SHOW TABLES LIKE " . $GLOBALS['db']->qstr($t));
	if (!$chk) {
		$tablesOk = false;
		break;
	}
}

$windowDays = 30;
$thrBan = 12.0;
$thrGag = 12.0;
$thrMute = 12.0;
if ($tablesOk) {
	$cfgWin = @$GLOBALS['db']->GetOne(
		"SELECT `cfg_value` FROM `" . $tblConfig . "` WHERE `cfg_key` = 'window_days'"
	);
	if ($cfgWin !== false && $cfgWin !== null && (int)$cfgWin > 0)
		$windowDays = (int)$cfgWin;
	$cfgThr = @$GLOBALS['db']->GetAll(
		"SELECT `cfg_key`, `cfg_value` FROM `" . $tblConfig . "`
		 WHERE `cfg_key` IN ('threshold_ban','threshold_gag','threshold_mute')"
	);
	if (is_array($cfgThr)) {
		foreach ($cfgThr as $cr) {
			$k = $cr['cfg_key'];
			$v = (float)$cr['cfg_value'];
			if ($k === 'threshold_ban' && $v > 0) $thrBan = $v;
			elseif ($k === 'threshold_gag' && $v > 0) $thrGag = $v;
			elseif ($k === 'threshold_mute' && $v > 0) $thrMute = $v;
		}
	}
}
$since = time() - ($windowDays * 86400);

function ma_recidivism_track_class($track)
{
	switch (strtolower((string)$track)) {
		case 'ban': return 'recid-track-ban';
		case 'gag': return 'recid-track-gag';
		case 'mute': return 'recid-track-mute';
		default: return 'recid-track-other';
	}
}

function ma_recidivism_pct($score, $thr)
{
	$thr = (float)$thr;
	if ($thr <= 0)
		return 0;
	$pct = ((float)$score / $thr) * 100.0;
	if ($pct < 0) $pct = 0;
	if ($pct > 100) $pct = 100;
	return (int)round($pct);
}

$steamInput = '';
if (isset($_GET['steam']))
	$steamInput = trim((string)$_GET['steam']);
elseif (isset($_POST['steam']))
	$steamInput = trim((string)$_POST['steam']);

$authid = $steamInput !== '' ? ma_recidivism_normalize_authid($steamInput) : '';

$player = null;
$events = array();
$eventCount = 0;
$recentPlayers = array();
$errorMsg = '';

if (!$tablesOk) {
	$errorMsg = 'Таблицы рецидива ещё не установлены. Выполните SQL из docs/schema.sql (см. также site/install_recidivism.sql).';
} elseif ($authid !== '') {
	if (!function_exists('validate_steam') || !validate_steam($authid)) {
		$errorMsg = 'Некорректный SteamID.';
	} else {
		$scoreRows = $GLOBALS['db']->GetAll(
			"SELECT `track`, `score`, `escalated`, `updated_at`
			 FROM `" . $tblScores . "` WHERE `authid` = ?",
			array($authid)
		);
		$points = array('ban' => 0.0, 'gag' => 0.0, 'mute' => 0.0);
		$escalated = array('ban' => 0, 'gag' => 0, 'mute' => 0);
		$updatedAt = 0;
		$missingRow = true;
		if (is_array($scoreRows) && count($scoreRows) > 0) {
			$missingRow = false;
			foreach ($scoreRows as $sr) {
				$tr = strtolower($sr['track']);
				if (isset($points[$tr])) {
					$points[$tr] = round((float)$sr['score'], 2);
					$escalated[$tr] = !empty($sr['escalated']) ? 1 : 0;
					$updatedAt = max($updatedAt, (int)$sr['updated_at']);
				}
			}
		}

		$name = $GLOBALS['db']->GetOne(
			"SELECT `name` FROM `" . $tblIncidents . "` WHERE `authid` = ? AND `name` <> '' ORDER BY `opened_at` DESC LIMIT 1",
			array($authid)
		);
		if (!$name) {
			$name = $GLOBALS['db']->GetOne(
				"SELECT `name` FROM `" . $pfx . "_bans` WHERE `authid` = ? ORDER BY `created` DESC LIMIT 1",
				array($authid)
			);
		}
		if (!$name) {
			$name = $GLOBALS['db']->GetOne(
				"SELECT `name` FROM `" . $pfx . "_comms` WHERE `authid` = ? ORDER BY `created` DESC LIMIT 1",
				array($authid)
			);
		}

		$linkedCards = function_exists('RecidivismBuildLinkedCards')
			? RecidivismBuildLinkedCards($authid)
			: array();
		$familyInfo = function_exists('RecidivismResolveFamily')
			? RecidivismResolveFamily($authid)
			: array('all' => array($authid), 'fingerprint_id' => '', 'is_banned' => 0);

		foreach ($linkedCards as &$la) {
			$la['parsec_url'] = 'index.php?p=admin&c=parsec&steam=' . rawurlencode($la['authid']);
			$la['banlist_url'] = 'index.php?p=banlist&searchText=' . rawurlencode($la['authid']);
			$src = isset($la['sources']) ? $la['sources'] : array();
			$chips = array();
			if (in_array('fingerprint', $src, true))
				$chips[] = array('text' => 'Один ПК', 'class' => 'parsec-chip-fp');
			if (in_array('api', $src, true))
				$chips[] = array('text' => 'Облако', 'class' => 'parsec-chip-api');
			$la['source_chips'] = $chips;
		}
		unset($la);

		// Family-max scores (не сумма)
		$famMax = array('ban' => $points['ban'], 'gag' => $points['gag'], 'mute' => $points['mute']);
		if (!empty($familyInfo['all']) && count($familyInfo['all']) > 1) {
			$ph = implode(',', array_fill(0, count($familyInfo['all']), '?'));
			$famRows = @$GLOBALS['db']->GetAll(
				"SELECT `track`, MAX(`score`) AS mx FROM `" . $tblScores . "`
				 WHERE `authid` IN ($ph) GROUP BY `track`",
				$familyInfo['all']
			);
			if (is_array($famRows)) {
				foreach ($famRows as $fr) {
					$tr = strtolower($fr['track']);
					if (isset($famMax[$tr]))
						$famMax[$tr] = round((float)$fr['mx'], 2);
				}
			}
		}

		$fpId = isset($familyInfo['fingerprint_id']) ? (string)$familyInfo['fingerprint_id'] : '';
		$player = array(
			'authid' => $authid,
			'name' => $name ? $name : '',
			'points_ban' => $points['ban'],
			'points_gag' => $points['gag'],
			'points_mute' => $points['mute'],
			'points_total' => round($points['ban'] + $points['gag'] + $points['mute'], 2),
			'pct_ban' => ma_recidivism_pct($points['ban'], $thrBan),
			'pct_gag' => ma_recidivism_pct($points['gag'], $thrGag),
			'pct_mute' => ma_recidivism_pct($points['mute'], $thrMute),
			'family_max_ban' => $famMax['ban'],
			'family_max_gag' => $famMax['gag'],
			'family_max_mute' => $famMax['mute'],
			'family_size' => count($familyInfo['all']),
			'fingerprint_id' => $fpId,
			'fingerprint_fmt' => function_exists('ParsecPanelFormatFingerprint')
				? ParsecPanelFormatFingerprint($fpId)
				: $fpId,
			'fp_is_banned' => !empty($familyInfo['is_banned']) ? 1 : 0,
			'escalated_ban' => $escalated['ban'],
			'escalated_gag' => $escalated['gag'],
			'escalated_mute' => $escalated['mute'],
			'updated_fmt' => $updatedAt ? date('d.m.Y H:i', $updatedAt) : '—',
			'missing_row' => $missingRow,
			'community_url' => 'https://steamcommunity.com/profiles/' . SteamIDToFriendID($authid),
			'banlist_url' => 'index.php?p=banlist&advSearch=' . urlencode($authid) . '&advType=steamid',
			'commslist_url' => 'index.php?p=commslist&advSearch=' . urlencode($authid) . '&advType=steamid',
			'parsec_url' => 'index.php?p=admin&c=parsec&steam=' . rawurlencode($authid)
		);

		$events = $GLOBALS['db']->GetAll(
			"SELECT e.*, i.`category`
			 FROM `" . $tblEvents . "` AS e
			 LEFT JOIN `" . $tblIncidents . "` AS i ON i.`incident_id` = e.`incident_id`
			 WHERE e.authid = ? AND e.created_at >= ?
			 ORDER BY e.created_at DESC, e.event_id DESC
			 LIMIT 100",
			array($authid, $since)
		);
		if (!is_array($events))
			$events = array();

		$adminsCache = array();
		foreach ($events as &$ev) {
			$ev['track_label'] = ma_recidivism_track_label(isset($ev['track']) ? $ev['track'] : '');
			$ev['track_class'] = ma_recidivism_track_class(isset($ev['track']) ? $ev['track'] : '');
			$ev['category_label'] = ma_recidivism_category_label(isset($ev['category']) ? $ev['category'] : '');
			$ev['source_label'] = ma_recidivism_source_label(isset($ev['source']) ? $ev['source'] : '');
			$ev['created_fmt'] = !empty($ev['created_at']) ? date('d.m.Y H:i', (int)$ev['created_at']) : '—';
			$ev['is_revoked'] = !empty($ev['revoked']);
			$raw = isset($ev['points_raw']) ? (float)$ev['points_raw'] : 0.0;
			$mult = isset($ev['incident_multiplier']) ? (float)$ev['incident_multiplier'] : 1.0;
			$rawEff = $raw * $mult;
			// Same soft decay as ma_recidivism / DESIGN §3 (linear to 0 at window end)
			$createdAt = !empty($ev['created_at']) ? (int)$ev['created_at'] : time();
			$windowSec = max(1, $windowDays * 86400);
			$weight = 1.0 - ((time() - $createdAt) / $windowSec);
			if ($weight < 0.0) $weight = 0.0;
			if ($weight > 1.0) $weight = 1.0;
			$ev['points_raw_total'] = round($rawEff, 2);
			$ev['points_display'] = round($rawEff * $weight, 2);
			$ev['decay_weight'] = round($weight, 4);
			$ev['has_decay'] = ($ev['points_display'] + 0.001 < $ev['points_raw_total']);
			$lenSec = isset($ev['length_seconds']) ? (int)$ev['length_seconds'] : 0;
			$ev['length_fmt'] = ($lenSec <= 0)
				? 'перм'
				: (function_exists('ParsecPanelFormatDuration')
					? ParsecPanelFormatDuration($lenSec)
					: ($lenSec . 'с'));
			$aid = isset($ev['aid']) ? (int)$ev['aid'] : 0;
			if ($aid > 0) {
				if (!isset($adminsCache[$aid])) {
					$adminsCache[$aid] = $GLOBALS['db']->GetOne(
						"SELECT `user` FROM `" . $pfx . "_admins` WHERE `aid` = ?",
						array($aid)
					);
				}
				$ev['admin_display'] = $adminsCache[$aid] ? $adminsCache[$aid] : ('aid#' . $aid);
			} else {
				$ev['admin_display'] = 'консоль / авто';
			}
			$ev['source_link'] = '';
			if (!empty($ev['ma_table']) && !empty($ev['ma_bid'])) {
				if ($ev['ma_table'] === 'bans')
					$ev['source_link'] = 'index.php?p=banlist&advSearch=' . urlencode($authid) . '&advType=steamid';
				elseif ($ev['ma_table'] === 'comms')
					$ev['source_link'] = 'index.php?p=commslist&advSearch=' . urlencode($authid) . '&advType=steamid';
			}
		}
		unset($ev);
		$eventCount = count($events);
	}
} elseif ($tablesOk) {
	// Топ рецидивистов по сумме очков
	$recentPlayers = $GLOBALS['db']->GetAll(
		"SELECT s.authid,
			MAX(CASE WHEN s.track = 'ban' THEN s.score ELSE 0 END) AS points_ban,
			MAX(CASE WHEN s.track = 'gag' THEN s.score ELSE 0 END) AS points_gag,
			MAX(CASE WHEN s.track = 'mute' THEN s.score ELSE 0 END) AS points_mute,
			MAX(CASE WHEN s.track = 'ban' THEN s.escalated ELSE 0 END) AS escalated_ban,
			MAX(CASE WHEN s.track = 'gag' THEN s.escalated ELSE 0 END) AS escalated_gag,
			MAX(CASE WHEN s.track = 'mute' THEN s.escalated ELSE 0 END) AS escalated_mute,
			MAX(s.updated_at) AS updated_at,
			(MAX(CASE WHEN s.track = 'ban' THEN s.score ELSE 0 END)
			 + MAX(CASE WHEN s.track = 'gag' THEN s.score ELSE 0 END)
			 + MAX(CASE WHEN s.track = 'mute' THEN s.score ELSE 0 END)) AS points_total
		 FROM `" . $tblScores . "` AS s
		 GROUP BY s.authid
		 HAVING (MAX(CASE WHEN s.track = 'ban' THEN s.score ELSE 0 END)
			 + MAX(CASE WHEN s.track = 'gag' THEN s.score ELSE 0 END)
			 + MAX(CASE WHEN s.track = 'mute' THEN s.score ELSE 0 END)) > 0
		 ORDER BY points_total DESC
		 LIMIT 40"
	);
	if (!is_array($recentPlayers))
		$recentPlayers = array();
	foreach ($recentPlayers as &$rp) {
		$nm = $GLOBALS['db']->GetOne(
			"SELECT `name` FROM `" . $tblIncidents . "` WHERE `authid` = ? AND `name` <> '' ORDER BY `opened_at` DESC LIMIT 1",
			array($rp['authid'])
		);
		$rp['name'] = $nm ? $nm : '';
		$rp['updated_fmt'] = !empty($rp['updated_at']) ? date('d.m.Y H:i', (int)$rp['updated_at']) : '—';
		$rp['view_url'] = 'index.php?p=admin&c=recidivism&steam=' . urlencode($rp['authid']);
		$rp['points_ban'] = round((float)$rp['points_ban'], 2);
		$rp['points_gag'] = round((float)$rp['points_gag'], 2);
		$rp['points_mute'] = round((float)$rp['points_mute'], 2);
		$rp['points_total'] = isset($rp['points_total'])
			? round((float)$rp['points_total'], 2)
			: round($rp['points_ban'] + $rp['points_gag'] + $rp['points_mute'], 2);
		$rp['has_escalate'] = !empty($rp['escalated_ban']) || !empty($rp['escalated_gag']) || !empty($rp['escalated_mute']);
		$rp['parsec_url'] = 'index.php?p=admin&c=parsec&steam=' . rawurlencode($rp['authid']);
	}
	unset($rp);
}

if (!isset($linkedCards))
	$linkedCards = array();

$theme->assign('tables_ok', $tablesOk);
$theme->assign('error_msg', $errorMsg);
$theme->assign('steam_input', $steamInput);
$theme->assign('authid', $authid);
$theme->assign('player', $player);
$theme->assign('events', $events);
$theme->assign('event_count', $eventCount);
$theme->assign('recent_players', $recentPlayers);
$theme->assign('linked_accounts', $linkedCards);
$theme->assign('window_days', $windowDays);
$theme->assign('thr_ban', $thrBan);
$theme->assign('thr_gag', $thrGag);
$theme->assign('thr_mute', $thrMute);
$theme->assign('permission_ok', true);

$theme->display('page_admin_recidivism.tpl');
