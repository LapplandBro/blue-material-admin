<?php
/**************************************************************************
 * This file is part of Blue Material Admin (SourceBans++ fork).
 *
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE and NOTICE in the project root.
 *
 * UI theme under themes/new_box has separate provenance — see NOTICE.
 ***************************************************************************/
require_once('init.php');

$pay_service__file  = sprintf("%s/pay_services/%s.php", INCLUDES_PATH, $GLOBALS['config']['autodonate.main.payment_service']);
if (!file_exists($pay_service__file))
    die(sprintf("SourceBans Autodonate Fatal Error: No such file with pay service %s.", $GLOBALS['config']['autodonate.main.payment_service']));

require_once($pay_service__file);
if (!class_exists($GLOBALS['config']['autodonate.main.payment_service']))
    die("Not found service class.");

/* Prepare Billing instance */
$BILLING    = new CDonate();
$service    = new $GLOBALS['config']['autodonate.main.payment_service']($BILLING);

if (!$service->isValidPayment())
    die("Invalid Payment data.");

$id = $service->getPaymentId();
if (!$id)
    die("ID is not valid.");

$BILLING->fireEvent('onPaymentSuccessfull', array($service, $id));
