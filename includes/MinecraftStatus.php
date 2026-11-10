<?php
/**************************************************************************
 * This file is part of Blue Material Admin (SourceBans++ fork).
 *
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE and NOTICE in the project root.
 *
 * Server List Ping (wiki.vg): TCP, без ext-gmp и без UDP GameSpy.
 ***************************************************************************/
if (!defined('IN_SB')) {echo("You should not be here. Only follow links!");die();}

/**
 * Статус Minecraft по игровому порту.
 *
 * Современный пинг (клиент с 1.7): handshake с next-state 1, затем status
 * request, ответ — JSON (players.online / max, description, version.name).
 * Если TCP открылся, а рукопожатие не разобралось — старый пинг 0xFE 0x01.
 * Таймаут одного захода около 2 секунд. Второе соединение только когда
 * первое уже установилось: закрытый порт не ждём дважды.
 */
class MinecraftStatus
{
	private $host;
	private $port;
	private $timeout;
	private $socket = null;
	private $deadline = 0.0;
	/** @var bool TCP на этом объекте хотя бы раз открылся */
	private $didConnect = false;

	/**
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}|null
	 */
	public static function query($host, $port = 25565, $timeout = 2)
	{
		$host = trim((string)$host);
		if (strlen($host) >= 2 && $host[0] === '[' && substr($host, -1) === ']')
			$host = substr($host, 1, -1);
		$port = (int)$port;
		$timeout = (int)$timeout;
		if ($timeout < 1)
			$timeout = 2;
		if ($timeout > 10)
			$timeout = 10;
		if ($host === '' || strlen($host) > 255 || preg_match('/[\x00-\x20]/', $host))
			return null;
		if ($port < 1 || $port > 65535)
			return null;

		$client = new self($host, $port, $timeout);
		$modern = $client->tryModern();
		if (is_array($modern))
			return $modern;
		if (!$client->didConnect)
			return null;
		return $client->tryLegacy();
	}

	private function __construct($host, $port, $timeout)
	{
		$this->host = $host;
		$this->port = (int)$port;
		$this->timeout = (int)$timeout;
	}

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}|null
	 */
	private function tryModern()
	{
		if (!$this->open())
			return null;
		try {
			$this->writeHandshake();
			return $this->readStatus();
		} catch (Throwable $e) {
			return null;
		} finally {
			$this->close();
		}
	}

	/**
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}|null
	 */
	private function tryLegacy()
	{
		if (!$this->open())
			return null;
		try {
			$this->write("\xFE\x01");
			return $this->readLegacy();
		} catch (Throwable $e) {
			return null;
		} finally {
			$this->close();
		}
	}

	private function open()
	{
		$this->close();
		$this->deadline = microtime(true) + $this->timeout;
		$remote = $this->host;
		if (strpos($remote, ':') !== false)
			$remote = '[' . $remote . ']';

		$prev = ini_get('default_socket_timeout');
		@ini_set('default_socket_timeout', (string)$this->timeout);
		$errno = 0;
		$errstr = '';
		$fp = @fsockopen($remote, $this->port, $errno, $errstr, (float)$this->timeout);
		if ($prev !== false && $prev !== '')
			@ini_set('default_socket_timeout', (string)$prev);
		if (!is_resource($fp))
			return false;

		stream_set_blocking($fp, true);
		$left = (int)ceil($this->deadline - microtime(true));
		if ($left < 1)
			$left = 1;
		stream_set_timeout($fp, $left);
		$this->socket = $fp;
		$this->didConnect = true;
		return true;
	}

	private function close()
	{
		if (is_resource($this->socket))
			@fclose($this->socket);
		$this->socket = null;
	}

	private function write($bytes)
	{
		$off = 0;
		$len = strlen($bytes);
		while ($off < $len) {
			if (microtime(true) > $this->deadline)
				throw new RuntimeException('timeout');
			$n = @fwrite($this->socket, substr($bytes, $off));
			if ($n === false || $n === 0)
				throw new RuntimeException('write');
			$off += $n;
		}
	}

	/**
	 * Handshake (packet id 0, protocol -1, next state 1) и сразу status request.
	 * Оба пакета одним write, чтобы Nagle не отложил второй.
	 */
	private function writeHandshake()
	{
		$host = $this->host;
		$body = "\x00"
			. "\xff\xff\xff\xff\x0f"
			. self::writeVarInt(strlen($host))
			. $host
			. pack('n', $this->port)
			. "\x01";
		$this->write(self::writeVarInt(strlen($body)) . $body . "\x01\x00");
	}

	/**
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}
	 */
	private function readStatus()
	{
		$packetLen = $this->readVarInt();
		if ($packetLen < 2 || $packetLen > 1048576)
			throw new RuntimeException('length');
		$packet = $this->readExact($packetLen);
		$offset = 0;
		$packetId = self::varIntBuf($packet, $offset);
		if ($packetId !== 0)
			throw new RuntimeException('packet');
		$strlen = self::varIntBuf($packet, $offset);
		if ($strlen < 2 || ($offset + $strlen) > strlen($packet))
			throw new RuntimeException('json');
		$json = substr($packet, $offset, $strlen);
		$data = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
		if (!is_array($data))
			throw new RuntimeException('json');
		unset($data['favicon']);
		if (!isset($data['description']) && !isset($data['players']) && !isset($data['version']))
			throw new RuntimeException('shape');
		return self::fromJson($data);
	}

	/**
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}
	 */
	private function readLegacy()
	{
		$hdr = $this->readExact(3);
		if ($hdr[0] !== "\xFF")
			throw new RuntimeException('legacy');
		$unpacked = unpack('n', substr($hdr, 1, 2));
		$chars = isset($unpacked[1]) ? (int)$unpacked[1] : 0;
		if ($chars < 1 || $chars > 1024)
			throw new RuntimeException('legacy');
		$raw = $this->readExact($chars * 2);
		$text = self::utf16be($raw);
		if ($text === '')
			throw new RuntimeException('legacy');
		$info = self::fromLegacy($text);
		if (!is_array($info))
			throw new RuntimeException('legacy');
		return $info;
	}

	private function readVarInt()
	{
		$num = 0;
		for ($i = 0; $i < 5; $i++) {
			$byte = ord($this->readExact(1));
			$num |= ($byte & 0x7F) << (7 * $i);
			if (($byte & 0x80) === 0)
				return $num;
		}
		throw new RuntimeException('varint');
	}

	private function readExact($n)
	{
		$data = '';
		$n = (int)$n;
		while (strlen($data) < $n) {
			$left = $this->deadline - microtime(true);
			if ($left <= 0)
				throw new RuntimeException('timeout');
			$read = array($this->socket);
			$write = null;
			$except = null;
			$sec = (int)floor($left);
			$usec = (int)(($left - $sec) * 1000000);
			$ready = @stream_select($read, $write, $except, $sec, $usec);
			if ($ready === false || $ready === 0)
				throw new RuntimeException('timeout');
			$want = $n - strlen($data);
			if ($want > 8192)
				$want = 8192;
			$chunk = @fread($this->socket, $want);
			if (!is_string($chunk) || $chunk === '') {
				$meta = @stream_get_meta_data($this->socket);
				if (!is_array($meta) || !empty($meta['eof']))
					throw new RuntimeException('eof');
				throw new RuntimeException('timeout');
			}
			$data .= $chunk;
		}
		return $data;
	}

	private static function varIntBuf($buf, &$offset)
	{
		$num = 0;
		$len = strlen($buf);
		for ($i = 0; $i < 5; $i++) {
			if ($offset >= $len)
				throw new RuntimeException('varint');
			$byte = ord($buf[$offset]);
			$offset++;
			$num |= ($byte & 0x7F) << (7 * $i);
			if (($byte & 0x80) === 0)
				return $num;
		}
		throw new RuntimeException('varint');
	}

	private static function writeVarInt($value)
	{
		$value = (int)$value;
		if ($value < 0)
			$value = 0;
		$out = '';
		while (true) {
			$byte = $value & 0x7F;
			$value >>= 7;
			if ($value !== 0)
				$out .= chr($byte | 0x80);
			else
				return $out . chr($byte);
		}
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}
	 */
	private static function fromJson($data)
	{
		$players = (isset($data['players']) && is_array($data['players'])) ? $data['players'] : array();
		$online = isset($players['online']) ? self::nint($players['online']) : 0;
		$max = isset($players['max']) ? self::nint($players['max']) : 0;
		$bots = 0;
		if (array_key_exists('bots', $players))
			$bots = self::nint($players['bots']);
		elseif (isset($data['bots']))
			$bots = self::nint($data['bots']);

		// Пустой сервер на части старых ядер: online=1 и единственный ник "nobody".
		if ($online === 1 && isset($players['sample']) && is_array($players['sample'])) {
			$sawNobody = false;
			$sawReal = false;
			foreach ($players['sample'] as $row) {
				if (!is_array($row))
					continue;
				$name = strtolower(self::cleanLine(isset($row['name']) ? $row['name'] : ''));
				if ($name === '' || $name === 'nobody')
					$sawNobody = true;
				else
					$sawReal = true;
			}
			if ($sawNobody && !$sawReal)
				$online = 0;
		}
		if ($bots > $online)
			$bots = $online;

		$version = '';
		if (isset($data['version']) && is_array($data['version']) && isset($data['version']['name']))
			$version = $data['version']['name'];
		elseif (isset($data['version']) && is_string($data['version']))
			$version = $data['version'];

		return self::packInfo(
			isset($data['description']) ? $data['description'] : '',
			$online,
			$max,
			$bots,
			$version
		);
	}

	/**
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}|null
	 */
	private static function fromLegacy($text)
	{
		$mark = "\xC2\xA7" . '1';
		if (strpos($text, "\0") !== false) {
			$pos = strpos($text, $mark);
			if ($pos === false)
				return null;
			$parts = explode("\0", substr($text, $pos));
			if (count($parts) < 6)
				return null;
			return self::packInfo($parts[3], $parts[4], $parts[5], 0, $parts[2]);
		}

		$parts = explode("\xC2\xA7", $text);
		if (count($parts) < 3)
			return null;
		$max = trim((string)array_pop($parts));
		$online = trim((string)array_pop($parts));
		if (!preg_match('/^-?\d+$/', $online) || !preg_match('/^-?\d+$/', $max))
			return null;
		$motd = implode("\xC2\xA7", $parts);
		return self::packInfo($motd, $online, $max, 0, '');
	}

	/**
	 * @param mixed $desc
	 * @param mixed $players
	 * @param mixed $max
	 * @param mixed $bots
	 * @param mixed $map
	 * @return array{HostName:string,Players:int,MaxPlayers:int,Bots:int,Map:string}
	 */
	private static function packInfo($desc, $players, $max, $bots, $map)
	{
		$map = self::clipMap($map);
		$name = self::cleanLine($desc);
		$players = self::nint($players);
		$max = self::nint($max);
		$bots = self::nint($bots);
		if ($bots > $players)
			$bots = $players;
		if ($name === '')
			$name = ($map !== '') ? $map : 'Minecraft';
		return array(
			'HostName' => $name,
			'Players' => $players,
			'MaxPlayers' => $max,
			'Bots' => $bots,
			'Map' => $map,
		);
	}

	/**
	 * @param mixed $component
	 */
	private static function cleanLine($component)
	{
		$text = self::plainText($component);
		$text = str_replace("\xEF\xBB\xBF", '', $text);
		$stripped = preg_replace("/\xC2\xA7./u", '', $text);
		if (is_string($stripped))
			$text = $stripped;
		$text = str_replace("\xC2\xA7", '', $text);
		$text = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $text);
		$text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
		if (!is_string($text))
			$text = '';
		$collapsed = preg_replace('/\s+/u', ' ', $text);
		if (is_string($collapsed))
			$text = $collapsed;
		$text = trim($text);
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($text, 'UTF-8') > 120)
				$text = mb_substr($text, 0, 120, 'UTF-8');
		} elseif (strlen($text) > 240) {
			$text = substr($text, 0, 240);
			$text = preg_replace('/[\xC0-\xF7][\x80-\xBF]*$/s', '', $text);
			if (!is_string($text))
				$text = '';
		}
		return $text;
	}

	/**
	 * @param mixed $map
	 */
	private static function clipMap($map)
	{
		$map = str_replace(array('/', '\\'), ' ', (string)$map);
		$map = self::cleanLine($map);
		$map = str_replace(array('<', '>', '"', "'"), '', $map);
		if (strlen($map) > 80) {
			$map = substr($map, 0, 80);
			$map = preg_replace('/[\xC0-\xF7][\x80-\xBF]*$/s', '', $map);
			if (!is_string($map))
				$map = '';
		}
		return trim($map);
	}

	/**
	 * @param mixed $component
	 */
	private static function plainText($component, $depth = 0)
	{
		if ($depth > 8)
			return '';
		if (is_string($component))
			return $component;
		if (is_int($component))
			return (string)$component;
		if (is_float($component))
			return is_finite($component) ? (string)$component : '';
		if (!is_array($component))
			return '';
		if (array_key_exists(0, $component)) {
			$buf = '';
			foreach ($component as $part)
				$buf .= self::plainText($part, $depth + 1);
			return $buf;
		}
		$buf = '';
		if (isset($component['text']) && (is_string($component['text']) || is_numeric($component['text'])))
			$buf = (string)$component['text'];
		if (isset($component['extra']))
			$buf .= self::plainText($component['extra'], $depth + 1);
		if ($buf === '' && isset($component['translate']) && is_string($component['translate'])) {
			$buf = $component['translate'];
			if (isset($component['with']))
				$buf .= ' ' . self::plainText($component['with'], $depth + 1);
		}
		return $buf;
	}

	/**
	 * @param mixed $value
	 */
	private static function nint($value)
	{
		$n = (int)$value;
		if ($n < 0)
			return 0;
		if ($n > 10000000)
			return 10000000;
		return $n;
	}

	private static function utf16be($raw)
	{
		if ($raw === '' || (strlen($raw) % 2) !== 0)
			return '';
		if (function_exists('iconv')) {
			$out = @iconv('UTF-16BE', 'UTF-8//IGNORE', $raw);
			if (is_string($out) && $out !== '')
				return $out;
		}
		if (function_exists('mb_convert_encoding')) {
			$out = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
			if (is_string($out) && $out !== '')
				return $out;
		}
		return self::utf16beManual($raw);
	}

	private static function utf16beManual($raw)
	{
		$out = '';
		$n = strlen($raw) - (strlen($raw) % 2);
		for ($i = 0; $i < $n; $i += 2) {
			$cu = (ord($raw[$i]) << 8) | ord($raw[$i + 1]);
			if ($cu >= 0xD800 && $cu <= 0xDBFF && ($i + 3) < $n) {
				$cu2 = (ord($raw[$i + 2]) << 8) | ord($raw[$i + 3]);
				if ($cu2 >= 0xDC00 && $cu2 <= 0xDFFF) {
					$cp = 0x10000 + (($cu - 0xD800) << 10) + ($cu2 - 0xDC00);
					$i += 2;
					$out .= self::utf8cp($cp);
					continue;
				}
			}
			if ($cu >= 0xD800 && $cu <= 0xDFFF)
				continue;
			$out .= self::utf8cp($cu);
		}
		return $out;
	}

	private static function utf8cp($cp)
	{
		if ($cp < 0x80)
			return chr($cp);
		if ($cp < 0x800)
			return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
		if ($cp < 0x10000)
			return chr(0xE0 | ($cp >> 12))
				. chr(0x80 | (($cp >> 6) & 0x3F))
				. chr(0x80 | ($cp & 0x3F));
		return chr(0xF0 | ($cp >> 18))
			. chr(0x80 | (($cp >> 12) & 0x3F))
			. chr(0x80 | (($cp >> 6) & 0x3F))
			. chr(0x80 | ($cp & 0x3F));
	}
}
