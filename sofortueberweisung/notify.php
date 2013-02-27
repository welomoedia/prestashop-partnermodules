<?php
/**
 * Script called on HTTP request from sofortüberweisung.de
 * confirming the payment
 *
 * @category  silbersaiten
 * @package   silbersaiten_sofortueberweisung
 * @author    silbersaiten <kontakt@silbersaiten.de>
 * @copyright 2010 Bangiev & Bangiev GbR
 * @license   http://www.opensource.org/licenses/osl-3.0.php Open-source licence 3.0
 * @version   1.2
 * @link      http://www.silbersaiten.de/
 *
 */

// Stop script when no POST data was commited
if (strlen($_POST['transaction']) <= 0) {
    die('Wrong use of the HTTP notifications. Please set up your sofortüberweisung.de notification to call this URL only with POST method.');
}

// Order state after successful payment
// TODO: Read the order state from configuration
$orderState = _PS_OS_PAYMENT_;

// This enables debugging and logging
// ATTENTION! Logs will contain sensitive information
// including your project and notification passwords!
// Immediatly delete the logfile module/sofortueberweisung/su.log
// after debug operation and disable this option again!
$debug = false;

$baseDir = dirname(__FILE__);

/*
 * Debugging logs are working only with PEAR
 * package Log installed on server.
 *
 * Enable it to check the HTTP response
 * from sofortüberweisung.de. It will be written
 * into file log file /modules/sofortueberweisung/su.log
 *
 */
if ($debug) {
    require_once('Log.php');

    $log = &Log::factory('file', 'su.log', 'Sofortueberweisung');
    $log->log('======================================');
    $log->log('Response start');
}
else {
    $log = null;
}

$useSSL = true;

include($baseDir . '/../../config/config.inc.php');
include($baseDir . '/sofortueberweisung.php');

// Get class instance
$su = new Sofortueberweisung();

// Set order state to "awaiting payment"
$orderState = Configuration::get('_SU_OS_OK_');
$cartId = 0;

// Check incoming response
if ($su->checkResponse($_POST, $log)) {

    if ($debug) {
        $log->log('Security check successful');
    }

    // Get cartId from the response
    $cartId = intval($_POST['user_variable_0']);

    if ($debug) {
        $log->log('CartId: ' . $cartId);
    }

    // Get the cart object
    $cart = new Cart($cartId);

    // Get order sum
    $orderSum = $cart->getOrderTotal();

    // Create order
    $su->updateOrder($cartId, $orderState, $orderSum, 'Payment through sofortüberweisung.de');
}
else {
    if ($debug) {
        $log->log('Security check failed');
    }
}

die();
