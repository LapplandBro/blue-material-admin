#include <sourcemod>
#include <regex>

#pragma semicolon 1
#pragma newdecls required

Regex regex;

void Fingerprint_Init()
{
	if(regex != null) {
		delete regex;
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_Init: Cleaned up leftover regex");
	}
	
	regex = new Regex("^[0-9a-fA-F]+$");
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_Init: Initialized regex pattern for fingerprint validation");
}

void Fingerprint_InitCache()
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_InitCache: Initialized (all functions use direct DB queries)");
}

bool Fingerprint_GetForClient(int client, const char[] steamid, const char[] ip, char[] fingerprint, int maxlen)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetForClient: client=%d, steamid=%s, ip=%s", client, steamid, ip);
	
	fingerprint[0] = '\0';
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetForClient: Database not ready");
		return false;
	}
	char actualSteamid[64];
	if(client > 0 && client <= MaxClients && IsClientInGame(client)) {
		if(steamid[0] == '\0')
			GetClientAuthId(client, AuthId_Steam2, actualSteamid, sizeof(actualSteamid));
		else
			strcopy(actualSteamid, sizeof(actualSteamid), steamid);
	} else {
		strcopy(actualSteamid, sizeof(actualSteamid), steamid);
	}
	#pragma unused ip
	if(actualSteamid[0]) {
		char query[512];
		char escapedSteamID[128];
		Database_EscapeString(actualSteamid, escapedSteamID, sizeof(escapedSteamID));
		
		Format(query, sizeof(query), "SELECT fingerprint FROM rebanner_lookup WHERE steamid2 = '%s' AND fingerprint != '' LIMIT 1", escapedSteamID);
		DBResultSet results = Database_ExecuteQuery(query);
		if(results != null) {
			if(results.FetchRow()) {
				results.FetchString(0, fingerprint, maxlen);
				delete results;
				WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetForClient: Found in DB by SteamID: %s -> %s", actualSteamid, fingerprint);
				return true;
			}
			delete results;
		} else {
			char error[255];
			Database_GetError(error, sizeof(error));
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetForClient: SteamID query error: %s", error);
		}
	}
	
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetForClient: Not found in DB");
	return false;
}

void Fingerprint_GenerateNewForClient(int client, const char[] steamid, char[] fingerprint, int maxlen)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_GenerateNewForClient: client=%d, steamid=%s", client, steamid);
	if(client < 0 || client > MaxClients) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_GenerateNewForClient: Invalid client index");
		return;
	}
	
	GenerateFingerprint(fingerprint, maxlen);
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_GenerateNewForClient: Generated new fingerprint: %s", fingerprint);
}

bool Fingerprint_AssociateSteamID(const char[] steamid, const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_AssociateSteamID: steamid=%s, fingerprint=%s", steamid, fingerprint);
	
	if(!steamid[0] || !fingerprint[0])
		return false;
	if(!IsValidSteamID(steamid, "Fingerprint_AssociateSteamID")) {
		return false;
	}
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Associations, "Fingerprint_AssociateSteamID: Database not available, skipping");
		return false;
	}
	char query[512];
	char escapedSteamID[128], escapedFingerprint[256];
	Database_EscapeString(steamid, escapedSteamID, sizeof(escapedSteamID));
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	char values[512];
	Format(values, sizeof(values), "'%s', '', '%s', 0", escapedSteamID, escapedFingerprint);
	FormatInsertOrUpdateLookupQuery(query, sizeof(query), values);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "Duplicate entry", false) == -1) {
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_AssociateSteamID: Query error: %s", error);
		}
	} else {
		delete results;
	}
	UpdateMainFingerprintRecordSync(fingerprint, steamid);
	
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_AssociateSteamID: Association saved to DB");
	return true;
}

void UpdateMainFingerprintRecordSync(const char[] fingerprint, const char[] steamid)
{
	if(!Database_IsReady() || !fingerprint[0]) {
		if(!Database_IsReady()) {
			WriteLogFormatted(LogLevel_Associations, "UpdateMainFingerprintRecordSync: Database not available, skipping");
		}
		return;
	}
	
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	if(steamid[0]) {
		char escapedSteamID[128];
		Database_EscapeString(steamid, escapedSteamID, sizeof(escapedSteamID));
		
		char updateQuery[1024];
		FormatConcatUpdateQuery(updateQuery, sizeof(updateQuery), "steamid2", escapedSteamID, escapedFingerprint);
		DBResultSet results = Database_ExecuteQuery(updateQuery);
		if(results == null) {
			char error[255];
			Database_GetError(error, sizeof(error));
			WriteLogFormatted(LogLevel_Extra, "UpdateMainFingerprintRecordSync: SteamID update error: %s", error);
		} else {
			delete results;
		}
	}
}

void Fingerprint_CreateNewRecord(const char[] fingerprint, const char[] steamid, const char[] ip)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_CreateNewRecord: fingerprint=%s, steamid=%s, ip=%s", fingerprint, steamid, ip);
	char validSteamid[64];
	if(steamid[0] && !IsValidSteamID(steamid, "Fingerprint_CreateNewRecord")) {
		validSteamid[0] = '\0';
	} else {
		strcopy(validSteamid, sizeof(validSteamid), steamid);
	}
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Associations, "Fingerprint_CreateNewRecord: Database not available, skipping");
		return;
	}
	
	char query[1024];
	char escapedFingerprint[256];
	
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	char values[512];
	Format(values, sizeof(values), "'%s', NULL, 0, 0, 0, NULL", escapedFingerprint);
	FormatInsertIgnoreQuery(query, sizeof(query), "rebanner_fingerprints", "fingerprint, steamid2, is_banned, banned_duration, banned_timestamp, ip", values);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "Duplicate entry", false) == -1) {
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_CreateNewRecord: Query error: %s", error);
		}
	} else {
		delete results;
	}
	
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_CreateNewRecord: Inserted main record, creating associations");
	if(validSteamid[0]) {
		Fingerprint_AssociateSteamID(validSteamid, fingerprint);
	}
	#pragma unused ip
}

bool Fingerprint_IsBanned(const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsBanned: fingerprint=%s", fingerprint);
	
	if(!fingerprint[0])
		return false;
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsBanned: Database not ready");
		return false;
	}
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	Format(query, sizeof(query), "SELECT is_banned FROM rebanner_fingerprints WHERE fingerprint = '%s' LIMIT 1", escapedFingerprint);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results != null) {
		if(results.FetchRow()) {
			int isBanned = results.FetchInt(0);
			delete results;
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsBanned: DB result: %d", isBanned);
			return (isBanned == 1);
		}
		delete results;
	} else {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsBanned: Query error: %s", error);
	}
	
	return false;
}

bool Fingerprint_Exists(const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_Exists: fingerprint=%s", fingerprint);
	
	if(!fingerprint[0])
		return false;
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_Exists: Database not ready");
		return false;
	}
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	Format(query, sizeof(query), "SELECT 1 FROM rebanner_fingerprints WHERE fingerprint = '%s' LIMIT 1", escapedFingerprint);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results != null) {
		bool exists = results.FetchRow();
		delete results;
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_Exists: DB result: %d", exists);
		return exists;
	} else {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_Exists: Query error: %s", error);
	}
	
	return false;
}

bool Fingerprint_IsTampered(const char[] fingerprint, ConVar antiTamperMode)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsTampered: fingerprint=%s, antiTamperMode=%d", fingerprint, antiTamperMode.IntValue);
	
	if(!antiTamperMode.IntValue) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsTampered: Anti-tamper mode disabled, returning false");
		return false;
	}
	
	int regexMatch = regex.Match(fingerprint);
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsTampered: Regex match result: %d", regexMatch);
	
	if(regexMatch == -1) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsTampered: Fingerprint does not match hex pattern, TAMPERED");
		return true;
	}
	
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_IsTampered: Fingerprint is valid, not tampered");
	return false;
}

void Fingerprint_GetSteamIDs(const char[] fingerprint, char[] buffer, int maxlen)
{
	buffer[0] = '\0';
	
	if(!fingerprint[0])
		return;
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetSteamIDs: Database not ready");
		return;
	}
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	Format(query, sizeof(query), "SELECT steamid2 FROM rebanner_fingerprints WHERE fingerprint = '%s' LIMIT 1", escapedFingerprint);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results != null) {
		if(results.FetchRow()) {
			results.FetchString(0, buffer, maxlen);
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetSteamIDs: Found in DB: %s -> %s", fingerprint, buffer);
		}
		delete results;
	} else {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetSteamIDs: Query error: %s", error);
	}
	
	if(buffer[0] == '\0') {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_GetSteamIDs: Not found in DB");
	}
}
int Fingerprint_GetTableSize()
{
	if(!Database_IsReady())
		return 0;
	
	char query[256];
	strcopy(query, sizeof(query), "SELECT COUNT(*) FROM rebanner_fingerprints");
	DBResultSet results = Database_ExecuteQuery(query);
	if(results != null && results.FetchRow()) {
		int count = results.FetchInt(0);
		delete results;
		return count;
	}
	if(results != null)
		delete results;
	return 0;
}

int Fingerprint_GetBannedCount()
{
	if(!Database_IsReady())
		return 0;
	
	char query[256];
	strcopy(query, sizeof(query), "SELECT COUNT(*) FROM rebanner_fingerprints WHERE is_banned = 1");
	DBResultSet results = Database_ExecuteQuery(query);
	if(results != null && results.FetchRow()) {
		int count = results.FetchInt(0);
		delete results;
		return count;
	}
	if(results != null)
		delete results;
	return 0;
}

int Fingerprint_GetSteamIDTableSize()
{
	if(!Database_IsReady())
		return 0;
	
	char query[256];
	strcopy(query, sizeof(query), "SELECT COUNT(*) FROM rebanner_lookup WHERE steamid2 != '' AND steamid2 IS NOT NULL");
	DBResultSet results = Database_ExecuteQuery(query);
	if(results != null && results.FetchRow()) {
		int count = results.FetchInt(0);
		delete results;
		return count;
	}
	if(results != null)
		delete results;
	return 0;
}

void Fingerprint_CleanupCache()
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_CleanupCache: Cleanup completed (all functions use direct DB queries)");
}
void Fingerprint_SaveTempFingerprint(const char[] fingerprint, const char[] steamid)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_SaveTempFingerprint: fingerprint=%s, steamid=%s", fingerprint, steamid);
	if(!IsValidSteamID(steamid, "Fingerprint_SaveTempFingerprint")) {
		return;
	}
	
	if(!fingerprint[0]) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_SaveTempFingerprint: Empty fingerprint, aborting");
		return;
	}
	
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Associations, "Fingerprint_SaveTempFingerprint: Database not available, skipping");
		return;
	}
	
	char query[512];
	char escapedFingerprint[256], escapedSteamID[128];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	Database_EscapeString(steamid, escapedSteamID, sizeof(escapedSteamID));
	
	char values[512];
	bool isSQLite = Database_IsSQLite();
	if(isSQLite) {
		Format(values, sizeof(values), "'%s', NULL, '%s', 0, 0", escapedSteamID, escapedFingerprint);
		Format(query, sizeof(query), "INSERT OR IGNORE INTO rebanner_temp (steamid2, ip, fingerprint, is_connected, is_verified) VALUES (%s)", values);
		DBResultSet results = Database_ExecuteQuery(query);
		if(results == null) {
			char error[255];
			Database_GetError(error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "Fingerprint_SaveTempFingerprint: SQLite INSERT error: %s", error);
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "UPDATE rebanner_temp SET steamid2 = '%s', ip = NULL WHERE fingerprint = '%s'", escapedSteamID, escapedFingerprint);
		results = Database_ExecuteQuery(query);
		if(results == null) {
			char error[255];
			Database_GetError(error, sizeof(error));
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_SaveTempFingerprint: SQLite UPDATE error: %s", error);
		} else {
			delete results;
		}
		return;
	}
	
	Format(values, sizeof(values), "'%s', NULL, '%s', 0, 0", escapedSteamID, escapedFingerprint);
	Format(query, sizeof(query), "INSERT INTO `rebanner_temp` (steamid2, ip, fingerprint, is_connected, is_verified) VALUES (%s) ON DUPLICATE KEY UPDATE steamid2 = VALUES(steamid2), ip = NULL", values);
	
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "Duplicate entry", false) == -1) {
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_SaveTempFingerprint: Query error: %s", error);
		}
	} else {
		delete results;
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_SaveTempFingerprint: Saved temp fingerprint to DB");
	}
}

void Fingerprint_MarkTempFingerprintConnected(const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_MarkTempFingerprintConnected: fingerprint=%s", fingerprint);
	
	if(!fingerprint[0] || !Database_IsReady()) {
		if(!Database_IsReady()) {
			WriteLogFormatted(LogLevel_Associations, "Fingerprint_MarkTempFingerprintConnected: Database not available, skipping");
		}
		return;
	}
	
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	Format(query, sizeof(query), "UPDATE rebanner_temp SET is_connected = 1 WHERE fingerprint = '%s'", escapedFingerprint);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_MarkTempFingerprintConnected: Query error: %s", error);
	} else {
		delete results;
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_MarkTempFingerprintConnected: Marked temp fingerprint as connected");
	}
}

void Fingerprint_MarkTempFingerprintVerified(const char[] fingerprint)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_MarkTempFingerprintVerified: fingerprint=%s", fingerprint);
	
	if(!fingerprint[0] || !Database_IsReady()) {
		if(!Database_IsReady()) {
			WriteLogFormatted(LogLevel_Associations, "Fingerprint_MarkTempFingerprintVerified: Database not available, skipping");
		}
		return;
	}
	
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	Format(query, sizeof(query), "UPDATE rebanner_temp SET is_verified = 1 WHERE fingerprint = '%s' AND is_connected = 1", escapedFingerprint);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_MarkTempFingerprintVerified: Query error: %s", error);
	} else {
		delete results;
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_MarkTempFingerprintVerified: Marked temp fingerprint as verified");
	}
}

void Fingerprint_RestoreFromTemp(const char[] fingerprint, const char[] steamid, const char[] ip)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_RestoreFromTemp: fingerprint=%s, steamid=%s, ip=%s", fingerprint, steamid, ip);
	
	if(!fingerprint[0] || !Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_RestoreFromTemp: Empty fingerprint or database not ready");
		return;
	}
	Fingerprint_CreateNewRecord(fingerprint, steamid, ip);
	Fingerprint_MarkTempFingerprintVerified(fingerprint);
	
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_RestoreFromTemp: Restored fingerprint from temp table");
}

bool Fingerprint_CheckTempFingerprintSync(const char[] fingerprint, char[] steamid, int steamidLen, char[] ip, int ipLen)
{
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: fingerprint=%s", fingerprint);
	
	if(!fingerprint[0] || !Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: Empty fingerprint or database not ready");
		return false;
	}
	
	char query[512];
	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));
	
	Format(query, sizeof(query), "SELECT steamid2, ip, is_connected, is_verified FROM rebanner_temp WHERE fingerprint = '%s' LIMIT 1", escapedFingerprint);
	DBResultSet results = Database_ExecuteQuery(query);
	if(results == null) {
		char error[255];
		Database_GetError(error, sizeof(error));
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: Query failed: %s", error);
		return false;
	}
	
	if(results.FetchRow()) {
		char tempSteamid[64], tempIP[64];
		results.FetchString(0, tempSteamid, sizeof(tempSteamid));
		results.FetchString(1, tempIP, sizeof(tempIP));
		int isConnected = results.FetchInt(2);
		int isVerified = results.FetchInt(3);
		
		WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: Found temp fingerprint: steamid=%s, ip=%s, is_connected=%d, is_verified=%d", tempSteamid, tempIP, isConnected, isVerified);
		
		// IMPORTANT: Если файл совпал, но is_connected=0 — hooks входа не успели (PutInServer/PostAdminCheck).
		// Раз файл валиден — принудительно выставляем оба флага.
		if(isConnected == 0 || isVerified == 0) {
			WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: File matched fingerprint, marking as verified and connected (is_connected=%d, is_verified=%d)", isConnected, isVerified);
			char updateQuery[512];
			Format(updateQuery, sizeof(updateQuery), "UPDATE rebanner_temp SET is_connected = 1, is_verified = 1 WHERE fingerprint = '%s'", escapedFingerprint);
			DBResultSet updateResults = Database_ExecuteQuery(updateQuery);
			if(updateResults == null) {
				char error[255];
				Database_GetError(error, sizeof(error));
				WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: Failed to update flags: %s", error);
			} else {
				delete updateResults;
			}
		}
		
		strcopy(steamid, steamidLen, tempSteamid);
		strcopy(ip, ipLen, tempIP);
		delete results;
		return true;
	}
	
	delete results;
	WriteLogFormatted(LogLevel_Extra, "Fingerprint_CheckTempFingerprintSync: Temp fingerprint not found");
	return false;
}
