<?php
/**
 * Main class for payments through
 * sofortüberweisung.de. Contains logic, installation,
 * uninstallation and displaying routines.
 *
 * @category  silbersaiten
 * @package   silbersaiten_sofortueberweisung
 * @author    silbersaiten <kontakt@silbersaiten.de>
 * @copyright 2010 Bangiev & Bangiev GbR
 * @license   http://www.opensource.org/licenses/osl-3.0.php Open-source licence 3.0
 * @version   1.2
 * @link      http://www.silbersaiten.de/
 *
 */

class Sofortueberweisung extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();
	private $_confFields = array();
	private $_hashFields = array();

	/**
	 * Class constructor
	 *
	 * @return null
	 */
	public function __construct()
	{
		$this->name = 'sofortueberweisung';
		$this->tab = 'payments_gateways';
		$this->version = 1.5;

		// Calling parent __construct method from Module
		parent::__construct();
	
		// Set display name
		$this->setDisplayName($this->l('sofortüberweisung.de'));
	
		// Set module description
		$this->setDescription($this->l('Payments through sofortüberweisung.de'));
	
		// Set backend configuration fields
		$this->setConfFields(array(
			'su_user_id',
			'su_project_id',
			'su_project_password',
			'su_notification_password'
		));

		// Set hash fields to check them after the response
		$this->setHashFields(array(
			'transaction',
			'user_id',
			'project_id',
			'sender_holder',
			'sender_account_number',
			'sender_bank_code',
			'sender_bank_name',
			'sender_bank_bic',
			'sender_iban',
			'sender_country_id',
			'recipient_holder',
			'recipient_account_number',
			'recipient_bank_code',
			'recipient_bank_name',
			'recipient_bank_bic',
			'recipient_iban',
			'recipient_country_id',
			'international_transaction',
			'amount',
			'currency_id',
			'reason_1',
			'reason_2',
			'security_criteria',
			'user_variable_0',
			'user_variable_1',
			'user_variable_2',
			'user_variable_3',
			'user_variable_4',
			'user_variable_5',
			'created'
		));

		return null;
	}

	/**
	 * Installing module through the backend
	 *
	 * @return bool
	 */
	function install() {
		// Use parent installing method first
		if (parent::install()) {
			// Register hooks
			$this->registerHook('payment');
			$this->registerHook('paymentReturn');
	    
			// Get configuration fields
			$fields = $this->getConfFields();
	    
			// Check if all configuration fields can be updated
			// and clear previous values
			foreach ($fields as $field) {
				$confField = strtoupper($field);
				if ( ! Configuration::updateValue($confField, '')) {
					return false;
				}
			}
			
			// Define order status for successful payment
			Configuration::updateValue('_SU_OS_OK_', 2);
			
			// Define order status for errors during the order process
			Configuration::updateValue('_SU_OS_ERROR_', 8);
	    
			// Installation complete
			return true;
		}
		return false;
	}

	/**
	 * Uninstalling module
	 *
	 * @return null
	 */
	public function uninstall() {
		$fields = $this->getConfFields();
		foreach ($fields as $field) {
			$confField = strtoupper($field);
	    
			// Delete configuration variable
			Configuration::deleteByName($confField);
		}
	
		// Define order status for successful payment
		Configuration::deleteByName('_SU_OS_OK_');
		
		// Define order status for errors during the order process
		Configuration::deleteByName('_SU_OS_ERROR_');
		
		// Call parent uninstall method
		if ( ! parent::uninstall()) {
			// If returns false > uninstallation went wrong
			return false;
		}
	
		// Uninstallation was successful
		return true;
	}

	/**
	 * Construct smarty form for backend configuration
	 *
	 * @return null
	 */
	private function _displayForm() {
		// Get configuration fields
		$fields = $this->getConfFields();
	
		// Fill URL and the module name
		$smartyAssignArray = array(
			'url' => $_SERVER['REQUEST_URI'],
			'lDisplayName' => $this->getDisplayName()
		);
	
		// Fill form values
		foreach ($fields as $field) {
			$smartyAssignArray['value_' . $field] = Configuration::get(strtoupper($field));
		}
	       
		$smartyAssignArray['domain'] = $this->getPathSsl();
	
		// Assign filled array
		$this->context->smarty->assign($smartyAssignArray);
	
		// Add smarty template tpl-admin-settings to the output
		$this->_html .= $this->display(__FILE__, 'tpl-admin-settings.tpl');
	
		return;
	}

	/**
	 * Show configuration form in backend
	 *
	 * @return string
	 */
	public function getContent() {
		$fields = $this->getConfFields();
		$postErrors = $this->getPostErrors();
	
		// Check if form was posted
		if ($this->isPosted()) {
			// Display errors if found
			if (count($postErrors) > 0) {
				// TODO: display errors
			} else {
				// Update configration from post values
				foreach ($fields as $field) {
					Configuration::updateValue(strtoupper($field), $_POST[$field]);
				}
			}
		}
	
		// Get filled smarty template
		$this->_html .= $this->_displayForm();
		
		return $this->_html;
	}

	/**
	 * Execute payment after selecting
	 * it from frontend
	 *
	 * @return string
	 */
	public function execPayment($cart) {
		// If module inactive > exit
		if ( ! $this->active) {
			return false;
		}
	    
		// Get currency from cart
		$currencyObj = new Currency($this->context->cart->id_currency);
		$currency = $currencyObj->getFields();
    
		// Get language from cart
		$langObj = new Language();
		$language = $langObj->getLanguage($this->context->cart->id_lang);
    
		// Get customer from cart
		$customerObj = new Customer($this->context->cart->id_customer);
	
		// Set transaction reasons
		$firstName = $customerObj->firstname;
		$lastName = ucfirst(strtolower($customerObj->lastname));
		$orderTime = strftime('%d.%m.%Y %H:%M');
	
		$shopName = Configuration::get('PS_SHOP_NAME');
		$shopName = substr($shopName, 0, 15);
	
		$reason1 = $shopName . ' Cart: ' . $cart->id;
		$reason2 = $firstName . $lastName;
    
		// Fill parameter vars
		$vars = array(
			'user_id' => Configuration::get('SU_USER_ID'),
			'project_id' => Configuration::get('SU_PROJECT_ID'),
			'amount' => $this->formatPrice($cart->getOrderTotal(true, 3)),
			'currency_id' => $currency['iso_code'],
			'language_id' => strtoupper($language['iso_code']),
			'user_variable_0' => $cart->id,
			'reason_1' => $reason1,
			'reason_2' => $reason2
		);
    
		// Fill smarty vars for displaying the form
		$smartyArray = array(
			'this_path' => $this->getPath(),
			'this_path_ssl' => $this->getPathSsl(),
			'hash' => $this->getHash($vars)
		);
    
		// Merge form vars and smarty vars
		$smartyArray = array_merge($smartyArray, $vars);
	    
		return $smartyArray;
	}

	/**
	 * Check HTTP response from
	 * sofortüberweisung.de
	 *
	 * @return boolean
	 */
	public function checkResponse($data) {
		// Get hash fields
		$hashFields = $this->getHashFields();
	
		foreach ($hashFields as $field) {
			$hashArray[$field] = $data[$field];
		}
	
		// Adding project password to the hash calculation
		$hashArray['project_password'] = Configuration::get('SU_NOTIFICATION_PASSWORD');
	
		$implodedHash = implode('|', $hashArray);
	
		// Building hash
		$hash = sha1($implodedHash);
    
		// If incoming hashed parameter equals local hash.
		if ($data['hash'] == $hash) {
			// Check OK
			return true;
		}
	
		// Check failed
		return false;
	}

	/**
	 * Display sofortüberweisung.de in
	 * payment methods in frontend order
	 * process
	 *
	 * @return string
	 */
	public function hookPayment($params) {
		// Fill smarty array
		$smartyAssignArray = array(
			'this_path' => $this->getPath(),
			'this_path_ssl' => $this->getPathSsl()
		);
	
		// Assign filled smarty array
		$this->context->smarty->assign($smartyAssignArray);
	
		// Return filled smarty template tpl-frontend-payment
		return $this->display(__FILE__, 'tpl-frontend-payment.tpl');
	}
    
	/**
	 * Display confirmation template after placing an order (added 19-01-2012)
	 *
	 * @return string
	 */
	public function hookPaymentReturn($params) {
		if ($this->active) {
			return $this->display(__FILE__, 'tpl-frontend-success.tpl');
		}
         
		return '';
	}

	/**
	 * Getter for _path
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->_path;
	}

	/**
	 * Getter for SSL path
	 *
	 * @return string
	 */
	public function getPathSsl() {
		$path = '';
	
		// Check if SSL is enabled
		if (Configuration::get('PS_SSL_ENABLED')) {
		    $path .= 'https://';
		}
		else {
		    $path .= 'http://';
		}
	
		// Build SSL path
		$path.= htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8') . __PS_BASE_URI__;
	
		return $path;
	}

	/**
	 * Getter for _confFields
	 *
	 * @return array
	 */
	public function getConfFields() {
		return $this->_confFields;
	}

	/**
	 * Getter for displayName
	 *
	 * @return string
	 */
	public function getDisplayName() {
		return $this->displayName;
	}

	/**
	 * Getter for postErrors
	 *
	 * @return array
	 */
	public function getPostErrors() {
		return $this->_postErrors;
	}

	/**
	 * Getter for name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Getter for _hashFields
	 *
	 * @return array
	 */
	public function getHashFields() {
		return $this->_hashFields;
	}

	/**
	 * Check if form was posted
	 *
	 * @return string
	 */
	public function isPosted($field = 'su_posted') {
		// If $_POST[$field] is set and contains 1
		if (isset($_POST[$field]) && $_POST[$field] == '1') {
	
			// Form was posted
			return true;
		}
	
		// Form was not posted
		return false;
	}

	/**
	 * Setter for displayName
	 *
	 * @return none
	 */
	public function setDisplayName($displayName) {
		$this->displayName = $displayName;
	}

	/**
	 * Setter for description
	 *
	 * @return none
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * Setter for _confFields
	 *
	 * @return none
	 */
	private function setConfFields($fields) {
		$this->_confFields = $fields;
	}

	/**
	 * Setter for _hashFields
	 *
	 * @return none
	 */
	private function setHashFields($fields) {
		$this->_hashFields = $fields;
	}

	/**
	 * Update / Create order wrapper
	 * method
	 *
	 * @return null
	 */
	public function updateOrder($cartId, $state, $amount, $message, $currency_special, $secure_key) {
		$this->context->link = new Link();
			
		$this->validateOrder($cartId, $state, $amount, $this->getDisplayName(), $message, $extra_vars = array(), (int)$currency_special, false, $secure_key);
			
		return null;
	}

	/**
	 * Price format wrapper
	 * method
	 *
	 * @return float
	 */
	public function formatPrice($price) {
		return number_format($price, 2, '.', '');
	}

	/**
	 * Generate checksum (hash) to secure
	 * the transaction to sofortüberweisung.de
	 *
	 * @return string
	 */
	public function getHash($vars) {
    
		$data = array(
			Configuration::get('SU_USER_ID'),
			Configuration::get('SU_PROJECT_ID'),
		       '', // sender_holder
		       '', // sender_account_number
		       '', // sender_bank_code
		       '', // sender_country_id
		       $vars['amount'],
		       $vars['currency_id'],
		       $vars['reason_1'], // reason_1
		       $vars['reason_2'], // reason_2
		       $vars['user_variable_0'], // user_variable_0
		       '', // user_variable_1
		       '', // user_variable_2
		       '', // user_variable_3
		       '', // user_variable_4
		       '', // user_variable_5
		       Configuration::get('SU_PROJECT_PASSWORD')
		);
	
		$imploded = implode('|', $data);
		$hash = sha1($imploded);
		
		return $hash;
	}
}