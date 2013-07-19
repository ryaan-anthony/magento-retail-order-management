<?php
/**
 * @category   TrueAction
 * @package    TrueAction_Eb2c
 * @copyright  Copyright (c) 2013 True Action Network (http://www.trueaction.com)
 */
/**
 * tests the tax calculation class.
 */
class TrueAction_Eb2cTax_Test_Model_ResponseTest extends EcomDev_PHPUnit_Test_Case
{
	public static $respXml = '';
	public static $cls;
	public static $reqXml;
	public $itemResults;
	public $request;

	public static function setUpBeforeClass()
	{
		self::$cls = new ReflectionClass(
			'TrueAction_Eb2cTax_Model_Response'
		);
		$path = dirname(__FILE__) . '/ResponseTest/fixtures/response.xml';
		self::$respXml = file_get_contents($path);
		$path = dirname(__FILE__) . '/ResponseTest/fixtures/request.xml';
		self::$reqXml = file_get_contents($path);
	}

	public function setUp()
	{
		parent::setUp();
		$_SESSION = array();
		$_baseUrl = Mage::getStoreConfig('web/unsecure/base_url');
		$this->app()->getRequest()->setBaseUrl($_baseUrl);
		$quote = Mage::getModel('sales/quote')->loadByIdWithoutStore(3);
		$this->request = Mage::getModel('eb2ctax/request', array(
			'quote' => $quote
		));

		$docObject = new TrueAction_Dom_Document('1.0', 'UTF-8');
		$docObject->loadXML(self::$reqXml);
		$requestReflector = new ReflectionObject($this->request);
		$doc = $requestReflector->getProperty('_doc');
		$doc->setAccessible(true);
		$doc->setValue($this->request, $docObject);
	}

	/**
	 * Testing getResponseForItem method
	 *
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testGetResponseForItem()
	{
		$response = Mage::getModel('eb2ctax/response', array(
			'xml' => self::$respXml,
			'request' => $this->request
		));

		$item = $this->getModelMock('sales/quote_item', array('getSku'));
		$item->expects($this->any())
			->method('getSku')
			->will($this->returnValue('gc_virtual1'));
		$addressMock1 = $this->getModelMock('sales/quote_address');
		$addressMock1->expects($this->any())
			->method('getId')
			->will($this->returnValue(1));
		$responseItem = $response->getResponseForItem($item, $addressMock1);
		$this->assertNotNull($responseItem);
		$this->assertSame(3, count($responseItem->getTaxQuotes()));
	}

	/**
	 * Testing getResponseItems method
	 *
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testGetResponseItems()
	{
		$response = Mage::getModel('eb2ctax/response', array(
			'xml' => self::$respXml,
			'request' => $this->request
		));

		$this->assertNotNull(
			$response->getResponseItems()
		);
	}

	/**
	 * Testing isValid method
	 *
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testIsValid()
	{
		$response = Mage::getModel('eb2ctax/response', array(
			'xml' => self::$respXml,
			'request' => $this->request
		));
		$this->assertTrue($response->isValid());
	}

	/**
	 * Testing _construct method - valid request/response match xml
	 *
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testConstructValidRequestResponseMatch()
	{
		$response = Mage::getModel('eb2ctax/response', array(
			'xml' => self::$respXml,
			'request' => $this->request
		));

		$addressMock = $this->getModelMock('sales/quote_address', array('getId'));
		$addressMock->expects($this->any())
			->method('getId')
			->will($this->returnValue(1));

		$responseReflector = new ReflectionObject($response);
		$addressProperty = $responseReflector->getProperty('_address');
		$addressProperty->setAccessible(true);
		$addressProperty->setValue($response, $addressMock);

		$constructMethod = $responseReflector->getMethod('_construct');
		$constructMethod->setAccessible(true);

		$this->assertNull(
			$constructMethod->invoke($response)
		);
	}

	/**
	 * Testing _construct method - invalid request/response match xml
	 *
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testConstructInvalidRequestResponseMatch()
	{
		$docObject = new TrueAction_Dom_Document('1.0', 'UTF-8');
		$docObject->loadXML(file_get_contents(dirname(__FILE__) . '/ResponseTest/fixtures/request-invalid.xml'));
		$requestReflector = new ReflectionObject($this->request);
		$doc = $requestReflector->getProperty('_doc');
		$doc->setAccessible(true);
		$doc->setValue($this->request, $docObject);

		$response = Mage::getModel('eb2ctax/response', array(
			'xml' => self::$respXml,
			'request' => $this->request
		));

		$responseReflector = new ReflectionObject($response);
		$constructMethod = $responseReflector->getMethod('_construct');
		$constructMethod->setAccessible(true);

		$this->assertNull(
			$constructMethod->invoke($response)
		);
	}

	/**
	 * Testing _construct method - invalid request/response match xml because of MailingAddress[@id="2"] element is different
	 *
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testConstructMailingAddressMisMatch()
	{
		$docObject = new TrueAction_Dom_Document('1.0', 'UTF-8');
		$docObject->loadXML(file_get_contents(dirname(__FILE__) . '/ResponseTest/fixtures/request-invalid-2.xml'));
		$requestReflector = new ReflectionObject($this->request);
		$doc = $requestReflector->getProperty('_doc');
		$doc->setAccessible(true);
		$doc->setValue($this->request, $docObject);

		$response = Mage::getModel('eb2ctax/response', array(
			'xml' => self::$respXml,
			'request' => $this->request
		));

		$responseReflector = new ReflectionObject($response);
		$constructMethod = $responseReflector->getMethod('_construct');
		$constructMethod->setAccessible(true);

		$this->assertNull(
			$constructMethod->invoke($response)
		);
	}


	/**
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testLoadAddress()
	{
		$xmlPath = dirname(__FILE__) . '/ResponseTest/fixtures/responseSplitAcrossShipGroups.xml';
		$response = Mage::getModel(
			'eb2ctax/response',
			array(
				'xml' => file_get_contents($xmlPath)
			)
		);
		$rf = new ReflectionObject($response);
		$loadAddress = $rf->getMethod('_loadAddress');
		$loadAddress->setAccessible(true);
		$loadAddress->invoke($response, 1);

		$address = $rf->getProperty('_address');
		$address->setAccessible(true);
		$a = $address->getValue($response);
		$this->assertInstanceOf('Mage_Sales_Model_Quote_Address', $a);
		$this->assertSame('1', $a->getId());
	}


	/**
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testGetAddress()
	{
		$xmlPath = dirname(__FILE__) . '/ResponseTest/fixtures/responseSplitAcrossShipGroups.xml';
		$response = Mage::getModel(
			'eb2ctax/response',
			array(
				'xml' => file_get_contents($xmlPath)
			)
		);
		$rf = new ReflectionObject($response);
		$docRf = $rf->getProperty('_doc');
		$docRf->setAccessible(true);
		$doc = $docRf->getValue($response);
		$x = new DOMXPath($doc);
		$x->registerNamespace('a', $doc->documentElement->namespaceURI);
		$shipGroups = $x->query('//a:ShipGroup');
		// NOTE: THIS IS DEPENDENT ON THE ORDER OF THE SHIPGROUPS IN THE XML
		foreach ($shipGroups as $index => $shipGroup) {
			$this->assertNotNull($shipGroup);
			$getAddress = $rf->getMethod('_getAddress');
			$getAddress->setAccessible(true);
			$a = $getAddress->invoke($response, $shipGroup);
			$this->assertInstanceOf('Mage_Sales_Model_Quote_Address', $a);
			$this->assertSame((string)($index + 1), $a->getId());
		}
	}

	/**
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testGetAddressFail()
	{
		$xmlPath = dirname(__FILE__) . '/ResponseTest/fixtures/responseSplitAcrossShipGroups.xml';
		$response = Mage::getModel(
			'eb2ctax/response',
			array(
				'xml' => file_get_contents($xmlPath)
			)
		);
		$rf = new ReflectionObject($response);
		$getAddress = $rf->getMethod('_getAddress');
		$getAddress->setAccessible(true);
		$docRf = $rf->getProperty('_doc');
		$docRf->setAccessible(true);
		$doc = $docRf->getValue($response);
		$x = new DOMXPath($doc);
		$x->registerNamespace('a', $doc->documentElement->namespaceURI);
		$shipGroups = $x->query('//a:ShipGroup');
		$shipGroup = $shipGroups->item(0)->parentNode->createChild('ShipGroup');
		$getAddress->invoke($response, $shipGroup);
		$this->assertFalse($response->isValid());
	}

	/**
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 */
	public function testItemSplitAcrossShipgroups()
	{
		$xmlPath = dirname(__FILE__) . '/ResponseTest/fixtures/responseSplitAcrossShipGroups.xml';
		$response = Mage::getModel(
			'eb2ctax/response',
			array(
				'xml' => file_get_contents($xmlPath)
			)
		);

		$addressMock1 = $this->getModelMock('sales/quote_address', array('getId'));
		$addressMock1->expects($this->any())
			->method('getId')
			->will($this->returnValue(1));
		$addressMock2 = $this->getModelMock('sales/quote_address', array('getId'));
		$addressMock2->expects($this->any())
			->method('getId')
			->will($this->returnValue(2));

		$itemMock = $this->getModelMock('sales/quote_item', array('getSku'));
		$itemMock->expects($this->any())
			->method('getSku')
			->will($this->returnValue('gc_virtual1'));
		$responseItems = $response->getResponseItems();

		$this->assertNotEmpty($response->getResponseItems());
		$itemResponse = $response->getResponseForItem($itemMock, $addressMock1);
		$this->assertNotNull($itemResponse);
		$this->assertSame('1', $itemResponse->getLineNumber());
		$itemResponse = $response->getResponseForItem($itemMock, $addressMock2);
		$this->assertNotNull($itemResponse);
		$this->assertSame('2', $itemResponse->getLineNumber());
	}

	/**
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testItemSplitAcrossShipGroups.yaml
	 * @loadExpectation
	 */
	public function testDiscounts()
	{
		$response = Mage::getModel('eb2ctax/response', array('xml' => self::$respXml));
		$addressMethods = array('getId');
		$itemMethods    = array('getSku');
		$mockAddress    = $this->getModelMock('sales/quote_address', $addressMethods);
		$mockItem       = $this->getModelMock('sales/quote_address_item', $itemMethods);

		$mockAddress->expects($this->any())
			->method('getId')
			->will($this->returnValue(1));
		$mockItem->expects($this->any())
			->method('getSku')
			->will($this->returnValue('gc_virtual1'));

		$ir = $response->getResponseForItem($mockItem, $mockAddress);
		$ds = $ir->getTaxQuoteDiscounts();
		$this->assertSame(3, count($ds));
		foreach ($ds as $d) {
			$e = $this->expected('0-' . $d->getDiscountId());
			$this->assertSame((float)$e->getAmount(), $d->getAmount());
			$this->assertSame((float)$e->getEffectiveRate(), $d->getEffectiveRate());
			$this->assertSame((float)$e->getTaxableAmount(), $d->getTaxableAmount());
			$this->assertSame((float)$e->getCalculatedTax(), $d->getCalculatedTax());
		}
	}

	public function testResponseQuote()
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
 			<Taxes xmlns="http://api.gsicommerce.com/schema/checkout/1.0">
				<Tax taxType="SELLER_USE" taxability="TAXABLE">
					<Situs>DESTINATION</Situs>
					<Jurisdiction jurisdictionLevel="STATE" jurisdictionId="31152">PENNSYLVANIA</Jurisdiction>
					<Imposition impositionType="General Sales and Use Tax">Sales and Use Tax</Imposition>
					<EffectiveRate>0.06</EffectiveRate>
					<TaxableAmount>2.0</TaxableAmount>
					<CalculatedTax>0.12</CalculatedTax>
				</Tax>
			</Taxes>';
		$doc = new TrueAction_Dom_Document();
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($xml);
		$node = $doc->documentElement->firstChild;
		$a = array(
			'node'           => $node,
			'code'           => 'PENNSYLVANIA-Sales and Use Tax',
			'situs'          => 'DESTINATION',
			'effective_rate' => 0.06,
			'taxable_amount' => 2.00,
			'calculated_tax' => 0.12,
		);
		$obj = Mage::getModel('eb2ctax/response_quote', array('node' => $node));
		$this->assertSame($a, $obj->getData());
	}
}