<?php
class SofortueberweisungCancelModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
	parent::initContent();

	// Get class instance
	$su = new Sofortueberweisung();
	
	$this->setTemplate('tpl-frontend-cancel.tpl');
    }
}