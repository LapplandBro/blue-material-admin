<?php
if (!defined('IN_SB')) {
	echo 'You should not be here. Only follow links!';
	die();
}

/**
 * JSON-ответ админских/публичных AJAX-действий.
 * Методы совместимы с прежними вызовами addScript/addAssign/…,
 * но в браузер уходит application/json, а не XML xajax.
 */
class SbJsonResponse
{
	public $aCommands = array();

	public function addAssign($sTarget, $sAttribute, $sData)
	{
		$this->aCommands[] = array('n' => 'as', 't' => (string)$sTarget, 'p' => (string)$sAttribute, 'data' => $sData);
		return $this;
	}

	public function assign($sTarget, $sAttribute, $sData)
	{
		return $this->addAssign($sTarget, $sAttribute, $sData);
	}

	public function addAppend($sTarget, $sAttribute, $sData)
	{
		$this->aCommands[] = array('n' => 'ap', 't' => (string)$sTarget, 'p' => (string)$sAttribute, 'data' => $sData);
		return $this;
	}

	public function append($sTarget, $sAttribute, $sData)
	{
		return $this->addAppend($sTarget, $sAttribute, $sData);
	}

	public function addPrepend($sTarget, $sAttribute, $sData)
	{
		$this->aCommands[] = array('n' => 'pp', 't' => (string)$sTarget, 'p' => (string)$sAttribute, 'data' => $sData);
		return $this;
	}

	public function prepend($sTarget, $sAttribute, $sData)
	{
		return $this->addPrepend($sTarget, $sAttribute, $sData);
	}

	public function addClear($sTarget, $sAttribute)
	{
		return $this->addAssign($sTarget, $sAttribute, '');
	}

	public function clear($sTarget, $sAttribute)
	{
		return $this->addClear($sTarget, $sAttribute);
	}

	public function addRemove($sTarget)
	{
		$this->aCommands[] = array('n' => 'rm', 't' => (string)$sTarget, 'data' => '');
		return $this;
	}

	public function remove($sTarget)
	{
		return $this->addRemove($sTarget);
	}

	public function addScript($sJS)
	{
		$this->aCommands[] = array('n' => 'js', 'data' => (string)$sJS);
		return $this;
	}

	public function script($sJS)
	{
		return $this->addScript($sJS);
	}

	public function addScriptCall()
	{
		$aArgs = func_get_args();
		$sFunc = (string)array_shift($aArgs);
		$this->aCommands[] = array('n' => 'jc', 't' => $sFunc, 'data' => array_values($aArgs));
		return $this;
	}

	public function call()
	{
		$aArgs = func_get_args();
		return call_user_func_array(array($this, 'addScriptCall'), $aArgs);
	}

	public function addAlert($sMsg)
	{
		$msg = (string)$sMsg;
		$title = 'Сообщение';
		$color = 'blue';
		if (preg_match('/ошибк|error|запрещ|не найден|нельзя|отказано|выключено/iu', $msg)) {
			$title = 'Ошибка';
			$color = 'red';
		} elseif (preg_match('/успешн|изменен|добавлен|готово/iu', $msg)) {
			$title = 'Успех';
			$color = 'green';
		}
		$html = nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'), false);
		$js = 'if(typeof ShowBox==="function"){ShowBox('
			. sb_ajax_json_encode($title)
			. ',' . sb_ajax_json_encode($html)
			. ',' . sb_ajax_json_encode($color)
			. ',"",true);}else{alert(' . sb_ajax_json_encode($msg) . ');}';
		return $this->addScript($js);
	}

	public function alert($sMsg)
	{
		return $this->addAlert($sMsg);
	}

	public function addRedirect($sURL, $iDelay = 0)
	{
		$u = sb_ajax_json_encode((string)$sURL);
		if ($iDelay)
			$this->addScript('window.setTimeout(function(){var u=' . $u . ';if(typeof sbGo==="function")sbGo(u);else if(typeof sbAbs==="function")window.location.href=sbAbs(u);else window.location.href=u;},' . ((int)$iDelay * 1000) . ');');
		else
			$this->addScript('(function(){var u=' . $u . ';if(typeof sbGo==="function")sbGo(u);else if(typeof sbAbs==="function")window.location.href=sbAbs(u);else window.location.href=u;})();');
		return $this;
	}

	public function redirect($sURL, $iDelay = 0)
	{
		return $this->addRedirect($sURL, $iDelay);
	}

	public function addIncludeScript($sFileName)
	{
		$this->aCommands[] = array('n' => 'in', 'data' => (string)$sFileName);
		return $this;
	}

	public function includeScript($sFileName)
	{
		return $this->addIncludeScript($sFileName);
	}

	public function loadXML($mCommands)
	{
		return $this->loadCommands($mCommands);
	}

	public function loadCommands($mCommands)
	{
		if ($mCommands instanceof SbJsonResponse) {
			$this->aCommands = array_merge($this->aCommands, $mCommands->aCommands);
		} elseif (is_array($mCommands)) {
			$this->aCommands = array_merge($this->aCommands, $mCommands);
		}
		return $this;
	}

	public function getXML()
	{
		return $this;
	}

	public function getOutput()
	{
		return sb_ajax_json_encode(array(
			'ok' => true,
			'cmds' => $this->aCommands,
		));
	}
}

class_alias('SbJsonResponse', 'xajaxResponse');

/**
 * Диспетчер JSON AJAX. Заменяет xajax 0.2.5.
 */
class SbAjax
{
	public $aFunctions = array();
	public $sRequestURI = './index.php';

	public function setRequestURI($uri)
	{
		$this->sRequestURI = (string)$uri;
	}

	public function registerFunction($name)
	{
		$name = (string)$name;
		if ($name !== '')
			$this->aFunctions[$name] = true;
	}

	public function processRequest()
	{
		return $this->processRequests();
	}

	public function processRequests()
	{
		$parsed = sb_ajax_parse_request();
		if ($parsed === null)
			return;

		$action = $parsed['action'];
		$args = $parsed['args'];

		$csrf_exempt = array(
			'ServerHostPlayers',
			'ServerHostProperty',
			'ServerHostPlayers_list',
			'ServerPlayers',
			'RefreshServer',
			'PingSession',
		);
		if (function_exists('sb_csrf_validate') && !in_array($action, $csrf_exempt, true)) {
			if (function_exists('sb_session_start'))
				sb_session_start();
			elseif (session_status() !== PHP_SESSION_ACTIVE)
				@session_start();
			$token = '';
			if (!empty($_SERVER['HTTP_X_SB_CSRF']))
				$token = (string)$_SERVER['HTTP_X_SB_CSRF'];
			elseif (isset($_POST['sb_csrf']))
				$token = (string)$_POST['sb_csrf'];
			elseif (isset($_GET['sb_csrf']))
				$token = (string)$_GET['sb_csrf'];
			elseif (isset($parsed['csrf']))
				$token = (string)$parsed['csrf'];
			if (!sb_csrf_validate($token)) {
				$msg = 'Страница открыта слишком долго — защитный токен устарел. Данные не сохранены. Нажмите «Обновить страницу» и повторите действие.';
				sb_ajax_emit(array(
					'ok' => false,
					'error' => $msg,
					'cmds' => array(array(
						'n' => 'js',
						'data' => 'if(typeof sbCsrfExpired==="function"){sbCsrfExpired(' . sb_ajax_json_encode($msg) . ');}'
							. 'else if(typeof ShowBox==="function"){ShowBox("Сессия устарела",' . sb_ajax_json_encode($msg) . ',"red","",false);}'
							. 'else if(confirm(' . sb_ajax_json_encode($msg . "\n\nОбновить страницу?") . ')){location.reload();}',
					)),
				));
			}
		}

		$obj = new SbJsonResponse();
		if (!isset($this->aFunctions[$action]) || !function_exists($action)) {
			$obj->addAlert('Неизвестное действие: ' . $action);
			sb_ajax_emit_response($obj);
		}

		ob_start();
		try {
			$result = call_user_func_array($action, $args);
			$noise = ob_get_clean();
			if ($noise !== '' && $noise !== false && class_exists('CSystemLog', false)) {
				new CSystemLog('w', 'AJAX output', $action . ': ' . substr(preg_replace('/\s+/', ' ', (string)$noise), 0, 400));
			}
			if ($result instanceof SbJsonResponse)
				$obj = $result;
			elseif (is_object($result) && method_exists($result, 'getOutput'))
				$obj = $result;
		} catch (Throwable $e) {
			if (ob_get_level() > 0)
				@ob_end_clean();
			$obj = new SbJsonResponse();
			$obj->addAlert('Ошибка: ' . $e->getMessage());
		}

		sb_ajax_emit_response($obj);
	}

	public function printJavascript($sJsURI = '', $sJsFile = null)
	{
		return $this->getJavascript($sJsURI, $sJsFile);
	}

	public function getJavascript($sJsURI = '', $sJsFile = null)
	{
		if ($sJsURI !== '' && substr($sJsURI, -1) !== '/')
			$sJsURI .= '/';
		$jsFile = 'sb-api.js';
		$ver = '';
		$full = (defined('ROOT') ? ROOT : '') . 'scripts/sb-api.js';
		if (is_file($full))
			$ver = '?v=' . filemtime($full);
		$names = array_keys($this->aFunctions);
		$html = "\t<script src=\"" . htmlspecialchars($sJsURI . $jsFile . $ver, ENT_QUOTES, 'UTF-8') . "\"></script>\n";
		$html .= "\t<script>\n";
		$html .= "window.SB_AJAX_URI=" . sb_ajax_json_encode($this->sRequestURI) . ";\n";
		$html .= "if(window.sbApi&&typeof sbApi.register==='function')sbApi.register(" . sb_ajax_json_encode($names) . ");\n";
		$html .= "else if(typeof ShowBox==='function')ShowBox('Ошибка','Не загрузился scripts/sb-api.js','red','',true);\n";
		$html .= "\t</script>\n";
		return $html;
	}
}

function sb_ajax_json_flags()
{
	$flags = JSON_UNESCAPED_UNICODE;
	if (defined('JSON_UNESCAPED_SLASHES'))
		$flags |= JSON_UNESCAPED_SLASHES;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE'))
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
	return $flags;
}

function sb_ajax_json_encode($data)
{
	$json = json_encode($data, sb_ajax_json_flags());
	return $json !== false ? $json : '{"ok":false,"error":"json_encode"}';
}

function sb_ajax_parse_request()
{
	$ctype = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
	if (isset($_SERVER['HTTP_CONTENT_TYPE']) && $ctype === '')
		$ctype = $_SERVER['HTTP_CONTENT_TYPE'];

	if (stripos($ctype, 'application/json') !== false) {
		$raw = file_get_contents('php://input');
		$in = json_decode($raw, true);
		if (!is_array($in) || empty($in['action']))
			return null;
		$action = (string)$in['action'];
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $action))
			return null;
		$args = array();
		if (isset($in['args']) && is_array($in['args']))
			$args = array_values($in['args']);
		$csrf = isset($in['csrf']) ? (string)$in['csrf'] : '';
		return array('action' => $action, 'args' => $args, 'csrf' => $csrf);
	}

	$key = '';
	$bag = null;
	if (!empty($_POST['sb_ajax'])) {
		$key = (string)$_POST['sb_ajax'];
		$bag = $_POST;
	} elseif (!empty($_GET['sb_ajax'])) {
		$key = (string)$_GET['sb_ajax'];
		$bag = $_GET;
	}
	if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key))
		return null;
	$args = array();
	if (isset($bag['sb_ajax_args']) && is_array($bag['sb_ajax_args']))
		$args = array_values($bag['sb_ajax_args']);
	$csrf = isset($bag['sb_csrf']) ? (string)$bag['sb_csrf'] : '';
	return array('action' => $key, 'args' => $args, 'csrf' => $csrf);
}

function sb_ajax_emit($payload)
{
	while (ob_get_level() > 0)
		@ob_end_clean();
	if (!headers_sent()) {
		header('HTTP/1.1 200 OK');
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('X-Content-Type-Options: nosniff');
	}
	echo sb_ajax_json_encode($payload);
	exit();
}

function sb_ajax_emit_response($obj)
{
	if (!($obj instanceof SbJsonResponse) && is_object($obj) && method_exists($obj, 'getOutput')) {
		while (ob_get_level() > 0)
			@ob_end_clean();
		if (!headers_sent()) {
			header('HTTP/1.1 200 OK');
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('X-Content-Type-Options: nosniff');
		}
		echo $obj->getOutput();
		exit();
	}
	if (!($obj instanceof SbJsonResponse))
		$obj = new SbJsonResponse();
	sb_ajax_emit(array(
		'ok' => true,
		'cmds' => $obj->aCommands,
	));
}
