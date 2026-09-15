#include <sourcemod>

#pragma semicolon 1
#pragma newdecls required

#if !defined LogLevel
enum LogLevel { LogLevel_None = 0, LogLevel_Bans, LogLevel_Associations, LogLevel_Debug, LogLevel_Extra }
#endif
forward void WriteLog(const char[] message, LogLevel level);

// Флаг, указывающий, что плагин полностью инициализирован (OnPluginStart завершён)
bool g_bPluginInitialized = false;

stock void Rebanner_SetInitialized(bool val)
{
    g_bPluginInitialized = val;
}

stock bool Rebanner_IsInitialized()
{
    return g_bPluginInitialized;
}

// Попытка использовать System2 для получения настоящей энтропии из /dev/urandom
#if defined _system2_included
	#define HAS_SYSTEM2_URANDOM
#else
	#tryinclude <system2>
	#if defined _system2_included
		#define HAS_SYSTEM2_URANDOM
	#endif
#endif

/**
 * При наличии System2 получает весь 32-символьный HEX ключ (fingerprint) напрямую из /dev/urandom.
 * Один вызов команды = полная криптографическая энтропия для всего ключа.
 *
 * @param fingerprint Выходной буфер для отпечатка (минимум 33 символа)
 * @param maxlen      Размер буфера
 * @return true если ключ успешно получен из /dev/urandom, false иначе (нет System2 или команда не сработала)
 */
stock bool Helpers_GetFullFingerprintFromUrandom(char[] fingerprint, int maxlen)
{
	#if defined HAS_SYSTEM2_URANDOM
	fingerprint[0] = '\0';
	if(maxlen < 33)
		return false;

	// 16 байт из /dev/urandom = 32 HEX символа. od + tr — POSIX, есть на Debian и минимальных системах (xxd часто отсутствует).
	char output[128];
	if(System2_Execute(output, sizeof(output), "od -An -N16 -tx1 /dev/urandom 2>/dev/null | tr -d ' \n'")) {
		TrimString(output);
		int len = strlen(output);
		if(len >= 32) {
			output[32] = '\0';
			len = 32;
			for(int i = 0; i < len; i++) {
				if(!(output[i] >= '0' && output[i] <= '9') && !(output[i] >= 'a' && output[i] <= 'f') && !(output[i] >= 'A' && output[i] <= 'F'))
					return false;
			}
			strcopy(fingerprint, maxlen, output);
			return true;
		}
	}
	#endif
	return false;
}

/**
 * Синхронно получает свежую энтропию из /dev/urandom через System2 (если доступно)
 * Fallback на комбинированные источники если System2 недоступен
 * 
 * @param entropy Выходное значение энтропии
 * @return true если системная энтропия успешно получена из /dev/urandom, false если использован fallback
 */
stock bool Helpers_GetSystemEntropySync(int &entropy)
{
	#if defined HAS_SYSTEM2_URANDOM
		char outputText[64];
		// Синхронно получаем настоящую энтропию из /dev/urandom
		// Команда: od -An -N4 -tu4 /dev/urandom (получить 4 байта как unsigned int)
		if(System2_Execute(outputText, sizeof(outputText), "od -An -N4 -tu4 /dev/urandom 2>&1")) {
			TrimString(outputText);
			
			// Парсим число из вывода команды
			int parsedEntropy = StringToInt(outputText);
			if(parsedEntropy != 0 || strlen(outputText) > 0) {
				// Используем значение даже если оно 0 (может быть валидным)
				// Но проверяем что вывод не пустой
				if(strlen(outputText) > 0) {
					entropy = parsedEntropy;
					return true;
				}
			}
		}
	#endif
	
	// Fallback: используем комбинированные источники
	int timeEntropy = GetTime();
	int engineTimeEntropy = RoundFloat(GetEngineTime() * 1000.0) & 0x7FFFFFFF;
	int uRandomEntropy = GetURandomInt();
	int randomEntropy = GetRandomInt(0, 2147483647);
	entropy = (timeEntropy ^ engineTimeEntropy) + (uRandomEntropy ^ randomEntropy);
	return false;
}

/**
 * Проверяет, доступна ли системная энтропия из /dev/urandom (через System2)
 * 
 * @return true если System2 доступен, false иначе
 */
stock bool Helpers_IsSystemEntropyAvailable()
{
	#if defined HAS_SYSTEM2_URANDOM
		return true;
	#else
		return false;
	#endif
}

/**
 * Проверяет, является ли клиент валидным
 * 
 * @param client        Индекс клиента
 * @param replaycheck   Проверять ли SourceTV и Replay клиентов (по умолчанию false)
 * @param onlyrealclients Проверять ли только реальных клиентов (не ботов, по умолчанию true)
 * @return              true если клиент валиден, false иначе
 */
stock bool IsValidClient(int client, bool replaycheck=false, bool onlyrealclients=true)
{
	if(client <= 0 || client > MaxClients)
		return false;
	
	if(!IsClientInGame(client))
		return false;
	
	if(onlyrealclients && IsFakeClient(client))
		return false;
	
	if(replaycheck && (IsClientSourceTV(client) || IsClientReplay(client)))
		return false;
	
	return true;
}

/**
 * Проверяет, может ли клиент быть обработан плагином
 * (в игре, не бот, не SourceTV, не Replay)
 * 
 * @param client Индекс клиента
 * @return       true если клиент может быть обработан, false иначе
 */
stock bool CanProcessClient(int client)
{
	return IsValidClient(client, true, true);
}

/**
 * Проверяет валидность индекса клиента для работы с массивами
 * 
 * @param client Индекс клиента
 * @return       true если индекс валиден, false иначе
 */
stock bool IsValidClientIndex(int client)
{
	return (client >= 1 && client <= MaxClients);
}

/**
 * Проверяет является ли SteamID валидным (не STEAM_ID_STOP_IGNORING_RETVALS)
 * 
 * @param steamid SteamID для проверки
 * @param functionName Имя функции для логирования
 * @return true если SteamID валиден, false если это STEAM_ID_STOP_IGNORING_RETVALS
 */
stock bool IsValidSteamID(const char[] steamid, const char[] functionName)
{
	if(steamid[0] && StrEqual(steamid, "STEAM_ID_STOP_IGNORING_RETVALS", false)) {
		WriteLogFormatted(LogLevel_Extra, "%s: Skipping invalid SteamID STEAM_ID_STOP_IGNORING_RETVALS", functionName);
		return false;
	}
	return true;
}

stock void NormalizeSteamIDForCompare(const char[] input, char[] output, int maxlen)
{
	output[0] = '\0';
	if(!input[0])
		return;

	char work[64];
	strcopy(work, sizeof(work), input);
	TrimString(work);

	if(StrContains(work, "STEAM_", false) == 0 && strlen(work) > 8)
		strcopy(work, sizeof(work), work[8]);

	if((work[0] == '0' || work[0] == '1') && work[1] == ':' && work[2] != '\0') {
		strcopy(output, maxlen, work[2]);
		return;
	}

	strcopy(output, maxlen, work);
}

stock bool IsSameSteamAccount(const char[] a, const char[] b)
{
	if(!a[0] || !b[0])
		return false;

	char normA[64], normB[64];
	NormalizeSteamIDForCompare(a, normA, sizeof(normA));
	NormalizeSteamIDForCompare(b, normB, sizeof(normB));

	return normA[0] && normB[0] && StrEqual(normA, normB, false);
}

/**
 * Проверяет валидность клиента для операций с баном
 * 
 * @param client Индекс клиента
 * @param functionName Имя функции для логирования
 * @return true если клиент валиден, false иначе
 */
stock bool ValidateClientForBan(int client, const char[] functionName)
{
	if(client < 1 || client > MaxClients || !IsClientInGame(client)) {
		WriteLogFormatted(LogLevel_Extra, "%s: Client not valid or not in game, aborting", functionName);
		return false;
	}
	return true;
}

/**
 * Гибридный генератор случайных чисел с множественными источниками энтропии
 * Комбинирует GetURandomInt(), GetRandomInt(), GetTime(), GetEngineTime(), GetGameTickCount()
 * для максимальной непредсказуемости
 * 
 * @param min Минимальное значение
 * @param max Максимальное значение
 * @return Случайное значение в диапазоне [min, max]
 */
stock int GetRandomValue(int min, int max)
{
	if(min >= max)
		return min;
	
	// Собираем энтропию из разных источников
	int timeEntropy = GetTime();                    // Время (никогда не повторяется)
	int engineTimeEntropy = RoundFloat(GetEngineTime() * 1000.0) & 0x7FFFFFFF;  // Дробная часть времени движка
	int tickEntropy = GetGameTickCount();           // Тики игры (повторяются при смене карты, но комбинируем с временем)
	int uRandomEntropy = GetURandomInt();           // Криптографически стойкий генератор
	int randomEntropy = GetRandomInt(0, 2147483647); // Дополнительная энтропия из HL2 Random Stream
	
	// Комбинируем все источники через XOR и сложение
	int combined = (timeEntropy ^ engineTimeEntropy) + (tickEntropy ^ uRandomEntropy) + randomEntropy;
	
	// Нормализуем к нужному диапазону с rejection sampling для избежания modulo bias
	int range = max - min + 1;
	int maxValid = (2147483647 / range) * range; // Максимальное значение, кратное range
	
	int value;
	do {
		// Смешиваем комбинированное значение с новыми вызовами генераторов
		value = (combined + GetURandomInt() + GetRandomInt(0, 2147483647)) & 0x7FFFFFFF;
	} while(value >= maxValid); // Rejection sampling - отбрасываем значения, создающие bias
	
	return min + (value % range);
}

/**
 * Генерирует криптографически стойкий 32-символьный HEX отпечаток
 * Использует гибридный подход: первые 16 символов через URANDOM, последние 16 через RANDOM + время + тики
 * Это делает отпечаток практически непредсказуемым даже при смене карты
 * 
 * @param fingerprint Выходной буфер для отпечатка (минимум 33 символа)
 * @param maxlen Размер выходного буфера
 */
stock void GenerateFingerprint(char[] fingerprint, int maxlen)
{
	fingerprint[0] = '\0';
	if(maxlen < 33)
		return;

	// При наличии System2 — генерируем весь ключ из /dev/urandom одним вызовом (наверняка криптостойко)
	#if defined HAS_SYSTEM2_URANDOM
	if(Helpers_GetFullFingerprintFromUrandom(fingerprint, maxlen))
		return;
	#endif

	// Fallback: гибридная генерация (нет System2 или команда не сработала)
	char hexChars[] = "0123456789abcdef";
	int systemEntropy;
	Helpers_GetSystemEntropySync(systemEntropy);

	for(int i = 0; i < 32; i++) {
		int randomIndex;

		if(i < 16) {
			int uRandom = GetURandomInt();
			randomIndex = ((uRandom % 16) + 16) % 16;
		} else {
			int timeEntropy = GetTime();
			int tickEntropy = GetGameTickCount();
			int random = GetRandomInt(0, 2147483647);
			int combined = (timeEntropy ^ tickEntropy) + random + systemEntropy;
			randomIndex = ((combined % 16) + 16) % 16;
		}

		char charStr[2];
		charStr[0] = hexChars[randomIndex];
		charStr[1] = '\0';
		StrCat(fingerprint, maxlen, charStr);
	}
}

/**
 * Строит SQL IN список из строки SteamID, разделенных точкой с запятой
 * Используется для построения запросов вида: WHERE authid IN ('STEAM_1:0:123', 'STEAM_1:1:456')
 * 
 * @param maDB База данных для экранирования строк
 * @param steamIdsRaw Строка со SteamID, разделенными точкой с запятой
 * @param outList Выходной буфер для SQL IN списка
 * @param maxlen Размер выходного буфера
 * @return true если список успешно построен, false если входная строка пуста
 */
stock bool BuildSQLInListFromSteamIDs(Database maDB, const char[] steamIdsRaw, char[] outList, int maxlen)
{
	outList[0] = '\0';
	if(!steamIdsRaw[0])
		return false;
	
	char steamIdArray[128][64];
	int count = ExplodeString(steamIdsRaw, ";", steamIdArray, sizeof(steamIdArray), sizeof(steamIdArray[]));
	int added = 0;
	
	for(int i = 0; i < count; i++)
	{
		TrimString(steamIdArray[i]);
		if(!steamIdArray[i][0])
			continue;
		
		char escaped[96];
		SQL_EscapeString(maDB, steamIdArray[i], escaped, sizeof(escaped));
		
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

/**
 * Извлекает часть SteamID после "STEAM_" префикса
 * Например: "STEAM_1:0:123456" -> "1:0:123456"
 * 
 * @param steamid Полный SteamID
 * @param outPart Выходной буфер для части SteamID
 * @param maxlen Размер выходного буфера
 */
stock void ExtractSteamIDPart(const char[] steamid, char[] outPart, int maxlen)
{
	strcopy(outPart, maxlen, steamid);
	if(StrContains(outPart, "STEAM_") == 0) {
		strcopy(outPart, maxlen, steamid[8]);
	}
}

/**
 * Упрощенная функция логирования с форматированием
 * Заменяет паттерн:
 *   char logMessage[512];
 *   Format(logMessage, sizeof(logMessage), "...", ...);
 *   WriteLog(logMessage, level);
 * 
 * На простой вызов:
 *   WriteLogFormatted(level, "...", ...);
 * 
 * @param level Уровень логирования
 * @param format Форматная строка (как в Format)
 * @param ... Аргументы для форматирования
 */
stock void WriteLogFormatted(LogLevel level, const char[] format, any ...)
{
	char logMessage[512];
	VFormat(logMessage, sizeof(logMessage), format, 3);
	WriteLog(logMessage, level);
}