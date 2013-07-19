<?php

/**
 * Generate the xml request to the AddressValidation service
 * @method Mage_Customer_Model_Address_Abstract getQuote
 * @method TrueAction_Eb2cAddress_Model_Validation_Request setADdress(Mage_Customer_Model_Address_Abstract $address)
 */
class TrueAction_Eb2cAddress_Model_Validation_Request
	extends Mage_Core_Model_Abstract
{

	const API_SERVICE = 'address';
	const API_OPERATION = 'validate';
	const API_FORMAT = 'xml';

	const DOM_ROOT_NODE_NAME = 'AddressValidationRequest';

	/**
	 * DOMDocument used to build the request message
	 * @var TrueAction_Dom_Document
	 */
	protected $_dom;

	/**
	 * Config helper with address validation config model loaded in.
	 * @var TrueAction_Eb2cCore_Helper_Config
	 */
	protected $_config;

	/**
	 * Get a core config helper object and load an address validation config model into it.
	 */
	protected function _construct()
	{
		$this->_config = Mage::getModel('eb2ccore/config_registry')
			->addConfigModel(Mage::getSingleton('eb2caddress/config'));
	}

	/**
	 * Get the DOMDocument (TrueAction_Dom_Document)
	 * to be sent with this message.
	 * @return TrueAction_Dom_Document
	 */
	public function getMessage()
	{
		$this->_dom = new TrueAction_Dom_Document('1.0', 'UTF-8');
		if ($this->hasData('address')) {
			$this->_dom->addElement(self::DOM_ROOT_NODE_NAME, null, $this->_config->apiNamespace);
			$this->_dom->documentElement->appendChild($this->_createMessageHeader());
			$this->_dom->documentElement->appendChild($this->_createMessageAddress());
		}
		return $this->_dom;
	}

	/**
	 * Create a document fragment for the <Header>...</Header> portion of the message.
	 * @return DOMDocumentFragment
	 */
	protected function _createMessageHeader()
	{
		$dom = $this->_dom;
		$fragment = $dom->createDocumentFragment();

		$fragment->appendChild(
			$dom->createElement('Header',
				$dom->createElement('MaxAddressSuggestions',
					$this->_config->maxAddressSuggestions
				)
			)
		);
		return $fragment;
	}

	/**
	 * Create a document fragment for the <Address>...</Address> portion of the message.
	 * @return DOMDocumentFragment
	 */
	protected function _createMessageAddress()
	{
		$dom = $this->_dom;
		$fragment = $dom->createDocumentFragment();

		$fragment->appendChild(
			$dom->createElement('Address',
				Mage::helper('eb2caddress')
					->addressToPhysicalAddressXml($this->getAddress(), $this->_dom)
			)
		);
		return $fragment;
	}

}