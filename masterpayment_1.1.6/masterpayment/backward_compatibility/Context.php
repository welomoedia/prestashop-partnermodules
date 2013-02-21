<?php

class Context
{
	protected static $instance;
	public $cart;
	public $customer;
	public $cookie;
	public $link;
	public $country;
	public $employee;
	public $controller;
	public $language;
	public $currency;
	public $tab;
	public $shop;
	public $smarty;

	public function __construct()
	{
		global $cookie, $cart, $smarty, $link;

		$this->tab = null;

		$this->cookie = $cookie;
		$this->cart = $cart;
		$this->smarty = $smarty;
		$this->link = $link;

		$this->controller = new ControllerBackwardModule();
		if (is_object($cookie))
		{
			$this->currency = new Currency((int)$cookie->id_currency);
			$this->language = new Language((int)$cookie->id_lang);
			$this->country = new Country((int)$cookie->id_country);
			$this->customer = new CustomerBackwardModule((int)$cookie->id_customer);
			$this->employee = new Employee((int)$cookie->id_employee);
		}
		else
		{
			$this->currency = null;
			$this->language = null;
			$this->country = null;
			$this->customer = null;
			$this->employee = null;
		}
		$this->shop = new ShopBackwardModule();
	}

	public static function getContext()
	{
		if (!isset(self::$instance))
			self::$instance = new Context();
		return self::$instance;
	}

	public function cloneContext()
	{
		return clone($this);
	}

	public static function shop()
	{
		if (!self::$instance->shop->getContextType())
			return ShopBackwardModule::CONTEXT_ALL;
		return self::$instance->shop->getContextType();
	}
}

class ShopBackwardModule extends Shop
{
	const CONTEXT_ALL = 1;

	public $id = 1;
	public $id_shop_group = 1;
	
	public function getContextType(){return ShopBackwardModule::CONTEXT_ALL;}
	public function getID(){return 1;}
	public function getTheme(){return _THEME_NAME_;}
}

class ControllerBackwardModule
{
	public function addJS($js_uri){Tools::addJS($js_uri);}
	public function addCSS($css_uri, $css_media_type = 'all'){Tools::addCSS($css_uri, $css_media_type);}
	public function addJquery()
	{
		if (_PS_VERSION_ < '1.5')
			$this->addJS(_PS_JS_DIR_.'jquery/jquery-1.4.4.min.js');
		elseif (_PS_VERSION_ >= '1.5')
			$this->addJS(_PS_JS_DIR_.'jquery/jquery-1.7.2.min.js');
	}

}

class CustomerBackwardModule extends Customer
{
	public $logged = false; 

	public function isLogged($with_guest = false)
	{
		if (!$with_guest && $this->is_guest == 1)
			return false;

		/* Customer is valid only if it can be load and if object password is the same as database one */
		if ($this->logged == 1 && $this->id && Validate::isUnsignedId($this->id) && Customer::checkPassword($this->id, $this->passwd))
			return true;
		return false;
	}
}
