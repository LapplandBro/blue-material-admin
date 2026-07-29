<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {sb_button text="Login" onclick=$redir class="ok" id="alogin" submit=false} function plugin
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
function smarty_function_sb_button($params, &$smarty) //$text, $click, $class, $id="", $submit=false
{
	$text = isset($params['text'])?$params['text']:"";
	$click = isset($params['onclick'])?$params['onclick']:"";
	$class = isset($params['class'])?$params['class']:"";
	$id = isset($params['id'])?$params['id']:"";
	$icon = isset($params['icon'])?$params['icon']:"";
	$submit = isset($params['submit'])?$params['submit']:"";

	$type = $submit ? "submit" : "button";
	if (function_exists('sb_normalize_html_attr_quotes'))
		$icon = sb_normalize_html_attr_quotes($icon);
	else
		$icon = preg_replace('/\b(class|href|id)=\'([^\']*)\'/', '$1="$2"', $icon);

	$idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
	$classEsc = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
	$textEsc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	$clickEsc = htmlspecialchars($click, ENT_QUOTES, 'UTF-8');
	$button = '<button type="'.$type.'" onclick="'.$clickEsc.'" name="'.$idEsc.'" class="btn '.$classEsc.' waves-effect" onmouseover="ButtonOver(\''.$idEsc.'\')" onmouseout="ButtonOver(\''.$idEsc.'\')" id="'.$idEsc.'" value="'.$textEsc.'">'.$icon.$text.'</button>';
	return $button;
}
