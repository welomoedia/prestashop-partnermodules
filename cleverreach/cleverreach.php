<?php
define('MAX_RECEIVERS_PER_CALL', 40);
define('API_URL', 'http://api.cleverreach.com/soap/interface_v5.0.php?wsdl');
define('STATUS_OK', 'SUCCESS');

class cleverreach extends Module
{
    private $api_key;
    private $list_id;
    private $error = false;
    private $message = '';
	private static $api = false;
	
	private static $_group_fields = array(
		'Company'    => 'text',
		'Country'    => 'text',
		'Firstname'  => 'text',
		'Lastname'   => 'text',
		'Salutation' => 'gender',
		'Status'     => 'text',
		'Street'     => 'text',
		'City'       => 'text',
		'ZIP'        => 'number'
	);
    
	public function __construct()
	{
		$this->name = 'cleverreach';
		$this->version = 0.1;
		$this->tab = 'advertising_marketing';
		
		parent::__construct();
		
		$this->displayName = $this->l('Cleverreach for Prestashop');
		$this->description = $this->l('Export new customers and orders to Cleverreach');
        
        $this->api_key = Configuration::get('cleverreach_api_key') or '';
        $this->list_id = Configuration::get('cleverreach_list_id') or '';
	}
	
	public function install()
	{
        if (!parent::install() OR
			!$this->registerHook('newOrder') OR
            !$this->registerHook('createAccount')
			)
			return false;
		return true;
	}
	
	public function uninstall()
	{
		return parent::uninstall();
	}
	
	public function getApiConn()
	{
		if ( ! self::$api)
		{
			self::$api = new SoapClient(API_URL);
		}
		
		return self::$api;
	}
	
	public function groupGetExistingFields()
	{
		$key      = Configuration::get($this->name . '_api_key');
		$list     = Configuration::get($this->name . '_list_id');
		$existing = array();
		
		if ($key && $list)
		{
			$api    = $this->getApiConn();
			$result = $api->groupGetDetails($key, $list);
			
			if (is_object($result))
			{
				if ($result->status == STATUS_OK)
				{
					$fields = $result->data->attributes;
					
					if (is_array($fields) && sizeof($fields))
					{
						foreach ($fields as $fieldObj)
						{
							$existing[(string)$fieldObj->key] = array(
								'variable' => (string)$fieldObj->variable,
								'type'     => (string)$fieldObj->type
							);
						}
					}
				}
				else
				{
					if (strlen($result->message) > 0)
					{
						$this->error = $result->message;
						
						return false;
					}
					else
					{
						$this->error = $this->l('Unable to receive existing fields');
						
						return false;
					}
				}
			}
		}
		
		return $existing;
	}
	
	public function addGroupAttribute($field_name, $field_type)
	{
		// Configuration::get gets cached in Configuration class, so it's pretty
		// much safe to do the following in the loop:
		$key    = Configuration::get($this->name . '_api_key');
		$list   = Configuration::get($this->name . '_list_id');
		$api    = $this->getApiConn();
		$result = $api->groupAttributeAdd($key, $list, $field_name, $field_type);
		
		if ( ! is_object($result) || $result->status != STATUS_OK)
		{
			$this->error = strlen($result->message > 0) ? $result->message : $this->l('Unable to add an attribute to the group:') . ' "' . $field_name . '"';
			
			return false;
		}
		
		return true;
	}
	
	public function getContent()
	{
		if (Tools::isSubmit('settings'))
		{
            $this->api_key = Tools::getValue('api_key');
            $this->list_id = Tools::getValue('list_id');
            
			Configuration::updateValue($this->name.'_api_key', $this->api_key);
            Configuration::updateValue($this->name.'_list_id', $this->list_id);
			
			$existing = $this->groupGetExistingFields();

			foreach (self::$_group_fields as $field_name => $field_type)
			{
				$field_name = strtolower(str_replace(' ', '_', $field_name));

				if ( ! $existing || ! array_key_exists($field_name, $existing))
				{
					if ( ! $this->addGroupAttribute($field_name, $field_type))
					{
						break;
					}
				}
			}
		}
        else if (Tools::isSubmit('export'))
        {
            $receivers = $this->getReceivers();
            $receivers = array_merge($receivers, $this->getNewsletterReceivers());
            $this->exportReceivers($receivers);
        }

		$this->_displayForm();
		return $this->_html;
	}
    
    public function hookcreateAccount($params) {
        try {
            $customer = $params['newCustomer'];
            $adresses = $customer->getAddresses((int)(Configuration::get('PS_LANG_DEFAULT')));
            $addr = $adresses[0];
            $receiver = array(
					'email' => $customer->email,
					'registered' => time(),
					'activated' => time(),
					'source' => "Prestashop",
					'attributes' => array(
						0 => array("key" => "salutation", "value" => ($customer->id_gender==2?'Frau':'Herr')),
						1 => array("key" => "firstname", "value" => $customer->firstname),
						2 => array("key" => "lastname", "value" => $customer->lastname),
						3 => array("key" => "street", "value" => $addr['address1']),
						4 => array("key" => "zip", "value" => $addr['postcode']),
						5 => array("key" => "city", "value" => $addr['city']),
						6 => array("key" => "country", "value" => $addr['country']),
						7 => array("key" => "company", "value" => $addr['company']),
						/*8 => array('key' => 'shop_id', 'value' => $u["shop_id"),
						9 => array('key' => 'newsletter', 'value' => $u["cr_newsletter"])));*/
                    )                    
            );
            self::api()->receiverAdd($this->api_key, $this->list_id, $receiver);
        }
        catch(Exception $e) {
        }
    }
    
    public function hookNewOrder($params)
	{
        try {
            $orders = $this->getCustomersOrders(array(array('id_customer' => $params['customer']->id)));
            $receiver = array(
                        'email' => $params['customer']->email,
                        'orders' => $orders[$params['customer']->id],
                        'source' => 'Prestashop',
                        'attributes' => array(
                            0 => array('key' => 'status', 'value' => ($params['customer']->isGuest() ?'Gast':'Kunde')),
						 )    
                        );
            self::api()->receiverUpdate($this->api_key, $this->list_id, $receiver);
        }
        catch(Exception $e) {
        }
    }
    
    private function exportReceivers($receivers) {
        $offset = 0;
        $total = count($receivers);
        while($offset < $total) {
            $slice = array_slice($receivers, $offset, min($total-$offset, MAX_RECEIVERS_PER_CALL));
            $result = self::api()->receiverAddBatch($this->api_key, $this->list_id, $slice);
            if($result->status != 'SUCCESS') {
                $this->error = true;
                $this->message = $result->message;
                return;
            }
            $offset += count($slice);
        }
        $this->message = $total.' '.$this->l('users exported');
    }
    
    
	private function _displayForm()
	{
        if(!empty($this->message)) {
            $this->_html .= ($this->error
                ?'<div class="error"><img src="../img/admin/error.png">'
                :'<div class="conf"><img src="../img/admin/ok2.png">').
                Tools::htmlentitiesUTF8($this->message).
            '</div>';
        }
		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
				<label>'.$this->l('API Key').'</label>
				<div class="margin-form">
					<input type="text" size="32" name="api_key" value="'.Tools::htmlentitiesUTF8($this->api_key).'" />
				</div>
                <label>'.$this->l('List ID').'</label>
				<div class="margin-form">
					<input type="text" size="7" name="list_id" value="'.Tools::htmlentitiesUTF8($this->list_id).'" />
				</div>';
				
		$existing = $this->groupGetExistingFields();
		
		if ($existing && sizeof($existing))
		{
			$this->_html.= '
			<label>' . $this->l('Existing fields') . '</label>
			<div class="margin-form">
				<table class="table" cellspacing="0" cellpadding="0" style="background: #fff;">
					<tr>
						<th>' . $this->l('Field name') . '</th>
						<th>' . $this->l('Variable') . '</th>
						<th>' . $this->l('Type') . '</th>
					</tr>';
			
			foreach ($existing as $fieldName => $fieldData)
			{
				$this->_html.= '
					<tr>
						<td>' . $fieldName . '</td>
						<td>' . $fieldData['variable'] . '</td>
						<td>' . $fieldData['type'] . '</td>
					</tr>';
			}
			
				
			$this->_html.= '
				</table>
			</div>';
		}
				
		$this->_html.= '
                <div class="margin-form">
                    <input type="submit" name="settings" value="'.$this->l('Save').'" class="button" />
                </div>
		</form>';
        
        $this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
                <div class="margin-form">
                	<p class="warning">'.$this->l('Click the "Export" button to export new Prestashop customers to Cleverreach. Warning: Already exported users will not be modified').'</p>
                    <input style="width:150px" type="submit" name="export" value="'.$this->l('Export').'" class="button" />
                </div>
		</form>

<hr>
<label><img src="../modules/cleverreach/cleverreach_logo.jpg"></label>
<div class="margin-form">

<p>
'.$this->l('CleverReach offers a powerful email marketing software that allows you to create professional e-mails online, send them safely, measure the success quota and manage recipients. ith this module, new newsletter subscribers and customers, including purchase order information, automatically transferred in your CleverReach account . All existing data can be transmitted with an initial import.').'
</p>

<strong>'.$this->l('Clever Reach Features').'</strong>
<ul>
<li>'.$this->l('Create and send newsletters online').'</li>
<li>'.$this->l('Login forms for your website').'</li>
<li>'.$this->l('Menage your customers').'</li>
<li>'.$this->l('Reports and analytics of user behaviors').'</li>
<li>'.$this->l('Autotic emails with auto responder').'</li>
<li>'.$this->l('Design and spamtests').'</li>
<li>'.$this->l('A/B Splittests').'</li>
</ul>

<p>'.$this->l('You can find further features at Clever Reach Homepage at').' <a href="http://www.cleverreach.de" target="_blank">www.cleverreach.de</a>.</p>
<strong>'.$this->l('Free Rate').'</strong><br>
'.$this->l('With our free offer you can manage up to 250 recipients and sent up to 1.000 emails per day for free! There is no set-up fee and no binding contract').'
<a href="http://www.cleverreach.de/frontend/account.php" target="_blank">'.$this->l('Register for free').' &raquo;</a>
<br>
<br>
<p>'.$this->l('Language').': <a href="http://www.cleverreach.de" target="_blank">DE</a> | <a href="http://www.cleverreach.com" target="_blank">EN</a></p>

</div>

';
	}
    
    private function getReceivers() {
        
        $defaultLanguage = Configuration::getInt('PS_LANG_DEFAULT');

        $customers = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
            SELECT  cust.`id_gender` as gender, cust.`is_guest` as guest, 
            cust.`email`, cust.`firstname`, cust.`lastname`, UNIX_TIMESTAMP(cust.`date_add`) as registered, UNIX_TIMESTAMP(cust.`date_upd`) as activated,
            a.*, cl.`name` AS country, s.name AS state, s.iso_code AS state_iso
            FROM `'._DB_PREFIX_.'customer` cust
            LEFT JOIN `'._DB_PREFIX_.'address` a on a.`id_customer` = cust.`id_customer`
            LEFT JOIN `'._DB_PREFIX_.'country` c ON (a.`id_country` = c.`id_country`)
            LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country`)
            LEFT JOIN `'._DB_PREFIX_.'state` s ON (s.`id_state` = a.`id_state`)
            WHERE `id_lang` = '.(int)($defaultLanguage).' AND a.`deleted` = 0 and a.`active`
            GROUP BY cust.id_customer'
        );
        $orders = $this->getCustomersOrders($customers);
        $receivers = array();
        foreach($customers as $c) {
            $receivers[] = array(
					'email' => $c['email'],
					'registered' => intval($c['registered']),
					'activated' => intval($c['activated']),
					'source' => 'Prestashop',
					'attributes' => array(
						0 => array('key' => 'salutation', 'value' => ($c['gender']==2 ?'Frau':'Herr')),
						1 => array('key' => 'firstname', 'value' => $c['firstname']),
						2 => array('key' => 'lastname', 'value' => $c['lastname']),
						3 => array('key' => 'street', 'value' => $c['address1']),
						4 => array('key' => 'zip', 'value' => $c['postcode']),
						5 => array('key' => 'city', 'value' => $c['city']),
						6 => array('key' => 'country', 'value' => $c['country']),
						7 => array('key' => 'company', 'value' => $c['company']),
                        8 => array('key' => 'status', 'value' => ($c['guest']==0 ?'Kunde':'Gast')),
						/*8 => array('key' => 'shop_id', 'value' => $u["shop_id"]),
						9 => array('key' => 'newsletter', 'value' => $u["cr_newsletter"])*/
                    ),
                    'orders' => $orders[$c['id_customer']]
            );
        }
        
        return $receivers;
    }
    
    private function getNewsletterReceivers() {
        
        $receivers = array();

        $subscribers = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
            SELECT  `email`, UNIX_TIMESTAMP(`newsletter_date_add`) as registered
            FROM `'._DB_PREFIX_.'newslettergermanext`'
        );
        if($subscribers) foreach($subscribers as $s) {
            $receivers[] = array(
					'email' => $s['email'],
					'registered' => intval($s['registered']),
					'activated' => intval($s['registered']),
					'source' => 'Prestashop Newsletter'
            );
        }
        
        return $receivers;
    }
    
    
    private function getCustomersOrders($customers) {
        
    	$ret = array();
        foreach($customers as &$c) {
        	$ret[$c['id_customer']] = array();
        	
            $orders = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
                SELECT o.id_order, UNIX_TIMESTAMP(o.date_add) as stamp, od.product_id as product_id, od.product_quantity as quantity, 
                    od.product_name as product, od.product_price as price
                FROM `'._DB_PREFIX_.'orders` o
                LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON od.`id_order` = o.`id_order`
                WHERE o.`id_customer` = '.(int)$c['id_customer'].'
            ');
            
            
            foreach($orders as $o) {
                $ret[$c['id_customer']][] = array(
                        // --- required order fields ---
                        'stamp' => $o['stamp'], //order date, unix timestamp
                        'order_id' => $o['id_order'], //unique order ID (order_id & product are the unqiue key)
                        'product' => $o['product'], //product name
                        // --- Optional order fields ---
                         'product_id' => $o['product_id'],    //product ID, integer
                        'quantity' => $o['quantity'], //default 1
                        'price' => $o['price'],
                        'source' => "Prestashop",
                        //'mailing_id' => 12345678 + $i,     //if available (via connect link extension),
                                                     //set this to see order conversion in statistics
                );
            }
        }
        return $ret;
    }
    
    static function api(){
    	$return = false;
    	
    	try {
    		$return = new SoapClient('http://api.cleverreach.com/soap/interface_v5.0.php?wsdl');
    	} catch (Exception $e) {
    		
    	}

    	return $return;
    }
}
?>
                
