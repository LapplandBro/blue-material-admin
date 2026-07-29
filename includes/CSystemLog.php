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

class CSystemLog {
	var $log_list = array();
	var $type = "";
	var $title = "";
	var $msg = "";
	var $aid = 0;
	var $host = "";
	var $created = 0;
	var $parent_function = "";
	var $query = "";
	
	function __construct($tpe="", $ttl="", $mg="", $done=true, $HideDebug = false)
	{
		global $userbank;
		if(!empty($tpe) && !empty($ttl) && !empty($mg))
		{
			$this->type = $tpe;
			$this->title = $ttl;
			$this->msg = $mg;
			// if (!$HideDebug && ((isset($_GET['debug']) && $_GET['debug'] == 1) || defined("DEVELOPER_MODE")))
			// {
				// echo "CSystemLog: " . $mg;
			// }
			
			if( !$userbank )
				return false;
			
			$this->aid =  $userbank->GetAid()?$userbank->GetAid():"-1";
			$this->host = $_SERVER['REMOTE_ADDR'];
			$this->created = time(); 
			$this->parent_function = $this->_getCaller();
			$this->query = $this->SanitizeSensitive(isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'');
			$this->title = $this->SanitizeSensitive($this->title);
			$this->msg = $this->SanitizeSensitive($this->msg);
			if(isset($done) && $done == true)
				$this->WriteLog();
		}				
	}
	
	function AddLogItem($tpe, $ttl, $mg)
	{
		$item = array();
		$item['type'] = $tpe;
		$item['title'] = $this->SanitizeSensitive($ttl);
		$item['msg'] = $this->SanitizeSensitive($mg);
		$item['aid'] =  SB_AID;
		$item['host'] = $_SERVER['REMOTE_ADDR'];
		$item['created'] = time(); 
		$item['parent_function'] = $this->_getCaller();
		$item['query'] = $this->SanitizeSensitive(isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'');
		
		array_push($this->log_list, $item);
	}
	
	function WriteLogEntries()
	{
		$this->log_list = array_unique($this->log_list);
		foreach($this->log_list as $logentry)
		{
			if(!$logentry['query'])
				$logentry['query'] = "N/A";
			if(isset($GLOBALS['db']))
			{
				$sm_log_entry = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_log(type,title,message, function, query, aid, host, created)
						VALUES (?,?,?,?,?,?,?,?)");
				$GLOBALS['db']->Execute($sm_log_entry,array($logentry['type'], $logentry['title'], $logentry['msg'], (string)$logentry['parent_function'],$logentry['query'], $logentry['aid'], $logentry['host'], $logentry['created']));
			}
		}
		unset($this->log_list);
	}
	
	function WriteLog()
	{
		if(!$this->query)
			$this->query = "N/A";
		if(isset($GLOBALS['db']))
		{
			$sm_log_entry = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_log(type,title,message, function, query, aid, host, created)
						VALUES (?,?,?,?,?,?,?,?)");
			$GLOBALS['db']->Execute($sm_log_entry,array($this->type, $this->title, $this->msg, (string)$this->parent_function,$this->query, $this->aid, $this->host, $this->created));
		}
	}
	
	function _getCaller()
	{
		$bt = debug_backtrace();
	
		$functions = "";
		$count = count($bt);
		for ($idx = 2; $idx<$count; $idx++)
			if ($bt[$idx]['function'] != "sbError")
				$functions .= "<b>". ($count-$idx) . "</b>: " . str_replace(ROOT, "/", $bt[$idx]['file']) . "::".$bt[$idx]['function']."(".$this->FormatArguments(isset($bt[$idx]['args'])?$bt[$idx]['args']:array(), $bt[$idx]['function']).") - " . $bt[$idx]['line'] . "<br />\n";
		return $this->SanitizeSensitive($functions);
	}

	/**
	 * Убирает из логов пароли, CSRF-токены (key=...), RCON и прочие секреты.
	 * CSRF/пароли раньше попадали сюда через QUERY_STRING и стек аргументов функций.
	 */
	function SanitizeSensitive($text)
	{
		if ($text === null || $text === '')
			return $text;
		$text = (string)$text;
		// query-string / url: key=, password=, csrf=, token=, rcon=, validation= ...
		$text = preg_replace(
			'/((?:^|[?&\'"\s]|\\?|&amp;)(?:password|passwd|pass|pwd|password2|a_password|a_password2|srv_password|authpassword|rcon|rcon2|csrf|token|key|validation|hash|secret)=)([^&\'"\s<>]+)/i',
			'$1[REDACTED]',
			$text
		);
		// аргументы в стеке: 'секрет' рядом с чувствительными именами функций уже гасятся в FormatArgument,
		// но на всякий случай длинные hex/base64-подобные токены в кавычках:
		$text = preg_replace("/'([A-Fa-f0-9]{16,}|[A-Za-z0-9+\\/=]{24,})'/", "'[REDACTED]'", $text);
		return $text;
	}

	function IsSensitiveFunction($funcName)
	{
		return (bool)preg_match('/password|passwd|login|rcon|csrf|token|auth|credential|secret|validation/i', (string)$funcName);
	}
	
	function GetAll($start, $limit, $searchstring="")
	{
		if( !is_object($GLOBALS['db']) )
				return false;
				
		$start = (int)$start;
		$limit = (int)$limit;
		$sm_logs = $GLOBALS['db']->GetAll("SELECT ad.user, l.type, l.title, l.message, l.function, l.query, l.host, l.created, l.aid 
										   FROM ".DB_PREFIX."_log AS l
										   LEFT JOIN ".DB_PREFIX."_admins AS ad ON l.aid = ad.aid
										   ".$searchstring."
										   ORDER BY l.created DESC 
										   LIMIT $start, $limit");
		return $sm_logs;
	}
	
	function LogCount($searchstring="")
	{
		$sm_logs = $GLOBALS['db']->GetRow("SELECT count(l.lid) AS count FROM ".DB_PREFIX."_log AS l".$searchstring);
		return $sm_logs[0];
	}
	
	function CountLogList()
	{
		return count($this->log_list);
	}
	
	/* Log Helpers for args logger */
	function FormatArguments($args, $funcName = '') {
		$argsV2 = array();
		foreach ($args as $arg)
			$argsV2[] = $this->FormatArgument($arg, $funcName);
		return implode(", ", $argsV2);
	}
	
	function GetEntryType($entry) {
		$type = gettype($entry);
		if ($type == "boolean") return 4;
		if ($type == "integer" || $type == "double") return 1;
		if ($type == "string") return 0;
		if ($type == "array") return 2;
		if ($type == "NULL") return 5;
		if ($type == "object") return 3;
		return -1;
	}
	
	function FormatArgument($arg, $funcName = '') {
		$et = $this->GetEntryType($arg);
		if ($et == 0) {
			// В чувствительных функциях (Plogin, ChangePassword, SendRcon...) строковые
			// аргументы почти всегда содержат пароль/токен — не пишем их в лог.
			if ($this->IsSensitiveFunction($funcName) && $arg !== '' && $arg !== null) {
				return "'[REDACTED]'";
			}
			// CSRF-токены и хеши часто выглядят как длинные hex/base64.
			if (preg_match('/^[A-Fa-f0-9]{16,}$/', $arg) || preg_match('/^[A-Za-z0-9+\\/=]{24,}$/', $arg)) {
				return "'[REDACTED]'";
			}
		}
		if ($et == 2) {
			$log = htmlentities($this->PrepareArray($arg, $funcName));
		} else if ($et == 0) {
			$log = htmlentities("'".$arg."'");
		} else if ($et == 3) {
			$log = htmlentities(sprintf("Object %s", get_class($arg)));
		} else {
			$log = htmlentities((string)$arg);
		}
		
		if (strlen($log) > 256)
			$log = sprintf("%s...%s", substr($log, 0, 256), ($et==0)?"'":"");
		
		return $log;
	}
	
	function PrepareArray($array, $funcName = '') {
		if (gettype($array) != "array") return $array;
		$result = "[";
		foreach ($array as $Key => $Entry) {
			// Ключи массива тоже могут быть password/key — маскируем значения.
			if (is_string($Key) && preg_match('/password|passwd|pass|pwd|rcon|csrf|token|key|secret|validation/i', $Key)) {
				$result .= "'[REDACTED]'";
			} else {
				$result .= $this->FormatArgument($Entry, $funcName);
			}
			$result .= ", ";
		}
		return str_replace(", ]", "]", $result."]");
	}
}
