<?php

class PSShopgateOrder extends ObjectModel
{
	public		$id;

	public 		$id_shopgate_order;
	public 		$id_cart;
	public 		$id_order;
	public 		$order_number;
	public 		$tracking_number;
	public 		$shipping_service = 'OTHER';
	public 		$shipping_cost;
	
	protected 	$table = 'shopgate_order';
	protected 	$identifier = 'id_shopgate_order';
	
	protected	$fieldsRequired = array('order_number', 'shipping_cost');
	protected 	$fieldsValidate = array
	(
		'id_cart' => 'isUnsignedId',
		'id_order' => 'isUnsignedId',
		'order_number' => 'isString',
		'shipping_cost'=>'isPrice',
		'shipping_service' => 'isString',
		'tracking_number'=>'isString'
	);

	protected 	$fieldsSize = array
	(
		'tracking_number' => 32,
		'shipping_service' => 16,
		'order_number' => 16
	);
		
	public function __construct($id = NULL, $identifier = 'id_shopgate_order')
	{
		$this->identifier = $identifier;
		parent::__construct($id);
		$this->id = $this->id_shopgate_order;
		$this->identifier = 'id_shopgate_order';
	}
	
	public function getFields()
	{
		parent::validateFields();
		$fields['id_cart'] = (int)($this->id_cart);
		$fields['id_order'] = (int)($this->id_order);
		$fields['order_number'] = pSQL($this->order_number);
		$fields['tracking_number'] = pSQL($this->tracking_number);
		$fields['shipping_service'] = pSQL($this->shipping_service);
		$fields['shipping_cost'] = (float)($this->shipping_cost);
		return $fields;
	}
	
	public static function instanceByCartId($id_cart = 0)
	{
		return new PSShopgateOrder($id_cart, 'id_cart');
	}

	public static function instanceByOrderId($id_order = 0)
	{
		return new PSShopgateOrder($id_order, 'id_order');
	}

	public static function instanceByOrderNumber($order_number = 0)
	{
		return new PSShopgateOrder($order_number, 'order_number');
	}	
}
