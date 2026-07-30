<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$seo_document_title|escape}</title>
<meta name="description" content="{$seo_description|escape}">
{if $seo_noindex}
<meta name="robots" content="noindex,nofollow">
{else}
<meta name="robots" content="index,follow">
{/if}
<link rel="canonical" href="{$seo_canonical|escape}">
<link rel="icon" href="./images/favicon.ico">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
<meta property="og:site_name" content="{$og_site_name|escape}">
<meta property="og:title" content="{$og_title|escape}">
<meta property="og:description" content="{$og_description|escape}">
<meta property="og:url" content="{$seo_canonical|escape}">
<meta property="og:image" content="{$og_image|escape}">
<meta property="og:image:secure_url" content="{$og_image|escape}">
<meta property="og:image:type" content="{$og_image_type|escape}">
<meta property="og:image:width" content="{$og_image_width|escape}">
<meta property="og:image:height" content="{$og_image_height|escape}">
<meta property="og:image:alt" content="{$og_image_alt|escape}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$og_title|escape}">
<meta name="twitter:description" content="{$og_description|escape}">
<meta name="twitter:image" content="{$og_image|escape}">
<meta name="twitter:image:alt" content="{$og_image_alt|escape}">
<script type="application/ld+json">{$seo_jsonld}</script>
<script src="./scripts/sourcebans.js?v={$sb_js_ver|escape}"></script>
<script src="./scripts/mootools.js"></script>
<script src="./scripts/contextMenoo.js"></script>
<link href="themes/{$theme_name}/css/rubik.css" rel="stylesheet">
<link href="themes/{$theme_name}/vendors/bower_components/animate.css/animate.min.css" rel="stylesheet">
<link href="themes/{$theme_name}/vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.css" rel="stylesheet">
<link href="themes/{$theme_name}/vendors/bower_components/material-design-iconic-font/dist/css/material-design-iconic-font.min.css" rel="stylesheet">
<link href="themes/{$theme_name}/vendors/bower_components/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.min.css" rel="stylesheet">
<link href="themes/{$theme_name}/vendors/bower_components/bootstrap-select/dist/css/bootstrap-select.css" rel="stylesheet">
<link href="themes/{$theme_name}/vendors/summernote/dist/summernote.css" rel="stylesheet">
<link href="themes/{$theme_name}/css/app.min.1.css" rel="stylesheet">
<link href="themes/{$theme_name}/css/app.min.2.css" rel="stylesheet">
<link href="themes/{$theme_name}/css/css_sup.css?v={$asset_ver}" rel="stylesheet">
<link href="themes/{$theme_name}/css/dark-blue-theme.css?v={$asset_ver}" rel="stylesheet">
<link href="themes/{$theme_name}/css/ptsans.css" rel="stylesheet">
<link href="themes/{$theme_name}/css/rules.css?v={$asset_ver}" rel="stylesheet">
{if $theme_css}
<style>{$theme_css}</style>
{/if}
{$xajax_functions}
<script>window.SB_CSRF="{$sb_csrf|escape:'javascript'}";</script>
</head>
<body {$def_body_chenger}>
<header id="header" class="clearfix" {$theme_color_attr}>
<ul class="header-inner">
<li id="menu-trigger" data-trigger="#sidebar">
<div class="line-wrap">
<div class="line top"></div>
<div class="line center"></div>
<div class="line bottom"></div>
</div>
</li>
{if $header_logo == "" || $header_logo == "images/icons/logo-material-admin.svg"}
<li class="hidden-xs header-brand-wrap">
<a href="./" class="header-brand" title="На главную" aria-label="На главную Material Admin | SourceBans">
<img class="header-brand-mark" src="images/icons/logo-mark.svg" width="32" height="32" alt="">
<span class="header-brand-text">
<span class="header-brand-primary">Material Admin</span>
<span class="header-brand-sep" aria-hidden="true">|</span>
<span class="header-brand-secondary">SourceBans</span>
</span>
</a>
</li>
{else}
<li class="hidden-xs header-logo-wrap">
<a href="./" title="На главную" aria-label="На главную">
<img class="header-logo-img" src="{$header_logo|escape}" alt="{$header_title|escape}" loading="lazy">
</a>
</li>
{/if}
<li class="pull-right">
<ul class="top-menu">
{$def_ch_chenger}
{if $logged_in}
<li class="dropdown">
<a data-toggle="dropdown" href="#header-notifications" title="Уведомления" aria-label="Уведомления" role="button"><i class="tm-icon zmdi zmdi-fire"></i><span class="sr-only">Уведомления</span></a>
<ul class="dropdown-menu dm-icon pull-right" id="nav"></ul>
</li>
{/if}
<li class="dropdown">
<a data-toggle="dropdown" href="#header-search" title="Поиск по банам и мутам" aria-label="Поиск по банам и мутам" role="button"><i class="tm-icon zmdi zmdi-search"></i><span class="sr-only">Поиск по банам и мутам</span></a>
<div class="dropdown-menu dropdown-menu-lg pull-right">
<div class="listview">
<div class="lv-header bgm-bluegray c-white">Быстрый Поиск</div>
<div class="lv-body p-b-20 header-search-body">
<div class="row">
<div class="col-xs-12">
<div class="input-group input-group-lg">
<span class="input-group-addon"><i class="zmdi zmdi-globe-lock zmdi-hc-fw"></i></span>
<div class="fg-line">
<form method="get" action="index.php">
<input type="hidden" name="p" value="banlist">
<label class="sr-only" for="header_search_bans">Поиск банов</label>
<input type="text" class="form-control input-lg" id="header_search_bans" placeholder="Поиск Банов" name="searchText">
<button type="submit" class="sr-only">Искать</button>
</form>
</div>
</div>
<div class="input-group input-group-lg">
<span class="input-group-addon"><i class="zmdi zmdi-mic-setting zmdi-hc-fw"></i></span>
<div class="fg-line">
<form method="get" action="index.php">
<input type="hidden" name="p" value="commslist">
<label class="sr-only" for="header_search_comms">Поиск мутов</label>
<input type="text" class="form-control input-lg" id="header_search_comms" placeholder="Поиск Мутов" name="searchText">
<button type="submit" class="sr-only">Искать</button>
</form>
</div>
</div>
<div class="lv-item">
<div class="media">
<div class="media-body">
<div class="lv-title">Информация</div>
<small class="lv-small">Поиск выполняется по всем критериям.<br>Можете использовать: SteamID, Имя игрока...</small>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="lv-header bgm-bluegray c-white header-search-divider">Подробный Поиск</div>
<div class="lv-body header-search-body">
<div class="row">
<div class="col-xs-12">
<div class="p-t-5 p-b-10 p-r-10 p-l-10 text-center">
<button type="button" class="btn btn-primary btn-block btn-icon-text waves-effect" onclick="window.location.href='?p=search_bans'"><i class="zmdi zmdi-lock-outline"></i>Баны</button>
<button type="button" class="btn btn-warning btn-block btn-icon-text waves-effect" onclick="window.location.href='?p=search_comm'"><i class="zmdi zmdi-mic-off"></i>Муты</button>
</div>
</div>
</div>
</div>
</div>
</div>
</li>
<li id="chat-trigger" data-trigger="#chat"{if $supports_count == 0} class="hidden"{/if}>
<a href="#chat" title="Чат с администраторами онлайн" aria-label="Чат с администраторами онлайн"><i class="tm-icon zmdi zmdi-comment-alt-text"></i><span class="sr-only">Чат с администраторами онлайн</span></a>
</li>
</ul>
</li>
</ul>
</header>
<section id="main" data-layout="layout-1">
<aside id="chat" class="sidebar c-overflow" aria-label="Онлайн администраторы">
{if $supports_count > 0}
<div class="chat-seach">
<div class="fg-line p-10">
<h4>Администраторы</h4>
</div>
</div>
<div class="listview">
{foreach from=$supports_list item=supp}
<a class="lv-item" href="https://steamcommunity.com/profiles/{$supp.authid}" target="_blank" rel="noopener noreferrer" title="Профиль {$supp.user|escape}">
<div class="media">
<div class="pull-left p-relative">
<img class="lv-img-sm" src="{$supp.avatarka|escape}" alt="{$supp.user|escape}">
<i class="chat-status-online"></i>
</div>
<div class="media-body">
<div class="lv-title"><span class="header-link-accent">{$supp.user|escape}</span></div>
<small class="lv-small">{if NOT empty($supp.vk) AND NOT empty($supp.discord)}({/if}{if NOT empty($supp.vk)}<span class="header-link-accent">VK</span>{/if} {if NOT empty($supp.vk) AND NOT empty($supp.discord)}/{/if} {if NOT empty($supp.discord)}<span class="header-link-accent">DISCORD: {$supp.discord|escape}</span>{/if}{if NOT empty($supp.vk) AND NOT empty($supp.discord)}){/if} {if NOT empty($supp.comment)}- {$supp.comment|escape}{/if}</small>
</div>
</div>
</a>
{/foreach}
</div>
{/if}
<div id="chat_aut"></div>
</aside>
<nav id="sidebar" class="sidebar c-overflow" aria-label="Главное меню">
<div class="profile-menu">
<a href="{if $logged_in}index.php?p=account{else}index.php?p=login{/if}" title="{if $logged_in}Профиль{else}Авторизация{/if}">
<div class="profile-pic">
<img src="{$avatar|escape}" alt="{if $logged_in}{$username|escape}{else}Гость{/if}">
</div>
<div class="profile-info">
{if $logged_in}<span class="profile-info-label">Аккаунт</span> <mark>{$username|escape}</mark>{else}Гость{/if}
<i class="zmdi zmdi-caret-down"></i>
</div>
</a>
<ul class="main-menu">
{if $logged_in}
<li><a href="index.php?p=account"><i class="zmdi zmdi-settings"></i> Профиль</a></li>
<li><a href="index.php?p=logout"><i class="zmdi zmdi-time-restore"></i> Выход</a></li>
{else}
<li><a href="index.php?p=login"><i class="zmdi zmdi-input-antenna"></i> Авторизация</a></li>
{if $vay4er_act == "1"}
<li><a href="index.php?p=pay"><i class="zmdi zmdi-shopping-cart-plus"></i> Активировать ваучер</a></li>
{/if}
{/if}
</ul>
</div>
<ul class="main-menu">
