#pragma semicolon 1

#include <sourcemod>
#include <adminmenu>

#undef REQUIRE_PLUGIN
#include <materialadmin>
#define REQUIRE_PLUGIN

#pragma newdecls required

/*
 * Schema: docs/schema.sql  (sb_recid_config / incidents / events / scores)
 * Design: docs/DESIGN.md
 *
 * CVars override sb_recid_config when changed (defaults match schema.sql):
 *   ma_recidivism_enable              1
 *   ma_recidivism_window_days         30
 *   ma_recidivism_incident_sec        900
 *   ma_recidivism_require_same_admin  0
 *   ma_recidivism_mult_primary        1.0
 *   ma_recidivism_mult_secondary      0.5
 *   ma_recidivism_mult_same_track     0.25
 *   ma_recidivism_silence_split       0.6
 *   ma_recidivism_threshold_ban       12
 *   ma_recidivism_threshold_gag       12
 *   ma_recidivism_threshold_mute      12
 *   ma_recidivism_dry_run             1   (1=score only, no auto action)
 *   ma_recidivism_revoke_on_unpunish  1   (unban/unmute → revoke points)
 *   ma_recidivism_decay               1
 *   ma_recidivism_escalate_mode       perm
 *   ma_recidivism_notify_admins       1
 */

#define PLUGIN_VERSION "1.2.0"
#define REASON_AUTO    "[AUTO]"

#define TRACK_BAN  0
#define TRACK_GAG  1
#define TRACK_MUTE 2
#define TRACK_COUNT 3

#define MA_MUTE_VOICE 1
#define MA_MUTE_CHAT  2
#define MA_MUTE_BOTH  3

#define DB_CHECK_INTERVAL     30.0
#define MAX_RECONNECT_ATTEMPTS 5
#define RECONNECT_DELAY       3.0
#define FAMILY_CACHE_TTL      60.0
#define FAMILY_MAX_MEMBERS    24

#define SCHEMA_STEP_CONFIG    1
#define SCHEMA_STEP_INCIDENTS 2
#define SCHEMA_STEP_EVENTS    3
#define SCHEMA_STEP_SCORES    4
#define SCHEMA_STEP_VIEW      5
#define SCHEMA_STEP_SEED      6
#define SCHEMA_STEP_LOADCFG   7

public Plugin myinfo =
{
	name = "[MA] Recidivism",
	author = "sibnet-moderation",
	description = "Recidivism tracks via sb_recid_* (DESIGN.md / schema.sql)",
	version = PLUGIN_VERSION,
	url = "https://sibnet-software.ru"
};

// Runtime config (seeded from schema defaults, then DB, then cvars)
int g_iWindowDays = 30;
int g_iIncidentSec = 900;
bool g_bRequireSameAdmin = false;
float g_fMultPrimary = 1.0;
float g_fMultSecondary = 0.5;
float g_fMultSameTrack = 0.25;
float g_fSilenceSplit = 0.6;
float g_fThresholdBan = 12.0;
float g_fThresholdGag = 12.0;
float g_fThresholdMute = 12.0;
bool g_bDryRun = true;
bool g_bDecay = true;
char g_sEscalateModeBan[16] = "perm";
int g_iEscalateMinBanMin = 10080;

ConVar g_cvEnable;
ConVar g_cvWindowDays;
ConVar g_cvIncidentSec;
ConVar g_cvRequireSameAdmin;
ConVar g_cvMultPrimary;
ConVar g_cvMultSecondary;
ConVar g_cvMultSameTrack;
ConVar g_cvSilenceSplit;
ConVar g_cvThresholdBan;
ConVar g_cvThresholdGag;
ConVar g_cvThresholdMute;
ConVar g_cvDryRun;
ConVar g_cvRevokeOnUnpunish;
ConVar g_cvDecay;
ConVar g_cvEscalateMode;
ConVar g_cvNotifyAdmins;

bool g_bMAAvailable;
bool g_bDBReady;
bool g_bSchemaReady;
bool g_bSchemaInstalling; // DDL chain in flight — don't start a second EnsureSchema
bool g_bInternalAction;
bool g_bApplyingCvars; // avoid feedback loops when syncing from DB
char g_sPrefix[32] = "sb";

// Soft cache only — ALWAYS refresh via GetWorkingDB() / MAGetDatabase() (see materialadmin_check)
Database g_hDB;
Handle g_hDBCheckTimer;
int g_iReconnectAttempts;
float g_flLastCheck;

TopMenu g_hAdminMenu;
TopMenuObject g_objPlayerCmds = INVALID_TOPMENUOBJECT;

// Re-Banner DB (optional) — fingerprint families for family-max scores
Database g_hRebanDB;
bool g_bRebanTried;
StringMap g_hFamilyCache;     // authid → "id;id;id"
StringMap g_hFamilyCacheTime; // authid → timestamp string

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	MarkNativeAsOptional("MABanPlayer");
	MarkNativeAsOptional("MAOffBanPlayer");
	MarkNativeAsOptional("MASetClientMuteType");
	MarkNativeAsOptional("MAOffSetClientMuteType");
	MarkNativeAsOptional("MAGetDatabase");
	MarkNativeAsOptional("MAGetConfigSetting");
	MarkNativeAsOptional("MALog");
	return APLRes_Success;
}

public void OnPluginStart()
{
	CreateConVar("ma_recidivism_version", PLUGIN_VERSION, "MA Recidivism version", FCVAR_NOTIFY|FCVAR_DONTRECORD);

	g_cvEnable = CreateConVar("ma_recidivism_enable", "1", "Enable recidivism scoring", _, true, 0.0, true, 1.0);
	g_cvWindowDays = CreateConVar("ma_recidivism_window_days", "30", "Rolling window days (schema: window_days)", _, true, 1.0, true, 365.0);
	g_cvIncidentSec = CreateConVar("ma_recidivism_incident_sec", "900", "Incident group window seconds", _, true, 1.0, true, 86400.0);
	g_cvRequireSameAdmin = CreateConVar("ma_recidivism_require_same_admin", "0", "Require same admin aid to join incident", _, true, 0.0, true, 1.0);
	g_cvMultPrimary = CreateConVar("ma_recidivism_mult_primary", "1.0", "Primary track multiplier in incident", _, true, 0.0);
	g_cvMultSecondary = CreateConVar("ma_recidivism_mult_secondary", "0.5", "Other-track multiplier in incident", _, true, 0.0);
	g_cvMultSameTrack = CreateConVar("ma_recidivism_mult_same_track", "0.25", "Same-track extra multiplier", _, true, 0.0);
	g_cvSilenceSplit = CreateConVar("ma_recidivism_silence_split", "0.6", "Silence split mult per gag/mute event", _, true, 0.0);
	g_cvThresholdBan = CreateConVar("ma_recidivism_threshold_ban", "12", "Ban track threshold", _, true, 0.0);
	g_cvThresholdGag = CreateConVar("ma_recidivism_threshold_gag", "12", "Gag track threshold", _, true, 0.0);
	g_cvThresholdMute = CreateConVar("ma_recidivism_threshold_mute", "12", "Mute track threshold", _, true, 0.0);
	g_cvDryRun = CreateConVar("ma_recidivism_dry_run", "1", "1=count only, no auto escalate (schema default)", _, true, 0.0, true, 1.0);
	g_cvRevokeOnUnpunish = CreateConVar("ma_recidivism_revoke_on_unpunish", "1", "Revoke recidivism points when ban/mute/gag is removed (MA + mirrored by site SQL)", _, true, 0.0, true, 1.0);
	g_cvDecay = CreateConVar("ma_recidivism_decay", "1", "Linear decay inside window", _, true, 0.0, true, 1.0);
	g_cvEscalateMode = CreateConVar("ma_recidivism_escalate_mode", "perm", "Ban escalate mode: perm | lock");
	g_cvNotifyAdmins = CreateConVar("ma_recidivism_notify_admins", "1", "Notify admins on score/escalate", _, true, 0.0, true, 1.0);

	g_cvWindowDays.AddChangeHook(OnCvarChanged);
	g_cvIncidentSec.AddChangeHook(OnCvarChanged);
	g_cvRequireSameAdmin.AddChangeHook(OnCvarChanged);
	g_cvMultPrimary.AddChangeHook(OnCvarChanged);
	g_cvMultSecondary.AddChangeHook(OnCvarChanged);
	g_cvMultSameTrack.AddChangeHook(OnCvarChanged);
	g_cvSilenceSplit.AddChangeHook(OnCvarChanged);
	g_cvThresholdBan.AddChangeHook(OnCvarChanged);
	g_cvThresholdGag.AddChangeHook(OnCvarChanged);
	g_cvThresholdMute.AddChangeHook(OnCvarChanged);
	g_cvDryRun.AddChangeHook(OnCvarChanged);
	g_cvDecay.AddChangeHook(OnCvarChanged);
	g_cvEscalateMode.AddChangeHook(OnCvarChanged);

	AutoExecConfig(true, "ma_recidivism");
	ApplyCvarsToRuntime();

	g_hFamilyCache = new StringMap();
	g_hFamilyCacheTime = new StringMap();

	RegAdminCmd("sm_history", Command_History, ADMFLAG_GENERIC, "Show recidivism history / points");
	RegAdminCmd("sm_recidivism", Command_History, ADMFLAG_GENERIC, "Alias of sm_history");
	RegAdminCmd("sm_recidlist", Command_RecidList, ADMFLAG_GENERIC, "Top recidivism scores list");
	RegAdminCmd("sm_recidivism_list", Command_RecidList, ADMFLAG_GENERIC, "Alias of sm_recidlist");
	RegAdminCmd("sm_recidivism_reset", Command_Reset, ADMFLAG_ROOT, "Revoke all recidivism events for target");
	RegAdminCmd("sm_recid_reload", Command_ReloadConfig, ADMFLAG_ROOT, "Reload sb_recid_config into runtime");

	TryBindAdminMenu();

	PrintToServer("[MA Recidivism] %s — DB via MAGetDatabase(), schema sb_recid_* (dry_run=%d)",
		PLUGIN_VERSION, g_bDryRun ? 1 : 0);
}

public void OnPluginEnd()
{
	StopMonitoringTimer();
	delete g_hFamilyCache;
	delete g_hFamilyCacheTime;
	g_hRebanDB = null;
}

public void OnCvarChanged(ConVar convar, const char[] oldValue, const char[] newValue)
{
	if (g_bApplyingCvars)
		return;
	ApplyCvarsToRuntime();
}

void ApplyCvarsToRuntime()
{
	g_iWindowDays = g_cvWindowDays.IntValue;
	g_iIncidentSec = g_cvIncidentSec.IntValue;
	g_bRequireSameAdmin = g_cvRequireSameAdmin.BoolValue;
	g_fMultPrimary = g_cvMultPrimary.FloatValue;
	g_fMultSecondary = g_cvMultSecondary.FloatValue;
	g_fMultSameTrack = g_cvMultSameTrack.FloatValue;
	g_fSilenceSplit = g_cvSilenceSplit.FloatValue;
	g_fThresholdBan = g_cvThresholdBan.FloatValue;
	g_fThresholdGag = g_cvThresholdGag.FloatValue;
	g_fThresholdMute = g_cvThresholdMute.FloatValue;
	g_bDryRun = g_cvDryRun.BoolValue;
	g_bDecay = g_cvDecay.BoolValue;
	g_cvEscalateMode.GetString(g_sEscalateModeBan, sizeof(g_sEscalateModeBan));
}

public void OnMapStart()
{
	LogMessage("[MA Recidivism] Map started, reinitializing MA DB attach...");
	g_bDBReady = false;
	g_bSchemaReady = false;
	g_bSchemaInstalling = false;
	g_iReconnectAttempts = 0;
	g_hDB = null; // do not Close — clones may still be in SQL callbacks; MA owns reconnects
	CreateTimer(2.0, Timer_DelayedInit, _, TIMER_FLAG_NO_MAPCHANGE);
}

public Action Timer_DelayedInit(Handle timer)
{
	CheckDatabaseAvailability();
	return Plugin_Stop;
}

public void OnAllPluginsLoaded()
{
	CheckDatabaseAvailability();
	TryConnectRebanDB();
	// materialadmin adds category "materialadmin" inside the same OnAdminMenuReady
	// forward — order is undefined. Re-bind after everyone finished.
	TryBindAdminMenu();
	CreateTimer(1.0, Timer_BindAdminMenu, _, TIMER_FLAG_NO_MAPCHANGE);
}

void TryConnectRebanDB()
{
	if (g_bRebanTried || g_hRebanDB != null)
		return;
	g_bRebanTried = true;
	Database.Connect(SQL_OnRebanConnected, "rebanner");
}

public void SQL_OnRebanConnected(Database db, const char[] error, any data)
{
	if (db == null)
	{
		LogMessage("[MA Recidivism] rebanner DB unavailable (%s) — family-max disabled", error);
		return;
	}
	g_hRebanDB = db;
	LogMessage("[MA Recidivism] rebanner DB connected (fingerprint families)");
}

public Action Timer_BindAdminMenu(Handle timer)
{
	TryBindAdminMenu();
	return Plugin_Stop;
}

public void OnLibraryAdded(const char[] name)
{
	if (StrEqual(name, "materialadmin"))
	{
		LogMessage("[MA Recidivism] Material Admin library loaded");
		CheckDatabaseAvailability();
		TryBindAdminMenu();
	}
	else if (StrEqual(name, "adminmenu"))
	{
		TryBindAdminMenu();
	}
}

public void OnLibraryRemoved(const char[] name)
{
	if (StrEqual(name, "materialadmin"))
	{
		LogMessage("[MA Recidivism] Material Admin library removed");
		g_bMAAvailable = false;
		g_bDBReady = false;
		g_bSchemaReady = false;
		g_bSchemaInstalling = false;
		g_hDB = null;
		StopMonitoringTimer();
	}
}

public void MAOnConnectDatabase(Database db)
{
	// Same idea as materialadmin_check: don't store MA's forward handle.
	// Just re-run availability → fresh MAGetDatabase() + validate.
	LogMessage("[MA Recidivism] MAOnConnectDatabase (db=%s) — re-acquire via MA API",
		db == null ? "null" : "ok");
	g_bDBReady = false;
	g_bSchemaReady = false;
	g_bSchemaInstalling = false;
	g_hDB = null;
	g_iReconnectAttempts = 0;
	CheckDatabaseAvailability();
}

public void MAOnConfigSetting() { RefreshPrefix(); }

// ---------------------------------------------------------------------------
// DB attach — mirror materialadmin_check.sp (proven on Sibnet)
// - Never SQL_Connect
// - Always MAGetDatabase() for work (CloneHandle from MA)
// - Monitor only checks library + null handle; MA manages reconnects
// - On dead handle / query error → g_bDBReady=false → timer re-fetches API
// ---------------------------------------------------------------------------

/**
 * Fresh handle from Material Admin API (like check's natives).
 * Soft-caches into g_hDB for legacy call sites; do NOT Close/delete it.
 */
Database GetWorkingDB()
{
	if (!LibraryExists("materialadmin"))
	{
		g_bMAAvailable = false;
		return null;
	}

	g_bMAAvailable = true;
	Database db = MAGetDatabase();
	if (db == null)
		return null;

	g_hDB = db;
	return db;
}

void CheckDatabaseAvailability()
{
	g_bMAAvailable = LibraryExists("materialadmin");

	if (!g_bMAAvailable)
	{
		if (g_bDBReady)
			LogMessage("[MA Recidivism] Material Admin library not available");
		else
			LogMessage("[MA Recidivism] Waiting for Material Admin library...");

		g_bDBReady = false;
		StartMonitoringTimer();
		return;
	}

	Database db = MAGetDatabase();
	if (db == null)
	{
		if (g_bDBReady)
			LogMessage("[MA Recidivism] Database handle lost");
		else
			LogMessage("[MA Recidivism] Waiting for MA DB (MAGetDatabase() == null)");

		g_bDBReady = false;
		g_hDB = null;
		StartMonitoringTimer();
		return;
	}

	g_hDB = db;

	// Only validate when not ready (materialadmin_check pattern)
	if (!g_bDBReady)
		ValidateConnection(db);
	else
		StartMonitoringTimer();
}

void ValidateConnection(Database db)
{
	if (db == null)
	{
		g_bDBReady = false;
		return;
	}
	db.Query(Validate_Callback, "SELECT 1");
}

public void Validate_Callback(Database db, DBResultSet results, const char[] error, any data)
{
	if (error[0])
	{
		LogError("[MA Recidivism] Validation failed: %s", error);
		g_bDBReady = false;
		g_bSchemaInstalling = false;

		if (IsConnectionError(error))
			ScheduleReconnect();
		else
			StartMonitoringTimer();
		return;
	}

	if (!g_bDBReady)
	{
		LogMessage("[MA Recidivism] Database ready (MAGetDatabase validated)");
		g_bDBReady = true;
		g_iReconnectAttempts = 0;
		if (db != null)
			g_hDB = db;
		RefreshPrefix();
		EnsureSchema();
	}
	StartMonitoringTimer();
}

void ScheduleReconnect()
{
	if (g_iReconnectAttempts >= MAX_RECONNECT_ATTEMPTS)
	{
		LogError("[MA Recidivism] Max reconnect attempts reached; will retry on map change / MA reconnect. Use will auto-retry via monitor.");
		StartMonitoringTimer();
		return;
	}

	g_iReconnectAttempts++;
	LogMessage("[MA Recidivism] Scheduling reconnect attempt %d/%d (re-fetch MAGetDatabase)",
		g_iReconnectAttempts, MAX_RECONNECT_ATTEMPTS);
	CreateTimer(RECONNECT_DELAY, Timer_Reconnect, _, TIMER_FLAG_NO_MAPCHANGE);
}

public Action Timer_Reconnect(Handle timer)
{
	CheckDatabaseAvailability();
	return Plugin_Stop;
}

void StartMonitoringTimer()
{
	if (g_hDBCheckTimer == null)
		g_hDBCheckTimer = CreateTimer(DB_CHECK_INTERVAL, Timer_Monitor, _, TIMER_REPEAT);
}

void StopMonitoringTimer()
{
	if (g_hDBCheckTimer != null)
	{
		KillTimer(g_hDBCheckTimer);
		g_hDBCheckTimer = null;
	}
}

public Action Timer_Monitor(Handle timer)
{
	float now = GetGameTime();
	if (now - g_flLastCheck < DB_CHECK_INTERVAL - 1.0)
		return Plugin_Continue;
	g_flLastCheck = now;

	// materialadmin_check: only library + handle existence — MA owns reconnects
	bool wasAvailable = g_bMAAvailable;
	g_bMAAvailable = LibraryExists("materialadmin");

	if (!g_bMAAvailable)
	{
		if (wasAvailable)
		{
			LogMessage("[MA Recidivism] Material Admin became unavailable");
			g_bDBReady = false;
			g_hDB = null;
		}
		return Plugin_Continue;
	}

	Database db = MAGetDatabase();
	if (db == null)
	{
		if (g_bDBReady)
		{
			LogMessage("[MA Recidivism] Database handle lost — will re-fetch");
			g_bDBReady = false;
			g_hDB = null;
		}
		return Plugin_Continue;
	}

	g_hDB = db;

	// Handle came back after loss — validate + schema again
	if (!g_bDBReady)
		ValidateConnection(db);

	return Plugin_Continue;
}

void MarkDBFailed(const char[] error)
{
	g_bDBReady = false;
	if (IsConnectionError(error))
		ScheduleReconnect();
}

bool IsConnectionError(const char[] error)
{
	return (StrContains(error, "Lost connection", false) != -1 ||
		StrContains(error, "MySQL server has gone away", false) != -1 ||
		StrContains(error, "Can't connect", false) != -1 ||
		StrContains(error, "Connection refused", false) != -1 ||
		StrContains(error, "Commands out of sync", false) != -1 ||
		StrContains(error, "Server shutdown in progress", false) != -1 ||
		StrContains(error, "Too many connections", false) != -1 ||
		StrContains(error, "Invalid database Handle", false) != -1 ||
		StrContains(error, "Invalid Handle", false) != -1);
}

void RefreshPrefix()
{
	char buf[64];
	if (GetFeatureStatus(FeatureType_Native, "MAGetConfigSetting") == FeatureStatus_Available
		&& MAGetConfigSetting("DatabasePrefix", buf) && buf[0])
	{
		strcopy(g_sPrefix, sizeof(g_sPrefix), buf);
	}
	// Recid tables are always sb_recid_* per DESIGN (site DB_PREFIX=sb)
}

// ---------------------------------------------------------------------------
// Schema bootstrap (matches docs/schema.sql)
// ---------------------------------------------------------------------------

void EnsureSchema()
{
	// Plugin is the sole production schema installer for sb_recid_*.
	if (!g_bDBReady)
	{
		LogMessage("[MA Recidivism] EnsureSchema skipped (MA DB not ready)");
		return;
	}
	if (g_bSchemaInstalling)
		return;

	Database db = GetWorkingDB();
	if (db == null)
	{
		LogMessage("[MA Recidivism] EnsureSchema skipped (MAGetDatabase null)");
		g_bDBReady = false;
		return;
	}

	g_bSchemaReady = false;
	g_bSchemaInstalling = true;
	LogMessage("[MA Recidivism] Ensuring sb_recid_* schema (plugin installer)...");
	RunSchemaStep(db, SCHEMA_STEP_CONFIG);
}

void RunSchemaStep(Database db, int step)
{
	if (db == null)
	{
		db = GetWorkingDB();
		if (db == null)
		{
			g_bSchemaInstalling = false;
			g_bDBReady = false;
			return;
		}
	}

	char query[2048];
	query[0] = '\0';

	switch (step)
	{
		case SCHEMA_STEP_CONFIG:
		{
			strcopy(query, sizeof(query),
				"CREATE TABLE IF NOT EXISTS `sb_recid_config` ( \
					`cfg_key` VARCHAR(64) NOT NULL, \
					`cfg_value` VARCHAR(255) NOT NULL, \
					`updated_at` INT UNSIGNED NOT NULL DEFAULT 0, \
					PRIMARY KEY (`cfg_key`) \
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		case SCHEMA_STEP_INCIDENTS:
		{
			strcopy(query, sizeof(query),
				"CREATE TABLE IF NOT EXISTS `sb_recid_incidents` ( \
					`incident_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, \
					`authid` VARCHAR(64) NOT NULL, \
					`name` VARCHAR(128) NOT NULL DEFAULT '', \
					`category` ENUM('tox','grief','voice','other') NOT NULL DEFAULT 'other', \
					`primary_track` ENUM('ban','gag','mute') NULL DEFAULT NULL, \
					`sid` INT NOT NULL DEFAULT 0, \
					`opened_by` INT NOT NULL DEFAULT 0, \
					`opened_at` INT UNSIGNED NOT NULL, \
					`closed_at` INT UNSIGNED NULL DEFAULT NULL, \
					`note` VARCHAR(255) NOT NULL DEFAULT '', \
					PRIMARY KEY (`incident_id`), \
					KEY `idx_auth_opened` (`authid`, `opened_at`), \
					KEY `idx_opened` (`opened_at`) \
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		case SCHEMA_STEP_EVENTS:
		{
			// FK omitted in auto-create for host compatibility; schema.sql has it for manual migrate
			strcopy(query, sizeof(query),
				"CREATE TABLE IF NOT EXISTS `sb_recid_events` ( \
					`event_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, \
					`incident_id` INT UNSIGNED NOT NULL, \
					`authid` VARCHAR(64) NOT NULL, \
					`track` ENUM('ban','gag','mute') NOT NULL, \
					`points_raw` DECIMAL(8,2) NOT NULL DEFAULT 0, \
					`incident_multiplier` DECIMAL(8,2) NOT NULL DEFAULT 1.00, \
					`source` ENUM('ban','gag','mute','silence','warn','auto_escalate','manual','revoke','legacy_import') NOT NULL DEFAULT 'manual', \
					`ma_table` ENUM('bans','comms','none') NOT NULL DEFAULT 'none', \
					`ma_bid` INT UNSIGNED NULL DEFAULT NULL, \
					`length_seconds` INT NOT NULL DEFAULT 0, \
					`reason` VARCHAR(255) NOT NULL DEFAULT '', \
					`aid` INT NOT NULL DEFAULT 0, \
					`sid` INT NOT NULL DEFAULT 0, \
					`created_at` INT UNSIGNED NOT NULL, \
					`revoked` TINYINT(1) NOT NULL DEFAULT 0, \
					`revoked_by` INT NOT NULL DEFAULT 0, \
					`revoked_at` INT UNSIGNED NULL DEFAULT NULL, \
					`revoke_reason` VARCHAR(255) NOT NULL DEFAULT '', \
					PRIMARY KEY (`event_id`), \
					KEY `idx_auth_track_created` (`authid`, `track`, `created_at`), \
					KEY `idx_incident` (`incident_id`), \
					KEY `idx_ma` (`ma_table`, `ma_bid`), \
					KEY `idx_created` (`created_at`) \
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		case SCHEMA_STEP_SCORES:
		{
			strcopy(query, sizeof(query),
				"CREATE TABLE IF NOT EXISTS `sb_recid_scores` ( \
					`authid` VARCHAR(64) NOT NULL, \
					`track` ENUM('ban','gag','mute') NOT NULL, \
					`score` DECIMAL(10,2) NOT NULL DEFAULT 0, \
					`events_active` INT UNSIGNED NOT NULL DEFAULT 0, \
					`escalated` TINYINT(1) NOT NULL DEFAULT 0, \
					`escalated_at` INT UNSIGNED NULL DEFAULT NULL, \
					`escalated_bid` INT UNSIGNED NULL DEFAULT NULL, \
					`updated_at` INT UNSIGNED NOT NULL DEFAULT 0, \
					PRIMARY KEY (`authid`, `track`), \
					KEY `idx_score` (`track`, `score`) \
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		case SCHEMA_STEP_VIEW:
		{
			strcopy(query, sizeof(query),
				"CREATE OR REPLACE VIEW `sb_recid_events_active` AS \
				SELECT e.* FROM `sb_recid_events` AS e \
				WHERE e.revoked = 0 AND e.created_at >= (UNIX_TIMESTAMP() - 30 * 86400)");
		}
		case SCHEMA_STEP_SEED:
		{
			int now = GetTime();
			FormatEx(query, sizeof(query),
				"INSERT IGNORE INTO `sb_recid_config` (`cfg_key`,`cfg_value`,`updated_at`) VALUES \
				('window_days','30',%d),('threshold_ban','12',%d),('threshold_gag','12',%d),('threshold_mute','12',%d), \
				('incident_group_seconds','900',%d),('incident_require_same_admin','0',%d), \
				('mult_primary','1.0',%d),('mult_secondary_track','0.5',%d),('mult_same_track_extra','0.25',%d), \
				('silence_split_mult','0.6',%d),('escalate_mode_ban','perm',%d),('escalate_min_ban_minutes','10080',%d), \
				('dry_run','1',%d),('decay_enabled','1',%d)",
				now, now, now, now, now, now, now, now, now, now, now, now, now, now);
		}
		case SCHEMA_STEP_LOADCFG:
		{
			strcopy(query, sizeof(query), "SELECT `cfg_key`,`cfg_value` FROM `sb_recid_config`");
			db.Query(SQL_OnLoadConfig, query);
			return;
		}
	}

	if (!query[0])
	{
		g_bSchemaInstalling = false;
		return;
	}

	DataPack pack = new DataPack();
	pack.WriteCell(step);
	// Always use the same CloneHandle from the SQL callback chain — never re-fetch mid-DDL
	db.Query(SQL_OnSchemaStep, query, pack);
}

public void SQL_OnSchemaStep(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int step = pack.ReadCell();
	delete pack;

	if (error[0])
	{
		// VIEW may fail on some hosts — continue with a fresh handle if needed
		if (step == SCHEMA_STEP_VIEW)
		{
			LogError("[MA Recidivism] VIEW warn (ignored): %s", error);
			Database next = (db != null) ? db : GetWorkingDB();
			RunSchemaStep(next, SCHEMA_STEP_SEED);
			return;
		}
		LogError("[MA Recidivism] Schema step %d failed: %s", step, error);
		g_bSchemaReady = false;
		g_bSchemaInstalling = false;
		MarkDBFailed(error);
		return;
	}

	if (db == null)
	{
		LogError("[MA Recidivism] Schema step %d: DB handle null — re-fetch", step);
		db = GetWorkingDB();
		if (db == null)
		{
			g_bSchemaReady = false;
			g_bSchemaInstalling = false;
			g_bDBReady = false;
			return;
		}
	}

	if (step < SCHEMA_STEP_LOADCFG)
		RunSchemaStep(db, step + 1);
}

public void SQL_OnLoadConfig(Database db, DBResultSet results, const char[] error, any data)
{
	g_bSchemaInstalling = false;

	if (error[0] || results == null)
	{
		LogError("[MA Recidivism] Load config failed: %s — using cvar defaults", error);
		g_bSchemaReady = true;
		return;
	}

	while (results.FetchRow())
	{
		char key[64], val[64];
		results.FetchString(0, key, sizeof(key));
		results.FetchString(1, val, sizeof(val));
		ApplyConfigKey(key, val);
	}

	// CVars (cfg / live) override DB values
	ApplyCvarsToRuntime();

	g_bSchemaReady = true;
	PrintToServer("[MA Recidivism] sb_recid_* ready (window=%dd dry_run=%d thr=%.0f/%.0f/%.0f)",
		g_iWindowDays, g_bDryRun ? 1 : 0, g_fThresholdBan, g_fThresholdGag, g_fThresholdMute);
}

void ApplyConfigKey(const char[] key, const char[] val)
{
	if (StrEqual(key, "window_days")) g_iWindowDays = StringToInt(val);
	else if (StrEqual(key, "threshold_ban")) g_fThresholdBan = StringToFloat(val);
	else if (StrEqual(key, "threshold_gag")) g_fThresholdGag = StringToFloat(val);
	else if (StrEqual(key, "threshold_mute")) g_fThresholdMute = StringToFloat(val);
	else if (StrEqual(key, "incident_group_seconds")) g_iIncidentSec = StringToInt(val);
	else if (StrEqual(key, "incident_require_same_admin")) g_bRequireSameAdmin = (StringToInt(val) != 0);
	else if (StrEqual(key, "mult_primary")) g_fMultPrimary = StringToFloat(val);
	else if (StrEqual(key, "mult_secondary_track")) g_fMultSecondary = StringToFloat(val);
	else if (StrEqual(key, "mult_same_track_extra")) g_fMultSameTrack = StringToFloat(val);
	else if (StrEqual(key, "silence_split_mult")) g_fSilenceSplit = StringToFloat(val);
	else if (StrEqual(key, "escalate_mode_ban")) strcopy(g_sEscalateModeBan, sizeof(g_sEscalateModeBan), val);
	else if (StrEqual(key, "escalate_min_ban_minutes")) g_iEscalateMinBanMin = StringToInt(val);
	else if (StrEqual(key, "dry_run")) g_bDryRun = (StringToInt(val) != 0);
	else if (StrEqual(key, "decay_enabled")) g_bDecay = (StringToInt(val) != 0);
}

public void SQL_EmptyCallback(Database db, DBResultSet results, const char[] error, any data)
{
	if (error[0])
	{
		LogError("[MA Recidivism] SQL: %s", error);
		MarkDBFailed(error);
	}
}

// ---------------------------------------------------------------------------
// MA forwards
// ---------------------------------------------------------------------------

public void MAOnClientBanned(int iClient, int iTarget, char[] sIp, char[] sSteamID, char[] sName, int iTime, char[] sReason)
{
	if (!g_cvEnable.BoolValue || g_bInternalAction)
		return;
	if (StrContains(sReason, REASON_AUTO, false) != -1)
		return;
	BeginAward(iClient, iTarget, sSteamID, sName, TRACK_BAN, "ban", "ban", "bans", iTime, sReason, false);
}

public void MAOnClientAddBanned(int iClient, char[] sIp, char[] sSteamID, int iTime, char[] sReason)
{
	if (!g_cvEnable.BoolValue || g_bInternalAction)
		return;
	if (StrContains(sReason, REASON_AUTO, false) != -1)
		return;
	BeginAward(iClient, 0, sSteamID, sSteamID, TRACK_BAN, "ban", "ban", "bans", iTime, sReason, false);
}

public void MAOnClientMuted(int iClient, int iTarget, char[] sIp, char[] sSteamID, char[] sName, int iType, int iTime, char[] sReason)
{
	if (!g_cvEnable.BoolValue || g_bInternalAction)
		return;
	if (StrContains(sReason, REASON_AUTO, false) != -1)
		return;

	if (iType == MA_MUTE_VOICE)
		BeginAward(iClient, iTarget, sSteamID, sName, TRACK_MUTE, "mute", "mute", "comms", iTime, sReason, false);
	else if (iType == MA_MUTE_CHAT)
		BeginAward(iClient, iTarget, sSteamID, sName, TRACK_GAG, "gag", "gag", "comms", iTime, sReason, false);
	else if (iType == MA_MUTE_BOTH)
	{
		// One MA silence → two events with silence_split_mult (DESIGN §4.2)
		BeginAward(iClient, iTarget, sSteamID, sName, TRACK_GAG, "gag", "silence", "comms", iTime, sReason, true);
		BeginAward(iClient, iTarget, sSteamID, sName, TRACK_MUTE, "mute", "silence", "comms", iTime, sReason, true);
	}
}

public void MAOnClientUnBanned(int iClient, char[] sIp, char[] sSteamID, char[] sReason)
{
	if (!g_cvEnable.BoolValue || !g_cvRevokeOnUnpunish.BoolValue || g_bInternalAction)
		return;
	if (sSteamID[0] == '\0')
		return;

	RevokeLatestEvent(sSteamID, "ban", "ma_unban", iClient);
}

public void MAOnClientUnMuted(int iClient, int iTarget, char[] sIp, char[] sSteamID, char[] sName, int iType, char[] sReason)
{
	if (!g_cvEnable.BoolValue || !g_cvRevokeOnUnpunish.BoolValue || g_bInternalAction)
		return;
	if (sSteamID[0] == '\0')
		return;

	if (iType == MA_MUTE_VOICE)
		RevokeLatestEvent(sSteamID, "mute", "ma_unmute", iClient);
	else if (iType == MA_MUTE_CHAT)
		RevokeLatestEvent(sSteamID, "gag", "ma_ungag", iClient);
	else if (iType == MA_MUTE_BOTH)
	{
		RevokeLatestEvent(sSteamID, "mute", "ma_unsilence", iClient);
		RevokeLatestEvent(sSteamID, "gag", "ma_unsilence", iClient);
	}
}

// ---------------------------------------------------------------------------
// Revoke on unpunish (latest unreoked event per track; then recompute score)
// ---------------------------------------------------------------------------

void RevokeLatestEvent(const char[] steamId, const char[] trackStr, const char[] reason, int adminClient)
{
	if (!g_bSchemaReady || !g_bDBReady || GetWorkingDB() == null)
	{
		g_bDBReady = false;
		return;
	}
	if (steamId[0] == '\0' || StrEqual(steamId, "BOT"))
		return;

	char escAuth[64], escTrack[16], escReason[128];
	g_hDB.Escape(steamId, escAuth, sizeof(escAuth));
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));
	g_hDB.Escape(reason, escReason, sizeof(escReason));

	int aid = 0;
	// aid reserved — keep 0 unless we resolve sb_admins later

	DataPack pack = new DataPack();
	pack.WriteString(steamId);
	pack.WriteString(trackStr);
	pack.WriteCell((adminClient > 0) ? GetClientUserId(adminClient) : 0);

	char query[768];
	FormatEx(query, sizeof(query),
		"UPDATE `sb_recid_events` SET \
			`revoked`=1, `revoked_by`=%d, `revoked_at`=%d, `revoke_reason`='%s' \
		WHERE `event_id` = ( \
			SELECT `eid` FROM ( \
				SELECT `event_id` AS `eid` FROM `sb_recid_events` \
				WHERE `authid`='%s' AND `track`='%s' AND `revoked`=0 \
				ORDER BY `created_at` DESC, `event_id` DESC LIMIT 1 \
			) AS `_recid_pick` \
		)",
		aid, GetTime(), escReason, escAuth, escTrack);

	g_hDB.Query(SQL_OnRevokeLatest, query, pack);
}

public void SQL_OnRevokeLatest(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));
	int adminUserId = pack.ReadCell();
	delete pack;

	if (error[0])
	{
		LogError("[MA Recidivism] Revoke failed (%s/%s): %s", steamId, trackStr, error);
		MarkDBFailed(error);
		return;
	}

	NotifyAdmins("\x04[Recidivism]\x01 Сняты очки [%s] с %s (снятие наказания)", trackStr, steamId);
	RecomputeAndMaybeEscalate(steamId, trackStr, 0);

	if (adminUserId)
	{
		int admin = GetClientOfUserId(adminUserId);
		if (admin > 0)
			PrintToChat(admin, "\x04[Recidivism]\x01 Очки ветки \x03%s\x01 сняты для %s", trackStr, steamId);
	}
}

// ---------------------------------------------------------------------------
// Award pipeline (async)
// ---------------------------------------------------------------------------

void BeginAward(int adminClient, int target, const char[] steamId, const char[] name, int track,
	const char[] trackStr, const char[] source, const char[] maTable, int durationMin, const char[] reason, bool fromSilence)
{
	if (!g_bSchemaReady || !g_bDBReady)
	{
		LogError("[MA Recidivism] Schema/DB not ready; skip award %s", steamId);
		return;
	}
	Database db = GetWorkingDB();
	if (db == null)
	{
		g_bDBReady = false;
		LogError("[MA Recidivism] MAGetDatabase null; skip award %s", steamId);
		return;
	}
	if (steamId[0] == '\0' || StrEqual(steamId, "BOT") || StrContains(steamId, "STEAM_ID_LAN") != -1)
		return;

	float pointsRaw = PointsRawFor(track, durationMin);
	int lengthSec = (durationMin <= 0) ? 0 : durationMin * 60;
	int userid = (target > 0 && IsClientInGame(target)) ? GetClientUserId(target) : 0;
	int aid = 0; // TODO: map admin steam → sb_admins.aid

	char adminSteam[64];
	adminSteam[0] = '\0';
	if (adminClient > 0 && IsClientInGame(adminClient) && !IsFakeClient(adminClient))
		GetClientAuthId(adminClient, AuthId_Steam2, adminSteam, sizeof(adminSteam));

	char category[16] = "other";
	if (track == TRACK_MUTE) strcopy(category, sizeof(category), "voice");

	DataPack pack = new DataPack();
	pack.WriteCell(track);
	pack.WriteCell(userid);
	pack.WriteCell(aid);
	pack.WriteCell(lengthSec);
	pack.WriteCell(fromSilence ? 1 : 0);
	pack.WriteFloat(pointsRaw);
	pack.WriteString(steamId);
	pack.WriteString(name);
	pack.WriteString(trackStr);
	pack.WriteString(source);
	pack.WriteString(maTable);
	pack.WriteString(reason);
	pack.WriteString(category);
	pack.WriteString(adminSteam); // reserved for aid lookup / audit

	char esc[64];
	db.Escape(steamId, esc, sizeof(esc));
	int cutoff = GetTime() - g_iIncidentSec;

	char query[512];
	if (g_bRequireSameAdmin && aid > 0)
	{
		FormatEx(query, sizeof(query),
			"SELECT i.`incident_id`, i.`primary_track`, i.`opened_by` FROM `sb_recid_incidents` i \
			WHERE i.`authid`='%s' AND i.`opened_at`>=%d AND i.`opened_by`=%d \
			AND (i.`closed_at` IS NULL OR i.`closed_at`>=%d) \
			ORDER BY i.`opened_at` DESC LIMIT 1",
			esc, cutoff, aid, GetTime());
	}
	else
	{
		FormatEx(query, sizeof(query),
			"SELECT i.`incident_id`, i.`primary_track`, i.`opened_by` FROM `sb_recid_incidents` i \
			WHERE i.`authid`='%s' AND i.`opened_at`>=%d \
			AND (i.`closed_at` IS NULL OR i.`closed_at`>=%d) \
			ORDER BY i.`opened_at` DESC LIMIT 1",
			esc, cutoff, GetTime());
	}

	db.Query(SQL_OnFindIncident, query, pack);
}

public void SQL_OnFindIncident(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	if (error[0])
	{
		LogError("[MA Recidivism] Find incident: %s", error);
		MarkDBFailed(error);
		delete pack;
		return;
	}

	int incidentId = 0;
	char primaryTrack[8];
	primaryTrack[0] = '\0';

	if (results != null && results.FetchRow())
	{
		incidentId = results.FetchInt(0);
		results.FetchString(1, primaryTrack, sizeof(primaryTrack));
	}

	pack.Reset();
	pack.ReadCell(); // track
	pack.ReadCell(); // userid
	int aid = pack.ReadCell();
	pack.ReadCell(); // length
	pack.ReadCell(); // silence
	pack.ReadFloat();
	char steamId[64], name[128], trackStr[8], source[24], maTable[16], reason[256], category[16];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(name, sizeof(name));
	pack.ReadString(trackStr, sizeof(trackStr));
	pack.ReadString(source, sizeof(source));
	pack.ReadString(maTable, sizeof(maTable));
	pack.ReadString(reason, sizeof(reason));
	pack.ReadString(category, sizeof(category));
	char adminSteam[64];
	pack.ReadString(adminSteam, sizeof(adminSteam));

	DataPack pack2 = CloneAwardPack(pack, incidentId, primaryTrack[0] ? primaryTrack : trackStr);
	delete pack;

	if (incidentId > 0)
	{
		QueryIncidentTracks(incidentId, pack2);
		return;
	}

	char escAuth[64], escName[256], escCat[32], escTrack[16];
	db.Escape(steamId, escAuth, sizeof(escAuth));
	db.Escape(name, escName, sizeof(escName));
	db.Escape(category, escCat, sizeof(escCat));
	db.Escape(trackStr, escTrack, sizeof(escTrack));

	char query[768];
	FormatEx(query, sizeof(query),
		"INSERT INTO `sb_recid_incidents` \
		(`authid`,`name`,`category`,`primary_track`,`sid`,`opened_by`,`opened_at`,`note`) \
		VALUES ('%s','%s','%s','%s',0,%d,%d,'')",
		escAuth, escName, escCat, escTrack, aid, GetTime());

	db.Query(SQL_OnCreateIncident, query, pack2);
}

DataPack CloneAwardPack(DataPack src, int incidentId, const char[] primaryTrack)
{
	src.Reset();
	int track = src.ReadCell();
	int userid = src.ReadCell();
	int aid = src.ReadCell();
	int lengthSec = src.ReadCell();
	int fromSilence = src.ReadCell();
	float pointsRaw = src.ReadFloat();
	char steamId[64], name[128], trackStr[8], source[24], maTable[16], reason[256], category[16], adminSteam[64];
	src.ReadString(steamId, sizeof(steamId));
	src.ReadString(name, sizeof(name));
	src.ReadString(trackStr, sizeof(trackStr));
	src.ReadString(source, sizeof(source));
	src.ReadString(maTable, sizeof(maTable));
	src.ReadString(reason, sizeof(reason));
	src.ReadString(category, sizeof(category));
	src.ReadString(adminSteam, sizeof(adminSteam));

	DataPack dst = new DataPack();
	dst.WriteCell(incidentId);
	dst.WriteString(primaryTrack);
	dst.WriteCell(track);
	dst.WriteCell(userid);
	dst.WriteCell(aid);
	dst.WriteCell(lengthSec);
	dst.WriteCell(fromSilence);
	dst.WriteFloat(pointsRaw);
	dst.WriteString(steamId);
	dst.WriteString(name);
	dst.WriteString(trackStr);
	dst.WriteString(source);
	dst.WriteString(maTable);
	dst.WriteString(reason);
	dst.WriteString(category);
	dst.WriteString(adminSteam);
	return dst;
}

public void SQL_OnCreateIncident(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	if (error[0])
	{
		LogError("[MA Recidivism] Create incident: %s", error);
		delete pack;
		return;
	}

	int incidentId = 0;
	if (results != null)
		incidentId = results.InsertId;

	if (incidentId <= 0)
	{
		LogError("[MA Recidivism] Create incident: no insert id");
		delete pack;
		return;
	}

	// Update pack incident id
	pack.Reset();
	pack.ReadCell(); // old 0
	char primaryTrack[8];
	pack.ReadString(primaryTrack, sizeof(primaryTrack));
	DataPack pack2 = CloneAwardPackFromOffset(pack, incidentId, primaryTrack);
	delete pack;

	QueryIncidentTracks(incidentId, pack2);
}

DataPack CloneAwardPackFromOffset(DataPack src, int incidentId, const char[] primaryTrack)
{
	int track = src.ReadCell();
	int userid = src.ReadCell();
	int aid = src.ReadCell();
	int lengthSec = src.ReadCell();
	int fromSilence = src.ReadCell();
	float pointsRaw = src.ReadFloat();
	char steamId[64], name[128], trackStr[8], source[24], maTable[16], reason[256], category[16], adminSteam[64];
	src.ReadString(steamId, sizeof(steamId));
	src.ReadString(name, sizeof(name));
	src.ReadString(trackStr, sizeof(trackStr));
	src.ReadString(source, sizeof(source));
	src.ReadString(maTable, sizeof(maTable));
	src.ReadString(reason, sizeof(reason));
	src.ReadString(category, sizeof(category));
	src.ReadString(adminSteam, sizeof(adminSteam));

	DataPack dst = new DataPack();
	dst.WriteCell(incidentId);
	dst.WriteString(primaryTrack[0] ? primaryTrack : trackStr);
	dst.WriteCell(track);
	dst.WriteCell(userid);
	dst.WriteCell(aid);
	dst.WriteCell(lengthSec);
	dst.WriteCell(fromSilence);
	dst.WriteFloat(pointsRaw);
	dst.WriteString(steamId);
	dst.WriteString(name);
	dst.WriteString(trackStr);
	dst.WriteString(source);
	dst.WriteString(maTable);
	dst.WriteString(reason);
	dst.WriteString(category);
	dst.WriteString(adminSteam);
	return dst;
}

void QueryIncidentTracks(int incidentId, DataPack pack)
{
	Database db = GetWorkingDB();
	if (db == null)
	{
		g_bDBReady = false;
		delete pack;
		return;
	}
	char query[256];
	FormatEx(query, sizeof(query),
		"SELECT `track` FROM `sb_recid_events` WHERE `incident_id`=%d AND `revoked`=0",
		incidentId);
	db.Query(SQL_OnIncidentTracks, query, pack);
}

public void SQL_OnIncidentTracks(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	if (error[0])
	{
		LogError("[MA Recidivism] Incident tracks: %s", error);
		MarkDBFailed(error);
		delete pack;
		return;
	}

	bool hasBan, hasGag, hasMute;
	int countBan, countGag, countMute;
	if (results != null)
	{
		while (results.FetchRow())
		{
			char t[8];
			results.FetchString(0, t, sizeof(t));
			if (StrEqual(t, "ban")) { hasBan = true; countBan++; }
			else if (StrEqual(t, "gag")) { hasGag = true; countGag++; }
			else if (StrEqual(t, "mute")) { hasMute = true; countMute++; }
		}
	}

	pack.Reset();
	int incidentId = pack.ReadCell();
	char primaryTrack[8];
	pack.ReadString(primaryTrack, sizeof(primaryTrack));
	int track = pack.ReadCell();
	int userid = pack.ReadCell();
	int aid = pack.ReadCell();
	int lengthSec = pack.ReadCell();
	int fromSilence = pack.ReadCell();
	float pointsRaw = pack.ReadFloat();
	char steamId[64], name[128], trackStr[8], source[24], maTable[16], reason[256], category[16];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(name, sizeof(name));
	pack.ReadString(trackStr, sizeof(trackStr));
	pack.ReadString(source, sizeof(source));
	pack.ReadString(maTable, sizeof(maTable));
	pack.ReadString(reason, sizeof(reason));
	pack.ReadString(category, sizeof(category));
	char adminSteam[64];
	pack.ReadString(adminSteam, sizeof(adminSteam));
	delete pack;

	int sameCount = 0;
	bool otherExists = false;
	if (track == TRACK_BAN) { sameCount = countBan; otherExists = (hasGag || hasMute); }
	else if (track == TRACK_GAG) { sameCount = countGag; otherExists = (hasBan || hasMute); }
	else { sameCount = countMute; otherExists = (hasBan || hasGag); }

	float mult = g_fMultPrimary;
	if (fromSilence)
		mult = g_fSilenceSplit;
	else if (sameCount > 0)
		mult = g_fMultSameTrack;
	else if (otherExists || (primaryTrack[0] && !StrEqual(primaryTrack, trackStr)))
		mult = g_fMultSecondary;

	LogMessage("[MA Recidivism] award admin=%s target=%s track=%s raw=%.1f mult=%.2f",
		adminSteam[0] ? adminSteam : "CONSOLE", steamId, trackStr, pointsRaw, mult);

	// Permanent input: points_raw already 0 — still write ledger + mark escalated on scores
	InsertEvent(incidentId, steamId, trackStr, pointsRaw, mult, source, maTable, lengthSec, reason, aid, userid, name);
}

void InsertEvent(int incidentId, const char[] steamId, const char[] trackStr, float pointsRaw, float mult,
	const char[] source, const char[] maTable, int lengthSec, const char[] reason, int aid, int userid, const char[] name)
{
	char escAuth[64], escTrack[16], escSrc[24], escTable[16], escReason[512], escName[256];
	g_hDB.Escape(steamId, escAuth, sizeof(escAuth));
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));
	g_hDB.Escape(source, escSrc, sizeof(escSrc));
	g_hDB.Escape(maTable, escTable, sizeof(escTable));
	g_hDB.Escape(reason, escReason, sizeof(escReason));
	g_hDB.Escape(name, escName, sizeof(escName));

	DataPack pack = new DataPack();
	pack.WriteCell(userid);
	pack.WriteString(steamId);
	pack.WriteString(trackStr);
	pack.WriteString(escName);
	pack.WriteFloat(pointsRaw);
	pack.WriteFloat(mult);

	char query[1024];
	FormatEx(query, sizeof(query),
		"INSERT INTO `sb_recid_events` \
		(`incident_id`,`authid`,`track`,`points_raw`,`incident_multiplier`,`source`,`ma_table`,`ma_bid`, \
		 `length_seconds`,`reason`,`aid`,`sid`,`created_at`,`revoked`) \
		VALUES (%d,'%s','%s',%.2f,%.2f,'%s','%s',NULL,%d,'%s',%d,0,%d,0)",
		incidentId, escAuth, escTrack, pointsRaw, mult, escSrc, escTable, lengthSec, escReason, aid, GetTime());

	g_hDB.Query(SQL_OnInsertEvent, query, pack);
}

public void SQL_OnInsertEvent(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	char steamId[64], trackStr[8], name[128];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));
	pack.ReadString(name, sizeof(name));
	float pointsRaw = pack.ReadFloat();
	float mult = pack.ReadFloat();
	delete pack;

	if (error[0])
	{
		LogError("[MA Recidivism] Insert event: %s", error);
		return;
	}

	float awarded = pointsRaw * mult;
	LogMessage("[MA Recidivism] +%.1f [%s] %s (raw=%.1f×%.2f)%s",
		awarded, trackStr, name[0] ? name : steamId, pointsRaw, mult, g_bDryRun ? " dry" : "");

	RecomputeAndMaybeEscalate(steamId, trackStr, userid);
}

// DESIGN §5 buckets (minutes from forward; length_seconds = minutes*60 in DB)
float PointsRawFor(int track, int durationMin)
{
	if (durationMin <= 0)
		return 0.0; // permanent → 0 + escalate flag later

	if (track == TRACK_BAN)
	{
		if (durationMin <= 1440) return 3.0;      // ≤ 1 day
		if (durationMin <= 10080) return 4.0;     // 2–7 days
		return 5.0;                               // > 7 days temp
	}

	// mute / gag
	if (durationMin <= 60) return 2.0;
	return 3.0;
}

void RecomputeAndMaybeEscalate(const char[] steamId, const char[] trackStr, int userid)
{
	if (GetWorkingDB() == null)
	{
		g_bDBReady = false;
		return;
	}
	char esc[64];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	int cutoff = GetTime() - (g_iWindowDays * 86400);

	DataPack pack = new DataPack();
	pack.WriteCell(userid);
	pack.WriteString(steamId);
	pack.WriteString(trackStr);

	char query[512];
	FormatEx(query, sizeof(query),
		"SELECT `points_raw`,`incident_multiplier`,`created_at`,`length_seconds` FROM `sb_recid_events` \
		WHERE `authid`='%s' AND `track`='%s' AND `revoked`=0 AND `created_at`>=%d",
		esc, trackStr, cutoff);

	g_hDB.Query(SQL_OnRecompute, query, pack);
}

public void SQL_OnRecompute(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));
	delete pack;

	if (error[0] || results == null)
	{
		if (error[0]) LogError("[MA Recidivism] Recompute: %s", error);
		return;
	}

	float score = 0.0;
	int events = 0;
	bool sawPerm = false;
	int now = GetTime();
	float windowSec = float(g_iWindowDays * 86400);

	while (results.FetchRow())
	{
		float raw = results.FetchFloat(0);
		float imult = results.FetchFloat(1);
		int created = results.FetchInt(2);
		int lengthSec = results.FetchInt(3);
		if (lengthSec == 0)
			sawPerm = true;

		float weight = 1.0;
		if (g_bDecay && windowSec > 0.0)
		{
			weight = 1.0 - (float(now - created) / windowSec);
			if (weight < 0.0) weight = 0.0;
		}
		score += raw * imult * weight;
		events++;
	}

	UpsertScore(steamId, trackStr, score, events, sawPerm, userid);
}

void UpsertScore(const char[] steamId, const char[] trackStr, float score, int events, bool markEscalatedCeiling, int userid)
{
	if (GetWorkingDB() == null)
	{
		g_bDBReady = false;
		return;
	}
	char esc[64], escTrack[16];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));

	DataPack pack = new DataPack();
	pack.WriteCell(userid);
	pack.WriteCell(markEscalatedCeiling ? 1 : 0);
	pack.WriteFloat(score);
	pack.WriteString(steamId);
	pack.WriteString(trackStr);

	// Preserve escalated if already set; optionally set when permanent input
	char query[768];
	if (markEscalatedCeiling)
	{
		FormatEx(query, sizeof(query),
			"INSERT INTO `sb_recid_scores` \
			(`authid`,`track`,`score`,`events_active`,`escalated`,`escalated_at`,`updated_at`) \
			VALUES ('%s','%s',%.2f,%d,1,%d,%d) \
			ON DUPLICATE KEY UPDATE \
			`score`=VALUES(`score`), `events_active`=VALUES(`events_active`), \
			`escalated`=1, \
			`escalated_at`=IF(`escalated_at` IS NULL, VALUES(`escalated_at`), `escalated_at`), \
			`updated_at`=VALUES(`updated_at`)",
			esc, escTrack, score, events, GetTime(), GetTime());
	}
	else
	{
		FormatEx(query, sizeof(query),
			"INSERT INTO `sb_recid_scores` \
			(`authid`,`track`,`score`,`events_active`,`escalated`,`updated_at`) \
			VALUES ('%s','%s',%.2f,%d,0,%d) \
			ON DUPLICATE KEY UPDATE \
			`score`=VALUES(`score`), `events_active`=VALUES(`events_active`), \
			`escalated`=IF(VALUES(`events_active`)=0, 0, `escalated`), \
			`escalated_at`=IF(VALUES(`events_active`)=0, NULL, `escalated_at`), \
			`updated_at`=VALUES(`updated_at`)",
			esc, escTrack, score, events, GetTime());
	}

	g_hDB.Query(SQL_OnUpsertScore, query, pack);
}

public void SQL_OnUpsertScore(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	int markedPerm = pack.ReadCell();
	float score = pack.ReadFloat();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));
	delete pack;

	if (error[0])
	{
		LogError("[MA Recidivism] Upsert score: %s", error);
		return;
	}

	// Chat: full Ban/Gag/Mute check for all admins (nick + scores + family max)
	AnnounceRecidivismCheck(steamId, userid, 0, false);

	if (markedPerm)
		return; // already at ceiling via permanent punish

	// Escalate uses family-max (not sum) across fingerprint-linked alts
	BeginEscalateWithFamilyMax(steamId, trackStr, score, userid);
}

void BeginEscalateWithFamilyMax(const char[] steamId, const char[] trackStr, float ownScore, int userid)
{
	float thr = ThresholdForTrack(trackStr);
	DataPack pack = new DataPack();
	pack.WriteCell(userid);
	pack.WriteFloat(ownScore);
	pack.WriteFloat(thr);
	pack.WriteString(steamId);
	pack.WriteString(trackStr);

	char familyCsv[1024];
	if (GetCachedFamilyCsv(steamId, familyCsv, sizeof(familyCsv)))
	{
		QueryFamilyMaxScore(trackStr, familyCsv, pack);
		return;
	}

	if (g_hRebanDB == null)
	{
		// No rebanner — escalate on own score only
		ContinueEscalateCheck(pack, ownScore);
		return;
	}

	char esc[64];
	g_hRebanDB.Escape(steamId, esc, sizeof(esc));
	char esc1[64];
	if (strlen(steamId) > 7)
	{
		FormatEx(esc1, sizeof(esc1), "STEAM_1%s", steamId[7]);
		g_hRebanDB.Escape(esc1, esc1, sizeof(esc1));
	}
	else
		strcopy(esc1, sizeof(esc1), esc);

	char query[512];
	FormatEx(query, sizeof(query),
		"SELECT `steamid2` FROM `rebanner_fingerprints` \
		WHERE FIND_IN_SET('%s', REPLACE(`steamid2`, ';', ',')) > 0 \
		   OR FIND_IN_SET('%s', REPLACE(`steamid2`, ';', ',')) > 0 \
		LIMIT 1",
		esc, esc1);
	g_hRebanDB.Query(SQL_OnFamilyForEscalate, query, pack);
}

public void SQL_OnFamilyForEscalate(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	float ownScore = pack.ReadFloat();
	float thr = pack.ReadFloat();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));

	char familyCsv[1024];
	strcopy(familyCsv, sizeof(familyCsv), steamId);

	if (error[0])
		LogError("[MA Recidivism] Family lookup: %s", error);
	else if (results != null && results.FetchRow())
	{
		char raw[1024];
		results.FetchString(0, raw, sizeof(raw));
		NormalizeFamilyCsv(steamId, raw, familyCsv, sizeof(familyCsv));
		CacheFamilyCsv(steamId, familyCsv);
	}

	// Rebuild pack for next step (was consumed)
	DataPack pack2 = new DataPack();
	pack2.WriteCell(userid);
	pack2.WriteFloat(ownScore);
	pack2.WriteFloat(thr);
	pack2.WriteString(steamId);
	pack2.WriteString(trackStr);
	delete pack;

	QueryFamilyMaxScore(trackStr, familyCsv, pack2);
}

void QueryFamilyMaxScore(const char[] trackStr, const char[] familyCsv, DataPack pack)
{
	if (GetWorkingDB() == null)
	{
		ContinueEscalateCheck(pack, 0.0);
		return;
	}

	char inList[1536];
	BuildSqlInListFromCsv(familyCsv, inList, sizeof(inList));
	if (!inList[0])
	{
		pack.Reset();
		pack.ReadCell();
		float ownScore = pack.ReadFloat();
		ContinueEscalateCheck(pack, ownScore);
		return;
	}

	char escTrack[16];
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));
	char query[2048];
	FormatEx(query, sizeof(query),
		"SELECT MAX(`score`) FROM `sb_recid_scores` WHERE `track`='%s' AND `authid` IN (%s)",
		escTrack, inList);
	g_hDB.Query(SQL_OnFamilyMaxScore, query, pack);
}

public void SQL_OnFamilyMaxScore(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	float ownScore = pack.ReadFloat();
	float thr = pack.ReadFloat();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));
	delete pack;

	float familyMax = ownScore;
	if (!error[0] && results != null && results.FetchRow())
	{
		float mx = results.FetchFloat(0);
		if (mx > familyMax)
			familyMax = mx;
	}

	if (familyMax < thr)
		return;

	DataPack pack2 = new DataPack();
	pack2.WriteCell(userid);
	pack2.WriteFloat(familyMax);
	pack2.WriteString(steamId);
	pack2.WriteString(trackStr);

	if (GetWorkingDB() == null)
	{
		delete pack2;
		return;
	}
	char esc[64], escTrack[16];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));
	char query[256];
	FormatEx(query, sizeof(query),
		"SELECT `escalated` FROM `sb_recid_scores` WHERE `authid`='%s' AND `track`='%s' LIMIT 1",
		esc, escTrack);
	g_hDB.Query(SQL_OnCheckEscalated, query, pack2);
}

void ContinueEscalateCheck(DataPack pack, float scoreForThreshold)
{
	pack.Reset();
	int userid = pack.ReadCell();
	float ownScore = pack.ReadFloat();
	float thr = pack.ReadFloat();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));

	float use = scoreForThreshold > 0.0 ? scoreForThreshold : ownScore;
	if (use < thr)
	{
		delete pack;
		return;
	}

	DataPack pack2 = new DataPack();
	pack2.WriteCell(userid);
	pack2.WriteFloat(use);
	pack2.WriteString(steamId);
	pack2.WriteString(trackStr);
	delete pack;

	if (GetWorkingDB() == null)
	{
		delete pack2;
		return;
	}
	char esc[64], escTrack[16];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));
	char query[256];
	FormatEx(query, sizeof(query),
		"SELECT `escalated` FROM `sb_recid_scores` WHERE `authid`='%s' AND `track`='%s' LIMIT 1",
		esc, escTrack);
	g_hDB.Query(SQL_OnCheckEscalated, query, pack2);
}

public void SQL_OnCheckEscalated(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	float score = pack.ReadFloat();
	char steamId[64], trackStr[8];
	pack.ReadString(steamId, sizeof(steamId));
	pack.ReadString(trackStr, sizeof(trackStr));
	delete pack;

	if (error[0])
	{
		LogError("[MA Recidivism] Check escalated: %s", error);
		return;
	}

	bool escalated = false;
	if (results != null && results.FetchRow())
		escalated = (results.FetchInt(0) != 0);

	if (escalated)
		return;

	if (g_bDryRun)
	{
		NotifyAdmins("\x04[Recidivism]\x01 \x07DRY-RUN\x01 порог [%s] для %s (%.1f) — автодействие не выдано",
			trackStr, steamId, score);
		return;
	}

	Escalate(trackStr, steamId, userid, score);
}

float ThresholdForTrack(const char[] trackStr)
{
	if (StrEqual(trackStr, "ban")) return g_fThresholdBan;
	if (StrEqual(trackStr, "gag")) return g_fThresholdGag;
	return g_fThresholdMute;
}

int TrackFromStr(const char[] trackStr)
{
	if (StrEqual(trackStr, "ban")) return TRACK_BAN;
	if (StrEqual(trackStr, "gag")) return TRACK_GAG;
	return TRACK_MUTE;
}

void Escalate(const char[] trackStr, const char[] steamId, int userid, float total)
{
	int track = TrackFromStr(trackStr);
	int target = userid ? GetClientOfUserId(userid) : 0;

	char reason[192];
	FormatEx(reason, sizeof(reason), "%s автоэскалация %s (%.1f / %dд)",
		REASON_AUTO, trackStr, total, g_iWindowDays);

	char steamBuf[64], nameBuf[64], emptyIp[8];
	strcopy(steamBuf, sizeof(steamBuf), steamId);
	strcopy(nameBuf, sizeof(nameBuf), steamId);
	emptyIp[0] = '\0';

	bool ok = false;
	g_bInternalAction = true;

	if (track == TRACK_BAN)
	{
		bool useLock = StrEqual(g_sEscalateModeBan, "lock", false);
		int banMin = useLock ? g_iEscalateMinBanMin : 0;
		if (target > 0 && IsClientInGame(target))
			ok = MABanPlayer(0, target, MA_BAN_STEAM, banMin, reason);
		else if (GetFeatureStatus(FeatureType_Native, "MAOffBanPlayer") == FeatureStatus_Available)
			ok = MAOffBanPlayer(0, MA_BAN_STEAM, steamBuf, emptyIp, nameBuf, banMin, reason);
	}
	else if (track == TRACK_GAG)
	{
		if (target > 0 && IsClientInGame(target))
			ok = MASetClientMuteType(0, target, reason, MA_GAG, 0);
		else if (GetFeatureStatus(FeatureType_Native, "MAOffSetClientMuteType") == FeatureStatus_Available)
			ok = MAOffSetClientMuteType(0, steamBuf, emptyIp, nameBuf, reason, MA_GAG, 0);
	}
	else
	{
		if (target > 0 && IsClientInGame(target))
			ok = MASetClientMuteType(0, target, reason, MA_MUTE, 0);
		else if (GetFeatureStatus(FeatureType_Native, "MAOffSetClientMuteType") == FeatureStatus_Available)
			ok = MAOffSetClientMuteType(0, steamBuf, emptyIp, nameBuf, reason, MA_MUTE, 0);
	}

	g_bInternalAction = false;

	if (ok)
	{
		NotifyAdmins("\x04[Recidivism]\x01 Эскалация \x03%s\x01 → %s (%.1f)", steamId, trackStr, total);
		MarkEscalated(steamId, trackStr, reason);
		if (GetFeatureStatus(FeatureType_Native, "MALog") == FeatureStatus_Available)
			MALog(MA_LogAction, "Recidivism escalate %s track=%s score=%.1f", steamId, trackStr, total);
	}
	else
	{
		LogError("[MA Recidivism] Escalate FAILED %s [%s] %.1f", steamId, trackStr, total);
		NotifyAdmins("\x04[Recidivism]\x01 \x07Не удалось\x01 эскалировать %s [%s]", steamId, trackStr);
	}
}

void MarkEscalated(const char[] steamId, const char[] trackStr, const char[] reason)
{
	char esc[64], escTrack[16], escReason[512];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	g_hDB.Escape(trackStr, escTrack, sizeof(escTrack));
	g_hDB.Escape(reason, escReason, sizeof(escReason));

	char query[640];
	FormatEx(query, sizeof(query),
		"UPDATE `sb_recid_scores` SET `escalated`=1, `escalated_at`=%d, `updated_at`=%d \
		WHERE `authid`='%s' AND `track`='%s'",
		GetTime(), GetTime(), esc, escTrack);
	g_hDB.Query(SQL_EmptyCallback, query);

	// Ledger marker attached to latest incident for this player (skip if none)
	FormatEx(query, sizeof(query),
		"INSERT INTO `sb_recid_events` \
		(`incident_id`,`authid`,`track`,`points_raw`,`incident_multiplier`,`source`,`ma_table`, \
		 `length_seconds`,`reason`,`aid`,`sid`,`created_at`) \
		SELECT i.`incident_id`, '%s', '%s', 0, 1.0, 'auto_escalate', 'none', 0, '%s', 0, 0, %d \
		FROM `sb_recid_incidents` i WHERE i.`authid`='%s' \
		ORDER BY i.`opened_at` DESC LIMIT 1",
		esc, escTrack, escReason, GetTime(), esc);
	g_hDB.Query(SQL_EmptyCallback, query);
}

void NotifyAdmins(const char[] fmt, any ...)
{
	if (!g_cvNotifyAdmins.BoolValue)
		return;
	char buffer[256];
	VFormat(buffer, sizeof(buffer), fmt, 2);
	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && !IsFakeClient(i) && CheckCommandAccess(i, "sm_history", ADMFLAG_GENERIC))
			PrintToChat(i, "%s", buffer);
	}
	PrintToServer("%s", buffer);
}

// ---------------------------------------------------------------------------
// Commands / Menu
// ---------------------------------------------------------------------------

public Action Command_ReloadConfig(int client, int args)
{
	Database db = GetWorkingDB();
	if (db == null || !g_bDBReady)
	{
		ReplyToCommand(client, "[Recidivism] DB not ready.");
		return Plugin_Handled;
	}
	RunSchemaStep(db, SCHEMA_STEP_LOADCFG);
	ReplyToCommand(client, "[Recidivism] Reloading sb_recid_config…");
	return Plugin_Handled;
}

public Action Command_History(int client, int args)
{
	if (args < 1)
	{
		if (client == 0)
		{
			ReplyToCommand(client, "[Recidivism] Usage: sm_history <target|STEAM_>");
			return Plugin_Handled;
		}
		ShowHistoryTargetMenu(client);
		return Plugin_Handled;
	}

	char arg[64];
	GetCmdArg(1, arg, sizeof(arg));
	char steamId[64];
	int target = FindTarget(client, arg, true, false);
	if (target > 0)
	{
		if (!GetClientAuthId(target, AuthId_Steam2, steamId, sizeof(steamId)))
		{
			ReplyToCommand(client, "[Recidivism] Нет SteamID.");
			return Plugin_Handled;
		}
		ShowHistory(client, steamId, target);
		return Plugin_Handled;
	}
	if (StrContains(arg, "STEAM_") == 0)
	{
		ShowHistory(client, arg, 0);
		return Plugin_Handled;
	}
	ReplyToCommand(client, "[Recidivism] Цель не найдена.");
	return Plugin_Handled;
}

public Action Command_Reset(int client, int args)
{
	if (args < 1)
	{
		ReplyToCommand(client, "Usage: sm_recidivism_reset <target|STEAM_>");
		return Plugin_Handled;
	}

	char arg[64], steamId[64];
	GetCmdArg(1, arg, sizeof(arg));
	int target = FindTarget(client, arg, true, false);
	if (target > 0)
	{
		if (!GetClientAuthId(target, AuthId_Steam2, steamId, sizeof(steamId)))
		{
			ReplyToCommand(client, "[Recidivism] Нет SteamID.");
			return Plugin_Handled;
		}
	}
	else if (StrContains(arg, "STEAM_") == 0)
		strcopy(steamId, sizeof(steamId), arg);
	else
	{
		ReplyToCommand(client, "[Recidivism] Цель не найдена.");
		return Plugin_Handled;
	}

	if (GetWorkingDB() == null || !g_bSchemaReady)
	{
		ReplyToCommand(client, "[Recidivism] БД не готова.");
		return Plugin_Handled;
	}

	char esc[64];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	char query[384];
	FormatEx(query, sizeof(query),
		"UPDATE `sb_recid_events` SET `revoked`=1, `revoked_by`=0, `revoked_at`=%d, `revoke_reason`='sm_recidivism_reset' \
		WHERE `authid`='%s' AND `revoked`=0",
		GetTime(), esc);
	g_hDB.Query(SQL_EmptyCallback, query);

	FormatEx(query, sizeof(query),
		"UPDATE `sb_recid_scores` SET `score`=0, `events_active`=0, `escalated`=0, `escalated_at`=NULL, `updated_at`=%d \
		WHERE `authid`='%s'",
		GetTime(), esc);
	g_hDB.Query(SQL_EmptyCallback, query);

	ReplyToCommand(client, "[Recidivism] События revoked, scores сброшены: %s", steamId);
	return Plugin_Handled;
}

void ShowHistory(int client, const char[] steamId, int target)
{
	if (GetWorkingDB() == null || !g_bSchemaReady)
	{
		ReplyToCommand(client, "[Recidivism] БД/схема не готова.");
		return;
	}

	int targetUid = 0;
	if (target > 0)
		targetUid = GetClientUserId(target);
	else
		targetUid = FindOnlineUserIdBySteam(steamId);

	// Menu for requester + chat check for all admins
	AnnounceRecidivismCheck(steamId, targetUid, client ? GetClientUserId(client) : 0, true);
}

// ---------------------------------------------------------------------------
// Fingerprint family helpers (rebanner DB)
// ---------------------------------------------------------------------------

bool GetCachedFamilyCsv(const char[] steamId, char[] outCsv, int maxlen)
{
	char tbuf[32];
	if (!g_hFamilyCacheTime.GetString(steamId, tbuf, sizeof(tbuf)))
		return false;
	if (GetGameTime() - StringToFloat(tbuf) > FAMILY_CACHE_TTL)
		return false;
	return g_hFamilyCache.GetString(steamId, outCsv, maxlen);
}

void CacheFamilyCsv(const char[] steamId, const char[] csv)
{
	char tbuf[32];
	FormatEx(tbuf, sizeof(tbuf), "%.1f", GetGameTime());
	g_hFamilyCache.SetString(steamId, csv);
	g_hFamilyCacheTime.SetString(steamId, tbuf);
}

void NormalizeFamilyCsv(const char[] selfId, const char[] rawList, char[] outCsv, int maxlen)
{
	char parts[FAMILY_MAX_MEMBERS][64];
	int n = ExplodeString(rawList, ";", parts, FAMILY_MAX_MEMBERS, 64);
	char seen[FAMILY_MAX_MEMBERS][64];
	int sn = 0;
	outCsv[0] = '\0';

	// always include self first
	strcopy(outCsv, maxlen, selfId);
	strcopy(seen[sn++], 64, selfId);

	for (int i = 0; i < n && sn < FAMILY_MAX_MEMBERS; i++)
	{
		TrimString(parts[i]);
		if (parts[i][0] == '\0' || strncmp(parts[i], "STEAM_", 6, false) != 0)
			continue;
		// normalize universe digit to 0
		if (parts[i][6] != '0')
			parts[i][6] = '0';

		bool dup = false;
		for (int j = 0; j < sn; j++)
		{
			if (StrEqual(seen[j], parts[i]))
			{
				dup = true;
				break;
			}
		}
		if (dup)
			continue;
		strcopy(seen[sn++], 64, parts[i]);
		Format(outCsv, maxlen, "%s;%s", outCsv, parts[i]);
	}
}

void BuildSqlInListFromCsv(const char[] csv, char[] outList, int maxlen)
{
	outList[0] = '\0';
	if (GetWorkingDB() == null)
		return;

	char parts[FAMILY_MAX_MEMBERS][64];
	int n = ExplodeString(csv, ";", parts, FAMILY_MAX_MEMBERS, 64);
	for (int i = 0; i < n; i++)
	{
		TrimString(parts[i]);
		if (parts[i][0] == '\0')
			continue;
		char esc[128];
		g_hDB.Escape(parts[i], esc, sizeof(esc));
		if (outList[0])
			Format(outList, maxlen, "%s,'%s'", outList, esc);
		else
			FormatEx(outList, maxlen, "'%s'", esc);
	}
}

int CountFamilyMembers(const char[] csv)
{
	if (!csv[0])
		return 1;
	int n = 1;
	for (int i = 0; csv[i]; i++)
		if (csv[i] == ';')
			n++;
	return n;
}

/**
 * Load scores → chat summary to admins; optionally open simple menu (nick + points).
 * targetUserId: online player userid (0 if offline)
 * viewerUserId: admin who opened menu (0 = chat-only broadcast)
 */
void AnnounceRecidivismCheck(const char[] steamId, int targetUserId, int viewerUserId, bool openMenu)
{
	if (GetWorkingDB() == null || !g_bSchemaReady)
		return;

	DataPack pack = new DataPack();
	pack.WriteString(steamId);
	pack.WriteCell(targetUserId);
	pack.WriteCell(viewerUserId);
	pack.WriteCell(openMenu ? 1 : 0);

	char esc[64];
	g_hDB.Escape(steamId, esc, sizeof(esc));
	char query[256];
	FormatEx(query, sizeof(query),
		"SELECT `track`,`score`,`escalated` FROM `sb_recid_scores` WHERE `authid`='%s'", esc);
	g_hDB.Query(SQL_OnRecidivismCheck, query, pack);
}

public void SQL_OnRecidivismCheck(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	char steamId[64];
	pack.ReadString(steamId, sizeof(steamId));
	int targetUserId = pack.ReadCell();
	int viewerUserId = pack.ReadCell();
	bool openMenu = pack.ReadCell() != 0;

	float sumBan, sumGag, sumMute;
	bool escBan, escGag, escMute;
	if (results != null && !error[0])
	{
		while (results.FetchRow())
		{
			char t[8];
			results.FetchString(0, t, sizeof(t));
			float sc = results.FetchFloat(1);
			bool es = results.FetchInt(2) != 0;
			if (StrEqual(t, "ban")) { sumBan = sc; escBan = es; }
			else if (StrEqual(t, "gag")) { sumGag = sc; escGag = es; }
			else if (StrEqual(t, "mute")) { sumMute = sc; escMute = es; }
		}
	}
	else if (error[0])
	{
		LogError("[MA Recidivism] Check scores: %s", error);
	}

	// Continue with family-max lookup
	DataPack pack2 = new DataPack();
	pack2.WriteString(steamId);
	pack2.WriteCell(targetUserId);
	pack2.WriteCell(viewerUserId);
	pack2.WriteCell(openMenu ? 1 : 0);
	pack2.WriteFloat(sumBan); pack2.WriteCell(escBan ? 1 : 0);
	pack2.WriteFloat(sumGag); pack2.WriteCell(escGag ? 1 : 0);
	pack2.WriteFloat(sumMute); pack2.WriteCell(escMute ? 1 : 0);
	delete pack;

	char familyCsv[1024];
	if (GetCachedFamilyCsv(steamId, familyCsv, sizeof(familyCsv)))
	{
		FetchFamilyMaxForAnnounce(familyCsv, pack2);
		return;
	}

	if (g_hRebanDB == null)
	{
		FinishRecidivismAnnounce(pack2, sumBan, sumGag, sumMute, 1);
		return;
	}

	char esc[64], esc1[64];
	g_hRebanDB.Escape(steamId, esc, sizeof(esc));
	if (strlen(steamId) > 7)
	{
		FormatEx(esc1, sizeof(esc1), "STEAM_1%s", steamId[7]);
		g_hRebanDB.Escape(esc1, esc1, sizeof(esc1));
	}
	else
		strcopy(esc1, sizeof(esc1), esc);

	char query[512];
	FormatEx(query, sizeof(query),
		"SELECT `steamid2` FROM `rebanner_fingerprints` \
		WHERE FIND_IN_SET('%s', REPLACE(`steamid2`, ';', ',')) > 0 \
		   OR FIND_IN_SET('%s', REPLACE(`steamid2`, ';', ',')) > 0 \
		LIMIT 1",
		esc, esc1);
	g_hRebanDB.Query(SQL_OnFamilyForAnnounce, query, pack2);
}

public void SQL_OnFamilyForAnnounce(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	char steamId[64];
	pack.ReadString(steamId, sizeof(steamId));
	// keep rest in pack — rebuild
	int targetUserId = pack.ReadCell();
	int viewerUserId = pack.ReadCell();
	bool openMenu = pack.ReadCell() != 0;
	float sumBan = pack.ReadFloat(); int escBan = pack.ReadCell();
	float sumGag = pack.ReadFloat(); int escGag = pack.ReadCell();
	float sumMute = pack.ReadFloat(); int escMute = pack.ReadCell();

	char familyCsv[1024];
	strcopy(familyCsv, sizeof(familyCsv), steamId);
	if (!error[0] && results != null && results.FetchRow())
	{
		char raw[1024];
		results.FetchString(0, raw, sizeof(raw));
		NormalizeFamilyCsv(steamId, raw, familyCsv, sizeof(familyCsv));
		CacheFamilyCsv(steamId, familyCsv);
	}

	DataPack pack2 = new DataPack();
	pack2.WriteString(steamId);
	pack2.WriteCell(targetUserId);
	pack2.WriteCell(viewerUserId);
	pack2.WriteCell(openMenu ? 1 : 0);
	pack2.WriteFloat(sumBan); pack2.WriteCell(escBan);
	pack2.WriteFloat(sumGag); pack2.WriteCell(escGag);
	pack2.WriteFloat(sumMute); pack2.WriteCell(escMute);
	delete pack;

	FetchFamilyMaxForAnnounce(familyCsv, pack2);
}

void FetchFamilyMaxForAnnounce(const char[] familyCsv, DataPack pack)
{
	int famCount = CountFamilyMembers(familyCsv);
	if (famCount <= 1 || GetWorkingDB() == null)
	{
		// 0,0,0 → Finish uses own scores from pack
		FinishRecidivismAnnounce(pack, 0.0, 0.0, 0.0, famCount > 0 ? famCount : 1);
		return;
	}

	char inList[1536];
	BuildSqlInListFromCsv(familyCsv, inList, sizeof(inList));
	DataPack packMeta = new DataPack();
	packMeta.WriteCell(view_as<int>(pack));
	packMeta.WriteCell(famCount);

	char query[2048];
	FormatEx(query, sizeof(query),
		"SELECT `track`, MAX(`score`) AS mx FROM `sb_recid_scores` \
		WHERE `authid` IN (%s) GROUP BY `track`",
		inList);
	g_hDB.Query(SQL_OnFamilyMaxAnnounce, query, packMeta);
}

public void SQL_OnFamilyMaxAnnounce(Database db, DBResultSet results, const char[] error, DataPack packMeta)
{
	packMeta.Reset();
	DataPack pack = view_as<DataPack>(packMeta.ReadCell());
	int famCount = packMeta.ReadCell();
	delete packMeta;

	pack.Reset();
	char steamId[64];
	pack.ReadString(steamId, sizeof(steamId));
	int targetUserId = pack.ReadCell();
	int viewerUserId = pack.ReadCell();
	bool openMenu = pack.ReadCell() != 0;
	float sumBan = pack.ReadFloat(); int escBan = pack.ReadCell();
	float sumGag = pack.ReadFloat(); int escGag = pack.ReadCell();
	float sumMute = pack.ReadFloat(); int escMute = pack.ReadCell();

	float famBan = sumBan, famGag = sumGag, famMute = sumMute;
	if (!error[0] && results != null)
	{
		while (results.FetchRow())
		{
			char t[8];
			results.FetchString(0, t, sizeof(t));
			float mx = results.FetchFloat(1);
			if (StrEqual(t, "ban") && mx > famBan) famBan = mx;
			else if (StrEqual(t, "gag") && mx > famGag) famGag = mx;
			else if (StrEqual(t, "mute") && mx > famMute) famMute = mx;
		}
	}

	DataPack pack2 = new DataPack();
	pack2.WriteString(steamId);
	pack2.WriteCell(targetUserId);
	pack2.WriteCell(viewerUserId);
	pack2.WriteCell(openMenu ? 1 : 0);
	pack2.WriteFloat(sumBan); pack2.WriteCell(escBan);
	pack2.WriteFloat(sumGag); pack2.WriteCell(escGag);
	pack2.WriteFloat(sumMute); pack2.WriteCell(escMute);
	pack2.WriteFloat(famBan);
	pack2.WriteFloat(famGag);
	pack2.WriteFloat(famMute);
	pack2.WriteCell(famCount);
	delete pack;

	FinishRecidivismAnnounceEx(pack2);
}

void FinishRecidivismAnnounce(DataPack pack, float famBan, float famGag, float famMute, int famCount)
{
	pack.Reset();
	char steamId[64];
	pack.ReadString(steamId, sizeof(steamId));
	int targetUserId = pack.ReadCell();
	int viewerUserId = pack.ReadCell();
	bool openMenu = pack.ReadCell() != 0;
	float sumBan = pack.ReadFloat(); int escBan = pack.ReadCell();
	float sumGag = pack.ReadFloat(); int escGag = pack.ReadCell();
	float sumMute = pack.ReadFloat(); int escMute = pack.ReadCell();
	delete pack;

	DataPack pack2 = new DataPack();
	pack2.WriteString(steamId);
	pack2.WriteCell(targetUserId);
	pack2.WriteCell(viewerUserId);
	pack2.WriteCell(openMenu ? 1 : 0);
	pack2.WriteFloat(sumBan); pack2.WriteCell(escBan);
	pack2.WriteFloat(sumGag); pack2.WriteCell(escGag);
	pack2.WriteFloat(sumMute); pack2.WriteCell(escMute);
	pack2.WriteFloat(famBan > sumBan ? famBan : sumBan);
	pack2.WriteFloat(famGag > sumGag ? famGag : sumGag);
	pack2.WriteFloat(famMute > sumMute ? famMute : sumMute);
	pack2.WriteCell(famCount);
	FinishRecidivismAnnounceEx(pack2);
}

void FinishRecidivismAnnounceEx(DataPack pack)
{
	pack.Reset();
	char steamId[64];
	pack.ReadString(steamId, sizeof(steamId));
	int targetUserId = pack.ReadCell();
	int viewerUserId = pack.ReadCell();
	bool openMenu = pack.ReadCell() != 0;
	float sumBan = pack.ReadFloat(); int escBan = pack.ReadCell();
	float sumGag = pack.ReadFloat(); int escGag = pack.ReadCell();
	float sumMute = pack.ReadFloat(); int escMute = pack.ReadCell();
	float famBan = pack.ReadFloat();
	float famGag = pack.ReadFloat();
	float famMute = pack.ReadFloat();
	int famCount = pack.ReadCell();
	delete pack;

	char name[64];
	ResolvePlayerName(steamId, targetUserId, name, sizeof(name));

	if (famCount > 1)
		NotifyAdmins("\x04[Recidivism]\x01 \x03%s\x01 — Ban \x03%.1f\x01/%.0f | Gag \x03%.1f\x01/%.0f | Mute \x03%.1f\x01/%.0f \x05(семья max %.1f/%.1f/%.1f, %d акк.)",
			name, sumBan, g_fThresholdBan, sumGag, g_fThresholdGag, sumMute, g_fThresholdMute,
			famBan, famGag, famMute, famCount);
	else
		NotifyAdmins("\x04[Recidivism]\x01 \x03%s\x01 — Ban \x03%.1f\x01/%.0f | Gag \x03%.1f\x01/%.0f | Mute \x03%.1f\x01/%.0f",
			name, sumBan, g_fThresholdBan, sumGag, g_fThresholdGag, sumMute, g_fThresholdMute);

	float useBan = famBan > sumBan ? famBan : sumBan;
	float useGag = famGag > sumGag ? famGag : sumGag;
	float useMute = famMute > sumMute ? famMute : sumMute;

	if (useBan >= g_fThresholdBan || escBan)
		NotifyAdmins("\x04[Recidivism]\x01 \x07⚠ Ban\x01 у %s: %.1f/%.0f%s",
			name, useBan, g_fThresholdBan, escBan ? " (уже перм)" : " → автоперм");
	else if (useBan >= g_fThresholdBan * 0.75)
		NotifyAdmins("\x04[Recidivism]\x01 Ban у %s близко к порогу: \x03%.1f\x01/%.0f",
			name, useBan, g_fThresholdBan);

	if (useGag >= g_fThresholdGag || escGag)
		NotifyAdmins("\x04[Recidivism]\x01 \x07⚠ Gag\x01 у %s: %.1f/%.0f%s",
			name, useGag, g_fThresholdGag, escGag ? " (уже перм)" : " → автоперм");
	else if (useGag >= g_fThresholdGag * 0.75)
		NotifyAdmins("\x04[Recidivism]\x01 Gag у %s близко к порогу: \x03%.1f\x01/%.0f",
			name, useGag, g_fThresholdGag);

	if (useMute >= g_fThresholdMute || escMute)
		NotifyAdmins("\x04[Recidivism]\x01 \x07⚠ Mute\x01 у %s: %.1f/%.0f%s",
			name, useMute, g_fThresholdMute, escMute ? " (уже перм)" : " → пермамут");
	else if (useMute >= g_fThresholdMute * 0.75)
		NotifyAdmins("\x04[Recidivism]\x01 Mute у %s близко к порогу: \x03%.1f\x01/%.0f",
			name, useMute, g_fThresholdMute);

	if (!openMenu)
		return;

	int viewer = viewerUserId ? GetClientOfUserId(viewerUserId) : 0;
	if (viewer <= 0)
		return;

	Menu menu = new Menu(MenuHandler_History);
	if (famCount > 1)
		menu.SetTitle("%s\nокно %dд · семья %d%s", name, g_iWindowDays, famCount, g_bDryRun ? " [dry]" : "");
	else
		menu.SetTitle("%s\nокно %dд%s", name, g_iWindowDays, g_bDryRun ? " [dry]" : "");

	char line[72];
	if (famCount > 1 && famBan > sumBan)
		FormatEx(line, sizeof(line), "Ban   %.1f / %.0f (max %.1f)%s", sumBan, g_fThresholdBan, famBan, escBan ? " *" : "");
	else
		FormatEx(line, sizeof(line), "Ban   %.1f / %.0f%s", sumBan, g_fThresholdBan, escBan ? " *" : "");
	menu.AddItem("", line, ITEMDRAW_DISABLED);

	if (famCount > 1 && famGag > sumGag)
		FormatEx(line, sizeof(line), "Gag   %.1f / %.0f (max %.1f)%s", sumGag, g_fThresholdGag, famGag, escGag ? " *" : "");
	else
		FormatEx(line, sizeof(line), "Gag   %.1f / %.0f%s", sumGag, g_fThresholdGag, escGag ? " *" : "");
	menu.AddItem("", line, ITEMDRAW_DISABLED);

	if (famCount > 1 && famMute > sumMute)
		FormatEx(line, sizeof(line), "Mute  %.1f / %.0f (max %.1f)%s", sumMute, g_fThresholdMute, famMute, escMute ? " *" : "");
	else
		FormatEx(line, sizeof(line), "Mute  %.1f / %.0f%s", sumMute, g_fThresholdMute, escMute ? " *" : "");
	menu.AddItem("", line, ITEMDRAW_DISABLED);

	menu.ExitBackButton = true;
	menu.Display(viewer, MENU_TIME_FOREVER);
}

void ResolvePlayerName(const char[] steamId, int targetUserId, char[] name, int maxlen)
{
	int target = targetUserId ? GetClientOfUserId(targetUserId) : 0;
	if (target > 0)
	{
		GetClientName(target, name, maxlen);
		return;
	}
	for (int i = 1; i <= MaxClients; i++)
	{
		if (!IsClientInGame(i) || IsFakeClient(i))
			continue;
		char id[64];
		if (GetClientAuthId(i, AuthId_Steam2, id, sizeof(id)) && StrEqual(id, steamId))
		{
			GetClientName(i, name, maxlen);
			return;
		}
	}
	// last known from incidents
	strcopy(name, maxlen, steamId);
}

int FindOnlineUserIdBySteam(const char[] steamId)
{
	for (int i = 1; i <= MaxClients; i++)
	{
		if (!IsClientInGame(i) || IsFakeClient(i))
			continue;
		char id[64];
		if (GetClientAuthId(i, AuthId_Steam2, id, sizeof(id)) && StrEqual(id, steamId))
			return GetClientUserId(i);
	}
	return 0;
}

public int MenuHandler_History(Menu menu, MenuAction action, int param1, int param2)
{
	if (action == MenuAction_End)
		delete menu;
	else if (action == MenuAction_Cancel && param2 == MenuCancel_ExitBack)
		ShowHistoryTargetMenu(param1);
	return 0;
}

void ShowHistoryTargetMenu(int client)
{
	Menu menu = new Menu(MenuHandler_PickTarget);
	menu.SetTitle("Recidivism: выбрать");
	menu.AddItem("list", "Список рецидивистов (топ)");
	char userid[16], display[64];
	for (int i = 1; i <= MaxClients; i++)
	{
		if (!IsClientInGame(i) || IsFakeClient(i))
			continue;
		FormatEx(userid, sizeof(userid), "%d", GetClientUserId(i));
		FormatEx(display, sizeof(display), "%N (онлайн)", i);
		menu.AddItem(userid, display);
	}
	menu.Display(client, MENU_TIME_FOREVER);
}

public int MenuHandler_PickTarget(Menu menu, MenuAction action, int param1, int param2)
{
	if (action == MenuAction_Select)
	{
		char info[64];
		menu.GetItem(param2, info, sizeof(info));
		if (StrEqual(info, "list"))
		{
			ShowRecidivistList(param1);
			return 0;
		}
		int target = GetClientOfUserId(StringToInt(info));
		if (target > 0)
		{
			char steamId[64];
			if (GetClientAuthId(target, AuthId_Steam2, steamId, sizeof(steamId)))
				ShowHistory(param1, steamId, target);
		}
	}
	else if (action == MenuAction_End)
		delete menu;
	return 0;
}

public Action Command_RecidList(int client, int args)
{
	if (client <= 0)
	{
		ReplyToCommand(client, "[Recidivism] Только в игре.");
		return Plugin_Handled;
	}
	ShowRecidivistList(client);
	return Plugin_Handled;
}

void ShowRecidivistList(int client)
{
	if (GetWorkingDB() == null || !g_bSchemaReady)
	{
		ReplyToCommand(client, "[Recidivism] БД/схема не готова.");
		return;
	}

	DataPack pack = new DataPack();
	pack.WriteCell(GetClientUserId(client));

	// Top by sum of branch scores (active cache).
	// No aliases in HAVING/ORDER BY — some MySQL modes reject them (looks like "empty=error").
	char query[640];
	strcopy(query, sizeof(query),
		"SELECT s.`authid`, \
			MAX(CASE WHEN s.`track`='ban' THEN s.`score` ELSE 0 END) AS `b`, \
			MAX(CASE WHEN s.`track`='gag' THEN s.`score` ELSE 0 END) AS `g`, \
			MAX(CASE WHEN s.`track`='mute' THEN s.`score` ELSE 0 END) AS `m`, \
			MAX(s.`escalated`) AS `esc` \
		FROM `sb_recid_scores` AS s \
		GROUP BY s.`authid` \
		HAVING SUM(s.`score`) > 0 \
		ORDER BY SUM(s.`score`) DESC \
		LIMIT 20");

	g_hDB.Query(SQL_OnRecidList, query, pack);
}

public void SQL_OnRecidList(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	delete pack;

	int client = GetClientOfUserId(userid);
	if (!client)
		return;

	// Real failure only: null result set. Empty table → 0 rows, not an error.
	if (results == null)
	{
		if (error[0])
			LogError("[MA Recidivism] Recid list: %s", error);
		PrintToChat(client, "\x04[Recidivism]\x01 Ошибка загрузки списка.");
		return;
	}

	Menu menu = new Menu(MenuHandler_RecidList);
	menu.SetTitle("Рецидивисты\nокно %dд%s", g_iWindowDays, g_bDryRun ? " [dry]" : "");

	int n = 0;
	while (results.FetchRow())
	{
		char auth[64], nick[64];
		results.FetchString(0, auth, sizeof(auth));
		float b = results.FetchFloat(1);
		float g = results.FetchFloat(2);
		float m = results.FetchFloat(3);
		bool esc = results.FetchInt(4) != 0;
		ResolvePlayerName(auth, FindOnlineUserIdBySteam(auth), nick, sizeof(nick));

		char display[128];
		FormatEx(display, sizeof(display), "%s%s  B%.1f G%.1f M%.1f",
			esc ? "* " : "", nick, b, g, m);
		menu.AddItem(auth, display);
		n++;
	}

	if (n == 0)
		menu.AddItem("", "Пока пусто — нет очков в кэше", ITEMDRAW_DISABLED);

	menu.ExitBackButton = true;
	menu.Display(client, MENU_TIME_FOREVER);
}

public int MenuHandler_RecidList(Menu menu, MenuAction action, int param1, int param2)
{
	if (action == MenuAction_Select)
	{
		char steamId[64];
		menu.GetItem(param2, steamId, sizeof(steamId));
		if (steamId[0])
		{
			int target = 0;
			for (int i = 1; i <= MaxClients; i++)
			{
				if (!IsClientInGame(i) || IsFakeClient(i))
					continue;
				char id[64];
				if (GetClientAuthId(i, AuthId_Steam2, id, sizeof(id)) && StrEqual(id, steamId))
				{
					target = i;
					break;
				}
			}
			ShowHistory(param1, steamId, target);
		}
	}
	else if (action == MenuAction_Cancel && param2 == MenuCancel_ExitBack)
		ShowHistoryTargetMenu(param1);
	else if (action == MenuAction_End)
		delete menu;
	return 0;
}

public void OnAdminMenuCreated(Handle aTopMenu)
{
	// Categories exist; items from other plugins may not yet.
	g_hAdminMenu = TopMenu.FromHandle(aTopMenu);
}

public void OnAdminMenuReady(Handle aTopMenu)
{
	g_hAdminMenu = TopMenu.FromHandle(aTopMenu);
	BindAdminMenuItems();
}

void TryBindAdminMenu()
{
	if (!LibraryExists("adminmenu"))
		return;

	TopMenu topmenu = GetAdminTopMenu();
	if (topmenu == null)
		return;

	g_hAdminMenu = topmenu;
	BindAdminMenuItems();
}

void BindAdminMenuItems()
{
	if (g_hAdminMenu == null)
		return;

	// Primary: Material Admin category (sm_admin → «Material Admin» / AdminMenu_Main).
	// This is what staff actually open — NOT stock Player Commands.
	TopMenuObject catMA = g_hAdminMenu.FindCategory("materialadmin");
	g_objPlayerCmds = g_hAdminMenu.FindCategory(ADMINMENU_PLAYERCOMMANDS);
	if (g_objPlayerCmds == INVALID_TOPMENUOBJECT)
		g_objPlayerCmds = g_hAdminMenu.FindCategory("PlayerCommands");

	TopMenuObject serverCmds = g_hAdminMenu.FindCategory(ADMINMENU_SERVERCOMMANDS);
	if (serverCmds == INVALID_TOPMENUOBJECT)
		serverCmds = g_hAdminMenu.FindCategory("ServerCommands");

	int added = 0;

	if (catMA != INVALID_TOPMENUOBJECT)
	{
		g_hAdminMenu.AddItem("ma_recidivism_history", AdminMenu_History, catMA, "sm_history", ADMFLAG_GENERIC);
		g_hAdminMenu.AddItem("ma_recidivism_list", AdminMenu_RecidList, catMA, "sm_recidlist", ADMFLAG_GENERIC);
		added += 2;
	}

	// Also under Player Commands (stock SM category) if present
	if (g_objPlayerCmds != INVALID_TOPMENUOBJECT)
	{
		g_hAdminMenu.AddItem("ma_recidivism_history_pc", AdminMenu_History, g_objPlayerCmds, "sm_history", ADMFLAG_GENERIC);
		g_hAdminMenu.AddItem("ma_recidivism_list_pc", AdminMenu_RecidList, g_objPlayerCmds, "sm_recidlist", ADMFLAG_GENERIC);
		added += 2;
	}
	else if (serverCmds != INVALID_TOPMENUOBJECT && catMA == INVALID_TOPMENUOBJECT)
	{
		g_hAdminMenu.AddItem("ma_recidivism_history", AdminMenu_History, serverCmds, "sm_history", ADMFLAG_GENERIC);
		g_hAdminMenu.AddItem("ma_recidivism_list", AdminMenu_RecidList, serverCmds, "sm_recidlist", ADMFLAG_GENERIC);
		added += 2;
	}

	if (added == 0)
		LogError("[MA Recidivism] Admin menu: no materialadmin/PlayerCommands category yet — will retry");
	else
		LogMessage("[MA Recidivism] Admin menu items bound (cats: ma=%s player=%s, items=%d)",
			catMA != INVALID_TOPMENUOBJECT ? "yes" : "no",
			g_objPlayerCmds != INVALID_TOPMENUOBJECT ? "yes" : "no",
			added);
}

public void AdminMenu_History(TopMenu topmenu, TopMenuAction action, TopMenuObject object_id, int param, char[] buffer, int maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		FormatEx(buffer, maxlength, "Рецидив: игрок / история");
	else if (action == TopMenuAction_SelectOption)
		ShowHistoryTargetMenu(param);
}

public void AdminMenu_RecidList(TopMenu topmenu, TopMenuAction action, TopMenuObject object_id, int param, char[] buffer, int maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		FormatEx(buffer, maxlength, "Рецидив: список (топ)");
	else if (action == TopMenuAction_SelectOption)
		ShowRecidivistList(param);
}
