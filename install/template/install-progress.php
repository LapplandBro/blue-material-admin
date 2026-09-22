<?php
if (!defined('IN_SB')) {
	echo 'You should not be here. Only follow links!';
	die();
}

$installStep = isset($installStep) ? (int) $installStep : 1;
if ($installStep < 1 || $installStep > 5) {
	$installStep = 1;
}
$installFinished = !empty($installFinished);

$steps = [
	1 => 'Шаг: Лицензия',
	2 => 'Шаг: База данных',
	3 => 'Шаг: Системные требования',
	4 => 'Шаг: Создание таблиц',
	5 => 'Шаг: Установка',
];
?>
<div class="ms-menu">
	<div class="ms-block p-10">
		<span class="c-black"><b>Процесс</b></span>
	</div>

	<div class="listview lv-user" id="install-progress">
<?php foreach ($steps as $num => $label):
	$done = ($num < $installStep) || ($installFinished && $num === $installStep);
	$current = ($num === $installStep) && !$installFinished;
	$itemClass = 'lv-item media';

	if ($current) {
		$itemClass .= ' active';
		$avatarClass = 'bgm-accent';
		$statusIcon = 'bi-record-circle step-current';
		$statusText = 'Текущий шаг';
	} elseif ($done) {
		$itemClass .= ' completed';
		$avatarClass = 'bgm-green';
		$statusIcon = 'bi-check-circle c-green';
		$statusText = ($installFinished && $num === $installStep) ? 'Завершено' : 'Выполнено';
	} else {
		$itemClass .= ' upcoming';
		$avatarClass = 'bgm-muted';
		$statusIcon = 'bi-circle step-muted';
		$statusText = 'Следующий шаг';
	}
?>
		<div class="<?= $itemClass ?>">
			<div class="lv-avatar <?= $avatarClass ?> pull-left"><?= (int) $num ?></div>
			<div class="media-body">
				<div class="lv-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
				<div class="lv-small"><i class="bi <?= $statusIcon ?>"></i> <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></div>
			</div>
		</div>
<?php endforeach; ?>
	</div>
</div>
