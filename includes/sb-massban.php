<?php
/**
 * Массовый бан Steam-группы и друзей.
 *
 * Без галочки «получать никнеймы»: список берётся из XML Steam
 * (memberslistxml / friends xml), в поле name пишется steamid64.
 * С галочкой — как раньше: HTML-страницы сообщества и ники с них.
 */

function sb_massban_fetch_nicks()
{
	return isset($GLOBALS['config']['config.fetchbannicks'])
		&& (string)$GLOBALS['config']['config.fetchbannicks'] === '1';
}

function sb_steam_community_ctx()
{
	return stream_context_create(array(
		'http' => array(
			'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'timeout' => 15,
			'follow_location' => 1,
			'header' => "Accept: text/xml,application/xml,text/html;q=0.9,*/*;q=0.8\r\n",
		),
	));
}

function sb_steam_xml_load($url)
{
	$raw = @file_get_contents($url, false, sb_steam_community_ctx());
	if (!is_string($raw) || $raw === '' || stripos($raw, '<html') !== false)
		return false;
	$raw = preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '', $raw);
	if (function_exists('strip_31_ascii'))
		$raw = strip_31_ascii($raw);
	$xml = @simplexml_load_string($raw);
	return ($xml === false) ? false : $xml;
}

function sb_massban_js($value)
{
	return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function sb_massban_ui_open($objResponse, $title)
{
	$objResponse->addScript('if (typeof sbMassBanUi === "function") sbMassBanUi(' . sb_massban_js($title) . ');');
}

function sb_massban_boot($kind, $title, $meta)
{
	$fetch = sb_massban_fetch_nicks();
	$_SESSION['mass_ban_state'] = array_merge(array(
		'kind' => $kind,
		'title' => $title,
		'fetch_nicks' => $fetch,
		'mode' => $fetch ? 'html' : 'xml',
		'steam_page' => 1,
		'page_members' => array(),
		'page_offset' => 0,
		'processed_count' => 0,
		'error_count' => 0,
		'start_time' => time(),
		'banned_steamids' => array(),
		'cache_loaded' => false,
		'total_members' => 0,
		'total_pages' => 0,
		'xml_exhausted' => false,
		'more_groups' => array(),
		'queue' => 'no',
		'last' => '',
		'reason' => '',
		'grpname' => '',
		'player_name' => '',
		'friendid' => '',
	), $meta);
}

function GroupBan($groupuri, $isgrpurl = "no", $queue = "no", $reason = "", $last = "")
{
	$objResponse = new xajaxResponse();

	if (empty($GLOBALS['config']['config.enablegroupbanning']) || !$GLOBALS['userbank']->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
		if (empty($GLOBALS['config']['config.enablegroupbanning']))
			return $objResponse;
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		new CSystemLog("w", "Ошибка доступа", $GLOBALS['username'] . " пытался забанить группу '" . htmlspecialchars(addslashes(trim($groupuri))) . "', не имея на это прав.");
		return $objResponse;
	}

	$chunks = ($isgrpurl === "yes")
		? array_values(array_filter(array_map('trim', explode(',', (string)$groupuri))))
		: array((string)$groupuri);

	$names = array();
	foreach ($chunks as $chunk) {
		$grpname = ($isgrpurl === "yes") ? $chunk : urldecode((string)basename((string)parse_url($chunk, PHP_URL_PATH)));
		$grpname = rtrim($grpname, '/');
		if ($grpname !== '')
			$names[] = $grpname;
	}

	if (empty($names)) {
		$objResponse->addAssign("groupurl.msg", "innerHTML", "Ошибка преобразования URL группы.");
		$objResponse->addScript("$('groupurl.msg').setStyle('display', 'block');");
		return $objResponse;
	}

	$objResponse->addScript("$('groupurl.msg').setStyle('display', 'none'); $('dialog-control').setStyle('display', 'none');");

	$first = array_shift($names);
	sb_massban_boot('group', 'Блокировка участников группы', array(
		'grpname' => $first,
		'queue' => $queue,
		'reason' => $reason,
		'last' => $last,
		'more_groups' => $names,
	));
	sb_massban_ui_open($objResponse, 'Блокировка участников группы');
	$objResponse->addScriptCall("xajax_BanMemberOfGroup");
	return $objResponse;
}

function BanFriends($friendid, $name)
{
	$objResponse = new xajaxResponse();
	if (empty($GLOBALS['config']['config.enablefriendsbanning']) || !is_numeric($friendid))
		return $objResponse;
	global $userbank, $username;
	if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		new CSystemLog("w", "Ошибка доступа", $username . " пытался забанить друзей, не имея прав.");
		return $objResponse;
	}

	$friendid = preg_replace('/\D+/', '', (string)$friendid);
	if (strlen($friendid) < 16)
		return $objResponse;

	$objResponse->addScript("if ($('dialog-control')) $('dialog-control').setStyle('display', 'none');");
	sb_massban_boot('friends', 'Блокировка друзей', array(
		'friendid' => $friendid,
		'player_name' => (string)$name,
		'reason' => 'Steam Community Friend Ban',
	));
	sb_massban_ui_open($objResponse, 'Блокировка друзей');
	$objResponse->addScriptCall("xajax_BanMemberOfGroup");
	return $objResponse;
}

function BanMemberOfGroup()
{
	set_time_limit(30);
	$objResponse = new xajaxResponse();

	if (!isset($_SESSION['mass_ban_state']) || !is_array($_SESSION['mass_ban_state'])) {
		$objResponse->addScript('if (typeof sbMassBanClear === "function") sbMassBanClear();');
		$objResponse->addScript("ShowBox('Ошибка', 'Процесс массового бана не инициализирован или уже завершён.', 'red', '', true);");
		return $objResponse;
	}

	$state = &$_SESSION['mass_ban_state'];
	$kind = isset($state['kind']) ? $state['kind'] : 'group';

	if ($kind === 'group' && empty($GLOBALS['config']['config.enablegroupbanning'])) {
		unset($_SESSION['mass_ban_state']);
		return $objResponse;
	}
	if ($kind === 'friends' && empty($GLOBALS['config']['config.enablefriendsbanning'])) {
		unset($_SESSION['mass_ban_state']);
		return $objResponse;
	}

	if (!$GLOBALS['userbank']->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
		$objResponse->redirect("index.php?p=login&m=no_access", 0);
		unset($_SESSION['mass_ban_state']);
		return $objResponse;
	}

	if (empty($state['cache_loaded'])) {
		$state['banned_steamids'] = getBannedSteamIds();
		$state['cache_loaded'] = true;
	}

	if ($kind === 'friends')
		return sb_friends_ban_step($objResponse, $state);

	return sb_group_ban_step($objResponse, $state);
}

function sb_group_ban_step($objResponse, &$state)
{
	$batch = empty($state['fetch_nicks']) ? 25 : 1;

	if ($state['page_offset'] >= count($state['page_members'])) {
		$loaded = sb_group_load_page($state);
		if ($loaded === false) {
			sb_massban_fail($objResponse, 'Не удалось получить список участников группы. Профиль группы скрыт или Steam не ответил.');
			unset($_SESSION['mass_ban_state']);
			return $objResponse;
		}
		if ($loaded === null) {
			return sb_group_ban_finish_or_next($objResponse, $state);
		}
	}

	$did = 0;
	while ($did < $batch && $state['page_offset'] < count($state['page_members'])) {
		$member = $state['page_members'][$state['page_offset']];
		$state['page_offset']++;
		$state['processed_count']++;
		$did++;
		if (!processMember($member, $state))
			$state['error_count']++;
	}

	updateProgress($objResponse, $state);
	$objResponse->addScriptCall("setTimeout", "xajax_BanMemberOfGroup()", 25);
	return $objResponse;
}

function sb_group_load_page(&$state)
{
	$grpname = $state['grpname'];
	$page = (int)$state['steam_page'];

	if (empty($state['fetch_nicks'])) {
		if (!empty($state['xml_exhausted']))
			return null;
		$data = sb_load_group_members_xml($grpname, $page);
		if ($data === null) {
			$html = loadGroupPage($grpname, $page);
			if (empty($html))
				return ($page <= 1) ? false : null;
			$state['page_members'] = sb_members_as_steamid64($html);
			$state['page_offset'] = 0;
			$state['steam_page']++;
			return empty($state['page_members']) ? null : true;
		}
		if (empty($data['ids'])) {
			$state['xml_exhausted'] = true;
			return null;
		}
		if (!empty($data['member_count']))
			$state['total_members'] = (int)$data['member_count'];
		if (!empty($data['total_pages']))
			$state['total_pages'] = (int)$data['total_pages'];
		$members = array();
		foreach ($data['ids'] as $id) {
			$members[] = array(
				'community_id' => $id,
				'name' => $id,
				'href' => 'https://steamcommunity.com/profiles/' . $id,
			);
		}
		$state['page_members'] = $members;
		$state['page_offset'] = 0;
		$state['steam_page']++;
		if ($state['total_pages'] > 0 && $page >= $state['total_pages'])
			$state['xml_exhausted'] = true;
		return true;
	}

	$new_members = loadGroupPage($grpname, $page);
	if (empty($new_members))
		return null;
	$state['page_members'] = $new_members;
	$state['page_offset'] = 0;
	$state['steam_page']++;
	return true;
}

function sb_group_ban_finish_or_next($objResponse, &$state)
{
	$more = isset($state['more_groups']) && is_array($state['more_groups']) ? $state['more_groups'] : array();
	if (!empty($more)) {
		$next = array_shift($more);
		$keep = array(
			'banned_steamids' => $state['banned_steamids'],
			'cache_loaded' => true,
			'queue' => $state['queue'],
			'reason' => $state['reason'],
			'last' => $state['last'],
			'start_time' => $state['start_time'],
			'processed_count' => $state['processed_count'],
			'error_count' => $state['error_count'],
		);
		sb_massban_boot('group', 'Блокировка участников группы', array_merge($keep, array(
			'grpname' => $next,
			'more_groups' => $more,
		)));
		updateProgress($objResponse, $_SESSION['mass_ban_state']);
		$objResponse->addScriptCall("setTimeout", "xajax_BanMemberOfGroup()", 40);
		return $objResponse;
	}

	finishBanning($objResponse, $state);
	unset($_SESSION['mass_ban_state']);
	return $objResponse;
}

function sb_friends_ban_step($objResponse, &$state)
{
	if (empty($state['page_members']) && empty($state['friends_loaded'])) {
		$loaded = sb_friends_load_list($state);
		$state['friends_loaded'] = true;
		if ($loaded === false || empty($state['page_members'])) {
			sb_massban_fail($objResponse, 'Не удалось найти друзей в профиле Steam. Профиль скрыт, список пуст, или Steam изменил страницу.');
			unset($_SESSION['mass_ban_state']);
			return $objResponse;
		}
		$state['total_members'] = count($state['page_members']);
	}

	$batch = empty($state['fetch_nicks']) ? 25 : 3;
	$did = 0;
	while ($did < $batch && $state['page_offset'] < count($state['page_members'])) {
		$member = $state['page_members'][$state['page_offset']];
		$state['page_offset']++;
		$state['processed_count']++;
		$did++;
		if (!processMember($member, $state))
			$state['error_count']++;
	}

	if ($state['page_offset'] >= count($state['page_members'])) {
		finishBanning($objResponse, $state);
		unset($_SESSION['mass_ban_state']);
		return $objResponse;
	}

	updateProgress($objResponse, $state);
	$objResponse->addScriptCall("setTimeout", "xajax_BanMemberOfGroup()", 25);
	return $objResponse;
}

function sb_friends_load_list(&$state)
{
	$friendid = $state['friendid'];
	if (empty($state['fetch_nicks'])) {
		$ids = sb_load_friends_xml($friendid);
		if (empty($ids)) {
			$html = sb_load_friends_html($friendid);
			$state['page_members'] = sb_members_as_steamid64($html);
			$state['page_offset'] = 0;
			$state['mode'] = empty($state['page_members']) ? 'xml' : 'html-ids';
			return !empty($state['page_members']);
		}
		$members = array();
		foreach ($ids as $id) {
			$members[] = array(
				'community_id' => $id,
				'name' => $id,
				'href' => 'https://steamcommunity.com/profiles/' . $id,
			);
		}
		$state['page_members'] = $members;
		$state['page_offset'] = 0;
		$state['mode'] = 'xml';
		return true;
	}

	$friends = sb_load_friends_html($friendid);
	if (empty($friends))
		return false;
	$state['page_members'] = $friends;
	$state['page_offset'] = 0;
	$state['mode'] = 'html';
	return true;
}

function sb_load_group_members_xml($grpname, $page)
{
	$page = max(1, (int)$page);
	$url = 'https://steamcommunity.com/groups/' . rawurlencode($grpname) . '/memberslistxml/?xml=1&p=' . $page;
	$xml = sb_steam_xml_load($url);
	if ($xml === false)
		return null;
	$ids = array();
	if (isset($xml->members->steamID64)) {
		foreach ($xml->members->steamID64 as $node) {
			$id = preg_replace('/\D+/', '', (string)$node);
			if (strlen($id) >= 16)
				$ids[] = $id;
		}
	}
	return array(
		'ids' => $ids,
		'total_pages' => isset($xml->totalPages) ? (int)$xml->totalPages : 1,
		'member_count' => isset($xml->memberCount) ? (int)$xml->memberCount : count($ids),
		'current_page' => isset($xml->currentPage) ? (int)$xml->currentPage : $page,
	);
}

function sb_load_friends_xml($friendid)
{
	$friendid = preg_replace('/\D+/', '', (string)$friendid);
	$url = 'https://steamcommunity.com/profiles/' . $friendid . '/friends/?xml=1';
	$raw = @file_get_contents($url, false, sb_steam_community_ctx());
	if (!is_string($raw) || $raw === '')
		return array();

	$ids = array();
	if (stripos($raw, '<html') === false) {
		$clean = preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '', $raw);
		if (function_exists('strip_31_ascii'))
			$clean = strip_31_ascii($clean);
		$xml = @simplexml_load_string($clean);
		if ($xml !== false) {
			if (isset($xml->friends->friend)) {
				foreach ($xml->friends->friend as $f) {
					$sid = '';
					if (isset($f->steamID64))
						$sid = (string)$f->steamID64;
					elseif (isset($f['steamID64']))
						$sid = (string)$f['steamID64'];
					$sid = preg_replace('/\D+/', '', $sid);
					if (strlen($sid) >= 16 && $sid !== $friendid)
						$ids[] = $sid;
				}
			}
		}
	}
	if (empty($ids) && preg_match_all('/<steamID64>(\d{16,20})<\/steamID64>/', $raw, $m)) {
		foreach ($m[1] as $sid) {
			if ($sid !== $friendid)
				$ids[] = $sid;
		}
	}
	return array_values(array_unique($ids));
}

function sb_load_friends_html($friendid)
{
	$ctx = sb_steam_community_ctx();
	$profile = 'https://steamcommunity.com/profiles/' . $friendid . '/';
	$headers = @get_headers($profile, 1);
	$loc = '';
	if (is_array($headers) && !empty($headers['Location']))
		$loc = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
	$base = ($loc !== '') ? rtrim($loc, '/') . '/' : $profile;
	$raw = @file_get_contents($base . 'friends', false, $ctx);
	if (!is_string($raw) || $raw === '')
		return array();

	$doc = new DOMDocument();
	@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $raw);
	$divs = $doc->getElementsByTagName('div');
	$friends = array();
	foreach ($divs as $div) {
		$class = $div->getAttribute('class');
		if (strpos($class, 'friend_block_v2') === false)
			continue;
		$profile_url = '';
		foreach ($div->getElementsByTagName('a') as $a) {
			$href = $a->getAttribute('href');
			if (strpos($href, 'steamcommunity.com/profiles/') !== false || strpos($href, 'steamcommunity.com/id/') !== false) {
				$profile_url = $href;
				break;
			}
		}
		$fname = '';
		foreach ($div->getElementsByTagName('div') as $cdiv) {
			if ($cdiv->getAttribute('class') === 'friend_block_content') {
				$fname = trim($cdiv->nodeValue);
				break;
			}
		}
		if ($profile_url === '')
			continue;
		$friends[] = array('href' => $profile_url, 'name' => $fname);
	}
	return $friends;
}

function sb_members_as_steamid64($members)
{
	$out = array();
	if (!is_array($members))
		return $out;
	foreach ($members as $member) {
		$href = isset($member['href']) ? $member['href'] : '';
		$path = parse_url($href, PHP_URL_PATH);
		$parts = explode('/', (string)$path);
		$profile_id = isset($parts[2]) ? $parts[2] : '';
		$community_id = '';
		if (strpos($href, 'steamcommunity.com/id/') !== false) {
			$fid = GetFriendIDFromCommunityID($profile_id);
			if ($fid)
				$community_id = (string)$fid;
		} else {
			$community_id = preg_replace('/\D+/', '', (string)$profile_id);
		}
		if (strlen($community_id) < 16)
			continue;
		$out[] = array(
			'community_id' => $community_id,
			'name' => $community_id,
			'href' => 'https://steamcommunity.com/profiles/' . $community_id,
		);
	}
	return $out;
}

function loadGroupPage($grpname, $page)
{
	$ctx = sb_steam_community_ctx();
	$page_url = "https://steamcommunity.com/groups/" . rawurlencode($grpname) . "/members" . ($page > 1 ? "?p={$page}" : "");
	$raw = @file_get_contents($page_url, false, $ctx);
	if (!$raw)
		return array();

	$doc = new DOMDocument();
	@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $raw);

	$members = array();
	foreach ($doc->getElementsByTagName('a') as $tag) {
		$href = $tag->getAttribute('href');
		if ((strpos($href, 'https://steamcommunity.com/id/') === 0
			|| strpos($href, 'https://steamcommunity.com/profiles/') === 0)
			&& $tag->hasChildNodes()
			&& $tag->childNodes->length == 1
			&& $tag->childNodes->item(0)->nodeValue != ""
		) {
			$members[] = array(
				'href' => $href,
				'name' => $tag->childNodes->item(0)->nodeValue,
			);
		}
	}
	return $members;
}

function getBannedSteamIds()
{
	$bans = $GLOBALS['db']->GetAll(
		"SELECT CAST(CAST(MID(authid,9,1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(authid,11,10) AS UNSIGNED) * 2 AS CHAR) AS community_id " .
		"FROM " . DB_PREFIX . "_bans " .
		"WHERE RemoveType IS NULL AND type = 0 AND authid LIKE 'STEAM\\_%'"
	);
	if (!is_array($bans))
		return array();
	$ids = array();
	foreach ($bans as $ban) {
		if (isset($ban['community_id']) && $ban['community_id'] !== '' && $ban['community_id'] !== null)
			$ids[] = (string)$ban['community_id'];
	}
	return $ids;
}

function processMember($member, &$state)
{
	$community_id = '';
	$name = '';

	if (!empty($member['community_id'])) {
		$community_id = preg_replace('/\D+/', '', (string)$member['community_id']);
		$name = isset($member['name']) ? (string)$member['name'] : $community_id;
	} elseif (!empty($member['href'])) {
		$path = parse_url($member['href'], PHP_URL_PATH);
		$url_parts = explode("/", (string)$path);
		$profile_id = isset($url_parts[2]) ? $url_parts[2] : '';
		$name = isset($member['name']) ? (string)$member['name'] : '';
		if (strpos($member['href'], 'steamcommunity.com/id/') !== false) {
			$friend_id = GetFriendIDFromCommunityID($profile_id);
			if (!$friend_id)
				return false;
			$community_id = (string)$friend_id;
		} else {
			$community_id = preg_replace('/\D+/', '', (string)$profile_id);
		}
	}

	if (strlen($community_id) < 16)
		return false;

	$steamid = FriendIDToSteamID($community_id);
	if (!$steamid)
		return false;

	if (in_array($community_id, $state['banned_steamids'], true))
		return true;

	$existing_ban = $GLOBALS['db']->GetRow("SELECT bid FROM " . DB_PREFIX . "_bans WHERE authid = ? AND RemoveType IS NULL AND type = 0", array($steamid));
	if ($existing_ban) {
		$state['banned_steamids'][] = $community_id;
		return true;
	}

	if (function_exists('sb_protected_steamids') && in_array($steamid, sb_protected_steamids(), true))
		return true;

	$admin_check = $GLOBALS['db']->GetRow("SELECT aid FROM " . DB_PREFIX . "_admins WHERE authid = ? LIMIT 1", array($steamid));
	if ($admin_check)
		return true;

	if (empty($state['fetch_nicks']))
		$display = $community_id;
	else {
		$display = sanitizeForMysql($name);
		if (!mb_check_encoding($display, 'UTF-8') || $display === '')
			$display = 'UNKNOWN NICKNAME';
	}

	if ($state['kind'] === 'friends') {
		$reason = empty($state['fetch_nicks'])
			? ('Steam Community Friend Ban (' . $community_id . ')')
			: ('Steam Community Friend Ban (' . $display . ')');
	} else {
		$reason = "Steam Community Group Ban (" . $state['grpname'] . ") " . $state['reason'];
	}

	$pre = $GLOBALS['db']->Prepare("INSERT INTO " . DB_PREFIX . "_bans(created,type,ip,authid,name,ends,length,reason,aid,adminIp) VALUES (UNIX_TIMESTAMP(),?,?,?,?,UNIX_TIMESTAMP(),?,?,?,?)");
	$aid = $GLOBALS['userbank']->GetAid();
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	$success = $GLOBALS['db']->Execute($pre, array(0, "", $steamid, $display, 0, $reason, $aid, $ip));

	if (!$success && $GLOBALS['db']->ErrorNo() == 1366) {
		$fallback = empty($state['fetch_nicks']) ? $community_id : 'UNKNOWN NICKNAME';
		$success = $GLOBALS['db']->Execute($pre, array(0, "", $steamid, $fallback, 0, $reason, $aid, $ip));
	}

	if ($success)
		$state['banned_steamids'][] = $community_id;

	return $success;
}

function updateProgress($objResponse, $state)
{
	$processed = (int)$state['processed_count'];
	$error_count = (int)$state['error_count'];
	$total = (int)$state['total_members'];
	$kind = isset($state['kind']) ? $state['kind'] : 'group';

	if ($kind === 'friends') {
		$line1 = 'Игрок: ' . (isset($state['player_name']) ? $state['player_name'] : '');
		$line2 = $total > 0
			? ('Обработано: ' . $processed . ' из ' . $total)
			: ('Обработано: ' . $processed . ' друзей');
		$pct = ($total > 0) ? min(100, round($processed * 100 / $total)) : min(99, $processed % 100);
	} else {
		$steam_page = max(1, (int)$state['steam_page'] - 1);
		$line1 = 'Группа: ' . (isset($state['grpname']) ? $state['grpname'] : '');
		if ($total > 0) {
			$line2 = 'Обработано: ' . $processed . ' из ' . $total;
			$pct = min(100, round($processed * 100 / $total));
		} else {
			$line2 = 'Обработано: ' . $processed . ' участников (стр. ' . $steam_page . ')';
			$pct = $processed % 100;
		}
	}

	$payload = array(
		'line1' => $line1,
		'line2' => $line2,
		'errors' => $error_count,
		'percent' => $pct,
		'label' => $processed . ($kind === 'friends' ? ' друзей' : ' участников'),
	);
	$objResponse->addScript('if (typeof sbMassBanProgress === "function") sbMassBanProgress(' . sb_massban_js($payload) . ');');
}

function finishBanning($objResponse, $state)
{
	$total_processed = (int)$state['processed_count'];
	$banned_count = $total_processed - (int)$state['error_count'];
	$elapsed_time = time() - (int)$state['start_time'];
	$time_str = formatTime($elapsed_time);
	$kind = isset($state['kind']) ? $state['kind'] : 'group';

	if ($kind === 'friends') {
		$title = 'Друзья забанены';
		$subtitle = 'Игрок: ' . (isset($state['player_name']) ? $state['player_name'] : '');
		$logWho = isset($state['player_name']) ? $state['player_name'] : $state['friendid'];
		$logTitle = 'Друзья забанены';
		$logBody = "Забанено {$banned_count} из {$total_processed} друзей у '{$logWho}'.<br>Ошибок: {$state['error_count']}. Время: {$time_str}";
	} else {
		$title = 'Группа успешно забанена';
		$subtitle = 'Группа: ' . (isset($state['grpname']) ? $state['grpname'] : '');
		$logTitle = 'Группа забанена';
		$logBody = "Забанено {$banned_count} из {$total_processed} обработанных участников группы '{$state['grpname']}'.<br>Ошибок: {$state['error_count']}. Время: {$time_str}";
	}

	$payload = array(
		'title' => $title,
		'subtitle' => $subtitle,
		'banned' => $banned_count,
		'errors' => (int)$state['error_count'],
		'total' => $total_processed,
		'time' => $time_str,
	);
	$objResponse->addScript('if (typeof sbMassBanDone === "function") sbMassBanDone(' . sb_massban_js($payload) . ');');

	if ($kind === 'group' && isset($state['queue']) && $state['queue'] == "yes") {
		$objResponse->addScript("$('steamGroupStatus').setStyle('display', 'block');");
		$objResponse->addAppend("steamGroupStatus", "innerHTML", "<p>Забанено {$banned_count} из {$total_processed} участников группы '{$state['grpname']}'. <br/>Ошибок: {$state['error_count']}.</p>");
		$objResponse->addScript("setTimeout(function() { location.reload(); }, 8000);");
		$objResponse->addScript("$('dialog-control').setStyle('display', 'block');");
	} else {
		$objResponse->addScript("if ($('dialog-control')) $('dialog-control').setStyle('display', 'block');");
	}

	new CSystemLog("m", $logTitle, $logBody);
}

function sb_massban_fail($objResponse, $message)
{
	$objResponse->addScript('if (typeof sbMassBanClear === "function") sbMassBanClear();');
	$objResponse->addScript("ShowBox('Ошибка', " . sb_massban_js($message) . ", 'red', 'index.php?p=banlist', true);");
	$objResponse->addScript("if ($('dialog-control')) $('dialog-control').setStyle('display', 'block');");
}

function formatTime($seconds)
{
	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;
	return $minutes > 0 ? "{$minutes} мин {$seconds} сек" : "{$seconds} сек";
}

function sanitizeForMysql($string)
{
	return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$string);
}
