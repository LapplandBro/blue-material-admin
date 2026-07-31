<?php
/**
 * strftime() replacement for PHP 8.1+ (common Smarty patterns).
 * On this branch (PHP 8.3) always use date() — strftime is deprecated/removed.
 *
 * @param string $format strftime-style format
 * @param int    $timestamp Unix timestamp
 * @return string
 */
function smarty_compat_strftime($format, $timestamp)
{
	static $map = null;
	if ($map === null) {
		$map = array(
			'%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%d' => 'd',
			'%H' => 'H', '%I' => 'h', '%M' => 'i', '%S' => 's',
			'%p' => 'A', '%B' => 'F', '%b' => 'M', '%A' => 'l', '%a' => 'D',
			'%e' => 'j', '%l' => 'g', '%D' => 'm/d/y', '%T' => 'H:i:s',
			'%R' => 'H:i', '%r' => 'h:i:s A', '%n' => "\n", '%t' => "\t",
			'%w' => 'w', '%u' => 'N', '%V' => 'W', '%G' => 'o', '%g' => 'y',
			'%c' => 'Y-m-d H:i:s', '%x' => 'Y-m-d', '%X' => 'H:i:s',
			'%h' => 'M',
		);
	}

	$dateFormat = strtr((string)$format, $map);
	return date($dateFormat, (int)$timestamp);
}
