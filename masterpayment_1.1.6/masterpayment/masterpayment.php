<?php

if (!defined('_PS_VERSION_'))
	exit;

define('MP_DIR', 'modules/masterpayment/');

class MasterPayment extends PaymentModule
{
	const txPrefix = 'txn';
	private $_html = '';
		
	public function __construct()
	{
		$this->name = 'masterpayment';
		$this->tab = 'payments_gateways';
		$this->version = '1.1.6';
		$this->author = 'Silbersaiten';

		parent::__construct();

		$this->displayName = $this->l('MasterPayment');
		$this->description = $this->l('Accepts payments by MasterPayment.');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');

		if (!Configuration::get('MP_MERCHANT_NAME') || !Configuration::get('MP_SECRET_KEY'))
			$this->warning = $this->l('Configurations required!');

		/** Backward compatibility 1.4 and 1.5 */
		if (substr(_PS_VERSION_, 0, 3) == '1.4')
			require(_PS_MODULE_DIR_.'/masterpayment/backward_compatibility/backward.php');
	}

	public function install()
	{
		if
		(
			!parent::install() ||
			!$this->registerHook('payment') ||
			!$this->registerHook('adminOrder') ||
			!$this->registerHook('leftColumn') ||
			!$this->registerHook('paymentReturn')
		)
			return false;

		//Save default configurations
		foreach($this->_getDefaults() as $key => $value)
			Configuration::updateValue($key, $value);
		
		//Add masterpayment order state if one not exists
		if(!Configuration::get('PS_OS_MASTERPAYMENT'))
		{
			//Creating new order state
			$orderState = new OrderState();
			$orderState->color = 'lightblue';
			$orderState->unremovable = 1;
			$orderState->name = array();
			foreach (Language::getLanguages() as $language)
			    $orderState->name[$language['id_lang']] = $this->l('Awaiting MasterPayment payment');
			if(!$orderState->add())
			    return false;
			
			copy(dirname(__FILE__).'/logo.gif', dirname(__FILE__).'/../../img/os/'.(int)$orderState->id.'.gif');
			Configuration::updateValue('PS_OS_MASTERPAYMENT', $orderState->id);
		}
	
		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall())
			return false;

		//Remove configurations	
		foreach($this->_getDefaults() as $key => $value)
			Configuration::deleteByName($key);

		return true;
	}
	
	
	private function _getDefaults()
	{
		global $link;
		return array
		(
			'MP_MERCHANT_NAME' => '',		
			'MP_SECRET_KEY' => '',
			'MP_GATEWAY_URL' => 'https://www.masterpayment.com/en/payment/gateway',

			'MP_MODE' => 'iframe',
			'MP_ORDER_CONFIRM' => 1,
			'MP_ORDER_CREATE' => 0,
	
			'MP_LANGUAGE' => 'EN',
			'MP_CURRENCY' => 'EUR',
			'MP_GATEWAY_STYLE' => 'standart',
			'MP_PAYMENT_METHODS' => 'none',
			'MP_CANCEL_OPTION' => 1,
			
			'MP_INSTALLMENTS_COUNT' => 6,
			'MP_INSTALLMENTS_PERIOD' => 'use_freq',
			'MP_INSTALLMENTS_FREQ' => 30,
			'MP_RECURRENT_PERIOD' => 'monthly',
			'MP_PAYMENT_DELAY' => 15,
			'MP_DUE_DAYS' => 14,
		);
	}
	
	public function getPaymentMethods()
	{
		return array
		(
			'none' 		=> $this->l('MasterPayment'),
			'credit_card' 	=> $this->l('Credit Card'),
			'debit_card' 	=> $this->l('Carte Bleue'),
//			'deferred_debit'=> $this->l('Deffered Debit'),
			'elv' 		=> $this->l('Lastschrift'),
			'elv_triggered' => $this->l('Gewinnspiele'),
			'phone' 	=> $this->l('Pay by Call'),
			'sofortbanking' => $this->l('Sofort Banking'),

			'anzahlungskauf'=> $this->l('Anzahlungskauf'),
			'finanzierung' 	=> $this->l('Finanzierung'),
			'ratenzahlung' 	=> $this->l('Ratenzahlung'),
			'rechnungskauf' => $this->l('Rechnungskauf'),

			'cc_recurrent' 	=> $this->l('Credit Card Recurring'),	//??
			'elv_recurrent' => $this->l('Lastschrift Recurrent'),	//??
		);		
	}
	
	public function getGatewayStyles()
	{
		return array
		(
			'standard' 	=> $this->l('Standard'),
			'lieferando' 	=> $this->l('Lieferando'),
			'motosino' 	=> $this->l('Motosino'),
			'gimahot' 	=> $this->l('Gimahot'),
			'avandeo' 	=> $this->l('Avandeo'),
			'harotec' 	=> $this->l('Harotec'),
			'mobile' 	=> $this->l('Mobile'),
			'afterbuy' 	=> $this->l('Afterbuy'),
			'afterbuy_shop'	=> $this->l('Afterbuy Shop')
		);
	}
	
	public function getValidLanguages()
	{
		return array
		(
			'EN' => $this->l('English'),
			'DE' => $this->l('German'),
			'IT' => $this->l('Italian'),
			'ES' => $this->l('Spanish'),
			'FR' => $this->l('French'),
			'PL' => $this->l('Polish')
		);
	}
	
	public function getValidCurrencies()
	{
		return array('EUR', 'GBP');
	}
	
	public function getModes()
	{
		return array
		(
			'iframe' => $this->l('iFrame'),
			'external' => $this->l('External'),
		);		
	}
	
	public function getInstallmentsPeriods()
	{
		return array
		(
			'use_freq' => $this->l('Use frequency'),
			'monthly' => $this->l('Monthly'),
			'end_of_month' => $this->l('End of month')
		);
	}
	
	public function getRecurrentPeriods()
	{
		return array
		(
			'weekly' => $this->l('Weekly'),
			'monthly' => $this->l('Monthly'),
			'quarterly' => $this->l('Quarterly'),
			'yearly' => $this->l('Yearly')
		);		
	}
	
	public function getConfigurations()
	{
		return Configuration::getMultiple(array_keys($this->_getDefaults()));
	}
	
	public function getValidCurrency()
	{
		$valid = $this->getValidCurrencies();
		$currency = Currency::getCurrent();

		if(in_array($currency->iso_code, $valid))
			return $currency;
		else
		{
			$currencies = Currency::getCurrencies();		
			foreach($currencies as $c)
				if(in_array($c['iso_code'], $valid))
					return 	Currency::getCurrencyInstance($c['id_currency']);
		}
		
		return null;
	}
	
	/*** Configurations ***/
	public function getContent()
	{		
		if(Tools::isSubmit('saveConfigurations'))
		{
			$cfg = Tools::getValue('cfg', array());
			$cfg['MP_PAYMENT_METHODS'] = implode(',', Tools::getValue('payment_methods', array()));
			
			foreach($cfg as $key => $value)
				Configuration::updateValue($key, $value);

			$this->_html .= $this->displayConfirmation($this->l('Configuration updated'));
		}

		$cfg = $this->getConfigurations();
		
		$this->tplAssign(array
		(
			'cfg' => $cfg,
			'payment_methods' => explode(',', $cfg['MP_PAYMENT_METHODS'])
		));

		return $this->_html.$this->tplDisplay('configurations');
	}

	/*** Hooks ***/
	public function hookPayment($params)
	{
		//Check if is configured and have valid currency		
		if(!Configuration::get('MP_MERCHANT_NAME') || !Configuration::get('MP_SECRET_KEY') || !$this->getValidCurrency())
			return '';
		
		$payment_methods = explode(',', Configuration::get('MP_PAYMENT_METHODS', array()));
		
		$this->tplAssign('payment_methods', array_intersect_key($this->getPaymentMethods(), array_flip($payment_methods)));
		return $this->tplDisplay('payment');
	}	

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;
		
		$order = $params['objOrder'];
		
		if ($order->module != $this->name)
			return;

		switch($order->getCurrentState())
		{
			case Configuration::get('PS_OS_PAYMENT'):
				$this->tplAssign('status', 'ok');
				break;
			case Configuration::get('PS_OS_MASTERPAYMENT'):
				$this->tplAssign('status', 'pending');
				break;
			case Configuration::get('PS_OS_ERROR'):
			default:
				$this->tplAssign('status', 'failed');
				break;
		}

		return $this->tplDisplay('paymentReturn');
	}
	
	public function hookAdminOrder($params)
	{
		global $cookie;
		$order = new Order((int)$params['id_order']);
		$msg = null;

		if($this->name != $order->module)
			return;

		$cart = new Cart($order->id_cart);
		$currency = new Currency($order->id_currency);
		
		if(Tools::isSubmit('submitMasterPaymentRefund'))
		{
			$amount = (float)Tools::getValue('amount', 0);
			
			if($amount > 0 && $amount <= $order->total_paid)
			{
				require_once(dirname(__FILE__).'/lib/api.php');
				$api = new MasterPaymentApi();
				
				$api->merchantName = Configuration::get('MP_MERCHANT_NAME');
				$api->secretKey = Configuration::get('MP_SECRET_KEY');
				$api->basketValue = $amount * 100;
				$api->txId = self::encodeTxID($cart);
	
				$comment = Tools::getValue('comment', '');
				$status = $api->refundRequest($comment);
	
				if($status == MasterPaymentApi::STATUS_REFUNDED)
				{
					// Update order state
					$order->setCurrentState(Configuration::get('PS_OS_REFUND'), $cookie->id_employee);
		
					// Add refund amount message
					$msg = new Message();
					$msg->message = $comment.' - '.$this->l('Refund amount').': '.Tools::displayPrice($amount, $currency);
					$msg->id_order = $order->id;
					$msg->id_customer = $cart->id_customer;
					$msg->private = true;
					$msg->add();
		
					// Redirect to order
					Tools::redirectAdmin('#');
				}
				else
					$msg = '<p class="error">'.$comment.'</p>';
			}
			else
				$msg = '<p class="error">'.$this->l('Ivalid amount').'</p>';
		}
		
		$this->tplAssign('msg', $msg);
		$this->tplAssign('order', $order);
		$this->tplAssign('amount', Tools::ps_round($order->total_paid,  2));
		$this->tplAssign('currency', $currency);
		$this->_html .= $this->tplDisplay('adminOrder');
		
		return $this->_html;
	}
	
	public function hookRightColumn($params)
	{
		return $this->tplDisplay('column');
	}

	public function hookLeftColumn($params)
	{
		return $this->hookRightColumn($params);
	}
	
	
	/*** Misc ***/
	static public function encodeTxID($cart)
	{
		return self::txPrefix.$cart->id;
	}
	
	static public function decodeTxID($txID)
	{
		return intval(substr($txID, strlen(self::txPrefix), strlen($txID)));
	}
	
	public function tplAssign($var, $val = null)
	{
		$this->context->smarty->assign($var, $val);
	}
	
	public function tplDisplay($tpl)
	{
		$this->context->smarty->assign(array
		(
			'mod_dir' => $this->_path,
			'this' => $this,
			'link' => $this->context->link
		));		
		return self::display(__FILE__, 'views/templates/hooks/'.$tpl.'.tpl');
	}
}


