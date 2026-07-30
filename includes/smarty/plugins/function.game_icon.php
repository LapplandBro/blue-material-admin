<?php
/**
 * Smarty {game_icon file="csgo.png" size=18 alt="Игра"}
 * Рендерит SVG/PNG иконку мода через sb_game_icon_html().
 */
function smarty_function_game_icon($params, &$smarty)
{
	$file = isset($params['file']) ? $params['file'] : 'web.png';
	$alt = isset($params['alt']) ? $params['alt'] : 'Игра';
	$size = isset($params['size']) ? (int)$params['size'] : 18;
	if (!function_exists('sb_game_icon_html'))
		return '';
	return sb_game_icon_html($file, $alt, $size);
}
