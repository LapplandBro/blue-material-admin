<?PHP
require_once('init.php');
require_once(sprintf('%s/LightOpenID.php', INCLUDES_PATH));
require_once(sprintf('%s/SteamOpenID.php', INCLUDES_PATH));

if (defined('DEVELOPER_MODE')) {
    ini_set('error_reporting', E_ALL);
    ini_set('display_errors', true);
    ini_set('display_startup_errors', true);
}

/**
 * @param string $url
 * @param string $text  Текст ShowBox (пустой = без flash)
 * @param bool   $ok    true = успех (зелёный), false = ошибка (красный). Не гадать по тексту.
 */
function RedirectToSite($url = SB_WP_URL, $text = "", $ok = false) {
    // Сообщение через ShowBox сайта (flash), без браузерного alert().
    if ($text !== "" && $text !== null) {
        if (function_exists('sb_session_start'))
            sb_session_start();
        elseif (session_status() === PHP_SESSION_NONE)
            @session_start();

        $ok = (bool)$ok;
        $_SESSION['sb_ui_flash'] = array(
            'title' => $ok ? 'Вход' : 'Ошибка входа',
            'msg' => (string)$text,
            'color' => $ok ? 'green' : 'red',
            'timer' => $ok ? 1600 : 0,
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
    
    // Хвост 0:123 / 1:123 — совпадение STEAM_0 и STEAM_1 (как раньше LIKE '%…').
    $authTail = preg_replace('/^STEAM_[0-9]:/', '', (string)$SteamID);
    if (!is_string($authTail) || !preg_match('/^[01]:[0-9]+$/', $authTail))
        RedirectToSite(SB_WP_URL, 'Некорректный SteamID от OpenID.', false);
    $result = $GLOBALS['db']->Execute(
        "SELECT aid, password, expired FROM `" . DB_PREFIX . "_admins` WHERE authid LIKE ?",
        array('%' . $authTail)
    );
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
        RedirectToSite(SB_WP_URL, "Найдено более одного администратора. Свяжитесь с главным администратором.", false);
    else if ($AdminsNum == 0)
    {
        if ($ExpiredAdmin)
            RedirectToSite(SB_WP_URL, 'Запись администратора истёкла, или сработала защита сайта, обратитесь к владельцу сайта.', false);
        RedirectToSite(SB_WP_URL, 'По предоставленным данным, не найдено ни одного администратора.', false);
    }
    else {
        $mfa = function_exists('sb_totp_gate_required') ? sb_totp_gate_required((int)$aid) : 'none';
        if ($mfa === 'challenge' || $mfa === 'enroll') {
            sb_mfa_begin((int)$aid, true, $mfa);
            RedirectToSite(rtrim(SB_WP_URL, '/') . '/login2fa', '', true);
        }

        sb_set_auth_cookie("aid", $aid, time()+LOGIN_COOKIE_LIFETIME);
        sb_set_auth_cookie("password", $password, time()+LOGIN_COOKIE_LIFETIME);

        // Как у Plogin: пишем успешный Steam-вход в системный лог.
        $adminRow = $GLOBALS['db']->GetRow("SELECT user FROM " . DB_PREFIX . "_admins WHERE aid = ?", array((int)$aid));
        $adminName = ($adminRow && !empty($adminRow['user'])) ? $adminRow['user'] : ('aid#' . (int)$aid);
        if (class_exists('CSystemLog')) {
            $log = new CSystemLog("m", "Успешный вход", "Администратор '" . htmlspecialchars($adminName) . "' вошёл через Steam.", false);
            $log->aid = (int)$aid;
            $log->WriteLog();
        }

        RedirectToSite(SB_WP_URL, 'Вход выполнен.', true);
    }
}
