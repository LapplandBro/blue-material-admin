<?php

// SECURITY: this script performs a mass ban of an entire Steam group and is
// intended to be run from the command line only. It must never be reachable
// via a web request (it builds its own DB connection and bypasses normal
// admin authentication/CSRF checks).
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only.');
}

// Include necessary files - ADJUST THESE PATHS IF YOUR STRUCTURE IS DIFFERENT
// Assuming group_ban_process.php is in /www/foxsys-tech.ru/includes/
require_once(__DIR__ . '/adodb/adodb.inc.php'); // ADOdb is likely in a subdirectory of includes
require_once(__DIR__ . '/../config.php'); // config.php is often in the root directory
require_once(__DIR__ . '/system-functions.php');
require_once(__DIR__ . '/user-functions.php');

// Setup database connection - similar to what's likely in your main application
$db = ADONewConnection(DB_TYPE);
$db->Connect(DB_HOST, DB_USER, DB_PASS, DB_NAME) or die("Database connection error");
$db->Execute("SET NAMES 'utf8'"); // Ensure UTF-8 connection

$userbank = new CUserBank(); // Initialize user bank if needed for logging admin who initiated ban

// Function to get arguments from command line
function get_cli_args($argv) {
    $args = [];
    // Simple argument parsing: assumes key=value or --key=value
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (strpos($arg, '=') !== false) {
            list($key, $value) = explode('=', $arg, 2);
            $key = ltrim($key, '-'); // Remove leading dashes
            $args[$key] = $value;
        } else {
            $args[$arg] = true; // Flag argument
        }
    }
    return $args;
}

// Get command line arguments
$cli_args = get_cli_args($argv);

// Extract ban parameters from arguments
$grpurl = isset($cli_args['groupurl']) ? $cli_args['groupurl'] : null;
$reason = isset($cli_args['reason']) ? htmlspecialchars_decode($cli_args['reason']) : ""; // Decode reason if HTML entities were encoded for CLI
$initiating_admin_aid = isset($cli_args['admin_aid']) ? (int)$cli_args['admin_aid'] : 0; // Admin who initiated the ban
$initiating_admin_ip = isset($cli_args['admin_ip']) ? $cli_args['admin_ip'] : "CLI_Unknown_IP"; // Admin IP for logging

// --- Start Banning Logic (adapted from BanMemberOfGroup) ---

// Basic validation
if (empty($grpurl)) {
    echo "Error: Group URL not provided.\n";
    exit(1);
}

// Use the initiating admin's context if available, or a default system context for logging
global $userbank, $db; // Make $db global for CSystemLog if it uses it

// Temporarily load admin for logging purposes in this script
$original_admin_id = $userbank->GetAid(); // Save current admin ID if any
$userbank->LoadAdmin($initiating_admin_aid > 0 ? $initiating_admin_aid : -1); // Load initiating admin or system context
$username = $userbank->GetProperty("user") ? $userbank->GetProperty("user") : "System";

echo "Starting group ban process for: " . $grpurl . " initiated by " . $username . "\n";

// This script will process the entire group, but we'll still use chunking internally
set_time_limit(0); // Remove execution time limit for CLI script
ignore_user_abort(true); // Continue running even if the user disconnects

$already = array();
$bans = $db->GetAll("SELECT CAST(MID(authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(authid, 11, 10) * 2 AS UNSIGNED) AS community_id FROM ".DB_PREFIX."_bans WHERE RemoveType IS NULL;");
foreach($bans as $ban) {
	$already[] = $ban["community_id"];
}

$total_members = 0;
$processed_count = 0;
$error_count = 0;
$chunk_size = 100; // Internal chunk size for fetching/processing pages - can be larger for CLI
$members_per_page = 50; // Steam's members per page

// --- Get total members and pagination info ---
echo "Fetching initial group page to get total members...\n";
$raw = @file_get_contents("https://steamcommunity.com/groups/" . $grpurl . "/members");
if ($raw === FALSE) {
    echo "Error: Could not fetch group page: " . $grpurl . "\n";
    $log = new CSystemLog("e", "Group Ban Error", "CLI Ban: Could not fetch group page '" . $grpurl . "' initiated by " . $username);
    // Restore original admin context before exiting
    $userbank->LoadAdmin($original_admin_id);
    exit(1);
}

@$doc = new DOMDocument();
@$doc->loadHTML($raw);

// Attempt to get total member count
$member_count_div = $doc->getElementById('memberCount');
if ($member_count_div) {
    $total_members = (int) filter_var($member_count_div->nodeValue, FILTER_SANITIZE_NUMBER_INT);
} else {
    // Fallback: estimate from pagination
    $pagetag = $doc->getElementsByTagName('div');
    $pageclasselmt = null;
    foreach($pagetag as $pageclass) {
        if($pageclass->getAttribute('class') == "pageLinks") {
            $pageclasselmt = $pageclass;
            break;
        }
    }
    $pagenumbers = [1];
    if (isset($pageclasselmt)) {
        $pagelinks = $pageclasselmt->getElementsByTagName('a');
        foreach($pagelinks as $pagelink) {
            $pagenumber = str_replace("?p=", "", $pagelink->childNodes->item(0)->nodeValue);
            if(strpos($pagenumber, ">") === false)
                $pagenumbers[] = (int) $pagenumber;
        }
    }
    $total_members = (max($pagenumbers) * $members_per_page); // This is an estimate
}

if ($total_members == 0) {
    echo "Error: Could not determine total members for group: " . $grpurl . "\n";
    $log = new CSystemLog("e", "Group Ban Error", "CLI Ban: Could not determine total members for group '" . $grpurl . "' initiated by " . $username);
     // Restore original admin context before exiting
    $userbank->LoadAdmin($original_admin_id);
    exit(1);
}

echo "Total members found: " . $total_members . "\n";

// --- Process members in chunks ---

$current_member_index = 0;

while ($current_member_index < $total_members) {
    $start_index = $current_member_index;
    $end_index = min($current_member_index + $chunk_size, $total_members);
    
    echo "Processing members from index " . $start_index . " to " . ($end_index - 1) . "\\n";

    // Determine which page(s) to load for this chunk
    $start_page = floor($start_index / $members_per_page) + 1;
    $end_page = floor(($end_index - 1) / $members_per_page) + 1;

    $members_in_chunk_tags = [];

    // Loop through the required pages to collect member tags within the chunk range
    for ($i = $start_page; $i <= $end_page; $i++) {
        echo "Fetching page " . $i . "...\\n";
        // Re-fetch content for pages (including the first if needed for subsequent chunks)
        $raw = @file_get_contents("https://steamcommunity.com/groups/" . $grpurl . "/members?p=" . $i);
        if ($raw === FALSE) {
             echo "Warning: Could not fetch group page: " . $grpurl . " (Page " . $i . ") - skipping page.\\n";
             $log = new CSystemLog("w", "Group Ban Warning", "CLI Ban: Could not fetch group page '" . $grpurl . "' (Page '" . $i . "') during ban process initiated by " . $username . ".");
             continue; // Skip this page, try next
        }
        
        @$doc = new DOMDocument();
        @$doc->loadHTML($raw);

        $tags = $doc->getElementsByTagName('a');

        $page_members = [];
        foreach ($tags as $tag) {
            if((strstr($tag->getAttribute('href'), "https://steamcommunity.com/id/") || strstr($tag->getAttribute('href'), "https://steamcommunity.com/profiles/")) && $tag->hasChildNodes() && $tag->childNodes->length == 1 && $tag->childNodes->item(0)->nodeValue != "") {
                $page_members[] = $tag;
            }
        }

        // Add members from this page that fall within the current chunk's index range
        $page_start_index = ($i - 1) * $members_per_page;
        foreach ($page_members as $index_on_page => $member_tag) {
            $global_index = $page_start_index + $index_on_page;
            if ($global_index >= $start_index && $global_index < $end_index) {
                $members_in_chunk_tags[] = $member_tag;
            }
        }
    }

    // Process collected member tags for this chunk
    echo "Processing " . count($members_in_chunk_tags) . " members in this chunk...\\n";
    foreach ($members_in_chunk_tags as $tag) {
        $url = parse_url($tag->getAttribute('href'), PHP_URL_PATH);
        $url = explode("/", $url);

        // Check if already banned
        if(in_array($url[2], $already)) {
            echo " - Skipping already banned: " . $url[2] . "\\n";
            $processed_count++;
            continue;
        }

        $steamid = null;
        $urltag = null;

        if(strstr($tag->getAttribute('href'), "https://steamcommunity.com/id/")) {
            if($tfriend = GetFriendIDFromCommunityID($url[2])) {
                 if(in_array($tfriend, $already)) {
                    echo " - Skipping already banned (via friend ID): " . $tfriend . "\\n";
                    $processed_count++;
                    continue;
                }
                $cust = $url[2];
                $steamid = FriendIDToSteamID($tfriend);
                $urltag = $tfriend;
            } else {
                echo " - Error getting friend ID for custom URL: " . $url[2] . "\\n";
                $error_count++;
                $processed_count++; // Count as processed to move offset
                continue; // Skip this member
            }
        } else {
            $cust = NULL;
            $steamid = FriendIDToSteamID($url[2]);
            $urltag = $url[2];
        }

        // If we have a steamid, attempt to ban
        if ($steamid) {
            // Защита из конфига: SteamID в SB_PROTECTED_STEAMIDS не банить
            $protected_steamids = array_filter(array_map('trim', explode(',', defined('SB_PROTECTED_STEAMIDS') ? SB_PROTECTED_STEAMIDS : '')));
            if (in_array($steamid, $protected_steamids)) {
                echo " - Пропуск: SteamID в защищённом списке (config): ".$steamid."\n";
                $processed_count++;
                continue;
            }
            // Блокировка: не банить администраторов веб-панели (уязвимость)
            $adminBySteam = $db->GetRow("SELECT aid, user FROM ".DB_PREFIX."_admins WHERE authid = ?", array($steamid));
            if ($adminBySteam) {
                echo " - Пропуск: SteamID принадлежит администратору (".$adminBySteam["user"]."): ".$steamid."\n";
                $processed_count++;
                continue;
            }

            $pre = $db->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,type,ip,authid,name,ends,length,reason,aid,adminIp ) VALUES(UNIX_TIMESTAMP(),?,?,?,?,UNIX_TIMESTAMP(),?,?,?,?)");

            $original_name = $tag->childNodes->item(0)->nodeValue;
            $name_to_insert = function_exists('mb_convert_encoding')
                ? mb_convert_encoding($original_name, 'UTF-8', 'ISO-8859-1')
                : $original_name;
            
            // Attempt to insert with the potentially encoded name
            $success = $db->Execute($pre,array(0,
                                               "",
                                               $steamid,
                                               $name_to_insert,
                                               0,
                                               "Steam Community Group Ban (" . $grpurl . ") " . $reason,
                                               $initiating_admin_aid, // Use admin ID from argument
                                               $initiating_admin_ip)); // Use admin IP from argument

            // If insert failed, check for error 1366 and retry with UNKNOWN NICKNAME
            if (!$success && $db->ErrorNo() == 1366) {
                 echo " - DB Error 1366 for SteamID " . $steamid . ", retrying with UNKNOWN NICKNAME.\\n";
                $error_count++; // Count as error for the original name attempt
                $name_to_insert = "UNKNOWN NICKNAME";

                // Retry insert with UNKNOWN NICKNAME
                $retry_success = $db->Execute($pre,array(0,
                                                       "",
                                                       $steamid,
                                                       $name_to_insert,
                                                       0,
                                                       "Steam Community Group Ban (" . $grpurl . ") " . $reason,
                                                       $initiating_admin_aid,
                                                       $initiating_admin_ip));

                if (!$retry_success) {
                    echo " - Failed to insert ban for SteamID " . $steamid . " even with UNKNOWN NICKNAME. Error: " . $db->ErrorMsg() . "\\n";
                     $log = new CSystemLog("e", "Database Error", "CLI Ban: Failed to insert ban for SteamID " . $steamid . " even with UNKNOWN NICKNAME. Error: " . $db->ErrorMsg() . " initiated by " . $username);
                } else {
                    // Retry with UNKNOWN NICKNAME was successful
                }
            } elseif (!$success) {
                 echo " - Failed to insert ban for SteamID " . $steamid . ". Error: " . $db->ErrorMsg() . "\\n";
                $error_count++; // Count as error
                 $log = new CSystemLog("e", "Database Error", "CLI Ban: Failed to insert ban for SteamID " . $steamid . ". Error: " . $db->ErrorMsg() . " initiated by " . $username);
            } else {
                echo " - Successfully banned SteamID: " . $steamid . "\\n";
                 // Log successful ban? Maybe too verbose.
            }
        }

        $processed_count++;
    }

    // Move to the next chunk\'s starting index
    $current_member_index = $end_index;

    // Optional: Add a small sleep to be gentle on resources or APIs
    // usleep(100000); // Sleep for 100 milliseconds between chunks
}

// --- Finalize ---\necho "Group ban process finished for: " . $grpurl . "\\n";\necho "Processed: " . $processed_count . ", Errors: " . $error_count . "\\n";\n\n$log_message = "CLI Ban: Finished group ban for \'" . $grpurl . "\' initiated by " . $username . ". Processed: " . $processed_count . ", Errors: " . $error_count . ".";\n$log_type = $error_count > 0 ? "w" : "m";\n$log = new CSystemLog($log_type, "Group Ban Process Finished", $log_message);\n\n// Restore original admin context\n$userbank->LoadAdmin($original_admin_id);\n\nexit(0); // Indicate success\n\n?>