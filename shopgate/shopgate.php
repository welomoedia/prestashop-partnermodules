<?php

if (!defined('_PS_VERSION_')) exit;
/*
    //Translations
    $this->l('Shopgate order ID:');
*/
define('SHOPGATE_PLUGIN_VERSION', '1.1.4');
define('SHOPGATE_DIR', _PS_MODULE_DIR_.'shopgate/');

require_once(SHOPGATE_DIR.'shopgate_library/shopgate.php');
require_once(SHOPGATE_DIR.'classes/PSShopgatePlugin.php');
require_once(SHOPGATE_DIR.'classes/PSShopgateOrder.php');
require_once(SHOPGATE_DIR.'classes/PSShopgateConfig.php');

class ShopGate extends PaymentModule
{
	private $shopgate_trans = array();
    private $configurations = array
    (
	'SHOPGATE_CARRIER_ID' => 1,
	'PS_OS_SHOPGATE' => 0,
	'PS_OS_MOBILE' => 0,
	'SHOPGATE_LANGUAGE_ID' => 0,
	'SHOPGATE_SHIPPING_SERVICE' => 'OTHER',
	'SHOPGATE_MIN_QUANTITY_CHECK' => 0,
	'SHOPGATE_OUT_OF_STOCK_CHECK' => 0,
    );
    
    private $shipping_service_list = array();
    
    function __construct()
    {
        $this->name = 'shopgate';
        $this->tab = 'market_place';
        $this->version = SHOPGATE_PLUGIN_VERSION;
	$this->author = 'Shopgate';
	$this->module_key = "";

        parent::__construct();

	$this->displayName = $this->l('Shopgate');
        $this->description = $this->l('Sell your products with your individual app and a website optimized for mobile devices.');

	//delivery service list
	$this->shipping_service_list = array
	(
	    'OTHER'		=> $this->l('Other'),
	    'DHL'		=> $this->l('DHL'),
	    'DHLEXPRESS'	=> $this->l('DHL Express'),
	    'DP'		=> $this->l('Deutsche Post'),
	    'DPD'		=> $this->l('DPD'),
	    'FEDEX'		=> $this->l('FedEx'),
	    'GLS'		=> $this->l('GLS'),
	    'HLG'		=> $this->l('Hermes'),
	    'TNT'		=> $this->l('TNT'),
	    'TOF'		=> $this->l('trans-o-flex'),
	    'UPS'		=> $this->l('UPS'),
	);
	
	$this->shopgate_trans = array(
		'Bankwire'         => $this->l('Bankwire'),
		'Cash on Delivery' => $this->l('Cash on Delivery'),
		'PayPal'           => $this->l('PayPal'),
		'Mobile Payment'   => $this->l('Mobile Payment'),
		'Shopgate'         => $this->l('Shopgate')
	);
    }

    function install()
    {
	if
	(
	    !parent::install() ||
	    !$this->registerHook('header') ||
	    !$this->registerHook('adminOrder') ||
	    !$this->registerHook('updateOrderStatus')
	)
	    return false;
	    
	$sql_table = '
	CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'shopgate_order`
	(
	    `id_shopgate_order` int(11) NOT NULL AUTO_INCREMENT,
	    `id_cart` int(11) NOT NULL DEFAULT \'0\',
	    `id_order` int(11) NOT NULL DEFAULT \'0\',
	    `order_number` varchar(16) NOT NULL,
	    `tracking_number` varchar(32) NOT NULL DEFAULT \'\',
	    `shipping_service` varchar(16) NOT NULL DEFAULT \'OTHER\',
	    `shipping_cost` decimal(17,2) NOT NULL DEFAULT \'0.00\',
	    PRIMARY KEY (`id_shopgate_order`),
	    UNIQUE KEY `order_number` (`order_number`)
	)
	ENGINE=InnoDB DEFAULT CHARSET=latin1;';
	 
	if(!Db::getInstance()->Execute($sql_table))
	    return false;
	
	// Create shopgate carrier if not exists
	$id_carrier = (int)Db::getInstance()->getValue('SELECT `id_carrier` FROM `'._DB_PREFIX_.'carrier` WHERE `external_module_name` = \'shopgate\'');
	$carrier = new Carrier($id_carrier);
	if(!Validate::isLoadedObject($carrier))
	{
	    $carrier->name = 'Shopgate';
	    $carrier->is_module = 1;
	    $carrier->deleted = 1;
	    $carrier->shipping_external = 1;
	    $carrier->external_module_name = 'shopgate';
	    foreach (Language::getLanguages() as $language)
		$carrier->delay[$language['id_lang']] = $this->l('Depends on Shopgate selected carrier');
	    if(!$carrier->add())
		return false;
	}

	// Creates new order states
	$this->addOrderState('PS_OS_SHOPGATE', $this->l('Shipping blocked (Shopgate)'));
	$this->addOrderState('PS_OS_MOBILE', $this->l('Preparation in progress (Shopgate)'));
	
	// Save default configurations
	$this->configurations['SHOPGATE_CARRIER_ID'] = $carrier->id;
	$this->configurations['SHOPGATE_LANGUAGE_ID'] = Configuration::get('PS_LANG_DEFAULT');
   
	foreach($this->configurations as $name => $value)
	    if(!Configuration::updateValue($name, $value))
		return false;

	return true;
    }
    
    function uninstall()
    {
    	$shopgateConfig = new ShopgateConfigPresta();
    	
		// Disable shopgate api
		$shopgateConfig->setShopIsActive(false);
		try{
			$shopgateConfig->saveFile(array('shop_is_active'));
		}catch(ShopgateLibraryException $ex){}
	
    	// Keeps order states
		unset($this->configurations['PS_OS_SHOPGATE'], $this->configurations['PS_OS_MOBILE']);
    
		// Remove configurations
		foreach($this->configurations as $name => $value)
	 	   if(!Configuration::deleteByName($name))
		return false;

		// Uninstall
		return parent::uninstall();
    }
    
	public function getTranslation($string)
	{
		return array_key_exists($string, $this->shopgate_trans) ? $this->shopgate_trans[$string] : $string;
	}
    
    
    private function addOrderState($state, $name)
    {
	$orderState = new OrderState((int)Configuration::get($state));
	if(!Validate::isLoadedObject($orderState))
	{
	    //Creating new order state
	    $orderState->color = 'lightblue';
	    $orderState->unremovable = 1;
	    $orderState->name = array();
	    foreach (Language::getLanguages() as $language)
		$orderState->name[$language['id_lang']] = $name;
	    if(!$orderState->add())
		return false;
	    
	    copy(dirname(__FILE__).'/logo.gif', dirname(__FILE__).'/../../img/os/'.(int)$orderState->id.'.gif');
	}

	return ($this->configurations[$state] = $orderState->id);
    }
    
    
    //Carrie module methods
    public function getOrderShippingCost($params, $shipping_cost)
    {
	return (float)($this->getOrderShippingCostExternal($params) + $shipping_cost);
    }
    public function getOrderShippingCostExternal($cart)
    {
	$shopgateOrder = PSShopgateOrder::instanceByCartId($cart->id);
	return Validate::isLoadedObject($shopgateOrder) ? $shopgateOrder->shipping_cost : 0;
    }
    
    
    public function hookHeader()
    {
    	$shopgateConfig = new ShopgateConfigPresta();
		$cfg = $shopgateConfig->toArray();
		
	if ( isset($cfg["shop_is_active"]) && isset($cfg["enable_mobile_website"]) && $cfg["shop_is_active"] && $cfg["enable_mobile_website"] )
	{
	    // instantiate and set up redirect class
	    $shopgateBuilder = new ShopgateBuilder($shopgateConfig);
	    $shopgateRedirector = $shopgateBuilder->buildRedirect();
	 
	    /*
	     The redirect class is usually able to determine whether to use HTTP or HTTPS to load the external
	     button images from Shopgate. However, in certain constellations the detection fails and causes
	     (false) security alerts to the visitor of your shop. Call this method to permanently activate
	     downloading via HTTPS.
	    */
	    if (!empty($cfg['always_use_ssl']))
		    $cfg->setAlwaysUseSSL();
	 
	    //Call to enable redirect keyword updates. You can pass the interval (in hours) after which keywords are updated.
	    $shopgateRedirector->enableKeywordUpdate(24);
	 
	    // Use this to set a different description for your mobile header button.
	    $shopgateRedirector->setButtonDescription($this->l('Activate mobile site'));
	 
	 
	    /* redirect logic */
	 
	    // check request for mobile devices
	    if ($shopgateRedirector->isRedirectAllowed() && $shopgateRedirector->isMobileRequest() )
	    {
			$redirectionUrl = null;
		 
			if ($id_product = Tools::getValue('id_product', 0))
			{
			    $productId = PSShopgatePlugin::prefix.$id_product.'_0';
			    $redirectionUrl = $shopgateRedirector->getItemUrl($productId);
			}
			elseif ($id_category = Tools::getValue('id_category', 0))
			    $redirectionUrl = $shopgateRedirector->getCategoryUrl($id_category);
			else
			    $redirectionUrl = $shopgateRedirector->getShopUrl();
	 
			// perform the redirect
			$shopgateRedirector->redirect($redirectionUrl);
	    }
	    elseif ( $shopgateRedirector->isMobileRequest() && !$shopgateRedirector->isRedirectAllowed() )
		echo $shopgateRedirector->getMobileHeader();
	}
    }
   
    public function hookUpdateOrderStatus($params)
    {
	$id_order = $params['id_order'];
	$orderState = $params['newOrderStatus'];
	$shopgateOrder = PSShopgateOrder::instanceByOrderId($id_order);
	
	$shopgateConfig = new ShopgateConfigPresta();
	$shopgateBuilder = new ShopgateBuilder($shopgateConfig);
	$shopgateMerchantApi = $shopgateBuilder->buildMerchantApi();
	
	if(!Validate::isLoadedObject($shopgateOrder))
	    return;
    
    	try
	{
	    switch($orderState->id)
	    {
		case _PS_OS_DELIVERED_:
		    $shopgateMerchantApi->setOrderShippingCompleted($shopgateOrder->order_number);
		    break;
		case _PS_OS_SHIPPING_:
		    $shopgateMerchantApi->addOrderDeliveryNote($shopgateOrder->order_number, $shopgateOrder->shipping_service, $shopgateOrder->tracking_number, true);
		    break;
		default:
		    break;
	    }
	}
	catch(ShopgateMerchantApiException $e)
	{
	    $msg = new Message();
	    $msg->message = $this->l('On order state').': '.$orderState->name.' - '.$this->l('Shopgate status was not updated because of following error').': '.$e->getMessage();
	    $msg->id_order = $id_order;
	    $msg->id_employee = isset($params['cookie']->id_employee) ? $params['cookie']->id_employee : 0;
	    $msg->private = true;
	    $msg->add();
	}
    }

    public function hookAdminOrder($params)
    {
	global $smarty, $cookie;
    	$id_order = $params['id_order'];

	$shopgateOrder = PSShopgateOrder::instanceByOrderId($id_order);

	if(Tools::isSubmit('updateShopgateOrder'))
	{
	    $shopgateOrder->shipping_service = $_POST['shopgateOrder']['shipping_service'];
	    $shopgateOrder->tracking_number = $_POST['shopgateOrder']['tracking_number'];
	    $shopgateOrder->update();
	}
	
	if(!Validate::isLoadedObject($shopgateOrder))
	    return '';

	$order = null;
	$error = null;
	try
	{
		$shopgateConfig = new ShopgateConfigPresta();
		$shopgateBuilder = new ShopgateBuilder($shopgateConfig);
		$shopgateMerchantApi = $shopgateBuilder->buildMerchantApi();
	    $orders = $shopgateMerchantApi->getOrders(array('order_numbers[0]'=>$shopgateOrder->order_number));
	    foreach($orders->getData() as $o)
		if($o->getOrderNumber() == $shopgateOrder->order_number)
		    $order = $o;
	}
	catch(ShopgateMerchantApiException $e)
	{
	    $error = $e->getMessage();
	}
	
	$paymentInfoStrings = array
	(
	    'shopgate_payment_name' => $this->l('Payment name'),
	    'upp_transaction_id' => $this->l('Transaction ID'),
	    'authorization' => $this->l('Authorization'),
	    'settlement' => $this->l('Settlement'),
	    'purpose' => $this->l('Purpose'),
	    'billsafe_transaction_id' => $this->l('Transaction ID'),
	    'reservation_number' => $this->l('Reservation number'),
	    'activation_invoice_number' => $this->l('Invoice activation number'),
	    'bank_account_holder' => $this->l('Account holder'),
	    'bank_account_number' => $this->l('Account number'),
	    'bank_code' => $this->l('Bank code'),
	    'bank_name' => $this->l('Bank name'),
	    'iban' => $this->l('IBAN'),
	    'bic' => $this->l('BIC'),
	    'transaction_id' => $this->l('Transaction ID'),
	    'payer_id' => $this->l('Payer ID'),
	    'payer_email' => $this->l('Payer email')
	);

	$smarty->assign('order', $order);
	$smarty->assign('shopgate_error', $error);
	$smarty->assign('paymentInfoStrings', $paymentInfoStrings);
	$smarty->assign('shopgateOrder', $shopgateOrder);
	$smarty->assign('shipping_service_list', $this->shipping_service_list);
	$smarty->assign('mod_dir', $this->_path);
	$smarty->assign('api_url', Tools::getHttpHost(true, true).$this->_path.'api.php');
	
	return $this->display(__FILE__, 'tpl/admin_order.tpl');
    }

    public function getContent()
    {
	global $cookie, $smarty;
	
	$output = '';
	$shopgateConfig = new ShopgateConfigPresta();
	
	$bools = array('true'=>true, 'false'=>false);

	if(Tools::isSubmit('saveConfigurations'))
	{
	    $configs = Tools::getValue('configs', array());
	    foreach($configs as $name => $value)
		if(isset($bools[$value])) $configs[$name] = $bools[$value];
	    
	    $configs['use_stock'] = !((bool)Configuration::get('PS_ORDER_OUT_OF_STOCK'));

	    $settings = Tools::getValue('settings', array());
	    foreach($settings as $key => $value)
		Configuration::updateValue($key, $value);
	    
	    Configuration::updateValue('SHOPGATE_LANGUAGE_ID', Language::getIdByIso($configs["language"]));
	    
	    try
	    {
	    	$shopgateConfig->loadArray($configs);
	    	$shopgateConfig->saveFile(array_keys($configs));
			$output .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />'.$this->l('Configurations updated').'</div>';
	    } catch (ShopgateLibraryException $e)
	    {
		$output .= '<div class="conf error"><img src="../img/admin/error.png" alt="'.$this->l('Error').'" />'.$this->l('Error').': '.$e->getAdditionalInformation().'</div>';
	    }
	}
	
	$langs = array();
	foreach(Language::getLanguages() as $id => $l)
	    $langs[strtoupper($l['iso_code'])] = $l['name'];

	$servers = array
	(
	    'live'=>$this->l('Live'),
	    'pg'=>$this->l('Playground'),
	    'custom'=>$this->l('Custom')
	);

	$enables = array();

	$settings = Configuration::getMultiple(array('SHOPGATE_SHIPPING_SERVICE', 'SHOPGATE_MIN_QUANTITY_CHECK', 'SHOPGATE_OUT_OF_STOCK_CHECK'));
	$shopgateConfig = new ShopgateConfigPresta();
	$configs = $shopgateConfig->toArray();
	
	$smarty->assign('settings', $settings);
	$smarty->assign('shipping_service_list', $this->shipping_service_list);
	$smarty->assign('langs', $langs);
	$smarty->assign('currencies', Currency::getCurrencies());
	$smarty->assign('servers', $servers);
	$smarty->assign('enables', $enables);
	$smarty->assign('configs', $configs);
	$smarty->assign('mod_dir', $this->_path);
	$smarty->assign('api_url', Tools::getHttpHost(true, true).$this->_path.'api.php');
	
	return $output.$this->display(__FILE__, 'tpl/configurations.tpl');
    }
}
