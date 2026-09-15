#include <sourcemod>

#pragma semicolon 1
#pragma newdecls required

Database db;
bool g_bIsSQLite = false;
Handle g_hReconnectTimer = null;

void Database_Init()
{
	if(db != null) {
		WriteLogFormatted(LogLevel_Extra, "Database_Init: WARNING - Found leftover database connection from previous plugin load (hot reload or improper unload detected)");
		delete db;
		db = null;
	}
	
	WriteLogFormatted(LogLevel_Extra, "Database_Init: Initializing fingerprint module");
	Fingerprint_InitCache();
	
	WriteLogFormatted(LogLevel_Extra, "Database_Init: Connecting to database 'rebanner'");
	Database.Connect(OnDatabaseConnected, "rebanner", 0);
}

public void OnDatabaseConnected(Database database, const char[] error, any data)
{
	if(database == null || error[0]) {
		WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Database connection failed: %s", error);
		WriteLogFormatted(LogLevel_Associations, "OnDatabaseConnected: Database unavailable, plugin idle until reconnect (30s)...");
		db = null;
		
		if(g_hReconnectTimer != null) {
			KillTimer(g_hReconnectTimer);
		}
		g_hReconnectTimer = CreateTimer(30.0, Timer_ReconnectDatabase, _, TIMER_REPEAT|TIMER_FLAG_NO_MAPCHANGE);
		return;
	}
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Database connected successfully");
	
	if(g_hReconnectTimer != null) {
		KillTimer(g_hReconnectTimer);
		g_hReconnectTimer = null;
	}
	
	db = database;
	char databaseType[16];
	db.Driver.GetIdentifier(databaseType, sizeof(databaseType));
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Database type: %s", databaseType);
	
	g_bIsSQLite = StrEqual(databaseType, "sqlite", false);
	bool isSQLite = g_bIsSQLite;
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: isSQLite=%d", isSQLite);
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Creating rebanner_fingerprints table");
	CreateTable(isSQLite, "rebanner_fingerprints", 
		isSQLite ? "fingerprint TEXT PRIMARY KEY, steamid2 TEXT, is_banned INTEGER, banned_duration INTEGER, banned_timestamp INTEGER, ip TEXT" :
				   "fingerprint VARCHAR(128), steamid2 TEXT, is_banned TINYINT(1), banned_duration INT, banned_timestamp INT, ip TEXT, PRIMARY KEY (fingerprint)");
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Creating rebanner_lookup table");
	CreateTable(isSQLite, "rebanner_lookup",
		isSQLite ? "steamid2 TEXT, ip TEXT, fingerprint TEXT, last_seen_ip INTEGER" :
				   "steamid2 VARCHAR(70), ip VARCHAR(70), fingerprint VARCHAR(128), last_seen_ip INT");
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Creating rebanner_temp table");
	CreateTable(isSQLite, "rebanner_temp",
		isSQLite ? "steamid2 TEXT, ip TEXT, fingerprint TEXT PRIMARY KEY, is_connected INTEGER, is_verified INTEGER" :
				   "steamid2 VARCHAR(70), ip VARCHAR(70), fingerprint VARCHAR(128), is_connected TINYINT(1), is_verified TINYINT(1), PRIMARY KEY (fingerprint)");
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Running migration for last_seen_ip column");
	char migrationQuery[256];
	if(isSQLite) {
		Format(migrationQuery, sizeof(migrationQuery), "ALTER TABLE rebanner_lookup ADD COLUMN last_seen_ip INTEGER DEFAULT 0");
	} else {
		Format(migrationQuery, sizeof(migrationQuery), "ALTER TABLE rebanner_lookup ADD COLUMN last_seen_ip INT DEFAULT 0");
	}
	DBResultSet migrationResults = SQL_Query(db, migrationQuery);
	if(migrationResults == null) {
		char sqlError[255];
		SQL_GetError(db, sqlError, sizeof(sqlError));
		if(StrContains(sqlError, "duplicate column", false) == -1 && StrContains(sqlError, "Duplicate column", false) == -1) {
			char sqlLogMessage[512];
			Format(sqlLogMessage, sizeof(sqlLogMessage), "OnDatabaseConnected: Migration error (last_seen_ip): %s", sqlError);
			WriteLogFormatted(LogLevel_Extra, sqlLogMessage);
			LogError("Migration error (may be expected): %s", sqlError);
		}
	} else {
		delete migrationResults;
		WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Migration completed successfully (last_seen_ip)");
	}
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Running migration for UNIQUE key on rebanner_lookup");
	char uniqueKeyQuery[512];
	if(isSQLite) {
		Format(uniqueKeyQuery, sizeof(uniqueKeyQuery), "CREATE UNIQUE INDEX IF NOT EXISTS idx_lookup_unique ON rebanner_lookup(fingerprint, steamid2, ip)");
	} else {
		Format(uniqueKeyQuery, sizeof(uniqueKeyQuery), "CREATE UNIQUE INDEX IF NOT EXISTS idx_lookup_unique ON `rebanner_lookup`(fingerprint, steamid2, ip)");
	}
	DBResultSet uniqueKeyResults = SQL_Query(db, uniqueKeyQuery);
	if(uniqueKeyResults == null) {
		char sqlError[255];
		SQL_GetError(db, sqlError, sizeof(sqlError));
		if(StrContains(sqlError, "duplicate", false) == -1 && StrContains(sqlError, "already exists", false) == -1) {
			char sqlLogMessage[512];
			Format(sqlLogMessage, sizeof(sqlLogMessage), "OnDatabaseConnected: Migration error (UNIQUE key): %s", sqlError);
			WriteLogFormatted(LogLevel_Extra, sqlLogMessage);
			LogError("Migration error (may be expected): %s", sqlError);
		}
	} else {
		delete uniqueKeyResults;
		WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Migration completed successfully (UNIQUE key)");
	}
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Creating indexes");
	CreateFingerprintsIndexes(isSQLite);
	CreateLookupIndexes(isSQLite);
	CreateTempIndexes(isSQLite);
	
	WriteLogFormatted(LogLevel_Extra, "OnDatabaseConnected: Database initialization completed");
}

void CreateTable(bool isSQLite, const char[] tableName, const char[] schema)
{
	char query[512];
	Format(query, sizeof(query), "CREATE TABLE IF NOT EXISTS %s%s%s (%s)", 
		isSQLite ? "'" : "`", tableName, isSQLite ? "'" : "`", schema);
	
	char sqlLogMessage[512];
	DBResultSet results = SQL_Query(db, query);
	if(results == null) {
		char sqlError[255];
		SQL_GetError(db, sqlError, sizeof(sqlError));
		Format(sqlLogMessage, sizeof(sqlLogMessage), "CreateTable: Failed to create table %s: %s", tableName, sqlError);
		WriteLogFormatted(LogLevel_Extra, sqlLogMessage);
		SetFailState("Database creation failure: %s", sqlError);
	} else {
		delete results;
		Format(sqlLogMessage, sizeof(sqlLogMessage), "CreateTable: Table %s created successfully", tableName);
		WriteLogFormatted(LogLevel_Extra, sqlLogMessage);
		
	}
}

void CreateFingerprintsIndexes(bool isSQLite)
{
	char query[512];
	
	if(isSQLite) {
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_fingerprints_steamid ON rebanner_fingerprints(steamid2) WHERE steamid2 IS NOT NULL");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateFingerprintsIndexes: Index creation error (steamid)");
			}
		} else {
			delete results;
		}
	} else {
		Format(query, sizeof(query), "CREATE INDEX idx_fingerprints_steamid ON `rebanner_fingerprints`(steamid2)");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateFingerprintsIndexes: Index creation error (steamid)");
			}
		} else {
			delete results;
		}
	}
	
	if(isSQLite) {
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_fingerprints_ip ON rebanner_fingerprints(ip) WHERE ip IS NOT NULL");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateFingerprintsIndexes: Index creation error (ip)");
			}
		} else {
			delete results;
		}
	} else {
		Format(query, sizeof(query), "CREATE INDEX idx_fingerprints_ip ON `rebanner_fingerprints`(ip)");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateFingerprintsIndexes: Index creation error (ip)");
			}
		} else {
			delete results;
		}
	}
	
	if(isSQLite) {
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_fingerprints_banned ON rebanner_fingerprints(is_banned) WHERE is_banned = 1");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateFingerprintsIndexes: Index creation error (banned)");
			}
		} else {
			delete results;
		}
	} else {
		Format(query, sizeof(query), "CREATE INDEX idx_fingerprints_banned ON `rebanner_fingerprints`(is_banned)");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateFingerprintsIndexes: Index creation error (banned)");
			}
		} else {
			delete results;
		}
	}
}

void CreateLookupIndexes(bool isSQLite)
{
	char query[512];
	if(isSQLite) {
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_lookup_steamid ON rebanner_lookup(steamid2) WHERE steamid2 IS NOT NULL");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateLookupIndexes: Index creation error (steamid)");
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_lookup_ip ON rebanner_lookup(ip) WHERE ip IS NOT NULL");
		results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateLookupIndexes: Index creation error (ip)");
			}
		} else {
			delete results;
		}
	} else {
		Format(query, sizeof(query), "CREATE INDEX idx_lookup_steamid ON `rebanner_lookup`(steamid2)");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateLookupIndexes: Index creation error (steamid)");
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "CREATE INDEX idx_lookup_ip ON `rebanner_lookup`(ip)");
		results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateLookupIndexes: Index creation error (ip)");
			}
		} else {
			delete results;
		}
	}
}

void CreateTempIndexes(bool isSQLite)
{
	char query[512];
	if(isSQLite) {
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_temp_steamid ON rebanner_temp(steamid2) WHERE steamid2 IS NOT NULL");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateTempIndexes: Index creation error (steamid)");
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_temp_ip ON rebanner_temp(ip) WHERE ip IS NOT NULL");
		results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateTempIndexes: Index creation error (ip)");
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "CREATE INDEX IF NOT EXISTS idx_temp_verified ON rebanner_temp(is_verified) WHERE is_verified = 0");
		results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateTempIndexes: Index creation error (verified)");
			}
		} else {
			delete results;
		}
	} else {
		Format(query, sizeof(query), "CREATE INDEX idx_temp_steamid ON `rebanner_temp`(steamid2)");
		DBResultSet results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateTempIndexes: Index creation error (steamid)");
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "CREATE INDEX idx_temp_ip ON `rebanner_temp`(ip)");
		results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateTempIndexes: Index creation error (ip)");
			}
		} else {
			delete results;
		}
		Format(query, sizeof(query), "CREATE INDEX idx_temp_verified ON `rebanner_temp`(is_verified)");
		results = SQL_Query(db, query);
		if(results == null) {
			char error[255];
			SQL_GetError(db, error, sizeof(error));
			if(StrContains(error, "duplicate", false) == -1 && StrContains(error, "already exists", false) == -1) {
				WriteLogFormatted(LogLevel_Extra, "CreateTempIndexes: Index creation error (verified)");
			}
		} else {
			delete results;
		}
	}
}

bool Database_IsReady()
{
	return db != null;
}

Database Database_GetHandle()
{
	return db;
}

bool Database_IsSQLite()
{
	return g_bIsSQLite;
}

void Database_Close()
{
	if(g_hReconnectTimer != null) {
		KillTimer(g_hReconnectTimer);
		g_hReconnectTimer = null;
	}
	
	if(db != null) {
		WriteLogFormatted(LogLevel_Extra, "Database_Close: Closing database connection");
		delete db;
		db = null;
		WriteLogFormatted(LogLevel_Extra, "Database_Close: Database connection closed");
	} else {
		WriteLogFormatted(LogLevel_Extra, "Database_Close: Database already closed or not initialized");
	}
}

void FormatInsertIgnoreQuery(char[] query, int maxlen, const char[] table, const char[] columns, const char[] values)
{
	if(g_bIsSQLite) {
		Format(query, maxlen, "INSERT OR IGNORE INTO %s (%s) VALUES (%s)", table, columns, values);
	} else {
		Format(query, maxlen, "INSERT IGNORE INTO %s (%s) VALUES (%s)", table, columns, values);
	}
}

void FormatInsertOrUpdateLookupQuery(char[] query, int maxlen, const char[] values)
{
	if(g_bIsSQLite) {
		Format(query, maxlen, "INSERT OR REPLACE INTO rebanner_lookup (steamid2, ip, fingerprint, last_seen_ip) VALUES (%s)", values);
	} else {
		Format(query, maxlen, "INSERT INTO `rebanner_lookup` (steamid2, ip, fingerprint, last_seen_ip) VALUES (%s) ON DUPLICATE KEY UPDATE steamid2 = VALUES(steamid2)", values);
	}
}

void FormatConcatUpdateQuery(char[] query, int maxlen, const char[] column, const char[] value, const char[] fingerprint)
{
	if(g_bIsSQLite) {
		Format(query, maxlen, "UPDATE rebanner_fingerprints SET %s = CASE WHEN %s IS NULL OR %s = '' THEN '%s' WHEN instr(';' || %s || ';', ';%s;') > 0 THEN %s ELSE %s || ';%s' END WHERE fingerprint = '%s'", column, column, column, value, column, value, column, column, value, fingerprint);
	} else {
		Format(query, maxlen, "UPDATE rebanner_fingerprints SET %s = CASE WHEN %s IS NULL OR %s = '' THEN '%s' WHEN CONCAT(';', %s, ';') LIKE CONCAT('%%;', '%s', ';%%') THEN %s ELSE CONCAT(%s, ';%s') END WHERE fingerprint = '%s'", column, column, column, value, column, value, column, column, value, fingerprint);
	}
}

void Database_EscapeString(const char[] input, char[] output, int maxlen)
{
	if(!Database_IsReady()) {
		output[0] = '\0';
		return;
	}
	SQL_EscapeString(Database_GetHandle(), input, output, maxlen);
}

DBResultSet Database_ExecuteQuery(const char[] query)
{
	if(!Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Database_ExecuteQuery: Database not ready");
		return null;
	}
	
	DBResultSet result = SQL_Query(Database_GetHandle(), query);
	
	if(result == null) {
		char sqlError[512];
		Database_GetError(sqlError, sizeof(sqlError));
		
		if(sqlError[0]) {
			WriteLogFormatted(LogLevel_Associations, "Database_ExecuteQuery: Query failed: %s", sqlError);
			
			if(StrContains(sqlError, "Lost connection", false) != -1 || 
			   StrContains(sqlError, "MySQL server has gone away", false) != -1 ||
			   StrContains(sqlError, "Can't connect", false) != -1) {
				WriteLogFormatted(LogLevel_Associations, "Database_ExecuteQuery: Connection lost, plugin idle until reconnect.");
				db = null;
				
				if(g_hReconnectTimer == null) {
					g_hReconnectTimer = CreateTimer(30.0, Timer_ReconnectDatabase, 0, TIMER_REPEAT|TIMER_FLAG_NO_MAPCHANGE);
				}
			}
		}
	}
	
	return result;
}

void Database_GetError(char[] error, int maxlen)
{
	if(!Database_IsReady()) {
		error[0] = '\0';
		return;
	}
	SQL_GetError(Database_GetHandle(), error, maxlen);
}

public Action Timer_ReconnectDatabase(Handle timer)
{
	if(Database_IsReady()) {
		WriteLogFormatted(LogLevel_Extra, "Timer_ReconnectDatabase: Database already connected, stopping reconnect timer");
		g_hReconnectTimer = null;
		return Plugin_Stop;
	}
	
	WriteLogFormatted(LogLevel_Associations, "Timer_ReconnectDatabase: Attempting to reconnect to database...");
	Database.Connect(OnDatabaseConnected, "rebanner", 0);
	return Plugin_Continue;
}
