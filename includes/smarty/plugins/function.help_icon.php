

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
	$style = isset($params['style'])?$params['style']:"";
	$msg = isset($params['message']) ? $params['message'] : '';
	$title = isset($params['title']) ? $params['title'] : '';
	return '<img class="sb-ico sb-ico-help" src="images/icons/help.svg" width="18" height="18" alt="Справка" style="float:left;margin-right:6px;'.$style.'" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="' .  $msg . '" title="" data-original-title="' .  $title . '">';
}
