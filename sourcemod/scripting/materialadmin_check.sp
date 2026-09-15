#include <sourcemod>
#include <materialadmin>

#pragma semicolon 1
#pragma newdecls required

#define PLUGIN_VERSION "2.2.0"
#define DEFAULT_BANS_TABLE "sb_bans"
#define DB_CHECK_INTERVAL 30.0
#define MAX_RECONNECT_ATTEMPTS 5
#define RECONNECT_DELAY 3.0

// Database state tracking
bool g_bMAAvailable = false;
bool g_bDBReady = false;
char g_sBansTable[64];

// Timers
Handle g_hDBCheckTimer = null;

// Reconnect throttling
int g_iReconnectAttempts = 0;
float g_flLastCheck = 0.0;

// Stats
int g_iSuccessfulQueries = 0;
int g_iFailedQueries = 0;

public Plugin myinfo = {
	name = "[Sib-Soft] Material Admin Check API",
	author = "Lappland_Bro, Claude",
	description = "Detects and re-bans alt accounts through client-side fingerprinting",
	url = "https://sibnet-software.ru",
	version = PLUGIN_VERSION
};

public void OnPluginStart()
{
	g_sBansTable = DEFAULT_BANS_TABLE;
	RegConsoleCmd("ma_check_status", Command_Status, "Check Material Admin Check status");
	RegAdminCmd("ma_check_reconnect", Command_ForceReconnect, ADMFLAG_ROOT, "Force database reconnection");
	RegAdminCmd("ma_check_reset", Command_ResetStats, ADMFLAG_ROOT, "Reset stats");
}

public void OnMapStart()
{
	LogMessage("[MA Check] Map started, reinitializing...");
	g_bDBReady = false;
	g_iReconnectAttempts = 0;
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
}

public void OnLibraryAdded(const char[] name)
{
	if(StrEqual(name, "materialadmin"))
	{
		LogMessage("[MA Check] Material Admin library loaded");
		CheckDatabaseAvailability();
	}
}

public void OnLibraryRemoved(const char[] name)
{
	if(StrEqual(name, "materialadmin"))
	{
		LogMessage("[MA Check] Material Admin library removed");
		g_bMAAvailable = false;
		g_bDBReady = false;
		StopMonitoringTimer();
	}
}

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	CreateNative("MaterialAdmin_IsAvailable", Native_IsAvailable);
	CreateNative("MaterialAdmin_GetDatabase", Native_GetDatabase);
	CreateNative("MaterialAdmin_CheckBan", Native_CheckBan);
	CreateNative("MaterialAdmin_CheckFingerprintBan", Native_CheckFingerprintBan);
	CreateNative("MaterialAdmin_GetBansTable", Native_GetBansTable);
	CreateNative("MaterialAdmin_SetBansTable", Native_SetBansTable);
	
	RegPluginLibrary("materialadmin_check");
	return APLRes_Success;
}

// ============================================================================
// Database Availability Check
// ============================================================================

void CheckDatabaseAvailability()
{
	g_bMAAvailable = LibraryExists("materialadmin");
	
	if(!g_bMAAvailable)
	{
		if(g_bDBReady)
		{
			LogMessage("[MA Check] Material Admin library not available");
			g_bDBReady = false;
		}
		StartMonitoringTimer();
		return;
	}
	
	Database db = MAGetDatabase();
	
	if(db == null)
	{
		if(g_bDBReady)
		{
			LogMessage("[MA Check] Database handle lost");
			g_bDBReady = false;
		}
		StartMonitoringTimer();
		return;
	}
	
	// Проверяем реальную работоспособность только если статус изменился
	if(!g_bDBReady)
	{
		ValidateConnection(db);
	}
	else
	{
		StartMonitoringTimer();
	}
}

void ValidateConnection(Database db)
{
	if(db == null)
	{
		g_bDBReady = false;
		return;
	}
	
	db.Query(Validate_Callback, "SELECT 1");
}

public void Validate_Callback(Database db, DBResultSet results, const char[] error, any data)
{
	if(error[0])
	{
		LogError("[MA Check] Validation failed: %s", error);
		g_bDBReady = false;
		g_iFailedQueries++;
		
		if(IsConnectionError(error))
		{
			ScheduleReconnect();
		}
		else
		{
			StartMonitoringTimer();
		}
	}
	else
	{
		if(!g_bDBReady)
		{
			LogMessage("[MA Check] Database ready");
			g_bDBReady = true;
			g_iReconnectAttempts = 0;
		}
		StartMonitoringTimer();
	}
}

void ScheduleReconnect()
{
	if(g_iReconnectAttempts >= MAX_RECONNECT_ATTEMPTS)
	{
		LogError("[MA Check] Max reconnect attempts reached. Use ma_check_reconnect to retry.");
		return;
	}
	
	g_iReconnectAttempts++;
	LogMessage("[MA Check] Scheduling reconnect attempt %d/%d", g_iReconnectAttempts, MAX_RECONNECT_ATTEMPTS);
	CreateTimer(RECONNECT_DELAY, Timer_Reconnect, _, TIMER_FLAG_NO_MAPCHANGE);
}

public Action Timer_Reconnect(Handle timer)
{
	CheckDatabaseAvailability();
	return Plugin_Stop;
}

void StartMonitoringTimer()
{
	if(g_hDBCheckTimer == null)
	{
		g_hDBCheckTimer = CreateTimer(DB_CHECK_INTERVAL, Timer_Monitor, _, TIMER_REPEAT);
	}
}

void StopMonitoringTimer()
{
	if(g_hDBCheckTimer != null)
	{
		KillTimer(g_hDBCheckTimer);
		g_hDBCheckTimer = null;
	}
}

public Action Timer_Monitor(Handle timer)
{
	float now = GetGameTime();
	if(now - g_flLastCheck < DB_CHECK_INTERVAL - 1.0)
	{
		return Plugin_Continue;
	}
	g_flLastCheck = now;
	
	// Проверяем только доступность библиотеки
	bool wasAvailable = g_bMAAvailable;
	g_bMAAvailable = LibraryExists("materialadmin");
	
	if(!g_bMAAvailable)
	{
		if(wasAvailable)
		{
			LogMessage("[MA Check] Material Admin became unavailable");
			g_bDBReady = false;
		}
		return Plugin_Continue;
	}
	
	// Проверяем что handle существует
	Database db = MAGetDatabase();
	if(db == null)
	{
		if(g_bDBReady)
		{
			LogMessage("[MA Check] Database handle lost");
			g_bDBReady = false;
		}
		return Plugin_Continue;
	}
	
	// Не проверяем handle на изменение - это нормально для Material Admin
	// Он сам управляет переподключениями
	
	return Plugin_Continue;
}

bool IsConnectionError(const char[] error)
{
	return (StrContains(error, "Lost connection", false) != -1 ||
	        StrContains(error, "MySQL server has gone away", false) != -1 ||
	        StrContains(error, "Can't connect", false) != -1 ||
	        StrContains(error, "Connection refused", false) != -1 ||
	        StrContains(error, "Commands out of sync", false) != -1 ||
	        StrContains(error, "Server shutdown in progress", false) != -1 ||
	        StrContains(error, "Too many connections", false) != -1);
}

// ============================================================================
// Natives Implementation
// ============================================================================

public any Native_IsAvailable(Handle plugin, int numParams)
{
	if(!g_bMAAvailable)
		return false;
	
	Database db = MAGetDatabase();
	return db != null && g_bDBReady;
}

public any Native_GetDatabase(Handle plugin, int numParams)
{
	if(!g_bMAAvailable || !g_bDBReady)
		return 0;
	
	Database db = MAGetDatabase();
	return db;
}

public any Native_CheckBan(Handle plugin, int numParams)
{
	int client = GetNativeCell(1);
	
	if(client < 1 || client > MaxClients)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", client);
		return false;
	}
	
	if(!IsClientInGame(client))
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", client);
		return false;
	}
	
	Database db = MAGetDatabase();
	if(!g_bDBReady || db == null)
	{
		return false;
	}
	
	char steamid[64];
	GetNativeString(2, steamid, sizeof(steamid));
	
	Function callback = GetNativeFunction(3);
	any data = GetNativeCell(4);
	
	char steamidPart[64];
	strcopy(steamidPart, sizeof(steamidPart), steamid);
	if(StrContains(steamidPart, "STEAM_") == 0)
	{
		strcopy(steamidPart, sizeof(steamidPart), steamid[8]);
	}
	
	char escapedSteamID[128];
	db.Escape(steamidPart, escapedSteamID, sizeof(escapedSteamID));
	
	char query[512];
	Format(query, sizeof(query), 
		"SELECT length, ends FROM %s WHERE authid REGEXP '^STEAM_[0-9]:%s$' AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) ORDER BY ends DESC LIMIT 1",
		g_sBansTable, escapedSteamID);
	
	DataPack pack = new DataPack();
	pack.WriteCell(client);
	pack.WriteCell(plugin);
	pack.WriteFunction(callback);
	pack.WriteCell(data);
	
	db.Query(CheckBan_Callback, query, pack);
	return true;
}

public void CheckBan_Callback(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int client = pack.ReadCell();
	Handle plugin = pack.ReadCell();
	Function callback = pack.ReadFunction();
	any data = pack.ReadCell();
	delete pack;
	
	if(error[0])
	{
		LogError("[MA Check] CheckBan query error: %s", error);
		g_iFailedQueries++;
		
		if(IsConnectionError(error))
		{
			g_bDBReady = false;
			ScheduleReconnect();
		}
		
		if(callback != INVALID_FUNCTION && plugin != null)
		{
			Call_StartFunction(plugin, callback);
			Call_PushCell(client);
			Call_PushCell(false);
			Call_PushCell(0);
			Call_PushCell(0);
			Call_PushCell(data);
			Call_Finish();
		}
		return;
	}
	
	g_iSuccessfulQueries++;
	
	bool isBanned = false;
	int banLength = 0;
	int banEnds = 0;
	
	if(results.FetchRow())
	{
		isBanned = true;
		banLength = results.FetchInt(0);
		banEnds = results.FetchInt(1);
	}
	
	if(callback != INVALID_FUNCTION && plugin != null)
	{
		Call_StartFunction(plugin, callback);
		Call_PushCell(client);
		Call_PushCell(isBanned);
		Call_PushCell(banLength);
		Call_PushCell(banEnds);
		Call_PushCell(data);
		Call_Finish();
	}
}

public any Native_CheckFingerprintBan(Handle plugin, int numParams)
{
	int client = GetNativeCell(1);
	
	if(client < 1 || client > MaxClients)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", client);
		return false;
	}
	
	if(!IsClientInGame(client))
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", client);
		return false;
	}
	
	Database db = MAGetDatabase();
	if(!g_bDBReady || db == null)
	{
		return false;
	}
	
	char fingerprint[128], steamids[2048];
	GetNativeString(2, fingerprint, sizeof(fingerprint));
	GetNativeString(3, steamids, sizeof(steamids));
	
	Function callback = GetNativeFunction(4);
	any data = GetNativeCell(5);
	
	char inList[4096];
	if(!BuildMAAuthIdInList(db, steamids, inList, sizeof(inList)))
	{
		return false;
	}
	
	char query[8192];
	Format(query, sizeof(query),
		"SELECT length, ends FROM %s WHERE authid IN (%s) AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) ORDER BY ends DESC LIMIT 1",
		g_sBansTable, inList);
	
	DataPack pack = new DataPack();
	pack.WriteCell(client);
	pack.WriteString(fingerprint);
	pack.WriteCell(plugin);
	pack.WriteFunction(callback);
	pack.WriteCell(data);
	
	db.Query(CheckFingerprintBan_Callback, query, pack);
	return true;
}

public void CheckFingerprintBan_Callback(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int client = pack.ReadCell();
	char fingerprint[128];
	pack.ReadString(fingerprint, sizeof(fingerprint));
	Handle plugin = pack.ReadCell();
	Function callback = pack.ReadFunction();
	any data = pack.ReadCell();
	delete pack;
	
	if(error[0])
	{
		LogError("[MA Check] CheckFingerprintBan query error: %s", error);
		g_iFailedQueries++;
		
		if(IsConnectionError(error))
		{
			g_bDBReady = false;
			ScheduleReconnect();
		}
		
		if(callback != INVALID_FUNCTION && plugin != null)
		{
			Call_StartFunction(plugin, callback);
			Call_PushCell(client);
			Call_PushString(fingerprint);
			Call_PushCell(false);
			Call_PushCell(0);
			Call_PushCell(0);
			Call_PushCell(data);
			Call_Finish();
		}
		return;
	}
	
	g_iSuccessfulQueries++;
	
	bool isBanned = false;
	int banLength = 0;
	int banEnds = 0;
	
	if(results.FetchRow())
	{
		isBanned = true;
		banLength = results.FetchInt(0);
		banEnds = results.FetchInt(1);
	}
	
	if(callback != INVALID_FUNCTION && plugin != null)
	{
		Call_StartFunction(plugin, callback);
		Call_PushCell(client);
		Call_PushString(fingerprint);
		Call_PushCell(isBanned);
		Call_PushCell(banLength);
		Call_PushCell(banEnds);
		Call_PushCell(data);
		Call_Finish();
	}
}

public any Native_GetBansTable(Handle plugin, int numParams)
{
	int maxlen = GetNativeCell(2);
	SetNativeString(1, g_sBansTable, maxlen);
	return true;
}

public any Native_SetBansTable(Handle plugin, int numParams)
{
	GetNativeString(1, g_sBansTable, sizeof(g_sBansTable));
	LogMessage("[MA Check] Bans table changed to: %s", g_sBansTable);
	return 0;
}

// ============================================================================
// Helper Functions
// ============================================================================

bool BuildMAAuthIdInList(Database maDB, const char[] steamIdsRaw, char[] outList, int maxlen)
{
	outList[0] = '\0';
	if(!steamIdsRaw[0])
		return false;
	
	char steamIdArray[128][64];
	int count = ExplodeString(steamIdsRaw, ";", steamIdArray, sizeof(steamIdArray), sizeof(steamIdArray[]));
	
	if(count <= 0)
		return false;
	
	int added = 0;
	
	for(int i = 0; i < count; i++)
	{
		TrimString(steamIdArray[i]);
		if(!steamIdArray[i][0])
			continue;
		
		char escaped[96];
		maDB.Escape(steamIdArray[i], escaped, sizeof(escaped));
		
		if(added == 0)
		{
			Format(outList, maxlen, "'%s'", escaped);
		}
		else
		{
			Format(outList, maxlen, "%s,'%s'", outList, escaped);
		}
		added++;
	}
	
	return added > 0;
}

// ============================================================================
// Commands
// ============================================================================

public Action Command_Status(int client, int args)
{
	ReplyToCommand(client, "[MA Check] === Status Report ===");
	ReplyToCommand(client, "  Version: %s", PLUGIN_VERSION);
	ReplyToCommand(client, "  MA Library: %s", g_bMAAvailable ? "Available" : "Not available");
	
	Database db = MAGetDatabase();
	ReplyToCommand(client, "  Database Handle: %s", db != null ? "Exists" : "NULL");
	ReplyToCommand(client, "  Database Ready: %s", g_bDBReady ? "YES" : "NO");
	ReplyToCommand(client, "  Monitor Timer: %s", g_hDBCheckTimer != null ? "Active" : "Inactive");
	ReplyToCommand(client, "  Bans Table: %s", g_sBansTable);
	ReplyToCommand(client, "  Queries Successful: %d", g_iSuccessfulQueries);
	ReplyToCommand(client, "  Queries Failed: %d", g_iFailedQueries);
	ReplyToCommand(client, "  Reconnect Attempts: %d/%d", g_iReconnectAttempts, MAX_RECONNECT_ATTEMPTS);
	
	if(g_iReconnectAttempts >= MAX_RECONNECT_ATTEMPTS)
	{
		ReplyToCommand(client, "  [!] Max reconnects reached! Use ma_check_reconnect to retry.");
	}
	
	return Plugin_Handled;
}

public Action Command_ForceReconnect(int client, int args)
{
	ReplyToCommand(client, "[MA Check] Forcing reconnection check...");
	g_bDBReady = false;
	g_iReconnectAttempts = 0;
	CheckDatabaseAvailability();
	return Plugin_Handled;
}

public Action Command_ResetStats(int client, int args)
{
	g_iReconnectAttempts = 0;
	g_iSuccessfulQueries = 0;
	g_iFailedQueries = 0;
	ReplyToCommand(client, "[MA Check] Statistics reset");
	return Plugin_Handled;
}

public void OnPluginEnd()
{
	StopMonitoringTimer();
}