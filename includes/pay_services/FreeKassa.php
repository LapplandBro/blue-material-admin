<?php
/**************************************************************************
 * This file is part of Blue Material Admin (SourceBans++ fork).
 *
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE and NOTICE in the project root.
 *
 * UI theme under themes/new_box has separate provenance — see NOTICE.
 ***************************************************************************/
if (!defined('IN_SB')) {echo("You should not be here. Only follow links!");die();}
if (!class_exists('CPaymentService')) require_once(INCLUDES_PATH . '/CDonate.php');

class FreeKassa extends CPaymentService {
    private $billing;
    private $order_id = null;
    private $amount = null;
    
    public function __construct($bill) {
        $this->billing = $bill;
        // Было: register_event() (несуществующий метод -> Fatal Error) и
        // событие "onPaymentSuccessful" (регистрация), которое нигде не
        // совпадало с "onPaymentSuccessfull" (двойная "l"), которым
        // событие реально вызывается из notify_donate.php. Из-за этого
        // обработка успешных платежей ПОЛНОСТЬЮ НЕ РАБОТАЛА.
        $this->billing->registerEvent('onPaymentSuccessfull', [$this, 'onPayment']);
    }
    
    public function onPayment($service, $id) {
        // SECURITY TODO: здесь нужно реализовать фактическую выдачу купленного
        // тарифа/прав администратора после успешной и проверенной оплаты
        // (например, через CDonate::AddPayment_Admin() и обновление записи
        // администратора). Сейчас после подтверждённой оплаты никакие права
        // фактически не выдаются - это отдельная бизнес-логика, которая
        // должна быть аккуратно реализована владельцем сайта в соответствии
        // с тем, как устроены тарифы (sb_billing_admintariffs) на конкретном
        // сайте, чтобы не выдать права по ошибке.
    }

    /**
     * Проверяет, что уведомление действительно пришло от FreeKassa и не было
     * подделано: сверяет ID магазина и криптографическую подпись (SIGN),
     * рассчитанную по секретному слову для уведомлений. Без этой проверки
     * ЛЮБОЙ посетитель мог бы отправить поддельный POST-запрос на
     * notify_donate.php и убедить систему, что оплата прошла.
     *
     * @return bool
     */
    public function isValidPayment() {
        if (!isset($_REQUEST['MERCHANT_ID'], $_REQUEST['AMOUNT'], $_REQUEST['MERCHANT_ORDER_ID'], $_REQUEST['SIGN']))
            return false;

        $merchant_id = (int) $_REQUEST['MERCHANT_ID'];
        $expected_merchant_id = (int) $GLOBALS['config']['billing.freekassa.shop_id'];
        if ($merchant_id <= 0 || $merchant_id !== $expected_merchant_id)
            return false;

        $order_id = (int) $_REQUEST['MERCHANT_ORDER_ID'];
        $amount   = $_REQUEST['AMOUNT'];

        $expected_sign = $this->getNotifySign($order_id, $amount);
        if (!hash_equals($expected_sign, (string) $_REQUEST['SIGN']))
            return false;

        $this->order_id = $order_id;
        $this->amount   = $amount;
        return true;
    }

    /**
     * @return int|null ID заказа из подтверждённого (проверенного через
     *                   isValidPayment()) уведомления, либо null.
     */
    public function getPaymentId() {
        return $this->order_id;
    }

    public function getName() {
        return 'FreeKassa';
    }
    
    public function getAuthor() {
        return '<a href="https://steamcommunity.com/profiles/76561198071596952/" target="_blank">CrazyHackGUT</a>';
    }
    
    public function getVersion() {
        return '0.1-dev';
    }
    
    public function getUrl() {
        return 'https://www.free-kassa.ru/';
    }
    
    public function getClientSign() {
        $order_id   = (int) func_get_arg(0);
        $summ       = (int) func_get_arg(1);
        return md5(sprintf("%s:%d:%s:%d", $GLOBALS['config']['billing.freekassa.shop_id'], $summ, $GLOBALS['config']['billing.freekassa.secret_word.client'], $order_id));
    }
    
    public function getNotifySign() {
        $order_id   = (int) func_get_arg(0);
        $summ       = (int) func_get_arg(1);
        return md5(sprintf("%s:%d:%s:%d", $GLOBALS['config']['billing.freekassa.shop_id'], $summ, $GLOBALS['config']['billing.freekassa.secret_word.notify'], $order_id));
    }
    
    public function generatePaymentUrl() {
        $m          = (int) $GLOBALS['config']['billing.freekassa.shop_id'];
        $oa         = (int) func_get_arg(0);
        $o          = (int) func_get_arg(1);
        $sign       = $this->getNotifySign($o, $oa);
        
        return sprintf("http://www.free-kassa.ru/merchant/cash.php?m=%d&oa=%d&o=%d&s=%s", $m, $oa, $o, $sign);
    }
}
