<?php
/**
 * Install wrapper — shared SEO helpers live in site includes/seo.inc.php.
 */
if (!defined('IN_SB') && !defined('IN_INSTALL')) {
	echo 'You should not be here. Only follow links!';
	die();
}

$__sb_seo = dirname(dirname(__DIR__)) . '/includes/seo.inc.php';
if (!is_readable($__sb_seo)) {
	// Fallback if tree is incomplete during packaging
	$__sb_seo = dirname(__DIR__) . '/../includes/seo.inc.php';
}
if (!is_readable($__sb_seo)) {
	die('Missing includes/seo.inc.php');
}
require_once $__sb_seo;
unset($__sb_seo);
