#include <sourcemod>
#include <materialadmin>

public Plugin myinfo =
{
	name = "[MA] Reban by IP (duration-aware)",
	author = "Lappland_Bro + AI",
	description = "Creates Steam bans for IP-only bans using the original duration",
	version = "2.3.0.0"
};

ConVar g_hTableName;
bool g_bMAReady = false;

bool IsValidRealClient(int client)
{
	return (client > 0 && client <= MaxClients && IsClientInGame(client) && !IsFakeClient(client));
}

void NormalizeIP(char[] ip)
{
	int colonPos = FindCharInString(ip, ':');
	if (colonPos != -1)
	{
		ip[colonPos] = '\0';
	}
}

public void OnPluginStart()
{
	g_hTableName = CreateConVar("ma_reban_table", "sb_bans", "MaterialAdmin/SourceBans bans table name (usually sb_bans or ma_bans)");
	AutoExecConfig(true, "ma_rebanner");
	PrintToServer("[MA Reban] Loaded. Version 2.3.0.0");
}

public void OnAllPluginsLoaded()
{
	if (LibraryExists("materialadmin"))
	{
		Database db = MAGetDatabase();
		if (db != null)
		{
			g_bMAReady = true;
			PrintToServer("[MA Reban] Connected to MaterialAdmin database (OnAllPluginsLoaded).");
		}
	}
}

public void OnLibraryAdded(const char[] name)
{
	if (StrEqual(name, "materialadmin"))
	{
		Database db = MAGetDatabase();
		if (db != null)
		{
			g_bMAReady = true;
			PrintToServer("[MA Reban] Connected to MaterialAdmin database (OnLibraryAdded).");
		}
	}
}

public void OnLibraryRemoved(const char[] name)
{
	if (StrEqual(name, "materialadmin"))
	{
		g_bMAReady = false;
	}
}

public void MAOnConnectDatabase(Database db)
{
	g_bMAReady = true;
	PrintToServer("[MA Reban] MaterialAdmin database ready (MAOnConnectDatabase).");
}

public void OnClientAuthorized(int client, const char[] auth)
{
	if (!g_bMAReady)
	{
		// Attempt to get DB one last time if it was missed
		Database dbCheck = MAGetDatabase();
		if (dbCheck != null)
		{
			g_bMAReady = true;
		}
		else
		{
			PrintToServer("[MA Reban] Skipped check for %N (MA database not ready)", client);
			return;
		}
	}

	if (!IsValidRealClient(client))
		return;

	Database db = MAGetDatabase();
	if (db == INVALID_HANDLE)
	{
		g_bMAReady = false; // Reset if invalid
		return;
	}

	char steam[32];
	strcopy(steam, sizeof(steam), auth);

	if (StrEqual(steam, "BOT") || StrContains(steam, "STEAM_ID_LAN") != -1)
		return;

	char ip[64];
	GetClientIP(client, ip, sizeof(ip));
	NormalizeIP(ip);

	char escIP[128], escSteam[64];
	db.Escape(ip, escIP, sizeof(escIP));
	db.Escape(steam, escSteam, sizeof(escSteam));

	char table[64];
	g_hTableName.GetString(table, sizeof(table));
	if (!table[0])
		strcopy(table, sizeof(table), "sb_bans");

	// Сначала проверяем Steam бан
	char querySteam[512];
	Format(querySteam, sizeof(querySteam),
		"SELECT bid FROM %s WHERE authid = '%s' AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) LIMIT 1",
		table, escSteam);
	
	DataPack packSteam = new DataPack();
	packSteam.WriteCell(GetClientUserId(client));
	packSteam.WriteString(ip);
	packSteam.WriteString(steam);
	
	PrintToServer("[MA Reban] Checking Steam ban for %N (Steam: %s)", client, steam);
	db.Query(SQL_CheckSteamBanCallback, querySteam, packSteam);
}

public void SQL_CheckSteamBanCallback(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	char ip[64], steam[32];
	pack.ReadString(ip, sizeof(ip));
	pack.ReadString(steam, sizeof(steam));
	delete pack;

	int client = GetClientOfUserId(userid);
	if (!IsValidRealClient(client))
		return;

	if (results == null)
	{
		PrintToServer("[MA Reban] SQL error checking Steam ban for %N: %s", client, error);
		return;
	}

	// Если найден Steam бан - просто кикаем
	if (results.FetchRow())
	{
		PrintToServer("[MA Reban] %N is already Steam banned, kicking", client);
		KickClient(client, "You are banned from this server");
		return;
	}

	// Steam бана нет - проверяем IP бан
	char table[64];
	g_hTableName.GetString(table, sizeof(table));
	if (!table[0])
		strcopy(table, sizeof(table), "sb_bans");

	char escIP[128];
	db.Escape(ip, escIP, sizeof(escIP));

	char queryIP[512];
	Format(queryIP, sizeof(queryIP),
		"SELECT ends, reason FROM %s WHERE ip = '%s' AND RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()) LIMIT 1",
		table, escIP);

	DataPack packIP = new DataPack();
	packIP.WriteCell(userid);
	packIP.WriteString(ip);
	packIP.WriteString(steam);

	PrintToServer("[MA Reban] Checking IP ban for %N (IP: %s)", client, ip);
	db.Query(SQL_CheckIPBanCallback, queryIP, packIP);
}

public void SQL_CheckIPBanCallback(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	char ip[64], steam[32];
	pack.ReadString(ip, sizeof(ip));
	pack.ReadString(steam, sizeof(steam));
	delete pack;

	int client = GetClientOfUserId(userid);
	if (!IsValidRealClient(client))
		return;

	if (results == null)
	{
		PrintToServer("[MA Reban] SQL error checking IP ban for %N: %s", client, error);
		return;
	}

	// Если IP бан не найден - ничего не делаем
	if (!results.FetchRow())
	{
		PrintToServer("[MA Reban] No IP ban found for %N (IP: %s)", client, ip);
		return;
	}

	// Получаем ends и reason из БД
	int ends = results.FetchInt(0);
	char reason[256];
	reason[0] = '\0';
	results.FetchString(1, reason, sizeof(reason));

	PrintToServer("[MA Reban] Found IP ban for %N (IP: %s): ends=%d, reason=%s", client, ip, ends, reason);

	// Проверяем, не истёк ли бан
	int now = GetTime();
	if (ends != 0 && ends <= now)
	{
		// Бан уже истёк, не применяем перебан
		PrintToServer("[MA Reban] IP ban expired for %N (ends=%d, now=%d) - skipping reban", client, ends, now);
		return;
	}

	// Вычисляем оставшееся время в минутах
	int remaining = 0;
	if (ends == 0)
	{
		remaining = 0; // перманентный бан
	}
	else
	{
		remaining = (ends - now) / 60;
		if (remaining < 1)
			remaining = 1; // минимум 1 минута
	}

	// Формируем причину для перебана
	char banReason[512];
	Format(banReason, sizeof(banReason), "Ban evasion detected (original: %s)", reason[0] ? reason : "no reason");

	PrintToServer("[MA Reban] Detected IP ban for %N! Re-banning SteamID %s for %d mins.", client, steam, remaining);
	LogMessage("[MA Reban] Detected IP ban for %N (IP: %s)! Re-banning SteamID %s for %d mins.", client, ip, steam, remaining);

	// Вызываем API Material Admin для блокировки по SteamID
	if (MABanPlayer(0, client, MA_BAN_STEAM, remaining, banReason))
	{
		PrintToServer("[MA Reban] Successfully banned %N via MA API", client);
		KickClient(client, banReason);
		return;
	}

	// Если API не сработало - используем fallback
	PrintToServer("[MA Reban] MA API failed, using fallback for %N", client);

	// Fallback: записываем напрямую в таблицу MA
	char table[64];
	g_hTableName.GetString(table, sizeof(table));
	if (!table[0])
		strcopy(table, sizeof(table), "sb_bans");

	char name[MAX_NAME_LENGTH];
	GetClientName(client, name, sizeof(name));

	char escSteam[64], escIP[128], escReason[512], escName[256];
	db.Escape(steam, escSteam, sizeof(escSteam));
	db.Escape(ip, escIP, sizeof(escIP));
	db.Escape(banReason, escReason, sizeof(escReason));
	db.Escape(name, escName, sizeof(escName));

	// ends уже вычислен из БД, используем его
	int banEnds = ends;

	char insert[1024];
	Format(insert, sizeof(insert),
		"INSERT INTO %s (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type) VALUES ('%s', '%s', '%s', UNIX_TIMESTAMP(), %d, %d, '%s', 0, '', 0, '', 0)",
		table, escIP, escSteam, escName, banEnds, remaining, escReason);

	PrintToServer("[MA Reban] MA API failed; inserting Steam ban manually for %N (remaining %d mins, ends=%d)", client, remaining, banEnds);
	
	// Передаем banReason в callback для кика
	DataPack packInsert = new DataPack();
	packInsert.WriteCell(GetClientUserId(client));
	packInsert.WriteString(banReason);
	
	db.Query(SQL_InsertBanCallback, insert, packInsert);
}

public void SQL_InsertBanCallback(Database db, DBResultSet results, const char[] error, DataPack pack)
{
	pack.Reset();
	int userid = pack.ReadCell();
	char banReason[512];
	pack.ReadString(banReason, sizeof(banReason));
	delete pack;
	
	int client = GetClientOfUserId(userid);
	
	if (error[0])
	{
		PrintToServer("[MA Reban] Failed to insert Steam ban for %d: %s", userid, error);
		LogMessage("Failed to insert Steam ban: %s", error);
	}
	else
	{
		PrintToServer("[MA Reban] Successfully inserted Steam ban for %d", userid);
	}

	// Кикаем игрока после записи в таблицу
	if (IsValidRealClient(client))
	{
		KickClient(client, banReason);
		PrintToServer("[MA Reban] Kicked %N after fallback ban insertion", client);
	}
}
