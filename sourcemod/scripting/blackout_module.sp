#include <sourcemod>

#pragma semicolon 1
#pragma newdecls required

#define PLUGIN_VERSION "1.4.0.1"

ConVar g_cvEnabled;
ConVar g_cvWarningTime;
ConVar g_cvLogging;

bool g_bChecking[MAXPLAYERS + 1];
bool g_bAlreadyKicked[MAXPLAYERS + 1];

int g_iChecksPending[MAXPLAYERS + 1];

// Система предупреждений
Handle g_hWarningTimer[MAXPLAYERS + 1];
int g_iWarningTimeLeft[MAXPLAYERS + 1];
bool g_bHasWarning[MAXPLAYERS + 1];
bool g_bHasFailedChecks[MAXPLAYERS + 1]; // Были ли ошибки в текущей проверке

// Тестовый режим
bool g_bTestMode[MAXPLAYERS + 1];
int g_iTestAdmin[MAXPLAYERS + 1];

// Статистика для тестирования
char g_sTestResults[MAXPLAYERS + 1][3][128];
bool g_bTestComplete[MAXPLAYERS + 1][3];

public Plugin myinfo = {
    name = "[ANY] Blackout Module",
    author = "Lappland_Bro + Claude Sonnet 4.5 (Thinking)",
    description = "Проверяет клиентские CVAR для работы системы антифрода",
    version = PLUGIN_VERSION,
    url = "https://sibnet-software.ru"
};

public void OnPluginStart()
{
    CreateConVar("blackout_version", PLUGIN_VERSION, "Версия плагина Blackout Module", FCVAR_NOTIFY | FCVAR_DONTRECORD);
    g_cvEnabled = CreateConVar("blackout_enabled", "0", "Включить проверку CVAR (0 = выключено, 1 = включено)", FCVAR_NOTIFY, true, 0.0, true, 1.0);
    g_cvWarningTime = CreateConVar("blackout_warning_time", "30", "Время предупреждения перед киком (секунды)", FCVAR_NOTIFY, true, 5.0, true, 60.0);
    g_cvLogging = CreateConVar("blackout_logging", "1", "Включить логирование (0 = выключено, 1 = включено)", FCVAR_NOTIFY, true, 0.0, true, 1.0);
    
    // Тестовые команды
    RegAdminCmd("sm_blackout_check", Command_Check, ADMFLAG_GENERIC, "Проверить CVAR игрока без кика (тестовый режим)");
    RegAdminCmd("sm_blackout_stats", Command_Stats, ADMFLAG_GENERIC, "Показать статистику по всем игрокам");
    RegAdminCmd("sm_blackout_test", Command_Test, ADMFLAG_GENERIC, "Запустить тестовую проверку всех игроков");
    RegAdminCmd("sm_blackout_warn", Command_Warn, ADMFLAG_GENERIC, "Показать тестовое предупреждение игроку");
    
    AutoExecConfig(true, "blackout_module");
    
    // Проверка уже подключенных игроков при загрузке плагина
    for (int i = 1; i <= MaxClients; i++)
    {
        if (IsClientInGame(i) && !IsFakeClient(i))
        {
            g_bAlreadyKicked[i] = false;
            CheckClientCvars(i);
        }
    }
    
    // Периодическая проверка всех игроков каждые 5 секунд
    CreateTimer(5.0, Timer_PeriodicCheck, _, TIMER_REPEAT);
    
    LogMessage("Blackout Module v%s загружен", PLUGIN_VERSION);
}

void BlackoutLog(const char[] format, any ...)
{
    if (!g_cvLogging.BoolValue)
        return;
    
    char buffer[512];
    VFormat(buffer, sizeof(buffer), format, 2);
    LogMessage("%s", buffer);
}

public void OnClientPutInServer(int client)
{
    if (IsFakeClient(client))
        return;
    
    ResetClientData(client);
    
    // Задержка для стабилизации подключения
    CreateTimer(5.0, Timer_CheckClient, GetClientUserId(client));
}

public Action Timer_CheckClient(Handle timer, int userid)
{
    int client = GetClientOfUserId(userid);
    
    if (client == 0 || !IsClientInGame(client))
        return Plugin_Stop;
    
    CheckClientCvars(client);
    return Plugin_Stop;
}

public Action Timer_PeriodicCheck(Handle timer)
{
    if (!g_cvEnabled.BoolValue)
        return Plugin_Continue;
    
    for (int i = 1; i <= MaxClients; i++)
    {
        if (IsClientInGame(i) && !IsFakeClient(i) && !g_bChecking[i] && !g_bAlreadyKicked[i] && !g_bTestMode[i])
        {
            CheckClientCvars(i);
        }
    }
    
    return Plugin_Continue;
}

void CheckClientCvars(int client, bool testMode = false, int admin = 0)
{
    if (!testMode && !g_cvEnabled.BoolValue)
        return;
    
    if (IsFakeClient(client))
        return;
    
    if (g_bChecking[client])
    {
        BlackoutLog("Клиент %N уже проверяется, пропуск повторной проверки", client);
        return;
    }
    
    g_bChecking[client] = true;
    g_bAlreadyKicked[client] = false;
    g_iChecksPending[client] = 3; // Ожидаем 3 проверки
    g_bHasFailedChecks[client] = false; // Сбрасываем флаг ошибок
    
    // Установка тестового режима
    g_bTestMode[client] = testMode;
    g_iTestAdmin[client] = admin;
    
    if (testMode)
    {
        for (int i = 0; i < 3; i++)
        {
            g_bTestComplete[client][i] = false;
            g_sTestResults[client][i][0] = '\0';
        }
    }
    
    // Запрос всех трёх переменных
    QueryClientConVar(client, "cl_downloadfilter", Query_DownloadFilter);
    QueryClientConVar(client, "cl_allowdownload", Query_AllowDownload);
    QueryClientConVar(client, "cl_allowupload", Query_AllowUpload);
    
    if (!testMode)
    {
        BlackoutLog("Начата проверка CVAR для клиента %N", client);
    }
}

public void Query_DownloadFilter(QueryCookie cookie, int client, ConVarQueryResult result, const char[] cvarName, const char[] cvarValue)
{
    if (!IsClientInGame(client) || g_bAlreadyKicked[client])
    {
        DecrementChecks(client);
        return;
    }
    
    bool testMode = g_bTestMode[client];
    
    if (result != ConVarQuery_Okay)
    {
        if (testMode)
        {
            FormatEx(g_sTestResults[client][0], sizeof(g_sTestResults[][]), "[FAIL] %s: не удалось получить значение (код: %d)", cvarName, result);
            g_bTestComplete[client][0] = true;
        }
        else
        {
            BlackoutLog("Не удалось получить значение %s от клиента %N (Результат: %d)", cvarName, client, result);
            g_bHasFailedChecks[client] = true;
            StartWarningTimer(client, "cl_downloadfilter: не удалось получить значение");
        }
        DecrementChecks(client);
        return;
    }
    
    if (!StrEqual(cvarValue, "all", false))
    {
        if (testMode)
        {
            FormatEx(g_sTestResults[client][0], sizeof(g_sTestResults[][]), "[FAIL] %s = '%s' (требуется: 'all')", cvarName, cvarValue);
            g_bTestComplete[client][0] = true;
        }
        else
        {
            BlackoutLog("Клиент %N имеет неверное значение %s = '%s' (требуется: 'all')", client, cvarName, cvarValue);
            g_bHasFailedChecks[client] = true;
            StartWarningTimer(client, "cl_downloadfilter");
        }
        DecrementChecks(client);
        return;
    }
    
    if (testMode)
    {
        FormatEx(g_sTestResults[client][0], sizeof(g_sTestResults[][]), "[OK] %s = '%s'", cvarName, cvarValue);
        g_bTestComplete[client][0] = true;
    }
    else
    {
        BlackoutLog("Клиент %N прошёл проверку %s = '%s'", client, cvarName, cvarValue);
    }
    
    DecrementChecks(client);
}

public void Query_AllowDownload(QueryCookie cookie, int client, ConVarQueryResult result, const char[] cvarName, const char[] cvarValue)
{
    if (!IsClientInGame(client) || g_bAlreadyKicked[client])
    {
        DecrementChecks(client);
        return;
    }
    
    bool testMode = g_bTestMode[client];
    
    if (result != ConVarQuery_Okay)
    {
        if (testMode)
        {
            FormatEx(g_sTestResults[client][1], sizeof(g_sTestResults[][]), "[FAIL] %s: не удалось получить значение (код: %d)", cvarName, result);
            g_bTestComplete[client][1] = true;
        }
        else
        {
            BlackoutLog("Не удалось получить значение %s от клиента %N (Результат: %d)", cvarName, client, result);
            g_bHasFailedChecks[client] = true;
            StartWarningTimer(client, "cl_allowdownload: не удалось получить значение");
        }
        DecrementChecks(client);
        return;
    }
    
    int value = StringToInt(cvarValue);
    if (value != 1)
    {
        if (testMode)
        {
            FormatEx(g_sTestResults[client][1], sizeof(g_sTestResults[][]), "[FAIL] %s = '%s' (требуется: '1')", cvarName, cvarValue);
            g_bTestComplete[client][1] = true;
        }
        else
        {
            BlackoutLog("Клиент %N имеет неверное значение %s = '%s' (требуется: '1')", client, cvarName, cvarValue);
            g_bHasFailedChecks[client] = true;
            StartWarningTimer(client, "cl_allowdownload");
        }
        DecrementChecks(client);
        return;
    }
    
    if (testMode)
    {
        FormatEx(g_sTestResults[client][1], sizeof(g_sTestResults[][]), "[OK] %s = '%s'", cvarName, cvarValue);
        g_bTestComplete[client][1] = true;
    }
    else
    {
        BlackoutLog("Клиент %N прошёл проверку %s = '%s'", client, cvarName, cvarValue);
    }
    
    DecrementChecks(client);
}

public void Query_AllowUpload(QueryCookie cookie, int client, ConVarQueryResult result, const char[] cvarName, const char[] cvarValue)
{
    if (!IsClientInGame(client) || g_bAlreadyKicked[client])
    {
        DecrementChecks(client);
        return;
    }
    
    bool testMode = g_bTestMode[client];
    
    if (result != ConVarQuery_Okay)
    {
        if (testMode)
        {
            FormatEx(g_sTestResults[client][2], sizeof(g_sTestResults[][]), "[FAIL] %s: не удалось получить значение (код: %d)", cvarName, result);
            g_bTestComplete[client][2] = true;
        }
        else
        {
            BlackoutLog("Не удалось получить значение %s от клиента %N (Результат: %d)", cvarName, client, result);
            g_bHasFailedChecks[client] = true;
            StartWarningTimer(client, "cl_allowupload: не удалось получить значение");
        }
        DecrementChecks(client);
        return;
    }
    
    int value = StringToInt(cvarValue);
    if (value != 1)
    {
        if (testMode)
        {
            FormatEx(g_sTestResults[client][2], sizeof(g_sTestResults[][]), "[FAIL] %s = '%s' (требуется: '1')", cvarName, cvarValue);
            g_bTestComplete[client][2] = true;
        }
        else
        {
            BlackoutLog("Клиент %N имеет неверное значение %s = '%s' (требуется: '1')", client, cvarName, cvarValue);
            g_bHasFailedChecks[client] = true;
            StartWarningTimer(client, "cl_allowupload");
        }
        DecrementChecks(client);
        return;
    }
    
    if (testMode)
    {
        FormatEx(g_sTestResults[client][2], sizeof(g_sTestResults[][]), "[OK] %s = '%s'", cvarName, cvarValue);
        g_bTestComplete[client][2] = true;
    }
    else
    {
        BlackoutLog("Клиент %N прошёл проверку %s = '%s'", client, cvarName, cvarValue);
    }
    
    DecrementChecks(client);
}

void DecrementChecks(int client)
{
    g_iChecksPending[client]--;
    
    if (g_iChecksPending[client] <= 0)
    {
        g_bChecking[client] = false;
        
        // Если это был тестовый режим, отправить результаты админу
        if (g_bTestMode[client])
        {
            int admin = g_iTestAdmin[client];
            if (admin > 0 && IsClientInGame(admin))
            {
                bool allPassed = true;
                for (int i = 0; i < 3; i++)
                {
                    if (g_sTestResults[client][i][0] != '\0' && StrContains(g_sTestResults[client][i], "[FAIL]") != -1)
                    {
                        allPassed = false;
                    }
                }
                
                if (allPassed)
                {
                    PrintToChat(admin, "\x04[Blackout]\x01 Игрок \x03%N\x01 прошёл проверку:", client);
                }
                else
                {
                    PrintToChat(admin, "\x04[Blackout]\x01 Игрок \x03%N\x01 \x02НЕ ПРОШЁЛ\x01 проверку:", client);
                }
                
                for (int i = 0; i < 3; i++)
                {
                    if (g_sTestResults[client][i][0] != '\0')
                    {
                        PrintToChat(admin, "  %s", g_sTestResults[client][i]);
                    }
                }
            }
            
            g_bTestMode[client] = false;
            g_iTestAdmin[client] = 0;
        }
        else if (!g_bAlreadyKicked[client])
        {
            // Если не было ошибок в текущей проверке - все CVAR верные
            if (!g_bHasFailedChecks[client])
            {
                BlackoutLog("Клиент %N успешно прошёл все проверки CVAR", client);
                // Отменяем предупреждение, если оно было активно (игрок исправил CVAR)
                if (g_bHasWarning[client])
                {
                    CancelWarningTimer(client);
                    PrintCenterText(client, "CVAR проверены успешно! Предупреждение отменено.");
                }
            }
            // Если были ошибки, предупреждение продолжит работать
        }
    }
}

void StartWarningTimer(int client, const char[] reason = "")
{
    if (!IsClientInGame(client) || g_bAlreadyKicked[client] || g_bHasWarning[client])
        return;
    
    // Отменяем предыдущее предупреждение, если есть
    CancelWarningTimer(client);
    
    g_bHasWarning[client] = true;
    g_iWarningTimeLeft[client] = g_cvWarningTime.IntValue;
    
    // Показываем первое предупреждение сразу
    ShowWarningMessage(client, g_iWarningTimeLeft[client]);
    
    // Запускаем таймер обратного отсчёта (каждую секунду)
    DataPack pack;
    g_hWarningTimer[client] = CreateDataTimer(1.0, Timer_WarningCountdown, pack, TIMER_REPEAT);
    pack.WriteCell(GetClientUserId(client));
    pack.WriteString(reason);
    
    BlackoutLog("Запущено предупреждение для клиента %N. Время до кика: %d секунд", client, g_iWarningTimeLeft[client]);
}

public Action Timer_WarningCountdown(Handle timer, DataPack pack)
{
    pack.Reset();
    int userid = pack.ReadCell();
    int client = GetClientOfUserId(userid);
    
    if (client == 0 || !IsClientInGame(client) || g_bAlreadyKicked[client])
    {
        CancelWarningTimer(client);
        return Plugin_Stop;
    }
    
    g_iWarningTimeLeft[client]--;
    
    if (g_iWarningTimeLeft[client] <= 0)
    {
        // Время вышло - кикаем
        char reason[64];
        pack.ReadString(reason, sizeof(reason));
        KickClientWithMessage(client, reason);
        CancelWarningTimer(client);
        return Plugin_Stop;
    }
    
    // Показываем предупреждение каждую секунду
    ShowWarningMessage(client, g_iWarningTimeLeft[client]);
    
    return Plugin_Continue;
}

void ShowWarningMessage(int client, int timeLeft)
{
    char message[256];
    FormatEx(message, sizeof(message), "ВНИМАНИЕ! Вы будете кикнуты через %d секунд!\nИзмените CVAR:\ncl_downloadfilter = all\ncl_allowdownload = 1\ncl_allowupload = 1", timeLeft);
    
    PrintHintText(client, message);
    PrintCenterText(client, "ВНИМАНИЕ! Вы будете кикнуты через %d секунд!\nИзмените CVAR: cl_downloadfilter = all, cl_allowdownload = 1, cl_allowupload = 1", timeLeft);
}

void CancelWarningTimer(int client)
{
    if (g_hWarningTimer[client] != null)
    {
        KillTimer(g_hWarningTimer[client]);
        g_hWarningTimer[client] = null;
    }
    
    g_bHasWarning[client] = false;
    g_iWarningTimeLeft[client] = 0;
}

void KickClientWithMessage(int client, const char[] reason = "")
{
    if (!IsClientInGame(client))
        return;
    
    if (g_bAlreadyKicked[client])
        return;
    
    // Отменяем предупреждение перед киком
    CancelWarningTimer(client);
    
    g_bAlreadyKicked[client] = true;
    g_bChecking[client] = false;
    
    if (strlen(reason) > 0)
    {
        BlackoutLog("Кик клиента %N. Причина: %s", client, reason);
    }
    
    KickClient(client, "Security System:\nПропишите в консоль:\ncl_downloadfilter all\ncl_allowdownload 1\ncl_allowupload 1");
}

void ResetClientData(int client)
{
    CancelWarningTimer(client);
    
    g_bChecking[client] = false;
    g_bAlreadyKicked[client] = false;
    g_iChecksPending[client] = 0;
    g_bTestMode[client] = false;
    g_iTestAdmin[client] = 0;
    g_bHasWarning[client] = false;
    g_iWarningTimeLeft[client] = 0;
    g_bHasFailedChecks[client] = false;
    
    for (int i = 0; i < 3; i++)
    {
        g_bTestComplete[client][i] = false;
        g_sTestResults[client][i][0] = '\0';
    }
}

public void OnClientDisconnect(int client)
{
    ResetClientData(client);
}

// ============================================
// ТЕСТОВЫЕ КОМАНДЫ
// ============================================

public Action Command_Check(int client, int args)
{
    if (args < 1)
    {
        ReplyToCommand(client, "[Blackout] Использование: sm_blackout_check <имя|#userid|@filter>");
        return Plugin_Handled;
    }
    
    char arg[64];
    GetCmdArg(1, arg, sizeof(arg));
    
    char target_name[MAX_TARGET_LENGTH];
    int target_list[MAXPLAYERS], target_count;
    bool tn_is_ml;
    
    if ((target_count = ProcessTargetString(
        arg,
        client,
        target_list,
        MAXPLAYERS,
        COMMAND_FILTER_NO_BOTS,
        target_name,
        sizeof(target_name),
        tn_is_ml)) <= 0)
    {
        ReplyToTargetError(client, target_count);
        return Plugin_Handled;
    }
    
    PrintToChat(client, "\x04[Blackout]\x01 Запуск тестовой проверки для \x03%s\x01...", target_name);
    
    for (int i = 0; i < target_count; i++)
    {
        CheckClientCvars(target_list[i], true, client);
    }
    
    return Plugin_Handled;
}

public Action Command_Stats(int client, int args)
{
    int total = 0;
    int checking = 0;
    
    PrintToChat(client, "\x04[Blackout]\x01 Статистика игроков на сервере:");
    PrintToChat(client, "──────────────────────────────────");
    
    for (int i = 1; i <= MaxClients; i++)
    {
        if (IsClientInGame(i) && !IsFakeClient(i))
        {
            total++;
            if (g_bChecking[i])
            {
                checking++;
                PrintToChat(client, "\x03%N\x01 - \x07проверяется...", i);
            }
        }
    }
    
    PrintToChat(client, "Всего игроков: \x03%d\x01 | Проверяется: \x07%d", total, checking);
    PrintToChat(client, "Плагин: \x03%s", g_cvEnabled.BoolValue ? "ВКЛЮЧЕН" : "ВЫКЛЮЧЕН");
    
    return Plugin_Handled;
}

public Action Command_Test(int client, int args)
{
    if (!client)
    {
        ReplyToCommand(client, "[Blackout] Эта команда доступна только в игре.");
        return Plugin_Handled;
    }
    
    int total = 0;
    
    PrintToChat(client, "\x04[Blackout]\x01 Запуск полной проверки всех игроков...");
    PrintToChat(client, "\x06ВНИМАНИЕ:\x01 Плагин сейчас \x03%s\x01, игроки НЕ будут кикнуты!", g_cvEnabled.BoolValue ? "ВКЛЮЧЕН" : "ВЫКЛЮЧЕН");
    
    for (int i = 1; i <= MaxClients; i++)
    {
        if (IsClientInGame(i) && !IsFakeClient(i))
        {
            total++;
            CheckClientCvars(i, true, client);
        }
    }
    
    PrintToChat(client, "\x04[Blackout]\x01 Проверка запущена для \x03%d\x01 игроков. Результаты появятся через несколько секунд.", total);
    
    // Отложенная статистика
    CreateTimer(5.0, Timer_ShowTestStats, GetClientUserId(client));
    
    return Plugin_Handled;
}

public Action Timer_ShowTestStats(Handle timer, int userid)
{
    int client = GetClientOfUserId(userid);
    
    if (client == 0 || !IsClientInGame(client))
        return Plugin_Stop;
    
    int passed = 0;
    int failed = 0;
    
    for (int i = 1; i <= MaxClients; i++)
    {
        if (IsClientInGame(i) && !IsFakeClient(i))
        {
            bool allPassed = true;
            
            for (int j = 0; j < 3; j++)
            {
                if (g_sTestResults[i][j][0] != '\0' && StrContains(g_sTestResults[i][j], "[FAIL]") != -1)
                {
                    allPassed = false;
                    break;
                }
            }
            
            if (allPassed && g_sTestResults[i][0][0] != '\0')
            {
                passed++;
            }
            else if (!allPassed && g_sTestResults[i][0][0] != '\0')
            {
                failed++;
            }
        }
    }
    
    PrintToChat(client, "═══════════════════════════════════");
    PrintToChat(client, "\x04[Blackout]\x01 Результаты тестовой проверки:");
    PrintToChat(client, "  Прошли: \x04%d", passed);
    PrintToChat(client, "  Не прошли: \x02%d", failed);
    
    if (failed > 0)
    {
        float percentage = (float(failed) / float(passed + failed)) * 100.0;
        PrintToChat(client, "  \x02ВНИМАНИЕ: %.1f%%\x01 игроков будут кикнуты при включении!", percentage);
    }
    else
    {
        PrintToChat(client, "  \x04Все игроки прошли проверку - безопасно включать!");
    }
    
    PrintToChat(client, "═══════════════════════════════════");
    
    return Plugin_Stop;
}

public Action Command_Warn(int client, int args)
{
    if (!client)
    {
        ReplyToCommand(client, "[Blackout] Эта команда доступна только в игре.");
        return Plugin_Handled;
    }
    
    int target = client;
    
    if (args >= 1)
    {
        char arg[64];
        GetCmdArg(1, arg, sizeof(arg));
        
        char target_name[MAX_TARGET_LENGTH];
        int target_list[MAXPLAYERS], target_count;
        bool tn_is_ml;
        
        if ((target_count = ProcessTargetString(
            arg,
            client,
            target_list,
            MAXPLAYERS,
            COMMAND_FILTER_NO_BOTS,
            target_name,
            sizeof(target_name),
            tn_is_ml)) <= 0)
        {
            ReplyToTargetError(client, target_count);
            return Plugin_Handled;
        }
        
        target = target_list[0];
    }
    
    if (!IsClientInGame(target) || IsFakeClient(target))
    {
        ReplyToCommand(client, "[Blackout] Целевой игрок не найден или является ботом.");
        return Plugin_Handled;
    }
    
    // Отменяем предыдущее предупреждение, если есть
    CancelWarningTimer(target);
    
    // Устанавливаем тестовое предупреждение
    g_bHasWarning[target] = true;
    g_iWarningTimeLeft[target] = g_cvWarningTime.IntValue;
    
    // Показываем предупреждение
    ShowWarningMessage(target, g_iWarningTimeLeft[target]);
    
    // Запускаем таймер обратного отсчёта
    DataPack pack;
    g_hWarningTimer[target] = CreateDataTimer(1.0, Timer_TestWarningCountdown, pack, TIMER_REPEAT);
    pack.WriteCell(GetClientUserId(target));
    pack.WriteCell(GetClientUserId(client)); // Админ, запустивший тест
    
    PrintToChat(client, "\x04[Blackout]\x01 Тестовое предупреждение запущено для \x03%N\x01", target);
    PrintToChat(client, "\x06ВНИМАНИЕ:\x01 Это тестовое предупреждение - игрок НЕ будет кикнут!");
    
    return Plugin_Handled;
}

public Action Timer_TestWarningCountdown(Handle timer, DataPack pack)
{
    pack.Reset();
    int target_userid = pack.ReadCell();
    int admin_userid = pack.ReadCell();
    
    int target = GetClientOfUserId(target_userid);
    int admin = GetClientOfUserId(admin_userid);
    
    if (target == 0 || !IsClientInGame(target))
    {
        if (admin > 0 && IsClientInGame(admin))
        {
            PrintToChat(admin, "\x04[Blackout]\x01 Тестовое предупреждение остановлено - игрок отключился.");
        }
        return Plugin_Stop;
    }
    
    g_iWarningTimeLeft[target]--;
    
    if (g_iWarningTimeLeft[target] <= 0)
    {
        // Время вышло - останавливаем тест
        CancelWarningTimer(target);
        PrintCenterText(target, "Тестовое предупреждение завершено (игрок НЕ был кикнут)");
        
        if (admin > 0 && IsClientInGame(admin))
        {
            PrintToChat(admin, "\x04[Blackout]\x01 Тестовое предупреждение для \x03%N\x01 завершено.", target);
        }
        
        return Plugin_Stop;
    }
    
    // Показываем предупреждение каждую секунду
    ShowWarningMessage(target, g_iWarningTimeLeft[target]);
    
    return Plugin_Continue;
}