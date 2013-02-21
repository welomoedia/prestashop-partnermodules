<?php

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/shopgate.php');

$controller = new FrontController();
$controller->init();

$plugin = new PSShopgatePlugin();

$response = $plugin->handleRequest($_POST);

?>