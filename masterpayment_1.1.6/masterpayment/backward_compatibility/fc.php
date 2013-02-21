<?php

include(dirname(__FILE__).'/../../../config/config.inc.php');
include(dirname(__FILE__).'/../masterpayment.php');

$controller = Tools::getValue('controller', null);

if($controller != 'submit')
	exit;

if (!in_array('ModuleFrontController', get_declared_classes()))
{
	class ModuleFrontController extends FrontController
	{
		protected $module = null;
		protected $template = null;
		protected $context = null;
		
		public function initContent(){}
		
		public function setTemplate($template){$this->template = $template;}
		
		public function init()
		{
			parent::init();
			require(dirname(__FILE__).'/backward.php');
			$this->module = new MasterPayment();
			$this->initContent();
		}
	
		public function displayContent()
		{
			parent::displayContent();			
			$this->context->smarty->display($this->getTemplatePath().$this->template);
		}
		
		public function getTemplatePath()
		{
			return _PS_MODULE_DIR_.$this->module->name.'/views/templates/front/';
		}
	}
}	
	
include(dirname(__FILE__).'/../controllers/front/'.$controller.'.php');

$controller_name = 'MasterPayment'.$controller.'ModuleFrontController';


$ctrl = new $controller_name();
$ctrl->run();


?>
