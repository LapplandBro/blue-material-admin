<?php if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
$installHeading = '';
if (!empty($GLOBALS['TitleRewrite']))
	$installHeading = (string) $GLOBALS['TitleRewrite'];
elseif (!empty($GLOBALS['pagetitle']))
	$installHeading = (string) $GLOBALS['pagetitle'];
?>

<section id="content">
	<div class="container">
		<div class="block-header">
			<h2 id="content_title"><?php echo htmlspecialchars($installHeading, ENT_QUOTES, 'UTF-8'); ?></h2>
		</div>
