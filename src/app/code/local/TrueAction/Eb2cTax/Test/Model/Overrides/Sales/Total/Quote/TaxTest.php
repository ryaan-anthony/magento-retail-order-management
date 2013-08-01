<?php
class TrueAction_Eb2cTax_Test_Model_Overrides_Sales_Total_Quote_TaxTest extends TrueAction_Eb2cTax_Test_Base
{
	public static $isDiscountTest = false;

	public function setUp()
	{
		$this->_setupBaseUrl();
	}

	/**
	 * @loadExpectation taxtest.yaml
	 */
	public function testCalcTaxForItemBeforeDiscount()
	{
		$this->markTestIncomplete('temporarily disabling');
		// set up the config registry to supply the necessary taxApplyAfterDiscount configuration
		Mage::unregister('_helper/tax');
		$configRegistry = $this->getModelMock('eb2ccore/config_registry', array('__get', 'setStore'));
		$configRegistry->expects($this->any())
			->method('__get')
			->will($this->returnValueMap(array(array('taxApplyAfterDiscount', false))));
		$configRegistry->expects($this->any())
			->method('setStore')
			->will($this->returnSelf());
		$this->replaceByMock('model', 'eb2ccore/config_registry', $configRegistry);

		// set up the SUT
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$calcTaxForItemMethod = $this->_reflectMethod($taxModel, '_calcTaxForItem');

		$response = Mage::getModel('eb2ctax/response', array('xml' => self::$responseXml));
		Mage::helper('tax')->getCalculator()->setTaxResponse($response);

		$address = $this->getModelMock('sales/quote_address', array('getId'));
		$address->expects($this->any())
			->method('getId')
			->will($this->returnValue(15));
		$this->_reflectProperty($taxModel, '_address')
			->setValue($taxModel, $address);

		$items = $this->_mockItemsCalcTaxForItem(true);

		$itemSelector = new Varien_Object(array('address' => $address));

		// precondition check
		$this->assertSame(2, count($items), 'number of items (' . count($items) . ') is not 2');
		foreach ($items as $item) {
			$expectationPath = '0-' . $item->getId();
			$e = $this->expected($expectationPath);
			$itemSelector->setItem($item);

			$calcTaxForItemMethod->invoke($taxModel, $itemSelector);

			$this->assertEquals(
				$e->getTaxAmount(),
				$item->getTaxAmount(),
				"$expectationPath: tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseTaxAmount(),
				$item->getBaseTaxAmount(),
				"$expectationPath: base_tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getRowTotalInclTax(),
				$item->getRowTotalInclTax(),
				"$expectationPath: row_total_incl_tax didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseRowTotalInclTax(),
				$item->getBaseRowTotalInclTax(),
				"$expectationPath: base_row_total_incl_tax didn't match expectation"
			);
			$this->assertEquals(
				$e->getHiddenTaxAmount(),
				$item->getHiddenTaxAmount(),
				"$expectationPath: hidden_tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseHiddenTaxAmount(),
				$item->getBaseHiddenTaxAmount(),
				"$expectationPath: base_hidden_tax_amount didn't match expectation"
			);
		}
		$this->assertSame(0, $address->getTotalAmount('hidden_tax'));
		$this->assertSame(0, $address->getBaseTotalAmount('hidden_tax'));
		$this->assertSame(0, $address->getTotalAmount('shipping_hidden_tax'));
		$this->assertSame(0, $address->getBaseTotalAmount('shipping_hidden_tax'));
	}

	/**
	 * @loadExpectation taxtest.yaml
	 */
	public function testCalcTaxForItemAfterDiscount()
	{
		$this->markTestIncomplete('temporarily disabling');
		// set up the config registry to supply the necessary taxApplyAfterDiscount configuration
		Mage::unregister('_helper/tax');
		$configRegistry = $this->getModelMock('eb2ccore/config_registry', array('__get', 'setStore'));
		$configRegistry->expects($this->any())
			->method('__get')
			->will($this->returnValueMap(array(array('taxApplyAfterDiscount', true))));
		$configRegistry->expects($this->any())
			->method('setStore')
			->will($this->returnSelf());
		$this->replaceByMock('model', 'eb2ccore/config_registry', $configRegistry);

		// set up the SUT
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$calcTaxForItemMethod = $this->_reflectMethod($taxModel, '_calcTaxForItem');

		$response = Mage::getModel('eb2ctax/response', array('xml' => self::$responseXml));
		Mage::helper('tax')->getCalculator()->setTaxResponse($response);

		$address = $this->getModelMock('sales/quote_address', array('getId'));
		$address->expects($this->any())
			->method('getId')
			->will($this->returnValue(15));
		$this->_reflectProperty($taxModel, '_address')
			->setValue($taxModel, $address);

		$items = $this->_mockItemsCalcTaxForItem(false);
		$itemSelector = new Varien_Object(array('address' => $address));

		// precondition check
		$this->assertSame(2, count($items), 'number of items (' . count($items) . ') is not 2');
		foreach ($items as $item) {
			$expectationPath = '1-' . $item->getId();
			$e = $this->expected($expectationPath);

			$itemSelector->setItem($item);
			$calcTaxForItemMethod->invoke($taxModel, $itemSelector);

			$this->assertEquals(
				$e->getTaxAmount(),
				$item->getTaxAmount(),
				"$expectationPath: tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseTaxAmount(),
				$item->getBaseTaxAmount(),
				"$expectationPath: base_tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getRowTotalInclTax(),
				$item->getRowTotalInclTax(),
				"$expectationPath: row_total_incl_tax didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseRowTotalInclTax(),
				$item->getBaseRowTotalInclTax(),
				"$expectationPath: base_row_total_incl_tax didn't match expectation"
			);
			$this->assertEquals(
				$e->getHiddenTaxAmount(),
				$item->getHiddenTaxAmount(),
				"$expectationPath: hidden_tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseHiddenTaxAmount(),
				$item->getBaseHiddenTaxAmount(),
				"$expectationPath: base_hidden_tax_amount didn't match expectation"
			);
		}
		$this->assertSame(1.6, $address->getTotalAmount('hidden_tax'));
		$this->assertSame(1.6, $address->getBaseTotalAmount('hidden_tax'));
		$this->assertSame(0, $address->getTotalAmount('shipping_hidden_tax'));
		$this->assertSame(0, $address->getBaseTotalAmount('shipping_hidden_tax'));
	}

	/**
	 * @test
	 * @large
	 */
	public function testCalcTaxForAddress()
	{
		$items = $this->_mockItemsCalcTaxForItem();
		$quote = $this->getModelMock('sales/quote', array('getTaxesForItems', 'setTaxesForItems'));
		$quote->expects($this->any())
			->method('getTaxesForItems')
			->will($this->returnValue(array('8' => 'foo')));
		$quote->expects($this->once())
			->method('setTaxesForItems')
			->with($this->equalTo(array(
				$items[0]->getId() => $this->classicJeansAppliedRatesBefore,
				$items[1]->getId() => $this->classicJeansAppliedRatesBefore,
				'8' => 'foo',
			)))
			->will($this->returnSelf());
		$address = $this->_buildModelMock(
			'sales/quote_address',
			array(
				'getAllNonNominalItems' => $this->returnValue($items),
				'getQuote'              => $this->returnValue($quote)
			)
		);
		$this->_mockCalculator2();

		// create the tax model after mocking the calculator
		// so that it gets initialized with the mock
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$calcTaxForAddressMethod = $this->_reflectMethod($taxModel, '_calcTaxForAddress');

		$this->assertSame($quote, $address->getQuote());
		$this->_reflectProperty($taxModel, '_address')
			->setValue($taxModel, $address);
		$calcTaxForAddressMethod->invoke($taxModel, $address);

		$this->assertSame(2, count($address->getAppliedTaxes()));
		$process = 1;
		foreach ($address->getAppliedTaxes() as $applied) {
			$this->assertSame($process, $applied['process']);
			++$process;
		}
	}

	public function testCollect()
	{
		$items = array(
			$this->_mockItem(),
		);

		$addressMock = $this->_buildModelMock(
			'sales/quote_address',
			array(
				'getId'                 => $this->returnValue(1),
				'getAllNonNominalItems' => $this->returnValue($items),
				'getQuote'              => null
			)
		);

		$quoteMock = $this->getModelMock('sales/quote', array('getStore'));
		$quoteMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));

		$addressMock->expects($this->any())
			->method('getQuote')
			->will($this->returnValue($quoteMock));

		$this->_mockCalculator();
		// create the tax model after mocking the calculator so that it gets initialized with the
		// mock
		$tax = Mage::getModel('tax/sales_total_quote_tax');
		$tax->collect($addressMock);
		// assert the item->getTaxAmount is as expected.
	}


	public function testCollectChildItem()
	{
		$items = array(
			$this->_mockChildItem(),
			$this->_mockItem(),
			$this->_mockParentItem()
		);

		$addressMock = $this->_buildModelMock(
			'sales/quote_address',
			array(
				'getId'                 => $this->returnValue(1),
				'getAllNonNominalItems' => $this->returnValue($items),
				'getQuote'              => null
			)
		);

		$quoteMock = $this->getModelMock('sales/quote', array('getStore'));
		$quoteMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));

		$addressMock->expects($this->any())
			->method('getQuote')
			->will($this->returnValue($quoteMock));

		$this->_mockCalculator();
		// create the tax model after mocking the calculator so that it gets initialized with the
		// mock
		$tax = Mage::getModel('tax/sales_total_quote_tax');
		$tax->collect($addressMock);
		// assert the item->getTaxAmount is as expected.
	}

	public function testCollectNoItems()
	{
		$addressMock = $this->_buildModelMock(
			'sales/quote_address',
			array(
				'getQuote'              => null,
				'getAllNonNominalItems' => $this->returnValue(array()),
				'getId'                 => $this->returnValue(1)
			)
		);

		$quoteMock = $this->getModelMock('sales/quote', array('getStore', 'getId', 'getItemsCount', 'getBillingAddress', 'getAllVisibleItems'));
		$quoteMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));

		$addressMock->expects($this->any())
			->method('getQuote')
			->will($this->returnValue($quoteMock));

		$tax = Mage::getModel('tax/sales_total_quote_tax');
		$tax->collect($addressMock);
	}

	/**
	 * @test
	 */
	public function testSaveAppliedTaxes()
	{
		$applied = array(
			array(
				'percent' => 6.0,
				'id' => 0,
				'amount' => 6.00,
			),
			array(
				'percent' => 8.0,
				'id' => 1,
				'amount' => 8.00,
			),
			array(
				'percent' => 1.2,
				'id' => 2,
				'amount' => 0.00,
			),
			array(
				'percent' => 0.0,
				'id' => 3,
			),
		);
		// these values seem to be unused in the method implementation so their value
		// here doesn't matter much.
		$amount = 13.37;
		$baseAmount = 13.37;
		$rate = 13.37;

		$addressApplied = array(
			array(
				'percent' => 12.0,
				'id' => 0,
				'amount' => 12,
			),
		);
		$address = $this->getModelMock('sales/quote_address', array('getAppliedTaxes', 'setAppliedTaxes'));
		$address->expects($this->any())
			->method('getAppliedTaxes')
			->will($this->returnValue($addressApplied));
		$address->expects($this->once())
			->method('setAppliedTaxes')
			->with($this->equalTo(array(
				array(
					'percent' => 12.0,
					'id' => 0,
					'amount' => 12,
				),
				array(
					'percent' => 8.0,
					'id' => 1,
					'amount' => 8.00,
					'process' => 1,
				)
			)))
			->will($this->returnSelf());

		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$saveAppliedTaxesMethod = $this->_reflectMethod($taxModel, '_saveAppliedTaxes');
		$saveAppliedTaxesMethod->invoke($taxModel, $address, $applied, $amount, $baseAmount, $rate);
	}

	/**
	 * @test
	 */
	public function testProcessConfigArray()
	{
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$config = $taxModel->processConfigArray(array(), null);
		$this->assertSame(array('after' => array('discount')), $config);
	}

	protected function _mockCalculator2()
	{
		$calcMock = $this->_buildModelMock(
			'tax/calculation',
			array(
				'getTax'          => $this->returnValue(8.0),
				'getTaxForAmount' => $this->returnValue(8.0),
				'getAppliedRates' => $this->returnValue($this->classicJeansAppliedRatesBefore)
			)
		);
		$this->replaceByMock('singleton', 'tax/calculation', $calcMock);
		$this->replaceByMock('model', 'tax/calculation', $calcMock);
	}

	protected function _mockCalculator()
	{
		$calcMock = $this->getModelMock('tax/calculation', array('getAppliedRates', 'getTax', 'getTaxForAmount'));
		$calcMock->expects($this->any())
			->method('getTax')->will($this->returnValue(8.25));
		$calcMock->expects($this->any())
			->method('getTaxForAmount')->will($this->returnValue(8.25));
		$calcMock->expects($this->any())
			->method('getAppliedRates')->will($this->returnValue(array(
				'jurisdiction-imposition-rate' => array(
					'percent'     => 8.25,
					'id'          => 'jurisdiction-imposition-rate',
					'process'     => 0,
					'amount'      => 8.25,
					'base_amount' => 8.25,
					'rates' => array(
						0 => array(
							'code'     => 'jurisdiction-imposition',
							'title'    => 'jurisdiction-imposition',
							'position' => '1',
							'priority' => '1',
						),
					),
				),
			))
		);
		$this->replaceByMock('singleton', 'tax/calculation', $calcMock);
		$this->replaceByMock('model', 'tax/calculation', $calcMock);
	}

	protected function _mockParentItem()
	{
		$productMock = $this->getModelMock('catalog/product', array('isVirtual'));

		$methods = array('getParentItem', 'getQty', 'getCalculationPriceOriginal', 'getStore', 'getId', 'getProduct', 'getHasChildren', 'isChildrenCalculated', 'getChildren');
		$itemMock = $this->getModelMock('sales/quote_item', $methods);
		$itemMock->expects($this->any())
			->method('getId')
			->will($this->returnValue(1));
		$itemMock->expects($this->any())
			->method('getProduct')
			->will($this->returnValue($productMock));
		$itemMock->expects($this->any())
			->method('getHasChildren')
			->will($this->returnValue(true));
		$itemMock->expects($this->any())
			->method('getCalculationPriceOriginal')
			->will($this->returnValue(100));
		$itemMock->expects($this->any())
			->method('isChildrenCalculated')
			->will($this->returnValue(true));
		$itemMock->expects($this->any())
			->method('getChildren')
			->will($this->returnValue(array($this->_mockChildItem())));
		$itemMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));
		$itemMock->expects($this->any())
			->method('getQty')
			->will($this->returnValue(1));
		return $itemMock;
	}

	protected function _mockItem()
	{
		$productMock = $this->getModelMock('catalog/product');
		$itemMock = $this->_buildModelMock(
			'sales/quote_item',
			array(
				'getId'                       => $this->returnValue(1),
				'getSku'                      => $this->returnValue('classic-jeans'),
				'getProduct'                  => $this->returnValue($productMock),
				'getStore'                    => $this->returnValue(Mage::app()->getStore()),
				'getHasChildren'              => $this->returnValue(false),
				'getQty'                      => $this->returnValue(1),
				'getCalculationPriceOriginal' => $this->returnValue(99.99),
				'getIsPriceInclVat'           => null,
			)
		);
		return $itemMock;
	}

	protected function _mockItemsCalcTaxForItem($after = false)
	{
		$items = array();
		$methods = array('getDiscountAmount', 'getBaseDiscountAmount', 'getSku', 'getTaxableAmount', 'getBaseTaxableAmount', 'getIsPriceInclVat', 'getId', 'setTaxRates');
		$itemMock = $this->getModelMock('sales/quote_item', $methods);
		$itemMock->expects($this->any())
			->method('getId')
			->will($this->returnValue(6));
		$itemMock->expects($this->any())
			->method('getSku')
			->will($this->returnValue('gc_virtual1'));
		$itemMock->expects($this->any())
			->method('getTaxableAmount')
			->will($this->returnValue(50.0));
		$itemMock->expects($this->any())
			->method('getBaseTaxableAmount')
			->will($this->returnValue(50.0));
		$itemMock->expects($this->any())
			->method('getDiscountAmount')
			->will($this->returnValue(0));
		$itemMock->expects($this->any())
			->method('getBaseDiscountAmount')
			->will($this->returnValue(0));
		$itemMock->expects($this->any())
			->method('setTaxRates')
			->with($this->equalTo(($after) ? $this->classicJeansAppliedRatesAfter : $this->classicJeansAppliedRatesBefore))
			->will($this->returnSelf());
		$items[] = $itemMock;

		$itemMock = $this->getModelMock('sales/quote_item', $methods);
		$itemMock->expects($this->any())
			->method('getId')
			->will($this->returnValue(7));
		$itemMock->expects($this->any())
			->method('getSku')
			->will($this->returnValue('classic-jeans'));
		$itemMock->expects($this->any())
			->method('getTaxableAmount')
			->will($this->returnValue(99.99));
		$itemMock->expects($this->any())
			->method('getBaseTaxableAmount')
			->will($this->returnValue(99.99));
		$itemMock->expects($this->any())
			->method('getDiscountAmount')
			->will($this->returnValue(20));
		$itemMock->expects($this->any())
			->method('getBaseDiscountAmount')
			->will($this->returnValue(20));
		$itemMock->expects($this->any())
			->method('setTaxRates')
			->with($this->equalTo(
				($after) ? $this->classicJeansAppliedRatesAfter : $this->classicJeansAppliedRatesBefore
			))
			->will($this->returnSelf());
		$items[] = $itemMock;
		return $items;
	}

	protected function _mockChildItem()
	{
		$productMock = $this->getModelMock('catalog/product', array('isVirtual'));

		$methods = array('getParentItem', 'getQty', 'getCalculationPriceOriginal', 'getStore', 'getId', 'getProduct', 'getHasChildren', 'isChildrenCalculated', 'getChildren');
		$itemMock = $this->getModelMock('sales/quote_item', $methods);
		$itemMock->expects($this->any())
			->method('getId')
			->will($this->returnValue(1));
		$itemMock->expects($this->any())
			->method('getParentItem')
			->will($this->returnSelf());
		$itemMock->expects($this->any())
			->method('getProduct')
			->will($this->returnValue($productMock));
		$itemMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));
		return $itemMock;
	}

	public  function getTaxCallback($itemSelector) {
		if ($itemSelector->getItem()->getSku() == 'classic-jeans') {
			return (self::$isDiscountTest) ? 6.4 : 8.0;
		}
		return 0;
	}

	public function getDiscountTaxCallback($item, $address) {
		if ($itemSelector->getItem()->getSku() == 'classic-jeans') {
			return (self::$isDiscountTest) ? 1.6 : 1.6;
		}
		return 0;
	}

	public function getAppliedRatesCallback($itemSelector) {
		if ($item->getSku() == 'classic-jeans') {
			return (self::$isDiscountTest) ?
				$this->classicJeansAppliedRatesAfter :
				$this->classicJeansAppliedRatesBefore;
		}
		return 0;
	}

	public $classicJeansAppliedRatesBefore = array(
		'PENNSYLVANIA-Sales and Use Tax-0.06' => array(
			'percent'     => 0.06,
			'id'          => 'PENNSYLVANIA-Sales and Use Tax-0.06',
			'amount'      => 6.0,
			'base_amount' => 6.0,
			'rates' => array(
				0 => array(
					'code'     => 'PENNSYLVANIA-Sales and Use Tax',
					'title'    => 'PENNSYLVANIA-Sales and Use Tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'PENNSYLVANIA-Random Tax-0.02' => array(
			'percent'     => 0.02,
			'id'          => 'PENNSYLVANIA-Random Tax-0.02',
			'amount'      => 2.0,
			'base_amount' => 2.0,
			'rates' => array(
				0 => array(
					'code'     => 'PENNSYLVANIA-Random Tax',
					'title'    => 'PENNSYLVANIA-Random Tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
	);

	public $classicJeansAppliedRatesAfter = array(
		'PENNSYLVANIA-Sales and Use Tax-0.06' => array(
			'percent'     => 0.06,
			'id'          => 'PENNSYLVANIA-Sales and Use Tax-0.06',
			'amount'      => 6.0,
			'base_amount' => 6.0,
			'rates' => array(
				0 => array(
					'code'     => 'PENNSYLVANIA-Sales and Use Tax',
					'title'    => 'PENNSYLVANIA-Sales and Use Tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'PENNSYLVANIA-Random Tax-0.02' => array(
			'percent'     => 0.02,
			'id'          => 'PENNSYLVANIA-Random Tax-0.02',
			'amount'      => 1.6,
			'base_amount' => 1.6,
			'rates' => array(
				0 => array(
					'code'     => 'PENNSYLVANIA-Random Tax',
					'title'    => 'PENNSYLVANIA-Random Tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
	);

	public static $responseXml = '<?xml version="1.0" encoding="UTF-8"?>
<TaxDutyQuoteRequest xmlns="http://api.gsicommerce.com/schema/checkout/1.0">
  <Currency><![CDATA[USD]]></Currency>
  <VATInclusivePricing><![CDATA[0]]></VATInclusivePricing>
  <CustomerTaxId/>
  <BillingInformation ref="_14"/>
  <Shipping>
    <ShipGroups>
      <ShipGroup id="shipGroup_15_FLATRATE" chargeType="FLATRATE">
        <DestinationTarget ref="_15"/>
        <Items>
          <OrderItem lineNumber="6">
            <ItemId><![CDATA[gc_virtual1]]></ItemId>
            <ItemDesc><![CDATA[Virtual Gift]]></ItemDesc>
            <HTSCode/>
            <Quantity><![CDATA[1]]></Quantity>
            <Pricing>
              <Merchandise>
                <Amount><![CDATA[50.0000]]></Amount>
                <TaxClass><![CDATA[2]]></TaxClass>
                <UnitPrice><![CDATA[50.0000]]></UnitPrice>
              </Merchandise>
            </Pricing>
          </OrderItem>
          <OrderItem lineNumber="7">
            <ItemId><![CDATA[classic-jeans]]></ItemId>
            <ItemDesc><![CDATA[Classic Jean]]></ItemDesc>
            <HTSCode/>
            <Quantity><![CDATA[1]]></Quantity>
            <Pricing>
              <Merchandise>
                <Amount><![CDATA[99.9900]]></Amount>
                <TaxData>
                  <TaxClass>89000</TaxClass>
                  <Taxes>
                    <Tax taxType="SELLER_USE" taxability="TAXABLE">
                      <Situs>DESTINATION</Situs>
                      <Jurisdiction jurisdictionId="31152" jurisdictionLevel="STATE">PENNSYLVANIA</Jurisdiction>
                      <Imposition impositionType="General Sales and Use Tax">Sales and Use Tax</Imposition>
                      <EffectiveRate>0.06</EffectiveRate>
                      <TaxableAmount>99.99</TaxableAmount>
                      <CalculatedTax>6.0</CalculatedTax>
                    </Tax>
                    <Tax taxType="SELLER_USE" taxability="TAXABLE">
                      <Situs>DESTINATION</Situs>
                      <Jurisdiction jurisdictionId="31152" jurisdictionLevel="STATE">PENNSYLVANIA</Jurisdiction>
                      <Imposition impositionType="Random Tax">Random Tax</Imposition>
                      <EffectiveRate>0.02</EffectiveRate>
                      <TaxableAmount>99.99</TaxableAmount>
                      <CalculatedTax>2.0</CalculatedTax>
                    </Tax>
                  </Taxes>
                </TaxData>
                <PromotionalDiscounts>
                  <Discount calculateDuty="false" id="334">
                    <Amount>20.00</Amount>
                    <Taxes>
                      <Tax taxType="SELLER_USE" taxability="TAXABLE">
                        <Situs>DESTINATION</Situs>
                        <Jurisdiction jurisdictionId="31152" jurisdictionLevel="STATE">PENNSYLVANIA</Jurisdiction>
                        <Imposition impositionType="General Sales and Use Tax">Sales and Use Tax</Imposition>
                        <EffectiveRate>0.06</EffectiveRate>
                        <TaxableAmount>20.0</TaxableAmount>
                        <CalculatedTax>1.2</CalculatedTax>
                      </Tax>
                      <Tax taxType="SELLER_USE" taxability="TAXABLE">
                        <Situs>DESTINATION</Situs>
                        <Jurisdiction jurisdictionId="31152" jurisdictionLevel="STATE">PENNSYLVANIA</Jurisdiction>
                        <Imposition impositionType="Random Tax">Random Tax</Imposition>
                        <EffectiveRate>0.02</EffectiveRate>
                        <TaxableAmount>20.0</TaxableAmount>
                        <CalculatedTax>0.4</CalculatedTax>
                      </Tax>
                    </Taxes>
                  </Discount>
                </PromotionalDiscounts>
              </Merchandise>
            </Pricing>
          </OrderItem>
        </Items>
      </ShipGroup>
    </ShipGroups>
    <Destinations>
      <MailingAddress id="_14">
        <PersonName>
          <LastName><![CDATA[guy]]></LastName>
          <FirstName><![CDATA[extra]]></FirstName>
        </PersonName>
        <Address>
          <Line1><![CDATA[1 Shields]]></Line1>
          <City><![CDATA[davis]]></City>
          <MainDivision><![CDATA[CA]]></MainDivision>
          <CountryCode><![CDATA[US]]></CountryCode>
          <PostalCode><![CDATA[90210]]></PostalCode>
        </Address>
      </MailingAddress>
      <MailingAddress id="_15">
        <PersonName>
          <LastName><![CDATA[guy]]></LastName>
          <FirstName><![CDATA[extra]]></FirstName>
        </PersonName>
        <Address>
          <Line1><![CDATA[1 Shields]]></Line1>
          <City><![CDATA[davis]]></City>
          <MainDivision><![CDATA[CA]]></MainDivision>
          <CountryCode><![CDATA[US]]></CountryCode>
          <PostalCode><![CDATA[90210]]></PostalCode>
        </Address>
      </MailingAddress>
    </Destinations>
  </Shipping>
</TaxDutyQuoteRequest>';
}
