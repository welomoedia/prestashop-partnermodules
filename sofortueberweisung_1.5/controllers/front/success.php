<?php
class SofortueberweisungSuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
	parent::initContent();

	// Get class instance
	$su = new Sofortueberweisung();
	
	// Get cartId from $_GET
	$id_cart = (int)Tools::getValue('cartid');
	
	if ($id_cart <= 0) {
	    // Display information how to set the cartid
	    $this->setTemplate('tpl-frontend-success-error.tpl');
	}
	else {
	    // Get orderId from cartId
	    $id_order = Order::getOrderByCartId($id_cart);
	    $order = new Order($id_order);
	    
	    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $id_cart . '&id_module=' . $su->id . '&id_order=' . $order->id . '&key=' . $order->secure_key);
	}
    }
}