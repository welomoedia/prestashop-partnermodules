<?php
/**
 * Script when the user selects sofortüberweisung.de as
 * payment method.
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

$useSSL = true;

$baseDir = dirname(__FILE__);

include($baseDir . '/../../config/config.inc.php');
include($baseDir . '/../../header.php');
require_once($baseDir . '/sofortueberweisung.php');

// Redirect back to the payment selection page
// if customer could not be verified
if (!$cookie->isLogged() && ! $cookie->id_guest) {
    Tools::redirect('authentication.php?back=order.php');
}

// Get class instance
$su = new Sofortueberweisung();

// Show form used for redirection
echo $su->execPayment($cart);

include_once($baseDir . '/../../footer.php');
