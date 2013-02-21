<?php

class Cart extends CartCore
{
	public function getDeliveryOptionList(Country $default_country = null, $flush = false)
	{
		$r = parent::getDeliveryOptionList($default_country, $flush);

		if($this->id_carrier == Configuration::get('SHOPGATE_CARRIER_ID'))
		{
			require_once(_PS_MODULE_DIR_.'shopgate/classes/PSShopgateOrder.php');

			$shopgateOrder = PSShopgateOrder::instanceByCartId($this->id);

			$r[$this->id_address_delivery][$this->id_carrier.',']['carrier_list'][$this->id_carrier] = array
			(
				'price_with_tax' => $shopgateOrder->shipping_cost,
				'price_without_tax' => 0,
				'package_list' => array(0),
				'product_list' => array(),
				'instance' => new Carrier($this->id_carrier),
			);
			
			$r[$this->id_address_delivery][$this->id_carrier.',']['is_best_price'] = 1;
			$r[$this->id_address_delivery][$this->id_carrier.',']['is_best_grade'] = 1;
			$r[$this->id_address_delivery][$this->id_carrier.',']['unique_carrier'] = 1;
			$r[$this->id_address_delivery][$this->id_carrier.',']['total_price_with_tax'] = $shopgateOrder->shipping_cost;
			$r[$this->id_address_delivery][$this->id_carrier.',']['total_price_without_tax'] = 0;
			$r[$this->id_address_delivery][$this->id_carrier.',']['position'] = 0;
		}
		return $r;
	}
}

?>