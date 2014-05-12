<?php
class EbayEnterprise_Eb2cPayment_Model_Storedvalue_Redeem
{
	/**
	 * Get gift card redeem from eb2c.
	 * @param string $pan Either a raw PAN or a token representing a PAN
	 * @param string $pin personal identification number or code associated with a gift card or gift certificate
	 * @param string $entityId sales/quote entity_id value
	 * @param string $amount amount to redeem
	 * @return string eb2c response to the request
	 */
	public function getRedeem($pan, $pin, $entityId, $amount)
	{
		$hlpr = Mage::helper('eb2cpayment');
		$uri = $hlpr->getSvcUri('get_gift_card_redeem', $pan);
		if ($uri === '') {
			Mage::log(sprintf('[ %s ] pan "%s" is out of range of any configured tender type bin.', __CLASS__, $pan), Zend_Log::ERR);
			return '';
		}
		return Mage::getModel('eb2ccore/api')
			->setStatusHandlerPath(EbayEnterprise_Eb2cPayment_Helper_Data::STATUS_HANDLER_PATH)
			->request(
				$this->buildStoredValueRedeemRequest($pan, $pin, $entityId, $amount),
				$hlpr->getConfigModel()->xsdFileStoredValueRedeem,
				$uri
			);
	}
	/**
	 * Build gift card Redeem request.
	 * @param string $pan, the payment account number
	 * @param string $pin, the personal identification number
	 * @param string $entityId, the sales/quote entity_id value
	 * @param string $amount, the amount to redeem
	 * @return DOMDocument The xml document, to be sent as request to eb2c.
	 */
	public function buildStoredValueRedeemRequest($pan, $pin, $entityId, $amount)
	{
		$domDocument = Mage::helper('eb2ccore')->getNewDomDocument();
		$storedValueRedeemRequest = $domDocument
			->addElement('StoredValueRedeemRequest', null, Mage::helper('eb2cpayment')->getXmlNs())
			->firstChild;
		$storedValueRedeemRequest->setAttribute(
			'requestId',
			Mage::helper('eb2cpayment')->getRequestId($entityId)
		);

		// creating PaymentContent element
		$paymentContext = $storedValueRedeemRequest->createChild('PaymentContext', null);

		// creating OrderId element
		$paymentContext->createChild('OrderId', $entityId);

		// creating PaymentAccountUniqueId element
		$paymentContext->createChild('PaymentAccountUniqueId', $pan, array('isToken' => 'false'));

		// add Pin
		$storedValueRedeemRequest->createChild('Pin', (string) $pin);

		// add amount
		$storedValueRedeemRequest->createChild('Amount', $amount, array('currencyCode' => 'USD'));

		return $domDocument;
	}

	/**
	 * Parse gift card Redeem response xml.
	 * @param string $storeValueRedeemReply the xml response from eb2c
	 * @return array, an associative array of response data
	 */
	public function parseResponse($storeValueRedeemReply)
	{
		$redeemData = array();
		if (trim($storeValueRedeemReply) !== '') {
			$doc = Mage::helper('eb2ccore')->getNewDomDocument();
			$doc->loadXML($storeValueRedeemReply);
			$redeemXpath = new DOMXPath($doc);
			$redeemXpath->registerNamespace('a', Mage::helper('eb2cpayment')->getXmlNs());
			$nodeOrderId = $redeemXpath->query('//a:PaymentContext/a:OrderId');
			$nodePaymentAccountUniqueId = $redeemXpath->query('//a:PaymentContext/a:PaymentAccountUniqueId');
			$nodeResponseCode = $redeemXpath->query('//a:ResponseCode');
			$nodeAmountRedeemed = $redeemXpath->query('//a:AmountRedeemed');
			$nodeBalanceAmount = $redeemXpath->query('//a:BalanceAmount');
			$redeemData = array(
				'orderId' => ($nodeOrderId->length)? (int) $nodeOrderId->item(0)->nodeValue: 0,
				'paymentAccountUniqueId' => ($nodePaymentAccountUniqueId->length)? (string) $nodePaymentAccountUniqueId->item(0)->nodeValue : null,
				'responseCode' => ($nodeResponseCode->length)? (string) $nodeResponseCode->item(0)->nodeValue : null,
				'amountRedeemed' => ($nodeAmountRedeemed->length)? (float) $nodeAmountRedeemed->item(0)->nodeValue : 0,
				'balanceAmount' => ($nodeBalanceAmount->length)? (float) $nodeBalanceAmount->item(0)->nodeValue : 0,
			);
		}
		return $redeemData;
	}
}