#include <sourcemod>
#include <filenetwork>
#include <sdktools_stringtables>
#include <materialadmin_check>
#include <materialadmin>

#pragma semicolon 1
#pragma newdecls required

#include "rebanner/rebanner_helpers.sp"
#include "rebanner/rebanner_database.sp"
#include "rebanner/rebanner_fingerprint.sp"
#include "rebanner/rebanner_fastdl.sp"
#include "rebanner/rebanner_banning.sp"
#include "rebanner/rebanner_commands.sp"
#include "rebanner/rebanner_ma_comms.sp"

#define DEFAULT_FINGERPRINT "materials/models/texture.vmt"
#define BAN_REASON "Alternative account detected, re-applying ban"
#define ANTITAMPER_ACTION_REASON "File tampering detected! Please download server files from scratch"
#define LOGFILE "logs/rebanner.log"
#define INVALID_USERID -1

ConVar antiTamperMode, antiTamperAction, logLevel;
char logFilePath[PLATFORM_MAX_PATH], fingerprintPath[PLATFORM_MAX_PATH];
char antitamperKickReason[256];
static char logLevelDefinitions[5][32] = {"[NONE]", "[BAN EVENT]", "[ASSOCIATION]", "[DEBUG]", "[EXTRA]"};

static bool g_bPutInServerHandled[MAXPLAYERS + 1];
static bool g_bFingerprintRequestSent[MAXPLAYERS + 1];
static bool g_bFingerprintProcessed[MAXPLAYERS + 1];

#define FINGERPRINT_FALLBACK_RETRY_SEC 2.0

void ResetClientFingerprintState(int client)
{
	if(!IsValidClientIndex(client))
		return;
	g_bPutInServerHandled[client] = false;
	g_bFingerprintRequestSent[client] = false;
	g_bFingerprintProcessed[client] = false;
}

void ResetAllClientFingerprintStates()
{
	for(int i = 1; i <= MaxClients; i++)
		ResetClientFingerprintState(i);
}

public Plugin myinfo = {
	name = "[Sib-Soft] Parallax Second",
	author = "Lappland_Bro, AI",
	description = "Detects and re-bans alt accounts of banned players through client-side fingerprinting",
	url = "https://sibnet-software.ru",
	version = "2.5.3.0"
};

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	__ext_materialadmin_check_SetNTVOptional();
	return APLRes_Success;
}

public void OnPluginStart()
{
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Initializing plugin...");
	CleanupLeftoverHandles();
	
	Fingerprint_Init();
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Fingerprint_Init() completed");
	
	Database_Init();
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Database_Init() completed");
	
	FastDL_Init();
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: FastDL_Init() completed");
	
	Banning_Init();
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Banning_Init() completed");
	
	Commands_Init();
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Commands_Init() completed");
	
	Rb_CommsSync_Init();
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Rb_CommsSync_Init() completed");
	
	logLevel = CreateConVar("rb_log_level", "3", "Logging level. 0 - disable. 1 - log alt account bans, 2 - also log associations, 3 - log everything(debug, lots of SPAM), 4 - EXTRA (log absolutely EVERYTHING that happens in plugin).");
	antiTamperAction = CreateConVar("rb_antitamper_action", "1", "Action taken when fingerprint tampering is detected. 0 - do nothing, 1 - kick");
	antiTamperMode = CreateConVar("rb_antitamper_mode", "1", "Anti-tamper mode. 0 - disable (DANGEROUS), 1 - reject non-HEX fingerprint strings");
	
	ConVar rebanDurationCV = CreateConVar("rb_reban_type", "0", "How long should alts be re-banned for? 1 - same duration as original ban, 0 - remaining duration of the original ban");
	Banning_SetRebanDuration(rebanDurationCV);
	
	Banning_SetRebanReason(BAN_REASON);
	
	strcopy(antitamperKickReason, sizeof(antitamperKickReason), ANTITAMPER_ACTION_REASON);
	
	WriteLogFormatted(LogLevel_Extra, "OnPluginStart: Plugin initialized. rb_antitamper_action=%d, rb_antitamper_mode=%d, rb_reban_type=%d", 
		antiTamperAction.IntValue, antiTamperMode.IntValue, rebanDurationCV.IntValue);
	// Пометка: плагин полностью инициализирован — безопасно вызывать операции с БД/MaterialAdmin
	Rebanner_SetInitialized(true);
	ResetAllClientFingerprintStates();
}


public void OnMapStart()
{
	WriteLogFormatted(LogLevel_Extra, "OnMapStart: Starting map initialization. Fingerprint path: %s", fingerprintPath);
	
	ParseConfigFile();
	WriteLogFormatted(LogLevel_Extra, "OnMapStart: ParseConfigFile() completed");
	
	FastDL_OnMapStart();
	WriteLogFormatted(LogLevel_Extra, "OnMapStart: FastDL_OnMapStart() completed");
	
	AddFileToDownloadsTable(fingerprintPath);
	WriteLogFormatted(LogLevel_Extra, "OnMapStart: Added fingerprint to downloads table: %s", fingerprintPath);
	
	char filepath[PLATFORM_MAX_PATH];
	Format(filepath, sizeof(filepath), "download/%s", fingerprintPath);
	if(FileExists(filepath)) {
		DeleteFile(filepath);
		WriteLogFormatted(LogLevel_Extra, "OnMapStart: Cleaned up old fingerprint file: %s", filepath);
	} else {
		WriteLogFormatted(LogLevel_Extra, "OnMapStart: No old fingerprint file to clean: %s", filepath);
	}
}

public void OnMapEnd()
{
	WriteLogFormatted(LogLevel_Extra, "OnMapEnd: Ending map, cleaning up...");
	ResetAllClientFingerprintStates();
	FastDL_OnMapEnd();
	WriteLogFormatted(LogLevel_Extra, "OnMapEnd: FastDL_OnMapEnd() completed");
}

void CleanupLeftoverHandles()
{
	Database_Close();
	Fingerprint_CleanupCache();
	FastDL_Cleanup();
	
	WriteLogFormatted(LogLevel_Extra, "CleanupLeftoverHandles: Cleanup completed");
}

public void OnPluginEnd()
{
	WriteLogFormatted(LogLevel_Extra, "OnPluginEnd: Plugin unloading, cleaning up all handles and caches");
	
	Database_Close();
	Fingerprint_CleanupCache();
	FastDL_Cleanup();
	
	// Пометка: плагин больше не инициализирован (предотвращаем обращения во время выгрузки)
	Rebanner_SetInitialized(false);
	WriteLogFormatted(LogLevel_Extra, "OnPluginEnd: Plugin cleanup completed");
}

public void OnClientPutInServer(int client)
{
	if(IsValidClientIndex(client))
		g_bPutInServerHandled[client] = true;
	
	BeginClientFingerprintFlow(client, "PutInServer");
}

public void OnClientPostAdminCheck(int client)
{
	if(!IsValidClientIndex(client))
		return;
	
	if(!g_bPutInServerHandled[client]) {
		WriteLogFormatted(LogLevel_Associations, "OnClientPostAdminCheck: OnClientPutInServer did not run for client %d, using fallback fingerprint flow", client);
	}
	
	if(!g_bFingerprintRequestSent[client])
		BeginClientFingerprintFlow(client, "PostAdminCheck");
	
	if(!CanProcessClient(client))
		return;
	
	int userid = GetClientUserId(client);
	if(userid == INVALID_USERID)
		return;
	
	CreateTimer(FINGERPRINT_FALLBACK_RETRY_SEC, Timer_FingerprintFallbackRetry, userid, TIMER_FLAG_NO_MAPCHANGE);
}

public Action Timer_FingerprintFallbackRetry(Handle timer, int userid)
{
	int client = GetClientOfUserId(userid);
	if(client <= 0 || !IsClientInGame(client) || !CanProcessClient(client))
		return Plugin_Stop;
	
	if(g_bFingerprintProcessed[client] || g_bFingerprintRequestSent[client])
		return Plugin_Stop;
	
	WriteLogFormatted(LogLevel_Associations, "Timer_FingerprintFallbackRetry: Fingerprint flow still pending for client %d after %.1fs, retrying", client, FINGERPRINT_FALLBACK_RETRY_SEC);
	BeginClientFingerprintFlow(client, "TimerFallback");
	return Plugin_Stop;
}

void BeginClientFingerprintFlow(int client, const char[] trigger)
{
	char steamid[64], ip[64];
	GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid), false);
	GetClientIP(client, ip, sizeof(ip));
	WriteLogFormatted(LogLevel_Extra, "BeginClientFingerprintFlow[%s]: client=%d, steamid=%s, ip=%s", trigger, client, steamid, ip);
	
	if(!CanProcessClient(client)) {
		WriteLogFormatted(LogLevel_Extra, "BeginClientFingerprintFlow[%s]: client=%d cannot be processed, skipping", trigger, client);
		return;
	}
	
	int userid = GetClientUserId(client);
	if(userid == INVALID_USERID) {
		WriteLogFormatted(LogLevel_Extra, "BeginClientFingerprintFlow[%s]: client=%d has invalid UserID, skipping", trigger, client);
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "BeginClientFingerprintFlow[%s]: client=%d, userid=%d", trigger, client, userid);
	
	char issuedFingerprint[128];
	FastDL_GetIssuedFingerprint(client, issuedFingerprint, sizeof(issuedFingerprint));
	if(issuedFingerprint[0]) {
		WriteLogFormatted(LogLevel_Extra, "BeginClientFingerprintFlow[%s]: Client %d has issued fingerprint %s, marking as connected", trigger, client, issuedFingerprint);
		Fingerprint_MarkTempFingerprintConnected(issuedFingerprint);
	}
	
	if(g_bFingerprintRequestSent[client]) {
		WriteLogFormatted(LogLevel_Extra, "BeginClientFingerprintFlow[%s]: FileNet request already sent for client %d, skipping", trigger, client);
		return;
	}
	
	if(RequestFingerprintFromClient(client, userid)) {
		g_bFingerprintRequestSent[client] = true;
	}
}

bool RequestFingerprintFromClient(int client, int userid)
{
	WriteLogFormatted(LogLevel_Extra, "RequestFingerprintFromClient: client=%d, userid=%d, fingerprintPath=%s", client, userid, fingerprintPath);
	
	if(!CanProcessClient(client)) {
		WriteLogFormatted(LogLevel_Extra, "RequestFingerprintFromClient: client=%d cannot be processed, aborting", client);
		return false;
	}
	
	DataPack pack = new DataPack();
	pack.WriteCell(userid);
	WriteLogFormatted(LogLevel_Extra, "RequestFingerprintFromClient: Created DataPack, calling FileNet_RequestFile");
	
	int requestId = FileNet_RequestFile(client, fingerprintPath, OnFingerprintReceivedCallback, pack);
	if(requestId <= 0) {
		WriteLogFormatted(LogLevel_Extra, "RequestFingerprintFromClient: FileNet_RequestFile failed for client=%d (requestId=%d) - client may not have downloaded file via FastDL", client, requestId);
		delete pack;
		return false;
	}
	
	WriteLogFormatted(LogLevel_Extra, "RequestFingerprintFromClient: Requested fingerprint file from client %d via File Network (request ID: %d, path: %s)", client, requestId, fingerprintPath);
	return true;
}

void OnFingerprintReceivedCallback(int client, const char[] file, int id, bool success, DataPack pack)
{
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceivedCallback: client=%d, file=%s, id=%d, success=%d", client, file, id, success);
	
	pack.Reset();
	int userid = pack.ReadCell();
	delete pack;
	
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceivedCallback: userid from pack=%d", userid);
	
	if(!success) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceivedCallback: Failed to receive fingerprint from client %d (file=%s, id=%d). Will wait - file may arrive later (up to 5-10 minutes while client is connected)", client, file, id);
		if(IsValidClientIndex(client))
			g_bFingerprintRequestSent[client] = false;
		return;
	}
	
	int currentUserid = GetClientUserId(client);
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceivedCallback: current userid=%d, expected userid=%d", currentUserid, userid);
	
	if(userid != currentUserid) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceivedCallback: UserID mismatch (expected %d, got %d), ignoring", userid, currentUserid);
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceivedCallback: Calling OnFingerprintReceived");
	OnFingerprintReceived(client, file, id, success);
}

public void OnClientDisconnect(int client)
{
	char steamid[64], ip[64];
	if(IsClientConnected(client)) {
		GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));
		GetClientIP(client, ip, sizeof(ip));
	}
	WriteLogFormatted(LogLevel_Extra, "OnClientDisconnect: client=%d, steamid=%s, ip=%s", client, steamid, ip);
	
	ResetClientFingerprintState(client);
	FastDL_OnClientDisconnect(client);
	WriteLogFormatted(LogLevel_Extra, "OnClientDisconnect: FastDL_OnClientDisconnect() completed");
	
	char filepath[PLATFORM_MAX_PATH];
	Format(filepath, sizeof(filepath), "download/%s", fingerprintPath);
	if(FileExists(filepath)) {
		DeleteFile(filepath);
		WriteLogFormatted(LogLevel_Extra, "OnClientDisconnect: Cleaned up fingerprint file: %s", filepath);
	} else {
		WriteLogFormatted(LogLevel_Extra, "OnClientDisconnect: No fingerprint file to clean: %s", filepath);
	}
}

void ParseConfigFile()
{
	char config[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, config, sizeof(config), "configs/rebanner.cfg");
	WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Config path: %s", config);
	
	if(!FileExists(config)) {
		WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Config file does not exist, generating new one");
		GenerateConfigFile(config);
	} else {
		WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Config file exists, parsing...");
	}
	
	KeyValues kv = new KeyValues("Settings");
	kv.ImportFromFile(config);
	kv.Rewind();
	
	char enabled[8];
	kv.GetString("enable", enabled, sizeof(enabled), "0");
	WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: enable=%s", enabled);
	
	if(!view_as<bool>(StringToInt(enabled)))
		SetFailState("Waiting for FastDownloads setup. Please refer to the Wiki Setup page!");
	
	char configFingerprint[PLATFORM_MAX_PATH];
	char tempRebanReason[256];
	kv.GetString("fingerprint path", configFingerprint, sizeof(configFingerprint));
	kv.GetString("ban reason", tempRebanReason, sizeof(tempRebanReason));
	if(tempRebanReason[0]) {
		WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Setting custom ban reason: %s", tempRebanReason);
		Banning_SetRebanReason(tempRebanReason);
	}
	kv.GetString("tampering kick reason", antitamperKickReason, sizeof(antitamperKickReason));
	
	strcopy(fingerprintPath, sizeof(fingerprintPath), configFingerprint);
	WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Fingerprint path set to: %s", fingerprintPath);

	FastDL_SetFingerprintPath(fingerprintPath);
	
	if(antitamperKickReason[0]) {
		WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Anti-tamper kick reason: %s", antitamperKickReason);
	}
	
	delete kv;
	WriteLogFormatted(LogLevel_Extra, "ParseConfigFile: Config parsing completed");
}

void GenerateConfigFile(const char[] path)
{
	char randomDownloadPath[PLATFORM_MAX_PATH];
	int downloadTable = FindStringTable("downloadables");
	if(downloadTable == INVALID_STRING_TABLE)
		SetFailState("Unable to find downloadables stringtable. What?");
	
	KeyValues kv = new KeyValues("Settings");
	int downloadTableSize = GetStringTableNumStrings(downloadTable);
	
	if(downloadTableSize > 3) {
		int randomIndex = GetRandomValue(3, downloadTableSize-1);
		ReadStringTable(downloadTable, randomIndex, randomDownloadPath, sizeof(randomDownloadPath));
		char explodedString[4][256];
		int explodeSize = ExplodeString(randomDownloadPath, ".", explodedString, 4, 256);
		Format(explodedString[explodeSize-2], 256, "%s1", explodedString[explodeSize-2]);
		
		char finalFingerprintPath[PLATFORM_MAX_PATH];
		finalFingerprintPath[0] = '\0';
		for(int i = 0; i < explodeSize-1; i++)
			Format(finalFingerprintPath, sizeof(finalFingerprintPath), "%s%s", finalFingerprintPath, explodedString[i]);
		
		Format(finalFingerprintPath, sizeof(finalFingerprintPath), "%s.%s", finalFingerprintPath, explodedString[explodeSize-1]);
		kv.SetString("fingerprint path", finalFingerprintPath);
	} else {
		kv.SetString("fingerprint path", DEFAULT_FINGERPRINT);
	}
	
	kv.SetString("ban reason", BAN_REASON);
	kv.SetString("tampering kick reason", ANTITAMPER_ACTION_REASON);
	kv.SetString("enable", "0");
	kv.Rewind();
	kv.ExportToFile(path);
	delete kv;
}

void OnFingerprintReceived(int client, const char[] file, int id, bool success)
{
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: client=%d, file=%s, id=%d, success=%d", client, file, id, success);
	
	if(id < 0) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Invalid ID (%d), aborting", id);
		return;
	}
	if(!file[0]) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Empty file parameter, aborting");
		return;
	}
	
	if(!success) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Failed to receive fingerprint file from client %d via File Network (file=%s, id=%d)", client, file, id);
		return;
	}
	
	int userid = GetClientUserId(client);
	if(userid == INVALID_USERID) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Invalid UserID for client %d, aborting", client);
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: client=%d, userid=%d, reading fingerprint file", client, userid);
	
	char filepath[PLATFORM_MAX_PATH];
	Format(filepath, sizeof(filepath), "download/%s", fingerprintPath);
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Opening file: %s", filepath);
	
	File fingerprintFile = OpenFile(filepath, "r");
	if(fingerprintFile == null) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Could not read fingerprint file received from client! Path: %s", filepath);
		return;
	}
	
	char clientFingerprint[256];
	if(!fingerprintFile.ReadLine(clientFingerprint, sizeof(clientFingerprint))) {
		delete fingerprintFile;
		if(FileExists(filepath))
			DeleteFile(filepath);
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Could not read fingerprint from file received from client! Path: %s", filepath);
		return;
	}
	delete fingerprintFile;
	
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Read raw fingerprint (before cleanup): '%s'", clientFingerprint);
	
	TrimString(clientFingerprint);
	ReplaceString(clientFingerprint, sizeof(clientFingerprint), "\n", "");
	ReplaceString(clientFingerprint, sizeof(clientFingerprint), "\r", "");
	
	WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Cleaned fingerprint: '%s'", clientFingerprint);
	
	// IMPORTANT: Проверка структуры файла - только HEX символы (0-9, a-f, A-F)
	// Все что выше (пробелы, спец-символы) - подмена, кикаем Anti-Tamper
	// Используем regex паттерн для эффективной проверки
	if(antiTamperMode.IntValue > 0) {
		bool isTampered = Fingerprint_IsTampered(clientFingerprint, antiTamperMode);
		if(isTampered) {
			WriteLogFormatted(LogLevel_Associations, "OnFingerprintReceived: Anti-Tamper detected! Fingerprint does not match HEX pattern: %s", clientFingerprint);
			if(antiTamperAction.BoolValue) {
				KickClient(client, antitamperKickReason);
			}
			return;
		}
	}
	
	if(!clientFingerprint[0]) {
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Received empty fingerprint from client!");
		if(FileExists(filepath))
			DeleteFile(filepath);
		return;
	}
	
	if(FileExists(filepath)) {
		DeleteFile(filepath);
		WriteLogFormatted(LogLevel_Extra, "OnFingerprintReceived: Read fingerprint from file and deleted it: %s", clientFingerprint);
	}
	
	ProcessReceivedFingerprint(client, clientFingerprint);
}

void ProcessReceivedFingerprint(int client, const char[] fingerprint)
{
	char ip[64], steamid[64];
	
	if (!GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid)) || !steamid[0])
	{
		if (!GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid), false) || !steamid[0])
		{
			WriteLogFormatted(LogLevel_Associations, "ProcessReceivedFingerprint: Cannot get SteamID for client %d (even unvalidated), aborting to prevent NULL steamid records", client);
			return;
		}
		WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Using unvalidated SteamID for client %d: %s", client, steamid);
	}
	
	GetClientIP(client, ip, sizeof(ip));
	
	WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: client=%d, steamid=%s, ip=%s, fingerprint=%s", client, steamid, ip, fingerprint);
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Associations, "ProcessReceivedFingerprint: Database not available, skipping all actions");
		return;
	}
	
	char issuedFingerprint[128];
	FastDL_GetIssuedFingerprint(client, issuedFingerprint, sizeof(issuedFingerprint));
	
	WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Issued fingerprint: '%s'", issuedFingerprint);
	
	if(issuedFingerprint[0]) {
		if(StrEqual(fingerprint, issuedFingerprint, false)) {
			WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Fingerprint matches issued fingerprint, checking if known client");
			
			char knownFingerprint[128];
			bool found = Fingerprint_GetForClient(client, steamid, ip, knownFingerprint, sizeof(knownFingerprint));
			
			WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Known fingerprint lookup result: found=%d, fingerprint=%s", found, knownFingerprint);
			
			if(found) {
				HandleKnownClient(client, steamid, ip, knownFingerprint);
			} else {
				HandleUnknownClient(client, steamid, ip, fingerprint);
			}
		} else {
			WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Fingerprint mismatch! Issued: %s, Received: %s", issuedFingerprint, fingerprint);
			if(Fingerprint_Exists(fingerprint)) {
				WriteLogFormatted(LogLevel_Associations, "Client %d returned different fingerprint than issued. Issued: %s, Received: %s. Fingerprint exists in DB - treating as KNOWN.", client, issuedFingerprint, fingerprint);
				HandleKnownClient(client, steamid, ip, fingerprint);
			} else {
			WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Fingerprint not found in DB, checking rebanner_temp: %s", fingerprint);
			
			char tempSteamid[64], tempIP[64];
			if(Fingerprint_CheckTempFingerprintSync(fingerprint, tempSteamid, sizeof(tempSteamid), tempIP, sizeof(tempIP))) {
				WriteLogFormatted(LogLevel_Associations, "ProcessReceivedFingerprint: Found fingerprint in rebanner_temp: temp_steamid=%s, temp_ip=%s, current_steamid=%s, current_ip=%s", tempSteamid, tempIP, steamid, ip);
				Fingerprint_RestoreFromTemp(fingerprint, tempSteamid, tempIP);
				HandleKnownClient(client, steamid, ip, fingerprint);
			} else {
				WriteLogFormatted(LogLevel_Associations, "Fingerprint tampering detected! Client %d returned fingerprint %s but was issued %s. Fingerprint not found in DB or temp - KICKING.", client, fingerprint, issuedFingerprint);
				
				if(antiTamperAction.BoolValue) {
					KickClient(client, antitamperKickReason);
				}
			}
		}
		}
	} else {
		WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: No issued fingerprint via FastDL (legacy mode)");
		
		char knownFingerprint[128];
		bool found = Fingerprint_GetForClient(client, steamid, ip, knownFingerprint, sizeof(knownFingerprint));
		
		WriteLogFormatted(LogLevel_Extra, "ProcessReceivedFingerprint: Legacy mode - Known fingerprint lookup result: found=%d, fingerprint=%s", found, knownFingerprint);
		
		if(found) {
			HandleKnownClient(client, steamid, ip, knownFingerprint);
		} else {
			HandleUnknownClient(client, steamid, ip, fingerprint);
		}
	}
	
	g_bFingerprintProcessed[client] = true;
	FastDL_ClearIssuedFingerprint(client);
}

void HandleKnownClient(int client, const char[] steamid, const char[] ip, const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "HandleKnownClient: client=%d, steamid=%s, ip=%s, fingerprint=%s", client, steamid, ip, fingerprint);
	#pragma unused ip
	
	char fingerprintBySteam[128];
	bool foundBySteam = Fingerprint_GetForClient(0, steamid, "", fingerprintBySteam, sizeof(fingerprintBySteam));
	
	WriteLogFormatted(LogLevel_Extra, "HandleKnownClient: foundBySteam=%d (fp=%s)", foundBySteam, fingerprintBySteam);
	
	if(!foundBySteam && fingerprint[0]) {
		if(!Database_IsReady()) {
			WriteLogFormatted(LogLevel_Associations, "HandleKnownClient: Database not ready, skipping association");
			return;
		}
		
		if(Fingerprint_Exists(fingerprint)) {
			WriteLogFormatted(LogLevel_Associations, "HandleKnownClient: No SteamID association found, but fingerprint exists - creating association for fingerprint %s (twink detected)", fingerprint);
			Fingerprint_AssociateSteamID(steamid, fingerprint);
		} else {
			WriteLogFormatted(LogLevel_Associations, "HandleKnownClient: WARNING - Fingerprint %s does not exist in DB, but was passed to HandleKnownClient. Skipping association to prevent wrong linking.", fingerprint);
		}
	}
	
	Rb_ApplyExistingCommsForClient(client, fingerprint);
	Banning_CheckAndReban(client, fingerprint);
}

void HandleUnknownClient(int client, const char[] steamid, const char[] ip, const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: client=%d, steamid=%s, ip=%s, fingerprint=%s", client, steamid, ip, fingerprint);
	
	bool exists = Fingerprint_Exists(fingerprint);
	WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: Fingerprint exists check: %d", exists);
	
	if(exists) {
		WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: Fingerprint exists, re-associating. SteamID=%s, IP=%s", steamid, ip);
		
		Fingerprint_AssociateSteamID(steamid, fingerprint);
		
		Fingerprint_MarkTempFingerprintVerified(fingerprint);
		
		WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: Re-associated, applying comms and checking ban for fingerprint %s", fingerprint);
		Rb_ApplyExistingCommsForClient(client, fingerprint);
		Banning_CheckAndReban(client, fingerprint);
		return;
	}
	
	bool isTampered = Fingerprint_IsTampered(fingerprint, antiTamperMode);
	WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: Tampering check: isTampered=%d, antiTamperMode=%d", isTampered, antiTamperMode.IntValue);
	
	if(isTampered) {
		WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: Potential fingerprint tampering detected! Fingerprint=%s, antiTamperAction=%d", fingerprint, antiTamperAction.BoolValue);
		WriteLogFormatted(LogLevel_Associations, "Potential fingerprint tampering detected!");
		if(antiTamperAction.BoolValue) {
			WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: Kicking client %d for tampering", client);
			KickClient(client, antitamperKickReason);
		}
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "HandleUnknownClient: New client, creating record. Fingerprint=%s, SteamID=%s, IP=%s", fingerprint, steamid, ip);
	Fingerprint_CreateNewRecord(fingerprint, steamid, ip);
	Fingerprint_MarkTempFingerprintVerified(fingerprint);
	Rb_ApplyExistingCommsForClient(client, fingerprint);
	Banning_CheckAndReban(client, fingerprint);
}

public void WriteLog(const char[] message, LogLevel level)
{
	if(logLevel == null)
		return;
	
	LogLevel currentLogLevel = view_as<LogLevel>(logLevel.IntValue);
	if(currentLogLevel >= level) {
		BuildPath(Path_SM, logFilePath, PLATFORM_MAX_PATH, LOGFILE);
		File logFile = OpenFile(logFilePath, "a");
		if(logFile != null) {
			char timestamp[64];
			FormatTime(timestamp, sizeof(timestamp), "%Y-%m-%d %H:%M:%S");
			logFile.WriteLine("[%s] %s %s", timestamp, logLevelDefinitions[level], message);
			logFile.Close();
		}
	}
}

#if defined _materialadmin_included
public void MAOnClientBanned(int iClient, int iTarget, char[] sIp, char[] sSteamID, char[] sName, int iTime, char[] sReason)
{
	WriteLogFormatted(LogLevel_Extra, "MAOnClientBanned: client=%d, target=%d, ip=%s, steamid=%s, name=%s, time=%d, reason=%s", 
		iClient, iTarget, sIp, sSteamID, sName, iTime, sReason);
	
	if(IsValidClient(iTarget)) {
		WriteLogFormatted(LogLevel_Extra, "MAOnClientBanned: Processing ban event");
		Banning_ProcessBanEvent(iTarget, iTime);
	} else {
		WriteLogFormatted(LogLevel_Extra, "MAOnClientBanned: Target client is not valid, skipping");
	}
}

public void MAOnClientAddBanned(int iClient, char[] sIp, char[] sSteamID, int iTime, char[] sReason)
{
	WriteLogFormatted(LogLevel_Extra, "MAOnClientAddBanned: client=%d, ip=%s, steamid=%s, time=%d, reason=%s", 
		iClient, sIp, sSteamID, iTime, sReason);
	
	char fingerprint[128];
	if(Fingerprint_GetForClient(0, sSteamID, "", fingerprint, sizeof(fingerprint))) {
		WriteLogFormatted(LogLevel_Extra, "MAOnClientAddBanned: Found fingerprint %s for offline ban", fingerprint);
		Banning_MarkFingerprintBanned(fingerprint, iTime);
		WriteLogFormatted(LogLevel_Bans, "Processing offline ban event via Material Admin");
	} else {
		WriteLogFormatted(LogLevel_Extra, "MAOnClientAddBanned: No fingerprint found for steamid=%s, ip=%s", sSteamID, sIp);
	}
}

public void MAOnClientUnBanned(int iClient, char[] sIp, char[] sSteamID, char[] sReason)
{
	WriteLogFormatted(LogLevel_Extra, "MAOnClientUnBanned: client=%d, ip=%s, steamid=%s, reason=%s", 
		iClient, sIp, sSteamID, sReason);
	
	char fingerprint[128];
	
	if(sSteamID[0] && Fingerprint_GetForClient(0, sSteamID, "", fingerprint, sizeof(fingerprint))) {
		WriteLogFormatted(LogLevel_Extra, "MAOnClientUnBanned: Found fingerprint %s by SteamID %s", fingerprint, sSteamID);
		
		if(Fingerprint_IsBanned(fingerprint)) {
			WriteLogFormatted(LogLevel_Extra, "MAOnClientUnBanned: Fingerprint %s is banned, clearing ban", fingerprint);
			Banning_ClearBanForFingerprint(fingerprint);
			WriteLogFormatted(LogLevel_Bans, "Unban via MaterialAdmin: SteamID %s, fingerprint %s", sSteamID, fingerprint);
		} else {
			WriteLogFormatted(LogLevel_Extra, "MAOnClientUnBanned: Fingerprint %s is not banned, skipping", fingerprint);
		}
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "MAOnClientUnBanned: No fingerprint found for steamid=%s", sSteamID);
}
#endif

public Action OnBanClient(int client, int time, int flags, const char[] reason, const char[] kick_message, const char[] command, any source)
{
	char steamid[64];
	if(IsValidClient(client))
		GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));
	WriteLogFormatted(LogLevel_Extra, "OnBanClient: client=%d, steamid=%s, time=%d, flags=%d, reason=%s, command=%s", client, steamid, time, flags, reason, command);
	
	Banning_ProcessBanEvent(client, time);
	return Plugin_Continue;
}

