<?php
/**
 * Script called when customer returns from
 * the sofortüberweisung.de payment page
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

$baseDir = dirname(__FILE__);

include($baseDir . '/../../config/config.inc.php');
include($baseDir . '/../../header.php');
require_once($baseDir . '/sofortueberweisung.php');

global $cookie, $smarty;

// Get class instance
$su = new Sofortueberweisung();

// Get cartId from $_GET
$cartId = intval($_GET['cartid']);

if ($cartId <= 0) {
    // Display information how to set the cartid
    $smarty->display(dirname(__FILE__) . '/tpl-frontend-success-error.tpl');
}
else {
    // Get orderId from cartId
    $orderId = Order::getOrderByCartId($cartId);
    $order = new Order($orderId);

    $redirectLink = __PS_BASE_URI__ . 'order-confirmation.php?id_cart=' . $cartId . '&id_module=' . $su->id . '&id_order=' . $orderId . '&key='.$order->secure_key;
    
    Tools::redirectLink($redirectLink);

    // Display tpl-frontend-success template
    $smarty->display(dirname(__FILE__) . '/tpl-frontend-success.tpl');
}

include($baseDir . '/../../footer.php');