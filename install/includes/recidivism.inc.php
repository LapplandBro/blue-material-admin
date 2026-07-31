<?php
/**
 * Схема рецидивизма (sb_recid_*) — общая для install/ и install_recidivism.php.
 * PHP 8.5+, безопасно при повторном запуске (IF NOT EXISTS / INSERT IGNORE).
 */
if (!defined('IN_SB') && !defined('IN_INSTALL')) {
	echo 'You should not be here. Only follow links!';
	die();
}

/**
 * @param string $prefix DB_PREFIX
 * @return string[] SQL statements
 */
function sb_install_recidivism_statements($prefix)
{
	$p = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$prefix);
	if ($p === '')
		$p = 'sb';

	return array(
		"CREATE TABLE IF NOT EXISTS `{$p}_recid_config` (
			`cfg_key` VARCHAR(64) NOT NULL,
			`cfg_value` VARCHAR(255) NOT NULL,
			`updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`cfg_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

		"INSERT IGNORE INTO `{$p}_recid_config` (`cfg_key`, `cfg_value`, `updated_at`) VALUES
			('window_days', '30', UNIX_TIMESTAMP()),
			('threshold_ban', '12', UNIX_TIMESTAMP()),
			('threshold_gag', '12', UNIX_TIMESTAMP()),
			('threshold_mute', '12', UNIX_TIMESTAMP()),
			('incident_group_seconds', '900', UNIX_TIMESTAMP()),
			('incident_require_same_admin', '0', UNIX_TIMESTAMP()),
			('mult_primary', '1.0', UNIX_TIMESTAMP()),
			('mult_secondary_track', '0.5', UNIX_TIMESTAMP()),
			('mult_same_track_extra', '0.25', UNIX_TIMESTAMP()),
			('silence_split_mult', '0.6', UNIX_TIMESTAMP()),
			('escalate_mode_ban', 'perm', UNIX_TIMESTAMP()),
			('escalate_min_ban_minutes', '10080', UNIX_TIMESTAMP()),
			('dry_run', '0', UNIX_TIMESTAMP()),
			('decay_enabled', '1', UNIX_TIMESTAMP())",

		"CREATE TABLE IF NOT EXISTS `{$p}_recid_incidents` (
			`incident_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`authid` VARCHAR(64) NOT NULL,
			`name` VARCHAR(128) NOT NULL DEFAULT '',
			`category` ENUM('tox','grief','voice','other') NOT NULL DEFAULT 'other',
			`primary_track` ENUM('ban','gag','mute') NULL DEFAULT NULL,
			`sid` INT NOT NULL DEFAULT 0,
			`opened_by` INT NOT NULL DEFAULT 0,
			`opened_at` INT UNSIGNED NOT NULL,
			`closed_at` INT UNSIGNED NULL DEFAULT NULL,
			`note` VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (`incident_id`),
			KEY `idx_auth_opened` (`authid`, `opened_at`),
			KEY `idx_opened` (`opened_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

		"CREATE TABLE IF NOT EXISTS `{$p}_recid_events` (
			`event_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`incident_id` INT UNSIGNED NOT NULL,
			`authid` VARCHAR(64) NOT NULL,
			`track` ENUM('ban','gag','mute') NOT NULL,
			`points_raw` DECIMAL(8,2) NOT NULL DEFAULT 0,
			`incident_multiplier` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
			`source` ENUM('ban','gag','mute','silence','warn','auto_escalate','manual','revoke','legacy_import') NOT NULL DEFAULT 'manual',
			`ma_table` ENUM('bans','comms','none') NOT NULL DEFAULT 'none',
			`ma_bid` INT UNSIGNED NULL DEFAULT NULL,
			`length_seconds` INT NOT NULL DEFAULT 0,
			`reason` VARCHAR(255) NOT NULL DEFAULT '',
			`aid` INT NOT NULL DEFAULT 0,
			`sid` INT NOT NULL DEFAULT 0,
			`created_at` INT UNSIGNED NOT NULL,
			`revoked` TINYINT(1) NOT NULL DEFAULT 0,
			`revoked_by` INT NOT NULL DEFAULT 0,
			`revoked_at` INT UNSIGNED NULL DEFAULT NULL,
			`revoke_reason` VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (`event_id`),
			KEY `idx_auth_track_created` (`authid`, `track`, `created_at`),
			KEY `idx_incident` (`incident_id`),
			KEY `idx_ma` (`ma_table`, `ma_bid`),
			KEY `idx_created` (`created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

		"CREATE TABLE IF NOT EXISTS `{$p}_recid_scores` (
			`authid` VARCHAR(64) NOT NULL,
			`track` ENUM('ban','gag','mute') NOT NULL,
			`score` DECIMAL(10,2) NOT NULL DEFAULT 0,
			`events_active` INT UNSIGNED NOT NULL DEFAULT 0,
			`escalated` TINYINT(1) NOT NULL DEFAULT 0,
			`escalated_at` INT UNSIGNED NULL DEFAULT NULL,
			`escalated_bid` INT UNSIGNED NULL DEFAULT NULL,
			`updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`authid`, `track`),
			KEY `idx_score` (`track`, `score`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

		"CREATE OR REPLACE VIEW `{$p}_recid_events_active` AS
			SELECT e.* FROM `{$p}_recid_events` AS e
			WHERE e.revoked = 0 AND e.created_at >= (UNIX_TIMESTAMP() - 30 * 86400)",
	);
}

/**
 * Применяет схему через ADOdb-соединение установщика.
 *
 * @param object $db ADOdb connection
 * @param string $prefix
 * @return array{ok:bool,logs:string[],errors:int}
 */
function sb_install_recidivism_apply($db, $prefix)
{
	$logs = array();
	$errors = 0;
	$n = 0;
	foreach (sb_install_recidivism_statements($prefix) as $sql) {
		$n++;
		$ok = @$db->Execute($sql);
		if ($ok) {
			$logs[] = "recidivism step $n OK";
			continue;
		}
		$err = method_exists($db, 'ErrorMsg') ? $db->ErrorMsg() : 'execute failed';
		// VIEW — не фатально (как в install_recidivism.php / плагине)
		if (stripos($sql, 'VIEW') !== false) {
			$logs[] = "recidivism step $n VIEW warn: $err (ignored)";
			continue;
		}
		$errors++;
		$logs[] = "recidivism step $n FAIL: $err";
		break;
	}
	return array(
		'ok' => ($errors === 0),
		'logs' => $logs,
		'errors' => $errors,
	);
}
