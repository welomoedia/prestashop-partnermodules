<?php
class SofortueberweisungRedirectModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
	parent::initContent();

        $this->context = $this->context;
	
	if ( ! isset($this->context->customer) || ! $this->context->customer->isLogged() || $this->context->customer->is_guest) {
	    Tools::redirect($this->context->link->getPageLink(
		'authentication',
		true,
		(int)$this->context->language->id,
		array(
		    'back' => $this->context->link->getPageLink(
			    'order',
			    true,
			    (int)$this->context->language->id
		    )
		)
	    ));
	}
	
	$su = new Sofortueberweisung();

	$this->context->smarty->assign($su->execPayment($this->context->cart));

        $this->setTemplate('tpl-frontend-redirect.tpl');
    }
}