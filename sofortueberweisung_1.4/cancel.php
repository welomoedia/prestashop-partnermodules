<?php
/**
 * Script called when user cancels the payment
 * process from sofortüberweisung.de payment page
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

// Display tpl-frontend-cancel template
$smarty->display(dirname(__FILE__) . '/tpl-frontend-cancel.tpl');

include($baseDir . '/../../footer.php');
