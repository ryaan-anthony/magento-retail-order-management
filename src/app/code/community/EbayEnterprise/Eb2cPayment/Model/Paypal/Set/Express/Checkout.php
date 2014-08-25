<?php
/**
 * Copyright (c) 2013-2014 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class EbayEnterprise_Eb2cPayment_Model_Paypal_Set_Express_Checkout extends EbayEnterprise_Eb2cPayment_Model_Paypal_Abstract
{
	// A mapping to something in the helper. Pretty contrived.
	const URI_KEY = 'get_paypal_set_express_checkout';
	const XSD_FILE = 'xsd_file_paypal_set_express';
	const STORED_FIELD = 'token';
	const ERROR_MESSAGE_ELEMENT = '//a:ErrorMessage';

	/**
	 * Build PaypalSetExpressCheckout request.
	 *
	 * @param Mage_Sales_Model_Quote $quote the quote to generate request XML from
	 * @return DOMDocument The XML document to be sent as request to eb2c.
	 */
	protected function _buildRequest(Mage_Sales_Model_Quote $quote)
	{
		/**
		 * @var EbayEnterprise_Eb2cCore_Helper_Data $coreHelper
		 * @var EbayEnterprise_Eb2cPayment_Helper_Data $helper
		 * @var float $gwPrice price of order level gift wrapping
		 * @var string $gwId id of giftwrapping object
		 * @var EbayEnterprise_Dom_Element $request
		 * @var array $addresses
		 */
		$coreHelper = Mage::helper('eb2ccore');
		$helper = Mage::helper('eb2cpayment');
		$doc = $coreHelper->getNewDomDocument();
		$gwPrice = $quote->getGwPrice();
		$gwId = $quote->getGwId();
		$totals = $quote->getTotals();
		$grandTotal = isset($totals['grand_total']) ? $totals['grand_total']->getValue() : 0;
		$shippingTotal = isset($totals['shipping']) ? $totals['shipping']->getValue() : 0;
		$taxTotal = isset($totals['tax']) ? $totals['tax']->getValue() : 0;
		$lineItemsTotal = (isset($totals['subtotal']) ? $totals['subtotal']->getValue() : 0) + $gwPrice;
		$curCodeAttr = array('currencyCode' => $quote->getQuoteCurrencyCode());
		$request = $doc->addElement('PayPalSetExpressCheckoutRequest', null, $helper->getXmlNs())->firstChild;
		$request
			->addChild('OrderId', (string) $quote->getEntityId())
		  ->addChild('ReturnUrl', (string) Mage::getUrl('*/*/return'))
		  ->addChild('CancelUrl', (string) Mage::getUrl('*/*/cancel'))
		  ->addChild('LocaleCode', (string) Mage::app()->getLocale()->getDefaultLocale())
		  ->addChild('Amount', sprintf('%.02f', $grandTotal), $curCodeAttr);
		$addresses = $quote->getAllAddresses();

		$lineItems = $request->createChild('LineItems', null);
		$lineItemsTotalNode = $lineItems->createChild('LineItemsTotal', null, $curCodeAttr); // value to be inserted below
		$lineItems
			->addChild('ShippingTotal', sprintf('%.02f', $shippingTotal), $curCodeAttr)
			->addChild('TaxTotal', sprintf('%.02f', $taxTotal), $curCodeAttr);

		if ($gwId) {
			$lineItems
				->createChild('LineItem', null)
				->addChild('Name', 'GiftWrap')
				->addChild('Quantity', '1')
				->addChild('UnitAmount', sprintf('%.02f', $gwPrice), $curCodeAttr);
		}
		foreach($addresses as $address) {
			foreach ($address->getAllItems() as $item) {
				// If gw_price is empty, php will treat it as zero.
				$lineItemsTotal += $item->getGwPrice();
				$this->_addLineItem($lineItems, $item, $curCodeAttr);
				$itemGwId = $item->getGwId();
				if ($itemGwId) {
					$lineItems
						->createChild('LineItem', null)
						->addChild('Name', 'GiftWrap')
						->addChild('Quantity', '1')
						->addChild('UnitAmount', sprintf('%.02f', $item->getGwPrice()), $curCodeAttr);
				}
			}
		}
		$lineItemsTotalNode->nodeValue = sprintf('%.02f', $lineItemsTotal);
		return $doc;
	}
	/**
	 * Parse PayPal SetExpress reply xml.
	 *
	 * @param string $payPalSetExpressCheckoutReply the xml response from eb2c
	 * @return Varien_Object an object of response data
	 */
	public function parseResponse($payPalSetExpressCheckoutReply)
	{
		$checkoutObject = new Varien_Object();
		if (trim($payPalSetExpressCheckoutReply) !== '') {
			/** @var EbayEnterprise_Dom_Document $doc */
			$doc = Mage::helper('eb2ccore')->getNewDomDocument();
			$doc->loadXML($payPalSetExpressCheckoutReply);
			$checkoutXpath = new DOMXPath($doc);
			$checkoutXpath->registerNamespace('a', Mage::helper('eb2cpayment')->getXmlNs());
			$nodeOrderId = $checkoutXpath->query('//a:OrderId');
			$nodeResponseCode = $checkoutXpath->query('//a:ResponseCode');
			$this->_blockIfRequestFailed($nodeResponseCode->item(0)->nodeValue, $checkoutXpath);

			$nodeToken = $checkoutXpath->query('//a:Token');
			$checkoutObject->setData(array(
				'order_id' => ($nodeOrderId->length)? (int) $nodeOrderId->item(0)->nodeValue : 0,
				'response_code' => ($nodeResponseCode->length)? (string) $nodeResponseCode->item(0)->nodeValue : null,
				'token' => ($nodeToken->length)? (string) $nodeToken->item(0)->nodeValue : null,
			));
		}
		return $checkoutObject;
	}

	/**
	 * @param DOMNode $parent
	 * @param string $name
	 * @param string $qty
	 * @param string $price
	 * @param array $curCodeAttr
	 * @return void
	 */
	protected function _addLineItem(EbayEnterprise_Dom_Element $parent, $name, $qty, $price, $curCodeAttr)
	{
		$parent
			->createChild('LineItem', null)
			->addChild('Name', $name)
			->addChild('Quantity', $qty)
			->addChild('UnitAmount', sprintf('%.02f', $price), $curCodeAttr);
	}
}
