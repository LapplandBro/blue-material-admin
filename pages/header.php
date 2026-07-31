<?php 
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

global $userbank, $theme, $xajax,$user,$start;
$time = microtime();
$time = explode(" ", $time);
$time = $time[1] + $time[0];
$start = $time;
ob_start(); 

if(!defined("IN_SB"))
{
	echo "Ошибка доступа!";
	die();
}
	
$body_classes = array('sb-theme-body');
if($GLOBALS['config']['template.global'] == "1"){
	$def_ch = "";
	$body_classes[] = 'toggled';
	$body_classes[] = 'sw-toggled';
}else{
	$def_ch = '<li id="toggle-width" class="p-t-5"><div class="toggle-switch" data-trigger="hover" data-toggle="popover" data-placement="bottom" data-content="Включить полноэкранную работу шаблона? Ваш браузер запомнит данный выбор." title="Управление" data-original-title="Управление"><input id="tw-switch" type="checkbox" hidden="hidden"><label for="tw-switch" class="ts-helper"></label></div></li>';
}
$def_body = 'class="'.implode(' ', $body_classes).'"';

// Тема: CSS в <style>, а не style="" на body/header (валидаторы ругаются на inline style).
$theme_css_parts = array();
$th_style_color = isset($GLOBALS['config']['theme.style.color']) ? trim($GLOBALS['config']['theme.style.color']) : '';
$th_skin = isset($GLOBALS['config']['theme.style']) ? trim($GLOBALS['config']['theme.style']) : '';
$theme_color_attr = '';
if ($th_style_color !== '' && preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|[a-zA-Z]+)$/', $th_style_color)) {
	$theme_css_parts[] = '#header{background-color:'.$th_style_color.';}';
} elseif ($th_skin !== '') {
	$theme_color_attr = 'data-current-skin="'.htmlspecialchars($th_skin, ENT_QUOTES, 'UTF-8').'"';
}

$body_css = array();
$bg = isset($GLOBALS['config']['theme.bg']) ? trim($GLOBALS['config']['theme.bg']) : '';
if ($bg !== '') {
	if (stristr($bg, '#') === false && stripos($bg, 'rgb(') === false && stripos($bg, 'rgba(') === false && stripos($bg, 'RGBA(') === false) {
		$bg_url = str_replace(array('"', "'", '(', ')', '\\'), '', $bg);
		$body_css[] = "background-image:url(\"".$bg_url."\")";
	} elseif (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', $bg)) {
		$body_css[] = "background-color:".$bg;
	}
}
$allowed_bg_token = '/^[a-zA-Z0-9%\s\.\-]+$/';
foreach (array(
	'theme.bg.rep' => 'background-repeat',
	'theme.bg.att' => 'background-attachment',
	'theme.bg.pos' => 'background-position',
	'theme.bg.size' => 'background-size'
) as $cfgKey => $cssProp) {
	$val = isset($GLOBALS['config'][$cfgKey]) ? trim($GLOBALS['config'][$cfgKey]) : '';
	if ($val !== '' && preg_match($allowed_bg_token, $val)) {
		$body_css[] = $cssProp.':'.$val;
		if ($cssProp === 'background-size') {
			$body_css[] = '-webkit-'.$cssProp.':'.$val;
			$body_css[] = '-moz-'.$cssProp.':'.$val;
			$body_css[] = '-o-'.$cssProp.':'.$val;
		}
	}
}
if (!empty($body_css))
	$theme_css_parts[] = 'body.sb-theme-body{'.implode(';', $body_css).';}';
$theme_css = implode("\n", $theme_css_parts);

/////////
/////////
/////////

function toCommunityID($id) {
    if (preg_match('/^STEAM_/', $id)) {
        $parts = explode(':', $id);
        return bcadd(bcadd(bcmul($parts[2], '2'), '76561197960265728'), $parts[1]);
    } elseif (is_numeric($id) && strlen($id) < 16) {
        return bcadd($id, '76561197960265728');
    } else {
        return $id; // We have no idea what this is, so just return it.
    }
}

$res = $GLOBALS['db']->Execute("SELECT authid, vk, comment, discord, user FROM `".DB_PREFIX."_admins` WHERE `support` = '1'");
$supports = array();
while (!$res->EOF)
{
    $suppurt_inf = array();
	
	$suppurt_inf['user'] = stripslashes($res->fields['user']);
	$suppurt_inf['comment'] = $res->fields['comment'];
	$suppurt_inf['vk'] = $res->fields['vk'];
	$suppurt_inf['discord'] = $res->fields['discord'];
	$suppurt_inf['authid'] = toCommunityID($res->fields['authid']);
	$suppurt_inf['avatarka'] = GetUserAvatar($res->fields['authid']);

	
	array_push($supports,$suppurt_inf);
	$res->MoveNext();
}


$theme->assign('supports_list', $supports);
$theme->assign('supports_count', count($supports));
////////
////////
////////

$theme->assign('avatar', GetUserAvatar($userbank->GetProperty('authid')));
$theme->assign('theme_css', $theme_css);
$theme->assign('theme_color_attr', $theme_color_attr);
$theme->assign('def_ch_chenger',  $def_ch);
$theme->assign('def_body_chenger',  $def_body);
$theme->assign('xajax_functions',  $xajax->printJavascript("scripts", "xajax.js"));
$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
// Шапка Material Admin | SourceBans — путь логотипа в настройках заблокирован.
$logo = 'images/icons/logo-material-admin.svg';
$theme->assign('header_logo', $logo);
$theme->assign('header_title', $GLOBALS['config']['template.title']);
$theme->assign('vay4er_act', $GLOBALS['config']['page.vay4er']);
$theme->assign('username', $userbank->GetProperty("user"));
$theme->assign('logged_in', $userbank->is_logged_in());
$theme->assign('theme_name', isset($GLOBALS['config']['config.theme'])?$GLOBALS['config']['config.theme']:'default');

// SEO / Open Graph / JSON-LD
$site_base = rtrim(defined('SB_WP_URL') ? SB_WP_URL : '', '/');
if ($site_base === '') {
	$site_base = ((COOKIE_SECURE) ? 'https' : 'http') . '://' . sb_get_site_host();
}
$seo_title = trim(strip_tags($GLOBALS['config']['template.title']));
if ($seo_title === '')
	$seo_title = 'SourceBans';

$seo_page = isset($_GET['p']) ? preg_replace('/[^a-z0-9_\-]/i', '', $_GET['p']) : '';
if ($seo_page === '')
	$seo_page = 'home';

// Уникальные title/description по страницам (header рисуется до RewritePageTitle).
$seo_brand = $seo_title;
$seo_page_meta = array(
	'home' => array(
		'label' => 'Главная',
		'desc' => 'Панель SourceBans ' . $seo_brand . ': серверы онлайн, банлист, муты и админлист.'
	),
	'servers' => array(
		'label' => 'Серверы',
		'desc' => 'Серверы ' . $seo_brand . ': онлайн, карта, IP и быстрое подключение.'
	),
	'banlist' => array(
		'label' => 'Банлист',
		'desc' => 'Банлист ' . $seo_brand . ': поиск по SteamID и нику, сроки блокировок.'
	),
	'commslist' => array(
		'label' => 'Муты и гаги',
		'desc' => 'Муты и гаги на серверах ' . $seo_brand . ': голосовой и текстовый чат.'
	),
	'adminlist' => array(
		'label' => 'Админы',
		'desc' => 'Администраторы серверов ' . $seo_brand . '.'
	),
	'login' => array(
		'label' => 'Вход',
		'desc' => 'Вход в панель SourceBans ' . $seo_brand . '.'
	),
	'submit' => array(
		'label' => 'Жалоба',
		'desc' => 'Жалоба на игрока через SourceBans ' . $seo_brand . '.'
	),
	'protest' => array(
		'label' => 'Апелляция',
		'desc' => 'Апелляция бана через SourceBans ' . $seo_brand . '.'
	),
	'account' => array(
		'label' => 'Профиль',
		'desc' => 'Кабинет администратора SourceBans ' . $seo_brand . '.'
	),
	'admin' => array(
		'label' => 'Админ-панель',
		'desc' => 'Админ-панель SourceBans ' . $seo_brand . ': серверы, баны, настройки.'
	)
);

$seo_strlen = function ($s) {
	return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
};
$seo_substr = function ($s, $start, $len) {
	return function_exists('mb_substr') ? mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len);
};

$seo_page_label = isset($seo_page_meta[$seo_page]['label']) ? $seo_page_meta[$seo_page]['label'] : '';
// Сниппет Google ~50–60 символов: бренд на главной, на внутренних — «Раздел | Бренд».
if ($seo_page_label !== '' && $seo_page !== 'home') {
	$seo_document_title = $seo_page_label . ' | ' . $seo_title;
	if ($seo_strlen($seo_document_title) > 60)
		$seo_document_title = $seo_substr($seo_document_title, 0, 57) . '…';
} else {
	$seo_document_title = $seo_title;
	if ($seo_strlen($seo_document_title) > 60)
		$seo_document_title = $seo_substr($seo_document_title, 0, 57) . '…';
}

// Описание: сначала per-page, иначе короткий текст из настроек (не dash.intro.text).
$seo_description = isset($seo_page_meta[$seo_page]['desc']) ? $seo_page_meta[$seo_page]['desc'] : '';
if ($seo_description === '') {
	$seo_desc_src = '';
	if (!empty($GLOBALS['config']['config.text_home']))
		$seo_desc_src = $GLOBALS['config']['config.text_home'];
	elseif (!empty($GLOBALS['config']['dash.info_block_text_t']))
		$seo_desc_src = $GLOBALS['config']['dash.info_block_text_t'];
	$seo_description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($seo_desc_src), ENT_QUOTES, 'UTF-8')));
}
if ($seo_description === '' || $seo_strlen($seo_description) < 50)
	$seo_description = 'Панель SourceBans ' . $seo_brand . ': серверы, банлист, муты и админлист.';
// Рекомендуемый диапазон сниппета ~120–155.
if ($seo_strlen($seo_description) > 155)
	$seo_description = $seo_substr($seo_description, 0, 152) . '…';

// Canonical: ЧПУ (/banlist, /admin/bans), главная без хвоста.
$seo_c = (isset($_GET['c']) ? preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_GET['c']) : '');
if ($seo_page === 'home')
	$seo_canonical = $site_base . '/';
elseif ($seo_page === 'admin' && $seo_c !== '')
	$seo_canonical = $site_base . '/admin/' . rawurlencode($seo_c);
elseif ($seo_page !== '')
	$seo_canonical = $site_base . '/' . rawurlencode($seo_page);
else
	$seo_canonical = $site_base . '/';

// <base href> — чтобы CSS/JS с /banlist не ломались (относительные themes/…)
$base_href = $site_base . '/';
$theme->assign('base_href', $base_href);

// --- Social embed (Discord/Telegram/etc.): отдельно от dashboard UI ---
$og_site_name = (defined('SB_OG_SITE_NAME') && SB_OG_SITE_NAME !== '')
	? SB_OG_SITE_NAME
	: $seo_brand;
$og_title = (defined('SB_OG_TITLE') && SB_OG_TITLE !== '')
	? SB_OG_TITLE
	: ($seo_page === 'home' ? ($og_site_name . ' — SourceBans') : $seo_document_title);
$og_description = (defined('SB_OG_DESCRIPTION') && SB_OG_DESCRIPTION !== '')
	? SB_OG_DESCRIPTION
	: $seo_description;

$og_image = (defined('SB_OG_IMAGE') && SB_OG_IMAGE !== '') ? trim((string)SB_OG_IMAGE) : '';
	if ($og_image === '')
	$og_image = 'images/og-cover.jpg';
if (!preg_match('#^https?://#i', $og_image))
	$og_image = $site_base . '/' . ltrim($og_image, '/');
// Cache-bust для Discord (жёстко кеширует embed)
$og_image_mtime = 0;
$og_image_local = ROOT . ltrim(preg_replace('#^https?://[^/]+/#i', '', $og_image), '/');
if (is_string($og_image_local) && is_readable($og_image_local))
	$og_image_mtime = (int)@filemtime($og_image_local);
if ($og_image_mtime <= 0)
	$og_image_mtime = time();
$og_image .= (strpos($og_image, '?') === false ? '?' : '&') . 'v=' . $og_image_mtime;

$og_image_width = (defined('SB_OG_IMAGE_WIDTH') && (int)SB_OG_IMAGE_WIDTH > 0) ? (int)SB_OG_IMAGE_WIDTH : 1200;
$og_image_height = (defined('SB_OG_IMAGE_HEIGHT') && (int)SB_OG_IMAGE_HEIGHT > 0) ? (int)SB_OG_IMAGE_HEIGHT : 630;
$og_image_type = 'image/png';
if (preg_match('/\.(jpe?g)(?:\?|$)/i', $og_image))
	$og_image_type = 'image/jpeg';
elseif (preg_match('/\.webp(?:\?|$)/i', $og_image))
	$og_image_type = 'image/webp';

// Старый seo_image оставляем для JSON-LD logo (не путать с OG cover)
$seo_image = trim(isset($GLOBALS['config']['template.logo']) ? (string)$GLOBALS['config']['template.logo'] : '');
if ($seo_image !== '' && !preg_match('#^https?://#i', $seo_image))
	$seo_image = $site_base . '/' . ltrim($seo_image, '/');
if ($seo_image === '' || preg_match('/\.(svg)$/i', $seo_image))
	$seo_image = $site_base . '/images/logos/sb-dark.png';

$seo_noindex = ($seo_page === 'admin' || $seo_page === 'account' || $seo_page === 'login' || $seo_page === 'lostpassword');

$theme->assign('seo_title', $seo_title);
$theme->assign('seo_document_title', $seo_document_title);
$theme->assign('seo_description', $seo_description);
$theme->assign('seo_canonical', $seo_canonical);
$theme->assign('seo_image', $seo_image);
$theme->assign('og_site_name', $og_site_name);
$theme->assign('og_title', $og_title);
$theme->assign('og_description', $og_description);
$theme->assign('og_image', $og_image);
$theme->assign('og_image_alt', $og_title);
$theme->assign('og_image_width', $og_image_width);
$theme->assign('og_image_height', $og_image_height);
$theme->assign('og_image_type', $og_image_type);
$theme->assign('seo_site_url', $site_base . '/');
$theme->assign('seo_noindex', $seo_noindex);

$seo_jsonld = array(
	'@context' => 'https://schema.org',
	'@graph' => array(
		array(
			'@type' => 'Organization',
			'@id' => $site_base . '/#organization',
			'name' => $og_site_name,
			'url' => $site_base . '/',
			'logo' => $seo_image
		),
		array(
			'@type' => 'WebSite',
			'@id' => $site_base . '/#website',
			'name' => $og_site_name,
			'url' => $site_base . '/',
			'description' => $og_description,
			'inLanguage' => 'ru-RU',
			'publisher' => array('@id' => $site_base . '/#organization')
		),
		array(
			'@type' => 'WebPage',
			'@id' => $seo_canonical . '#webpage',
			'url' => $seo_canonical,
			'name' => $seo_document_title,
			'description' => $seo_description,
			'isPartOf' => array('@id' => $site_base . '/#website'),
			'inLanguage' => 'ru-RU'
		)
	)
);
$theme->assign('seo_jsonld', json_encode($seo_jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Cache-busting для собственных CSS темы (иначе правки видны только через 7 дней кэша).
// Берём максимум mtime по всем нашим CSS — правка ЛЮБОГО файла сбрасывает кэш.
$css_dir = dirname(__FILE__) . '/../themes/new_box/css/';
$css_ver = 0;
foreach (array('dark-blue-theme.css', 'css_sup.css', 'rules.css') as $css_file) {
	$mt = @filemtime($css_dir . $css_file);
	if ($mt !== false && $mt > $css_ver) {
		$css_ver = $mt;
	}
}
if ($css_ver === 0) {
	$css_ver = defined('SB_VERSION') ? SB_VERSION : '1';
}
$theme->assign('asset_ver', $css_ver);

// Cache-bust scripts/sourcebans.js (иначе прод годами крутит старый файл без sbSetChecked).
$sb_js_mt = @filemtime(dirname(__FILE__) . '/../scripts/sourcebans.js');
$theme->assign('sb_js_ver', ($sb_js_mt !== false) ? $sb_js_mt : $css_ver);

$theme->display('page_header.tpl');
