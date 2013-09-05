<?php
/**
 * @category   TrueAction
 * @package    TrueAction_Eb2c
 * @copyright  Copyright (c) 2013 True Action Network (http://www.trueaction.com)
 */
class TrueAction_Eb2cInventory_Model_Allocation extends Mage_Core_Model_Abstract
{
	protected $_helper;

	/**
	 * Initialize model
	 */
	protected function _construct()
	{
		$this->setHelper(Mage::helper('eb2cinventory'));
	}

	/**
	 * Allocating all items brand new quote from eb2c.
	 *
	 * @param Mage_Sales_Model_Quote $quote, the quote to allocate inventory items in eb2c for
	 *
	 * @return string the eb2c response to the request.
	 */
	public function allocateQuoteItems($quote)
	{
		$allocationResponseMessage = '';
		try{
			// build request
			$allocationRequestMessage = $this->buildAllocationRequestMessage($quote);

			// make request to eb2c for quote items allocation
			$allocationResponseMessage = $this->getHelper()->getApiModel()
				->setUri($this->getHelper()->getOperationUri('allocate_inventory'))
				->request($allocationRequestMessage);

		}catch(Exception $e){
			Mage::logException($e);
		}

		return $allocationResponseMessage;
	}

	/**
	 * Build  Allocation request.
	 *
	 * @param Mage_Sales_Model_Quote $quote, the quote to generate request XML from
	 *
	 * @return DOMDocument The XML document, to be sent as request to eb2c.
	 */
	public function buildAllocationRequestMessage($quote)
	{
		$domDocument = Mage::helper('eb2ccore')->getNewDomDocument();
		$allocationRequestMessage = $domDocument->addElement('AllocationRequestMessage', null, $this->getHelper()->getXmlNs())->firstChild;
		$allocationRequestMessage->setAttribute('requestId', $this->getHelper()->getRequestId($quote->getEntityId()));
		$allocationRequestMessage->setAttribute('reservationId', $this->getHelper()->getReservationId($quote->getEntityId()));
		if ($quote) {
			foreach($quote->getAllAddresses() as $addresses){
				if ($addresses){
					foreach ($addresses->getAllItems() as $item) {
						try{
							// creating quoteItem element
							$quoteItem = $allocationRequestMessage->createChild(
								'OrderItem',
								null,
								array('lineId' => $item->getId(), 'itemId' => $item->getSku())
							);

							// add quantity
							$quoteItem->createChild(
								'Quantity',
								(string) $item->getQty() // integer value doesn't get added only string
							);

							$shippingAddress = $quote->getShippingAddress();
							// creating shipping details
							$shipmentDetails = $quoteItem->createChild(
								'ShipmentDetails',
								null
							);

							// add shipment method
							$shipmentDetails->createChild(
								'ShippingMethod',
								$shippingAddress->getShippingMethod()
							);

							// add ship to address
							$shipToAddress = $shipmentDetails->createChild(
								'ShipToAddress',
								null
							);

							// add ship to address Line 1
							$shipToAddress->createChild(
								'Line1',
								$shippingAddress->getStreet(1),
								null
							);

							// add ship to address City
							$shipToAddress->createChild(
								'City',
								$shippingAddress->getCity(),
								null
							);

							// add ship to address MainDivision
							$shipToAddress->createChild(
								'MainDivision',
								$shippingAddress->getRegion(),
								null
							);

							// add ship to address CountryCode
							$shipToAddress->createChild(
								'CountryCode',
								$shippingAddress->getCountryId(),
								null
							);

							// add ship to address PostalCode
							$shipToAddress->createChild(
								'PostalCode',
								$shippingAddress->getPostcode(),
								null
							);
						}catch(Exception $e){
							Mage::logException($e);
						}
					}
				}
			}
		}
		return $domDocument;
	}

	/**
	 * Parse allocation response XML.
	 *
	 * @param string $allocationResponseMessage the XML response from eb2c
	 *
	 * @return array, an associative array of response data
	 */
	public function parseResponse($allocationResponseMessage)
	{
		$allocationData = array();
		if (trim($allocationResponseMessage) !== '') {
			$doc = Mage::helper('eb2ccore')->getNewDomDocument();

			// load response string XML from eb2c
			$doc->loadXML($allocationResponseMessage);
			$i = 0;
			$allocationResponse = $doc->getElementsByTagName('AllocationResponse');
			$allocationMessage = $doc->getElementsByTagName('AllocationResponseMessage');
			foreach($allocationResponse as $response) {
				$allocationData[] = array(
					'lineId' => $response->getAttribute('lineId'),
					'itemId' => $response->getAttribute('itemId'),
					'qty' => (int) $allocationResponse->item($i)->nodeValue,
					'reservation_id' => $allocationMessage->item(0)->getAttribute('reservationId'),
					'reservation_expires' => Mage::getModel('core/date')->date('Y-m-d H:i:s')
				);
				$i++;
			}
		}

		return $allocationData;
	}

	/**
	 * update quote with allocation response data.
	 *
	 * @param Mage_Sales_Model_Order $quote the quote we use to get allocation response from eb2c
	 * @param string $allocationData, a parse associative array of eb2c response
	 *
	 * @return array, error results of item that cannot be allocated
	 */
	public function processAllocation($quote, $allocationData)
	{
		$allocationResult = array();

		foreach ($allocationData as $data) {
			foreach ($quote->getAllItems() as $item) {
				// find the item in the quote
				if ((int) $item->getItemId() === (int) $data['lineId']) {
					// update quote with eb2c data.
					$result = $this->_updateQuoteWithEb2cAllocation($item, $data);
					if (trim($result) !== '') {
						$allocationResult[] = $result;
					}
				}
			}
		}

		return $allocationResult;
	}

	/**
	 * Removing all allocation data from quote item.
	 *
	 * @param Mage_Sales_Model_Order $quote the quote to empty any allocation data from it's item
	 *
	 * @return void
	 */
	protected function _emptyQuoteAllocation($quote)
	{
		foreach ($quote->getAllItems() as $item) {
			// emptying reservation data from quote item
			$item->setEb2cReservationId(null)
				->setEb2cReservationExpires(null)
				->setEb2cQtyReserved(null)
				->save();
		}
	}

	/**
	 * checking if any quote item has allocation data.
	 *
	 * @param Mage_Sales_Model_Order $quote the quote to check if it's items have any allocation data
	 *
	 * @return boolean, true reserved allocation is found, false no allocation data found on any quote item
	 */
	public function hasAllocation($quote)
	{
		$hasReservation = false;
		foreach ($quote->getAllItems() as $item) {
			// find the reservation data in the quote item
			if (trim($item->getEb2cReservationId()) !== '') {
				$hasReservation = true;
				break;
			}
		}
		return $hasReservation;
	}

	/**
	 * Check if the reserved allocation exceed the maximum expired setting.
	 *
	 * @param Mage_Sales_Model_Order $quote the quote to check if it's items have any allocation data
	 *
	 * @return boolean, true reserved allocation is found, false no allocation data found on any quote item
	 */
	public function isExpired($quote)
	{
		$isExpired = false;
		foreach ($quote->getAllItems() as $item) {
			// find the reservation data in the quote item
			if (trim($item->getEb2cReservationExpires()) !== '') {
				$reservedExpiredDateTime = new DateTime($item->getEb2cReservationExpires());
				$currentDateTime = new DateTime(gmdate('c'));
				$interval = $reservedExpiredDateTime->diff($currentDateTime);
				$differenceInMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
				// check if the current date time exceed the maximum allocation expired in minutes
				if ($differenceInMinutes > (int) $this->getHelper()->getConfigModel()->allocation_expired) {
					$isExpired = true;
					break;
				}
			}
		}
		return $isExpired;
	}

	/**
	 * update quote with allocation response data.
	 *
	 * @param Mage_Sales_Model_Quote_Item $quoteItem the item to be updated with eb2c data
	 * @param array $quoteData the data from eb2c for the quote item
	 *
	 * @return string, the allocation error message for that particular inventory
	 */
	protected function _updateQuoteWithEb2cAllocation($quoteItem, $quoteData)
	{
		$results = '';

		// get quote from quote-item
		$quote = $quoteItem->getQuote();

		// Set the message allocation failure, adjust quote with quantity reserved.
		if ($quoteData['qty'] > 0 && $quoteItem->getQty() > $quoteData['qty']) {
			// save reservation data to inventory detail
			$quoteItem->setQty($quoteData['qty'])
				->setEb2cReservationId($quoteData['reservation_id'])
				->setEb2cReservationExpires($quoteData['reservation_expires'])
				->setEb2cQtyReserved($quoteData['qty'])
				->save();

			// save the quote
			$quote->save();

			$results = 'Sorry, we only have ' . $quoteData['qty'] . ' of item "' . $quoteItem->getSku() . '" in stock.';
		} elseif ($quoteData['qty'] <= 0) {
			// removed the out of stock allocated item
			$quote->deleteItem($quoteItem);
			$results = 'Sorry, item "' . $quoteItem->getSku() . '" out of stock.';
		}

		return $results;
	}

	/**
	 * Rolling back allocation request.
	 *
	 * @param Mage_Sales_Model_Quote $quote, the quote to generate request XMLfrom
	 *
	 * @return string, the string xml message
	 */
	public function rollbackAllocation($quote)
	{
		// remove last allocations data from quote item
		$this->_emptyQuoteAllocation($quote);

		$rollbackAllocationResponseMessage = '';
		try{
			// build request
			$rollbackAllocationRequestMessage = $this->buildRollbackAllocationRequestMessage($quote);

			// make request to eb2c for inventory rollback allocation
			$rollbackAllocationResponseMessage = $this->getHelper()->getApiModel()
				->setUri($this->getHelper()->getOperationUri('rollback_allocation'))
				->request($rollbackAllocationRequestMessage);
		}catch(Exception $e){
			Mage::logException($e);
		}

		return $rollbackAllocationResponseMessage;
	}

	/**
	 * Build  Rollback Allocation request.
	 *
	 * @param Mage_Sales_Model_Quote $quote, the quote to generate request XML from
	 *
	 * @return DOMDocument The XML document, to be sent as request to eb2c.
	 */
	public function buildRollbackAllocationRequestMessage($quote)
	{
		$domDocument = Mage::helper('eb2ccore')->getNewDomDocument();
		$rollbackAllocationRequestMessage = $domDocument->addElement('RollbackAllocationRequestMessage', null, $this->getHelper()->getXmlNs())->firstChild;
		$rollbackAllocationRequestMessage->setAttribute('requestId', $this->getHelper()->getRequestId($quote->getEntityId()));
		$rollbackAllocationRequestMessage->setAttribute('reservationId', $this->getHelper()->getReservationId($quote->getEntityId()));

		return $domDocument;
	}
}
