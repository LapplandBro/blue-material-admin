<?php
if (!defined('IN_SB')) {
	echo 'Ошибка доступа!';
	die();
}

/**
 * Мешок переменных вместо Smarty на живом сайте.
 * Страницы по-прежнему делают $theme->assign(); Blue V2 читает _tpl_vars.
 * display() не компилирует шаблоны — живой UI только Twig.
 */
class CThemeBag
{
	var $_tpl_vars = array();
	var $left_delimiter = '{';
	var $right_delimiter = '}';
	var $error_reporting = 0;
	var $use_sub_dirs = false;
	var $compile_id = '';
	var $caching = false;
	var $template_dir = '';
	var $force_compile = false;

	function assign($tpl_var, $value = null)
	{
		if (is_array($tpl_var)) {
			foreach ($tpl_var as $key => $val) {
				if ($key !== '')
					$this->_tpl_vars[$key] = $val;
			}
			return;
		}
		if ($tpl_var !== '')
			$this->_tpl_vars[$tpl_var] = $value;
	}

	function getTemplateVars($name = null)
	{
		if ($name === null)
			return $this->_tpl_vars;
		return isset($this->_tpl_vars[$name]) ? $this->_tpl_vars[$name] : null;
	}

	function get_template_vars($name = null)
	{
		return $this->getTemplateVars($name);
	}

	function display($tpl)
	{
		@error_log('CThemeBag::display skipped (Blue V2, no Smarty): ' . (string)$tpl);
	}

	function clear_compiled_tpl($tpl_file = null, $compile_id = null, $exp_time = null)
	{
		return true;
	}
}
