<?php 
if(!defined("IN_SB"))
{
	echo "Ошибка доступа!";
	die();
}
$GLOBALS['TitleRewrite'] = "Подробный поиск банов";

if (function_exists('sb_ui_v2_enabled') && sb_ui_v2_enabled() && function_exists('sb_ui_v2_render')) {
	ob_start();
	require(TEMPLATES_PATH . "/admin.bans.search.php");
	$inner = ob_get_clean();
	sb_ui_v2_render('search_bans_page.twig', array(
		'title' => 'Подробный поиск банов',
		'nav_active' => 'search_bans',
		'inner_html' => $inner,
	));
	return;
}

require(TEMPLATES_PATH . "/admin.bans.search.php"); //Set theme vars from servers page
?>
