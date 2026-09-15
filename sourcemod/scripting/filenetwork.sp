#include <sourcemod>
#include <sdktools>
#include <dhooks>

#pragma semicolon 1
#pragma newdecls required

#define PLUGIN_VERSION "1.8.0.0"
#define MAX_FILES_PER_CLIENT 50
#define MAX_FILE_SIZE (1 * 1024 * 1024)

enum struct FileEnum
{
	int UserID;
	char Filename[PLATFORM_MAX_PATH];
	Handle Plugin;
	Function Func;
	any Data;
	int Id;
	bool IsOurRequest;
}

Handle SDKGetPlayerNetInfo;
Handle SDKSendFile;
Handle SDKRequestFile;
Handle SDKIsFileInWaitingList;
Handle SDKGetNetChannel;
Address EngineAddress;

int TransferID;
ArrayList SendListing;
ArrayList RequestListing;
EngineVersion g_EngineVersion;

ConVar cv_DebugMode;

void DebugLog(const char[] format, any ...)
{
	if(cv_DebugMode && cv_DebugMode.BoolValue)
	{
		char buffer[512];
		VFormat(buffer, sizeof(buffer), format, 2);
		LogMessage("[FileNetwork] %s", buffer);
	}
}

methodmap CNetChan
{
	public CNetChan(int client)
	{
		if(!SDKGetPlayerNetInfo || EngineAddress == Address_Null)
			return view_as<CNetChan>(Address_Null);
		
		Address result = SDKCall(SDKGetPlayerNetInfo, EngineAddress, client);
		if(result == Address_Null)
			return view_as<CNetChan>(Address_Null);
		
		return view_as<CNetChan>(result);
	}

	public bool SendFile(const char[] filename)
	{
		bool result = SDKCall(SDKSendFile, this, filename, TransferID++);
		if(TransferID >= 0x7FFFFFFF)
			TransferID = 0;
		return result;
	}
	
	public int RequestFile(const char[] filename)
	{
		return SDKCall(SDKRequestFile, this, filename);
	}
	
	public bool IsFileInWaitingList(const char[] filename)
	{
		return SDKCall(SDKIsFileInWaitingList, this, filename);
	}
	
	property Address Address
	{
		public get()
		{
			return view_as<Address>(this);
		}
	}
}

public Plugin myinfo =
{
	name = "File Network",
	author = "Batfoxkid, Optimized by Community",
	description = "Server-Client file transfer without loading screen",
	version = PLUGIN_VERSION,
	url = "github.com/Batfoxkid/File-Network"
}

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	CreateNative("FileNet_SendFile", Native_SendFile);
	CreateNative("FileNet_RequestFile", Native_RequestFile);
	CreateNative("FileNet_IsFileInWaitingList", Native_IsFileInWaitingList);
	CreateNative("FileNet_GetNetChanPtr", Native_GetNetChanPtr);
	RegPluginLibrary("filenetwork");
	return APLRes_Success;
}

public void OnPluginStart()
{
	g_EngineVersion = GetEngineVersion();
	
	if(g_EngineVersion != Engine_TF2)
	{
		SetFailState("This plugin only supports Team Fortress 2");
		return;
	}

	GameData gamedata = new GameData("filenetwork");
	if(!gamedata)
	{
		SetFailState("Failed to load filenetwork.txt gamedata");
		return;
	}

	char identifier[64];
	if(!gamedata.GetKeyValue("EngineInterface", identifier, sizeof(identifier)))
	{
		delete gamedata;
		SetFailState("[Gamedata] Could not find EngineInterface");
		return;
	}

	StartPrepSDKCall(SDKCall_Static);
	PrepSDKCall_SetFromConf(gamedata, SDKConf_Signature, "CreateInterface");
	PrepSDKCall_AddParameter(SDKType_String, SDKPass_Pointer);
	PrepSDKCall_AddParameter(SDKType_PlainOldData, SDKPass_Pointer, VDECODE_FLAG_ALLOWNULL);
	PrepSDKCall_SetReturnInfo(SDKType_PlainOldData, SDKPass_Plain);
	Handle sdkcall = EndPrepSDKCall();
	if(!sdkcall)
	{
		delete gamedata;
		SetFailState("[Gamedata] Could not find CreateInterface");
		return;
	}

	EngineAddress = SDKCall(sdkcall, identifier, 0);
	if(EngineAddress == Address_Null)
	{
		delete sdkcall;
		delete gamedata;
		SetFailState("[Gamedata] EngineInterface is incorrect for mod");
		return;
	}

	delete sdkcall;

	bool failed;

	StartPrepSDKCall(SDKCall_Raw);
	PrepSDKCall_SetFromConf(gamedata, SDKConf_Virtual, "GetPlayerNetInfo");
	PrepSDKCall_AddParameter(SDKType_PlainOldData, SDKPass_Plain);
	PrepSDKCall_SetReturnInfo(SDKType_PlainOldData, SDKPass_Plain);
	SDKGetPlayerNetInfo = EndPrepSDKCall();
	if(!SDKGetPlayerNetInfo)
	{
		LogError("[Gamedata] Could not find GetPlayerNetInfo");
		failed = true;
	}

	StartPrepSDKCall(SDKCall_Raw);
	PrepSDKCall_SetFromConf(gamedata, SDKConf_Signature, "CNetChan::SendFile");
	PrepSDKCall_AddParameter(SDKType_String, SDKPass_Pointer);
	PrepSDKCall_AddParameter(SDKType_PlainOldData, SDKPass_Plain);
	PrepSDKCall_SetReturnInfo(SDKType_Bool, SDKPass_ByValue);
	SDKSendFile = EndPrepSDKCall();
	if(!SDKSendFile)
	{
		LogError("[Gamedata] Could not find CNetChan::SendFile");
		failed = true;
	}

	StartPrepSDKCall(SDKCall_Raw);
	PrepSDKCall_SetFromConf(gamedata, SDKConf_Signature, "CNetChan::RequestFile");
	PrepSDKCall_AddParameter(SDKType_String, SDKPass_Pointer);
	PrepSDKCall_SetReturnInfo(SDKType_PlainOldData, SDKPass_ByValue);
	SDKRequestFile = EndPrepSDKCall();
	if(!SDKRequestFile)
	{
		LogError("[Gamedata] Could not find CNetChan::RequestFile");
		failed = true;
	}

	StartPrepSDKCall(SDKCall_Raw);
	PrepSDKCall_SetFromConf(gamedata, SDKConf_Signature, "CNetChan::IsFileInWaitingList");
	PrepSDKCall_AddParameter(SDKType_String, SDKPass_Pointer);
	PrepSDKCall_SetReturnInfo(SDKType_Bool, SDKPass_ByValue);
	SDKIsFileInWaitingList = EndPrepSDKCall();
	if(!SDKIsFileInWaitingList)
	{
		LogError("[Gamedata] Could not find CNetChan::IsFileInWaitingList");
		failed = true;
	}

	StartPrepSDKCall(SDKCall_Raw);
	PrepSDKCall_SetFromConf(gamedata, SDKConf_Signature, "CBaseClient::GetNetChannel");
	PrepSDKCall_SetReturnInfo(SDKType_PlainOldData, SDKPass_ByValue);
	SDKGetNetChannel = EndPrepSDKCall();
	if(!SDKGetNetChannel)
	{
		LogError("[Gamedata] Could not find CBaseClient::GetNetChannel");
		failed = true;
	}

	DynamicDetour detour = DynamicDetour.FromConf(gamedata, "CGameClient::FileReceived");
	if(detour)
	{
		if(!detour.Enable(Hook_Post, OnFileReceived))
		{
			LogError("[Gamedata] Failed to enable detour: CGameClient::FileReceived");
			failed = true;
		}
		delete detour;
	}
	else
	{
		LogError("[Gamedata] Could not find CGameClient::FileReceived");
		failed = true;
	}

	detour = DynamicDetour.FromConf(gamedata, "CGameClient::FileDenied");
	if(detour)
	{
		if(!detour.Enable(Hook_Post, OnFileDenied))
		{
			LogError("[Gamedata] Failed to enable detour: CGameClient::FileDenied");
			failed = true;
		}
		delete detour;
	}
	else
	{ 
		LogError("[Gamedata] Could not find CGameClient::FileDenied");
		failed = true;
	}

	if(failed)
	{
		if(SDKGetPlayerNetInfo) delete SDKGetPlayerNetInfo;
		if(SDKSendFile) delete SDKSendFile;
		if(SDKRequestFile) delete SDKRequestFile;
		if(SDKIsFileInWaitingList) delete SDKIsFileInWaitingList;
		if(SDKGetNetChannel) delete SDKGetNetChannel;
		delete gamedata;
		SetFailState("Gamedata failed, see error logs");
		return;
	}

	delete gamedata;

	SendListing = new ArrayList(sizeof(FileEnum));
	RequestListing = new ArrayList(sizeof(FileEnum));
	
	cv_DebugMode = CreateConVar("sm_filenet_debug", "0", 
		"Enable debug logging for FileNetwork plugin", 
		FCVAR_NONE, true, 0.0, true, 1.0);
	
	RegAdminCmd("sm_filenet_send", Command_TestSend, ADMFLAG_ROOT, "Test file send to client");
	RegAdminCmd("sm_filenet_request", Command_TestRequest, ADMFLAG_ROOT, "Test file request from client");
	RegAdminCmd("sm_filenet_status", Command_Status, ADMFLAG_ROOT, "Show client file transfer status");
	RegAdminCmd("sm_filenet_clear", Command_Clear, ADMFLAG_ROOT, "Clear all queues for a client");
}

public void OnAllPluginsLoaded()
{
	ConVar cv;
	
	cv = FindConVar("sv_allowdownload");
	if(cv != null)
		cv.SetInt(1);
	
	cv = FindConVar("sv_allowupload");
	if(cv != null)
		cv.SetInt(1);
}

public Action Command_Clear(int client, int args)
{
	int target = client;
	if(args > 0)
		target = GetCmdArgInt(1);
	
	if(target < 1 || target > MaxClients || !IsClientInGame(target))
	{
		ReplyToCommand(client, "[FileNetwork] Invalid client");
		return Plugin_Handled;
	}
	
	int userid = GetClientUserId(target);
	int cleared = 0;
	
	if(SendListing != null)
	{
		for(int i = SendListing.Length - 1; i >= 0; i--)
		{
			FileEnum info;
			SendListing.GetArray(i, info);
			if(info.UserID == userid)
			{
				SendListing.Erase(i);
				cleared++;
			}
		}
	}
	
	if(RequestListing != null)
	{
		for(int i = RequestListing.Length - 1; i >= 0; i--)
		{
			FileEnum info;
			RequestListing.GetArray(i, info);
			if(info.UserID == userid)
			{
				RequestListing.Erase(i);
				cleared++;
			}
		}
	}
	
	ReplyToCommand(client, "[FileNetwork] Cleared %d entries for %N", cleared, target);
	return Plugin_Handled;
}

public Action Command_Status(int client, int args)
{
	int target = client;
	if(args > 0)
		target = GetCmdArgInt(1);
	
	if(target < 1 || target > MaxClients || !IsClientInGame(target))
	{
		ReplyToCommand(client, "[FileNetwork] Invalid client");
		return Plugin_Handled;
	}
	
	int userid = GetClientUserId(target);
	CNetChan chan = CNetChan(target);
	ReplyToCommand(client, "[FileNetwork] Status for %N (UserID: %d):", target, userid);
	ReplyToCommand(client, "  NetChan: %x", chan.Address);
	ReplyToCommand(client, "  Ping: %.0fms | Loss: %.1f%% | Choke: %.1f%%",
		GetClientAvgLatency(target, NetFlow_Outgoing) * 1000,
		GetClientAvgLoss(target, NetFlow_Outgoing) * 100,
		GetClientAvgChoke(target, NetFlow_Outgoing) * 100);
	
	int queued = 0;
	if(SendListing != null)
	{
		for(int i = 0; i < SendListing.Length; i++)
		{
			FileEnum info;
			SendListing.GetArray(i, info);
			if(info.UserID == userid)
			{
				queued++;
				ReplyToCommand(client, "    Send[%d]: %s", i, info.Filename);
			}
		}
	}
	ReplyToCommand(client, "  Total queued sends: %d", queued);
	
	int requested = 0;
	if(RequestListing != null)
	{
		for(int i = 0; i < RequestListing.Length; i++)
		{
			FileEnum info;
			RequestListing.GetArray(i, info);
			if(info.UserID == userid)
			{
				requested++;
				ReplyToCommand(client, "    Request[%d]: %s (ID:%d, our:%s)", 
					i, info.Filename, info.Id, info.IsOurRequest ? "YES" : "NO");
			}
		}
	}
	ReplyToCommand(client, "  Total active requests: %d", requested);
	
	return Plugin_Handled;
}

public Action Command_TestSend(int client, int args)
{
	if(args < 1)
	{
		ReplyToCommand(client, "Usage: sm_filenet_send <filename>");
		return Plugin_Handled;
	}

	char buffer[PLATFORM_MAX_PATH];
	GetCmdArgString(buffer, sizeof(buffer));
	StripQuotes(buffer);
	TrimString(buffer);

	if(!IsValidFilePath(buffer))
	{
		ReplyToCommand(client, "[FileNetwork] Invalid file path");
		return Plugin_Handled;
	}

	CNetChan chan = CNetChan(client);
	if(chan.Address == Address_Null)
	{
		ReplyToCommand(client, "[FileNetwork] Invalid NetChannel address");
	}
	else if(chan.SendFile(buffer))
	{
		ReplyToCommand(client, "[FileNetwork] File sent to client");
	}
	else
	{
		ReplyToCommand(client, "[FileNetwork] Failed to send file");
	}
	return Plugin_Handled;
}

public Action Command_TestRequest(int client, int args)
{
	if(args < 1)
	{
		ReplyToCommand(client, "Usage: sm_filenet_request <filename>");
		return Plugin_Handled;
	}

	char buffer[PLATFORM_MAX_PATH];
	GetCmdArgString(buffer, sizeof(buffer));
	StripQuotes(buffer);
	TrimString(buffer);

	if(!IsValidFilePath(buffer))
	{
		ReplyToCommand(client, "[FileNetwork] Invalid file path");
		return Plugin_Handled;
	}

	CNetChan chan = CNetChan(client);
	if(chan.Address == Address_Null)
	{
		ReplyToCommand(client, "[FileNetwork] Invalid NetChannel address");
	}
	else
	{
		int id = chan.RequestFile(buffer);
		ReplyToCommand(client, "[FileNetwork] Requested file (ID: %d)", id);
	}

	return Plugin_Handled;
}

public void OnClientDisconnect(int client)
{
	if(client < 1 || client > MaxClients)
		return;
	
	int userid = GetClientUserId(client);
	if(userid == 0)
		return;
	
	FileEnum info;
	
	if(SendListing != null)
	{
		for(int i = SendListing.Length - 1; i >= 0; i--)
		{
			SendListing.GetArray(i, info);
			if(info.UserID == userid)
			{
				SendListing.Erase(i);
				DebugLog("Cleaned send listing for UserID %d: %s", userid, info.Filename);
				CallSentFileFinish(info, false);
			}
		}
	}

	if(RequestListing != null)
	{
		for(int i = RequestListing.Length - 1; i >= 0; i--)
		{
			RequestListing.GetArray(i, info);
			if(info.UserID == userid)
			{
				RequestListing.Erase(i);
				DebugLog("Cleaned request listing for UserID %d: %s", userid, info.Filename);
				CallRequestFileFinish(info, false);
			}
		}
	}
}

public MRESReturn OnFileReceived(Address address, DHookParam param)
{
	if(!SDKGetNetChannel || address == Address_Null)
		return MRES_Ignored;
	
	char receivedFileName[PLATFORM_MAX_PATH];
	param.GetString(1, receivedFileName, sizeof(receivedFileName));
	if(receivedFileName[0] == '\0')
		return MRES_Ignored;
	
	int id = param.Get(2);
	
	if(IsEngineFileRequest(receivedFileName))
		return MRES_Ignored;
	
	CNetChan chan = view_as<CNetChan>(SDKCall(SDKGetNetChannel, address));
	if(chan.Address == Address_Null)
		return MRES_Ignored;

	int client = FindClientByNetChan(chan.Address);
	if(client == -1 || !IsClientInGame(client))
		return MRES_Ignored;
	
	int userid = GetClientUserId(client);
	if(userid == 0)
		return MRES_Ignored;
	
	if(RequestListing == null || RequestListing.Length == 0)
		return MRES_Ignored;
	
	for(int i = 0; i < RequestListing.Length; i++)
	{
		FileEnum info;
		RequestListing.GetArray(i, info);
		
		if(!info.IsOurRequest)
			continue;
		
		if(info.UserID == userid && info.Id == id && StrEqual(info.Filename, receivedFileName, false))
		{
			DebugLog("File received: client=%d, file=%s, ID=%d", client, info.Filename, info.Id);
			RequestListing.Erase(i);
			CallRequestFileFinish(info, true);
			break;
		}
	}
	
	return MRES_Ignored;
}

public MRESReturn OnFileDenied(Address address, DHookParam param)
{
	if(!SDKGetNetChannel || address == Address_Null)
		return MRES_Ignored;
	
	char deniedFileName[PLATFORM_MAX_PATH];
	param.GetString(1, deniedFileName, sizeof(deniedFileName));
	if(deniedFileName[0] == '\0')
		return MRES_Ignored;
	
	int id = param.Get(2);
	
	if(IsEngineFileRequest(deniedFileName))
		return MRES_Ignored;
	
	CNetChan chan = view_as<CNetChan>(SDKCall(SDKGetNetChannel, address));
	if(chan.Address == Address_Null)
		return MRES_Ignored;

	int client = FindClientByNetChan(chan.Address);
	if(client == -1 || !IsClientInGame(client))
		return MRES_Ignored;
	
	int userid = GetClientUserId(client);
	if(userid == 0)
		return MRES_Ignored;
	
	if(RequestListing == null || RequestListing.Length == 0)
		return MRES_Ignored;
	
	for(int i = 0; i < RequestListing.Length; i++)
	{
		FileEnum info;
		RequestListing.GetArray(i, info);
		
		if(!info.IsOurRequest)
			continue;
		
		if(info.UserID == userid && info.Id == id && StrEqual(info.Filename, deniedFileName, false))
		{
			DebugLog("File denied: client=%d, file=%s, ID=%d", client, info.Filename, info.Id);
			RequestListing.Erase(i);
			CallRequestFileFinish(info, false);
			break;
		}
	}
	
	return MRES_Ignored;
}

int FindClientByNetChan(Address netChanAddr)
{
	if(netChanAddr == Address_Null)
		return -1;
	
	for(int i = 1; i <= MaxClients; i++)
	{
		if(!IsClientInGame(i))
			continue;
		
		CNetChan clientChan = CNetChan(i);
		if(clientChan.Address != Address_Null && clientChan.Address == netChanAddr)
			return i;
	}
	return -1;
}

bool IsEngineFileRequest(const char[] filename)
{
	if(StrContains(filename, "user_custom/") != -1)
		return true;
	if(StrContains(filename, "temp/") != -1)
		return true;
	if(StrContains(filename, ".tmp") != -1)
		return true;
	if(StrContains(filename, "decals/") != -1)
		return true;
	if(StrContains(filename, "materials/") != -1 && StrContains(filename, "temp") != -1)
		return true;
	if(StrContains(filename, "download/") != -1)
		return true;
	if(StrContains(filename, ".dat") != -1)
		return true;
	
	return false;
}

void CallSentFileFinish(const FileEnum info, bool success)
{
	int client = GetClientOfUserId(info.UserID);
	
	if(info.Func != INVALID_FUNCTION && info.Plugin != null)
	{
		if(!IsPluginValid(info.Plugin))
			return;
		
		if(client > 0 && client <= MaxClients && IsClientInGame(client))
		{
			Call_StartFunction(info.Plugin, info.Func);
			Call_PushCell(client);
			Call_PushString(info.Filename);
			Call_PushCell(success);
			Call_PushCell(info.Data);
			Call_Finish();
		}
	}
}

void CallRequestFileFinish(const FileEnum info, bool success)
{
	int client = GetClientOfUserId(info.UserID);
	
	if(info.Func != INVALID_FUNCTION && info.Plugin != null)
	{
		if(!IsPluginValid(info.Plugin))
			return;
		
		if(client > 0 && client <= MaxClients && IsClientInGame(client))
		{
			Call_StartFunction(info.Plugin, info.Func);
			Call_PushCell(client);
			Call_PushString(info.Filename);
			Call_PushCell(info.Id);
			Call_PushCell(success);
			Call_PushCell(info.Data);
			Call_Finish();
		}
	}
}

bool IsPluginValid(Handle plugin)
{
	Handle hIterator = GetPluginIterator();
	bool pluginValid = false;
	
	while(MorePlugins(hIterator))
	{
		Handle pl = ReadPlugin(hIterator);
		if(pl == plugin)
		{
			PluginStatus status = GetPluginStatus(pl);
			pluginValid = (status == Plugin_Running);
			break;
		}
	}
	delete hIterator;
	
	return pluginValid;
}

void StartNative()
{
	if(!SendListing)
		ThrowNativeError(SP_ERROR_NATIVE, "Please wait until OnAllPluginsLoaded");
}

int GetNativeClient(int param)
{
	int client = GetNativeCell(param);
	if(client < 1 || client > MaxClients)
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", client);

	if(!IsClientInGame(client))
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in-game", client);

	if(IsFakeClient(client))
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is a bot player", client);

	return client;
}

bool FileExistsForUserID(int userid, const char[] filename)
{
	if(SendListing == null || SendListing.Length == 0)
		return false;
	
	for(int i = 0; i < SendListing.Length; i++)
	{
		FileEnum info;
		SendListing.GetArray(i, info);
		if(info.UserID == userid && StrEqual(info.Filename, filename, false))
			return true;
	}
	return false;
}

bool RequestExistsForUserID(int userid, const char[] filename)
{
	if(RequestListing == null || RequestListing.Length == 0)
		return false;
	
	for(int i = 0; i < RequestListing.Length; i++)
	{
		FileEnum info;
		RequestListing.GetArray(i, info);
		if(info.UserID == userid && StrEqual(info.Filename, filename, false))
			return true;
	}
	return false;
}

int GetClientQueuedFiles(int userid)
{
	if(SendListing == null)
		return 0;
	
	int count = 0;
	for(int i = 0; i < SendListing.Length; i++)
	{
		FileEnum info;
		SendListing.GetArray(i, info);
		if(info.UserID == userid)
			count++;
	}
	return count;
}

bool IsValidFilePath(const char[] path)
{
	if(path[0] == '\0')
		return false;
	
	int len = strlen(path);
	if(len == 0 || len >= PLATFORM_MAX_PATH)
		return false;
	
	// Проверка на path traversal атаки
	if(StrContains(path, "../") != -1 || StrContains(path, "..\\") != -1)
		return false;
	
	if(StrContains(path, "//") != -1 || StrContains(path, "\\\\") != -1)
		return false;
	
	// Проверка на абсолютные пути
	if(path[0] == '/' || path[0] == '\\')
		return false;
	
	if(len > 1 && path[1] == ':')
		return false;
	
	// Проверка на null-байты и опасные символы
	for(int i = 0; i < len; i++)
	{
		if(path[i] == '\0' && i < len - 1)
			return false;
		if(path[i] < 32 && path[i] != '\t' && path[i] != '\n' && path[i] != '\r')
			return false;
	}
	
	return true;
}

public any Native_SendFile(Handle plugin, int params)
{
	StartNative();

	int client = GetNativeClient(1);
	int userid = GetClientUserId(client);
	FileEnum info;
	info.UserID = userid;
	GetNativeString(2, info.Filename, sizeof(info.Filename));

	if(!IsValidFilePath(info.Filename))
	{
		DebugLog("Native_SendFile: Invalid file path: %s", info.Filename);
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid file path: %s", info.Filename);
		return false;
	}

	if(!FileExists(info.Filename))
	{
		DebugLog("Native_SendFile: File does not exist: %s", info.Filename);
		ThrowNativeError(SP_ERROR_NATIVE, "File does not exist: %s", info.Filename);
		return false;
	}

	if(FileExistsForUserID(userid, info.Filename))
	{
		DebugLog("Native_SendFile: File already in queue: %s", info.Filename);
		ThrowNativeError(SP_ERROR_NATIVE, "File already in queue: %s", info.Filename);
		return false;
	}

	if(GetClientQueuedFiles(userid) >= MAX_FILES_PER_CLIENT)
	{
		DebugLog("Native_SendFile: Queue limit reached for UserID %d", userid);
		ThrowNativeError(SP_ERROR_NATIVE, "Queue limit reached (max %d files)", MAX_FILES_PER_CLIENT);
		return false;
	}

	info.Plugin = plugin;
	info.Func = GetNativeFunction(3);
	info.Data = GetNativeCell(4);
	info.Id = 0;
	info.IsOurRequest = false;

	int fileSize = FileSize(info.Filename);
	if(fileSize < 0)
	{
		DebugLog("Native_SendFile: Failed to get file size: %s", info.Filename);
		ThrowNativeError(SP_ERROR_NATIVE, "Failed to get file size: %s", info.Filename);
		return false;
	}
	
	if(fileSize > MAX_FILE_SIZE)
	{
		DebugLog("Native_SendFile: File too large: %s (%d bytes, max %d)", 
			info.Filename, fileSize, MAX_FILE_SIZE);
		ThrowNativeError(SP_ERROR_NATIVE, "File too large: %d bytes (max %d bytes)", 
			fileSize, MAX_FILE_SIZE);
		return false;
	}

	DebugLog("Native_SendFile: UserID=%d, client=%d, file=%s, size=%d bytes", 
		userid, client, info.Filename, fileSize);

	CNetChan chan = CNetChan(client);
	if(chan.Address == Address_Null)
	{
		DebugLog("Native_SendFile: Invalid NetChannel");
		ThrowNativeError(SP_ERROR_NATIVE, "Client NetChannel is invalid");
		return false;
	}
	
	// Сохраняем TransferID до вызова SendFile, так как он инкрементируется внутри
	int savedTransferID = TransferID;
	bool result = chan.SendFile(info.Filename);
	if(result)
	{
		// Используем сохраненный ID, так как TransferID уже был увеличен в SendFile
		info.Id = savedTransferID;
		SendListing.PushArray(info);
		CallSentFileFinish(info, true);
	}
	else
	{
		DebugLog("Native_SendFile: SendFile returned false for: %s", info.Filename);
		CallSentFileFinish(info, false);
	}
	
	return result;
}

public any Native_RequestFile(Handle plugin, int params)
{
	StartNative();

	int client = GetNativeClient(1);
	int userid = GetClientUserId(client);
	FileEnum info;
	info.UserID = userid;
	GetNativeString(2, info.Filename, sizeof(info.Filename));

	info.Plugin = plugin;
	info.Func = GetNativeFunction(3);
	info.Data = GetNativeCell(4);
	info.IsOurRequest = true;

	DebugLog("Native_RequestFile: UserID=%d, client=%d, file=%s", userid, client, info.Filename);

	if(!IsValidFilePath(info.Filename))
	{
		DebugLog("Native_RequestFile: Invalid file path: %s", info.Filename);
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid file path: %s", info.Filename);
		return 0;
	}

	if(RequestExistsForUserID(userid, info.Filename))
	{
		DebugLog("Native_RequestFile: File already requested: %s", info.Filename);
		ThrowNativeError(SP_ERROR_NATIVE, "File already requested: %s", info.Filename);
		return 0;
	}

	CNetChan chan = CNetChan(client);
	if(chan.Address == Address_Null)
	{
		DebugLog("Native_RequestFile: Invalid NetChannel");
		ThrowNativeError(SP_ERROR_NATIVE, "Client NetChannel is invalid");
		return 0;
	}

	info.Id = chan.RequestFile(info.Filename);
	if(info.Id == 0)
	{
		DebugLog("Native_RequestFile: RequestFile returned 0");
		ThrowNativeError(SP_ERROR_NATIVE, "Failed to request file");
		return 0;
	}
	
	RequestListing.PushArray(info);
	
	DebugLog("Native_RequestFile: UserID=%d, ID=%d", userid, info.Id);
	return info.Id;
}

public any Native_IsFileInWaitingList(Handle plugin, int params)
{
	StartNative();

	int client = GetNativeCell(1);
	if(client < 1 || client > MaxClients || !IsClientInGame(client))
		return false;

	int userid = GetClientUserId(client);
	int length;
	GetNativeStringLength(2, length);
	char[] filename = new char[++length];
	GetNativeString(2, filename, length);

	return FileExistsForUserID(userid, filename);
}

public any Native_GetNetChanPtr(Handle plugin, int params)
{
	StartNative();
	
	int client = GetNativeCell(1);
	
	CNetChan chan = CNetChan(client);
	
	if(chan.Address == Address_Null)
		return Address_Null;
	
	return chan.Address;
}