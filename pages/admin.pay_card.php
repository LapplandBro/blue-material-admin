<?php
if(!defined("IN_SB")){echo "Ошибка доступа!";die();}
global $userbank, $theme;

	echo '<div id="admin-page-content">';
	if(!$userbank->HasAccess(ADMIN_OWNER))
	{
		echo '<div id="0" style="display:none;">Доступ запрещен!</div>';
	} else {
		
		if((isset($_GET['o']) && $_GET['o'] == "del")){
			if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
				echo '<script>setTimeout(\'ShowBox("Ошибка", "ID бана не указан!", "red", "index.php");\', 1200);</script>';
				PageDie();
			}else{
				$qwer = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_vay4er` WHERE aid = ?", array((int)$_GET['id']));
				if($qwer){
					$qww = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_vay4er` WHERE aid = ?", array((int)$_GET['id']));
					if($qww){
						echo '<script>setTimeout(\'ShowBox("Успешно", "Ваучер был успешно удален!", "green", "index.php?p=admin&c=pay_card");\', 1200);</script>';
						$log = new CSystemLog("m", "Ваучер удалён", $userbank->GetProperty("user") . " удалил ваучер (ключ: " . $qwer['value'] . ", дней: " . $qwer['days'] . ").");
					}
				}else{
					echo '<script>setTimeout(\'ShowBox("Ошибка", "ID бана не указан!", "red", "index.php");\', 1200);</script>';
				}
			}
		}
		#########[list]###############
		echo '<div id="0" style="display:none;">';
			
			
			
			$cards = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_vay4er` ORDER BY `activ` DESC");
			$card_list = array();
			foreach($cards AS $card)
			{
				$info['aid'] = $card['aid'];
				$info['activ'] = $card['activ'];
				$info['value'] = function_exists('sb_voucher_format_key')
					? sb_voucher_format_key($card['value'])
					: $card['value'];
				$info['days'] = $card['days'];
				$info['group_web'] = $card['group_web'];
				$info['group_srv'] = $card['group_srv'];
				$info['servers'] = $card['servers'];
				array_push($card_list, $info);
			}
			$theme->assign('card_list', $card_list);
			$apiBase = (defined('SB_WP_URL') && SB_WP_URL !== '') ? rtrim(SB_WP_URL, '/') : '';
			$theme->assign('voucher_api_url', ($apiBase !== '' ? $apiBase . '/' : '') . 'api/voucher_create.php');
			$theme->assign('voucher_api_enabled', (function_exists('sb_voucher_api_enabled') && sb_voucher_api_enabled()) ? '1' : '0');
			$theme->display('page_admin_pay_list.tpl');	
		echo '</div>';
		#########/[list]###############
		
		#########[add]###############
		echo '<div id="1" style="display:none;">';
			
			//
			$servers = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_servers`");
			$server_list = array();
			$serverscript = "<script type=\"text/javascript\">";
			foreach($servers AS $server)
			{
				$serverscript .= "xajax_ServerHostPlayers('".$server['sid']."', 'id', 'sa".$server['sid']."');";
				$info['sid'] = $server['sid'];
				$info['ip'] = $server['ip'];
				$info['port'] = $server['port'];
				array_push($server_list, $info);
			}


			$serverscript .= "</script>";

			$theme->assign('server_list', $server_list);
			$theme->assign('server_script', $serverscript);
			//
			
			// Add Page
			$server_admin_group_list = 	$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
			$server_group_list = 		$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type != 3");

			echo '<div id="1" style="display:none;">';
				$theme->assign('server_admin_group_list', $server_admin_group_list);
				$theme->assign('server_group_list', $server_group_list);
				$theme->display('page_admin_admins_add.tpl');
			echo '</div>';


			
			if(isset($_POST['pay_card_admin'])){
				if ($_POST['pay_card_admin'] == "pay_card_add"){
					$csrf = isset($_POST['sb_csrf']) ? $_POST['sb_csrf'] : '';
					if (function_exists('sb_csrf_validate') && !sb_csrf_validate($csrf)) {
						echo "<script>setTimeout(\"ShowBox('Ваучер', 'Сессия устарела. Обновите страницу.', 'red', '', true);\", 1200);</script>";
					} elseif(($_POST['card_key'] != "") && ($_POST['card_exp'] >= 0) && ($_POST['card_gr_web'] != "")){
						
						$key_vr = function_exists('sb_voucher_normalize_key')
							? sb_voucher_normalize_key($_POST['card_key'])
							: strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$_POST['card_key']));
						$exp_vr = preg_replace("/[^0-9]/", '', (string)$_POST['card_exp']);
						if($exp_vr == ""){
							$exp_vr = "0";
						}
						$gr_web_vr = $_POST['card_gr_web'];
						$gr_srv_vr = $_POST['card_gr_srv'];
						$srv_check = isset($_POST['srv_check_int']) ? $_POST['srv_check_int'] : '';
						
						if((stristr($srv_check, ',') && stristr($srv_check, 's')) == FALSE){
							if($srv_check != "-1"){
								$srv_check = "";
							}
						}
						
						$ifvay4_shon = $GLOBALS['db']->GetOne("SELECT COUNT(`value`) FROM `".DB_PREFIX."_vay4er` WHERE value = ?", array($key_vr));
						if(!function_exists('sb_voucher_key_valid') || !sb_voucher_key_valid($key_vr)){
							echo "<script>setTimeout(\"ShowBox('Ваучер', 'Ошибка: ключ должен быть 32 hex-символа (16 байт). Нажмите «Сгенерировать».', 'red', 'index.php?p=admin&c=pay_card#^1');\", 1200);</script>";
						}elseif($ifvay4_shon == "0" || $ifvay4_shon == "" || $ifvay4_shon < 1){
							
							$edit = $GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_vay4er` (`activ`, `value`, `days`, `group_web`, `group_srv`, `servers`)
								VALUES (1, ?, ?, ?, ?, ?)", array($key_vr, $exp_vr, $gr_web_vr, $gr_srv_vr, $srv_check));
							
							if($edit){
								echo "<script>setTimeout(\"ShowBox('Ваучер', 'Ваучер был успешно добавлен!', 'green', 'index.php?p=admin&c=pay_card');\", 1200);</script>";
								$log = new CSystemLog("m", "Ваучер создан", $userbank->GetProperty("user") . " создал ваучер (ключ: " . $key_vr . ", дней: " . $exp_vr . ").");
							}else{
								echo "<script>setTimeout(\"ShowBox('Ваучер', 'Ошибка, не могу добавить ключ!', 'red', 'index.php?p=admin&c=pay_card');\", 1200);</script>";
							}
						}else{
							echo "<script>setTimeout(\"ShowBox('Ваучер', 'Ошибка, данный ключ уже есть в системе!', 'red', '', true);\", 1200);</script>";
						}
					}else{
						echo "<script>setTimeout(\"ShowBox('Ваучер', 'Ошибка, заполните все данные правильно!', 'red', '', true);\", 1200);</script>";
					}
				}
			}
			$gen_key = function_exists('sb_voucher_generate_key')
				? (function_exists('sb_voucher_format_key') ? sb_voucher_format_key(sb_voucher_generate_key(16)) : sb_voucher_generate_key(16))
				: '';
			$theme->assign('card_key_default', $gen_key);
			$theme->assign('sb_csrf', function_exists('sb_csrf_token') ? sb_csrf_token() : '');
			$theme->display('page_admin_pay_add.tpl');	
		echo '</div>';
		#########/[add]###############
	}
?>
<script>
function sbVoucherGenerateHex(bytes) {
	bytes = bytes || 16;
	var arr = new Uint8Array(bytes);
	if (window.crypto && window.crypto.getRandomValues) {
		window.crypto.getRandomValues(arr);
	} else {
		for (var i = 0; i < bytes; i++) arr[i] = Math.floor(Math.random() * 256);
	}
	var hex = '';
	for (var i = 0; i < arr.length; i++) {
		hex += ('0' + arr[i].toString(16)).slice(-2);
	}
	return hex.match(/.{1,4}/g).join('-');
}
function getPassword(length) {
	// совместимость со старыми onclick; length игнорируется — всегда 16 байт HEX
	return sbVoucherGenerateHex(16);
}
(function () {
	var el = document.getElementById('card_key');
	if (el && (!el.value || el.value === '')) el.value = sbVoucherGenerateHex(16);
})();

function Check_cal(){
	var el = document.getElementsByName('servers[]');
	var svr_vv = '';
	for(i=0;i<el.length;i++){
		if(el[i].checked){
			if(el[i].value == "-1"){
				svr_vv = "-1";
			}else{
				svr_vv = svr_vv + ',' + el[i].value;
			}
		}
	}
	var h = document.getElementById('srv_check_int');
	if (h) h.value = svr_vv;
}
</script>
