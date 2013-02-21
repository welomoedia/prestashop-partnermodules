<?php
if (!in_array('ExtendedLink', get_declared_classes()))
{
	class ExtendedLink extends Link
	{
		public function getModuleLink($module, $controller = 'default', array $params = array(), $ssl = false, $id_lang = null)
		{
			return $this->getPageLink('modules/'.$module.'/backward_compatibility/fc.php?controller='.$controller, $ssl, $id_lang, $params);
		}
	}
}

$GLOBALS['link'] = new ExtendedLink();

// Get out if the context is already defined
if (!in_array('Context', get_declared_classes()))
	require_once(dirname(__FILE__).'/Context.php');

$this->context = Context::getContext();
