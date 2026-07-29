<?PHP
require_once('init.php');
require_once(sprintf('%s/LightOpenID.php', INCLUDES_PATH));
require_once(sprintf('%s/SteamOpenID.php', INCLUDES_PATH));

if (defined('DEVELOPER_MODE')) {
    ini_set('error_reporting', E_ALL);
    ini_set('display_errors', true);
    ini_set('display_startup_errors', true);
}

function RedirectToSite($url = SB_WP_URL, $text = "", $NotJS = false) {
    // Сообщение через ShowBox сайта (flash), без браузерного alert().
    if ($text !== "" && $text !== null) {
        if (function_exists('sb_session_start'))
            sb_session_start();
        elseif (session_status() === PHP_SESSION_NONE)
            @session_start();

        $msg = (string)$text;
        $isOk = (stripos($msg, 'найден') !== false && stripos($msg, 'не найдено') === false)
            || stripos($msg, 'переадресация') !== false;
        $_SESSION['sb_ui_flash'] = array(
            'title' => $isOk ? 'Вход' : 'Ошибка входа',
            'msg' => $msg,
            'color' => $isOk ? 'green' : 'red',
        );
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>document.location.href=' . json_encode((string)$url) . ';</script>';
    exit;
}

function CommunityIDToSteamID($communityid) {
    $authserver = bcsub( $communityid, '76561197960265728' ) & 1;
    $authid = (bcsub( $communityid, '76561197960265728' ) - $authserver ) / 2;
    return sprintf("STEAM_0:%d:%d", $authserver, $authid);
}

$Site = SB_WP_URL;
$Site = str_replace(array('https', 'http', '://'), '', $Site);

$AuthResult = SteamAuthorize($Site);
if (!$AuthResult)
    RedirectToSite(); // Something error. User cancelled authentication?
else if (strpos($AuthResult, 'steamcommunity') !== false)
    RedirectToSite($AuthResult); // Auth started. Redirect to Steam.
else {
    // Auth success. Steam returned SteamID64
    $SteamID = CommunityIDToSteamID($AuthResult);
    
    $AdminsNum = 0;
    $ExpiredAdmin = false;
    $aid = 0;
    $password = '';
    
    $result = $GLOBALS['db']->Execute(sprintf("SELECT aid,password,expired FROM %s_admins WHERE authid LIKE '%%%s'", DB_PREFIX, str_replace('STEAM_0:', '', $SteamID)));
	while(!$result->EOF) {
        $exp = $result->fields['expired'];
        if (($exp > 0 && $exp > time()) || $exp == '0' || $exp == '') {
            $AdminsNum++;
            $aid      = $result->fields['aid'];
            $password = $result->fields['password'];
        } else
            $ExpiredAdmin = true;
        
        $result->MoveNext();
    }
    
    if ($AdminsNum > 1)
        RedirectToSite(SB_WP_URL, "Найдено более одного администратора. Свяжитесь с главным администратором.", true);
    else if ($AdminsNum == 0)
    {
        if ($ExpiredAdmin)
            RedirectToSite(SB_WP_URL, 'Запись администратора истёкла, или сработала защита сайта, обратитесь к владельцу сайта WOLFA22.', true);
        RedirectToSite(SB_WP_URL, 'По предоставленным данным, не найдено ни одного администратора.', true);
    }
    else {
        sb_set_auth_cookie("aid", $aid, time()+LOGIN_COOKIE_LIFETIME);
        sb_set_auth_cookie("password", $password, time()+LOGIN_COOKIE_LIFETIME);
        RedirectToSite(SB_WP_URL, 'Администратор найден, переадресация...');
    }
}
