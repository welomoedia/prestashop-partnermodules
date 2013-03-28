<?php

class PSShopgatePlugin extends ShopgatePlugin
{
	private $link;
	private $id_lang = 1;
	private $id_currency = 0;
	private $currency_iso = false;

	const prefix = 'BD';

	public function startup(){
		global $link, $cookie;
		$this->link = $link;
		$this->id_lang = Configuration::get('SHOPGATE_LANGUAGE_ID');

		//Set configs that depends on prestashop settings
		require_once(dirname(__FILE__) . '/PSShopgateConfig.php');
		$this->config = new ShopgateConfigPresta();
		
		$config = $this->config->toArray();
		
		$this->id_currency = $cookie->id_currency = Currency::getIdByIsoCode($config['currency']);
		
		$this->setCurrencyIso();

		$this->config->setUseStock( ! ((bool)Configuration::get('PS_ORDER_OUT_OF_STOCK')));
	}
	
	private function setCurrencyIso() {
		$this->currency_iso = strtoupper(Db::getInstance()->getValue('
			SELECT
				`iso_code`
			FROM
				`' . _DB_PREFIX_ . 'currency`
			WHERE
				`id_currency` = ' . (int)$this->id_currency)
		);
	}

	public function getCustomer($user, $pass){
		$id_customer = (int)Db::getInstance()->getValue
		('
		SELECT `id_customer`
		FROM `'._DB_PREFIX_.'customer`
		WHERE
		`active` AND
		`email` = \''.pSQL($user).'\' AND
		`passwd` = \''.md5(pSQL(_COOKIE_KEY_.$pass)).'\' AND
		`deleted` = 0  AND
		`is_guest` = 0
		');

		if(!$id_customer)
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD, 'Username or password is incorrect');

		$customer = new Customer($id_customer);

		$gender = array(
			1 => 'm',
			2 => 'f',
			9 => null
		);

		$shopgateCustomer = new ShopgateCustomer();

		$shopgateCustomer->setCustomerId($customer->id);
		$shopgateCustomer->setCustomerNumber($customer->id);
		$shopgateCustomer->setCustomerGroup(Db::getInstance()->getValue('SELECT `name` FROM `'._DB_PREFIX_.'group_lang` WHERE `id_group`=\''.$customer->id_default_group.'\' AND `id_lang`='.$this->id_lang));
		$shopgateCustomer->setCustomerGroupId($customer->id_default_group);
		$shopgateCustomer->setFirstName($customer->firstname);
		$shopgateCustomer->setLastName($customer->lastname);
		$shopgateCustomer->setGender(isset($gender[$customer->id_gender]) ? $gender[$customer->id_gender] : null);
		$shopgateCustomer->setBirthday($customer->birthday);
		$shopgateCustomer->setMail($customer->email);
		$shopgateCustomer->setNewsletterSubscription($customer->newsletter);

		$addresses = array();
		foreach($customer->getAddresses($this->id_lang) as $a){
			$address = new ShopgateAddress();
			 
			$address->setId($a['id_address']);
			$address->setFirstName($a['firstname']);
			$address->setLastName($a['lastname']);
			$address->setCompany($a['company']);
			$address->setStreet1($a['address1']);
			$address->setStreet2($a['address2']);
			$address->setCity($a['city']);
			$address->setZipcode($a['postcode']) ;
			$address->setCountry($a['country']);
			$address->setState($a['state']);
			$address->setPhone($a['phone']);
			$address->setMobile($a['phone_mobile']);

			array_push($addresses, $address);
		}

		$shopgateCustomer->setAddresses($addresses);

		return $shopgateCustomer;
	}


	private function getPSAddress(Customer $customer, ShopgateAddress $shopgateAddress){
		//Get country
		$id_country = Country::getByIso($shopgateAddress->getCountry());
		if(!$id_country)
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_UNKNOWN_COUNTRY_CODE, 'Invalid country code:'.$id_country, true);

		//Get state
		$id_state = 0;
		if($shopgateAddress->getState())
			$id_state = (int)Db::getInstance()->getValue('SELECT `id_state` FROM `'._DB_PREFIX_.'state` WHERE `id_country` = '.$id_country.' AND `iso_code` = \''.pSQL(substr($shopgateAddress->getState(), 3, 2)).'\'');

		//	if($shopgateAddress->getState() && !($id_state = State::getIdByIso($shopgateAddress->getState())))
			//	    throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_UNKNOWN_STATE_CODE, 'Invalid state code:'.$shopgateAddress->getState());

		//Create alias
		$alias = 'Shopgate_'.$shopgateAddress->getId();

		//Try getting address id by alias
		$id_address = Db::getInstance()->getValue('SELECT `id_address` FROM `'._DB_PREFIX_.'address` WHERE `alias` = \''.pSQL($alias).'\' AND `id_customer`='.$customer->id);

		//Get or create address
		$address = new Address($id_address ? $id_address : (int)$shopgateAddress->getId());
		if(!$address->id){
			$address->id_customer = $customer->id;
			$address->id_country = $id_country;
			$address->id_state = $id_state;
			$address->country = Country::getNameById($this->id_lang, $address->id_country);
			$address->alias = $alias;
			$address->company = $shopgateAddress->getCompany();
			$address->lastname = $shopgateAddress->getLastName();
			$address->firstname = $shopgateAddress->getFirstName();
			$address->address1 = $shopgateAddress->getStreet1();
			$address->address2 = $shopgateAddress->getStreet2();
			$address->postcode = $shopgateAddress->getZipcode();
			$address->city = $shopgateAddress->getCity();
			$address->phone = $shopgateAddress->getPhone();
			$address->phone_mobile = $shopgateAddress->getMobile();
			if(!$address->add())
				throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Unable to create address', true);
		}

		return $address;
	}

	private function getProductIdentifiers(ShopgateOrderItem $item){
		return explode('_', substr($item->getItemNumber(), strlen(self::prefix)));
	}
	
	private function getOrderStateId($order_state_var) {
		return (int)(defined($order_state_var) ? constant($order_state_var) : Configuration::get($order_state_var));
	}

	public function addOrder(ShopgateOrder $order)
	{
		$shopgateOrder = PSShopgateOrder::instanceByOrderNumber($order->getOrderNumber());
		if($shopgateOrder->id)
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DUPLICATE_ORDER, 'Duplicate order', true);
	    
		//Check product quantitys
		$products = array();
		$settings = Configuration::getMultiple(array('SHOPGATE_MIN_QUANTITY_CHECK', 'SHOPGATE_OUT_OF_STOCK_CHECK'));

		foreach($order->getItems() as $i){
			list($id_product, $id_product_attribute) = $this->getProductIdentifiers($i);
			
			if ($id_product == 0)
			{
				continue;
			}

			$wantedQty = (int)$i->getQuantity();
			$product = new Product($id_product, false, (int)Configuration::get('PS_LANG_DEFAULT'));

			if((int)$id_product_attribute){
				$stockQty = (int)Product::getQuantity((int)$id_product, (int)$id_product_attribute);
				$minQty = Attribute::getAttributeMinimalQty((int)$id_product_attribute);
			} else {
				$stockQty = (int)Product::getQuantity((int)$id_product, NULL);
				$minQty = (int)$product->minimal_quantity;
			}
			
			$oos_available = Product::isAvailableWhenOutOfStock($product->out_of_stock);
			
			$qtyDifference = 0;
			
			if ( ! $oos_available && $wantedQty > $stockQty)
			{
				$qtyDifference = $wantedQty - $stockQty;
			}
			 
			$p = array();
			$p['id_product'] = (int)$id_product;
			$p['id_product_attribute'] = (int)$id_product_attribute;
			$p['name'] = $product->name;
			$p['quantity'] = $wantedQty;
			$p['quantity_in_stock'] = $stockQty;
			$p['quantity_difference'] = $qtyDifference;

			if ($oos_available)
			{
				$stockQty = $wantedQty;
			}

			if((bool)$settings['SHOPGATE_MIN_QUANTITY_CHECK'] && $wantedQty < $minQty)
				throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Minimum quantity required', true);

			if((bool)$settings['SHOPGATE_OUT_OF_STOCK_CHECK'] && $wantedQty > $stockQty)
				throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Out of stock', true);

			array_push($products, $p);
		}

		//Get or create customer
		$id_customer = Customer::customerExists($order->getMail(), true, false);
		$customer = new Customer($id_customer ? $id_customer : (int)$order->getExternalCustomerId());
		if(!$customer->id)
		{
			$customer->lastname = $order->getInvoiceAddress()->getLastName();
			$customer->firstname = $order->getInvoiceAddress()->getFirstName();
			$customer->email = $order->getMail();
			$customer->passwd = md5(_COOKIE_KEY_.time());
			$customer->is_guest = (int)Configuration::get('PS_GUEST_CHECKOUT_ENABLED');
			if(!$customer->add())
				throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Unable to create customer', true);
		}

		//Get invoice and delivery addresses
		$invoiceAddress = $this->getPSAddress($customer, $order->getInvoiceAddress());
		$deliveryAddress = ($order->getInvoiceAddress() == $order->getDeliveryAddress()) ? $invoiceAddress : $this->getPSAddress($customer, $order->getDeliveryAddress());

		//Creating currency
		$id_currecy = $order->getCurrency() ? Currency::getIdByIsoCode($order->getCurrency()) : $this->id_currency;
		$currency = new Currency($id_currecy ? $id_currecy : $this->id_currency);

		//Creating new cart
		$cart = new Cart();
		$cart->id_lang = $this->id_lang;
		$cart->id_currency = $currency->id;
		$cart->id_address_delivery = $deliveryAddress->id;
		$cart->id_address_invoice = $invoiceAddress->id;
		$cart->id_customer = $customer->id;
		$cart->id_guest = $customer->is_guest;
		$cart->recyclable = 0;
		$cart->gift = 0;
		$cart->id_carrier = (int)Configuration::get('SHOPGATE_CARRIER_ID');
		$cart->secure_key = $customer->secure_key;

		if(!$cart->add())
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Unable to create cart', true);

		//Adding items to cart
		foreach($products as $p){
			//TODO deal with customizations
			$id_customization = false;
			$cart->updateQty($p['quantity'] - $p['quantity_difference'], $p['id_product'], $p['id_product_attribute'], $id_customization, 'up', $deliveryAddress->id);
			
			if ($p['quantity_difference'] > 0)
			{
				$message = new Message();
				$message->id_cart = $cart->id;
				$message->private = 1;
				$message->message = 'Warning, wanted quantity for product "' . $p['name'] . '" was ' . $p['quantity'] . ' unit(s), however, the amount in stock is ' . $p['quantity_in_stock'] . ' unit(s). Only ' . $p['quantity_in_stock'] . ' unit(s) were added to the order';
				
				$message->save();
			}
		}

		$id_order_state = 0;

		$shopgate = new Shopgate();
		$payment_name = $shopgate->getTranslation('Mobile Payment');
		
		if($order->getIsPaid())
			$id_order_state = $this->getOrderStateId('PS_OS_PAYMENT');
		else
		{
			switch($order->getPaymentMethod())
			{
				case 'SHOPGATE': 	$id_order_state = $this->getOrderStateId('PS_OS_SHOPGATE'); $payment_name = $shopgate->getTranslation('Shopgate'); break;
				case 'PREPAY': 		$id_order_state = $this->getOrderStateId('PS_OS_BANKWIRE'); $payment_name = $shopgate->getTranslation('Bankwire'); break;
				case 'COD': 		$id_order_state = $this->getOrderStateId('PS_OS_PREPARATION'); $payment_name = $shopgate->getTranslation('Cash on Delivery'); break;
				case 'PAYPAL': 		$id_order_state = $this->getOrderStateId('PS_OS_PAYPAL'); $payment_name = $shopgate->getTranslation('PayPal'); break;
				case 'DEBIT':       $id_order_state = $this->getOrderStateId('PS_OS_PREPARATION'); break;
				default: 		    $id_order_state = $this->getOrderStateId('PS_OS_PREPARATION');  break;
			}
		}
		
		//Creates shopgate order record and save shipping cost for future use
		$shopgateOrder = new PSShopgateOrder();
		$shopgateOrder->order_number = $order->getOrderNumber();
		$shopgateOrder->shipping_cost = $order->getAmountShipping() + $order->getAmountShopPayment();
		$shopgateOrder->shipping_service = Configuration::get('SHOPGATE_SHIPPING_SERVICE');
		$shopgateOrder->id_cart = $cart->id;
		if(!$shopgateOrder->add())
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Unable to create shopgate order', true);
		
		//PS 1.5 compatibility
		if(_PS_VERSION_ >= 1.5)
		{
			$this->context = Context::getContext();
			$this->context->cart = $cart;
			$cart->setDeliveryOption(array($cart->id_address_delivery => $cart->id_carrier.','));
			$cart->update();
			$cart->id_carrier = (int)Configuration::get('SHOPGATE_CARRIER_ID');
		}

		$shopgate->validateOrder(
			$cart->id,
			$id_order_state,
			$order->getAmountComplete(),
			$payment_name,
			NULL,
			array(),
			NULL,
			false,
			$cart->secure_key
		);
		
		if ((int)$shopgate->currentOrder > 0)
		{
			$shopgateOrder->id_order = $shopgate->currentOrder;
			$shopgateOrder->update();
			
			return array(
				'external_order_id' => $shopgate->currentOrder,
				'external_order_number' => $shopgate->currentOrder
			);
		}
		else
		{
			$shopgateOrder->delete();
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'Unable to create order', true);
		}
	}

	public function cron($jobname, $params, &$message, &$errorcount){
		return;
	}
	
	public function updateOrder(ShopgateOrder $order){
		$shopgateOrder = PSShopgateOrder::instanceByOrderNumber($order->getOrderNumber());

		if(!Validate::isLoadedObject($shopgateOrder))
			throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_ORDER_NOT_FOUND, 'Order not found', true);
			
		$order_states = array();

		if($order->getUpdatePayment() && $order->getIsPaid())
			array_push($order_states, (int)Configuration::get('PS_OS_PAYMENT'));

		if($order->getUpdateShipping() && !$order->getIsShippingBlocked())
			array_push($order_states, (int)Configuration::get('PS_OS_PREPARATION'));

		if(count($order_states)){
			$ps_order = new Order($shopgateOrder->id_order);
			foreach($order_states as $id_order_state)
				$ps_order->setCurrentState($id_order_state);
		}
		return array(
			'external_order_id' => $shopgateOrder->id_order,
			'external_order_number' => $shopgateOrder->id_order
		);
	}
	
	protected static function convertProductWeightToGrams($weight)
	{
		$ps_weight_unit = strtolower(Configuration::get('PS_WEIGHT_UNIT'));
		
		$multipliers = array(
			'kg' => 1000,
			'lbs' => 453.59237
		);
		
		if (array_key_exists($ps_weight_unit, $multipliers))
		{
			$weight*= $multipliers[$ps_weight_unit];
		}
		
		return $weight;
	}
	
	
	public static function getHighlightProducts()
	{
		$prepared = array();
		
		$result = Db::getInstance()->ExecuteS('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_category` = 1');
		
		if ($result && sizeof($result))
		{
			foreach ($result as $product)
			{
				array_push($prepared, (int)$product['id_product']);
			}
		}
		
		$prepared = array_unique($prepared);
		
		return $prepared;
	}
	
	private static function roundPricesInArray(&$array, $keys_to_round) {
		if ( ! is_array($keys_to_round) || ! sizeof($keys_to_round)) {
			return false;
		}
		
		foreach ($keys_to_round as $round_key) {
			if (array_key_exists($round_key, $array)) {
				$array[$round_key] = Tools::ps_round($array[$round_key], 2);
			}
		}
	}


	protected function createItemsCsv(){
		$limit = Tools::getValue('limit', 0);
		$offset = Tools::getValue('offset', 0);
		
		$products = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS
		('
			SELECT *
			FROM `'._DB_PREFIX_.'product` p
			WHERE `available_for_order` = 1 AND `active` = 1
			ORDER BY `id_product` DESC'.
			($limit ? ' LIMIT '.$offset.', '.$limit : '')
		);
		
		$highlights = self::getHighlightProducts();
		$highlight_index = 1;

		$maxSortOrderCategories = Db::getInstance()->ExecuteS
		('
			SELECT id_category, MAX(position) as max_position
			FROM `'._DB_PREFIX_.'category_product`
			GROUP BY `id_category`'
		);
		
		$maxSortOrderByCategoryNumber = array();
		foreach($maxSortOrderCategories as $sortOrderCategory){
			$maxSortOrderByCategoryNumber[$sortOrderCategory['id_category']] = $sortOrderCategory['max_position'];
		}
		
		$rootCategoryIds = array();
		if(version_compare(_PS_VERSION_, '1.5.0.0', '>=')){
			// equal greater than 1.5.0.0
			$rootCategoryIds = array_keys($this->getRootCategoriesByCategoryId());
		}

		foreach($products as $p)
		{
			$product = new Product($p['id_product'], true, $this->id_lang);
			$row = $this->buildDefaultProductRow();
			$reduction = (float)$product->getPrice(true, NULL, 2, NULL, true);
			 
			$row['item_number'] =			self::prefix.$product->id.'_0';
			$row['item_name'] =			$product->name;
			$row['unit_amount'] =			$product->getPrice(true, NULL, 2);
			$row['description'] =			str_replace(array("\r", "\n"), '', $product->description);
			//$row['is_available'] =			$product->available_for_order;
			
			$availableText = '';
			if($product->available_for_order && $product->quantity > 0){
				$availableText = $product->available_now;
				$row['is_available'] = 1;
			} else {
				$availableText = $product->available_later;
				$row['is_available'] = 0;
			}
			$row['available_text'] = $availableText;
			
			$productRewriteInformation = Product::getUrlRewriteInformations($product->id);

			$row['manufacturer'] =			$product->manufacturer_name;
			$row['url_deeplink'] =			$this->link->getProductLink($product->id, $product->link_rewrite, (isset($productRewriteInformation[0]['category_rewrite']) ? $productRewriteInformation[0]['category_rewrite'] : NULL), (isset($productRewriteInformation[0]['ean13']) ? $productRewriteInformation[0]['ean13'] : NULL), $this->id_lang);
			$row['old_unit_amount'] =		$reduction != 0 ? $product->getPrice(true, NULL, 2, NULL, false, false) : 0;
			$row['manufacturer_item_number'] =	$product->id_manufacturer;
			$row['currency'] = 			$this->currency_iso;;
			$row['tax_percent'] = 			$this->formatPriceNumber($product->tax_rate);
			$row['item_number_public'] = !empty($product->reference) ? $product->reference : '';
			if($product->out_of_stock == 2){
				$row['use_stock'] =	(int)!(bool)Configuration::get('PS_ORDER_OUT_OF_STOCK');
			} else {
				$row['use_stock'] = (int)!(bool)$product->out_of_stock;
			}
			
			$is_highlight = in_array($product->id, $highlights);

			$row['stock_quantity'] = 		$product->quantity;
			$row['ean'] = 				$product->ean13;
			$row['last_update'] = 			substr($product->date_upd, 0, 10);
			$row['tags'] = 				implode(',', isset($product->tags[$this->id_lang]) ? $product->tags[$this->id_lang] : array());
			$row['sort_order'] = 			$product->getWsPositionInCategory();
			$row['related_shop_item_numbers'] = 	Db::getInstance()->getValue('SELECT GROUP_CONCAT(`id_product_2` SEPARATOR \'||\') FROM `'._DB_PREFIX_.'accessory` WHERE `id_product_1` = '.$product->id.' GROUP BY `id_product_1`');
			$row['weight'] = 			self::convertProductWeightToGrams($product->weight);
			$row['is_highlight'] = 			$product->on_sale;
			$row['minimum_order_quantity'] = $product->minimal_quantity;
			$row['is_highlight'] = (int)$is_highlight;
			
			if(version_compare(_PS_VERSION_, '1.5.0.0', '<')){
				// lower than 1.5.0.0
				$sortOrderCategories = Db::getInstance()->ExecuteS('SELECT `id_category`, `position` FROM `'._DB_PREFIX_.'category_product` WHERE `id_product` = '.$product->id.' AND `id_category` != 1');
			} else {
				$sortOrderCategories = Db::getInstance()->ExecuteS('SELECT `id_category`, `position` FROM `'._DB_PREFIX_.'category_product` WHERE `id_product` = '.$product->id.' AND `id_category` != 1 AND `id_category` NOT IN ('.implode(',',$rootCategoryIds).')');
			}
			
			$row['category_numbers'] = '';
			foreach($sortOrderCategories as $sortOrderCategory){
				if(!empty($row['category_numbers'])){
					$row['category_numbers'] .= '||';
				}
				$row['category_numbers'] .= $sortOrderCategory['id_category'].'=>'.(($maxSortOrderByCategoryNumber[$sortOrderCategory['id_category']] - $sortOrderCategory['position']) + 1);
			}
			

			if ($is_highlight)
			{
				$row['highlight_order_index'] = $highlight_index;
				$highlight_index++;
			}

			// TODO: maybe tax must to be added
			$row['additional_shipping_costs_per_unit'] = Tools::ps_round($product->additional_shipping_cost, 2);

			//Images
			$image_urls = array();
			$image_ids = $product->getWsImages();
			foreach($image_ids as $i) {
				if(version_compare(_PS_VERSION_, '1.5.0.0', '<')){
					// lower than 1.5.0.0
					array_push($image_urls, $this->link->getImageLink($product->link_rewrite, $product->id.'-'.$i['id'], 'thickbox'));
				} else {
					array_push($image_urls, $this->link->getImageLink($product->link_rewrite, $product->id.'-'.$i['id'], 'thickbox_default'));
				}
			}
			$row['urls_images'] =	(string)implode('||', $image_urls);
			// Url in 1.5.2.0 wrong (thickbox_default)
			//Categories
			$category = new Category($product->id_category_default);
			$row['categories'] = $category->getName($this->id_lang);

			//Features
			$features = $product->getFrontFeatures($this->id_lang);
			$properties = array();
			foreach($features as $f) array_push($properties, $f['name'].'=>'.$f['value']);
			$row['properties'] = implode('||', $properties);
			 
			 
			//Product customizations
			if($product->customizable)
			{
				$row['has_input_fields'] = 1;
				$cfields = $product->getCustomizationFields($this->id_lang);

				$i = 1;
				foreach($cfields as $f)
				{
					$row['input_field_'.$i.'_type'] = ($f['type'] == 1) ? 'text' : 'image';
					$row['input_field_'.$i.'_label'] = $f['name'];//$f['id_customization_field'];
					//		    $row['input_field_'.$i.'_infotext'] = $f['name'];
					$row['input_field_'.$i.'_required'] = (bool)$f['required'];
					$i++;
				}
			}

			//Prodcut attributes
			$row['has_children'] = $product->hasAttributes() ? 1 : 0;

			if($row['has_children']){
				$attributes = $product->getAttributeCombinaisons($this->id_lang);
				$images = $product->getCombinationImages($this->id_lang);

				$combinations = array();
				$attribute_groups = array();
				foreach($attributes as $a)
				{
					$combinations[$a['id_product_attribute']][$a['id_attribute_group']] = $a;
					$attribute_groups[$a['id_attribute_group']] = $a['group_name'];
				}

				$i = 1;
				foreach($attribute_groups as $id => $name)
					$row['attribute_'.($i++)] = $name;
			}
			
			self::roundPricesInArray($row, array('unit_amount', 'old_unit_amount', 'additional_shipping_costs_per_unit'));
			 
			//Adding product
			$this->addItem($row);


			//Adding product combinations
			if($row['has_children']){
				$r = $row;
				foreach($combinations as $id => $c){
					$combination = current($c);
					$reduction = (float)$product->getPrice(true, (int)$id, 2, NULL, true);
					
					//Images
					$image_urls = array();
					if(isset($images[$id]) && is_array($images[$id])){
						foreach($images[$id] as $i){
							if(version_compare(_PS_VERSION_, '1.5.0.0', '<')){
								// lower than 1.5.0.0
								array_push($image_urls, $this->link->getImageLink($product->link_rewrite, $product->id.'-'.$i['id_image'], 'thickbox'));
							} else {
								array_push($image_urls, $this->link->getImageLink($product->link_rewrite, $product->id.'-'.$i['id_image'], 'thickbox_default'));
							}
						}
					}
					
					if($product->available_for_order && $combination['quantity'] > 0){
						$availableText = $product->available_now;
						$r['is_available'] = 1;
					} else {
						$availableText = $product->available_later;
						$r['is_available'] = 0;
					}

					$r['item_number'] = 	self::prefix.$product->id.'_'.$id;
					$r['has_children'] = 	0;
					$r['parent_item_number'] = 	$row['item_number'];
					$r['urls_images'] =		implode('||', $image_urls);
					$r['old_unit_amount'] = $reduction != 0 ? $product->getPrice(true, (int)$id, 2, NULL, false, false) : 0;
					$r['unit_amount'] = 	$product->getPrice(true, (int)$id, 2);
					$r['stock_quantity'] = 	$combination['quantity'];
					$r['ean'] = 		$combination['ean13'];
					$r['weight'] = 		$combination['weight'];
					$r['minimum_order_quantity'] = $combination['minimal_quantity'];
					$r['available_text'] = $availableText;
					$r['item_number_public'] = 	(array_key_exists('reference', $combination) && !empty($combination['reference'])) ? $combination['reference'] : '';

					$i = 1;
					foreach($attribute_groups as $id => $name)
						$r['attribute_'.($i++)] = $c[$id]['attribute_name'];
					
					self::roundPricesInArray($r, array('unit_amount', 'old_unit_amount'));

					$this->addItem($r);
				}
			}
		}
	}

	protected function createCategoriesCsv(){
		$maxSortOrder = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS(
			'SELECT id_parent, MAX(position) as max_position FROM `'._DB_PREFIX_.'category` GROUP BY id_parent'
		);
		
		$maxSortOrderByCategoryNumber = array();
		foreach($maxSortOrder as $sortOrder){
			$maxSortOrderByCategoryNumber[$sortOrder['id_parent']] = $sortOrder['max_position'];
		}
		
		$rootCategoriesByCategoryId = array();
		if(version_compare(_PS_VERSION_, '1.5.0.0', '<')){
			// lower than 1.5.0.0
			$cats = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT c.*, cl.`name`, cl.`link_rewrite` FROM `'._DB_PREFIX_.'category` c NATURAL LEFT JOIN `'._DB_PREFIX_.'category_lang` cl WHERE c.`id_category`!=1 AND cl.`id_lang` = '.$this->id_lang);
		} else {
			$cats = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT c.*, cl.`name`, cl.`link_rewrite` FROM `'._DB_PREFIX_.'category` c NATURAL LEFT JOIN `'._DB_PREFIX_.'category_lang` cl WHERE c.`id_parent`!=0 AND c.`is_root_category`!=1 AND cl.`id_lang` = '.$this->id_lang);
			$rootCategoriesByCategoryId = $this->getRootCategoriesByCategoryId();
		}
		
		foreach($cats as $c){
			$cat = $this->buildDefaultCategoryRow();
			
			$cat['category_number'] =	(int)$c['id_category'];
			$cat['category_name'] = 	(string)$c['name'];
			$cat['parent_id'] = 	($c['id_parent'] == 1 || !empty($rootCategoriesByCategoryId[(int)$c['id_parent']])) ? '' : (int)$c['id_parent'];
			if(version_compare(_PS_VERSION_, '1.5.0.0', '<')){
				// lower than 1.5.0.0
				$cat['url_image'] = 	(string)_PS_BASE_URL_.$this->link->getCatImageLink($c['link_rewrite'], $c['id_category'], 'large');
			} else {
				$cat['url_image'] = 	(string)$this->link->getCatImageLink($c['link_rewrite'], $c['id_category'], 'category_default');
			}
			$cat['order_index'] = 	(int)($maxSortOrderByCategoryNumber[$c['id_parent']]-$c['position'])+1;
			$cat['is_active'] = 	(bool)$c['active'];
			$cat['url_deeplink'] = 	(string)$this->link->getCategoryLink($c['id_category'], $c['link_rewrite'], $this->id_lang);
			 
			$this->addItem($cat);
		}
	}
	
	/**
	 * used in Prestashop 1.5.x.x to find root categories
	 */
	private function getRootCategoriesByCategoryId(){
		$rootCategoriesByCategoryId = array();
		$rootCategories = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT c.* FROM `'._DB_PREFIX_.'category` c NATURAL LEFT JOIN `'._DB_PREFIX_.'category_lang` cl WHERE (c.`is_root_category`=1 OR c.`id_category` = 1) AND cl.`id_lang` = '.$this->id_lang);
			
		foreach($rootCategories as $rootCategory){
			$rootCategoriesByCategoryId[(int)$rootCategory['id_category']] = $rootCategory;
		}
		
		return $rootCategoriesByCategoryId;
	}

	protected function createReviewsCsv()
	{

	}
	
	public function getRedirect()
	{
		return $this->builder->buildRedirect();
	}
}
