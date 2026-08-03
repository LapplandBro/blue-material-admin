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

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
/**
* Extended substr function. If it finds mbstring extension it will use, else
* it will use old substr() function
*
* @param string $string String that need to be fixed
* @param integer $start Start extracting from
* @param integer $length Extract number of characters
* @return string
*/
function substr_utf($string, $start = 0, $length = null) {
$start = (integer) $start >= 0 ? (integer) $start : 0;
if(is_null($length))
	$length = strlen_utf($string) - $start;
    return substr($string, $start, $length);
}

/**
* Equivalent to htmlspecialchars(), but allows &#[0-9]+ (for unicode)
* This function was taken from punBB codebase <http://www.punbb.org/>
*
* @param string $str
* @return string
*/
function clean($str) {
	$str = preg_replace('/&(?!#[0-9]+;)/s', '&amp;', $str);
	$str = str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $str);
	return $str;
}

/**
* Check if selected email has valid email format
*
* @param string $user_email Email address
* @return boolean
*/
function is_valid_email($user_email) {
	$chars = EMAIL_FORMAT;
	if(strstr($user_email, '@') && strstr($user_email, '.')) {
		return (boolean) preg_match($chars, $user_email);
	}else{
		return false;
	}
}

/**
 * Returns the full location that the website is running in
 *
 * @return string location of SourceBans
 */
function GetLocation()
{
	return substr($_SERVER['SCRIPT_FILENAME'], 0, strlen($base)-strlen("index.php"));
}

/**
 * Displays the header of SourceBans
 *
 * @return noreturn
 */
function BuildPageHeader()
{
	include TEMPLATES_PATH . "/header.php";
}

/**
 * Displays the sub-nav menu of SourceBans
 *
 * @return noreturn
 */
function BuildSubMenu()
{
	global $theme;
	$theme->left_delimiter = '<!--{';
	$theme->right_delimiter = '}-->';
	$theme->display('submenu.tpl');
	$theme->left_delimiter = '{';
	$theme->right_delimiter = '}';
}

/**
 * Displays the content header
 *
 * @return noreturn
 */
function BuildContHeader()
{
	global $theme, $userbank;
	if(isset($_GET['p']) && $_GET['p'] == "admin" && !$userbank->is_admin()) {
		echo "Вы не являетесь Администратором !";
		RedirectJS('index.php?p=login');
		PageDie();
	}

	if(!isset($_GET['s']) && isset($GLOBALS['pagetitle']))
	{
		$page = "<b>".$GLOBALS['pagetitle']."</b>";
	}

	$theme->assign('main_title', isset($page)?$page:'');
	$theme->assign('xleb', $GLOBALS['config']['page.xleb']);
	$theme->display('content_header.tpl');
}


/**
 * Adds a tab to the page
 *
 * @param string $title The title of the tab
 * @param string $utl The link of the tab
 * @param boolean $active Is the tab active?
 * @return noreturn
 */

/**
 * Classify a sidebar menu URL into a visual group (does not change DB order within a group).
 *
 * @param string $url
 * @param string $title
 * @return string site|tools|community|admin
 */
function sb_menu_group($url, $title)
{
	$url_l = strtolower((string)$url);
	$title_l = function_exists('mb_strtolower')
		? mb_strtolower(strip_tags((string)$title), 'UTF-8')
		: strtolower(strip_tags((string)$title));
	$hay = $url_l . ' ' . $title_l;

	if (preg_match('/(?:\?|&)p=admin\b/', $url_l) || strpos($title_l, 'админ-панель') !== false)
		return 'admin';

	if (preg_match('/(?:vk\.com|discord\.|steamcommunity\.|steampowered\.|youtube\.|youtu\.be|facebook\.|t\.me\/|telegram\.|fasttby|shop)/i', $url_l)
		|| preg_match('/\b(vk|discord|steam|youtube|facebook|магазин|группу)\b/u', $title_l))
		return 'community';

	if (preg_match('/оплат|хостинг|pay\.html|hosting.?pay/i', $hay))
		return 'site';

	// Инструменты: конвертеры, API, статистика, аудио-редакторы и т.п.
	if (preg_match('/(?:hlstats|vtf|converter|\/api\b|\bapi\b|audio|audacity|twistedwave|online-?audio|sfxr|bfxr|wav|mp3|ogg|sound.?edit|voice.?edit|equalizer)/i', $hay)
		|| preg_match('/\b(конвертер|hlstats|api|статистик|аудио|звук|редактор|микрофон|войс|voice)\b/u', $title_l))
		return 'tools';

	if (preg_match('/^(https?:)?\/\//i', $url_l) && !preg_match('/index\.php/i', $url_l))
		return 'community';

	return 'site';
}

/** Допустимые разделы бокового меню. */
function sb_menu_group_choices()
{
	return array(
		'' => 'Авто',
		'site' => 'Сайт',
		'tools' => 'Инструменты',
		'community' => 'Сообщество',
		'admin' => 'Админка',
	);
}

function sb_menu_normalize_group($group)
{
	$group = strtolower(trim((string)$group));
	$allowed = array('site', 'tools', 'community', 'admin');
	return in_array($group, $allowed, true) ? $group : '';
}

/**
 * Раздел пункта: явный menu_group из БД или авто по URL/названию.
 */
function sb_menu_resolve_group($item)
{
	if (is_array($item) && !empty($item['menu_group']))
	{
		$g = sb_menu_normalize_group($item['menu_group']);
		if ($g !== '')
			return $g;
	}
	$url = is_array($item) ? (isset($item['url']) ? $item['url'] : '') : '';
	$text = is_array($item) ? (isset($item['text']) ? $item['text'] : '') : '';
	return sb_menu_group($url, $text);
}

/** Добавляет колонку menu_group при отсутствии (один раз за запрос). */
function sb_menu_ensure_group_column()
{
	static $done = false;
	if ($done || empty($GLOBALS['db']))
		return;
	$done = true;
	$table = DB_PREFIX . '_menu';
	$cols = @$GLOBALS['db']->MetaColumnNames($table);
	if (!is_array($cols))
		$cols = array();
	$have = false;
	foreach ($cols as $c)
	{
		if (strtolower((string)$c) === 'menu_group')
		{
			$have = true;
			break;
		}
	}
	if (!$have)
		@$GLOBALS['db']->Execute("ALTER TABLE `{$table}` ADD `menu_group` VARCHAR(16) NOT NULL DEFAULT ''");
}

/**
 * HTML select раздела меню.
 */
function sb_menu_group_picker_html($selected = '')
{
	$selected = sb_menu_normalize_group($selected);
	$html = '<select class="form-control" name="menu_group" id="menu_group">';
	foreach (sb_menu_group_choices() as $val => $label)
	{
		$sel = ($val === $selected) ? ' selected="selected"' : '';
		$html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
			. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
	}
	$html .= '</select>';
	$html .= '<div class="parsec-muted m-t-5" style="font-size:12px;">«Авто» — по URL/названию. Для внешних утилит ставь «Инструменты», иначе они уедут в «Сообщество».</div>';
	return $html;
}

/**
 * Каталог иконок меню (Material Design Iconic Font) для пикера в админке.
 * class пустой = авто-подбор по URL/названию.
 *
 * @return array
 */
function sb_menu_icon_catalog()
{
	return array(
		array('class' => '', 'label' => 'Авто'),
		array('class' => 'zmdi zmdi-home', 'label' => 'Главная'),
		array('class' => 'zmdi zmdi-dns', 'label' => 'Серверы'),
		array('class' => 'zmdi zmdi-block-alt', 'label' => 'Баны'),
		array('class' => 'zmdi zmdi-mic-off', 'label' => 'Муты'),
		array('class' => 'zmdi zmdi-accounts', 'label' => 'Админы'),
		array('class' => 'zmdi zmdi-shield-security', 'label' => 'Админ-панель'),
		array('class' => 'zmdi zmdi-flag', 'label' => 'Жалоба'),
		array('class' => 'zmdi zmdi-balance', 'label' => 'Апелляция'),
		array('class' => 'zmdi zmdi-card', 'label' => 'Оплата'),
		array('class' => 'zmdi zmdi-shopping-cart', 'label' => 'Магазин'),
		array('class' => 'zmdi zmdi-shopping-cart-plus', 'label' => 'Ваучер'),
		array('class' => 'zmdi zmdi-steam', 'label' => 'Steam'),
		array('class' => 'zmdi zmdi-vk', 'label' => 'VK'),
		array('class' => 'sb-menu-ico sb-menu-ico-youtube', 'label' => 'YouTube'),
		array('class' => 'sb-menu-ico sb-menu-ico-telegram', 'label' => 'Telegram'),
		array('class' => 'zmdi zmdi-comments', 'label' => 'Discord/Чат'),
		array('class' => 'zmdi zmdi-facebook', 'label' => 'Facebook'),
		array('class' => 'zmdi zmdi-twitter', 'label' => 'Twitter'),
		array('class' => 'zmdi zmdi-instagram', 'label' => 'Instagram'),
		array('class' => 'zmdi zmdi-globe', 'label' => 'Сайт'),
		array('class' => 'zmdi zmdi-open-in-new', 'label' => 'Внешняя'),
		array('class' => 'zmdi zmdi-link', 'label' => 'Ссылка'),
		array('class' => 'zmdi zmdi-chart', 'label' => 'Статистика'),
		array('class' => 'zmdi zmdi-swap', 'label' => 'Конвертер'),
		array('class' => 'zmdi zmdi-code-setting', 'label' => 'API'),
		array('class' => 'zmdi zmdi-settings', 'label' => 'Настройки'),
		array('class' => 'zmdi zmdi-info', 'label' => 'Инфо'),
		array('class' => 'zmdi zmdi-help', 'label' => 'Помощь'),
		array('class' => 'zmdi zmdi-alert-circle', 'label' => 'Важно'),
		array('class' => 'zmdi zmdi-notifications', 'label' => 'Уведомления'),
		array('class' => 'zmdi zmdi-email', 'label' => 'Почта'),
		array('class' => 'zmdi zmdi-phone', 'label' => 'Телефон'),
		array('class' => 'zmdi zmdi-account', 'label' => 'Профиль'),
		array('class' => 'zmdi zmdi-accounts-list', 'label' => 'Список'),
		array('class' => 'zmdi zmdi-assignment', 'label' => 'Правила'),
		array('class' => 'zmdi zmdi-file-text', 'label' => 'Документ'),
		array('class' => 'zmdi zmdi-calendar', 'label' => 'Календарь'),
		array('class' => 'zmdi zmdi-time', 'label' => 'Время'),
		array('class' => 'zmdi zmdi-search', 'label' => 'Поиск'),
		array('class' => 'zmdi zmdi-download', 'label' => 'Скачать'),
		array('class' => 'zmdi zmdi-upload', 'label' => 'Загрузить'),
		array('class' => 'zmdi zmdi-cloud', 'label' => 'Облако'),
		array('class' => 'zmdi zmdi-wifi', 'label' => 'Онлайн'),
		array('class' => 'zmdi zmdi-gamepad', 'label' => 'Игра'),
		array('class' => 'zmdi zmdi-portable-wifi', 'label' => 'Сеть'),
		array('class' => 'zmdi zmdi-headset', 'label' => 'Голос'),
		array('class' => 'zmdi zmdi-volume-up', 'label' => 'Аудио'),
		array('class' => 'zmdi zmdi-equalizer', 'label' => 'Эквалайзер'),
		array('class' => 'zmdi zmdi-mic', 'label' => 'Микрофон'),
		array('class' => 'zmdi zmdi-playlist-audio', 'label' => 'Плейлист'),
		array('class' => 'zmdi zmdi-comment-text', 'label' => 'Текст'),
		array('class' => 'zmdi zmdi-star', 'label' => 'Избранное'),
		array('class' => 'zmdi zmdi-favorite', 'label' => 'Лайк'),
		array('class' => 'zmdi zmdi-fire', 'label' => 'Hot'),
		array('class' => 'zmdi zmdi-flash', 'label' => 'Flash'),
		array('class' => 'zmdi zmdi-money', 'label' => 'Деньги'),
		array('class' => 'zmdi zmdi-balance-wallet', 'label' => 'Кошелёк'),
		array('class' => 'zmdi zmdi-gift', 'label' => 'Подарок'),
		array('class' => 'zmdi zmdi-ticket-star', 'label' => 'Билет'),
		array('class' => 'zmdi zmdi-chevron-right', 'label' => 'Стрелка'),
	);
}

/**
 * Достаёт class иконки из текста пункта меню (если вбит вручную / через пикер).
 */
function sb_menu_extract_icon($text)
{
	$text = (string)$text;
	if (preg_match('/class\s*=\s*["\']([^"\']*\bsb-menu-ico\s+sb-menu-ico-[a-z0-9\-]+[^"\']*)["\']/i', $text, $m))
		return trim(preg_replace('/\s+/', ' ', $m[1]));
	if (preg_match('/class\s*=\s*["\']([^"\']*\bzmdi\s+zmdi-[a-z0-9\-]+[^"\']*)["\']/i', $text, $m))
		return trim(preg_replace('/\s+/', ' ', $m[1]));
	if (preg_match('/\bsb-menu-ico\s+sb-menu-ico-[a-z0-9\-]+\b/i', $text, $m))
		return trim($m[0]);
	if (preg_match('/\bzmdi\s+zmdi-[a-z0-9\-]+\b/i', $text, $m))
		return trim($m[0]);
	return '';
}

/** Убирает HTML-иконку из заголовка, оставляя чистый текст. */
function sb_menu_strip_icon($text)
{
	$text = (string)$text;
	$text = preg_replace('/<i\b[^>]*class\s*=\s*["\'][^"\']*(?:zmdi|sb-menu-ico)[^"\']*["\'][^>]*>\s*<\/i>/iu', '', $text);
	$text = preg_replace('/^\s*(?:zmdi\s+zmdi-[a-z0-9\-]+|sb-menu-ico\s+sb-menu-ico-[a-z0-9\-]+)\s*/iu', '', $text);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')));
}

/** Проверка, что class есть в каталоге (anti-XSS). */
function sb_menu_icon_allowed($icon_class)
{
	$icon_class = trim((string)$icon_class);
	if ($icon_class === '')
		return true;
	foreach (sb_menu_icon_catalog() as $item)
	{
		if ($item['class'] === $icon_class)
			return true;
	}
	return false;
}

/**
 * Собирает заголовок для БД: чистый текст + опциональная иконка.
 */
function sb_menu_compose_title($text, $icon_class)
{
	$clean = sb_menu_strip_icon($text);
	$icon_class = trim((string)$icon_class);
	if ($icon_class === '' || !sb_menu_icon_allowed($icon_class))
		return $clean;
	return '<i class="' . htmlspecialchars($icon_class, ENT_QUOTES, 'UTF-8') . '"></i> ' . $clean;
}

/**
 * HTML пикера иконок для форм добавления/редактирования меню.
 */
function sb_menu_icon_picker_html($selected = '')
{
	$selected = trim((string)$selected);
	$items = sb_menu_icon_catalog();
	$html = '<div class="menu-icon-picker" data-menu-icon-picker>'
		. '<input type="hidden" name="menu_icon" id="menu_icon" value="' . htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') . '">'
		. '<div class="menu-icon-picker__grid">';

	foreach ($items as $item)
	{
		$class = $item['class'];
		$label = $item['label'];
		$is_sel = ($class === $selected) || ($selected === '' && $class === '');
		$btn_class = 'menu-icon-pick' . ($is_sel ? ' is-selected' : '');
		$html .= '<button type="button" class="' . $btn_class . '"'
			. ' data-icon="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"'
			. ' title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
		if ($class === '')
			$html .= '<span class="menu-icon-pick__auto">A</span>';
		else
			$html .= '<i class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"></i>';
		$html .= '<span class="menu-icon-pick__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
		$html .= '</button>';
	}

	$html .= '</div></div>';
	$html .= '<script>(function(){var r=document.querySelector("[data-menu-icon-picker]");if(!r)return;var h=r.querySelector("#menu_icon");r.addEventListener("click",function(e){var b=e.target.closest(".menu-icon-pick");if(!b||!r.contains(b))return;e.preventDefault();var v=b.getAttribute("data-icon")||"";h.value=v;var all=r.querySelectorAll(".menu-icon-pick");for(var i=0;i<all.length;i++)all[i].classList.remove("is-selected");b.classList.add("is-selected");});})();</script>';
	return $html;
}

/**
 * Pick a Material icon class for a menu item.
 *
 * @param string $url
 * @param string $title
 * @return string zmdi-* class name without "zmdi " prefix chain — full class string
 */
function sb_menu_icon($url, $title)
{
	// Ручной выбор из пикера хранится как <i class="zmdi ..."> — strip_tags его съедает,
	// поэтому проверяем сырой title, иначе авто-иконка по URL перетирает выбор.
	if (function_exists('sb_menu_extract_icon') && sb_menu_extract_icon($title) !== '')
		return '';

	$url_l = strtolower((string)$url);
	$title_l = function_exists('mb_strtolower')
		? mb_strtolower(strip_tags((string)$title), 'UTF-8')
		: strtolower(strip_tags((string)$title));

	if (preg_match('/(?:\?|&)p=admin\b/', $url_l) || preg_match('#(?:^|/)admin(?:/|$)#', $url_l) || strpos($title_l, 'админ-панель') !== false)
		return 'zmdi zmdi-shield-security';
	if (preg_match('/(?:\?|&)p=(home|default)\b/', $url_l) || $url_l === './' || $url_l === '/' || $title_l === 'главная')
		return 'zmdi zmdi-home';
	if (preg_match('/(?:\?|&)p=servers\b/', $url_l) || preg_match('#(?:^|/)servers(?:\?|$)#', $url_l) || strpos($title_l, 'сервер') !== false)
		return 'zmdi zmdi-dns';
	if (preg_match('/(?:\?|&)p=banlist\b/', $url_l) || preg_match('#(?:^|/)banlist(?:\?|$)#', $url_l) || (strpos($title_l, 'бан') !== false && strpos($title_l, 'апелля') === false))
		return 'zmdi zmdi-block-alt';
	if (preg_match('/(?:\?|&)p=commslist\b/', $url_l) || preg_match('#(?:^|/)commslist(?:\?|$)#', $url_l) || strpos($title_l, 'мут') !== false || strpos($title_l, 'гаг') !== false)
		return 'zmdi zmdi-mic-off';
	if (preg_match('/(?:\?|&)p=adminlist\b/', $url_l) || preg_match('#(?:^|/)adminlist(?:\?|$)#', $url_l) || strpos($title_l, 'админлист') !== false || strpos($title_l, 'список админ') !== false)
		return 'zmdi zmdi-accounts';
	if (preg_match('/(?:\?|&)p=submit\b/', $url_l) || preg_match('#(?:^|/)submit(?:\?|$)#', $url_l) || strpos($title_l, 'жалоб') !== false)
		return 'zmdi zmdi-flag';
	if (preg_match('/(?:\?|&)p=protest\b/', $url_l) || preg_match('#(?:^|/)protest(?:\?|$)#', $url_l) || strpos($title_l, 'апелля') !== false)
		return 'zmdi zmdi-balance';
	if (preg_match('/(?:\?|&)p=pay\b/', $url_l) || preg_match('#(?:^|/)pay(?:\?|$)#', $url_l) || strpos($title_l, 'ваучер') !== false)
		return 'zmdi zmdi-shopping-cart-plus';
	if (preg_match('/vk\.com/i', $url_l) || preg_match('/\bvk\b/', $title_l))
		return 'zmdi zmdi-vk';
	if (preg_match('/discord/i', $url_l) || strpos($title_l, 'discord') !== false)
		return 'zmdi zmdi-comments';
	if (preg_match('/steam/i', $url_l) || strpos($title_l, 'steam') !== false)
		return 'zmdi zmdi-steam';
	if (preg_match('/youtube|youtu\.be/i', $url_l.$title_l))
		return 'sb-menu-ico sb-menu-ico-youtube';
	if (preg_match('/t\.me\/|telegram/i', $url_l.$title_l))
		return 'sb-menu-ico sb-menu-ico-telegram';
	if (preg_match('/hlstats|статистик/i', $url_l.$title_l))
		return 'zmdi zmdi-chart';
	if (preg_match('/vtf|converter|конвертер/i', $url_l.$title_l))
		return 'zmdi zmdi-swap';
	if (preg_match('/оплат|хостинг|pay\.html|hosting.?pay/i', $url_l.$title_l))
		return 'zmdi zmdi-card';
	if (preg_match('/аудио|звук|редактор|equalizer|audio|voice|mic/i', $url_l.$title_l))
		return 'zmdi zmdi-volume-up';
	if (preg_match('/\bapi\b/i', $url_l.$title_l))
		return 'zmdi zmdi-code-setting';
	if (preg_match('/shop|магазин|fasttby/i', $url_l.$title_l))
		return 'zmdi zmdi-shopping-cart';
	if (preg_match('/^(https?:)?\/\//i', $url_l))
		return 'zmdi zmdi-open-in-new';

	return 'zmdi zmdi-chevron-right';
}

function AddTab($title, $url, $desc, $newtab=false, $active=false)
{
	global $tabs;
	$tab_arr = array(	);
	$tab_arr[0] = "Главная";
	$tab_arr[1] = "Серверы";
	$tab_arr[2] = "Список банов";
	$tab_arr[3] = "Список мутов\гагов";
	$tab_arr[4] = "Пожаловаться на игрока";
	$tab_arr[5] = "Апелляция бана";
	$tab_arr[6] = "Список админов";

	$stored_icon = function_exists('sb_menu_extract_icon') ? sb_menu_extract_icon($title) : '';
	$icon = ($stored_icon !== '') ? '' : sb_menu_icon($url, $title);
	$label = function_exists('sb_menu_strip_icon') ? sb_menu_strip_icon($title) : trim(strip_tags((string)$title));
	if ($stored_icon !== '')
		$title = '<i class="'.htmlspecialchars($stored_icon, ENT_QUOTES, 'UTF-8').'"></i> <span class="main-menu-text">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
	elseif ($icon !== '')
		$title = '<i class="'.$icon.'"></i> <span class="main-menu-text">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
	elseif (strpos($title, 'main-menu-text') === false && strpos($title, '<i') !== false)
		$title = preg_replace('/<\/i>\s*/', '</i> <span class="main-menu-text">', $title, 1) . '</span>';

	$tabs = array();
	$tabs['title'] = $title;
	$tabs['url'] = $url;
	$tabs['desc'] = $desc;
	$tabs['newtab'] = $newtab;
	$tabs['group'] = isset($GLOBALS['sb_menu_current_group']) ? $GLOBALS['sb_menu_current_group'] : '';
	if($_GET['p'] == "default" && $label == $tab_arr[intval($GLOBALS['config']['config.defaultpage'])])
	{
		$tabs['active'] = true;
		$GLOBALS['pagetitle'] = $label;
	}
	else
	{
		// Active только по точному p (+ c, если есть в URL пункта).
		// Иначе на p=admin&c=menu синели и «Админ-панель», и любой deep-link с p=admin.
		$p = isset($_GET['p']) ? (string)$_GET['p'] : '';
		$c = isset($_GET['c']) ? (string)$_GET['c'] : '';
		$active_match = false;
		if ($p !== '' && $p !== 'default')
		{
			$u = html_entity_decode((string)$url, ENT_QUOTES, 'UTF-8');
			$path = $u;
			$qs = array();
			$qpos = strpos($u, '?');
			if ($qpos !== false)
			{
				$path = substr($u, 0, $qpos);
				$query = substr($u, $qpos + 1);
				if ($query !== '')
					parse_str($query, $qs);
			}
			$path = trim($path, '/');
			if ($path === '.' || $path === './')
				$path = '';

			if (!empty($qs['p']) && (string)$qs['p'] === $p)
			{
				if (!empty($qs['c']))
					$active_match = ($c !== '' && (string)$qs['c'] === $c);
				else
					$active_match = ($c === '');
			}
			elseif ($p === 'admin' && $c !== '' && preg_match('#^admin/([a-zA-Z0-9_]+)$#', $path, $pm))
				$active_match = ($pm[1] === $c);
			elseif ($c === '' && $path === $p)
				$active_match = true;
			elseif ($c === '' && $p === 'home' && ($path === '' || $path === 'home'))
				$active_match = true;
			elseif ($c === '' && strlen($url) > 12 && substr($url, 12) == $p)
				$active_match = true;
		}

		if($active_match)
		{
			$tabs['active'] = true;
			$GLOBALS['pagetitle'] = $label;
		}
		else
			$tabs['active'] = false;
	}
	include TEMPLATES_PATH . "/tab.php";
}

/**
 * Displays the pagetabs
 *
 * @return noreturn
 */
function BuildPageTabs()
{
	global $userbank;

	sb_menu_ensure_group_column();

	$items = $GLOBALS['db']->GetAll(sprintf("SELECT * FROM `%s_menu` WHERE `enabled` = 1 ORDER BY `priority` DESC", DB_PREFIX));
	$groups = array(
		'site' => array(),
		'tools' => array(),
		'community' => array(),
		'admin' => array(),
	);
	$group_labels = array(
		'site' => 'Сайт',
		'tools' => 'Инструменты',
		'community' => 'Сообщество',
		'admin' => 'Админка',
	);

	foreach ($items as $item)
	{
		$g = sb_menu_resolve_group($item);
		if (!isset($groups[$g]))
			$g = 'site';
		$groups[$g][] = $item;
	}

	// Пункт «Оплатить хостинг» из config.php (SB_HOSTING_PAY_*)
	if (defined('SB_HOSTING_PAY_URL') && SB_HOSTING_PAY_URL !== '')
	{
		$pay_label = (defined('SB_HOSTING_PAY_LABEL') && SB_HOSTING_PAY_LABEL !== '')
			? SB_HOSTING_PAY_LABEL
			: 'Оплатить хостинг';
		$pay_newtab = (!defined('SB_HOSTING_PAY_NEWTAB') || SB_HOSTING_PAY_NEWTAB === '1' || SB_HOSTING_PAY_NEWTAB === 1 || SB_HOSTING_PAY_NEWTAB === true)
			? '1'
			: '0';
		$pay_item = array(
			'text' => $pay_label,
			'url' => SB_HOSTING_PAY_URL,
			'description' => $pay_label,
			'newtab' => $pay_newtab,
		);
		$groups['site'][] = $pay_item;
	}

	// Ваучеры: пункт активации только гостям (создаёт новый аккаунт; залогиненным не нужен).
	if (isset($GLOBALS['config']['page.vay4er']) && (string)$GLOBALS['config']['page.vay4er'] === '1'
		&& (!$userbank->is_logged_in()))
	{
		$has_voucher = false;
		foreach ($groups as $glist)
		{
			foreach ($glist as $it)
			{
				if (!empty($it['url']) && (preg_match('/(?:\?|&)p=pay\b/', $it['url']) || preg_match('#(?:^|/)pay(?:\?|$)#', $it['url'])))
				{
					$has_voucher = true;
					break 2;
				}
			}
		}
		if (!$has_voucher)
		{
			$groups['site'][] = array(
				'text' => 'Активировать ваучер',
				'url' => function_exists('sb_url') ? sb_url('pay') : 'index.php?p=pay',
				'description' => 'Активация ваучера для получения админки (только для гостей)',
				'newtab' => '0',
			);
		}
	}

	if ($userbank->is_admin())
	{
		$has_admin_hub = false;
		foreach ($groups['admin'] as $aitem)
		{
			$au = $aitem['url'];
			if ((preg_match('/(?:\?|&)p=admin\b/', $au) && !preg_match('/(?:\?|&)c=/', $au))
				|| preg_match('#(?:^|/)admin/?$#', $au))
			{
				$has_admin_hub = true;
				break;
			}
		}
		if (!$has_admin_hub)
		{
			$groups['admin'][] = array(
				'text' => 'Админ-панель',
				'url' => function_exists('sb_url') ? sb_url('admin') : 'index.php?p=admin',
				'description' => 'Управление серверами, админами и настройками',
				'newtab' => '0',
			);
		}
	}

	// Жалоба / апелляция — для игроков. Залогиненным админам в меню не показываем
	// (бан/разбан и очереди заявок — в админ-панели).
	if ($userbank->is_logged_in())
	{
		foreach ($groups as $gk => $glist)
		{
			$filtered = array();
			foreach ($glist as $it)
			{
				$u = isset($it['url']) ? (string)$it['url'] : '';
				if (preg_match('/(?:\?|&)p=submit\b/', $u) || preg_match('#(?:^|/)submit(?:\?|$)#', $u))
					continue;
				if (preg_match('/(?:\?|&)p=protest\b/', $u) || preg_match('#(?:^|/)protest(?:\?|$)#', $u))
					continue;
				$filtered[] = $it;
			}
			$groups[$gk] = $filtered;
		}
	}

	foreach ($groups as $gkey => $list)
	{
		if (empty($list))
			continue;
		echo '<li class="main-menu-label" aria-hidden="true"><span>'.$group_labels[$gkey].'</span></li>';
		$GLOBALS['sb_menu_current_group'] = $gkey;
		foreach ($list as $item)
			AddTab($item['text'], function_exists('sb_legacy_to_pretty_url') ? sb_legacy_to_pretty_url($item['url']) : $item['url'], $item['description'], ($item['newtab']=="1"));
	}
	unset($GLOBALS['sb_menu_current_group']);

	include INCLUDES_PATH . "/CTabsMenu.php";

	// BUILD THE SUB-MENU's FOR ADMIN PAGES (top-right #nav)
	$submenu = new CTabsMenu();
	if($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS))
		$submenu->addMenuItem("Администраторы", 0,"", sb_url('admin', array('c' => 'admins')), true);
	if(($userbank->HasAccess(ADMIN_OWNER)) && ($GLOBALS['config']['page.vay4er'] == "1"))
		$submenu->addMenuItem("Ваучеры", 0,"", sb_url('admin', array('c' => 'pay_card')), true);
	if($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS))
		$submenu->addMenuItem("Серверы", 0,"", sb_url('admin', array('c' => 'servers')), true);
	if($userbank->HasAccess( ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS))
		$submenu->addMenuItem("Баны", 0,"", sb_url('admin', array('c' => 'bans')), true);
	if($userbank->HasAccess( ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS))
		$submenu->addMenuItem("Муты и гаги", 0,"", sb_url('admin', array('c' => 'comms')), true);
	if(function_exists('RecidivismCanView') ? RecidivismCanView() : $userbank->HasAccess( ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS))
		$submenu->addMenuItem("История нарушений", 0,"", sb_url('admin', array('c' => 'recidivism')), true);
	if(function_exists('ParsecPanelCanView') ? ParsecPanelCanView() : false)
		$submenu->addMenuItem("Связанные аккаунты", 0,"", sb_url('admin', array('c' => 'parsec')), true);
	if($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|ADMIN_DELETE_GROUPS))
		$submenu->addMenuItem("Группы", 0,"", sb_url('admin', array('c' => 'groups')), true);
	if($userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS))
		$submenu->addMenuItem("Настройки", 0,"", sb_url('admin', array('c' => 'settings')), true);
	if($userbank->HasAccess(ADMIN_OWNER))
		$submenu->addMenuItem("Меню", 0,"", sb_url('admin', array('c' => 'menu')), true);
	if($userbank->HasAccess( ADMIN_OWNER|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS))
		$submenu->addMenuItem("Моды", 0,"", sb_url('admin', array('c' => 'mods')), true);
	SubMenu( $submenu->getMenuArray() );
}

/**
 * Rewrites the breadcrumb html
 *
 * @return noreturn
 */
function BuildBreadcrumbs()
{
	$base = isset($GLOBALS['pagetitle']) ? $GLOBALS['pagetitle'] : '';
	if(isset($_GET['c']))
	{
		switch($_GET['c'])
		{
			case "admins":
				$cat = "Управление админами";
				break;
			case "servers":
				$cat = "Управление серверами";
				break;
			case "bans":
				$cat = "Управление банами";
				break;
			case "comms":
				$cat = "Управление мутами\гагами";
				break;
			case "recidivism":
				$cat = "История нарушений";
				break;
			case "parsec":
				$cat = "Связанные аккаунты";
				break;
			case "groups":
				$cat = "Управление группами";
				break;
			case "settings":
				$cat = "Настройки SourceBans";
				break;
			case "mods":
				$cat = "Управление модами";
				break;
			case "pay_card":
				$cat = "Управление Ваучерами";
				break;
			case "menu":
				$cat = "Управление меню";
				break;
			default:
				// Не трогаем $_GET['c']: иначе unknown /admin/hooy сбрасывается в хаб
				// до includes/admin.php, и проверка known-разделов не срабатывает.
				$cat = '';
				break;
		}
	}

	if($GLOBALS['config']['page.xleb']){
		if(!isset($_GET['c']))
		{
			if(!empty($base))
				$bread = '<li class="active">' . $base . '</li>';
			else
				unset ($bread);
		}
		else
		{
			if(!empty($cat))
				$bread = '<li><a href="index.php?p='. htmlspecialchars($_GET['p'], ENT_QUOTES, 'UTF-8') . '">' . $base . '</a></li> <li class="active">' . $cat . '</li>';
			else
				$bread = '<li><a href="index.php?p='. htmlspecialchars($_GET['p'], ENT_QUOTES, 'UTF-8') . '">' . $base . '</a></li>';
		}

		if(!empty($bread))
			$text = $bread;
		else
			$text = '<li><a href="index.php?p=home">Главная</a></li>';
		// json_encode даёт валидный JS-строковый литерал: двойные кавычки внутри HTML
		// (class="active", href="...") больше не рвут аргумент setHTML(...).
		echo '<script>$("breadcrumb").setHTML(' . json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');</script>';
	}
}
/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param string $title The title of the tooltip/text to link
 * @param string $url The link
 * @param string $tooltip The tooltip message
 * @param string $target The new links target
 * @return noreturn
 */
function CreateLink($title, $url, $tooltip="", $target="_self", $wide=false)
{
	if($wide)
		$class = "perm";
	else
		$class = "tip";
	if(strlen($tooltip) == 0)
	{
		echo '<a href="' . $url . '" target="' . $target . '">' . $title .' </a>';
	}else{
		echo '<a href="' . $url . '" class="' . $class .'" title="' .  $title . ' :: ' .  $tooltip . '" target="' . $target . '">' . $title .' </a>';
	}
}

/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param string $title The title of the tooltip/text to link
 * @param string $url The link
 * @param string $tooltip The tooltip message
 * @param string $target The new links target
 * @return URL
 */
/**
 * Приводит class='/href=' к class="/href=" в HTML-фрагментах (иконки меню из БД).
 */
function sb_normalize_html_attr_quotes($html)
{
	return preg_replace('/\b(class|href|id|src|rel|target)=\'([^\']*)\'/', '$1="$2"', (string)$html);
}

function CreateLinkR($title, $url, $tooltip="", $target="_self", $wide=false, $onclick="")
{
	if($wide)
		$class = "perm";
	else
		$class = "tip";
	$title = sb_normalize_html_attr_quotes($title);
	// XSS: $url часто содержит значения из запроса (поиск, фильтры и т.п.). Без экранирования
	// кавычки в $url позволяли вырваться из атрибута href и внедрить произвольные атрибуты/JS.
	$url_attr = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
	if(strlen($tooltip) == 0)
	{
		return '<a href="' . $url_attr . '" onclick="' . $onclick . '" target="' . $target . '">' . $title .' </a>';
	}else{
		return '<a href="' . $url_attr . '" class="' . $class .'" data-original-title="' .  htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '" target="' . $target . '" data-toggle="tooltip" data-placement="top">' . $title .' </a>';
	}
}

function HelpIcon($title, $text)
{
	// Тот же markup, что {help_icon}: Bootstrap popover, container=body.
	$t = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
	$m = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
	return '<img class="sb-ico sb-ico-help" src="images/icons/help.svg" width="18" height="18" alt="Справка"'
		. ' tabindex="0" role="button"'
		. ' data-toggle="popover" data-trigger="hover focus" data-placement="top" data-container="body"'
		. ' title="' . $t . '" data-content="' . $m . '">';
}

/**
 * Allows the title of the page to change wherever the code is being executed from
 *
 * @param string $title The new title
 * @return noreturn
 */
function RewritePageTitle($title)
{
	$GLOBALS['TitleRewrite'] = $title;
}

/**
 * Build sub-menu
 *
 * @param array $el The array of elements for the menu
 * @return noreturn
 */
function SubMenu($el)
{
	$output = "";
	$first = true;
	foreach($el AS $e)
	{
		$c = '';
		preg_match('/.*?&c=(.*)/', html_entity_decode($e['url']), $matches);
		if (!empty($matches[1]))
			$c = $matches[1];

		$output .= "<li style=\"".($first?"":"").(isset($_GET['c'])&&$_GET['c']==$c?"background-color: rgba(0, 0, 0, 0.075);":"")."\"><a href=\"" . $e['url'] . "\">" . $e['title']. "</a></li>";
		$first = false;
	}
	$GLOBALS['NavRewrite'] = $output;
}

/**
 * Converts a flag bitmask into a string
 *
 * @param integer $mask The mask to convert
 * @return string
 */
function BitToString($mask, $masktype=0, $head=true)
{
	$string = "";
		if($head)
			$string .= "<p class='c-blue'>Веб-права</p><ul class='clist clist-star'>";
		if($mask == 0)
		{
			$string .= "<li><i>Отсутствуют</i></li>";
			if($head)
				$string .= "</ul>";
			return $string;
		}
		if(($mask & ADMIN_LIST_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Список администраторов</li>";
		if(($mask & ADMIN_ADD_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Добавить администратора</li>";
		if(($mask & ADMIN_EDIT_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Редактировать администратора</li>";
		if(($mask & ADMIN_DELETE_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Удалить администратора</li>";

		if(($mask & ADMIN_LIST_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Список серверов</li>";
		if(($mask & ADMIN_ADD_SERVER) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Добавить сервер</li>";
		if(($mask & ADMIN_EDIT_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Редактировать сервер</li>";
		if(($mask & ADMIN_DELETE_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Удалить сервер</li>";

		if(($mask & ADMIN_ADD_BAN) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Добавить бан</li>";
		if(($mask & ADMIN_EDIT_OWN_BANS) !=0 && ($mask & ADMIN_EDIT_ALL_BANS) ==0)
			$string .="<li> Редактировать свои баны</li>";
		if(($mask & ADMIN_EDIT_GROUP_BANS) !=0 && ($mask & ADMIN_EDIT_ALL_BANS) ==0)
			$string .= "<li> Редактировать групповые баны</li>";
		if(($mask & ADMIN_EDIT_ALL_BANS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Редактировать все баны</li>";
		if(($mask & ADMIN_BAN_PROTESTS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Протесты баны</li>";
		if(($mask & ADMIN_BAN_SUBMISSIONS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Жалобы на игроков</li>";

		if(($mask & ADMIN_UNBAN_OWN_BANS) !=0 && ($mask & ADMIN_UNBAN) ==0)
			$string .= "<li> Разбан своих банов</li>";
		if(($mask & ADMIN_UNBAN_GROUP_BANS) !=0 && ($mask & ADMIN_UNBAN) ==0)
			$string .= "<li> Разбан банов групп</li>";
		if(($mask & ADMIN_UNBAN) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Разбан всех банов</li>";
		if(($mask & ADMIN_DELETE_BAN) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Удаление всех банов</li>";
		if(($mask & ADMIN_BAN_IMPORT) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Импорт банов</li>";

		if(($mask & ADMIN_LIST_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Просмотр групп</li>";
		if(($mask & ADMIN_ADD_GROUP) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Добавление групп</li>";
		if(($mask & ADMIN_EDIT_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Редактирование групп</li>";
		if(($mask & ADMIN_DELETE_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Удаление групп</li>";

		if(($mask & ADMIN_NOTIFY_SUB) !=0 || ($mask & ADMIN_NOTIFY_SUB) !=0)
			$string .= "<li> Уведомление по e-mail о предложении бана</li>";
		if(($mask & ADMIN_NOTIFY_PROTEST) !=0 || ($mask & ADMIN_NOTIFY_PROTEST) !=0)
			$string .= "<li> Уведомление по e-mail о протесте бана</li>";

		if(($mask & ADMIN_WEB_SETTINGS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Настройки ВЕБ</li>";

		if(($mask & ADMIN_LIST_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Просмотр МОДов</li>";
		if(($mask & ADMIN_ADD_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Добавление МОДов</li>";
		if(($mask & ADMIN_EDIT_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Редактирование МОДов</li>";
		if(($mask & ADMIN_DELETE_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Удаление МОДов</li>";

		if(($mask & ADMIN_OWNER) !=0)
			$string .= "<li> Главный админ</li>";
		
		if($head)
			$string .= "</ul>";

	return $string;
}


function SmFlagsToSb($flagstring, $head=true)
{

	$string = "";
	if($head)
		$string .= "<p class='c-blue'>Серверные права</p><ul class='clist clist-star'>";
	if(empty($flagstring))
		{
			$string .= "<li><i>Отсутствуют</i></li>";
			if($head)
				$string .= "</ul>";
			return $string;
		}
	if((strstr($flagstring, "a") || strstr($flagstring, "z")))
		$string .= "<li> Резервный слот</li>";
	if((strstr($flagstring, "b") || strstr($flagstring, "z")))
		$string .= "<li> Админ</li>";
	if((strstr($flagstring, "c") || strstr($flagstring, "z")))
		$string .= "<li> Кик</li>";
	if((strstr($flagstring, "d") || strstr($flagstring, "z")))
		$string .= "<li> Бан</li>";
	if((strstr($flagstring, "e") || strstr($flagstring, "z")))
		$string .= "<li> Разбан</li>";
	if((strstr($flagstring, "f") || strstr($flagstring, "z")))
		$string .= "<li> Слэй</li>";
	if((strstr($flagstring, "g") || strstr($flagstring, "z")))
		$string .= "<li> Смена карты</li>";
	if((strstr($flagstring, "h") || strstr($flagstring, "z")))
		$string .= "<li> Изменение КВАРов</li>";
	if((strstr($flagstring, "i") || strstr($flagstring, "z")))
		$string .= "<li> Исполнение конфигов</li>";
	if((strstr($flagstring, "j") || strstr($flagstring, "z")))
		$string .= "<li> Админский чат</li>";
	if((strstr($flagstring, "k") || strstr($flagstring, "z")))
		$string .="<li> Голосования</li>";
	if((strstr($flagstring, "l") || strstr($flagstring, "z")))
		$string .="<li> Пароль сервера</li>";
	if((strstr($flagstring, "m") || strstr($flagstring, "z")))
		$string .="<li> RCON</li>";
	if((strstr($flagstring, "n") || strstr($flagstring, "z")))
		$string .="<li> Разрешение читов</li>";
	if((strstr($flagstring, "z")))
		$string .="<li> Главный админ</li>";

	if((strstr($flagstring, "o") || strstr($flagstring, "z")))
		$string .="<li> Дополнительный флаг 1</li>";
	if((strstr($flagstring, "p") || strstr($flagstring, "z")))
		$string .="<li> Дополнительный флаг 2</li>";
	if((strstr($flagstring, "q") || strstr($flagstring, "z")))
		$string .="<li> Дополнительный флаг 3</li>";
	if((strstr($flagstring, "r") || strstr($flagstring, "z")))
		$string .="<li> Дополнительный флаг 4</li>";
	if((strstr($flagstring, "s") || strstr($flagstring, "z")))
		$string .="<li> Дополнительный флаг 5</li>";
	if((strstr($flagstring, "t") || strstr($flagstring, "z")))
		$string .="<li> Дополнительный флаг 6</li>";

	if($head)
		$string .= "</ul>";
	//if(($mask & SM_DEF_IMMUNITY) != 0)
	//{
	//	$flagstring .="&bull; Default immunity<br />";
	//}
	//if(($mask & SM_GLOBAL_IMMUNITY) != 0)
	//{
	//	$flagstring .="&bull; Global immunity<br />";
	//}
	
	return $string;

}

function PrintArray($array)
{
	echo "<pre>";
		print_r($array);
	echo "</pre>";
}

/**
 * Рекурсивно маскирует секреты в массивах для debug-панели.
 */
function sb_debug_scrub($data, $depth = 0)
{
	if ($depth > 8)
		return '...';
	if (!is_array($data))
		return $data;

	$sensitive = array(
		'password', 'passwd', 'pass', 'srv_password', 'smtp.password', 'smtp_password',
		'token', 'secret', 'csrf', 'phpsessid',
	);
	$out = array();
	foreach ($data as $k => $v) {
		$key = strtolower((string)$k);
		$redact = false;
		foreach ($sensitive as $s) {
			if ($key === $s || strpos($key, $s) !== false) {
				$redact = true;
				break;
			}
		}
		if ($redact && (is_string($v) || is_numeric($v)))
			$out[$k] = '[redacted]';
		elseif (is_array($v))
			$out[$k] = sb_debug_scrub($v, $depth + 1);
		elseif (is_object($v))
			$out[$k] = '[object ' . get_class($v) . ']';
		else
			$out[$k] = $v;
	}
	return $out;
}

/**
 * Компактная debug-панель вместо print_r всего CUserManager (там password hash).
 * Только ADMIN_OWNER; гостям и обычным админам не светим сессии/куки.
 */
function sb_render_developer_debug_panel($userbank)
{
	if (!defined('DEVELOPER_MODE'))
		return;
	if (!is_object($userbank) || !method_exists($userbank, 'is_logged_in') || !$userbank->is_logged_in())
		return;
	if (!method_exists($userbank, 'HasAccess') || !$userbank->HasAccess(ADMIN_OWNER))
		return;

	$safeUser = array(
		'aid' => method_exists($userbank, 'GetAid') ? $userbank->GetAid() : null,
		'user' => $userbank->GetProperty('user'),
		'authid' => $userbank->GetProperty('authid'),
		'gid' => $userbank->GetProperty('gid'),
		'email' => $userbank->GetProperty('email'),
		'extraflags' => $userbank->GetProperty('extraflags'),
		'srv_group' => $userbank->GetProperty('srv_group'),
	);

	$meta = array(
		'php' => PHP_VERSION,
		'sb_version' => defined('SB_VERSION') ? SB_VERSION : '?',
		'page' => isset($_GET['p']) ? (string)$_GET['p'] : '',
		'c' => isset($_GET['c']) ? (string)$_GET['c'] : '',
		'queries' => (isset($GLOBALS['db']) && is_object($GLOBALS['db']) && isset($GLOBALS['db']->Queries)) ? $GLOBALS['db']->Queries : '?',
		'memory_peak' => function_exists('memory_get_peak_usage') ? round(memory_get_peak_usage(true) / 1048576, 2) . ' MB' : '?',
		'display_errors' => ini_get('display_errors'),
	);

	$esc = function ($s) {
		return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
	};
	$dump = function ($label, $data) use ($esc) {
		$json = print_r($data, true);
		echo '<details class="sb-debug-block" style="margin:10px 0;">';
		echo '<summary style="cursor:pointer;font-weight:600;">' . $esc($label) . '</summary>';
		echo '<pre style="max-height:280px;overflow:auto;margin:8px 0 0;white-space:pre-wrap;word-break:break-word;">';
		echo $esc($json);
		echo '</pre></details>';
	};

	// Вне #content — без отступа сайдбара панель уезжает под меню (как #footer: padding-left 268px).
	echo '<div class="sb-debug-panel">';
	echo '<div class="container">';
	echo '<div class="card" style="border:1px solid rgba(255,193,7,.45);">';
	echo '<div class="card-header"><h2>Режим отладки <small>только владелец · секреты скрыты</small></h2></div>';
	echo '<div class="card-body card-padding">';
	echo '<p class="m-b-10">Включены PHP <code>display_errors</code> и принудительная компиляция Smarty. ';
	echo 'Отключить: настройки → «Режим отладки» или закомментировать <code>define(\'DEVELOPER_MODE\', true);</code> в <code>config.php</code>.</p>';
	$dump('Сводка', $meta);
	$dump('Текущий админ (без паролей)', $safeUser);
	$dump('POST', sb_debug_scrub($_POST));
	$dump('SESSION', sb_debug_scrub($_SESSION));
	$dump('COOKIE', sb_debug_scrub($_COOKIE));
	echo '</div></div></div></div>';
}

function NextGid()
{
	$gid = $GLOBALS['db']->GetRow("SELECT MAX(gid) AS next_gid FROM `" . DB_PREFIX . "_groups`");
	return ($gid['next_gid']+1);
}
function NextSGid()
{
	$gid = $GLOBALS['db']->GetRow("SELECT MAX(id) AS next_id FROM `" . DB_PREFIX . "_srvgroups`");
	return ($gid['next_id']+1);
}
function NextSid()
{
	$sid = $GLOBALS['db']->GetRow("SELECT MAX(sid) AS next_sid FROM `" . DB_PREFIX . "_servers`");
	return ($sid['next_sid']+1);
}
function NextAid()
{
	$aid = $GLOBALS['db']->GetRow("SELECT MAX(aid) AS next_aid FROM `" . DB_PREFIX . "_admins`");
	return ($aid['next_aid']+1);
}

function trunc($text, $len, $byword=true)
{
	if(strlen($text) <= $len)
		return $text;
    $text = $text." ";
    $text = substr($text,0,$len);
    if($byword)
    	$text = substr($text,0,strrpos($text,' '));
    $text = $text."...";
    return $text;
}

function StripQuotes($str)
{
	$str = str_replace("'", "", $str);
	$str = str_replace('"', "", $str);
	return $str;
}

/*function CreateRedBox($title, $content)
{
	$text = '<div id="msg-red-debug" style="">
	<i><img class="sb-ico" src="images/icons/warning.svg" width="18" height="18" alt="Внимание" /></i>
	<b>' . $title .'</b>
	<br />
	' . $content . '</i>
</div>';

	echo $text;
}
function CreateGreenBox($title, $contnet)
{
	$text = '<div id="msg-green-dbg" style="">
	<i><img src="./images/yay.png" alt="Yay!" /></i>
	<b>' . $title .'</b>
	<br />
	' . $contnet . '</i>
</div>';

	echo $text;
}
*/
function CreateRedBox($title, $content)
{
	$text = '<div class="alert alert-danger" id="msg-red-debug" role="alert"><h4>' . $title .'</h4><span class="p-l-10">' . $content . '</span></div>';
	echo $text;
}

function CreateGreenBox($title, $content)
{
	$text = '<div class="alert alert-success" id="msg-green-dbg" role="alert"><h4>' . $title .'</h4><span class="p-l-10">' . $content . '</span></div>';
	echo $text;
}

function CheckAdminAccess($mask)
{
	global $userbank;
	if(!$userbank->HasAccess($mask))
	{
		RedirectJS("index.php?p=login&m=no_access");
		die();
	}
}

function RedirectJS($url)
{
	$url = function_exists('sb_abs_url') ? sb_abs_url($url) : (string)$url;
	$js = json_encode((string)$url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	echo '<script>if(typeof sbGo==="function")sbGo(' . $js . ');else if(typeof sbAbs==="function")window.location.href=sbAbs(' . $js . ');else window.location.href=' . $js . ';</script>';
}

function RemoveCode($text)
{
	// ENT_QUOTES обязателен: на PHP < 8.1 кавычки иначе не экранируются (CVE-2026-30760).
	return htmlspecialchars(strip_tags((string)$text), ENT_QUOTES, 'UTF-8');
}

function sb_sanitize_admin_html_url_allowed($url, $allow_relative = true)
{
	$url = html_entity_decode(trim((string)$url), ENT_QUOTES, 'UTF-8');
	if ($url === '')
		return false;

	$url = preg_replace('/[\x00-\x20\x7f]+/u', '', $url);
	if ($url === '')
		return false;

	$lower = strtolower($url);
	if (preg_match('/^(javascript|data|vbscript)\s*:/i', $lower))
		return false;

	if (preg_match('/^([a-z][a-z0-9+.-]*)\s*:/i', $lower, $m))
		return in_array($m[1], array('http', 'https', 'mailto', 'tel'), true);

	if (!$allow_relative)
		return false;

	return ($lower[0] === '/' || $lower[0] === '#' || $lower[0] === '?' || substr($lower, 0, 2) === './' || substr($lower, 0, 3) === '../');
}

/**
 * Inline style для Summernote-правил: keep allowlist CSS, drop expression/js/url(data).
 */
function sb_sanitize_admin_html_style($style)
{
	$style = html_entity_decode((string)$style, ENT_QUOTES, 'UTF-8');
	$style = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $style);
	if ($style === null || $style === '')
		return '';

	$lower = strtolower($style);
	if (strpos($lower, 'expression(') !== false
		|| strpos($lower, 'javascript:') !== false
		|| strpos($lower, 'vbscript:') !== false
		|| strpos($lower, 'behavior:') !== false
		|| strpos($lower, '-moz-binding') !== false
		|| strpos($lower, '@import') !== false)
		return '';

	$allowed = array(
		'color' => true, 'background' => true, 'background-color' => true, 'background-image' => true,
		'background-repeat' => true, 'background-position' => true, 'background-size' => true,
		'font' => true, 'font-family' => true, 'font-size' => true, 'font-weight' => true, 'font-style' => true,
		'line-height' => true, 'letter-spacing' => true, 'text-align' => true, 'text-decoration' => true,
		'text-transform' => true, 'text-shadow' => true, 'white-space' => true, 'word-break' => true,
		'overflow-wrap' => true, 'vertical-align' => true,
		'margin' => true, 'margin-top' => true, 'margin-right' => true, 'margin-bottom' => true, 'margin-left' => true,
		'padding' => true, 'padding-top' => true, 'padding-right' => true, 'padding-bottom' => true, 'padding-left' => true,
		'border' => true, 'border-top' => true, 'border-right' => true, 'border-bottom' => true, 'border-left' => true,
		'border-color' => true, 'border-style' => true, 'border-width' => true, 'border-radius' => true,
		'border-collapse' => true, 'border-spacing' => true,
		'width' => true, 'max-width' => true, 'min-width' => true, 'height' => true, 'max-height' => true, 'min-height' => true,
		'display' => true, 'float' => true, 'clear' => true, 'opacity' => true, 'box-sizing' => true,
		'list-style' => true, 'list-style-type' => true, 'list-style-position' => true,
		'overflow' => true, 'overflow-x' => true, 'overflow-y' => true,
		'flex' => true, 'flex-direction' => true, 'flex-wrap' => true, 'justify-content' => true, 'align-items' => true, 'gap' => true,
		'box-shadow' => true, 'outline' => true, 'cursor' => true,
	);

	$out = array();
	foreach (explode(';', $style) as $decl) {
		$decl = trim($decl);
		if ($decl === '' || strpos($decl, ':') === false)
			continue;
		$parts = explode(':', $decl, 2);
		$prop = strtolower(trim($parts[0]));
		$val = trim($parts[1]);
		if ($prop === '' || $val === '' || !isset($allowed[$prop]))
			continue;
		$vlow = strtolower($val);
		if (strpos($vlow, 'expression(') !== false || strpos($vlow, 'javascript:') !== false || strpos($vlow, 'vbscript:') !== false)
			continue;
		if (preg_match('/url\s*\(\s*[\'"]?\s*data\s*:/i', $val) || preg_match('/url\s*\(\s*[\'"]?\s*javascript\s*:/i', $val))
			continue;
		// url(...) только http(s)/relative
		if (preg_match_all('/url\s*\(\s*(.*)\s*\)/i', $val, $um)) {
			$badUrl = false;
			foreach ($um[1] as $u) {
				$u = trim($u, " \t\"'");
				if ($u !== '' && !sb_sanitize_admin_html_url_allowed($u, true)) {
					$badUrl = true;
					break;
				}
			}
			if ($badUrl)
				continue;
		}
		$out[] = $prop . ': ' . $val;
	}
	return implode('; ', $out);
}

function sb_sanitize_admin_html_node(DOMNode $node, array $allowed_tags, array $allowed_attrs)
{
	for ($child = $node->firstChild; $child !== null; ) {
		$next = $child->nextSibling;

		if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
			$node->removeChild($child);
			$child = $next;
			continue;
		}

		if ($child->nodeType === XML_ELEMENT_NODE) {
			$tag = strtolower($child->nodeName);
			if (isset($allowed_tags[$tag])) {
				$attrs = array();
				if ($child->hasAttributes()) {
					foreach ($child->attributes as $attr) {
						$attrs[] = strtolower($attr->nodeName);
					}
				}
				foreach ($attrs as $attr_name) {
					if (strpos($attr_name, 'on') === 0) {
						$child->removeAttribute($attr_name);
						continue;
					}
					if ($attr_name === 'style') {
						$safe = sb_sanitize_admin_html_style($child->getAttribute('style'));
						if ($safe === '')
							$child->removeAttribute('style');
						else
							$child->setAttribute('style', $safe);
						continue;
					}
					if (!in_array($attr_name, $allowed_attrs[$tag], true)) {
						$child->removeAttribute($attr_name);
						continue;
					}
					if (($attr_name === 'href' || $attr_name === 'src') && !sb_sanitize_admin_html_url_allowed($child->getAttribute($attr_name), $tag === 'a')) {
						if ($attr_name === 'src' && $tag === 'img') {
							$node->removeChild($child);
							$child = $next;
							continue 2;
						}
						$child->removeAttribute($attr_name);
					}
				}

				if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank' && !$child->hasAttribute('rel')) {
					$child->setAttribute('rel', 'noopener noreferrer');
				}

				sb_sanitize_admin_html_node($child, $allowed_tags, $allowed_attrs);
			} else {
				if (isset($allowed_tags['_drop'][$tag])) {
					$node->removeChild($child);
				} else {
					while ($child->firstChild) {
						$node->insertBefore($child->firstChild, $child);
					}
					$node->removeChild($child);
				}
			}
		} elseif ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
			$node->removeChild($child);
		}

		$child = $next;
	}
}

/**
 * Allowlist-санация HTML из админки (dash intro, info block и т.п.).
 * Сохраняет ограниченный набор тегов и безопасных атрибутов, остальное удаляет.
 */
function sb_sanitize_admin_html($html)
{
	$html = (string)$html;
	if ($html === '')
		return '';

	if (!class_exists('DOMDocument')) {
		return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
	}

	$allowed_tags = array(
		'a' => true,
		'b' => true,
		'blockquote' => true,
		'br' => true,
		'code' => true,
		'center' => true,
		'div' => true,
		'em' => true,
		'font' => true,
		'h1' => true,
		'h2' => true,
		'h3' => true,
		'h4' => true,
		'h5' => true,
		'h6' => true,
		'hr' => true,
		'i' => true,
		'img' => true,
		'li' => true,
		'ol' => true,
		'p' => true,
		'pre' => true,
		's' => true,
		'small' => true,
		'span' => true,
		'strong' => true,
		'sub' => true,
		'sup' => true,
		'table' => true,
		'tbody' => true,
		'td' => true,
		'th' => true,
		'thead' => true,
		'tfoot' => true,
		'tr' => true,
		'u' => true,
		'ul' => true,
		'_drop' => array(
			'base' => true,
			'embed' => true,
			'form' => true,
			'iframe' => true,
			'link' => true,
			'meta' => true,
			'object' => true,
			'script' => true,
			// <style> блок по-прежнему drop; inline style= чистится отдельно
			'style' => true,
		),
	);
	// style обрабатывается отдельно (allowlist CSS), здесь перечислен для прохождения проверки in_array
	$common = array('class', 'id', 'title', 'style', 'align');
	$allowed_attrs = array(
		'b' => $common,
		'br' => array(),
		'a' => array_merge($common, array('href', 'name', 'rel', 'target')),
		'blockquote' => $common,
		'center' => $common,
		'code' => $common,
		'div' => $common,
		'em' => $common,
		'font' => array_merge($common, array('color', 'face', 'size')),
		'h1' => $common,
		'h2' => $common,
		'h3' => $common,
		'h4' => $common,
		'h5' => $common,
		'h6' => $common,
		'hr' => $common,
		'i' => $common,
		'img' => array_merge($common, array('alt', 'height', 'loading', 'src', 'width')),
		'li' => $common,
		'ol' => $common,
		'p' => $common,
		'pre' => $common,
		's' => $common,
		'small' => $common,
		'span' => $common,
		'strong' => $common,
		'sub' => $common,
		'sup' => $common,
		'table' => array_merge($common, array('border', 'cellpadding', 'cellspacing', 'width')),
		'tbody' => $common,
		'td' => array_merge($common, array('colspan', 'rowspan', 'scope', 'width', 'height', 'valign')),
		'th' => array_merge($common, array('colspan', 'rowspan', 'scope', 'width', 'height', 'valign')),
		'thead' => $common,
		'tfoot' => $common,
		'tr' => $common,
		'u' => $common,
		'ul' => $common,
	);

	$prev = libxml_use_internal_errors(true);
	$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = true;
	$dom->formatOutput = false;

	$wrapped = '<div id="sb-html-root">' . $html . '</div>';
	if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD) === false) {
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
	}

	$root = null;
	foreach ($dom->getElementsByTagName('div') as $div) {
		if ($div->getAttribute('id') === 'sb-html-root') {
			$root = $div;
			break;
		}
	}
	if (!$root instanceof DOMElement) {
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
	}

	sb_sanitize_admin_html_node($root, $allowed_tags, $allowed_attrs);

	$out = '';
	foreach ($root->childNodes as $child) {
		$out .= $dom->saveHTML($child);
	}

	libxml_clear_errors();
	libxml_use_internal_errors($prev);

	return $out;
}

/**
 * Грубая санация «доверенного» HTML из админки (dash intro и т.п.):
 * убирает script/iframe и inline-обработчики / javascript: URL.
 */
function sb_sanitize_admin_html_legacy($html)
{
	$html = (string)$html;
	if ($html === '')
		return '';
	$html = preg_replace('#<\s*(script|iframe|object|embed|link|meta|style|form|base)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
	$html = preg_replace('#<\s*(script|iframe|object|embed|link|meta|style|form|base)[^>]*/?\s*>#is', '', $html);
	$html = preg_replace('/\son[a-z]+\s*=\s*("|\')(?:(?!\1).)*\1/iu', '', $html);
	$html = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/iu', '', $html);
	$html = preg_replace('/\s(href|src|action)\s*=\s*("|\')\s*javascript:[^\'"]*\2/iu', ' $1="#"', $html);
	$html = preg_replace('/\s(href|src|action)\s*=\s*javascript:[^\s>]*/iu', ' $1="#"', $html);
	return $html;
}

/** Разрешает только http(s) URL; иначе пустая строка. */
function sb_safe_http_url($url)
{
	$url = trim((string)$url);
	if ($url === '')
		return '';
	if (!preg_match('#^https?://#i', $url))
		return '';
	return $url;
}

/** Нормализация ключа ваучера → lowercase hex без разделителей. */
function sb_voucher_normalize_key($raw)
{
	return strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$raw));
}

/** Валидный ключ: 32 hex-символа (16 байт / 128 bit). */
function sb_voucher_key_valid($key)
{
	return is_string($key) && strlen($key) === 32 && ctype_xdigit($key);
}

/** Криптостойкий HEX-ключ ваучера (по умолчанию 16 байт → 32 hex). */
function sb_voucher_generate_key($bytes = 16)
{
	$bytes = (int)$bytes;
	if ($bytes < 8)
		$bytes = 8;
	if ($bytes > 32)
		$bytes = 32;
	if (function_exists('random_bytes'))
		return bin2hex(random_bytes($bytes));
	if (function_exists('openssl_random_pseudo_bytes')) {
		$raw = openssl_random_pseudo_bytes($bytes, $strong);
		if ($raw !== false && strlen($raw) === $bytes)
			return bin2hex($raw);
	}
	$out = '';
	for ($i = 0; $i < $bytes; $i++)
		$out .= sprintf('%02x', mt_rand(0, 255));
	return $out;
}

/** Красивый вывод: a1b2c3d4e5f6… → a1b2-c3d4-e5f6-… */
function sb_voucher_format_key($key)
{
	$key = sb_voucher_normalize_key($key);
	if ($key === '')
		return '';
	return implode('-', str_split($key, 4));
}

/**
 * После успешной капчи + проверки ключа — одноразовый «разблокированный» код в сессии.
 * AddAdmin_pay принимает только его (нельзя скипнуть капчу прямым xajax).
 */
function sb_voucher_unlock_set($key)
{
	$key = sb_voucher_normalize_key($key);
	if (!sb_voucher_key_valid($key))
		return false;
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	$_SESSION['sb_voucher_unlock'] = array(
		'code' => $key,
		'exp' => time() + 1800,
		'token' => function_exists('random_bytes') ? bin2hex(random_bytes(16)) : md5(uniqid((string)mt_rand(), true)),
	);
	return $_SESSION['sb_voucher_unlock']['token'];
}

function sb_voucher_unlock_check($key)
{
	$key = sb_voucher_normalize_key($key);
	if (!sb_voucher_key_valid($key))
		return false;
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	if (empty($_SESSION['sb_voucher_unlock']) || !is_array($_SESSION['sb_voucher_unlock']))
		return false;
	$u = $_SESSION['sb_voucher_unlock'];
	if (empty($u['code']) || empty($u['exp']) || (int)$u['exp'] < time())
		return false;
	return hash_equals((string)$u['code'], $key);
}

function sb_voucher_unlock_clear()
{
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	unset($_SESSION['sb_voucher_unlock']);
}

/** После успешной активации — короткое окно на RCON rehash (ваучер уже activ=0). */
function sb_voucher_rehash_set($key)
{
	$key = sb_voucher_normalize_key($key);
	if (!sb_voucher_key_valid($key))
		return false;
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	$_SESSION['sb_voucher_rehash'] = array(
		'code' => $key,
		'exp' => time() + 600,
	);
	return true;
}

function sb_voucher_rehash_check($key)
{
	$key = sb_voucher_normalize_key($key);
	if (!sb_voucher_key_valid($key))
		return false;
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	if (empty($_SESSION['sb_voucher_rehash']) || !is_array($_SESSION['sb_voucher_rehash']))
		return false;
	$u = $_SESSION['sb_voucher_rehash'];
	if (empty($u['code']) || empty($u['exp']) || (int)$u['exp'] < time())
		return false;
	return hash_equals((string)$u['code'], $key);
}

function sb_voucher_rehash_clear()
{
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	unset($_SESSION['sb_voucher_rehash']);
}

/**
 * Создать активный ваучер (HEX-ключ). Используется админкой и api/voucher_create.php.
 * @return array{ok:bool,error?:string,key?:string,key_fmt?:string,days?:int,group_web?:string,group_srv?:string,servers?:string}
 */
function sb_voucher_create_record($days, $group_web, $group_srv = '', $servers = '')
{
	if (!isset($GLOBALS['db']) || !is_object($GLOBALS['db']))
		return array('ok' => false, 'error' => 'db');

	$days = (int)$days;
	if ($days < 0)
		$days = 0;
	if ($days > 36500)
		$days = 36500;

	$group_web = trim(strip_tags((string)$group_web));
	$group_srv = trim(strip_tags((string)$group_srv));
	if ($group_web === '')
		return array('ok' => false, 'error' => 'group_web_required');

	if ($group_web !== '0') {
		$exists = (int)$GLOBALS['db']->GetOne(
			"SELECT COUNT(*) FROM `" . DB_PREFIX . "_groups` WHERE `name` = ?",
			array($group_web)
		);
		if ($exists < 1)
			return array('ok' => false, 'error' => 'group_web_unknown');
	}

	if ($group_srv !== '' && $group_srv !== '0') {
		$exists = (int)$GLOBALS['db']->GetOne(
			"SELECT COUNT(*) FROM `" . DB_PREFIX . "_srvgroups` WHERE `name` = ?",
			array($group_srv)
		);
		if ($exists < 1)
			return array('ok' => false, 'error' => 'group_srv_unknown');
	} else {
		$group_srv = '';
	}

	$servers = trim((string)$servers);
	if ($servers !== '' && $servers !== '-1') {
		$parts = preg_split('/\s*,\s*/', $servers, -1, PREG_SPLIT_NO_EMPTY);
		$norm = array();
		if (is_array($parts)) {
			foreach ($parts as $p) {
				if (preg_match('/^s?(\d+)$/i', $p, $m)) {
					$sid = (int)$m[1];
					$okSid = $GLOBALS['db']->GetOne(
						"SELECT sid FROM `" . DB_PREFIX . "_servers` WHERE sid = ?",
						array($sid)
					);
					if ($okSid)
						$norm[] = 's' . $sid;
				}
			}
		}
		$servers = $norm ? (',' . implode(',', $norm)) : '';
	}

	for ($i = 0; $i < 8; $i++) {
		$key = sb_voucher_generate_key(16);
		if (!sb_voucher_key_valid($key))
			continue;
		$cnt = (int)$GLOBALS['db']->GetOne(
			"SELECT COUNT(*) FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ?",
			array($key)
		);
		if ($cnt > 0)
			continue;
		$ok = $GLOBALS['db']->Execute(
			"INSERT INTO `" . DB_PREFIX . "_vay4er` (`activ`, `value`, `days`, `group_web`, `group_srv`, `servers`)
			 VALUES (1, ?, ?, ?, ?, ?)",
			array($key, (string)$days, $group_web, $group_srv, $servers)
		);
		if (!$ok)
			return array('ok' => false, 'error' => 'insert_failed');
		return array(
			'ok' => true,
			'key' => $key,
			'key_fmt' => sb_voucher_format_key($key),
			'days' => $days,
			'group_web' => $group_web,
			'group_srv' => $group_srv,
			'servers' => $servers,
		);
	}
	return array('ok' => false, 'error' => 'key_collision');
}

/** Токен API задан и не пустой. */
function sb_voucher_api_enabled()
{
	return defined('SB_VOUCHER_API_TOKEN')
		&& is_string(SB_VOUCHER_API_TOKEN)
		&& SB_VOUCHER_API_TOKEN !== '';
}

/**
 * Живой тест api/voucher_create.php (HTTP POST на свой URL + токен из config).
 * @param bool $keep true — оставить ваучер в БД; false — удалить после успеха
 * @return array
 */
function sb_voucher_api_self_test($keep = false)
{
	if (!sb_voucher_api_enabled())
		return array('ok' => false, 'error' => 'api_disabled', 'hint' => 'SB_VOUCHER_API_TOKEN пустой');

	$base = (defined('SB_WP_URL') && SB_WP_URL !== '') ? rtrim(SB_WP_URL, '/') : '';
	if ($base === '')
		return array('ok' => false, 'error' => 'no_sb_wp_url', 'hint' => 'Задай SB_WP_URL в config.php');

	$url = $base . '/api/voucher_create.php';
	// token в JSON: Apache/CGI часто выкидывает Authorization; тело надёжнее.
	$payload = json_encode(array(
		'token' => SB_VOUCHER_API_TOKEN,
		'days' => 1,
		'group_web' => '0',
		'group_srv' => '',
		'servers' => '',
	));

	$httpCode = 0;
	$body = '';
	$err = '';

	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . SB_VOUCHER_API_TOKEN,
				'X-SB-Voucher-Token: ' . SB_VOUCHER_API_TOKEN,
			),
		));
		$body = curl_exec($ch);
		if ($body === false)
			$err = curl_error($ch);
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	} else {
		$ctx = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: application/json\r\n"
					. "Authorization: Bearer " . SB_VOUCHER_API_TOKEN . "\r\n"
					. "X-SB-Voucher-Token: " . SB_VOUCHER_API_TOKEN . "\r\n",
				'content' => $payload,
				'timeout' => 15,
				'ignore_errors' => true,
			),
		));
		$body = @file_get_contents($url, false, $ctx);
		if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m))
			$httpCode = (int)$m[1];
		if ($body === false)
			$err = 'file_get_contents failed';
	}

	if ($body === false || $body === '') {
		return array(
			'ok' => false,
			'error' => 'http_failed',
			'hint' => $err !== '' ? $err : 'Пустой ответ',
			'url' => $url,
			'http' => $httpCode,
		);
	}

	$data = json_decode($body, true);
	if (!is_array($data)) {
		return array(
			'ok' => false,
			'error' => 'bad_json',
			'http' => $httpCode,
			'raw' => substr($body, 0, 500),
			'url' => $url,
		);
	}

	$data['http'] = $httpCode;
	$data['url'] = $url;

	if (!empty($data['ok']) && !empty($data['key']) && !$keep && isset($GLOBALS['db'])) {
		$GLOBALS['db']->Execute(
			"DELETE FROM `" . DB_PREFIX . "_vay4er` WHERE `value` = ? AND `activ` = '1'",
			array($data['key'])
		);
		$data['cleaned'] = true;
	} else {
		$data['cleaned'] = false;
	}

	return $data;
}

function SecondsToString($sec, $textual=true)
{
	if($textual)
	{
		$div = array( 2592000, 604800, 86400, 3600, 60, 1 );
		$desc = array('Мес','Нед','Дн','Час','Мин','Сек');
		$ret = null;
		foreach($div as $index => $value)
		{
			$quotent = floor($sec / $value); //greatest whole integer
			if($quotent > 0) {
				$ret .= "$quotent {$desc[$index]}, ";
				$sec %= $value;
			}
		}
		return substr($ret,0,-2);
	}
	else
	{
		$hours = floor ($sec / 3600);
		$sec -= $hours * 3600;
		$mins = floor ($sec / 60);
		$secs = $sec % 60;
		return "$hours:$mins:$secs";
	}
}

// unused, as loading too slowly.
function CreateHostnameCache()
{
	require_once INCLUDES_PATH.'/CServerControl.php';
	$res = $GLOBALS['db']->Execute("SELECT sid, ip, port FROM ".DB_PREFIX."_servers ORDER BY sid");
	$servers = array();
	while (!$res->EOF)
	{
		$info = array();
		$sinfo = new CServerControl($res->fields[1],$res->fields[2]);
		$sinfo->Connect($res->fields[1], $res->fields[2]);
		$info = $sinfo->GetInfo();
		if(!empty($info['HostName']))
			$servers[$res->fields[0]] = $info['HostName'];
		else
			$servers[$res->fields[0]] = $res->fields[1].":".$res->fields[2];
		$res->MoveNext();
	}
	return($servers);
}

function FetchIp($ip)
{
	$ip = sprintf('%u', ip2long($ip));
	if(!isset($_SESSION['CountryFetchHndl']) || !is_resource($_SESSION['CountryFetchHndl'])) {
		$handle = fopen(INCLUDES_PATH.'/IpToCountry.csv', "r");
		$_SESSION['CountryFetchHndl'] = $handle;
	}
	else {
		$handle = $_SESSION['CountryFetchHndl'];
		rewind($handle);
	}

	if (!$handle)
		return "zz";

	while (($ipdata = fgetcsv($handle, 4096)) !== FALSE) {
		// If line is comment or IP is out of range
		if ($ipdata[0][0] == '#' || $ip < $ipdata[0] || $ip > $ipdata[1])
			continue;

		// БАГ-ФИКС: формат IpToCountry.csv сменился со старого 7-колоночного
		// (software77.net, сайт больше не существует) на новый 3-колоночный
		// "ipFrom,ipTo,countryCode" (ip-location-db) - код страны теперь в
		// индексе 2, а не 4.
		if(empty($ipdata[2]))
			return "zz";
		return $ipdata[2];
	}

	return "zz";
}

function PageDie()
{
	include TEMPLATES_PATH.'/footer.php';
	die();
}

/** Страницы с ЧПУ: /banlist вместо index.php?p=banlist */
function sb_pretty_pages()
{
	return array(
		'login', 'logout', 'admin', 'submit', 'banlist', 'commslist', 'servers',
		'protest', 'account', 'lostpassword', 'login2fa', 'home', 'search_bans', 'search_comm',
		'pay', 'adminlist',
	);
}

/**
 * ЧПУ-ссылка: sb_url('banlist'), sb_url('admin', array('c'=>'bans')), sb_url('banlist', array('page'=>2)).
 * Старые index.php?p=… тоже работают (редирект/rewrite).
 */
function sb_url($p, $extra = array())
{
	if (!is_array($extra))
		$extra = array();
	$p = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$p);
	$c = '';
	if (isset($extra['c'])) {
		$c = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$extra['c']);
		unset($extra['c']);
	}
	unset($extra['p']);

	$pages = sb_pretty_pages();
	if ($p === 'admin' && $c !== '') {
		$path = 'admin/' . $c;
	} elseif ($p !== '' && in_array($p, $pages, true)) {
		$path = ($p === 'home') ? './' : $p;
	} else {
		$q = $extra;
		if ($p !== '')
			$q = array_merge(array('p' => $p), $q);
		if ($c !== '')
			$q['c'] = $c;
		$qs = http_build_query($q);
		return 'index.php' . ($qs !== '' ? ('?' . $qs) : '');
	}

	// /banlist/2 вместо banlist?page=2 (остальные GET остаются в query)
	if (($p === 'banlist' || $p === 'commslist') && isset($extra['page'])) {
		$pageNum = (int)$extra['page'];
		unset($extra['page']);
		if ($pageNum > 1)
			$path .= '/' . $pageNum;
	}

	// /admin/recidivism/STEAM_0-0-123 вместо ?steam=STEAM_0%3A0%3A123
	if ($p === 'admin' && ($c === 'recidivism' || $c === 'parsec') && isset($extra['steam'])) {
		$sid = trim((string)$extra['steam']);
		if (function_exists('ma_recidivism_normalize_authid')) {
			$norm = ma_recidivism_normalize_authid($sid);
			if ($norm !== '')
				$sid = $norm;
		} elseif (function_exists('ma_parsec_normalize_authid')) {
			$norm = ma_parsec_normalize_authid($sid);
			if ($norm !== '')
				$sid = $norm;
		}
		$token = sb_steam_path_token($sid);
		if ($token !== '') {
			$path .= '/' . $token;
			unset($extra['steam']);
		}
	}

	$qs = http_build_query($extra);
	return $path . ($qs !== '' ? ('?' . $qs) : '');
}

/** STEAM_0:1:123 → STEAM_0-1-123 (для path; «:» в URL на Windows/Apache — боль). */
function sb_steam_path_token($steam)
{
	$steam = trim((string)$steam);
	if (preg_match('/^STEAM_(\d+):([01]):(\d+)$/i', $steam, $m))
		return 'STEAM_' . $m[1] . '-' . $m[2] . '-' . $m[3];
	if (preg_match('/^STEAM_(\d+)-([01])-(\d+)$/i', $steam, $m))
		return 'STEAM_' . $m[1] . '-' . $m[2] . '-' . $m[3];
	return '';
}

function sb_steam_from_path_token($token)
{
	$token = trim((string)$token);
	if (preg_match('/^STEAM_(\d+)-([01])-(\d+)$/i', $token, $m))
		return 'STEAM_' . $m[1] . ':' . $m[2] . ':' . $m[3];
	return '';
}

/** Rewrite отдаёт steam=STEAM_0-0-N — вернуть классический STEAM_0:0:N в $_GET. */
function sb_apply_steam_path_param()
{
	if (empty($_GET['steam']))
		return;
	$from = sb_steam_from_path_token($_GET['steam']);
	if ($from !== '')
		$_GET['steam'] = $from;
}

/** /admin/parsec?steam=STEAM_0:0:N → /admin/parsec/STEAM_0-0-N */
function sb_canonical_admin_steam_redirect($section)
{
	if ($section !== 'recidivism' && $section !== 'parsec')
		return;
	if (empty($_GET['steam']))
		return;
	sb_apply_steam_path_param();
	$steam = trim((string)$_GET['steam']);
	if (function_exists('ma_recidivism_normalize_authid')) {
		$norm = ma_recidivism_normalize_authid($steam);
		if ($norm !== '')
			$steam = $norm;
	} elseif (function_exists('ma_parsec_normalize_authid')) {
		$norm = ma_parsec_normalize_authid($steam);
		if ($norm !== '')
			$steam = $norm;
	}
	$token = sb_steam_path_token($steam);
	if ($token === '')
		return;
	$uriPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
	if (is_string($uriPath) && preg_match('#/admin/' . preg_quote($section, '#') . '/' . preg_quote($token, '#') . '/?$#i', $uriPath))
		return;
	$q = $_GET;
	unset($q['p'], $q['c']);
	$q['steam'] = $steam;
	sb_redirect(sb_url('admin', array_merge(array('c' => $section), $q)), 301);
}

/**
 * Если открыли /banlist?page=2 — 301 на /banlist/2 (и то же для commslist).
 * Вызывать в начале page.banlist / page.commslist.
 */
function sb_canonical_list_page_redirect($listPage)
{
	if ($listPage !== 'banlist' && $listPage !== 'commslist')
		return;
	if (empty($_GET['page']) || (int)$_GET['page'] < 2)
		return;
	// Уже красивый путь /banlist/2 — в QUERY_STRING page из rewrite, в URI есть /N
	$uriPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
	if (is_string($uriPath) && preg_match('#/' . preg_quote($listPage, '#') . '/[0-9]+/?$#', $uriPath))
		return;
	// Только если page реально в query string (не только из path rewrite без ?page= в URI)
	$qs = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
	if ($qs === '' || !preg_match('/(?:^|&)page=/i', $qs))
		return;
	$q = $_GET;
	unset($q['p']);
	sb_redirect(sb_url($listPage, $q), 301);
}

/**
 * ЧПУ из хвоста query (?a=1&b=2 или &page=2).
 * sb_url_query('banlist', '&page=2&searchText=x') → banlist?page=2&searchText=x
 */
function sb_url_query($p, $query = '', $extra = array())
{
	if (!is_array($extra))
		$extra = array();
	$qstr = html_entity_decode((string)$query, ENT_QUOTES, 'UTF-8');
	$qstr = ltrim($qstr, "?& \t\n\r");
	if ($qstr !== '') {
		parse_str($qstr, $parsed);
		if (is_array($parsed) && $parsed)
			$extra = array_merge($parsed, $extra);
	}
	return sb_url($p, $extra);
}

/**
 * Абсолютный URL для Location: (браузер НЕ учитывает <base href>).
 * admin/bans → http://site/admin/bans или /admin/bans
 */
function sb_abs_url($url)
{
	$url = (string)$url;
	if ($url === '' || $url[0] === '/' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url))
		return $url === '' ? '/' : $url;
	$base = defined('SB_WP_URL') ? rtrim((string)SB_WP_URL, '/') : '';
	$rel = ltrim($url, './');
	if ($base !== '')
		return $base . '/' . $rel;
	return '/' . $rel;
}

/** 303-редирект на ЧПУ (PRG), с очисткой буферов. */
function sb_redirect($url, $code = 303)
{
	while (ob_get_level() > 0)
		@ob_end_clean();
	header('Location: ' . sb_abs_url($url), true, (int)$code);
	exit;
}

/** Превратить index.php?p=banlist&c=… / ?p=… в /banlist или /admin/c. */
function sb_legacy_to_pretty_url($url)
{
	$url = (string)$url;
	if ($url === '' || $url[0] === '#' || preg_match('#^(https?:)?//#i', $url) || stripos($url, 'javascript:') === 0)
		return $url;
	$hadAmp = (strpos($url, '&amp;') !== false);
	$u = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
	$frag = '';
	$hashPos = strpos($u, '#');
	if ($hashPos !== false) {
		$frag = substr($u, $hashPos);
		$u = substr($u, 0, $hashPos);
	}
	$query = '';
	if (preg_match('#^(?:\.\./)*(?:\./)?index\.php\?(.*)$#i', $u, $m))
		$query = $m[1];
	elseif (preg_match('#^\?(.*)$#', $u, $m) && preg_match('/(?:^|&)p=/i', $m[1]))
		$query = $m[1];
	else
		return $url;
	parse_str($query, $q);
	if (empty($q['p']))
		return $url;
	$p = $q['p'];
	unset($q['p']);
	$pretty = sb_url($p, $q) . $frag;
	if ($hadAmp)
		$pretty = str_replace('&', '&amp;', $pretty);
	return $pretty;
}

/** Smarty outputfilter: href/js с index.php?p=… → ЧПУ. */
function sb_smarty_pretty_urls($tpl_output, &$smarty)
{
	if (!is_string($tpl_output) || $tpl_output === '' || strpos($tpl_output, 'index.php?') === false)
		return $tpl_output;
	// Query: обычные символы + «&» + HTML-entity «&amp;».
	// Нельзя брать [^"']+ — после htmlspecialchars JS-кавытка становится &#039;
	// и жадный матч съедает хвост sbGo('…&#039;).
	// Нельзя и просто […&…]+ без &amp; — матч обрывается на «;» внутри &amp;,
	// и href «index.php?p=admin&amp;c=admins…» превращается в «admin?amp=;c=…».
	return preg_replace_callback(
		// &amp; — HTML; голый & только перед ключом query (не &#039; из htmlspecialchars).
		'#(?:\.\./)*(?:\./)?index\.php\?(?:[a-zA-Z0-9_.=+%.-]+|&amp;|&(?=[a-zA-Z0-9_]))+#i',
		function ($m) {
			return sb_legacy_to_pretty_url($m[0]);
		},
		$tpl_output
	);
}

/** Отдать статическую errors/404.html с HTTP 404 и завершить скрипт. */
function sb_send_static_404()
{
	while (ob_get_level() > 0)
		@ob_end_clean();
	http_response_code(404);
	header('Content-Type: text/html; charset=utf-8');
	header('X-Robots-Tag: noindex, nofollow');
	$path = (defined('ROOT') ? ROOT : dirname(__DIR__) . '/') . 'errors/404.html';
	if (is_readable($path)) {
		readfile($path);
		exit;
	}
	echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>404</title></head>'
		. '<body><h1>404</h1><p>Страница не найдена. <a href="index.php">На главную</a></p></body></html>';
	exit;
}

/**
 * PRG: действие списка (unmute/ungag/delete) → один редирект + flash.
 * Убирает двойную перезагрузку (страница с ?a=… и потом ещё ShowBox-редирект).
 */
function sb_list_action_done($title, $msg, $color, $redirect)
{
	if (session_status() === PHP_SESSION_NONE && function_exists('session_start'))
		@session_start();
	$_SESSION['sb_list_flash'] = array(
		'title' => (string)$title,
		'msg' => (string)$msg,
		'color' => (string)$color,
	);
	$redirect = (string)$redirect;
	if ($redirect === '')
		$redirect = 'index.php';
	$redirect = function_exists('sb_abs_url') ? sb_abs_url($redirect) : $redirect;
	if (!headers_sent())
	{
		header('Location: ' . $redirect);
		exit;
	}
	$js = json_encode($redirect, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	echo '<script>if(typeof sbGo==="function")sbGo(' . $js . ');else window.location.href=' . $js . ';</script>';
	exit;
}

/** ShowBox из flash после PRG (без повторного редиректа). */
function sb_list_action_flash_script()
{
	return sb_ui_flash_script('sb_list_flash');
}

/** Универсальный UI-flash (Steam-вход, tripwire и т.п.). */
function sb_ui_flash_set($title, $msg, $color = 'red', $timer = 0)
{
	if (function_exists('sb_session_start'))
		sb_session_start();
	elseif (session_status() === PHP_SESSION_NONE)
		@session_start();
	$_SESSION['sb_ui_flash'] = array(
		'title' => (string)$title,
		'msg' => (string)$msg,
		'color' => (string)$color,
		'timer' => (int)$timer,
	);
}

/**
 * ShowBox из session-flash. $key — ключ сессии (по умолчанию sb_ui_flash).
 * Зелёные/успешные — автозакрытие (timer); ошибки ждут OK.
 * Опционально: $f['timer'] (мс), $f['noclose'] (bool).
 */
function sb_ui_flash_script($key = 'sb_ui_flash')
{
	if (session_status() === PHP_SESSION_NONE && function_exists('session_start'))
		@session_start();
	if (empty($_SESSION[$key]) || !is_array($_SESSION[$key]))
		return '';
	$f = $_SESSION[$key];
	unset($_SESSION[$key]);
	$title = isset($f['title']) ? $f['title'] : '';
	$msg = isset($f['msg']) ? $f['msg'] : '';
	$color = isset($f['color']) ? $f['color'] : 'green';
	$noclose = !empty($f['noclose']) ? 'true' : 'false';
	$timer = isset($f['timer']) ? (int)$f['timer'] : 0;
	if ($timer <= 0 && $color === 'green')
		$timer = 1800;
	$timerJs = $timer > 0 ? (string)$timer : 'undefined';
	return '<script>setTimeout(function(){ if(typeof ShowBox==="function") ShowBox('
		. json_encode($title) . ',' . json_encode($msg) . ',' . json_encode($color)
		. ',"",' . $noclose . ',' . $timerJs . '); }, 300);</script>';
}

/** Список SteamID из SB_PROTECTED_STEAMIDS. */
function sb_protected_steamids()
{
	if (!defined('SB_PROTECTED_STEAMIDS') || SB_PROTECTED_STEAMIDS === '')
		return array();
	return array_values(array_filter(array_map('trim', explode(',', SB_PROTECTED_STEAMIDS))));
}

/**
 * Есть ли у админа доступ к игровому серверу $sid (прямая привязка или группа серверов).
 * OWNER всегда true.
 *
 * @param int $sid
 * @param int|null $aid null = текущий админ
 * @return bool
 */
function sb_admin_has_server_access($sid, $aid = null)
{
	global $userbank;
	$sid = (int)$sid;
	if ($sid <= 0 || !isset($userbank) || !is_object($userbank))
		return false;
	if ($aid === null)
		$aid = (int)$userbank->GetAid();
	else
		$aid = (int)$aid;
	if ($aid <= 0)
		return false;
	if ($userbank->HasAccess(ADMIN_OWNER, $aid))
		return true;

	$admin_servers = $GLOBALS['db']->GetAll(
		"SELECT `server_id`, `srv_group_id` FROM `".DB_PREFIX."_admins_servers_groups` WHERE admin_id = ?",
		array($aid)
	);
	if (!is_array($admin_servers))
		return false;

	foreach ($admin_servers as $srv) {
		if ((int)$srv['server_id'] === $sid)
			return true;
		if ((int)$srv['srv_group_id'] > 0) {
			$servers_in_group = $GLOBALS['db']->GetAll(
				"SELECT `server_id` FROM `".DB_PREFIX."_servers_groups` WHERE group_id = ?",
				array((int)$srv['srv_group_id'])
			);
			if (!is_array($servers_in_group))
				continue;
			foreach ($servers_in_group as $servig) {
				if ((int)$servig['server_id'] === $sid)
					return true;
			}
		}
	}
	return false;
}

/**
 * Валидация идентификатора игрока перед подстановкой в RCON-команду.
 * Пропускает только SteamID2 (STEAM_0:1:2), SteamID3 ([U:1:5]) и Steam64 (7656…).
 * Любые разделители команд (`;`, кавычки, пробелы, переводы строк) отсекаются формой.
 *
 * @param string $id
 * @return string|false нормализованный ID либо false
 */
function sb_sanitize_steamid_for_rcon($id)
{
	$id = trim((string)$id);
	if ($id === '' || strlen($id) > 32)
		return false;
	if (preg_match('/^STEAM_[0-9]:[0-1]:[0-9]+$/', $id))
		return $id;
	if (preg_match('/^\[U:[0-9]:[0-9]+\]$/', $id))
		return $id;
	if (preg_match('/^[0-9]{15,20}$/', $id))
		return $id;
	return false;
}

/**
 * Подготовка произвольной строки (ник, текст сообщения) для консоли Source.
 * Кавычки, `;`, переводы строк и непечатаемые символы делают команду небезопасной,
 * поэтому такая строка отклоняется целиком, а не «чистится».
 *
 * @param string $s
 * @param int $maxLen 0 = без ограничения
 * @return string '' если строка непригодна
 */
function sb_sanitize_rcon_string($s, $maxLen = 0)
{
	$s = trim((string)$s);
	if ($s === '')
		return '';
	$maxLen = (int)$maxLen;
	if ($maxLen > 0 && strlen($s) > $maxLen)
		return '';
	// " — конец аргумента, ; — разделитель команд, \r\n\t — новая команда в консоли
	if (strpbrk($s, "\";\r\n\t") !== false)
		return '';
	// управляющие символы (в т.ч. \0) и байты, которые консоль трактует по-своему
	if (preg_match('/[\x00-\x1F\x7F]/', $s))
		return '';
	return $s;
}

/**
 * Можно ли текущему админу менять другого (права/группы/серверы).
 * Не-OWNER не трогает OWNER и SteamID из SB_PROTECTED_STEAMIDS.
 *
 * @param int $targetAid
 * @return bool
 */
function sb_can_manage_admin($targetAid)
{
	global $userbank;
	$targetAid = (int)$targetAid;
	if ($targetAid <= 0 || !isset($userbank) || !is_object($userbank))
		return false;
	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
		return false;

	$auth = $userbank->GetProperty('authid', $targetAid);
	if (!empty($auth) && in_array($auth, sb_protected_steamids(), true))
		return false;

	if (!$userbank->HasAccess(ADMIN_OWNER) && $userbank->HasAccess(ADMIN_OWNER, $targetAid))
		return false;

	return true;
}

/** Сброс auth-кук без session_destroy (безопасно при bootstrap). */
function sb_clear_auth_cookies()
{
	if (function_exists('sb_set_auth_cookie')) {
		sb_set_auth_cookie('aid', '', time() - 86400);
		sb_set_auth_cookie('password', '', time() - 86400);
	} else {
		@setcookie('aid', '', time() - 86400);
		@setcookie('password', '', time() - 86400);
	}
	unset($_COOKIE['aid'], $_COOKIE['password']);
}

/**
 * Не-OWNER не может выдавать ADMIN_OWNER через bitmask (AddAdmin/AddGroup).
 */
function sb_strip_nonowner_web_flags($flags)
{
	global $userbank;
	$flags = (int)$flags;
	if (!isset($userbank) || !is_object($userbank) || !$userbank->HasAccess(ADMIN_OWNER))
		$flags = $flags & ~ADMIN_OWNER;
	return $flags;
}

/** Веб-группа содержит бит ADMIN_OWNER? */
function sb_web_group_has_owner($gid)
{
	$gid = (int)$gid;
	if ($gid <= 0 || empty($GLOBALS['db']))
		return false;
	$flags = $GLOBALS['db']->GetOne("SELECT flags FROM `" . DB_PREFIX . "_groups` WHERE gid = ?", array($gid));
	return (((int)$flags) & ADMIN_OWNER) !== 0;
}

/**
 * Tripwire: отозвать права (expired=1), мгновенно выкинуть из сессии.
 * Сообщение: «Превышение полномочий».
 *
 * @param object|null $objResponse xajaxResponse или null (обычный HTTP)
 * @param string $log_detail причина для системного лога
 * @return bool true если наказание применено
 */
function sb_tripwire_punish_actor($objResponse = null, $log_detail = '')
{
	global $userbank;
	if (!isset($userbank) || !is_object($userbank))
		return false;

	$actor_aid = (int)$userbank->GetAid();
	if ($actor_aid <= 0)
		return false;

	$actor_authid = (string)$userbank->GetProperty('authid');
	$username = (string)$userbank->GetProperty('user');
	$protected = sb_protected_steamids();
	if ($actor_authid !== '' && in_array($actor_authid, $protected, true))
		return false;

	$GLOBALS['db']->Execute(
		"UPDATE `".DB_PREFIX."_admins` SET `expired` = 1 WHERE `aid` = ?",
		array($actor_aid)
	);

	$detail = $log_detail !== '' ? $log_detail : 'превышение полномочий';
	new CSystemLog(
		"w",
		"Превышение полномочий",
		"Админ " . $username . " (aid " . $actor_aid . ") — " . $detail
			. " — срок админки сброшен (expired=1), сессия принудительно сброшена."
	);

	if (function_exists('logout'))
		@logout();
	else
		sb_clear_auth_cookies();

	if (isset($userbank->aid))
		$userbank->aid = -1;

	$login = 'index.php?p=login&m=overreach';
	if ($objResponse !== null && is_object($objResponse)) {
		$objResponse->addScript(
			"ShowBox('Превышение полномочий',"
			. " 'Ваши права администратора отозваны за превышение полномочий. Обратитесь к вышестоящему.',"
			. " 'red', " . json_encode($login) . ", true);"
		);
		if (method_exists($objResponse, 'addRedirect'))
			$objResponse->addRedirect($login, 0);
		elseif (method_exists($objResponse, 'redirect'))
			$objResponse->redirect($login, 0);
	} else {
		if (!headers_sent()) {
			header('Location: ' . $login);
			exit;
		}
		echo '<script>window.location=' . json_encode($login) . ';</script>';
		exit;
	}
	return true;
}

/**
 * Маппинг старых PNG/GIF иконок модов → SVG в images/icons/games/.
 * Ключ — basename файла из mods.icon (как в БД), значение — путь от корня сайта.
 */
function sb_game_icon_svg_map()
{
	static $map = null;
	if ($map !== null)
		return $map;

	$base = 'images/icons/games/';
	$map = array(
		// Web / generic
		'web.png' => $base . 'web.svg',
		'web.svg' => $base . 'web.svg',
		// Counter-Strike
		'csgo.png' => $base . 'csgo.svg',
		'csgo.svg' => $base . 'csgo.svg',
		'cs2.png' => $base . 'cs2.svg',
		'cs2.svg' => $base . 'cs2.svg',
		'csource.png' => $base . 'csource.svg',
		'csource.svg' => $base . 'csource.svg',
		'cspromod.png' => $base . 'cspromod.svg',
		'cspromod.svg' => $base . 'cspromod.svg',
		// TF / HL2 family
		'tf2.png' => $base . 'tf2.svg',
		'tf2.gif' => $base . 'tf2.svg',
		'tf2.svg' => $base . 'tf2.svg',
		'hl2dm.png' => $base . 'hl2dm.svg',
		'hl2dm.svg' => $base . 'hl2dm.svg',
		'hl2ctf.png' => $base . 'hl2ctf.svg',
		'hl2ctf.svg' => $base . 'hl2ctf.svg',
		'hl2-fortressforever.png' => $base . 'hl2-fortressforever.svg',
		'hl2-fortressforever.gif' => $base . 'hl2-fortressforever.svg',
		'hl2-fortressforever.svg' => $base . 'hl2-fortressforever.svg',
		// Other Source
		'dods.png' => $base . 'dods.svg',
		'dods.svg' => $base . 'dods.svg',
		'ins.png' => $base . 'ins.svg',
		'ins.gif' => $base . 'ins.svg',
		'ins.svg' => $base . 'ins.svg',
		'gmod.png' => $base . 'gmod.svg',
		'gmod.svg' => $base . 'gmod.svg',
		'l4d.png' => $base . 'l4d.svg',
		'l4d.svg' => $base . 'l4d.svg',
		'l4d2.png' => $base . 'l4d2.svg',
		'l4d2.svg' => $base . 'l4d2.svg',
		'nmrih.png' => $base . 'nmrih.svg',
		'nmrih.svg' => $base . 'nmrih.svg',
		'alienswarm.png' => $base . 'alienswarm.svg',
		'alienswarm.svg' => $base . 'alienswarm.svg',
		'cure.png' => $base . 'cure.svg',
		'cure.svg' => $base . 'cure.svg',
		'nucleardawn.png' => $base . 'nucleardawn.svg',
		'nucleardawn.svg' => $base . 'nucleardawn.svg',
		'synergy.png' => $base . 'synergy.svg',
		'synergy.svg' => $base . 'synergy.svg',
		'zps.png' => $base . 'zps.svg',
		'zps.gif' => $base . 'zps.svg',
		'zps.svg' => $base . 'zps.svg',
		'dys.png' => $base . 'dys.svg',
		'dys.gif' => $base . 'dys.svg',
		'dys.svg' => $base . 'dys.svg',
		'hidden.png' => $base . 'hidden.svg',
		'hidden.svg' => $base . 'hidden.svg',
		'pvkii.png' => $base . 'pvkii.svg',
		'pvkii.gif' => $base . 'pvkii.svg',
		'pvkii.svg' => $base . 'pvkii.svg',
		'pdark.png' => $base . 'pdark.svg',
		'pdark.gif' => $base . 'pdark.svg',
		'pdark.svg' => $base . 'pdark.svg',
		'ship.png' => $base . 'ship.svg',
		'ship.gif' => $base . 'ship.svg',
		'ship.svg' => $base . 'ship.svg',
		'eye.png' => $base . 'eye.svg',
		'eye.svg' => $base . 'eye.svg',
		'SourceForts.png' => $base . 'SourceForts.svg',
		'SourceForts.svg' => $base . 'SourceForts.svg',
	);
	return $map;
}

/**
 * HTML иконки игры: SVG если есть маппинг, иначе старый PNG из images/games/.
 */
function sb_game_icon_html($iconFile, $alt = 'Игра', $size = 18)
{
	$iconFile = basename((string)$iconFile);
	if ($iconFile === '' || $iconFile === '.' || $iconFile === '..')
		$iconFile = 'web.png';
	$map = sb_game_icon_svg_map();
	if (isset($map[$iconFile]))
		$src = $map[$iconFile];
	else
	{
		$root = defined('ROOT') ? rtrim(str_replace('\\', '/', ROOT), '/') . '/' : '';
		$legacy = 'images/games/' . $iconFile;
		$src = ($root !== '' && is_file($root . $legacy)) ? $legacy : 'images/icons/unknown.svg';
	}
	$size = (int)$size;
	if ($size < 12) $size = 12;
	if ($size > 64) $size = 64;
	$alt = htmlspecialchars((string)$alt, ENT_QUOTES, 'UTF-8');
	$src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
	return '<img class="sb-ico" src="' . $src . '" width="' . $size . '" height="' . $size . '" alt="' . $alt . '" loading="lazy">';
}

/**
 * HTML иконки ОС сервера (A2S: l/w/…).
 */
function sb_os_icon_html($osKey, $size = 16)
{
	$key = strtolower(trim((string)$osKey));
	$size = (int)$size;
	if ($size < 12) $size = 12;
	if ($size > 64) $size = 64;

	if ($key === 'l' || $key === 'linux') {
		$src = 'images/icons/os-linux.svg';
		$alt = 'Linux';
	} elseif ($key === 'w' || $key === 'win' || $key === 'windows') {
		$src = 'images/icons/os-windows.svg';
		$alt = 'Windows';
	} else {
		// неизвестная ОС — старый PNG fallback (server_small / m / …)
		$file = basename($key !== '' ? $key : 'server_small');
		$src = 'images/' . $file . '.png';
		$alt = 'ОС сервера';
	}
	return '<img class="sb-ico" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" width="' . $size . '" height="' . $size . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * HTML иконки VAC.
 */
function sb_vac_icon_html($secure, $size = 16)
{
	$size = (int)$size;
	if ($size < 12) $size = 12;
	if ($size > 64) $size = 64;
	if ($secure) {
		$src = 'images/icons/vac-on.svg';
		$alt = 'VAC on';
	} else {
		$src = 'images/icons/vac-off.svg';
		$alt = 'VAC off';
	}
	return '<img class="sb-ico" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" width="' . $size . '" height="' . $size . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * HTML иконки типа блокировки связи: mute / gag / silence.
 * $type: 1|mute|v — мут; 2|gag|c — гаг; 3|silence|s — оба.
 */
function sb_comms_type_icon_html($type, $size = 16)
{
	$size = (int)$size;
	if ($size < 12) $size = 12;
	if ($size > 64) $size = 64;

	$key = is_string($type) ? strtolower(trim($type)) : (string)(int)$type;
	if ($key === '1' || $key === 'mute' || $key === 'v' || $key === 'type_v' || $key === 'images/type_v.png') {
		$src = 'images/icons/comms-mute.svg';
		$alt = 'Микрофон';
	} elseif ($key === '2' || $key === 'gag' || $key === 'c' || $key === 'type_c' || $key === 'images/type_c.png') {
		$src = 'images/icons/comms-gag.svg';
		$alt = 'Чат';
	} elseif ($key === '3' || $key === 'silence' || $key === 's' || $key === 'type_silence' || $key === 'images/type_silence.png') {
		$src = 'images/icons/comms-silence.svg';
		$alt = 'Микрофон и чат';
	} else {
		$src = 'images/icons/comms-mute.svg';
		$alt = 'Блокировка';
	}
	return '<img class="sb-ico sb-ico-comms" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" width="' . $size . '" height="' . $size . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
}

/**
 * URL превью карты для <img src>.
 * Приоритет: локальный файл (ручная загрузка) → внешний GameTracker (без скачивания на диск) → nomap.jpg.
 * Внешний URL грузит браузер сам; при 404 срабатывает onerror → nomap.
 */
function GetMapImage($map, $game=false)
{
	$map = basename(str_replace('\\', '/', (string)$map));
	$map = preg_replace('/\.(bsp|jpg|jpeg|png|webp)$/i', '', $map);

	if ($map === '' || $map === '.' || $map === '..')
		return 'images/maps/nomap.jpg';

	$exts = defined('ALLOW_GAMEMAPS_EXT') ? ALLOW_GAMEMAPS_EXT : array('jpg', 'jpeg', 'png', 'webp');

	if ($game) {
		$game = strtolower((string)$game);
		foreach ($exts as $ext) {
			$localGame = SB_MAP_LOCATION . '/' . $game . '/' . $map . '.' . $ext;
			if (@is_file($localGame))
				return 'images/maps/' . $game . '/' . $map . '.' . $ext;
		}
	}

	foreach ($exts as $ext) {
		$local = SB_MAP_LOCATION . '/' . $map . '.' . $ext;
		if (@is_file($local))
			return 'images/maps/' . $map . '.' . $ext;
	}

	// Без сетевого кэша на диск: отдаём прямую ссылку, браузер сам подтянет (или упрётся в onerror → nomap).
	$remote = GetRemoteMapImageUrl($map, $game);
	if ($remote !== '')
		return $remote;

	return 'images/maps/nomap.jpg';
}

/**
 * Перекодирует изображение через GD, срезая полиглоты/метаданные.
 * Без GD возвращает false — вызывающий решает, принимать ли файл только по getimagesize.
 *
 * @param string $filePath
 * @param int $imageType IMAGETYPE_*
 * @return bool
 */
function reencodeImage($filePath, $imageType)
{
	if (!extension_loaded('gd') || !is_string($filePath) || $filePath === '' || !is_file($filePath))
		return false;

	$img = null;
	$imageType = (int)$imageType;

	if ($imageType === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
		$img = @imagecreatefromjpeg($filePath);
		if ($img)
			@imagejpeg($img, $filePath, 90);
	} elseif ($imageType === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
		$img = @imagecreatefrompng($filePath);
		if ($img) {
			imagealphablending($img, false);
			imagesavealpha($img, true);
			@imagepng($img, $filePath, 9);
		}
	} elseif (defined('IMAGETYPE_WEBP') && $imageType === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
		$img = @imagecreatefromwebp($filePath);
		if ($img)
			@imagewebp($img, $filePath, 80);
	}

	if (!$img)
		return false;

	imagedestroy($img);
	return true;
}

/**
 * CSRF для popup-загрузчиков (map/icon/demo). При провале — HTML-отказ и exit.
 */
function sb_upload_require_csrf()
{
	$token = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
	if (!function_exists('sb_csrf_validate') || !sb_csrf_validate($token)) {
		$log = new CSystemLog("w", "CSRF", "Отклонена загрузка файла: неверный CSRF-токен (" . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?') . ").");
		sb_upload_access_denied('Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
	}
}

/**
 * Прямой URL превью карты на GameTracker (без file_get_contents / записи на диск).
 * Пустая строка — мод неизвестен или автозагрузка выключена.
 */
function GetRemoteMapImageUrl($map, $game)
{
	if (isset($GLOBALS['config']['feature.map_autofetch']) && $GLOBALS['config']['feature.map_autofetch'] != '1')
		return '';

	if (!preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/', $map))
		return '';

	static $gtSlugs = array(
		'tf'         => 'tf2',
		'csgo'       => 'csgo',
		'cstrike'    => 'cs',
		'cstrike15'  => 'csgo',
		'css'        => 'css',
		'garrysmod'  => 'garrysmod',
		'left4dead'  => 'l4d',
		'left4dead2' => 'l4d2',
		'dod'        => 'dod',
		'insurgency' => 'insurgency',
		'hl2mp'      => 'hl2dm',
	);
	$game = strtolower((string)$game);
	if ($game === '' || !isset($gtSlugs[$game]))
		return '';

	return 'https://image.gametracker.com/images/maps/160x120/'
		. $gtSlugs[$game] . '/' . rawurlencode($map) . '.jpg';
}

/**
 * @deprecated Раньше качал картинку на диск во время AJAX списка серверов — тормозило страницу.
 * Оставлено как no-op / тонкая обёртка на случай старых вызовов.
 */
function FetchRemoteMapImage($map, $game)
{
	return false;
}

/*
function GetMapImage($map)
{
	if(@file_exists(SB_MAP_LOCATION . "/" . $map . ".jpg"))
		return "images/maps/" . $map . ".jpg";
	else
		return "images/maps/nomap.jpg";
}
*/
/**
 * HTML-страница отказа в доступе для popup-загрузчиков (demo/icon/map).
 */
function sb_upload_access_denied($title = 'Нет доступа')
{
	$title = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
	header('Content-Type: text/html; charset=UTF-8');
	$css = '';
	$css_file = defined('SB_THEMES') ? (SB_THEMES . 'new_box/css/uploadfile.css') : '';
	if ($css_file !== '' && is_readable($css_file))
		$css = (string)@file_get_contents($css_file);
	echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>' . $title . ' · Material Admin</title>'
		. '<link rel="stylesheet" href="/themes/new_box/css/uploadfile.css?v=20260727b">'
		. ($css !== '' ? '<style>' . $css . '</style>' : '')
		. '</head><body class="upload-page">'
		. '<main class="upload-card upload-denied" role="main">'
		. '<header class="upload-card__head">'
		. '<span class="upload-card__mark" aria-hidden="true">M</span>'
		. '<div class="upload-card__titles">'
		. '<h1 class="upload-card__title">' . $title . '</h1>'
		. '<p>Войдите как администратор с нужными правами и откройте загрузку из панели.</p>'
		. '</div></header>'
		. '</main></body></html>';
	exit;
}

function CheckExt($filename, $ext)
{
	if (is_array($ext)) {
		foreach ($ext as &$Ext)
			if (CheckExt($filename, $Ext)) return true;
		return false;
	}
	$filename = str_replace(chr(0), '', $filename);
	$path_info = pathinfo($filename);
	if(strtolower($path_info['extension']) == strtolower($ext))
		return true;
	else
		return false;
}

function ShowBox($title, $msg, $color, $redir="", $noclose=false)
{
	echo sprintf("<script>ShowBox('%s', '%s', '%s', '%s', %s);</script>", addslashes($title), addslashes($msg), addslashes($color), addslashes($redir), $noclose ? "true" : "false");
}
function ShowBox_ajx($title, $msg, $color, &$response, $redir="", $noclose=false)
{
	$response->AddScript(sprintf("ShowBox('%s', '%s', '%s', '%s', %s);", addslashes($title), addslashes($msg), addslashes($color), addslashes($redir), $noclose ? "true" : "false"));
}

function PruneBans()
{
	global $userbank;

	$res = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_bans` SET `RemovedBy` = 0, `RemoveType` = \'E\', `RemovedOn` = UNIX_TIMESTAMP() WHERE `length` != 0 and `ends` < UNIX_TIMESTAMP() and `RemoveType` IS NULL');
	$prot = $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_protests` SET archiv = '3', archivedby = ".($userbank->GetAid()<0?0:$userbank->GetAid())." WHERE archiv = '0' AND bid IN((SELECT bid FROM `".DB_PREFIX."_bans` WHERE `RemoveType` = 'E'))");
	$submission = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_submissions` SET archiv = \'3\', archivedby = '.($userbank->GetAid()<0?0:$userbank->GetAid()).' WHERE archiv = \'0\' AND (SteamId IN((SELECT authid FROM `'.DB_PREFIX.'_bans` WHERE `type` = 0 AND `RemoveType` IS NULL)) OR sip IN((SELECT ip FROM `'.DB_PREFIX.'_bans` WHERE `type` = 1 AND `RemoveType` IS NULL)))');
    return $res?true:false;
}

function PruneComms()
{
	global $userbank;

	$res = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_comms` SET `RemovedBy` = 0, `RemoveType` = \'E\', `RemovedOn` = UNIX_TIMESTAMP() WHERE `length` != 0 and `ends` < UNIX_TIMESTAMP() and `RemoveType` IS NULL');
    return $res?true:false;
}

/*
function GetSVNRev()
{
	preg_match('/\\$Rev:[\\s]+([\\d]+)/', SB_REV, $rev, PREG_OFFSET_CAPTURE);
	return (int)$rev[1][0];
}*/

function GetGITRev()
{
	preg_match('/\\$Git:[\\s]+([\\d]+)/', SB_GITRev, $gitrev, PREG_OFFSET_CAPTURE);
	return (int)$gitrev[1][0];
}



// Function by Luman (http://snipplr.com/users/luman)
function array_qsort(&$array, $column=0, $order=SORT_ASC, $first=0, $last= -2)
{
  // $array  - the array to be sorted
  // $column - index (column) on which to sort
  //          can be a string if using an associative array
  // $order  - SORT_ASC (default) for ascending or SORT_DESC for descending
  // $first  - start index (row) for partial array sort
  // $last  - stop  index (row) for partial array sort
  // $keys  - array of key values for hash array sort

  $keys = array_keys($array);
  if($last == -2) $last = count($array) - 1;
  if($last > $first) {
   $alpha = $first;
   $omega = $last;
   $key_alpha = $keys[$alpha];
   $key_omega = $keys[$omega];
   $guess = $array[$key_alpha][$column];
   while($omega >= $alpha) {
     if($order == SORT_ASC) {
       while($array[$key_alpha][$column] < $guess) {$alpha++; $key_alpha = $keys[$alpha]; }
       while($array[$key_omega][$column] > $guess) {$omega--; $key_omega = $keys[$omega]; }
     } else {
       while($array[$key_alpha][$column] > $guess) {$alpha++; $key_alpha = $keys[$alpha]; }
       while($array[$key_omega][$column] < $guess) {$omega--; $key_omega = $keys[$omega]; }
     }
     if($alpha > $omega) break;
     $temporary = $array[$key_alpha];
     $array[$key_alpha] = $array[$key_omega]; $alpha++;
     $key_alpha = $keys[$alpha];
     $array[$key_omega] = $temporary; $omega--;
     if ($omega > 0)
     	$key_omega = $keys[$omega];
   }
   array_qsort ($array, $column, $order, $first, $omega);
   array_qsort ($array, $column, $order, $alpha, $last);
  }
}


function getDirectorySize($path)
{
	$totalsize = 0;
	$totalcount = 0;
	$dircount = 0;
	if ($handle = opendir ($path))
	{
		while (false !== ($file = readdir($handle)))
		{
			$nextpath = $path . '/' . $file;
			if ($file != '.' && $file != '..' && !is_link ($nextpath))
			{
				if (is_dir ($nextpath))
				{
					$dircount++;
					$result = getDirectorySize($nextpath);
					$totalsize += $result['size'];
					$totalcount += $result['count'];
					$dircount += $result['dircount'];
				}
				elseif (is_file ($nextpath))
				{
					$totalsize += filesize ($nextpath);
					$totalcount++;
				}
			}
		}
	}
	closedir ($handle);
	$total['size'] = $totalsize;
	$total['count'] = $totalcount;
	$total['dircount'] = $dircount;
	return $total;
}


function sizeFormat($size)
{
	if($size<1024)
	{
		return $size." bytes";
	}
	else if($size<(1024*1024))
	{
		$size=round($size/1024,1);
		return $size." KB";
	}
	else if($size<(1024*1024*1024))
	{
		$size=round($size/(1024*1024),2);
		return $size." MB";
	}
	else
	{
		$size=round($size/(1024*1024*1024),2);
		return $size." GB";
	}
}

function check_email($email) {
  $nonascii      = "\x80-\xff"; # Non-ASCII-Chars are not allowed

  $nqtext        = "[^\\\\$nonascii\015\012\"]";
  $qchar         = "\\\\[^$nonascii]";

  $protocol      = '(?:mailto:)';

  $normuser      = '[a-zA-Z0-9][a-zA-Z0-9_.-]*';
  $quotedstring  = "\"(?:$nqtext|$qchar)+\"";
  $user_part     = "(?:$normuser|$quotedstring)";

  $dom_mainpart  = '[a-zA-Z0-9][a-zA-Z0-9._-]*\\.';
  $dom_subpart   = '(?:[a-zA-Z0-9][a-zA-Z0-9._-]*\\.)*';
  $dom_tldpart   = '[a-zA-Z]{2,5}';
  $domain_part   = "$dom_subpart$dom_mainpart$dom_tldpart";

  $regex         = "$protocol?$user_part\@$domain_part";

  return preg_match("/^$regex$/",$email);
}

// check, if one steamid is online on one specific server
function checkSinglePlayer($sid, $steamid)
{
	require_once(INCLUDES_PATH.'/CServerControl.php');
	$sid = (int)$sid;
	$serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".$sid."';");
	if(empty($serv['rcon'])) {
		return false;
	}
	$test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
	if(!$test) {
		return false;
	}
	
	$r = new CServerControl();
	$r->Connect($serv['ip'], $serv['port']);
	
	if(!$r->AuthRcon($serv['rcon'])) {
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".(int)$sid."';");
		return false;
	}

	$ret = $r->rconCommand("status");
	$search = preg_match_all(STATUS_PARSE,$ret,$matches,PREG_PATTERN_ORDER);
	$i = 0;
	foreach($matches[3] AS $match) {
		if(getAccountId($match) == getAccountId($steamid)) {
			$steam = $matches[3][$i];
			$name = $matches[2][$i];
			$time = $matches[4][$i];
			$ip = explode(":", $matches[8][$i]);
			$ip = $ip[0];
			$ping = $matches[5][$i];
			return array('name' => $name, 'steam' => $steamid, 'ip' => $ip, 'time' => $time, 'ping' => $ping);
		}
		$i++;
	}
	return false;
}

//function to check for multiple steamids on one server.
// param $steamids needs to be an array of steamids.
//returns array('STEAM_ID_1' => array('name' => $name, 'steam' => $steam, 'ip' => $ip, 'time' => $time, 'ping' => $ping), 'STEAM_ID_2' => array()....)
function checkMultiplePlayers($sid, $steamids)
{
	require_once(INCLUDES_PATH.'/CServerControl.php');
	$sid = (int)$sid;
	$serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".$sid."';");
	if(empty($serv['rcon'])) {
		return false;
	}
	$test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
	if(!$test) {
		return false;
	}
	
	$r = new CServerControl();
	$r->Connect($serv['ip'], $serv['port']);
	
	if(!$r->AuthRcon($serv['rcon'])) {
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".(int)$sid."';");
		return false;
	}

	$ret = $r->rconCommand("status");
	$search = preg_match_all(STATUS_PARSE,$ret,$matches,PREG_PATTERN_ORDER);
	$i = 0;
	$found = array();
	foreach($matches[3] AS $match) {
		foreach($steamids AS $steam) {
			if(getAccountId($match) == getAccountId($steam)) {
				$steam = $matches[3][$i];
				$name = $matches[2][$i];
				$time = $matches[4][$i];
				$ping = $matches[5][$i];
				$ip = explode(":", $matches[8][$i]);
				$ip = $ip[0];
				$found[$steam] = array('name' => $name, 'steam' => $steam, 'ip' => $ip, 'time' => $time, 'ping' => $ping);
				break;
			}
		}
		$i++;
	}
	return $found;
}

function getAccountId($steamid)
{
	if(strpos($steamid, "STEAM_") === 0) {
		$parts = explode(":", $steamid);
		if(count($parts) != 3)
			return -1;
		return (int)$parts[2]*2 + (int)$parts[1];
	}
	elseif(strpos($steamid, "[U:") === 0) {
		$parts = explode(":", $steamid);
		if(count($parts) != 3)
			return -1;
		return (int)substr($parts[2], 0, -1);
	}
	return -1;
}

function renderSteam2($accountId, $universe)
{
	return "STEAM_" . $universe . ":" . ($accountId & 1) . ":" . ($accountId >> 1);
}

function SBDate($format, $timestamp="")
{
    if(version_compare(PHP_VERSION, "5") != -1)
    {
        if($GLOBALS['config']['config.summertime'] == "1")
        {
            $str = date("r", $timestamp);
            $date = new DateTime($str);
            $date->modify("+1 hour");
            return $date->format($format);
        }
        else if(empty($timestamp))
            return date($format);
    }
    else
    {
        if($GLOBALS['config']['config.summertime'] == "1") {
            $summertime = 3600;
        } else {
            $summertime = 0;
        }
        if(empty($timestamp)) {
            $timestamp = time() + SB_TIMEZONE*3600 + $summertime;
        } else {
            $timestamp = $timestamp + SB_TIMEZONE*3600 + $summertime;
        }
    }
	return date($format, $timestamp);
}

/**
* Converts a SteamID to a FriendID
*
* @param string $authid the steamid to convert
* @return string
*/
function SteamIDToFriendID($authid)
{
	$authid = $GLOBALS['db']->qstr($authid);
	$friendid = $GLOBALS['db']->GetRow("SELECT CAST(MID(".$authid.", 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(".$authid.", 11, 10) * 2 AS UNSIGNED) AS friend_id");
	return $friendid["friend_id"];
}

/**
* Converts a FriendID to a SteamID
*
* @param string $friendid the friendid to convert
* @return string
*/
function FriendIDToSteamID($friendid)
{
	$friendid = $GLOBALS['db']->qstr($friendid);
	$steamid = $GLOBALS['db']->GetRow("SELECT CONCAT(\"STEAM_0:\", (CAST(".$friendid." AS UNSIGNED) - CAST('76561197960265728' AS UNSIGNED)) % 2, \":\", CAST(((CAST(".$friendid." AS UNSIGNED) - CAST('76561197960265728' AS UNSIGNED)) - ((CAST(".$friendid." AS UNSIGNED) - CAST('76561197960265728' AS UNSIGNED)) % 2)) / 2 AS UNSIGNED)) AS steam_id;");
	return $steamid['steam_id'];
}

/**
* Gets the friendid from a custom user id
*
* @param string $comid the customid to get the friendid for
* @return string
*/
function GetFriendIDFromCommunityID($comid)
{
	$raw = @file_get_contents("http://steamcommunity.com/id/".$comid."/?xml=1");
	preg_match("/<privacyState>([^\]]*)<\/privacyState>/", $raw, $status);
	if(($status && $status[1] != "public") || strstr($raw, "</profile>")) {
		$raw = str_replace("&", "", $raw);
		$raw = strip_31_ascii($raw);
		$raw = utf8_encode($raw);
		$xml = simplexml_load_string($raw);
		$result = $xml->xpath('/profile/steamID64');
		$friendid = (string)$result[0];
		return $friendid;
	}
	return false;
}
function GetCommunityName($steamid)
{
	$friendid = SteamIDToFriendID($steamid);
	$result = get_headers("http://steamcommunity.com/profiles/".$friendid."/", 1);
	$raw = file_get_contents(($result["Location"]!=""?$result["Location"]:"http://steamcommunity.com/profiles/".$friendid."/")."?xml=1");
	if(strstr($raw, "</profile>")) {
		$raw = str_replace("&", "", $raw);
        $raw = strip_31_ascii($raw);
		$raw = utf8_encode($raw);
		$xml = simplexml_load_string($raw);
		$result = $xml->xpath('/profile/steamID');
		$friendid = (string)$result[0];
		return $friendid;
	}
	return "";
}

function SendRconSilent($rcon, $sid)
{
	require_once(INCLUDES_PATH.'/CServerControl.php');
	$sid = (int)$sid;
	$serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".$sid."';");
	if(empty($serv['rcon'])) {
		return false;
	}
	$test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
	if(!$test) {
		return false;
	}
	
	$r = new CServerControl($serv['ip'], $serv['port'], $serv['rcon']);
	$r->Connect($serv['ip'], $serv['port']);
	
	if(!$r->AuthRcon($serv['rcon'])) {
		$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".(int)$sid."';");
		return false;
	}

	$ret = $r->SendCommand($rcon);
	if($ret)
		return true;
	return false;
}

/* Function to check if a needle is inside a 2 layered recursive array
* like the one from ADODB->GetAll
* @param string $needle The string to search for
* @param array $array The array to search in
* @return boolean
*/
function in_array_dim($needle, $array)
{
	foreach($array as $secarray)
	{
		foreach($secarray as $part)
		{
			if($part == $needle)
				return true;
		}
	}
	return false;
}

// Strip all undisplayable chars from a string. e.g. or
function strip_31_ascii($string)
{
	for($i=0;$i<32;$i++)
		$string = str_replace(chr($i), "", $string);
	return $string;
}

function GetCommunityIDFromSteamID2($sid) {
    $parts = explode(':', str_replace('STEAM_', '' ,$sid)); 
    return bcadd(bcadd('76561197960265728', $parts[1]), bcmul($parts[2], '2'));
}

/** Нормализация Steam64 (community id). */
function sb_steam_normalize_community_id($id)
{
	$id = preg_replace('/\D+/', '', (string)$id);
	if ($id !== '' && preg_match('/^7656\d{13}$/', $id))
		return $id;
	return '';
}

/** Fallback-ссылка на профиль: всегда /profiles/STEAM64. */
function sb_steam_profile_url_fallback($communityid)
{
	$c = sb_steam_normalize_community_id($communityid);
	return ($c !== '') ? ('https://steamcommunity.com/profiles/' . $c) : '';
}

/**
 * Батч GetPlayerSummaries → реальные profileurl.
 * У кого есть custom URL — вернётся https://steamcommunity.com/id/name,
 * иначе https://steamcommunity.com/profiles/7656…
 *
 * @param string[] $communityIds
 * @return array<string,string> map communityid => url
 */
function sb_steam_fetch_profile_urls(array $communityIds)
{
	$out = array();
	$ids = array();
	foreach ($communityIds as $id) {
		$c = sb_steam_normalize_community_id($id);
		if ($c !== '')
			$ids[$c] = true;
	}
	$ids = array_keys($ids);
	foreach ($ids as $c)
		$out[$c] = 'https://steamcommunity.com/profiles/' . $c;

	if (!$ids || !defined('STEAMAPIKEY') || STEAMAPIKEY === '')
		return $out;

	foreach (array_chunk($ids, 100) as $chunk) {
		$url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='
			. rawurlencode(STEAMAPIKEY) . '&steamids=' . implode(',', $chunk);
		$raw = @file_get_contents($url);
		if ($raw === false || $raw === '')
			continue;
		$json = @json_decode($raw);
		if (empty($json->response->players) || !is_array($json->response->players))
			continue;
		foreach ($json->response->players as $player) {
			if (empty($player->steamid))
				continue;
			$sid = sb_steam_normalize_community_id($player->steamid);
			if ($sid === '')
				continue;
			if (!empty($player->profileurl) && is_string($player->profileurl)) {
				$purl = rtrim(preg_replace('#^http://#i', 'https://', $player->profileurl), '/');
				if (preg_match('#^https://steamcommunity\.com/(?:id|profiles)/#i', $purl))
					$out[$sid] = $purl;
			}
		}
	}
	return $out;
}

/**
 * Проставляет steam_profile / steam_vanity в элементах списка банов/мутов.
 * @param array $items
 * @return array
 */
function sb_steam_enrich_list_profiles(array $items)
{
	$ids = array();
	foreach ($items as $row) {
		if (!empty($row['communityid']))
			$ids[] = $row['communityid'];
		elseif (!empty($row['steam_profile']) && preg_match('#/profiles/(\d+)#', $row['steam_profile'], $m))
			$ids[] = $m[1];
	}
	$map = sb_steam_fetch_profile_urls($ids);
	foreach ($items as $k => $row) {
		$cid = '';
		if (!empty($row['communityid']))
			$cid = sb_steam_normalize_community_id($row['communityid']);
		if ($cid === '' && !empty($row['steam_profile']) && preg_match('#/profiles/(\d+)#', $row['steam_profile'], $m))
			$cid = sb_steam_normalize_community_id($m[1]);
		if ($cid === '')
			continue;
		$url = isset($map[$cid]) ? $map[$cid] : sb_steam_profile_url_fallback($cid);
		$items[$k]['steam_profile'] = $url;
		$items[$k]['steam_vanity'] = '';
		if (preg_match('#steamcommunity\.com/id/([A-Za-z0-9_-]+)#i', $url, $vm))
			$items[$k]['steam_vanity'] = $vm[1];
	}
	return $items;
}

/**
 * Разобрать ввод: STEAM_, Steam64, [U:1:…], URL /profiles/… или /id/vanity → STEAM_0:X:Y.
 * Если не распознано — вернуть исходную строку (дальше сработает обычная валидация).
 * @param string $input
 * @param string|null $error текст ошибки (по ссылке), если vanity не удалось разрешить
 */
function sb_steam_resolve_to_steamid2($input, &$error = null)
{
	$error = '';
	$input = trim((string)$input);
	if ($input === '')
		return '';

	// https://steamcommunity.com/id/thatsember
	if (preg_match('#(?:https?://)?(?:www\.)?steamcommunity\.com/id/([A-Za-z0-9_-]+)/?#i', $input, $m)) {
		$vanity = $m[1];
		$hasKey = defined('STEAMAPIKEY') && is_string(STEAMAPIKEY) && STEAMAPIKEY !== '';

		if ($hasKey) {
			$api = 'https://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key='
				. rawurlencode(STEAMAPIKEY) . '&vanityurl=' . rawurlencode($vanity);
			$j = @json_decode(@file_get_contents($api));
			if (!empty($j->response->success) && (int)$j->response->success === 1 && !empty($j->response->steamid))
				return FriendIDToSteamID((string)$j->response->steamid);
		}

		// запасной разбор без ключа (XML профиля; часто ломается)
		if (function_exists('GetFriendIDFromCommunityID')) {
			$fid = GetFriendIDFromCommunityID($vanity);
			if ($fid)
				return FriendIDToSteamID((string)$fid);
		}

		if (!$hasKey) {
			$error = 'Ссылки вида steamcommunity.com/id/... требуют STEAMAPIKEY в config.php. '
				. 'Либо вставьте Steam64 / STEAM_0:X:Y / ссылку /profiles/7656…';
		} else {
			$error = 'Не удалось распознать ссылку /id/' . htmlspecialchars($vanity, ENT_QUOTES, 'UTF-8')
				. '. Проверьте ник и STEAMAPIKEY, либо вставьте Steam64 / STEAM_0:X:Y.';
		}
		return '';
	}

	// https://steamcommunity.com/profiles/7656119…
	if (preg_match('#(?:https?://)?(?:www\.)?steamcommunity\.com/profiles/(\d{15,20})#i', $input, $m))
		return FriendIDToSteamID($m[1]);

	if (preg_match('/^7656\d{13}$/', $input))
		return FriendIDToSteamID($input);

	return $input;
}

function GetUserAvatar($sid = -1) {
    global $userbank;
    
    static $avatarCache = null;
    if (!$avatarCache) {
        $query = $GLOBALS['db']->Execute(sprintf("SELECT * FROM `%s_avatars`", DB_PREFIX));
        $avatarCache = [];

        while (!$query->EOF) {
            $avatarCache[$query->fields['authid']] = $query->fields['url'];
            $query->MoveNext();
        }
    }
    
    $communityid = false;
    $res = false;
    $AvatarFile = sprintf("themes/new_box/img/profile-pics/%d.jpg", rand(1,9));
    $sid = ($sid==-1)?($userbank->is_logged_in()?$userbank->getProperty("authid"):0):$sid;
    
    if ($sid) $communityid = GetCommunityIDFromSteamID2($sid);
    if ($communityid)
        $res = isset($avatarCache[$communityid]) ? $avatarCache[$communityid] : null;
    
    if ($res)
        $AvatarFile = $res;
    else if ($communityid) {
        $SteamResponse = @json_decode(file_get_contents(sprintf("http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s", STEAMAPIKEY, $communityid)));
        if (isset($SteamResponse->response->players[0]->avatarfull))
            $AvatarFile = $SteamResponse->response->players[0]->avatarfull;
        
        // Add file to memory cache
        $avatarCache[$communityid] = $AvatarFile;
        
        // And insert to DB
        $query = null;
        $AF = $GLOBALS['db']->qstr($AvatarFile);
        if ($res) $query = sprintf("UPDATE `%s_avatars` SET `url` = %s", DB_PREFIX, $AF);
        else $query = sprintf("INSERT INTO `%s_avatars` (`authid`, `url`) VALUES ('%s', %s)", DB_PREFIX, $communityid, $AF);
        $GLOBALS['db']->Execute($query);
    }
    return $AvatarFile;
}

function normalize_files_array($files = []) {
    $normalized_array = [];
    foreach($files as $index => $file) {
        if (!is_array($file['name'])) {
            $normalized_array[$index][] = $file;
            continue;
        }
        foreach($file['name'] as $idx => $name) {
            $normalized_array[$index][$idx] = [
                'name' => $name,
                'type' => $file['type'][$idx],
                'tmp_name' => $file['tmp_name'][$idx],
                'error' => $file['error'][$idx],
                'size' => $file['size'][$idx]
            ];
        }
    }
    return $normalized_array;
}

function getReasonByCode($code, $frmt) {
        switch ($code) {
                case 1:      return "Размер файла превысил допустимый размер";
                case 2:      return "Размер файла превысил допустимый размер";
                case 3:      return "Файл был получен лишь частично";
                case 4:      return "Файл не был загружен";
                case 6:      return "Отсутствует временная папка для загрузок";
                case 7:      return "Нет прав на запись";
                case 8:      return "Расширение PHP остановило загрузку файла принудительно";
                case 100500: return "Файл должен быть в формате ".$frmt;
                default:     return "Неизвестно";
        }
}

function prepareSize($bytes, $precision = 2) {
    $base = log($bytes, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)] .'b';
}

function generateMsgBoxJS($title = "Успех!", $text = "Действие успешно выполнено", $color = "green", $redirect = "", $button = true) {
    return sprintf('ShowBox("%s", "%s", "%s", "%s", %s)', htmlspecialchars(addslashes($title)), htmlspecialchars(addslashes($text)), $color, $redirect, $button?"true":"false");
}

function PushScriptToExecuteAfterLoadPage($script) {
	setcookie("ScriptFooter", $script, time()+60);
}

function FatalRefresh($url = 0) {
	if ($url === 0)
		$url = $_SERVER['REQUEST_URI'];
	
	ob_end_clean();
	Header("Location: " . $url);
	exit(0);
}

function AddScriptWithReload($script = 'alert("test")', $url = 0) {
    PushScriptToExecuteAfterLoadPage($script);
    FatalRefresh($url);
}

function smtpmail($mail_to, $subject, $message, $headers='') {
    $SEND =   "Date: ".date("D, d M Y H:i:s") . " UT\r\n";
    $SEND .=   'Subject: =?'.$GLOBALS['config']['smtp.charset'].'?B?'.base64_encode($subject)."=?=\r\n";
    if ($headers)
        $SEND .= $headers."\r\n\r\n";
    else {
        $SEND .= "Reply-To: ".$GLOBALS['config']['smtp.username']."\r\n";
        $SEND .= "MIME-Version: 1.0\r\n";
        $SEND .= "Content-Type: text/plain; charset=\"".$GLOBALS['config']['smtp.charset']."\"\r\n";
        $SEND .= "Content-Transfer-Encoding: 8bit\r\n";
        $SEND .= "From: \"".$GLOBALS['config']['smtp.from']."\" <".$GLOBALS['config']['smtp.username'].">\r\n";
        $SEND .= "To: $mail_to <$mail_to>\r\n";
        $SEND .= "X-Priority: 3\r\n\r\n";
    }
    
    $SEND .=  $message."\r\n";
    if (!$socket = @fsockopen($GLOBALS['config']['smtp.host'], $GLOBALS['config']['smtp.port'], $errno, $errstr, 5)) {
        new CSystemLog("e", "SMTP Mailing Error", sprintf("[%d] %s", $errno, $errstr));
        return false;
    }

    if (!server_parse($socket, "220", __LINE__)) return false;

    @fputs($socket, "HELO " . $GLOBALS['config']['smtp.host'] . "\r\n");
    if (!server_parse($socket, "250", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Не удаётся отправить HELO");
        fclose($socket);
        return false;
    }

    @fputs($socket, "AUTH LOGIN\r\n");
    if (!server_parse($socket, "334", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Не удаётся найти ответ на запрос авторизации клиента.");
        fclose($socket);
        return false;
    }

    @fputs($socket, base64_encode($GLOBALS['config']['smtp.username']) . "\r\n");
    if (!server_parse($socket, "334", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Логин пользователя не был принят сервером.");
        fclose($socket);
        return false;
    }

    @fputs($socket, base64_encode($GLOBALS['config']['smtp.password']) . "\r\n");
    if (!server_parse($socket, "235", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Пароль не был принят сервером как верный.");
        fclose($socket);
        return false;
    }

    @fputs($socket, "MAIL FROM: <".$GLOBALS['config']['smtp.username'].">\r\n");
    if (!server_parse($socket, "250", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Не удаётся отправить команду.");
        fclose($socket);
        return false;
    }

    @fputs($socket, "RCPT TO: <" . $mail_to . ">\r\n");
    if (!server_parse($socket, "250", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Не удаётся отправить команду.");
        fclose($socket);
        return false;
    }

    @fputs($socket, "DATA\r\n");
    if (!server_parse($socket, "354", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Не удаётся отправить команду.");
        fclose($socket);
        return false;
    }

    @fputs($socket, $SEND."\r\n.\r\n");
    if (!server_parse($socket, "250", __LINE__)) {
        new CSystemLog("e", "SMTP Mailing Error", "Не удаётся отправить письмо на удалённый сервер.");
        fclose($socket);
        return false;
    }

    @fputs($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

function server_parse($socket, $response, $line = __LINE__) {
    while (substr($response, 3, 1) != ' ') {
        if (!($response = fgets($socket, 256)))
            return false;
    }
    if (!(substr($response, 0, 3) == $response))
        return false;

    return true;
}

function EMail($to, $subject, $message, $headers) { // SendMail - registered in sb-callback.php :<
    // Защита от email header injection: адрес получателя, тема письма и
    // заголовки нередко строятся из пользовательского ввода (например,
    // $_GET['email'] в page.lostpassword.php). Если в них попадёт перевод
    // строки (\r или \n), можно внедрить произвольные дополнительные
    // заголовки (Cc/Bcc и т.д.) или вообще отдельное письмо через mail().
    // Поэтому вырезаем переводы строк из "to"/"subject" и убираем случайные
    // пустые строки (которые начинают новый заголовок) из $headers.
    $to      = str_replace(array("\r", "\n"), '', (string)$to);
    $subject = str_replace(array("\r", "\n"), '', (string)$subject);
    $headers = preg_replace('/\r\n|\r|\n(?!\S)/', "\n", trim((string)$headers));
    $headers = preg_replace('/\n{2,}/', "\n", $headers);

    if ($GLOBALS['config']['smtp.enabled'] == "1")
        $func = "smtpmail";
    else
        $func = "mail";
        
    if ($func == "smtpmail")
        $headers = str_replace('Sourcebans@' . sb_get_site_host(), $GLOBALS['config']['smtp.username'], $headers);

    return $func($to, $subject, $message, $headers);
}

function decompress_tar($path, $output) {
    try {
        $phar = new PharData($path);
        $phar->extractTo($output);
        return true;
    } catch (PharException $e) {
        new CSystemLog("e", "PHP Exception", $e->getMessage());
        return false;
    }
}

function FindArrayItemOnKey($array, $key, $value) {
    foreach ($array as $arr)
        if ($arr[$key] == $value)
            return $arr;
    
    return null;
}

function getClientByName($serverInstance, $name) {
    $status = explode("\n", $serverInstance->SendCommand("status"));
    foreach ($status as $statusStr) {
        $data = parseStatus($statusStr);

        if ($data['name'] == $name)
            return $data;
    }

    return false;
}

function getClientBySteamId($serverInstance, $authId) {
    $authId = getAccountId($authId);

    $status = explode("\n", $serverInstance->SendCommand("status"));
    foreach ($status as $statusStr) {
        $data = parseStatus($statusStr);

        if ($authId == getAccountId($data['steam']))
            return $data;
    }

    return false;
}

function getClientByIp($serverInstance, $ip) {
    $status = explode("\n", $serverInstance->SendCommand("status"));
    foreach ($status as $statusStr) {
        $data = parseStatus($statusStr);
        
        if ($data['ip'] == $ip)
            return $data;
    }

    return false;
}

function parseStatus($selected) {
    $matches = null;
    $response = [];

    // Try parse SteamID
    if (preg_match('/STEAM_(\d):([0-1]):(\d{0,})/', $selected, $matches, PREG_OFFSET_CAPTURE) || preg_match('/\[U:1:\d{0,}\]/', $selected, $matches, PREG_OFFSET_CAPTURE)) {
        $response['steam'] = $matches[0][0];
    } else {
        $response['steam'] = 'STEAM_ID_PENDING';
    }
    
    // Try parse IP
    if (preg_match('/(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/', $selected, $matches, PREG_OFFSET_CAPTURE)) {
        $response['ip'] = filter_var($matches[0][0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if ($response['ip'] === FALSE) {
            $response['ip'] = "127.0.0.1";
        }
    } else {
        $response['ip'] = "127.0.0.1";
    }
    
    // Maybe, try parse nickname?
    if (preg_match('/\"(.{1,})\"/', $selected, $matches, PREG_OFFSET_CAPTURE)) {
        $response['name'] = $matches[1][0];
    } else {
        $response['name'] = "unnamed";
    }
    
    return $response;
}

function kickClient($serverInstance, $identity) {
    $client = null;
    if (filter_var($identity, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        $client = getClientByIp($serverInstance, $identity);
    else
        $client = getClientBySteamId($serverInstance, $identity);

    if (!$client)
        return false;

    $serverInstance->sendCommand(sprintf("kickid \"%s\"", $client['steam']));
    return true;
}

/** Права на просмотр рецидива (та же маска, что у admin&c=recidivism). */
function RecidivismAccessMask()
{
	return ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_GROUP_BANS;
}

/**
 * Семья аккаунтов: fingerprint (rebanner_*) + API LinkedAccounts.
 * Возвращает список уникальных STEAM_0:… (без самого $authid в начале — он добавляется вызывающим при необходимости).
 *
 * @return array{fingerprint: string[], api: string[], all: string[], fingerprint_id: string, is_banned: int, banned_duration: int, banned_timestamp: int}
 */
function RecidivismResolveFamily($authid)
{
	$out = array(
		'fingerprint' => array(),
		'api' => array(),
		'all' => array(),
		'fingerprint_id' => '',
		'is_banned' => 0,
		'banned_duration' => 0,
		'banned_timestamp' => 0
	);
	$authid = trim((string)$authid);
	if ($authid === '' || !isset($GLOBALS['db']))
		return $out;

	$norm = function ($sid) {
		$sid = trim((string)$sid);
		if ($sid === '' || !preg_match('/^STEAM_[0-9]:[0-1]:\d+$/', $sid))
			return '';
		if ($sid[6] !== '0')
			$sid = 'STEAM_0' . substr($sid, 7);
		return $sid;
	};
	$authid = $norm($authid);
	if ($authid === '')
		return $out;

	$seen = array($authid => true);
	$out['all'][] = $authid;

	$add = function ($sid, $bucket) use (&$out, &$seen, $norm) {
		$sid = $norm($sid);
		if ($sid === '')
			return;
		$out[$bucket][] = $sid;
		if (!isset($seen[$sid])) {
			$seen[$sid] = true;
			$out['all'][] = $sid;
		}
	};

	// --- Local Re-Banner tables (same MA DB by default) ---
	if (!defined('REBANNER_USE_MA_DB') || REBANNER_USE_MA_DB) {
		$db = $GLOBALS['db'];
		$hasFp = @$db->GetOne("SHOW TABLES LIKE " . $db->qstr('rebanner_fingerprints'));
		if ($hasFp) {
			// FIND_IN_SET: "_" in SteamID must not be LIKE-wildcard
			$row = @$db->GetRow(
				"SELECT `fingerprint`, `steamid2`, `is_banned`, `banned_duration`, `banned_timestamp`
				 FROM `rebanner_fingerprints`
				 WHERE FIND_IN_SET(?, REPLACE(`steamid2`, ';', ',')) > 0 LIMIT 1",
				array($authid)
			);
			if (!$row) {
				// also try STEAM_1: form
				$alt = 'STEAM_1' . substr($authid, 7);
				$row = @$db->GetRow(
					"SELECT `fingerprint`, `steamid2`, `is_banned`, `banned_duration`, `banned_timestamp`
					 FROM `rebanner_fingerprints`
					 WHERE FIND_IN_SET(?, REPLACE(`steamid2`, ';', ',')) > 0 LIMIT 1",
					array($alt)
				);
			}
			if (!$row) {
				$fp = @$db->GetOne(
					"SELECT `fingerprint` FROM `rebanner_lookup` WHERE `steamid2` IN (?, ?) LIMIT 1",
					array($authid, 'STEAM_1' . substr($authid, 7))
				);
				if ($fp) {
					$row = @$db->GetRow(
						"SELECT `fingerprint`, `steamid2`, `is_banned`, `banned_duration`, `banned_timestamp`
						 FROM `rebanner_fingerprints` WHERE `fingerprint` = ? LIMIT 1",
						array($fp)
					);
				}
			}
			if (!empty($row['steamid2']) || !empty($row['fingerprint'])) {
				$out['fingerprint_id'] = isset($row['fingerprint']) ? (string)$row['fingerprint'] : '';
				$out['is_banned'] = !empty($row['is_banned']) ? 1 : 0;
				$out['banned_duration'] = isset($row['banned_duration']) ? (int)$row['banned_duration'] : 0;
				$out['banned_timestamp'] = isset($row['banned_timestamp']) ? (int)$row['banned_timestamp'] : 0;
				if (!empty($row['steamid2'])) {
					foreach (explode(';', $row['steamid2']) as $part)
						$add($part, 'fingerprint');
				}
			}
		}
	}
	if (empty($out['fingerprint']))
		$out['fingerprint'][] = $authid;

	// --- PARSEC API LinkedAccounts (Steam64 → Steam2) ---
	$apiBase = defined('PARSEC_API_PLAYER_URL') ? PARSEC_API_PLAYER_URL : '';
	if ($apiBase !== '' && function_exists('SteamIDToFriendID')) {
		$friend = @SteamIDToFriendID($authid);
		if ($friend) {
			$url = rtrim($apiBase, '/') . '/' . rawurlencode((string)$friend);
			$json = RecidivismHttpGetJson($url, 3);
			if (is_array($json) && !empty($json['response'][0])) {
				$resp = $json['response'][0];
				if (!empty($resp['LinkedAccounts']) && is_array($resp['LinkedAccounts'])) {
					foreach ($resp['LinkedAccounts'] as $sid64) {
						$sid2 = @FriendIDToSteamID((string)$sid64);
						if ($sid2)
							$add($sid2, 'api');
					}
				}
			}
		}
	}

	return $out;
}

/**
 * HTTP GET JSON with short timeout. Returns decoded array or null.
 * File cache 5 minutes (PARSEC API docs).
 */
function RecidivismHttpGetJson($url, $timeoutSec = 3, $cacheTtl = 300)
{
	$timeoutSec = max(1, (int)$timeoutSec);
	$cacheTtl = max(0, (int)$cacheTtl);
	$headers = array();
	$parsecToken = defined('PARSEC_API_PLAYER_TOKEN') ? trim((string)PARSEC_API_PLAYER_TOKEN) : '';
	if ($parsecToken !== '')
		$headers[] = 'X-SB-Player-Token: ' . $parsecToken;

	$cacheFile = '';
	if ($cacheTtl > 0 && defined('ROOT')) {
		$cacheDir = ROOT . 'cache';
		if (!is_dir($cacheDir))
			@mkdir($cacheDir, 0755, true);
		if (is_dir($cacheDir) && is_writable($cacheDir)) {
			$cacheFile = $cacheDir . '/parsec_' . md5($url . '|' . $parsecToken) . '.json';
			if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
				$cached = @file_get_contents($cacheFile);
				if ($cached !== false) {
					$data = json_decode($cached, true);
					if (is_array($data))
						return $data;
				}
			}
		}
	}

	$raw = false;
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => $timeoutSec,
			CURLOPT_TIMEOUT => $timeoutSec,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_USERAGENT => 'SibnetMA-Recidivism/1.0'
		));
		if (!empty($headers))
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$raw = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($raw === false || $code < 200 || $code >= 300)
			return null;
	} else {
		$http = array('timeout' => $timeoutSec, 'ignore_errors' => true);
		if (!empty($headers))
			$http['header'] = implode("\r\n", $headers);
		$ctx = stream_context_create(array(
			'http' => $http,
			'ssl' => array('verify_peer' => true)
		));
		$raw = @file_get_contents($url, false, $ctx);
		if ($raw === false)
			return null;
	}
	$data = json_decode($raw, true);
	if (!is_array($data))
		return null;
	if ($cacheFile !== '')
		@file_put_contents($cacheFile, $raw, LOCK_EX);
	return $data;
}

/**
 * Карточки связанных аккаунтов для UI (очки + активный бан).
 *
 * @return array list of assoc rows
 */
function RecidivismBuildLinkedCards($authid)
{
	$family = RecidivismResolveFamily($authid);
	$cards = array();
	if (empty($family['all']) || !isset($GLOBALS['db']))
		return $cards;

	$db = $GLOBALS['db'];
	$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sb';
	$self = trim((string)$authid);
	if (strncmp($self, 'STEAM_', 6) === 0 && $self[6] !== '0')
		$self = 'STEAM_0' . substr($self, 7);

	foreach ($family['all'] as $sid) {
		if ($sid === $self)
			continue;

		$sources = array();
		if (in_array($sid, $family['fingerprint'], true))
			$sources[] = 'fingerprint';
		if (in_array($sid, $family['api'], true))
			$sources[] = 'api';

		$scores = array('ban' => 0.0, 'gag' => 0.0, 'mute' => 0.0);
		$rows = @$db->GetAll(
			"SELECT `track`, `score` FROM `{$prefix}_recid_scores` WHERE `authid` = ?",
			array($sid)
		);
		if (is_array($rows)) {
			foreach ($rows as $r) {
				$tr = strtolower($r['track']);
				if (isset($scores[$tr]))
					$scores[$tr] = round((float)$r['score'], 1);
			}
		}

		$name = @$db->GetOne(
			"SELECT `name` FROM `{$prefix}_bans` WHERE `authid` = ? ORDER BY `created` DESC LIMIT 1",
			array($sid)
		);
		if (!$name) {
			$name = @$db->GetOne(
				"SELECT `name` FROM `{$prefix}_comms` WHERE `authid` = ? ORDER BY `created` DESC LIMIT 1",
				array($sid)
			);
		}

		$activeBan = (int)@$db->GetOne(
			"SELECT COUNT(*) FROM `{$prefix}_bans`
			 WHERE `authid` = ? AND `RemoveType` IS NULL AND (`length` = 0 OR `ends` > UNIX_TIMESTAMP())",
			array($sid)
		);

		$cards[] = array(
			'authid' => $sid,
			'name' => $name ? $name : '',
			'sources' => $sources,
			'source_label' => implode('+', $sources),
			'points_ban' => $scores['ban'],
			'points_gag' => $scores['gag'],
			'points_mute' => $scores['mute'],
			'points_display' => sprintf('B%s G%s M%s', $scores['ban'], $scores['gag'], $scores['mute']),
			'active_ban' => $activeBan > 0,
			'view_url' => sb_url('admin', array('c' => 'recidivism', 'steam' => $sid)),
			'family_size' => count($family['all'])
		);
	}

	return $cards;
}

/**
 * Family size (+alts with scores/ban) for banlist hint. Batch-friendly.
 *
 * @param array $authids STEAM ids
 * @return array authid => array{alts:int, hint:string}
 */
function RecidivismFamilyHintsForAuthids(array $authids)
{
	$hints = array();
	foreach ($authids as $aid) {
		$hints[$aid] = array('alts' => 0, 'hint' => '');
	}
	if (!RecidivismCanView() || empty($authids) || !isset($GLOBALS['db']))
		return $hints;

	$db = $GLOBALS['db'];
	$hasFp = @$db->GetOne("SHOW TABLES LIKE " . $db->qstr('rebanner_fingerprints'));
	if (!$hasFp)
		return $hints;

	foreach ($authids as $aid) {
		if (!preg_match('/^STEAM_[0-9]:[0-1]:\d+$/', $aid))
			continue;
		$sid0 = ($aid[6] !== '0') ? ('STEAM_0' . substr($aid, 7)) : $aid;
		$sid1 = 'STEAM_1' . substr($sid0, 7);
		$steams = @$db->GetOne(
			"SELECT `steamid2` FROM `rebanner_fingerprints`
			 WHERE FIND_IN_SET(?, REPLACE(`steamid2`, ';', ',')) > 0
			    OR FIND_IN_SET(?, REPLACE(`steamid2`, ';', ',')) > 0
			 LIMIT 1",
			array($sid0, $sid1)
		);
		if (!$steams)
			continue;
		$parts = array();
		foreach (explode(';', $steams) as $p) {
			$p = trim($p);
			if (preg_match('/^STEAM_[0-9]:[0-1]:\d+$/', $p))
				$parts[$p] = true;
		}
		$n = count($parts);
		if ($n > 1) {
			$alts = $n - 1;
			$hints[$aid] = array(
				'alts' => $alts,
				'hint' => '(+' . $alts . ')'
			);
		}
	}
	return $hints;
}

function RecidivismCanView()
{
	global $userbank;
	return isset($userbank) && $userbank->HasAccess(RecidivismAccessMask());
}

/**
 * Подтянуть очки Ban/Gag/Mute в строки банлиста / commslist (in-place).
 * Гостям и админам без прав — поля пустые, колонка скрывается через view_recidivism.
 *
 * @param array $rows ссылка на массив банов (элементы с ключом steamid)
 */
function RecidivismAttachScoresToList(array &$rows)
{
	foreach ($rows as &$row) {
		$row['recid_ban'] = null;
		$row['recid_gag'] = null;
		$row['recid_mute'] = null;
		$row['recid_display'] = '';
		$row['recid_url'] = '';
		$row['view_recidivism'] = false;
	}
	unset($row);

	if (!RecidivismCanView() || !isset($GLOBALS['db']) || empty($rows))
		return;

	$authids = array();
	foreach ($rows as $row) {
		if (!empty($row['steamid']) && preg_match('/^STEAM_[0-9]:[0-1]:\d+$/', $row['steamid']))
			$authids[$row['steamid']] = true;
	}
	if (empty($authids))
		return;

	$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sb';
	$tbl = $prefix . '_recid_scores';
	$chk = @$GLOBALS['db']->GetOne("SHOW TABLES LIKE " . $GLOBALS['db']->qstr($tbl));

	$map = array();
	if ($chk) {
		$ids = array_keys($authids);
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$scoreRows = @$GLOBALS['db']->GetAll(
			"SELECT `authid`, `track`, `score` FROM `{$tbl}` WHERE `authid` IN ($ph)",
			$ids
		);
		if (is_array($scoreRows)) {
			foreach ($scoreRows as $sr) {
				$aid = $sr['authid'];
				if (!isset($map[$aid]))
					$map[$aid] = array('ban' => 0.0, 'gag' => 0.0, 'mute' => 0.0);
				$tr = strtolower($sr['track']);
				if (isset($map[$aid][$tr]))
					$map[$aid][$tr] = round((float)$sr['score'], 1);
			}
		}
	}

	$hintAuths = array();
	foreach ($rows as $row) {
		if (!empty($row['steamid']))
			$hintAuths[] = $row['steamid'];
	}
	$familyHints = RecidivismFamilyHintsForAuthids($hintAuths);

	foreach ($rows as &$row) {
		$row['view_recidivism'] = true;
		$row['recid_family_hint'] = '';
		if (!empty($row['steamid'])) {
			$row['recid_url'] = sb_url('admin', array('c' => 'recidivism', 'steam' => $row['steamid']));
			if (!empty($familyHints[$row['steamid']]['hint']))
				$row['recid_family_hint'] = $familyHints[$row['steamid']]['hint'];
		}
		if (empty($row['steamid']) || !isset($map[$row['steamid']])) {
			$row['recid_display'] = '—';
			if ($row['recid_family_hint'] !== '')
				$row['recid_display'] = '— ' . $row['recid_family_hint'];
			continue;
		}
		$s = $map[$row['steamid']];
		$row['recid_ban'] = $s['ban'];
		$row['recid_gag'] = $s['gag'];
		$row['recid_mute'] = $s['mute'];
		$row['recid_display'] = sprintf('B%s G%s M%s', $s['ban'], $s['gag'], $s['mute']);
		if ($row['recid_family_hint'] !== '')
			$row['recid_display'] .= ' ' . $row['recid_family_hint'];
	}
	unset($row);
}

/**
 * Снять очки рецидива при отмене наказания (зеркало ma_recidivism_revoke_on_unpunish).
 *
 * @param string      $authid  STEAM_x:y:z (пусто — только по bid)
 * @param string      $track   ban|gag|mute
 * @param string      $maTable bans|comms|none
 * @param int|null    $maBid   bid из sb_bans / sb_comms
 * @param string      $reason  короткий тег причины revoke
 * @return int число помеченных событий
 */
function RecidivismRevokeOnUnpunish($authid, $track, $maTable = 'none', $maBid = null, $reason = 'web_unpunish')
{
	if (!isset($GLOBALS['db']))
		return 0;

	$track = strtolower((string)$track);
	if (!in_array($track, array('ban', 'gag', 'mute'), true))
		return 0;

	$db = $GLOBALS['db'];
	$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sb';
	$now = time();
	$reason = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$reason), 0, 64);
	if ($reason === '')
		$reason = 'web_unpunish';

	$affected = 0;

	// 1) По связи ma_bid (если плагин успел записать bid)
	if ($maBid !== null && (int)$maBid > 0 && in_array($maTable, array('bans', 'comms'), true)) {
		$db->Execute(
			"UPDATE `{$prefix}_recid_events` SET
				`revoked` = 1,
				`revoked_at` = ?,
				`revoke_reason` = ?
			 WHERE `ma_table` = ? AND `ma_bid` = ? AND `track` = ? AND `revoked` = 0",
			array($now, $reason, $maTable, (int)$maBid, $track)
		);
		$affected += (int)$db->Affected_Rows();
	}

	// 2) Fallback: последнее активное событие ветки по SteamID (как in-game плагин)
	if ($affected === 0 && $authid !== '' && $authid !== null) {
		$row = $db->GetRow(
			"SELECT `event_id` FROM `{$prefix}_recid_events`
			 WHERE `authid` = ? AND `track` = ? AND `revoked` = 0
			 ORDER BY `created_at` DESC, `event_id` DESC LIMIT 1",
			array($authid, $track)
		);
		if (!empty($row['event_id'])) {
			$db->Execute(
				"UPDATE `{$prefix}_recid_events` SET
					`revoked` = 1,
					`revoked_at` = ?,
					`revoke_reason` = ?
				 WHERE `event_id` = ? AND `revoked` = 0",
				array($now, $reason, (int)$row['event_id'])
			);
			$affected += (int)$db->Affected_Rows();
		}
	}

	if ($affected > 0 && $authid !== '' && $authid !== null)
		RecidivismRecomputeScore($authid, $track);

	return $affected;
}

/**
 * Пересчитать sb_recid_scores для ветки (упрощённо, без decay — плагин уточнит при след. событии).
 * Счёт = сумма points_raw * incident_multiplier за окно 30 дней.
 */
function RecidivismRecomputeScore($authid, $track)
{
	if (!isset($GLOBALS['db']) || $authid === '' || $authid === null)
		return;

	$track = strtolower((string)$track);
	if (!in_array($track, array('ban', 'gag', 'mute'), true))
		return;

	$db = $GLOBALS['db'];
	$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sb';
	$now = time();
	$window = 30 * 86400;

	$cfg = $db->GetOne("SELECT `cfg_value` FROM `{$prefix}_recid_config` WHERE `cfg_key` = 'window_days'");
	if ($cfg !== false && $cfg !== null && (int)$cfg > 0)
		$window = (int)$cfg * 86400;

	$cutoff = $now - $window;
	$rows = $db->GetAll(
		"SELECT `points_raw`, `incident_multiplier`, `created_at` FROM `{$prefix}_recid_events`
		 WHERE `authid` = ? AND `track` = ? AND `revoked` = 0 AND `created_at` >= ?",
		array($authid, $track, $cutoff)
	);

	$score = 0.0;
	$events = 0;
	if (is_array($rows)) {
		foreach ($rows as $r) {
			$age = $now - (int)$r['created_at'];
			$weight = ($window > 0) ? max(0.0, 1.0 - ($age / $window)) : 1.0;
			$score += (float)$r['points_raw'] * (float)$r['incident_multiplier'] * $weight;
			$events++;
		}
	}

	$db->Execute(
		"INSERT INTO `{$prefix}_recid_scores`
			(`authid`, `track`, `score`, `events_active`, `escalated`, `escalated_at`, `updated_at`)
		 VALUES (?, ?, ?, ?, 0, NULL, ?)
		 ON DUPLICATE KEY UPDATE
			`score` = VALUES(`score`),
			`events_active` = VALUES(`events_active`),
			`escalated` = IF(VALUES(`events_active`) = 0, 0, `escalated`),
			`escalated_at` = IF(VALUES(`events_active`) = 0, NULL, `escalated_at`),
			`updated_at` = VALUES(`updated_at`)",
		array($authid, $track, round($score, 2), $events, $now)
	);
}

/* =============================================================================
 * PARSEC / Re-Banner admin panel (read + gated fingerprint is_banned writes)
 * ============================================================================= */

define('PARSEC_PANEL_SESSION_UNLOCK', 'parsec_panel_unlock_until');
define('PARSEC_PANEL_SESSION_WRITE', 'parsec_panel_write_mode');
define('PARSEC_PANEL_SESSION_CSRF', 'parsec_panel_csrf');
define('PARSEC_PANEL_UNLOCK_TTL', 1800); // 30 minutes

function ParsecPanelCanView()
{
	return function_exists('RecidivismCanView') ? RecidivismCanView() : false;
}

function ParsecPanelNormalizeSteam($sid)
{
	$sid = trim((string)$sid);
	if ($sid === '' || !preg_match('/^STEAM_[0-9]:[0-1]:\d+$/', $sid))
		return '';
	if ($sid[6] !== '0')
		$sid = 'STEAM_0' . substr($sid, 7);
	return $sid;
}

function ParsecPanelWriteAllowlist()
{
	$list = array();
	$raw = defined('PARSEC_PANEL_WRITE_STEAMIDS') ? PARSEC_PANEL_WRITE_STEAMIDS : '';
	foreach (explode(',', (string)$raw) as $part) {
		$n = ParsecPanelNormalizeSteam($part);
		if ($n !== '')
			$list[$n] = true;
	}
	return $list;
}

function ParsecPanelAdminSteam()
{
	global $userbank;
	if (!isset($userbank) || !$userbank->is_logged_in())
		return '';
	return ParsecPanelNormalizeSteam($userbank->GetProperty('authid'));
}

/** OWNER or WEB_SETTINGS + SteamID in allowlist. */
function ParsecPanelCanWriteEligible()
{
	global $userbank;
	if (!isset($userbank) || !$userbank->is_logged_in())
		return false;
	if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_WEB_SETTINGS))
		return false;
	$steam = ParsecPanelAdminSteam();
	if ($steam === '')
		return false;
	$allow = ParsecPanelWriteAllowlist();
	return isset($allow[$steam]);
}

function ParsecPanelSessionUnlocked()
{
	if (session_status() !== PHP_SESSION_ACTIVE && function_exists('session_id') && session_id() === '')
		return false;
	$until = isset($_SESSION[PARSEC_PANEL_SESSION_UNLOCK]) ? (int)$_SESSION[PARSEC_PANEL_SESSION_UNLOCK] : 0;
	if ($until < time()) {
		unset($_SESSION[PARSEC_PANEL_SESSION_UNLOCK], $_SESSION[PARSEC_PANEL_SESSION_WRITE]);
		return false;
	}
	return true;
}

function ParsecPanelWriteModeOn()
{
	return ParsecPanelSessionUnlocked()
		&& !empty($_SESSION[PARSEC_PANEL_SESSION_WRITE]);
}

/** All gates for actual DB mutation. */
function ParsecPanelCanWrite()
{
	return ParsecPanelCanWriteEligible()
		&& ParsecPanelSessionUnlocked()
		&& ParsecPanelWriteModeOn();
}

function ParsecPanelEnsureCsrf()
{
	if (empty($_SESSION[PARSEC_PANEL_SESSION_CSRF]))
		$_SESSION[PARSEC_PANEL_SESSION_CSRF] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
	return $_SESSION[PARSEC_PANEL_SESSION_CSRF];
}

function ParsecPanelCheckCsrf($token)
{
	$expect = isset($_SESSION[PARSEC_PANEL_SESSION_CSRF]) ? (string)$_SESSION[PARSEC_PANEL_SESSION_CSRF] : '';
	return $expect !== '' && is_string($token) && hash_equals($expect, $token);
}

function ParsecPanelTryUnlock($password)
{
	if (!ParsecPanelCanWriteEligible())
		return false;
	$cfg = defined('PARSEC_PANEL_WRITE_PASSWORD') ? (string)PARSEC_PANEL_WRITE_PASSWORD : '';
	if ($cfg === '' || $cfg === 'change-me-parsec-panel')
		return false; // must set a real password in config.php
	if (!hash_equals($cfg, (string)$password))
		return false;
	$_SESSION[PARSEC_PANEL_SESSION_UNLOCK] = time() + PARSEC_PANEL_UNLOCK_TTL;
	$_SESSION[PARSEC_PANEL_SESSION_WRITE] = 0; // toggle still off
	return true;
}

function ParsecPanelSetWriteMode($on)
{
	if (!ParsecPanelCanWriteEligible() || !ParsecPanelSessionUnlocked())
		return false;
	$_SESSION[PARSEC_PANEL_SESSION_WRITE] = $on ? 1 : 0;
	return true;
}

function ParsecPanelLockSession()
{
	unset($_SESSION[PARSEC_PANEL_SESSION_UNLOCK], $_SESSION[PARSEC_PANEL_SESSION_WRITE]);
}

function ParsecPanelFormatDuration($seconds)
{
	$seconds = (int)$seconds;
	if ($seconds <= 0)
		return 'перманент';
	$d = (int)floor($seconds / 86400);
	$h = (int)floor(($seconds % 86400) / 3600);
	$m = (int)floor(($seconds % 3600) / 60);
	$parts = array();
	if ($d > 0) $parts[] = $d . ' д';
	if ($h > 0) $parts[] = $h . ' ч';
	if ($m > 0 && $d === 0) $parts[] = $m . ' мин';
	if (!$parts)
		$parts[] = $seconds . ' сек';
	return implode(' ', $parts);
}

/** 32-char hex → "abcd efgh …" groups for UI (full string preserved). */
function ParsecPanelFormatFingerprint($fingerprint)
{
	$fingerprint = strtolower(preg_replace('/\s+/', '', (string)$fingerprint));
	if ($fingerprint === '')
		return '';
	return trim(chunk_split($fingerprint, 4, ' '));
}

function ParsecPanelApiStateLabel($state)
{
	switch (strtolower(trim((string)$state))) {
		case 'clean': return 'Не в бане';
		case 'temporary': return 'Временный бан';
		case 'permanent': return 'Бан навсегда';
		default: return $state !== '' ? $state : 'нет данных';
	}
}

function ParsecPanelApiStateClass($state)
{
	switch (strtolower(trim((string)$state))) {
		case 'clean': return 'parsec-badge-ok';
		case 'temporary': return 'parsec-badge-warn';
		case 'permanent': return 'parsec-badge-danger';
		default: return 'parsec-badge-muted';
	}
}

function ParsecPanelTablesOk()
{
	if (!isset($GLOBALS['db']))
		return false;
	if (defined('REBANNER_USE_MA_DB') && !REBANNER_USE_MA_DB)
		return false;
	$db = $GLOBALS['db'];
	return (bool)@$db->GetOne("SHOW TABLES LIKE " . $db->qstr('rebanner_fingerprints'));
}

/**
 * Fetch fingerprint row by exact fingerprint hash.
 * @return array|null
 */
function ParsecPanelGetFingerprint($fingerprint)
{
	$fingerprint = trim((string)$fingerprint);
	if ($fingerprint === '' || !ParsecPanelTablesOk())
		return null;
	$row = @$GLOBALS['db']->GetRow(
		"SELECT `fingerprint`, `steamid2`, `is_banned`, `banned_duration`, `banned_timestamp`, `ip`
		 FROM `rebanner_fingerprints` WHERE `fingerprint` = ? LIMIT 1",
		array($fingerprint)
	);
	return $row ? $row : null;
}

/**
 * Paginated list of banned fingerprints.
 * @return array{rows: array, total: int}
 */
function ParsecPanelListBannedFingerprints($page = 1, $perPage = 25)
{
	$out = array('rows' => array(), 'total' => 0);
	if (!ParsecPanelTablesOk())
		return $out;
	$db = $GLOBALS['db'];
	$perPage = max(5, min(100, (int)$perPage));
	$page = max(1, (int)$page);
	$out['total'] = (int)@$db->GetOne("SELECT COUNT(*) FROM `rebanner_fingerprints` WHERE `is_banned` = 1");
	$offset = ($page - 1) * $perPage;
	$rows = @$db->GetAll(
		"SELECT `fingerprint`, `steamid2`, `is_banned`, `banned_duration`, `banned_timestamp`
		 FROM `rebanner_fingerprints` WHERE `is_banned` = 1
		 ORDER BY `banned_timestamp` DESC LIMIT " . (int)$offset . ", " . (int)$perPage
	);
	if (!is_array($rows))
		return $out;
	foreach ($rows as $r) {
		$steams = array();
		if (!empty($r['steamid2'])) {
			foreach (explode(';', $r['steamid2']) as $p) {
				$n = ParsecPanelNormalizeSteam($p);
				if ($n !== '')
					$steams[] = $n;
			}
		}
		$fp = (string)$r['fingerprint'];
		$out['rows'][] = array(
			'fingerprint' => $fp,
			'fingerprint_fmt' => ParsecPanelFormatFingerprint($fp),
			'steam_count' => count($steams),
			'steams' => $steams,
			'steams_preview' => implode(', ', array_slice($steams, 0, 3))
				. (count($steams) > 3 ? '…' : ''),
			'banned_duration' => (int)$r['banned_duration'],
			'banned_duration_fmt' => ParsecPanelFormatDuration($r['banned_duration']),
			'banned_timestamp' => (int)$r['banned_timestamp'],
			'banned_at_fmt' => !empty($r['banned_timestamp'])
				? date('d.m.Y H:i', (int)$r['banned_timestamp'])
				: '—',
			'open_url' => !empty($steams[0])
				? (sb_url('admin', array('c' => 'parsec', 'steam' => $steams[0])))
				: ('index.php?p=admin&c=parsec&fp=' . rawurlencode($fp))
		);
	}
	return $out;
}

/**
 * PARSEC API snapshot for one Steam2.
 * @return array|null
 */
function ParsecPanelFetchApiPlayer($authid)
{
	$authid = ParsecPanelNormalizeSteam($authid);
	$apiBase = defined('PARSEC_API_PLAYER_URL') ? PARSEC_API_PLAYER_URL : '';
	if ($authid === '' || $apiBase === '' || !function_exists('SteamIDToFriendID'))
		return null;
	$friend = @SteamIDToFriendID($authid);
	if (!$friend)
		return null;
	$url = rtrim($apiBase, '/') . '/' . rawurlencode((string)$friend);
	$json = RecidivismHttpGetJson($url, 3);
	if (!is_array($json) || empty($json['response'][0]))
		return null;
	$r = $json['response'][0];
	$state = isset($r['CurrentState']) ? (string)$r['CurrentState'] : '';
	return array(
		'steam64' => isset($r['SteamID']) ? (string)$r['SteamID'] : (string)$friend,
		'name' => isset($r['Name']) ? (string)$r['Name'] : '',
		'state' => $state,
		'state_label' => ParsecPanelApiStateLabel($state),
		'state_class' => ParsecPanelApiStateClass($state),
		'ban_reason' => isset($r['BanReason']) ? (string)$r['BanReason'] : '',
		'linked_count' => isset($r['LinkedAccountsCount']) ? (int)$r['LinkedAccountsCount'] : 0,
		'ban_timestamp' => isset($r['BanTimestamp']) ? (int)$r['BanTimestamp'] : 0,
		'unban_timestamp' => isset($r['UnbanTimestamp']) ? (int)$r['UnbanTimestamp'] : 0,
		'ban_at_fmt' => !empty($r['BanTimestamp']) ? date('d.m.Y H:i', (int)$r['BanTimestamp']) : '',
		'unban_at_fmt' => !empty($r['UnbanTimestamp']) ? date('d.m.Y H:i', (int)$r['UnbanTimestamp']) : ''
	);
}

/**
 * Clear fingerprint ban flag (same SQL as Re-Banner Banning_ClearBanForFingerprint).
 */
function ParsecPanelClearFingerprintBan($fingerprint)
{
	if (!ParsecPanelCanWrite() || !ParsecPanelTablesOk())
		return false;
	$fingerprint = trim((string)$fingerprint);
	if ($fingerprint === '' || !preg_match('/^[a-fA-F0-9]{8,128}$/', $fingerprint))
		return false;
	$ok = @$GLOBALS['db']->Execute(
		"UPDATE `rebanner_fingerprints`
		 SET `is_banned` = 0, `banned_duration` = 0, `banned_timestamp` = 0
		 WHERE `fingerprint` = ?",
		array($fingerprint)
	);
	if ($ok) {
		$admin = ParsecPanelAdminSteam();
		new CSystemLog('m', 'PARSEC panel: clear is_banned',
			'fingerprint=' . $fingerprint . ' by ' . $admin);
	}
	return (bool)$ok;
}

/**
 * Mark fingerprint banned (perm, duration 0 seconds) — sync helper.
 */
function ParsecPanelMarkFingerprintBanned($fingerprint)
{
	if (!ParsecPanelCanWrite() || !ParsecPanelTablesOk())
		return false;
	$fingerprint = trim((string)$fingerprint);
	if ($fingerprint === '' || !preg_match('/^[a-fA-F0-9]{8,128}$/', $fingerprint))
		return false;
	$now = time();
	$ok = @$GLOBALS['db']->Execute(
		"UPDATE `rebanner_fingerprints`
		 SET `is_banned` = 1, `banned_duration` = 0, `banned_timestamp` = ?
		 WHERE `fingerprint` = ?",
		array($now, $fingerprint)
	);
	if ($ok) {
		$admin = ParsecPanelAdminSteam();
		new CSystemLog('m', 'PARSEC panel: mark is_banned',
			'fingerprint=' . $fingerprint . ' by ' . $admin);
	}
	return (bool)$ok;
}
