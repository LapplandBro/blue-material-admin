

<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {help_icon title="gaben" message="hello"} function plugin
 *
 * Type:     function<br>
 * Name:     help tip<br>
 * Purpose:  show help tip
 * @link http://www.sourcebans.net
 * @author  SourceBans Development Team
 * @param array
 * @param Smarty
 * @return string
 */
function smarty_function_help_icon($params, &$smarty)
{
	$style = isset($params['style']) ? $params['style'] : '';
	$msg = isset($params['message']) ? htmlspecialchars((string)$params['message'], ENT_QUOTES, 'UTF-8') : '';
	$title = isset($params['title']) ? htmlspecialchars((string)$params['title'], ENT_QUOTES, 'UTF-8') : '';
	$styleAttr = $style !== '' ? ' style="' . htmlspecialchars((string)$style, ENT_QUOTES, 'UTF-8') . '"' : '';
	// title= обязателен для Bootstrap popover; container=body — иначе клип родителей (карточки/overflow).
	return '<img class="sb-ico sb-ico-help" src="images/icons/help.svg" width="18" height="18" alt="Справка"'
		. $styleAttr
		. ' tabindex="0" role="button"'
		. ' data-toggle="popover" data-trigger="hover focus" data-placement="top" data-container="body"'
		. ' title="' . $title . '" data-content="' . $msg . '">';
}
