<?php
if (!defined('IN_SB')) {
	echo 'Ошибка доступа!';
	die();
}

if (!function_exists('sb_ui_v2_render')) {
	echo 'UI v2 не подключен.';
	return;
}

sb_ui_v2_render('preview_banlist.twig', array(
	'title' => 'Макет банлиста — Blue Admin v2',
	'login_url' => 'index.php?p=login',
	'rows' => array(
		array(
			'player' => 'xXx_ProGamer_xXx',
			'steam' => 'STEAM_0:1:23456789',
			'reason' => 'Читы / Aimbot',
			'admin' => 'Lappland',
			'length' => 'Навсегда',
			'date' => '06.08.2026 18:12',
			'status' => 'Permanent',
			'badge' => 'perm',
		),
		array(
			'player' => 'ToxicIvan',
			'steam' => 'STEAM_0:0:99887766',
			'reason' => 'Оскорбления голосовым',
			'admin' => 'Admin#2',
			'length' => '7 дней',
			'date' => '05.08.2026 21:40',
			'status' => 'Active',
			'badge' => 'temp',
		),
		array(
			'player' => 'FriendlyFire_Fan',
			'steam' => 'STEAM_0:1:11223344',
			'reason' => 'Grief / team kill',
			'admin' => 'Lappland',
			'length' => '1 день',
			'date' => '01.08.2026 11:05',
			'status' => 'Expired',
			'badge' => 'ok',
		),
	),
));
