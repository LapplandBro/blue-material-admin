#include <sourcemod>

#pragma semicolon 1
#pragma newdecls required

void Commands_Init()
{
	WriteLogFormatted(LogLevel_Extra, "Commands_Init: Registering admin commands");
	RegAdminCmd("rb_unbansteam", Command_UnbanBySteamID, ADMFLAG_UNBAN, "Remove the ban flag on a fingerprint via a SteamID");
	RegAdminCmd("rb_status", Command_Status, ADMFLAG_GENERIC, "Display Re-Banner status information");
	RegAdminCmd("rb_test_entropy", Command_TestEntropy, ADMFLAG_GENERIC, "Display entropy sources used for fingerprint generation");
	RegAdminCmd("rb_unlink", Command_Unlink, ADMFLAG_BAN, "Unlink a SteamID from a fingerprint, ban the SteamID and last known IP. Usage: rb_unlink <fingerprint> <STEAM_X:X:XXXXXXXXX>");
	WriteLogFormatted(LogLevel_Extra, "Commands_Init: Commands registered successfully");
}

public Action Command_UnbanBySteamID(int client, int args)
{
	char adminName[64];
	if(client > 0)
		GetClientName(client, adminName, sizeof(adminName));
	else
		strcopy(adminName, sizeof(adminName), "Console");
	
	WriteLogFormatted(LogLevel_Extra, "Command_UnbanBySteamID: Called by %s (client=%d), args=%d", adminName, client, args);
	
	if(args != 1) {
		ReplyToCommand(client, "[Re-Banner] Usage: rb_unbansteam <STEAM_X:X:XXXXXXXXX>");
		return Plugin_Handled;
	}
	
	char steamid[64];
	GetCmdArg(1, steamid, sizeof(steamid));
	
	WriteLogFormatted(LogLevel_Extra, "Command_UnbanBySteamID: Attempting to unban SteamID: %s", steamid);
	
	if(Banning_TryUnbanBySteamID(steamid)) {
		WriteLogFormatted(LogLevel_Extra, "Command_UnbanBySteamID: Successfully unbanned SteamID: %s", steamid);
		ReplyToCommand(client, "[Re-Banner] Successfully removed ban record from SteamID %s.", steamid);
	} else {
		WriteLogFormatted(LogLevel_Extra, "Command_UnbanBySteamID: Failed to unban SteamID: %s (not found or not banned)", steamid);
		ReplyToCommand(client, "[Re-Banner] Failed to match %s to a known fingerprint or it's not banned.", steamid);
	}
	
	return Plugin_Handled;
}

public Action Command_Status(int client, int args)
{
	char adminName[64];
	if(client > 0)
		GetClientName(client, adminName, sizeof(adminName));
	else
		strcopy(adminName, sizeof(adminName), "Console");
	
	WriteLogFormatted(LogLevel_Extra, "Command_Status: Called by %s (client=%d)", adminName, client);
	
	int fingerprints = Fingerprint_GetTableSize();
	int banned = Fingerprint_GetBannedCount();
	int steamidAssoc = Fingerprint_GetSteamIDTableSize();
	bool dbReady = Database_IsReady();
	bool isSQLite = Database_IsSQLite();
	
	WriteLogFormatted(LogLevel_Extra, "Command_Status: fingerprints=%d, banned=%d, steamidAssoc=%d, dbReady=%d, isSQLite=%d", 
		fingerprints, banned, steamidAssoc, dbReady, isSQLite);
	
	ReplyToCommand(client, "[Re-Banner] Status Information:");
	ReplyToCommand(client, "  Fingerprints tracked: %d", fingerprints);
	ReplyToCommand(client, "  Banned fingerprints: %d", banned);
	ReplyToCommand(client, "  SteamID associations: %d", steamidAssoc);
	ReplyToCommand(client, "  Database connected: %s", dbReady ? "Yes" : "No");
	ReplyToCommand(client, "  Database type: %s", isSQLite ? "SQLite" : "MySQL");
	
	if(LibraryExists("materialadmin_check")) {
		bool maAvailable = MaterialAdmin_IsAvailable();
		WriteLogFormatted(LogLevel_Extra, "Command_Status: MaterialAdmin Check available: %d", maAvailable);
		ReplyToCommand(client, "  MaterialAdmin Check: %s", maAvailable ? "Available" : "Not Available");
	} else {
		WriteLogFormatted(LogLevel_Extra, "Command_Status: MaterialAdmin Check library not loaded");
		ReplyToCommand(client, "  MaterialAdmin Check: Not loaded");
	}
	
	return Plugin_Handled;
}

public Action Command_TestEntropy(int client, int args)
{
	char adminName[64];
	if(client > 0)
		GetClientName(client, adminName, sizeof(adminName));
	else
		strcopy(adminName, sizeof(adminName), "Console");
	
	WriteLogFormatted(LogLevel_Extra, "Command_TestEntropy: Called by %s (client=%d)", adminName, client);
	
	// Получаем все источники энтропии
	int timeEntropy = GetTime();
	float engineTimeFloat = GetEngineTime();
	int engineTimeEntropy = RoundFloat(engineTimeFloat * 1000.0) & 0x7FFFFFFF;
	int tickEntropy = GetGameTickCount();
	int uRandomEntropy = GetURandomInt();
	int randomEntropy = GetRandomInt(0, 2147483647);
	
	// Комбинированное значение (как в GetRandomValue)
	int combined = (timeEntropy ^ engineTimeEntropy) + (tickEntropy ^ uRandomEntropy) + randomEntropy;
	
	// Генерируем тестовый отпечаток
	char testFingerprint[33];
	GenerateFingerprint(testFingerprint, sizeof(testFingerprint));
	
	WriteLogFormatted(LogLevel_Extra, "Command_TestEntropy: timeEntropy=%d, engineTimeFloat=%.6f, engineTimeEntropy=%d, tickEntropy=%d, uRandomEntropy=%d, randomEntropy=%d, combined=%d", 
		timeEntropy, engineTimeFloat, engineTimeEntropy, tickEntropy, uRandomEntropy, randomEntropy, combined);
	
	ReplyToCommand(client, "[Re-Banner] Entropy Sources (for fingerprint generation):");
	ReplyToCommand(client, "  GetTime(): %d", timeEntropy);
	ReplyToCommand(client, "  GetEngineTime(): %.6f (entropy: %d)", engineTimeFloat, engineTimeEntropy);
	ReplyToCommand(client, "  GetGameTickCount(): %d", tickEntropy);
	ReplyToCommand(client, "  GetURandomInt(): %d", uRandomEntropy);
	ReplyToCommand(client, "  GetRandomInt(): %d", randomEntropy);
	ReplyToCommand(client, "  Combined (XOR+ADD): %d", combined);
	
	// Получаем свежую системную энтропию из /dev/urandom
	int systemEntropy;
	bool systemEntropyFromURandom = Helpers_GetSystemEntropySync(systemEntropy);
	if(systemEntropyFromURandom) {
		ReplyToCommand(client, "  System entropy (/dev/urandom): Available");
		ReplyToCommand(client, "  System entropy value: %d", systemEntropy);
	} else {
		ReplyToCommand(client, "  System entropy (/dev/urandom): Not available (using fallback)");
		ReplyToCommand(client, "  System entropy (fallback): %d", systemEntropy);
	}
	
	ReplyToCommand(client, "  Generated test fingerprint: %s", testFingerprint);
	
	return Plugin_Handled;
}

public Action Command_Unlink(int client, int args)
{
	char adminName[64];
	if(client > 0)
		GetClientName(client, adminName, sizeof(adminName));
	else
		strcopy(adminName, sizeof(adminName), "Console");

	if(args < 2) {
		ReplyToCommand(client, "[Re-Banner] Usage: rb_unlink <fingerprint> <STEAM_X:X:XXXXXXXXX>");
		ReplyToCommand(client, "[Re-Banner] Unlinks a SteamID from a fingerprint, bans the SteamID (MA) and last known IP (addip).");
		return Plugin_Handled;
	}

	char fingerprint[128], steamid[64];
	GetCmdArg(1, fingerprint, sizeof(fingerprint));
	GetCmdArg(2, steamid, sizeof(steamid));

	WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Called by %s - fingerprint=%s, steamid=%s", adminName, fingerprint, steamid);

	if(!Database_IsReady()) {
		ReplyToCommand(client, "[Re-Banner] Database not available.");
		return Plugin_Handled;
	}
	if(!Fingerprint_Exists(fingerprint)) {
		ReplyToCommand(client, "[Re-Banner] Fingerprint %s not found in database.", fingerprint);
		return Plugin_Handled;
	}

	char escapedFingerprint[256];
	Database_EscapeString(fingerprint, escapedFingerprint, sizeof(escapedFingerprint));

	// --- Find attacker's IP (legacy rows only) ---
	char lastIP[64];
	lastIP[0] = '\0';

	char query[1024];
	DBResultSet results;

	Format(query, sizeof(query),
		"SELECT steamid2, ip FROM rebanner_temp WHERE fingerprint = '%s' AND ip IS NOT NULL AND ip != ''",
		escapedFingerprint);
	results = Database_ExecuteQuery(query);
	if(results != null) {
		while(results.FetchRow()) {
			char rowSteam[64];
			results.FetchString(0, rowSteam, sizeof(rowSteam));
			if(IsSameSteamAccount(rowSteam, steamid)) {
				results.FetchString(1, lastIP, sizeof(lastIP));
				break;
			}
		}
		delete results;
	}

	if(!lastIP[0]) {
		Format(query, sizeof(query),
			"SELECT steamid2, ip FROM rebanner_lookup WHERE fingerprint = '%s' AND ip IS NOT NULL AND ip != ''",
			escapedFingerprint);
		results = Database_ExecuteQuery(query);
		if(results != null) {
			while(results.FetchRow()) {
				char rowSteam[64];
				results.FetchString(0, rowSteam, sizeof(rowSteam));
				if(IsSameSteamAccount(rowSteam, steamid)) {
					results.FetchString(1, lastIP, sizeof(lastIP));
					break;
				}
			}
			delete results;
		}
	}

	WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Resolved IP=%s for steamid=%s fingerprint=%s", lastIP, steamid, fingerprint);

	// --- Step 1: DELETE matching rows from rebanner_lookup ---
	Format(query, sizeof(query), "SELECT steamid2 FROM rebanner_lookup WHERE fingerprint = '%s'", escapedFingerprint);
	results = Database_ExecuteQuery(query);
	if(results != null) {
		while(results.FetchRow()) {
			char rowSteam[64];
			results.FetchString(0, rowSteam, sizeof(rowSteam));
			if(!IsSameSteamAccount(rowSteam, steamid))
				continue;

			char escapedRow[128];
			Database_EscapeString(rowSteam, escapedRow, sizeof(escapedRow));
			char delQuery[512];
			Format(delQuery, sizeof(delQuery),
				"DELETE FROM rebanner_lookup WHERE fingerprint = '%s' AND steamid2 = '%s'",
				escapedFingerprint, escapedRow);
			DBResultSet delResults = Database_ExecuteQuery(delQuery);
			if(delResults != null)
				delete delResults;
		}
		delete results;
	}

	// --- Step 2: Remove SteamID from rebanner_fingerprints.steamid2 (semicolon-separated) ---
	Format(query, sizeof(query),
		"SELECT steamid2 FROM rebanner_fingerprints WHERE fingerprint = '%s'",
		escapedFingerprint);
	results = Database_ExecuteQuery(query);
	if(results != null && results.FetchRow()) {
		char currentSteamIDs[2048];
		results.FetchString(0, currentSteamIDs, sizeof(currentSteamIDs));
		delete results;

		char sidArray[128][64];
		int count = ExplodeString(currentSteamIDs, ";", sidArray, sizeof(sidArray), sizeof(sidArray[]));
		char newSteamIDs[2048];
		newSteamIDs[0] = '\0';
		bool first = true;

		for(int i = 0; i < count; i++) {
			TrimString(sidArray[i]);
			if(!sidArray[i][0]) continue;
			if(IsSameSteamAccount(sidArray[i], steamid)) continue;

			if(!first) StrCat(newSteamIDs, sizeof(newSteamIDs), ";");
			StrCat(newSteamIDs, sizeof(newSteamIDs), sidArray[i]);
			first = false;
		}

		if(newSteamIDs[0]) {
			char escapedNew[2048];
			Database_EscapeString(newSteamIDs, escapedNew, sizeof(escapedNew));
			Format(query, sizeof(query),
				"UPDATE rebanner_fingerprints SET steamid2 = '%s' WHERE fingerprint = '%s'",
				escapedNew, escapedFingerprint);
		} else {
			Format(query, sizeof(query),
				"UPDATE rebanner_fingerprints SET steamid2 = NULL WHERE fingerprint = '%s'",
				escapedFingerprint);
		}
		results = Database_ExecuteQuery(query);
		if(results != null) delete results;
	} else {
		if(results != null) delete results;
	}

	// --- Step 3: DELETE matching rows from rebanner_temp ---
	Format(query, sizeof(query), "SELECT steamid2 FROM rebanner_temp WHERE fingerprint = '%s'", escapedFingerprint);
	results = Database_ExecuteQuery(query);
	if(results != null) {
		while(results.FetchRow()) {
			char rowSteam[64];
			results.FetchString(0, rowSteam, sizeof(rowSteam));
			if(!IsSameSteamAccount(rowSteam, steamid))
				continue;

			char escapedRow[128];
			Database_EscapeString(rowSteam, escapedRow, sizeof(escapedRow));
			char delQuery[512];
			Format(delQuery, sizeof(delQuery),
				"DELETE FROM rebanner_temp WHERE fingerprint = '%s' AND steamid2 = '%s'",
				escapedFingerprint, escapedRow);
			DBResultSet delResults = Database_ExecuteQuery(delQuery);
			if(delResults != null)
				delete delResults;
		}
		delete results;
	}

	ReplyToCommand(client, "[Re-Banner] Unlinked %s from fingerprint %s.", steamid, fingerprint);
	WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Unlinked SteamID %s from fingerprint %s (admin: %s)", steamid, fingerprint, adminName);

	// --- Step 4: Ban SteamID via Material Admin ---
	char banReason[256];
	strcopy(banReason, sizeof(banReason), "PARSEC: Fingerprint spoofing");

	if(LibraryExists("materialadmin") && MaterialAdmin_IsAvailable()) {
		int target = 0;
		for(int i = 1; i <= MaxClients; i++) {
			if(!IsClientInGame(i) || IsFakeClient(i)) continue;
			char sid[64];
			if(!GetClientAuthId(i, AuthId_Steam2, sid, sizeof(sid), false)) continue;
			if(IsSameSteamAccount(sid, steamid)) { target = i; break; }
		}

		if(target > 0) {
			MABanPlayer(client, target, MA_BAN_STEAM, 0, banReason);
			char targetName[64];
			GetClientName(target, targetName, sizeof(targetName));
			ReplyToCommand(client, "[Re-Banner] Banned online player \"%s\" (%s) via MA (permanent).", targetName, steamid);
			WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Banned online client=%d steamid=%s via MA", target, steamid);
		} else {
			char offSteam[64], offIp[64], offName[64];
			strcopy(offSteam, sizeof(offSteam), steamid);
			strcopy(offIp, sizeof(offIp), lastIP[0] ? lastIP : "");
			strcopy(offName, sizeof(offName), "Unlinked");
			MAOffBanPlayer(client, MA_BAN_STEAM, offSteam, offIp, offName, 0, banReason);
			ReplyToCommand(client, "[Re-Banner] Banned %s via MA (permanent, offline).", steamid);
			WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Banned offline steamid=%s ip=%s via MA", steamid, lastIP);
		}
	} else {
		BanIdentity(steamid, 0, BANFLAG_AUTHID, "PARSEC: Fingerprint spoofing");
		ReplyToCommand(client, "[Re-Banner] Banned %s via BanIdentity (MA not available).", steamid);
		WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Banned steamid=%s via BanIdentity (MA unavailable)", steamid);
	}

	// --- Step 5: Ban IP via addip ---
	if(lastIP[0]) {
		ServerCommand("addip 0 %s", lastIP);
		ReplyToCommand(client, "[Re-Banner] Banned IP %s via addip (permanent).", lastIP);
		WriteLogFormatted(LogLevel_Bans, "Command_Unlink: Banned IP %s via addip", lastIP);
	} else {
		ReplyToCommand(client, "[Re-Banner] WARNING: No IP found. Use 'addip 0 <IP>' manually if known.");
		WriteLogFormatted(LogLevel_Bans, "Command_Unlink: No IP found for steamid=%s, manual IP ban required", steamid);
	}

	return Plugin_Handled;
}
