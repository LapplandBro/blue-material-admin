#include <sourcemod>
#include <materialadmin>
#include <materialadmin_check>

#pragma semicolon 1
#pragma newdecls required

// Модуль синхронизации мутов/гагов/сайлентов Material Admin
// между всеми аккаунтами, связанными по отпечатку в Re-Banner.
//
// CHANGELOG v3 — прямые SQL INSERT вместо MA нативов:
// - Вместо MASetClientMuteType / MAOffSetClientMuteType используется
//   прямой INSERT INTO sb_comms — форвард MAOnClientMuted НЕ срабатывает,
//   рекурсия и дублирование исключены полностью.
// - MA comm сам применяет мут к онлайн-игрокам на основе записей в sb_comms,
//   отдельный basecomm не требуется.
// - Database handle НЕ кешируется глобально — каждый раз запрашивается через
//   MaterialAdmin_GetDatabase() прямо перед использованием. MA сам управляет
//   переподключениями и всегда отдаёт актуальный handle.
// - sid (ID сервера) кешируется один раз при первом подключении к БД через
//   MAOnConnectDatabase и больше не сбрасывается — IP:PORT сервера не меняется.
// - g_iRbCommsSyncDepth оставлен как дополнительная защита на случай
//   если MA всё же как-то вызовет форвард (например при будущих обновлениях MA).
//
// Зависит от:
//   - WriteLogFormatted, IsValidSteamID          (rebanner_helpers.sp)
//   - Database_IsReady                           (rebanner_database.sp)
//   - Fingerprint_GetForClient / GetSteamIDs     (rebanner_fingerprint.sp)

ConVar g_hRbSyncCommsEnable;
int g_iRbCommsSyncDepth = 0;
int g_iRbServerSid = -1;	// ID сервера в sb_servers, -1 = ещё не загружен

#define RB_MA_COMMS_TABLE	"sb_comms"
#define RB_MA_SERVERS_TABLE	"sb_servers"
#define RB_MIN_COMM_TIME_SECONDS	60
#define RB_SYNC_REASON_APPLY	"PARSEC: Linked account comm sync"
#define RB_SYNC_REASON_REMOVE	"PARSEC: Linked account comm removal"
#define RB_SYNC_REASON_EVASION	"PARSEC: Mute/gag evasion detected"

// -----------------------------------------------------------------------------
// Вспомогательные функции
// -----------------------------------------------------------------------------

int Rb_FindClientBySteamID2(const char[] steamid)
{
	for (int i = 1; i <= MaxClients; i++)
	{
		if (!IsClientInGame(i) || IsFakeClient(i))
			continue;

		char sid[64];
		if (!GetClientAuthId(i, AuthId_Steam2, sid, sizeof(sid), false))
			continue;

		if (StrEqual(sid, steamid, false))
			return i;
	}

	return 0;
}

void Rb_NormalizeSteamIDForCompare(const char[] input, char[] output, int maxlen)
{
	NormalizeSteamIDForCompare(input, output, maxlen);
}

bool Rb_IsSameSteamAccount(const char[] a, const char[] b)
{
	return IsSameSteamAccount(a, b);
}

// Строит SQL-предикат эквивалентности SteamID между форматами:
// authid='STEAM_0:X:Y' OR authid='STEAM_1:X:Y' OR authid='X:Y'
void Rb_BuildSteamIDMatchPredicate(Database maDB, const char[] steamid, const char[] fieldName, char[] outPredicate, int maxlen)
{
	outPredicate[0] = '\0';
	if (maDB == null || !steamid[0] || !fieldName[0])
		return;

	char variants[3][64];
	int varCount = 0;

	char raw[64];
	strcopy(raw, sizeof(raw), steamid);
	TrimString(raw);
	if (raw[0])
	{
		strcopy(variants[varCount], sizeof(variants[]), raw);
		varCount++;
	}

	char part[64];
	Rb_NormalizeSteamIDForCompare(raw, part, sizeof(part)); // X:Y
	if (part[0])
	{
		char steam0[64], steam1[64];
		Format(steam0, sizeof(steam0), "STEAM_0:%s", part);
		Format(steam1, sizeof(steam1), "STEAM_1:%s", part);

		bool hasSteam0 = false, hasSteam1 = false, hasPart = false;
		for (int i = 0; i < varCount; i++)
		{
			if (StrEqual(variants[i], steam0, false)) hasSteam0 = true;
			if (StrEqual(variants[i], steam1, false)) hasSteam1 = true;
			if (StrEqual(variants[i], part, false)) hasPart = true;
		}

		if (!hasSteam0 && varCount < sizeof(variants))
		{
			strcopy(variants[varCount], sizeof(variants[]), steam0);
			varCount++;
		}
		if (!hasSteam1 && varCount < sizeof(variants))
		{
			strcopy(variants[varCount], sizeof(variants[]), steam1);
			varCount++;
		}
		if (!hasPart && varCount < sizeof(variants))
		{
			strcopy(variants[varCount], sizeof(variants[]), part);
			varCount++;
		}
	}

	for (int i = 0; i < varCount; i++)
	{
		char escaped[128], clause[192];
		SQL_EscapeString(maDB, variants[i], escaped, sizeof(escaped));
		Format(clause, sizeof(clause), "%s = '%s'", fieldName, escaped);

		if (!outPredicate[0])
			strcopy(outPredicate, maxlen, clause);
		else
			Format(outPredicate, maxlen, "%s OR %s", outPredicate, clause);
	}
}

// Есть ли уже активный комм нужного типа для данного SteamID
bool Rb_HasActiveCommForSteamID(Database maDB, const char[] steamid, int commType)
{
	if (maDB == null || !steamid[0])
		return false;

	int now = GetTime();
	char steamMatch[768];
	Rb_BuildSteamIDMatchPredicate(maDB, steamid, "authid", steamMatch, sizeof(steamMatch));
	if (!steamMatch[0])
		return false;

	char query[512];
	Format(query, sizeof(query),
		"SELECT 1 FROM %s WHERE (%s) AND RemoveType IS NULL AND type = %d AND (length = 0 OR ends > %d) LIMIT 1",
		RB_MA_COMMS_TABLE, steamMatch, commType, now);

	DBResultSet results = SQL_Query(maDB, query);
	if (results == null)
	{
		char sqlError[255];
		SQL_GetError(maDB, sqlError, sizeof(sqlError));
		WriteLogFormatted(LogLevel_Extra,
			"Rb_HasActiveCommForSteamID: query failed steamid=%s type=%d error=%s",
			steamid, commType, sqlError);
		return false;
	}

	bool exists = results.FetchRow();
	delete results;

	WriteLogFormatted(LogLevel_Extra,
		"Rb_HasActiveCommForSteamID: steamid=%s type=%d exists=%d",
		steamid, commType, exists);

	return exists;
}

// Прямой INSERT в sb_comms
// timeMinutes=0 → permanent (length=0, ends=created)
// adminSteamID="" → aid=0, adminIp='STEAM_ID_SERVER'
void Rb_InsertComm(Database maDB,
                   const char[] targetSteamID,
                   const char[] targetName,
                   int commType,
                   int timeMinutes,
                   const char[] reason,
                   const char[] adminSteamID)
{
	if (maDB == null || !targetSteamID[0])
		return;

	char escSteam[128], escName[256], escReason[512];
	SQL_EscapeString(maDB, targetSteamID, escSteam, sizeof(escSteam));
	SQL_EscapeString(maDB, targetName, escName, sizeof(escName));
	SQL_EscapeString(maDB, reason, escReason, sizeof(escReason));

	int now = GetTime();
	int lengthSec = timeMinutes * 60;
	// При permanent (timeMinutes=0) ends=created, как в твоей таблице ("1771336341" "1771336341" "0")
	int ends = (lengthSec == 0) ? now : (now + lengthSec);

	// Определяем aid и adminIp
	int aid = 0;
	char adminIp[128];

	if (adminSteamID[0])
	{
		char escAdmin[128];
		SQL_EscapeString(maDB, adminSteamID, escAdmin, sizeof(escAdmin));
		strcopy(adminIp, sizeof(adminIp), adminSteamID);

		// Ищем числовой aid в sb_admins по SteamID
		char aidQuery[256];
		Format(aidQuery, sizeof(aidQuery),
			"SELECT id FROM sb_admins WHERE authid = '%s' LIMIT 1",
			escAdmin);

		DBResultSet aidRes = SQL_Query(maDB, aidQuery);
		if (aidRes != null)
		{
			if (aidRes.FetchRow())
				aid = aidRes.FetchInt(0);
			delete aidRes;
		}
	}
	else
	{
		strcopy(adminIp, sizeof(adminIp), "STEAM_ID_SERVER");
	}

	char escAdminIp[128];
	SQL_EscapeString(maDB, adminIp, escAdminIp, sizeof(escAdminIp));

	int sid = (g_iRbServerSid > 0) ? g_iRbServerSid : 1;

	char query[1024];
	Format(query, sizeof(query),
		"INSERT INTO %s (authid, name, created, ends, length, reason, aid, adminIp, sid, type) VALUES ('%s', '%s', %d, %d, %d, '%s', %d, '%s', %d, %d)",
		RB_MA_COMMS_TABLE,
		escSteam, escName,
		now, ends, lengthSec,
		escReason,
		aid, escAdminIp,
		sid,
		commType);

	WriteLogFormatted(LogLevel_Extra,
		"Rb_InsertComm: steamid=%s name=%s type=%d timeMin=%d sid=%d reason=%s",
		targetSteamID, targetName, commType, timeMinutes, sid, reason);

	if (!SQL_FastQuery(maDB, query))
	{
		char sqlError[255];
		SQL_GetError(maDB, sqlError, sizeof(sqlError));
		WriteLogFormatted(LogLevel_Extra,
			"Rb_InsertComm: INSERT failed steamid=%s error=%s",
			targetSteamID, sqlError);
	}
}

// Снятие мута — UPDATE RemoveType='U' в sb_comms
void Rb_RemoveComm(Database maDB,
                   const char[] targetSteamID,
                   int commType,
                   const char[] adminSteamID,
                   const char[] reason)
{
	if (maDB == null || !targetSteamID[0])
		return;

	char escSteam[128], escReason[512], escAdminIp[128];
	SQL_EscapeString(maDB, targetSteamID, escSteam, sizeof(escSteam));
	SQL_EscapeString(maDB, reason, escReason, sizeof(escReason));

	if (adminSteamID[0])
	{
		char tmp[128];
		SQL_EscapeString(maDB, adminSteamID, tmp, sizeof(tmp));
		strcopy(escAdminIp, sizeof(escAdminIp), tmp);
	}
	else
	{
		strcopy(escAdminIp, sizeof(escAdminIp), "STEAM_ID_SERVER");
	}

	int now = GetTime();

	char query[512];
	Format(query, sizeof(query),
		"UPDATE %s SET RemovedBy = '%s', RemoveType = 'U', RemovedOn = %d, ureason = '%s' WHERE authid = '%s' AND type = %d AND RemoveType IS NULL AND (length = 0 OR ends > %d)",
		RB_MA_COMMS_TABLE,
		escAdminIp, now, escReason,
		escSteam, commType, now);

	WriteLogFormatted(LogLevel_Extra,
		"Rb_RemoveComm: UPDATE steamid=%s type=%d reason=%s",
		targetSteamID, commType, reason);

	if (!SQL_FastQuery(maDB, query))
	{
		char sqlError[255];
		SQL_GetError(maDB, sqlError, sizeof(sqlError));
		WriteLogFormatted(LogLevel_Extra,
			"Rb_RemoveComm: UPDATE failed steamid=%s error=%s",
			targetSteamID, sqlError);
	}
}

// Получить отпечаток для цели наказания
bool Rb_GetFingerprintForMAComms(const char[] sSteamID,
                                 const char[] sIp,
                                 int iTarget,
                                 char[] fingerprint,
                                 int maxlen)
{
	fingerprint[0] = '\0';

	if (iTarget > 0 && iTarget <= MaxClients && IsClientInGame(iTarget) && !IsFakeClient(iTarget))
	{
		if (Fingerprint_GetForClient(iTarget, sSteamID, sIp, fingerprint, maxlen))
			return true;
	}

	if (Fingerprint_GetForClient(0, sSteamID, sIp, fingerprint, maxlen))
		return true;

	return false;
}

// Загрузка sid сервера из sb_servers по IP:PORT
void Rb_LoadServerSid(Database maDB)
{
	if (maDB == null)
		return;

	int serverPort = GetConVarInt(FindConVar("hostport"));

	char serverIP[64];
	GetConVarString(FindConVar("ip"), serverIP, sizeof(serverIP));

	// Если ip convar пустой или 0.0.0.0 — пробуем hostip (числовой)
	if (!serverIP[0] || StrEqual(serverIP, "0.0.0.0"))
	{
		int ipInt = GetConVarInt(FindConVar("hostip"));
		if (ipInt != 0)
			Format(serverIP, sizeof(serverIP), "%d.%d.%d.%d",
				(ipInt >> 24) & 0xFF,
				(ipInt >> 16) & 0xFF,
				(ipInt >>  8) & 0xFF,
				ipInt & 0xFF);
	}

	char addr[128], addrEsc[128];
	Format(addr, sizeof(addr), "%s:%d", serverIP, serverPort);
	SQL_EscapeString(maDB, addr, addrEsc, sizeof(addrEsc));

	char query[256];
	Format(query, sizeof(query),
		"SELECT id FROM %s WHERE address = '%s' LIMIT 1",
		RB_MA_SERVERS_TABLE, addrEsc);

	DBResultSet res = SQL_Query(maDB, query);
	if (res != null)
	{
		if (res.FetchRow())
		{
			g_iRbServerSid = res.FetchInt(0);
			WriteLogFormatted(LogLevel_Extra,
				"Rb_LoadServerSid: sid=%d address=%s",
				g_iRbServerSid, addr);
		}
		else
		{
			WriteLogFormatted(LogLevel_Extra,
				"Rb_LoadServerSid: not found address=%s, using sid=1", addr);
			g_iRbServerSid = 1;
		}
		delete res;
	}
	else
	{
		char sqlError[255];
		SQL_GetError(maDB, sqlError, sizeof(sqlError));
		WriteLogFormatted(LogLevel_Extra,
			"Rb_LoadServerSid: query failed error=%s, using sid=1", sqlError);
		g_iRbServerSid = 1;
	}
}

// -----------------------------------------------------------------------------
// Основная функция синхронизации наказаний по отпечатку
// Вызывается при MAOnClientMuted — INSERT в sb_comms для всех альт-аккаунтов
// -----------------------------------------------------------------------------

void Rb_SyncCommsForFingerprint(const char[] fingerprint,
                                const char[] originSteamID,
                                const char[] originName,
                                int commType,		// 1=voice 2=text 3=both
                                int timeMinutes,	// 0=permanent
                                bool unmute,
                                int adminClient)
{
	if (!fingerprint[0])
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_SyncCommsForFingerprint: empty fingerprint, aborting");
		return;
	}

	if (!Database_IsReady())
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_SyncCommsForFingerprint: database not ready, aborting");
		return;
	}

	if (!LibraryExists("materialadmin") || !MaterialAdmin_IsAvailable())
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_SyncCommsForFingerprint: MA not available, aborting");
		return;
	}

	Database maDB = MaterialAdmin_GetDatabase();
	if (maDB == null)
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_SyncCommsForFingerprint: MA DB not available, aborting");
		return;
	}

	if (g_iRbServerSid < 0)
		Rb_LoadServerSid(maDB);

	// SteamID администратора
	char adminSteamID[64];
	if (adminClient > 0 && adminClient <= MaxClients && IsClientInGame(adminClient) && !IsFakeClient(adminClient))
		GetClientAuthId(adminClient, AuthId_Steam2, adminSteamID, sizeof(adminSteamID), false);

	char steamids[2048];
	Fingerprint_GetSteamIDs(fingerprint, steamids, sizeof(steamids));

	if (!steamids[0])
	{
		WriteLogFormatted(LogLevel_Extra,
			"Rb_SyncCommsForFingerprint: no SteamIDs for fingerprint %s", fingerprint);
		return;
	}

	WriteLogFormatted(LogLevel_Associations,
		"Rb_SyncCommsForFingerprint: fingerprint=%s originSteamID=%s type=%d timeMin=%d unmute=%d steamids=%s",
		fingerprint, originSteamID, commType, timeMinutes, unmute, steamids);

	char sidArray[128][64];
	int count = ExplodeString(steamids, ";", sidArray, sizeof(sidArray), sizeof(sidArray[]));
	if (count <= 0)
		return;

	g_iRbCommsSyncDepth++;

	for (int i = 0; i < count; i++)
	{
		TrimString(sidArray[i]);
		if (!sidArray[i][0])
			continue;

		// Пропускаем исходный аккаунт (тот которому уже выдан мут)
		if (originSteamID[0] && Rb_IsSameSteamAccount(sidArray[i], originSteamID))
			continue;

		if (!IsValidSteamID(sidArray[i], "Rb_SyncCommsForFingerprint"))
			continue;

		char altSteam[64];
		strcopy(altSteam, sizeof(altSteam), sidArray[i]);

		int altClient = Rb_FindClientBySteamID2(altSteam);

		char altName[MAX_NAME_LENGTH];
		if (altClient > 0 && IsClientInGame(altClient))
			GetClientName(altClient, altName, sizeof(altName));
		else
			strcopy(altName, sizeof(altName), originName);

		if (!unmute)
		{
			// Не дублируем если активный комм уже есть
			if (Rb_HasActiveCommForSteamID(maDB, altSteam, commType))
			{
				WriteLogFormatted(LogLevel_Extra,
					"Rb_SyncCommsForFingerprint: active comm already exists steamid=%s type=%d, skipping",
					altSteam, commType);
				continue;
			}

			WriteLogFormatted(LogLevel_Associations,
				"Rb_SyncCommsForFingerprint: INSERT comm steamid=%s name=%s type=%d timeMin=%d",
				altSteam, altName, commType, timeMinutes);

			// Прямой INSERT — MAOnClientMuted НЕ сработает
			// MA comm сам применит мут к онлайн-игроку при следующей проверке
			Rb_InsertComm(maDB, altSteam, altName, commType, timeMinutes,
				RB_SYNC_REASON_APPLY, adminSteamID);
		}
		else
		{
			WriteLogFormatted(LogLevel_Associations,
				"Rb_SyncCommsForFingerprint: REMOVE comm steamid=%s type=%d",
				altSteam, commType);

			Rb_RemoveComm(maDB, altSteam, commType, adminSteamID, RB_SYNC_REASON_REMOVE);
		}
	}

	g_iRbCommsSyncDepth--;
}

// -----------------------------------------------------------------------------
// Применение уже существующих мутов при заходе нового альт-аккаунта
// -----------------------------------------------------------------------------

void Rb_ApplyExistingCommsForClient(int client, const char[] fingerprint)
{
	if (!IsValidClient(client))
		return;

	if (!Rebanner_IsInitialized())
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_ApplyExistingCommsForClient: not initialized, skipping");
		return;
	}

	if (!fingerprint[0])
		return;

	if (!LibraryExists("materialadmin") || !MaterialAdmin_IsAvailable())
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_ApplyExistingCommsForClient: MA not available");
		return;
	}

	Database maDB = MaterialAdmin_GetDatabase();
	if (maDB == null)
	{
		WriteLogFormatted(LogLevel_Extra, "Rb_ApplyExistingCommsForClient: MA DB not available");
		return;
	}

	if (g_iRbServerSid < 0)
		Rb_LoadServerSid(maDB);

	char clientSteamID[64];
	GetClientAuthId(client, AuthId_Steam2, clientSteamID, sizeof(clientSteamID), false);

	char clientName[MAX_NAME_LENGTH];
	GetClientName(client, clientName, sizeof(clientName));

	char steamids[2048];
	Fingerprint_GetSteamIDs(fingerprint, steamids, sizeof(steamids));
	if (!steamids[0])
	{
		WriteLogFormatted(LogLevel_Extra,
			"Rb_ApplyExistingCommsForClient: no SteamIDs for fingerprint %s", fingerprint);
		return;
	}

	char inList[4096];
	if (!BuildSQLInListFromSteamIDs(maDB, steamids, inList, sizeof(inList)))
	{
		WriteLogFormatted(LogLevel_Extra,
			"Rb_ApplyExistingCommsForClient: failed to build IN list for %s", steamids);
		return;
	}

	int nowAtStart = GetTime();
	char selfPredicate[768];
	Rb_BuildSteamIDMatchPredicate(maDB, clientSteamID, "authid", selfPredicate, sizeof(selfPredicate));

	for (int commType = 1; commType <= 3; commType++)
	{
		// Если у клиента уже есть активная запись в sb_comms этого типа — не вставляем дубль.
		// MA comm сам подхватит запись и применит мут при подключении игрока.
		if (Rb_HasActiveCommForSteamID(maDB, clientSteamID, commType))
		{
			WriteLogFormatted(LogLevel_Extra,
				"Rb_ApplyExistingCommsForClient: client already has active comm type=%d (steamid=%s), skipping",
				commType, clientSteamID);
			continue;
		}

		int queryTimeThreshold = nowAtStart + 5;

		char query[1024];
		char selfExclude[840];
		if (selfPredicate[0])
			Format(selfExclude, sizeof(selfExclude), " AND NOT (%s)", selfPredicate);
		else
			selfExclude[0] = '\0';

		Format(query, sizeof(query),
			"SELECT length, ends, reason FROM %s WHERE authid IN (%s) AND RemoveType IS NULL AND type = %d AND (length = 0 OR ends > %d)%s ORDER BY ends DESC LIMIT 1",
			RB_MA_COMMS_TABLE, inList, commType, queryTimeThreshold,
			selfExclude);

		WriteLogFormatted(LogLevel_Extra,
			"Rb_ApplyExistingCommsForClient: checking commType=%d", commType);

		DBResultSet results = SQL_Query(maDB, query);
		if (results == null)
		{
			char sqlError[255];
			SQL_GetError(maDB, sqlError, sizeof(sqlError));
			WriteLogFormatted(LogLevel_Extra,
				"Rb_ApplyExistingCommsForClient: SQL failed commType=%d error=%s", commType, sqlError);
			continue;
		}

		if (!results.FetchRow())
		{
			WriteLogFormatted(LogLevel_Extra,
				"Rb_ApplyExistingCommsForClient: no active comm found commType=%d", commType);
			delete results;
			continue;
		}

		int lengthSec = results.FetchInt(0);
		int ends = results.FetchInt(1);
		char reason[256];
		results.FetchString(2, reason, sizeof(reason));
		delete results;

		int now = GetTime();
		int timeMinutes;

		if (lengthSec == 0)
		{
			timeMinutes = 0;
			WriteLogFormatted(LogLevel_Extra,
				"Rb_ApplyExistingCommsForClient: permanent comm commType=%d", commType);
		}
		else
		{
			int remaining = ends - now;

			WriteLogFormatted(LogLevel_Extra,
				"Rb_ApplyExistingCommsForClient: time check commType=%d ends=%d now=%d remaining=%d",
				commType, ends, now, remaining);

			if (remaining < RB_MIN_COMM_TIME_SECONDS)
			{
				WriteLogFormatted(LogLevel_Extra,
					"Rb_ApplyExistingCommsForClient: insufficient remaining=%d (min=%d) commType=%d, skipping",
					remaining, RB_MIN_COMM_TIME_SECONDS, commType);
				continue;
			}

			timeMinutes = remaining / 60;
			if (timeMinutes < 1)
				timeMinutes = 1;

			WriteLogFormatted(LogLevel_Extra,
				"Rb_ApplyExistingCommsForClient: %d min remaining commType=%d", timeMinutes, commType);
		}

		WriteLogFormatted(LogLevel_Associations,
			"Rb_ApplyExistingCommsForClient: INSERT evasion comm client=%d steamid=%s type=%d timeMin=%d fingerprint=%s",
			client, clientSteamID, commType, timeMinutes, fingerprint);

		// Прямой INSERT — MAOnClientMuted НЕ сработает, двойного мута не будет
		// MA comm сам применит мут к игроку на основе записи в sb_comms
		Rb_InsertComm(maDB, clientSteamID, clientName, commType, timeMinutes,
			RB_SYNC_REASON_EVASION, "");

		WriteLogFormatted(LogLevel_Extra,
			"Rb_ApplyExistingCommsForClient: inserted comm type=%d for client=%d", commType, client);
	}
}

// -----------------------------------------------------------------------------
// Инициализация модуля
// -----------------------------------------------------------------------------

void Rb_CommsSync_Init()
{
	g_hRbSyncCommsEnable = CreateConVar(
		"rb_sync_comms_enable",
		"1",
		"Синхронизировать муты/гаги/сайлент Material Admin со всеми аккаунтами, связанными через Re-Banner (0 - выкл, 1 - вкл)"
	);

	WriteLogFormatted(LogLevel_Extra,
		"Rb_CommsSync_Init: rb_sync_comms_enable=%d", g_hRbSyncCommsEnable.IntValue);
}

// MA подключился/переподключился к БД
// Handle мог смениться (смена карты, реконнект MA) — это нормально.
// Мы никогда не кешируем handle — каждый раз берём свежий через MaterialAdmin_GetDatabase().
// sid не сбрасываем: IP:PORT сервера не меняется между картами.
public void MAOnConnectDatabase(Database maConnectDB)
{
	if (maConnectDB == null)
		return;

	if (g_iRbServerSid < 0)
	{
		WriteLogFormatted(LogLevel_Extra, "MAOnConnectDatabase: DB connected, loading server sid");
		Rb_LoadServerSid(maConnectDB);
	}
	else
	{
		WriteLogFormatted(LogLevel_Extra,
			"MAOnConnectDatabase: DB reconnected, sid=%d already known, skipping reload",
			g_iRbServerSid);
	}
}

// -----------------------------------------------------------------------------
// Обработчики событий Material Admin
// -----------------------------------------------------------------------------

#if defined _materialadmin_included

public void MAOnClientMuted(int iClient, int iTarget, char[] sIp, char[] sSteamID, char[] sName, int iType, int iTime, char[] sReason)
{
	if (!Rebanner_IsInitialized())
		return;

	// Игнорируем события от самого Re-Banner
	// (прямой INSERT не вызывает форвард, но защита остаётся на будущее)
	if (StrEqual(sReason, RB_SYNC_REASON_APPLY, false)
		|| StrEqual(sReason, RB_SYNC_REASON_EVASION, false))
		return;

	if (g_iRbCommsSyncDepth > 0)
		return;

	if (g_hRbSyncCommsEnable != null && !g_hRbSyncCommsEnable.BoolValue)
		return;

	WriteLogFormatted(LogLevel_Extra,
		"MAOnClientMuted: admin=%d target=%d ip=%s steamid=%s name=%s type=%d time=%d reason=%s",
		iClient, iTarget, sIp, sSteamID, sName, iType, iTime, sReason);

	char fingerprint[128];
	if (!Rb_GetFingerprintForMAComms(sSteamID, sIp, iTarget, fingerprint, sizeof(fingerprint)))
	{
		WriteLogFormatted(LogLevel_Extra,
			"MAOnClientMuted: no fingerprint steamid=%s ip=%s", sSteamID, sIp);
		return;
	}

	WriteLogFormatted(LogLevel_Associations,
		"MAOnClientMuted: fingerprint=%s steamid=%s", fingerprint, sSteamID);

	char originSteam[64];
	strcopy(originSteam, sizeof(originSteam), sSteamID);
	if (iTarget > 0 && iTarget <= MaxClients && IsClientInGame(iTarget) && !IsFakeClient(iTarget))
	{
		char targetSteam[64];
		if (GetClientAuthId(iTarget, AuthId_Steam2, targetSteam, sizeof(targetSteam), false) && targetSteam[0])
			strcopy(originSteam, sizeof(originSteam), targetSteam);
	}

	Rb_SyncCommsForFingerprint(fingerprint, originSteam, sName,
		iType, iTime, false, iClient);
}

public void MAOnClientUnMuted(int iClient, int iTarget, char[] sIp, char[] sSteamID, char[] sName, int iType, char[] sReason)
{
	if (!Rebanner_IsInitialized())
		return;

	if (StrEqual(sReason, RB_SYNC_REASON_REMOVE, false))
		return;

	if (g_iRbCommsSyncDepth > 0)
		return;

	if (g_hRbSyncCommsEnable != null && !g_hRbSyncCommsEnable.BoolValue)
		return;

	WriteLogFormatted(LogLevel_Extra,
		"MAOnClientUnMuted: admin=%d target=%d ip=%s steamid=%s name=%s type=%d reason=%s",
		iClient, iTarget, sIp, sSteamID, sName, iType, sReason);

	char fingerprint[128];
	if (!Rb_GetFingerprintForMAComms(sSteamID, sIp, iTarget, fingerprint, sizeof(fingerprint)))
	{
		WriteLogFormatted(LogLevel_Extra,
			"MAOnClientUnMuted: no fingerprint steamid=%s ip=%s", sSteamID, sIp);
		return;
	}

	WriteLogFormatted(LogLevel_Associations,
		"MAOnClientUnMuted: fingerprint=%s steamid=%s", fingerprint, sSteamID);

	char originSteam[64];
	strcopy(originSteam, sizeof(originSteam), sSteamID);
	if (iTarget > 0 && iTarget <= MaxClients && IsClientInGame(iTarget) && !IsFakeClient(iTarget))
	{
		char targetSteam[64];
		if (GetClientAuthId(iTarget, AuthId_Steam2, targetSteam, sizeof(targetSteam), false) && targetSteam[0])
			strcopy(originSteam, sizeof(originSteam), targetSteam);
	}

	Rb_SyncCommsForFingerprint(fingerprint, originSteam, sName,
		iType, 0, true, iClient);
}

#endif
