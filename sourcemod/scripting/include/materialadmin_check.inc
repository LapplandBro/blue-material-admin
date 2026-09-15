#if defined _materialadmin_check_included
 #endinput
#endif
#define _materialadmin_check_included

#define MATERIALADMIN_CHECK_VERSION "1.0.0"

/**
 * Checks if Material Admin is loaded and available
 * 
 * @return          True if Material Admin is available, false otherwise
 */
native bool MaterialAdmin_IsAvailable();

/**
 * Gets the Material Admin database handle
 * 
 * @return          Database handle, or null if not available
 */
native Database MaterialAdmin_GetDatabase();

/**
 * Checks if a SteamID has an active ban in Material Admin
 * 
 * @param steamid   SteamID to check (format: STEAM_X:X:XXXXXXXX)
 * @param callback  Function to call with result
 *                  Function format: void(int client, bool isBanned, int banLength, int banEnds)
 * @param client    Client index to pass to callback
 * @return          True if check was initiated, false on error
 */
native bool MaterialAdmin_CheckBan(int client, const char[] steamid, Function callback, any data = 0);

/**
 * Checks if any of the SteamIDs associated with a fingerprint have an active ban
 * 
 * @param fingerprint   Fingerprint to check
 * @param steamids      Semicolon-separated list of SteamIDs
 * @param callback      Function to call with result
 *                      Function format: void(int client, const char[] fingerprint, bool isBanned, int banLength, int banEnds, any data)
 * @param client        Client index to pass to callback
 * @return              True if check was initiated, false on error
 */
native bool MaterialAdmin_CheckFingerprintBan(int client, const char[] fingerprint, const char[] steamids, Function callback, any data = 0);

/**
 * Gets the Material Admin bans table name
 * 
 * @param buffer    Buffer to store table name
 * @param maxlen    Maximum length of buffer
 * @return          True if table name was retrieved
 */
native bool MaterialAdmin_GetBansTable(char[] buffer, int maxlen);

/**
 * Sets the Material Admin bans table name
 * 
 * @param tablename Table name to use
 * @noreturn
 */
native void MaterialAdmin_SetBansTable(const char[] tablename);

/**
 * Marks all natives as optional so the plugin can load even if materialadmin_check is not loaded
 * This should be called in AskPluginLoad2 if you want optional support
 */
public void __ext_materialadmin_check_SetNTVOptional()
{
	MarkNativeAsOptional("MaterialAdmin_IsAvailable");
	MarkNativeAsOptional("MaterialAdmin_GetDatabase");
	MarkNativeAsOptional("MaterialAdmin_CheckBan");
	MarkNativeAsOptional("MaterialAdmin_CheckFingerprintBan");
	MarkNativeAsOptional("MaterialAdmin_GetBansTable");
	MarkNativeAsOptional("MaterialAdmin_SetBansTable");
}
