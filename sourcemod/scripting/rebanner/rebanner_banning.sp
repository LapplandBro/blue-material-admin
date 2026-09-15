#include <sourcemod>
#include <materialadmin_check>
#include <materialadmin>

#pragma semicolon 1
#pragma newdecls required

ConVar rebanDuration;
char rebanReason[256];

void Banning_Init(){
}

void Banning_HandleNoBanInMA(const char[] fingerprint, const char[] functionName, const char[] reason = "")
{
	if(reason[0]) {
		WriteLogFormatted(LogLevel_Extra, "%s: %s, clearing local ban flags for fingerprint %s, NOT banning", 
			functionName, reason, fingerprint);
	} else {
		WriteLogFormatted(LogLevel_Extra, "%s: Ban is NOT valid in Material Admin (isBanned=false), clearing local ban flags for fingerprint %s, NOT banning", 
			functionName, fingerprint);
	}
	Banning_ClearBanForFingerprint(fingerprint);
}

void Banning_ProcessBanEvent(int client, int time)
{
	char steamid[64];
	GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));
	WriteLogFormatted(LogLevel_Extra, "Banning_ProcessBanEvent: client=%d, steamid=%s, time=%d", client, steamid, time);
	
	char fingerprint[128];
	if(Fingerprint_GetForClient(client, steamid, "", fingerprint, sizeof(fingerprint))) {
		WriteLogFormatted(LogLevel_Extra, "Banning_ProcessBanEvent: Found fingerprint %s, marking as banned", fingerprint);
		Banning_MarkFingerprintBanned(fingerprint, time);
	} else {
		WriteLogFormatted(LogLevel_Extra, "Banning_ProcessBanEvent: No fingerprint found for steamid %s", steamid);
	}
}

void Banning_MarkFingerprintBanned(const char[] fingerprint, int duration)
{
	WriteLogFormatted(LogLevel_Extra, "Banning_MarkFingerprintBanned: fingerprint=%s, duration=%d (minutes)", fingerprint, duration);
	
	if(Fingerprint_IsBanned(fingerprint)) {
		WriteLogFormatted(LogLevel_Extra, "Banning_MarkFingerprintBanned: Fingerprint already banned, skipping");
		return;
	}
	
	int durationInSeconds = (duration == 0) ? 0 : (duration * 60);
	WriteLogFormatted(LogLevel_Extra, "Banning_MarkFingerprintBanned: Converted duration: %d minutes -> %d seconds", duration, durationInSeconds);
	
	if(Database_IsReady()) {
		int currentTime = GetTime();
		char query[512];
		char escapedFingerprint[256];
		Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
		Format(query, sizeof(query), "UPDATE rebanner_fingerprints SET banned_duration = %i, banned_timestamp = %i, is_banned = 1 WHERE fingerprint = '%s'", durationInSeconds, currentTime, escapedFingerprint);
		WriteLogFormatted(LogLevel_Extra, "Banning_MarkFingerprintBanned: Executing query with duration=%d seconds, timestamp=%d", durationInSeconds, currentTime);
		DBResultSet results = Database_ExecuteQuery(query);
		if(results == null) {
			char error[255];
			Database_GetError(error, sizeof(error));
			WriteLogFormatted(LogLevel_Extra, "Banning_MarkFingerprintBanned: Query error: %s", error);
			LogError("Failed to update ban record: %s", error);
		} else {
			delete results;
		}
	} else {
		WriteLogFormatted(LogLevel_Associations, "Banning_MarkFingerprintBanned: Database not available, skipping");
	}
}

void Banning_CheckAndReban(int client, const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: client=%d, fingerprint=%s", client, fingerprint);
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Database not ready");
		return;
	}
	
	if(!ValidateClientForBan(client, "Banning_CheckAndReban")) {
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Checking ban status in database");
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	Format(query, sizeof(query), "SELECT is_banned FROM rebanner_fingerprints WHERE fingerprint = '%s'", escapedFingerprint);
	
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Database error: %s", error);
		LogError("Failed to check fingerprint ban status: %s", error);
		return;
	}
	
	if(results.FetchRow()) {
		bool isBanned = view_as<bool>(results.FetchInt(0));
		delete results;
		
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: isBanned=%d", isBanned);
		
		if(isBanned) {
			WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Fingerprint is marked as banned in DB, checking Material Admin before applying");
			
			if(LibraryExists("materialadmin_check") && MaterialAdmin_IsAvailable()) {
				WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Material Admin available, checking active ban before rebanning");
				char steamids[2048];
				Fingerprint_GetSteamIDs(fingerprint, steamids, sizeof(steamids));
				if(steamids[0]) {
					WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Found SteamIDs: %s", steamids);
					MaterialAdmin_CheckFingerprintBan(client, fingerprint, steamids, OnMACheckBeforeReban, 0);
					return;
				} else {
					WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: No SteamIDs found, performing reban directly");
					Banning_RebanClient(client, fingerprint);
				}
			} else {
				WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Material Admin not available, performing reban directly");
				Banning_RebanClient(client, fingerprint);
			}
		} else {
			if(LibraryExists("materialadmin_check") && MaterialAdmin_IsAvailable()) {
				WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Fingerprint not banned in DB, checking Material Admin for sync");
				char steamids[2048];
				Fingerprint_GetSteamIDs(fingerprint, steamids, sizeof(steamids));
				if(steamids[0]) {
					WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Found SteamIDs: %s", steamids);
					MaterialAdmin_CheckFingerprintBan(client, fingerprint, steamids, OnMACheckComplete, 0);
					return;
				} else {
					WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: No SteamIDs found for fingerprint, no action needed");
				}
			} else {
				WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Fingerprint not banned in DB and Material Admin not available, no action needed");
			}
		}
	} else {
		delete results;
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: No record found in database, checking Material Admin");
		if(LibraryExists("materialadmin_check") && MaterialAdmin_IsAvailable()) {
			char steamids[2048];
			Fingerprint_GetSteamIDs(fingerprint, steamids, sizeof(steamids));
			if(steamids[0]) {
				WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: Found SteamIDs for MA check: %s", steamids);
				MaterialAdmin_CheckFingerprintBan(client, fingerprint, steamids, OnMACheckComplete, 0);
				return;
			} else {
				WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: No SteamIDs found for MA check, no action needed");
			}
		} else {
			WriteLogFormatted(LogLevel_Extra, "Banning_CheckAndReban: No record in DB and Material Admin not available, no action needed");
		}
	}
}

public void OnMACheckComplete(int client, const char[] fingerprint, bool isBanned, int banLength, int banEnds, any data)
{
	WriteLogFormatted(LogLevel_Extra, "OnMACheckComplete: client=%d, fingerprint=%s, isBanned=%d, banLength=%d, banEnds=%d", 
		client, fingerprint, isBanned, banLength, banEnds);
	
	if(!ValidateClientForBan(client, "OnMACheckComplete")) {
		return;
	}
	
	if(isBanned) {
		int derivedTimestamp = (banLength == 0) ? GetTime() : (banEnds - banLength * 60);
		WriteLogFormatted(LogLevel_Extra, "OnMACheckComplete: Client is banned, banLength=%d minutes, banEnds=%d, derivedTimestamp=%d", 
			banLength, banEnds, derivedTimestamp);
		
		Banning_MarkFingerprintBanned(fingerprint, banLength);
		
		int durationInSeconds = (banLength == 0) ? 0 : (banLength * 60);
		Banning_PerformReban(client, fingerprint, durationInSeconds, derivedTimestamp);
	} else {
		Banning_HandleNoBanInMA(fingerprint, "OnMACheckComplete");
	}
}

public void OnMACheckBeforeReban(int client, const char[] fingerprint, bool isBanned, int banLength, int banEnds, any data)
{
	WriteLogFormatted(LogLevel_Extra, "OnMACheckBeforeReban: client=%d, fingerprint=%s, isBanned=%d, banLength=%d, banEnds=%d", 
		client, fingerprint, isBanned, banLength, banEnds);
	
	if(!ValidateClientForBan(client, "OnMACheckBeforeReban")) {
		return;
	}
	
	if(isBanned) {
		WriteLogFormatted(LogLevel_Extra, "OnMACheckBeforeReban: Ban is valid in Material Admin, performing reban");
		Banning_RebanClient(client, fingerprint);
	} else {
		Banning_HandleNoBanInMA(fingerprint, "OnMACheckBeforeReban");
	}
}

void Banning_RebanClient(int client, const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: client=%d, fingerprint=%s", client, fingerprint);
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: Database not ready");
		return;
	}
	
	if(!ValidateClientForBan(client, "Banning_RebanClient")) {
		return;
	}
	
	char query[512];
	char escapedFingerprint[256];
	
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	Format(query, sizeof(query), "SELECT banned_duration, banned_timestamp FROM rebanner_fingerprints WHERE fingerprint = '%s'", escapedFingerprint);
	
	WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: Querying ban data from database");
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: Database error: %s", error);
		LogError("Failed to query banned fingerprint data: %s", error);
		return;
	}
	
	if(results.FetchRow()) {
		int duration = results.FetchInt(0);
		int banned_timestamp = results.FetchInt(1);
		delete results;
		
		WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: duration=%d (seconds), banned_timestamp=%d, reason=%s", 
			duration, banned_timestamp, rebanReason);
		
		bool isPermanent = (duration == 0);
		int currentTime = GetTime();
		int timeDiff = currentTime - banned_timestamp;
		bool isExpired = (!isPermanent && timeDiff >= duration);
		
		WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: isPermanent=%d, currentTime=%d, timeDiff=%d, isExpired=%d", 
			isPermanent, currentTime, timeDiff, isExpired);
		
		if(isExpired) {
			WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: Ban expired, clearing ban for fingerprint %s", fingerprint);
			Banning_ClearBanForFingerprint(fingerprint);
			return;
		}
		
		char steamid[64];
		GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));
		
		if(LibraryExists("materialadmin_check") && MaterialAdmin_IsAvailable()) {
			WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: Material Admin available, checking active ban before applying");
			Banning_CheckMAActiveBanThenApply(client, fingerprint, steamid, rebanReason, duration, banned_timestamp);
		} else {
			WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: Material Admin not available, performing reban directly");
			Banning_PerformReban(client, fingerprint, duration, banned_timestamp);
		}
	} else {
		delete results;
		WriteLogFormatted(LogLevel_Extra, "Banning_RebanClient: No results from query");
	}
}

void Banning_CheckMAActiveBanThenApply(int client, const char[] fingerprint, const char[] steamid, const char[] reason, int duration, int banned_timestamp)
{
	// Защита от late-load: если плагин ещё не инициализирован — fallback на прямой ребан
	if (!Rebanner_IsInitialized())
	{
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckMAActiveBanThenApply: Plugin not initialized yet, performing direct reban");
		Banning_PerformReban(client, fingerprint, duration, banned_timestamp);
		return;
	}

	Database maDB = MaterialAdmin_GetDatabase();
	if(maDB == null) {
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckMAActiveBanThenApply: Material Admin database not available, performing reban directly");
		Banning_PerformReban(client, fingerprint, duration, banned_timestamp);
		return;
	}
	
	char tableName[64];
	MaterialAdmin_GetBansTable(tableName, sizeof(tableName));
	if(!tableName[0])
		strcopy(tableName, sizeof(tableName), "sb_bans");
	
	char steamids[2048];
	Fingerprint_GetSteamIDs(fingerprint, steamids, sizeof(steamids));
	
	char checkQuery[8192];
	if(steamids[0]) {
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckMAActiveBanThenApply: Checking all SteamIDs for fingerprint: %s", steamids);
		
		char inList[4096];
		if(BuildSQLInListFromSteamIDs(maDB, steamids, inList, sizeof(inList))) {
			Format(checkQuery, sizeof(checkQuery),
				"SELECT bid FROM %s WHERE authid IN (%s) AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) LIMIT 1",
				tableName, inList);
		} else {
			char steamidPart[64];
			strcopy(steamidPart, sizeof(steamidPart), steamid);
			if(StrContains(steamidPart, "STEAM_") == 0) {
				strcopy(steamidPart, sizeof(steamidPart), steamid[8]);
			}
			char escapedSteamID[128];
			SQL_EscapeString(maDB, steamidPart, escapedSteamID, sizeof(escapedSteamID));
			Format(checkQuery, sizeof(checkQuery), 
				"SELECT bid FROM %s WHERE authid REGEXP '^STEAM_[0-9]:%s$' AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) LIMIT 1",
				tableName, escapedSteamID);
		}
	} else {
		WriteLogFormatted(LogLevel_Extra, "Banning_CheckMAActiveBanThenApply: No associated SteamIDs found, using single SteamID: %s", steamid);
		
		char steamidPart[64];
		ExtractSteamIDPart(steamid, steamidPart, sizeof(steamidPart));
		char escapedSteamID[128];
		SQL_EscapeString(maDB, steamidPart, escapedSteamID, sizeof(escapedSteamID));
		Format(checkQuery, sizeof(checkQuery), 
			"SELECT bid FROM %s WHERE authid REGEXP '^STEAM_[0-9]:%s$' AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) LIMIT 1",
			tableName, escapedSteamID);
	}
	
	WriteLogFormatted(LogLevel_Extra, "Banning_CheckMAActiveBanThenApply: Executing query: %s", checkQuery);
	
	DataPack checkPack = new DataPack();
	checkPack.WriteCell(client);
	checkPack.WriteString(fingerprint);
	checkPack.WriteString(steamid);
	checkPack.WriteString(reason);
	checkPack.WriteCell(duration);
	checkPack.WriteCell(banned_timestamp);
	
	maDB.Query(CheckMAActiveBan_Callback, checkQuery, checkPack);
}

public void CheckMAActiveBan_Callback(Database maDB, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int client = pack.ReadCell();
	char fingerprint[128], steamid[64], reason[256];
	pack.ReadString(fingerprint, sizeof(fingerprint));
	pack.ReadString(steamid, sizeof(steamid));
	pack.ReadString(reason, sizeof(reason));
	int duration = pack.ReadCell();
	int banned_timestamp = pack.ReadCell();
	delete pack;
	
	if(!ValidateClientForBan(client, "CheckMAActiveBan_Callback")) {
		return;
	}
	
	if(error[0]) {
		char errorMsg[512];
		Format(errorMsg, sizeof(errorMsg), "Database error: %s, clearing local ban flags (NOT banning to avoid false positives)", error);
		Banning_HandleNoBanInMA(fingerprint, "CheckMAActiveBan_Callback", errorMsg);
		return;
	}
	
	if(results.FetchRow()) {
		WriteLogFormatted(LogLevel_Extra, "CheckMAActiveBan_Callback: Active ban found in Material Admin, performing reban");
		Banning_PerformReban(client, fingerprint, duration, banned_timestamp);
		return;
	}
	
	Banning_HandleNoBanInMA(fingerprint, "CheckMAActiveBan_Callback", "No active ban in Material Admin (NULL/0/not found)");
}

void Banning_PerformReban(int client, const char[] fingerprint, int duration, int banned_timestamp)
{
	WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: client=%d, fingerprint=%s, duration=%d (seconds), banned_timestamp=%d", 
		client, fingerprint, duration, banned_timestamp);
	
	if(!fingerprint[0]) {
		WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: Empty fingerprint, aborting");
		return;
	}
	
	char steamid[64];
	GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));
	
	int currentTime = GetTime();
	int banDurationInSeconds = rebanDuration.BoolValue ? duration : (duration - (currentTime - banned_timestamp));
	if(banDurationInSeconds < 0)
		banDurationInSeconds = 0;
	
	int banDurationInMinutes = (banDurationInSeconds == 0) ? 0 : ((banDurationInSeconds + 59) / 60);
	
	WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: currentTime=%d, banDurationInSeconds=%d, banDurationInMinutes=%d (rebanDuration.BoolValue=%d)", 
		currentTime, banDurationInSeconds, banDurationInMinutes, rebanDuration.BoolValue);
	
	if(LibraryExists("materialadmin") && MaterialAdmin_IsAvailable()) {
		WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: Using Material Admin to ban");
		char maReason[256];
		strcopy(maReason, sizeof(maReason), rebanReason);
		if(MABanPlayer(0, client, MA_BAN_STEAM, banDurationInMinutes, maReason)) {
			WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: Material Admin ban successful (duration=%d minutes)", banDurationInMinutes);
			return;
		} else {
			WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: Material Admin ban failed, falling back to BanClient");
		}
	}
	
	WriteLogFormatted(LogLevel_Extra, "Banning_PerformReban: Using BanClient (duration=%d minutes, reason=%s)", banDurationInMinutes, rebanReason);
	BanClient(client, banDurationInMinutes, BANFLAG_AUTO, rebanReason, rebanReason, "rebanner", client);
}

void Banning_ClearBanForFingerprint(const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Banning_ClearBanForFingerprint: fingerprint=%s", fingerprint);
	
	if(Database_IsReady()) {
		char query[512];
		char escapedFingerprint[256];
		Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
		Format(query, sizeof(query), "UPDATE rebanner_fingerprints SET is_banned = 0, banned_duration = 0, banned_timestamp = 0 WHERE fingerprint = '%s'", escapedFingerprint);
		WriteLogFormatted(LogLevel_Extra, "Banning_ClearBanForFingerprint: Executing query to clear ban");
		DBResultSet results = Database_ExecuteQuery(query);
		if(results == null) {
			char error[255];
			Database_GetError(error, sizeof(error));
			WriteLogFormatted(LogLevel_Extra, "Banning_ClearBanForFingerprint: Query error: %s", error);
			LogError("Failed to update ban record in database: %s", error);
		} else {
			delete results;
		}
	} else {
		WriteLogFormatted(LogLevel_Associations, "Banning_ClearBanForFingerprint: Database not available, skipping");
	}
}

bool Banning_TryUnbanBySteamID(const char[] steamid)
{
	char fingerprint[128];
	if(!Fingerprint_GetForClient(0, steamid, "", fingerprint, sizeof(fingerprint)))
		return false;
	
	if(!Fingerprint_IsBanned(fingerprint))
		return false;
	
	Banning_ClearBanForFingerprint(fingerprint);
	return true;
}

void Banning_SetRebanDuration(ConVar cv)
{
	rebanDuration = cv;
}

void Banning_SetRebanReason(const char[] reason)
{
	strcopy(rebanReason, sizeof(rebanReason), reason);
}
