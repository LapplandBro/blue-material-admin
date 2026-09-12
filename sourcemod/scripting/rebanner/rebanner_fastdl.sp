#include <sourcemod>
#include <dhooks>

#pragma semicolon 1
#pragma newdecls required

#define FASTDL_BLOCK_TIME 0.1
#define QUEUE_KICK_REASON "Disconnect: Buffer overflow in net message"

Handle hPlayerSlot = INVALID_HANDLE;
GameData gamedatafile;

enum OSType { OS_Linux = 0, OS_Windows, OS_Unknown }
OSType os = OS_Unknown;

int currentConnectingClient = 0;
float connectionBlockUntil[MAXPLAYERS + 1];
Handle connectionBlockTimer[MAXPLAYERS + 1] = {INVALID_HANDLE, ...};
Handle queueReleaseTimer = INVALID_HANDLE;
ConVar svDownloadUrl;
char defaultDownloadUrlConvar[512];
char savedDownloadUrl[512];
char g_FastDL_FingerprintPath[PLATFORM_MAX_PATH];

int modifyConVarCurrentClient = 0;

bool g_MassConnectionMode = false;
Handle g_MassConnectionTimer = INVALID_HANDLE;

char fastDLIssuedFingerprint[MAXPLAYERS + 1][128];

void FastDL_Init()
{
	WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Initializing FastDL module");
	
	if(gamedatafile != null) {
		delete gamedatafile;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Cleaned up leftover gamedatafile");
	}
	if(hPlayerSlot != INVALID_HANDLE) {
		delete hPlayerSlot;
		hPlayerSlot = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Cleaned up leftover hPlayerSlot");
	}
	
	if(queueReleaseTimer != INVALID_HANDLE) {
		KillTimer(queueReleaseTimer);
		queueReleaseTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Cleaned up leftover queueReleaseTimer");
	}
	if(g_MassConnectionTimer != INVALID_HANDLE) {
		KillTimer(g_MassConnectionTimer);
		g_MassConnectionTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Cleaned up leftover g_MassConnectionTimer");
	}
	
	int timersKilled = 0;
	for(int i = 1; i <= MaxClients; i++) {
		if(connectionBlockTimer[i] != INVALID_HANDLE) {
			KillTimer(connectionBlockTimer[i]);
			connectionBlockTimer[i] = INVALID_HANDLE;
			timersKilled++;
		}
		connectionBlockUntil[i] = 0.0;
		fastDLIssuedFingerprint[i][0] = '\0';
	}
	if(timersKilled > 0) {
		WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Cleaned up %d leftover connectionBlockTimer timers", timersKilled);
	}
	
	currentConnectingClient = 0;
	g_MassConnectionMode = false;
	
	CheckOS();
	
	gamedatafile = LoadGameConfigFile("rebanner.games");
	if(gamedatafile == null)
		SetFailState("Cannot load rebanner.games.txt! Make sure you have it installed!");
	
	Handle detourSendServerInfo = DHookCreateDetour(Address_Null, CallConv_THISCALL, ReturnType_Bool, ThisPointer_Address);
	if(detourSendServerInfo == null)
		SetFailState("Failed to create detour for CBaseClient::SendServerInfo!");
	
	if(!DHookSetFromConf(detourSendServerInfo, gamedatafile, SDKConf_Signature, "CBaseClient::SendServerInfo"))
		SetFailState("Failed to load CBaseClient::SendServerInfo signature from gamedata!");
	
	if(!DHookEnableDetour(detourSendServerInfo, false, sendServerInfoDetCallback_Pre))
		SetFailState("Failed to detour CBaseClient::SendServerInfo PreHook!");
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Enabled SendServerInfo detour");
	
	Handle detourBuildConVarMessage = DHookCreateDetour(Address_Null, CallConv_CDECL, ReturnType_Void, ThisPointer_Ignore);
	if(detourBuildConVarMessage == null)
		SetFailState("Failed to create detour for Host_BuildConVarUpdateMessage!");
	
	if(!DHookSetFromConf(detourBuildConVarMessage, gamedatafile, SDKConf_Signature, "Host_BuildConVarUpdateMessage"))
		SetFailState("Failed to load Host_BuildConVarUpdateMessage signature from gamedata!");
	
	DHookAddParam(detourBuildConVarMessage, HookParamType_Int);
	DHookAddParam(detourBuildConVarMessage, HookParamType_Bool);
	
	if(!DHookEnableDetour(detourBuildConVarMessage, false, buildConVarMessageDetCallback_Pre))
		SetFailState("Failed to detour Host_BuildConVarUpdateMessage PreHook!");
	
	if(!DHookEnableDetour(detourBuildConVarMessage, true, buildConVarMessageDetCallback_Post))
		SetFailState("Failed to detour Host_BuildConVarUpdateMessage PostHook!");
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Enabled BuildConVarMessage detour (pre and post)");
	
	StartPrepSDKCall(SDKCall_Raw);
	PrepSDKCall_SetFromConf(gamedatafile, SDKConf_Virtual, "CBaseClient::GetPlayerSlot");
	PrepSDKCall_SetReturnInfo(SDKType_PlainOldData, SDKPass_Plain);
	hPlayerSlot = EndPrepSDKCall();
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_Init: FastDL module initialized successfully");
}

void CheckOS() {
	char cmdline[256];
	GetCommandLine(cmdline, sizeof(cmdline));
	
	if(StrContains(cmdline, "./srcds_linux ", false) != -1)
		os = OS_Linux;
	else if(StrContains(cmdline, ".exe", false) != -1)
		os = OS_Windows;
	else
		os = OS_Unknown;
		
	if (os == OS_Windows) WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Detected OS: Windows");
	else if (os == OS_Linux) WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Detected OS: Linux");
	else WriteLogFormatted(LogLevel_Extra, "FastDL_Init: Detected OS: Unknown");
}

void FastDL_OnMapStart()
{
	WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapStart: Starting FastDL map initialization");

	svDownloadUrl = FindConVar("sv_downloadurl");
	if(svDownloadUrl != null)
	{
		GetConVarString(svDownloadUrl, defaultDownloadUrlConvar, sizeof(defaultDownloadUrlConvar));
		strcopy(savedDownloadUrl, sizeof(savedDownloadUrl), defaultDownloadUrlConvar);
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapStart: Found sv_downloadurl: %s", savedDownloadUrl);
	} else {
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapStart: sv_downloadurl not found!");
	}
	
	g_MassConnectionMode = true;
	WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapStart: Mass connection mode enabled for 30 seconds");
	
	if(g_MassConnectionTimer != INVALID_HANDLE)
	{
		KillTimer(g_MassConnectionTimer);
		g_MassConnectionTimer = INVALID_HANDLE;
	}
	
	g_MassConnectionTimer = CreateTimer(30.0, Timer_DisableMassConnectionMode);
}

void FastDL_SetFingerprintPath(const char[] path)
{
	strcopy(g_FastDL_FingerprintPath, sizeof(g_FastDL_FingerprintPath), path);
	WriteLogFormatted(LogLevel_Extra, "FastDL_SetFingerprintPath: Set to %s", g_FastDL_FingerprintPath);
}

void FastDL_OnMapEnd()
{
	WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapEnd: Ending map, cleaning up FastDL state");
	
	int oldClient = currentConnectingClient;
	currentConnectingClient = 0;
	
	if(oldClient != 0) {
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapEnd: Cleared currentConnectingClient (was %d)", oldClient);
	}
	
	if(queueReleaseTimer != INVALID_HANDLE)
	{
		KillTimer(queueReleaseTimer);
		queueReleaseTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapEnd: Killed queue release timer");
	}
	
	if(g_MassConnectionTimer != INVALID_HANDLE)
	{
		KillTimer(g_MassConnectionTimer);
		g_MassConnectionTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapEnd: Killed mass connection mode timer");
	}
	
	g_MassConnectionMode = false;
	
	int timersKilled = 0;
	for(int i = 1; i <= MaxClients; i++)
	{
		if(connectionBlockTimer[i] != INVALID_HANDLE)
		{
			KillTimer(connectionBlockTimer[i]);
			connectionBlockTimer[i] = INVALID_HANDLE;
			timersKilled++;
		}
		connectionBlockUntil[i] = 0.0;
	}
	
	if(timersKilled > 0) {
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapEnd: Killed %d connection block timers", timersKilled);
	}
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_OnMapEnd: Resetting download URL");
	ResetDownloadUrl();
}

void FastDL_Cleanup()
{
	WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: Cleaning up FastDL resources");
	
	if(gamedatafile != null) {
		delete gamedatafile;
		gamedatafile = null;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: Deleted gamedatafile");
	}
	
	if(hPlayerSlot != INVALID_HANDLE) {
		delete hPlayerSlot;
		hPlayerSlot = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: Deleted hPlayerSlot");
	}
	
	if(queueReleaseTimer != INVALID_HANDLE) {
		KillTimer(queueReleaseTimer);
		queueReleaseTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: Killed queue release timer");
	}
	
	if(g_MassConnectionTimer != INVALID_HANDLE) {
		KillTimer(g_MassConnectionTimer);
		g_MassConnectionTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: Killed mass connection mode timer");
	}
	
	int timersKilled = 0;
	for(int i = 1; i <= MaxClients; i++) {
		if(connectionBlockTimer[i] != INVALID_HANDLE) {
			KillTimer(connectionBlockTimer[i]);
			connectionBlockTimer[i] = INVALID_HANDLE;
			timersKilled++;
		}
		connectionBlockUntil[i] = 0.0;
		fastDLIssuedFingerprint[i][0] = '\0';
	}
	
	if(timersKilled > 0) {
		WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: Killed %d connection block timers", timersKilled);
	}
	
	currentConnectingClient = 0;
	g_MassConnectionMode = false;
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_Cleanup: FastDL cleanup completed");
}

void FastDL_OnClientDisconnect(int client)
{
	WriteLogFormatted(LogLevel_Extra, "FastDL_OnClientDisconnect: client=%d, currentConnectingClient=%d", client, currentConnectingClient);
	
	if(currentConnectingClient == client)
	{
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnClientDisconnect: Client %d was connecting, releasing queue", client);
		LogMessage("[FastDL Queue] Client %d disconnected during connection process, releasing queue", client);
		FastDL_ReleaseQueue();
	} else {
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnClientDisconnect: Client %d was NOT connecting (currentConnectingClient=%d), only cleaning up timers", 
			client, currentConnectingClient);
	}
	
	if(connectionBlockTimer[client] != INVALID_HANDLE)
	{
		KillTimer(connectionBlockTimer[client]);
		connectionBlockTimer[client] = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnClientDisconnect: Killed connection block timer");
	}
	connectionBlockUntil[client] = 0.0;
	
	char issuedFp[128];
	FastDL_GetIssuedFingerprint(client, issuedFp, sizeof(issuedFp));
	if(issuedFp[0]) {
		WriteLogFormatted(LogLevel_Extra, "FastDL_OnClientDisconnect: Clearing issued fingerprint: %s", issuedFp);
	}
	FastDL_ClearIssuedFingerprint(client);
}

bool FastDL_CheckConnection(int client)
{
	float currentTime = GetEngineTime();
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: client=%d, currentTime=%.2f, currentConnectingClient=%d, massMode=%d", 
		client, currentTime, currentConnectingClient, g_MassConnectionMode);
	
	if(g_MassConnectionMode)
	{
		WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: Mass connection mode active - allowing client %d without queue blocking", client);
		return true;
	}
	
	if(connectionBlockUntil[client] > currentTime)
	{
		float blockTime = connectionBlockUntil[client] - currentTime;
		WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: Client %d still in cooldown (%.2f seconds remaining)", client, blockTime);
		ResetDownloadUrl();
		KickClient(client, QUEUE_KICK_REASON);
		LogMessage("[FastDL Queue] Blocked connection for client %d - still in cooldown", client);
		return false;
	}
	
	if(currentConnectingClient != 0 && currentConnectingClient != client)
	{
		WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: Queue busy (processing client %d), kicking client %d", currentConnectingClient, client);
		
		float timerSetTime = GetEngineTime();
		connectionBlockUntil[client] = currentTime + FASTDL_BLOCK_TIME;
		connectionBlockTimer[client] = CreateTimer(FASTDL_BLOCK_TIME, Timer_UnblockConnection, client);
		WriteLogFormatted(LogLevel_Associations, "[PROFILING] FastDL_CheckConnection: Timer set for client %d, time=%.6f, duration=%.1f", 
			client, timerSetTime, FASTDL_BLOCK_TIME);
		
		KickClient(client, QUEUE_KICK_REASON);
		LogMessage("[FastDL Queue] Kicked client %d - queue is busy (processing client %d)", client, currentConnectingClient);
		return false;
	}
	
	currentConnectingClient = client;
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: Queue acquired by client %d", client);
	
	if(queueReleaseTimer != INVALID_HANDLE)
	{
		KillTimer(queueReleaseTimer);
		WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: Killed existing queue release timer");
	}
	queueReleaseTimer = CreateTimer(FASTDL_BLOCK_TIME, Timer_ReleaseQueue);
	
	float timerSetTime = GetEngineTime();
	connectionBlockUntil[client] = currentTime + FASTDL_BLOCK_TIME;
	connectionBlockTimer[client] = CreateTimer(FASTDL_BLOCK_TIME, Timer_UnblockConnection, client, TIMER_FLAG_NO_MAPCHANGE);
	
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] FastDL_CheckConnection: Timer set for client %d (queue), time=%.6f, duration=%.1f", 
		client, timerSetTime, FASTDL_BLOCK_TIME);
	WriteLogFormatted(LogLevel_Extra, "FastDL_CheckConnection: Set timers - queue locked for %.1f seconds", FASTDL_BLOCK_TIME);
	LogMessage("[FastDL Queue] Processing client %d - queue locked for %.1f seconds", client, FASTDL_BLOCK_TIME);
	
	return true;
}

public Action Timer_UnblockConnection(Handle timer, int client)
{
	float timerTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] Timer_UnblockConnection: FIRED client=%d, time=%.6f (timer was set for %.1f seconds)", 
		client, timerTime, FASTDL_BLOCK_TIME);
	
	if(IsValidClientIndex(client))
	{
		connectionBlockUntil[client] = 0.0;
		connectionBlockTimer[client] = INVALID_HANDLE;
	}

	return Plugin_Stop;
}

public Action Timer_ReleaseQueue(Handle timer)
{
	FastDL_ReleaseQueue();
	queueReleaseTimer = INVALID_HANDLE;
	return Plugin_Stop;
}

public Action Timer_DisableMassConnectionMode(Handle timer)
{
	g_MassConnectionMode = false;
	g_MassConnectionTimer = INVALID_HANDLE;
	WriteLogFormatted(LogLevel_Extra, "Timer_DisableMassConnectionMode: Mass connection mode disabled, returning to normal queue mode");
	LogMessage("[FastDL Queue] Mass connection mode disabled - queue blocking re-enabled");
	return Plugin_Stop;
}

void FastDL_ReleaseQueue()
{
	int oldClient = currentConnectingClient;
	
	if(oldClient != 0)
	{
		WriteLogFormatted(LogLevel_Extra, "FastDL_ReleaseQueue: Releasing queue (was processing client %d)", oldClient);
		LogMessage("[FastDL Queue] Releasing queue (was processing client %d)", oldClient);
		currentConnectingClient = 0;
	} else {
		WriteLogFormatted(LogLevel_Extra, "FastDL_ReleaseQueue: Queue already released");
	}
	
	if(queueReleaseTimer != INVALID_HANDLE)
	{
		KillTimer(queueReleaseTimer);
		queueReleaseTimer = INVALID_HANDLE;
		WriteLogFormatted(LogLevel_Extra, "FastDL_ReleaseQueue: Killed queue release timer");
	}
	
	WriteLogFormatted(LogLevel_Extra, "FastDL_ReleaseQueue: Resetting download URL");
	ResetDownloadUrl();
}

public MRESReturn sendServerInfoDetCallback_Pre(Address pointer, Handle hReturn, Handle hParams)
{
	Address adjustedPointer = (os == OS_Windows) ? (pointer + view_as<Address>(0x4)) : pointer;
	
	modifyConVarCurrentClient = view_as<int>(SDKCall(hPlayerSlot, adjustedPointer)) + 1;
	return MRES_Ignored;
}

public MRESReturn buildConVarMessageDetCallback_Pre(Handle hParams)
{
	int client = modifyConVarCurrentClient;
	float startTime = GetEngineTime();

	WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Pre: START client=%d, time=%.6f", client, startTime);
	
	if(!IsValidClientIndex(client)) {
		float endTime = GetEngineTime();
		WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Pre: END (invalid client) client=%d, time=%.6f, total_duration=%.6f", 
			client, endTime, endTime - startTime);
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Invalid client index %d, ignoring", client);
		return MRES_Ignored;
	}
	
	if(!FastDL_CheckConnection(client))
	{
		float endTime = GetEngineTime();
		WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Pre: END (check failed) client=%d, time=%.6f, total_duration=%.6f", 
			client, endTime, endTime - startTime);
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: FastDL_CheckConnection failed for client %d", client);
		return MRES_Ignored;
	}
	
	char steamid2[64];
	GetClientAuthId(client, AuthId_Steam2, steamid2, sizeof(steamid2), false);
	GetConVarString(svDownloadUrl, savedDownloadUrl, sizeof(savedDownloadUrl));
	strcopy(defaultDownloadUrlConvar, sizeof(defaultDownloadUrlConvar), savedDownloadUrl);
	
	WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: client=%d, steamid=%s, savedDownloadUrl=%s", 
		client, steamid2, savedDownloadUrl);
	
	char fingerprint[128];
	char fingerprintBySteam[128];
	bool foundBySteam = Fingerprint_GetForClient(client, steamid2, "", fingerprintBySteam, sizeof(fingerprintBySteam));
	
	WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: foundBySteam=%d (fp=%s)", 
		foundBySteam, fingerprintBySteam);
	
	if(foundBySteam) {
		strcopy(fingerprint, sizeof(fingerprint), fingerprintBySteam);
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Using fingerprint by SteamID: %s", fingerprint);
	} else {
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Generating new fingerprint for client");
		Fingerprint_GenerateNewForClient(client, steamid2, fingerprint, sizeof(fingerprint));
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Generated new fingerprint: %s", fingerprint);
		Fingerprint_SaveTempFingerprint(fingerprint, steamid2);
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Saved temp fingerprint to rebanner_temp");
	}
	
	if(Fingerprint_IsBanned(fingerprint)) {
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Fingerprint is banned (will be rebanned in post-connection)");
	}
	
	float beforeUrlChange = GetEngineTime();
	bool urlUpdated = updateDownloadUrlConVarWithUniqueFingerprint(fingerprint);
	float afterUrlChange = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Pre: URL change for client %d, success=%d, time_before=%.6f, time_after=%.6f, duration=%.6f", 
		client, urlUpdated, beforeUrlChange, afterUrlChange, afterUrlChange - beforeUrlChange);
	
	if(urlUpdated) {
		FastDL_SetIssuedFingerprint(client, fingerprint);
		WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Pre: Set issued fingerprint for client %d: %s", client, fingerprint);
	} else {
		WriteLogFormatted(LogLevel_Associations, "buildConVarMessageDetCallback_Pre: Failed to issue fingerprint for client %d (file or URL update failed)", client);
	}
	
	float endTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Pre: END client=%d, time=%.6f, total_duration=%.6f", 
		client, endTime, endTime - startTime);
	
	return MRES_Ignored;
}

public MRESReturn buildConVarMessageDetCallback_Post(Handle hParams)
{
	float postStartTime = GetEngineTime();
	
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Post: START time=%.6f", postStartTime);

	WriteLogFormatted(LogLevel_Extra, "buildConVarMessageDetCallback_Post: Resetting download URL");
	float beforeReset = GetEngineTime();
	ResetDownloadUrl();
	float afterReset = GetEngineTime();
	
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Post: URL reset, time_before=%.6f, time_after=%.6f, duration=%.6f", 
		beforeReset, afterReset, afterReset - beforeReset);
	
	float postEndTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] buildConVarMessageDetCallback_Post: END time=%.6f, total_duration=%.6f", 
		postEndTime, postEndTime - postStartTime);
	
	return MRES_Ignored;
}

void ResetDownloadUrl()
{
	if(svDownloadUrl == null)
		return;
	
	float resetStartTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] ResetDownloadUrl: START time=%.6f", resetStartTime);
	
	int oldflags = GetConVarFlags(svDownloadUrl);
	SetConVarFlags(svDownloadUrl, oldflags &~ FCVAR_REPLICATED);
	
	float beforeSetConVar = GetEngineTime();
	SetConVarString(svDownloadUrl, savedDownloadUrl);
	float afterSetConVar = GetEngineTime();
	
	SetConVarFlags(svDownloadUrl, oldflags|FCVAR_REPLICATED);
	
	float resetEndTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] ResetDownloadUrl: SetConVarString time_before=%.6f, time_after=%.6f, duration=%.6f, total_duration=%.6f", 
		beforeSetConVar, afterSetConVar, afterSetConVar - beforeSetConVar, resetEndTime - resetStartTime);
}

bool updateDownloadUrlConVarWithUniqueFingerprint(const char[] fingerprint)
{
	float updateStartTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] updateDownloadUrlConVarWithUniqueFingerprint: START fingerprint=%s, time=%.6f", fingerprint, updateStartTime);

	if(svDownloadUrl == null) {
		WriteLogFormatted(LogLevel_Extra, "updateDownloadUrlConVarWithUniqueFingerprint: svDownloadUrl is null");
		return false;
	}

	char filepath[PLATFORM_MAX_PATH];
	Format(filepath, sizeof(filepath), "%s", g_FastDL_FingerprintPath);

	char dirPath[PLATFORM_MAX_PATH];
	strcopy(dirPath, sizeof(dirPath), filepath);
	int lastSlash = FindCharInString(dirPath, '/', true);
	if(lastSlash != -1) {
		dirPath[lastSlash] = '\0';
		CreateDirectory(dirPath, 511);
	}

	File fingerprintFile = OpenFile(filepath, "w+");
	if(fingerprintFile == null) {
		WriteLogFormatted(LogLevel_Extra, "updateDownloadUrlConVarWithUniqueFingerprint: Failed to create fingerprint file: %s", filepath);
		return false;
	}

	fingerprintFile.WriteString(fingerprint, false);
	fingerprintFile.Flush();
	fingerprintFile.Close();

	WriteLogFormatted(LogLevel_Extra, "updateDownloadUrlConVarWithUniqueFingerprint: Created fingerprint file: %s with content: %s", filepath, fingerprint);

	int oldflags = GetConVarFlags(svDownloadUrl);
	SetConVarFlags(svDownloadUrl, oldflags &~ FCVAR_REPLICATED);

	char newDownloadUrl[512];
	Format(newDownloadUrl, sizeof(newDownloadUrl), "%s/serve.php?id=%s&url=", defaultDownloadUrlConvar, fingerprint);
	
	float beforeSetConVar = GetEngineTime();
	SetConVarString(svDownloadUrl, newDownloadUrl);
	float afterSetConVar = GetEngineTime();
	
	SetConVarFlags(svDownloadUrl, oldflags|FCVAR_REPLICATED);

	float updateEndTime = GetEngineTime();
	WriteLogFormatted(LogLevel_Associations, "[PROFILING] updateDownloadUrlConVarWithUniqueFingerprint: SetConVarString time_before=%.6f, time_after=%.6f, duration=%.6f, total_duration=%.6f, url=%s", 
		beforeSetConVar, afterSetConVar, afterSetConVar - beforeSetConVar, updateEndTime - updateStartTime, newDownloadUrl);
	return true;
}

void FastDL_SetIssuedFingerprint(int client, const char[] fingerprint)
{
	if(IsValidClientIndex(client)) {
		strcopy(fastDLIssuedFingerprint[client], sizeof(fastDLIssuedFingerprint[]), fingerprint);
	}
}

void FastDL_GetIssuedFingerprint(int client, char[] buffer, int maxlen)
{
	if(IsValidClientIndex(client)) {
		strcopy(buffer, maxlen, fastDLIssuedFingerprint[client]);
	} else {
		buffer[0] = '\0';
	}
}

void FastDL_ClearIssuedFingerprint(int client)
{
	if(IsValidClientIndex(client)) {
		fastDLIssuedFingerprint[client][0] = '\0';
	}
}
