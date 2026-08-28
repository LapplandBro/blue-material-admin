<?php
if (!defined('IN_SB')) {
	echo 'Ошибка доступа!';
	die();
}

/**
 * Мешок переменных для страниц: $theme->assign() → _tpl_vars для Twig (Blue V2).
 */
class CThemeBag
{
	var $_tpl_vars = array();

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
}
