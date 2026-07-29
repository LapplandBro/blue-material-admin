<?php
// *************************************************************************
//  SourceBans++ installer entry
// *************************************************************************

if (version_compare(PHP_VERSION, '7.1', '<')) {
	header('Content-Type: text/plain; charset=UTF-8');
	echo "Для установки требуется PHP 7.1 или новее.\n";
	echo 'Сейчас установлена версия ' . PHP_VERSION;
	exit;
}

session_start();
include_once 'init.php';
include_once INCLUDES_PATH . '/system-functions.php';
include_once INCLUDES_PATH . '/page-builder.php';
