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

if(!defined("IN_SB")){echo "Ошибка доступа!";die();} 
global $theme;

$first = true; 
$i=0;
$tabs = array();
foreach($var AS $v)
{ 
	if(empty($v['title']))
	{
		$i++; continue;
	} 
	if($first) 
		$GLOBALS['enable'] = $v['id']; 
	$hrefAttr = '#';
	$click = '';
	if(isset($v['external']) && $v['external'] == true) 
	{
		$lnk = (string)$v['url'];
		// javascript:… → чистый JS в onclick; обычные URL — sbGo (location не видит <base href>).
		if ($lnk !== '' && stripos($lnk, 'javascript:') === 0)
		{
			$js = substr($lnk, strlen('javascript:'));
			$js = rtrim($js, " \t;");
			$click = $js . ';return false;';
			$hrefAttr = '#';
		}
		else
		{
			$click = "if(typeof sbGo==='function'){sbGo('".addslashes($lnk)."');return false;}";
			$hrefAttr = htmlspecialchars($lnk, ENT_QUOTES, 'UTF-8');
		}
	} 
	else 
	{
		// С <base href="/"> голый #^N уезжает на главную. Префикс — текущий путь страницы.
		$tabBase = '';
		if (function_exists('sb_url')) {
			$tp = isset($_GET['p']) ? (string)$_GET['p'] : 'admin';
			$textra = array();
			if (!empty($_GET['c']))
				$textra['c'] = (string)$_GET['c'];
			$tabBase = sb_url($tp, $textra);
			if ($tabBase === './')
				$tabBase = '';
			// Сохраняем query (ppage/spage/page/…) — иначе клик по вкладке сбрасывает пагинацию
			// и обработчик #^ считает URL «другим» и уводит на путь без ?page=.
			if (!empty($_SERVER['QUERY_STRING'])) {
				parse_str((string)$_SERVER['QUERY_STRING'], $tabQs);
				unset($tabQs['p'], $tabQs['c']);
				if (!empty($tabQs)) {
					$tabBase .= (strpos($tabBase, '?') !== false ? '&' : '?') . http_build_query($tabQs);
				}
			}
		}
		$hrefAttr = htmlspecialchars($tabBase . "#^" . $v['id'], ENT_QUOTES, 'UTF-8');
		$click = "SwapPane(". (int)$v['id'] .");";
	} 
	if($i == 0) 
		$class = "active"; 
	else 
		$class = "";
	$itm = array();
	$itm['tab'] = "<li id='tab-". (int)$v['id'] . "' class='" . $class . "'><a href='".$hrefAttr."' id='admin_tab_".(int)$v['id']."' onclick=\"".$click."\"> " . htmlspecialchars($v['title'], ENT_QUOTES, 'UTF-8') . "</a></li>";
	array_push($tabs, $itm) ;
	$i++;
	$first=false;
}

//if($_GET['p'] == "account")
	//$theme->assign('pane_image','<img src="themes/' . SB_THEME . '/images/admin/your_account.png"> </div>') ;
//else 
	//$theme->assign('pane_image', '<img src="themes/' . SB_THEME . '/images/admin/'.  $_GET['c'] . '.png"> </div>');
	
$theme->assign('tabs', $tabs);

$theme->display('item_admin_tabs.tpl');
