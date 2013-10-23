<?php
class SofortueberweisungRedirectModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
	$this->display_column_left = false;
	$this->display_column_right = false;
	parent::initContent();

        $this->context = $this->context;
	
	if ( ! isset($this->context->customer) || ! $this->context->customer->isLogged(true)) {
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
