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

class CUserManager
{
	var $aid = -1;
	var $admins = array();
	
	/**
	 * Class constructor
	 *
	 * @param $aid the current user's aid
	 * @param $password the current user's password
	 * @return noreturn.
	 */
	function __construct($aid, $password)
	{
		if($this->CheckLogin($password, $aid))
		{
			$this->aid = $aid;
			$this->GetUserArray($aid);
		}
		else 
			$this->aid = -1;
	}
	
	
	/**
	 * Gets all user details from the database, saves them into
	 * the admin array 'cache', and then returns the array
	 *
	 * @param $aid the ID of admin to get info for.
	 * @return array.
	 */
	function GetUserArray($aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;	
		// Invalid aid
		if($aid < 0 || empty($aid))
			return 0;
		
		$aid = (int)$aid;
		// We already got the data from the DB, and its saved in the manager
		if(isset($this->admins[$aid]) && !empty($this->admins[$aid]))
			return $this->admins[$aid];
		// Not in the manager, so we need to get them from DB
		$res = $GLOBALS['db']->GetRow("SELECT adm.user user, adm.authid authid, adm.password password, adm.gid gid, adm.email email, adm.validate validate, adm.extraflags extraflags, 
									   adm.immunity admimmunity,sg.immunity sgimmunity, adm.srv_password srv_password, adm.srv_group srv_group, adm.srv_flags srv_flags,sg.flags sgflags,
									   wg.flags wgflags, wg.name wgname, adm.lastvisit lastvisit, adm.expired expired, adm.discord discord, adm.comment comment, adm.vk vk
									   FROM " . DB_PREFIX . "_admins AS adm
									   LEFT JOIN " . DB_PREFIX . "_groups AS wg ON adm.gid = wg.gid
									   LEFT JOIN " . DB_PREFIX . "_srvgroups AS sg ON adm.srv_group = sg.name
									   WHERE adm.aid = $aid");
		
		if(!$res)	
			return 0;  // ohnoes some type of db error
		
		$user = array();	
		//$user['user'] = stripslashes($res[0]);
		$user['aid'] = $aid; //immediately obvious
		$user['user'] = $res['user'];
		$user['authid'] = $res['authid'];	
		$user['password'] = $res['password'];
		$user['gid'] = $res['gid'];
		$user['email'] = $res['email'];
		$user['validate'] = $res['validate'];
		$user['extraflags'] = (intval($res['extraflags']) | intval($res['wgflags']));

		if(intval($res['admimmunity']) > intval($res['sgimmunity']))
			$user['srv_immunity'] = intval($res['admimmunity']);
		else 
			$user['srv_immunity'] = intval($res['sgimmunity']);

		$user['srv_password'] = $res['srv_password'];
		$user['srv_groups'] = $res['srv_group'];
		$user['srv_flags'] = $res['srv_flags'] . $res['sgflags'];
		$user['group_name'] = $res['wgname'];
		$user['lastvisit'] = $res['lastvisit'];
		$user['expired'] = $res['expired'];
		$user['discord'] = $res['discord'];
		$user['comment'] = $res['comment'];
		$user['vk'] = $res['vk'];
		$this->admins[$aid] = $user;
		return $user;
	}

	
	/**
	 * Will check to see if an admin has any of the flags given
	 *
	 * @param $flags The flags to check for.
	 * @param $aid The user to check flags for.
	 * @return boolean.
	 */
	function HasAccess($flags, $aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
			
		if(empty($flags) || $aid <= 0)
			return false;
		
		$aid = (int)$aid;
		if(is_numeric($flags))
		{
			if(!isset($this->admins[$aid]))
				$this->GetUserArray($aid);
			return ($this->admins[$aid]['extraflags'] & $flags) != 0 ? true : false;
		}
		else 
		{
			if(!isset($this->admins[$aid]))
				$this->GetUserArray($aid);
			for($i=0;$i<strlen($this->admins[$aid]['srv_flags']);$i++)
			{
				for($a=0;$a<strlen($flags);$a++)
				{
					if(strstr($this->admins[$aid]['srv_flags'][$i], $flags[$a]))
						return true;
				}
			}
		}
	}
	
	
	/**
	 * Gets a 'property' from the user array eg. 'authid'
	 *
	 * @param $aid the ID of admin to get info for.
	 * @return mixed.
	 */
	function GetProperty($name, $aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
		if(empty($name) || $aid < 0)
			return false;
		$aid = (int)$aid;	
		if(!isset($this->admins[$aid]))
			$this->GetUserArray($aid);

		// БАГ-ФИКС: если $aid не существует в БД (например, администратор был удалён, а на
		// него осталась старая ссылка вида ...&id=260), GetUserArray() выше ничего не кладёт
		// в $this->admins[$aid] (возвращает 0). Раньше здесь всё равно шло обращение
		// к $this->admins[$aid][$name], что генерировало PHP notice/warning
		// (Undefined array key / Trying to access array offset on value of type int) -
		// это "коверкало" страницу поверх собственного сообщения об ошибке "не найден".
		// Теперь просто корректно возвращаем false, как и для явно некорректного $aid выше.
		if(!isset($this->admins[$aid]) || !isset($this->admins[$aid][$name]))
			return false;

		return $this->admins[$aid][$name];
	}
	

	/**
	 * Will test the user's login stuff to check if they havnt changed their 
	 * cookies or something along those lines.
	 *
	 * @param $password The admins password.
	 * @param $aid the admins aid
	 * @return boolean.
	 */
	function CheckLogin($password, $aid)
	{
		$aid = (int)$aid;

		if(empty($password))
			return false;
		if(!isset($this->admins[$aid]))
			$this->GetUserArray($aid);

		if(empty($this->admins[$aid]) || empty($this->admins[$aid]['password']))
			return false;
			
		// Кука password хранит тот же хэш, что в БД (старый sha1-двойной или password_hash).
		// hash_equals — против magic hash / timing.
		if(hash_equals((string)$this->admins[$aid]['password'], (string)$password))
		{
			// Истёкший / отозванный аккаунт (в т.ч. tripwire expired=1) — не держим сессию по кукам.
			$expired = isset($this->admins[$aid]['expired']) ? (int)$this->admins[$aid]['expired'] : 0;
			if ($expired > 0 && $expired < time())
			{
				if (function_exists('sb_clear_auth_cookies'))
					sb_clear_auth_cookies();
				elseif (function_exists('logout'))
					@logout();
				return false;
			}
			$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `lastvisit` = UNIX_TIMESTAMP() WHERE `aid` = ?", array($aid));
			return true;
		}
		else 
			return false;
	}

	/**
	 * Старый SourceBans-хэш (sha1(sha1(SALT.password))). Нужен как fallback при логине.
	 */
	function legacy_password_hash($password, $salt=SB_SALT)
	{
		return sha1(sha1($salt . $password));
	}

	function is_modern_password_hash($hash)
	{
		if (!is_string($hash) || $hash === '')
			return false;
		return (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0 || strpos($hash, '$2b$') === 0 || strpos($hash, '$argon2') === 0);
	}

	/**
	 * Новый хэш для записи в БД (password_hash / bcrypt).
	 */
	function hash_password($password)
	{
		return password_hash((string)$password, PASSWORD_DEFAULT);
	}

	/**
	 * Совместимое имя: теперь пишет современный хэш.
	 * Для проверки plaintext используйте verify_password().
	 */
	function encrypt_password($password, $salt=SB_SALT)
	{
		return $this->hash_password($password);
	}

	/**
	 * Проверка plaintext-пароля против хэша админа в БД (bcrypt или legacy sha1).
	 */
	function verify_password($password, $aid)
	{
		$aid = (int)$aid;
		if ($password === '' || $password === null)
			return false;
		if (!isset($this->admins[$aid]))
			$this->GetUserArray($aid);
		if (empty($this->admins[$aid]['password']))
			return false;
		$stored = (string)$this->admins[$aid]['password'];
		if ($this->is_modern_password_hash($stored))
			return password_verify((string)$password, $stored);
		return hash_equals($stored, $this->legacy_password_hash($password));
	}

	/**
	 * После успешного логина со старым хэшем — тихо мигрируем на password_hash.
	 */
	function upgrade_password_hash($aid, $password)
	{
		$aid = (int)$aid;
		if (!isset($this->admins[$aid]))
			$this->GetUserArray($aid);
		if (empty($this->admins[$aid]['password']))
			return;
		$stored = (string)$this->admins[$aid]['password'];
		if ($this->is_modern_password_hash($stored) && !password_needs_rehash($stored, PASSWORD_DEFAULT))
			return;
		$new = $this->hash_password($password);
		$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `password` = ? WHERE `aid` = ?", array($new, $aid));
		$this->admins[$aid]['password'] = $new;
	}
	
	
	function login($aid, $password, $save = true)
	{
		$aid = (int)$aid;
		if(!$this->verify_password($password, $aid))
			return false;

		$this->upgrade_password_hash($aid, $password);
		$stored = (string)$this->admins[$aid]['password'];

		// Фиксация session fixation после успешной аутентификации.
		if (session_status() === PHP_SESSION_ACTIVE)
			@session_regenerate_id(true);

		if($save)
		{
			sb_set_auth_cookie("aid", $aid, time()+LOGIN_COOKIE_LIFETIME);
			sb_set_auth_cookie("password", $stored, time()+LOGIN_COOKIE_LIFETIME);
			setcookie("user", isset($_SESSION['user']['user'])?$_SESSION['user']['user']:null, time()+LOGIN_COOKIE_LIFETIME, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, true);
		}
		else
		{
			sb_set_auth_cookie("aid", $aid, 0);
			sb_set_auth_cookie("password", $stored, 0);
			setcookie("user", isset($_SESSION['user']['user'])?$_SESSION['user']['user']:null, 0, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, true);
		}
		return true;
	}
	
	function is_logged_in()
	{
		if($this->aid != -1)
			return true;
		else 
			return false;
	}
	
	
	function is_admin($aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
		
		if($this->HasAccess(ALL_WEB, $aid))
			return true;
		else 	
			return false;
	}
	
	
	function GetAid()
	{
		return $this->aid;
	}
	
	
	function GetAllAdmins()
	{
		$res = $GLOBALS['db']->GetAll("SELECT aid FROM " . DB_PREFIX . "_admins");
		foreach($res AS $admin)
			$this->GetUserArray($admin['aid']);
		return $this->admins;
	}
	
	
	function GetAdmin($aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
		if($aid < 0)
			return false;	
			
		$aid = (int)$aid;
		
		if(!isset($this->admins[$aid]))
			$this->GetUserArray($aid);
		return $this->admins[$aid];
	}
	
	
	function AddAdmin($name, $steam, $password, $email, $web_group, $web_flags, $srv_group, $srv_flags, $immunity, $srv_password, $period, $discord, $comment, $vk)
	{		
		$add_admin = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_admins(user, authid, password, gid, email, extraflags, immunity, srv_group, srv_flags, srv_password, expired, discord, comment, vk)
											 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
		$GLOBALS['db']->Execute($add_admin,array($name, $steam, $this->hash_password($password), $web_group, $email, $web_flags, $immunity, $srv_group, $srv_flags, $srv_password, $period, $discord, $comment, $vk));
		return ($add_admin) ? (int)$GLOBALS['db']->Insert_ID() : -1;
	}
}
