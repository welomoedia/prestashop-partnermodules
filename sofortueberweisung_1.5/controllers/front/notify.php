<?php
class SofortueberweisungNotifyModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
	$log = null;
	$this->display_header = false;
	$this->display_footer = false;
	parent::initContent();
	
	$transaction = Tools::getValue('transaction');
	
	if (Tools::isEmpty($transaction)) {
	    die(Tools::displayError('Wrong use of the HTTP notifications. Please set up your sofortüberweisung.de notification to call this URL only with POST method.'));
	}
	
	$orderState = _PS_OS_PAYMENT_;
	
	$baseDir = dirname(__FILE__);
	
	$useSSL = true;
	
	// Get class instance
	$su = new Sofortueberweisung();
	
	// Set order state to "awaiting payment"
	$orderState = Configuration::get('_SU_OS_OK_');
	$cartId = 0;
	
	// Check incoming response
	if ($su->checkResponse($_POST, $log)) {
	    // Get cartId from the response
	    $cartId = (int)(Tools::getValue('user_variable_0'));

	    // Get the cart object
	    $cart = new Cart($cartId);
	    
	    // Get the customer object
	    $customer = new Customer((int)$cart->id_customer);
	
	    // Get order sum
	    $orderSum = (float)Tools::getValue('amount');
	    
	    $currency_special = $cart->id_currency;
	    $message = 'Payment through Sofortüberweisung.de - Transaction-ID: ' . $transaction . ' ';
	    $secure_key = $customer->secure_key;
	
	    // Create order
	    $su->updateOrder($cartId, $orderState, $orderSum, $message, $currency_special, $secure_key);
	}
    }
}