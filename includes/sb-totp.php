<?php
/**
 * TOTP 2FA helpers for Blue Material Admin (RFC 6238).
 * PHP 7.1+ compatible, no Composer deps.
 */

if (!defined('IN_SB') && !defined('SB_VERSION')) {
	// Allow include from init after constants exist.
}

/** Ensure TOTP columns exist on _admins. */
function sb_totp_migrate_schema()
{
	if (empty($GLOBALS['db']) || !defined('DB_PREFIX'))
		return;
	$table = DB_PREFIX . '_admins';
	$cols = array(
		'totp_secret' => "ALTER TABLE `{$table}` ADD `totp_secret` VARCHAR(255) NULL DEFAULT NULL",
		'totp_enabled' => "ALTER TABLE `{$table}` ADD `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0",
		'totp_confirmed_at' => "ALTER TABLE `{$table}` ADD `totp_confirmed_at` INT(11) NULL DEFAULT NULL",
		'totp_recovery_codes' => "ALTER TABLE `{$table}` ADD `totp_recovery_codes` TEXT NULL",
	);
	foreach ($cols as $name => $sql) {
		$exists = @$GLOBALS['db']->GetRow("SHOW COLUMNS FROM `{$table}` LIKE " . $GLOBALS['db']->qstr($name));
		if (!$exists)
			@$GLOBALS['db']->Execute($sql);
	}
}

function sb_totp_key()
{
	$raw = (defined('SB_SALT') ? SB_SALT : 'SourceBans')
		. '|' . (defined('DB_PASS') ? DB_PASS : '')
		. '|' . (defined('DB_NAME') ? DB_NAME : '');
	return hash('sha256', $raw, true);
}

function sb_totp_encrypt($plaintext)
{
	$plaintext = (string)$plaintext;
	if ($plaintext === '')
		return '';
	$key = sb_totp_key();
	$iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
	$cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
	if ($cipher === false)
		return '';
	return base64_encode($iv . $cipher);
}

function sb_totp_decrypt($blob)
{
	$blob = (string)$blob;
	if ($blob === '')
		return '';
	$raw = base64_decode($blob, true);
	if ($raw === false || strlen($raw) < 17)
		return '';
	$iv = substr($raw, 0, 16);
	$cipher = substr($raw, 16);
	$plain = openssl_decrypt($cipher, 'AES-256-CBC', sb_totp_key(), OPENSSL_RAW_DATA, $iv);
	return ($plain === false) ? '' : $plain;
}

/** Base32 (RFC 4648) encode without padding. */
function sb_totp_base32_encode($data)
{
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$data = (string)$data;
	$bits = '';
	for ($i = 0, $len = strlen($data); $i < $len; $i++)
		$bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
	$out = '';
	for ($i = 0, $blen = strlen($bits); $i + 5 <= $blen; $i += 5)
		$out .= $alphabet[bindec(substr($bits, $i, 5))];
	$rem = strlen($bits) % 5;
	if ($rem)
		$out .= $alphabet[bindec(str_pad(substr($bits, -$rem), 5, '0', STR_PAD_RIGHT))];
	return $out;
}

function sb_totp_base32_decode($b32)
{
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string)$b32));
	if ($b32 === '')
		return '';
	$bits = '';
	for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
		$v = strpos($alphabet, $b32[$i]);
		if ($v === false)
			return '';
		$bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
	}
	$out = '';
	for ($i = 0, $blen = strlen($bits); $i + 8 <= $blen; $i += 8)
		$out .= chr(bindec(substr($bits, $i, 8)));
	return $out;
}

function sb_totp_generate_secret($bytes = 20)
{
	$raw = function_exists('random_bytes') ? random_bytes($bytes) : openssl_random_pseudo_bytes($bytes);
	return sb_totp_base32_encode($raw);
}

function sb_totp_code_at($secret_b32, $time = null, $period = 30, $digits = 6)
{
	if ($time === null)
		$time = time();
	$key = sb_totp_base32_decode($secret_b32);
	if ($key === '')
		return null;
	$counter = pack('N*', 0, (int)floor($time / $period));
	$hash = hash_hmac('sha1', $counter, $key, true);
	$offset = ord($hash[19]) & 0x0f;
	$truncated = (
		((ord($hash[$offset]) & 0x7f) << 24)
		| ((ord($hash[$offset + 1]) & 0xff) << 16)
		| ((ord($hash[$offset + 2]) & 0xff) << 8)
		| (ord($hash[$offset + 3]) & 0xff)
	);
	$mod = (int)pow(10, $digits);
	return str_pad((string)($truncated % $mod), $digits, '0', STR_PAD_LEFT);
}

function sb_totp_verify($secret_b32, $code, $window = 1)
{
	$code = preg_replace('/\s+/', '', (string)$code);
	if (!preg_match('/^\d{6}$/', $code))
		return false;
	$now = time();
	for ($w = -$window; $w <= $window; $w++) {
		$expect = sb_totp_code_at($secret_b32, $now + ($w * 30));
		if ($expect !== null && hash_equals($expect, $code))
			return true;
	}
	return false;
}

function sb_totp_otpauth_uri($secret_b32, $account, $issuer = 'SourceBans')
{
	$issuer = (string)$issuer;
	$account = (string)$account;
	$label = rawurlencode($issuer . ':' . $account);
	return 'otpauth://totp/' . $label
		. '?secret=' . rawurlencode($secret_b32)
		. '&issuer=' . rawurlencode($issuer)
		. '&algorithm=SHA1&digits=6&period=30';
}

function sb_totp_row($aid)
{
	$aid = (int)$aid;
	if ($aid <= 0)
		return null;
	return $GLOBALS['db']->GetRow(
		"SELECT `aid`, `user`, `email`, `extraflags`, `totp_secret`, `totp_enabled`, `totp_confirmed_at`, `totp_recovery_codes`, `password`
		 FROM `" . DB_PREFIX . "_admins` WHERE `aid` = ?",
		array($aid)
	);
}

function sb_totp_is_enabled($aid)
{
	$row = sb_totp_row($aid);
	return ($row && !empty($row['totp_enabled']) && !empty($row['totp_secret']));
}

function sb_totp_admin_is_owner($aid)
{
	$row = sb_totp_row($aid);
	if (!$row)
		return false;
	return (((int)$row['extraflags'] & ADMIN_OWNER) === ADMIN_OWNER);
}

function sb_totp_enforce_owner()
{
	return !empty($GLOBALS['config']['config.totp.enforce_owner'])
		&& (string)$GLOBALS['config']['config.totp.enforce_owner'] === '1';
}

/** After factor1: need MFA challenge or forced enroll? */
function sb_totp_gate_required($aid)
{
	$aid = (int)$aid;
	if ($aid <= 0)
		return 'none';
	if (sb_totp_is_enabled($aid))
		return 'challenge';
	if (sb_totp_enforce_owner() && sb_totp_admin_is_owner($aid))
		return 'enroll';
	return 'none';
}

function sb_mfa_begin($aid, $remember, $mode = 'challenge')
{
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() !== PHP_SESSION_ACTIVE)
		@session_start();
	if (session_status() === PHP_SESSION_ACTIVE)
		@session_regenerate_id(true);
	$_SESSION['sb_mfa_aid'] = (int)$aid;
	$_SESSION['sb_mfa_remember'] = $remember ? 1 : 0;
	$_SESSION['sb_mfa_mode'] = ($mode === 'enroll') ? 'enroll' : 'challenge';
	$_SESSION['sb_mfa_expires'] = time() + 600;
	unset($_SESSION['sb_totp_pending_secret'], $_SESSION['sb_totp_pending_codes']);
}

function sb_mfa_clear()
{
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() !== PHP_SESSION_ACTIVE)
		@session_start();
	unset(
		$_SESSION['sb_mfa_aid'],
		$_SESSION['sb_mfa_remember'],
		$_SESSION['sb_mfa_mode'],
		$_SESSION['sb_mfa_expires'],
		$_SESSION['sb_totp_pending_secret'],
		$_SESSION['sb_totp_pending_codes']
	);
}

/** @return array|null {aid, remember, mode} */
function sb_mfa_pending()
{
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() !== PHP_SESSION_ACTIVE)
		@session_start();
	if (empty($_SESSION['sb_mfa_aid']) || empty($_SESSION['sb_mfa_expires']))
		return null;
	if ((int)$_SESSION['sb_mfa_expires'] < time()) {
		sb_mfa_clear();
		return null;
	}
	return array(
		'aid' => (int)$_SESSION['sb_mfa_aid'],
		'remember' => !empty($_SESSION['sb_mfa_remember']),
		'mode' => (isset($_SESSION['sb_mfa_mode']) && $_SESSION['sb_mfa_mode'] === 'enroll') ? 'enroll' : 'challenge',
	);
}

function sb_totp_generate_recovery_codes($count = 8)
{
	$codes = array();
	for ($i = 0; $i < $count; $i++) {
		$raw = function_exists('random_bytes') ? random_bytes(5) : openssl_random_pseudo_bytes(5);
		$codes[] = strtoupper(bin2hex($raw));
	}
	return $codes;
}

function sb_totp_hash_recovery_codes(array $codes)
{
	$out = array();
	foreach ($codes as $c) {
		$c = strtoupper(preg_replace('/\s+/', '', (string)$c));
		if ($c !== '')
			$out[] = hash('sha256', $c);
	}
	return json_encode($out);
}

function sb_totp_consume_recovery($aid, $code)
{
	$aid = (int)$aid;
	$row = sb_totp_row($aid);
	if (!$row || empty($row['totp_recovery_codes']))
		return false;
	$code = strtoupper(preg_replace('/\s+/', '', (string)$code));
	if ($code === '' || strlen($code) < 8)
		return false;
	$hashes = json_decode($row['totp_recovery_codes'], true);
	if (!is_array($hashes))
		return false;
	$target = hash('sha256', $code);
	$found = false;
	$keep = array();
	foreach ($hashes as $h) {
		if (!$found && hash_equals((string)$h, $target)) {
			$found = true;
			continue;
		}
		$keep[] = $h;
	}
	if (!$found)
		return false;
	$GLOBALS['db']->Execute(
		"UPDATE `" . DB_PREFIX . "_admins` SET `totp_recovery_codes` = ? WHERE `aid` = ?",
		array(json_encode($keep), $aid)
	);
	return true;
}

function sb_totp_enable($aid, $secret_b32, array $recovery_plain)
{
	$aid = (int)$aid;
	$enc = sb_totp_encrypt($secret_b32);
	$GLOBALS['db']->Execute(
		"UPDATE `" . DB_PREFIX . "_admins` SET `totp_secret` = ?, `totp_enabled` = 1, `totp_confirmed_at` = ?, `totp_recovery_codes` = ? WHERE `aid` = ?",
		array($enc, time(), sb_totp_hash_recovery_codes($recovery_plain), $aid)
	);
}

function sb_totp_disable($aid)
{
	$aid = (int)$aid;
	$GLOBALS['db']->Execute(
		"UPDATE `" . DB_PREFIX . "_admins` SET `totp_secret` = NULL, `totp_enabled` = 0, `totp_confirmed_at` = NULL, `totp_recovery_codes` = NULL WHERE `aid` = ?",
		array($aid)
	);
}

function sb_totp_secret_for_aid($aid)
{
	$row = sb_totp_row($aid);
	if (!$row || empty($row['totp_secret']))
		return '';
	return sb_totp_decrypt($row['totp_secret']);
}

function sb_totp_issuer()
{
	if (!empty($GLOBALS['config']['template.title']))
		return preg_replace('/[^\p{L}\p{N}\s._-]/u', '', (string)$GLOBALS['config']['template.title']) ?: 'SourceBans';
	return 'SourceBans';
}
