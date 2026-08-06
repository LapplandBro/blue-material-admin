<?php
/**************************************************************************
 * This file is part of Blue Material Admin (SourceBans++ fork).
 *
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE and NOTICE in the project root.
 *
 * UI theme under themes/new_box has separate provenance — see NOTICE.
 ***************************************************************************/
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}

global $userbank, $theme;



if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_MODS)) {
    CreateRedBox("Доступ запрещен!", "У вас нету доступных привилегий на просмотр данной страницы.");
    PageDie();
}

/* Request */
$manifest = @file_get_contents("https://raw.githubusercontent.com/CrazyHackGUT/SB_Material_Design/master/updates.json");
$manifest = json_decode($manifest, true);
if (json_last_error() != JSON_ERROR_NONE) {
    CreateRedBox("Ошибка!", "Не удаётся получить доступ к манифесту обновлений");
    PageDie();
}

// SECURITY FIX: URL mods_manifest ранее брался из удалённого updates.json без какой-либо
// проверки хоста. Если бы вышестоящий GitHub-репозиторий был скомпрометирован/перехвачен,
// второй file_get_contents() мог быть направлен на внутренний/приватный адрес (SSRF).
// Разрешаем загрузку только с того же доверенного хоста, что и сам манифест обновлений,
// и только по http(s).
$mods_manifest_url = isset($manifest['mods_manifest']) ? (string)$manifest['mods_manifest'] : '';
$mods_manifest_host = parse_url($mods_manifest_url, PHP_URL_HOST);
$mods_manifest_scheme = parse_url($mods_manifest_url, PHP_URL_SCHEME);
if ($mods_manifest_host !== 'raw.githubusercontent.com' || !in_array($mods_manifest_scheme, array('http', 'https'), true)) {
    CreateRedBox("Ошибка!", "Манифест репозитория МОДов ссылается на недоверенный источник.");
    PageDie();
}
$manifest = @file_get_contents($mods_manifest_url);
$manifest = json_decode($manifest, true);
if (json_last_error() != JSON_ERROR_NONE) {
    CreateRedBox("Ошибка!", "Не удаётся получить доступ к манифесту репозитория МОДов!");
    PageDie();
}

/* Prepare data to displaying */
$games = $manifest['games'];
foreach ($games as &$game) {
    $game['installed'] = ((int) ($GLOBALS['db']->GetOne(sprintf("SELECT COUNT(*) FROM `%s_mods` WHERE `modfolder` = %s", DB_PREFIX, $GLOBALS['db']->qstr($game['folder'])))) == 1);
}

/* Display */
$tabs = new CTabsMenu();
$tabs->addMenuItem("Назад",0,"","index.php?p=admin&c=mods", true);
$tabs->outputMenu();

$theme->assign('mirror_iconsdir',   $manifest['manifest']['icons_dir']);
$theme->assign('mirror',            $manifest['manifest']['mirror']);
$theme->assign('modlist',           $games);
$theme->display('page_admin_mods_repo.tpl');
