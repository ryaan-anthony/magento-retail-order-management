<?php
class EbayEnterprise_Eb2cTax_Test_Model_Overrides_Sales_Total_Quote_TaxTest extends EbayEnterprise_Eb2cCore_Test_Base
{
	public static $isDiscountTest = false;

	public function setUp()
	{
		parent::setUp();
		$this->_setupBaseUrl();
	}

	public function discountTaxCalculationSequence()
	{
		return array(
			array('beforediscount'),
			array('afterdiscount')
		);
	}

	/**
	 * @loadExpectation taxtest.yaml
	 * @dataProvider discountTaxCalculationSequence
	 */
	public function testCalcTaxForItemSingleItem($scenario)
	{
		// set up the config registry to supply the necessary taxApplyAfterDiscount configuration
		// @hack
		$configRegistry = $this->getModelMock('eb2ccore/config_registry', array('__get', 'setStore'));
		$configRegistry->expects($this->any())
			->method('__get')
			->will($this->returnValueMap(array(array('taxApplyAfterDiscount', $scenario === 'afterdiscount'))));
		$configRegistry->expects($this->any())
			->method('setStore')
			->will($this->returnSelf());
		$this->replaceByMock('model', 'eb2ccore/config_registry', $configRegistry);

		$address = $this->getModelMock('sales/quote_address', array('getId', 'setTotalAmount'));
		$address->expects($this->any())
			->method('getId')
			->will($this->returnValue(15));
		$items = $this->_mockSingleItemForCalcTaxForItem(true);
		$itemSelector = new Varien_Object(array('address' => $address));

		// setup the calculator
		$calcMock = $this->getModelMock('tax/calculation', array('getTax', 'getTaxForAmount', 'getAppliedRates'));
		$calcMock->expects($this->any())->method('getTax')->will($this->returnValueMap(array(
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 6.25),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.20),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 8.72),
		)));
		$calcMock->expects($this->any())->method('getDiscountTax')->will($this->returnValueMap(array(
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 0.77),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.07),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 0),
		)));
		$calcMock->expects($this->any())->method('getTaxForAmount')->will($this->returnValueMap(array(
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 6.25),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.20),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 8.72),
		)));
		$calcMock->expects($this->any())->method('getDiscountTaxForAmount')->will($this->returnValueMap(array(
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 0.77),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.07),
			array($this->anything(), EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 0),
		)));
		$calcMock->expects($this->any())->method('getAppliedRates')->will($this->returnValue(
			$scenario === 'afterdiscount' ? self::$classicJeansAppliedRatesAfterB : self::$classicJeansAppliedRatesBeforeB
		));

		// set up the SUT
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$this->_reflectProperty($taxModel, '_store')->setValue($taxModel, Mage::app()->getStore());
		$this->_reflectProperty($taxModel, '_calculator')->setValue($taxModel, $calcMock);
		$calcTaxForItemMethod = $this->_reflectMethod($taxModel, '_calcTaxForItem');
		$this->_reflectProperty($taxModel, '_address')
			->setValue($taxModel, $address);

		// precondition check
		$this->assertSame(1, count($items), 'number of items (' . count($items) . ') is not 1');
		foreach ($items as $item) {
			$e = $this->expected($scenario . '-' . $item->getId());

			$item->expects($this->any())
				->method('setTaxAmount')
				->with($this->equalTo($e->getTaxAmount()))
				->will($this->returnSelf());
			$item->expects($this->any())
				->method('setRowTotalInclTax')
				->with($this->equalTo($e->getRowTotalInclTax()))
				->will($this->returnSelf());
			$item->expects($this->any())
				->method('setHiddenTaxAmount')
				->with($this->equalTo($e->getHiddenTaxAmount()))
				->will($this->returnSelf());
			// base amounts
			$item->expects($this->any())
				->method('setBaseTaxAmount')
				->with($this->equalTo($e->getBaseTaxAmount()))
				->will($this->returnSelf());
			$item->expects($this->any())
				->method('setBaseRowTotalInclTax')
				->with($this->equalTo($e->getBaseRowTotalInclTax()))
				->will($this->returnSelf());
			$item->expects($this->any())
				->method('setBaseHiddenTaxAmount')
				->with($this->equalTo($e->getBaseHiddenTaxAmount()))
				->will($this->returnSelf());

			$e = $this->expected("{$scenario}-address");
			$address->expects($this->any())
				->method('setTotalAmount')
				->with($this->equalTo('hidden_tax'), $this->equalTo($e->getTotalAmountHiddenTax()))
				->will($this->returnSelf());
			$address->expects($this->any())
				->method('setTotalAmount')
				->with($this->equalTo('shipping_hidden_tax'), $this->equalTo($e->getTotalAmountShippingHiddenTax()))
				->will($this->returnSelf());
			// base amounts
			$address->expects($this->any())
				->method('setBaseTotalAmount')
				->with($this->equalTo('hidden_tax'), $this->equalTo($e->getBaseTotalAmountHiddenTax()))
				->will($this->returnSelf());
			$address->expects($this->any())
				->method('setBaseTotalAmount')
				->with($this->equalTo('shipping_hidden_tax'), $this->equalTo($e->getBaseTotalAmountShippingHiddenTax()))
				->will($this->returnSelf());

			$itemSelector->setItem($item);
			$calcTaxForItemMethod->invoke($taxModel, $itemSelector);
		}
	}

	protected function _mockSingleItemForCalcTaxForItem($after=false)
	{
		$methods = array(
			'getDiscountAmount',
			'getBaseDiscountAmount',
			'getSku',
			'getTaxableAmount',
			'getBaseTaxableAmount',
			'getIsPriceInclVat',
			'getId',
			'setTaxRates',
			'setTaxAmount',
			'setRowTotalInclTax',
			'setHiddenTaxAmount',
			'setBaseTaxAmount',
			'setBaseRowTotalInclTax',
			'setBaseHiddenTaxAmount',
		);
		$itemMock = $this->getModelMock('sales/quote_item', $methods);
		$itemMock->expects($this->any())
			->method('getId')
			->will($this->returnValue(6));
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
		return $itemMock;
	}

	public static $classicJeansAppliedRatesBeforeB = array(
		'state sales tax-0.06' => array(
			'percent'     => 6.25,
			'id'          => 'state sales tax-0.06',
			'amount'      => 6.25,
			'base_amount' => 6.25,
			'rates' => array(
				0 => array(
					'code'     => 'state sales tax',
					'title'    => 'state sales tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'shipping tax-0.06' => array(
			'percent'     => 1.33,
			'id'          => 'shipping tax-0.06',
			'amount'      => 0.20,
			'base_amount' => 0.20,
			'rates' => array(
				0 => array(
					'code'     => 'shipping tax',
					'title'    => 'shipping tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'duty tax-0.0262' => array(
			'percent'     => 2.62,
			'id'          => 'duty tax-0.0262',
			'amount'      => 0.51,
			'base_amount' => 0.51,
			'rates' => array(
				0 => array(
					'code'     => 'duty tax',
					'title'    => 'duty tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'eb2c-duty-amount' => array(
			'percent'     => null,
			'id'          => 'eb2c-duty-amount',
			'amount'      => 8.21,
			'base_amount' => 8.21,
			'rates' => array(
				0 => array(
					'code'     => 'eb2c-duty-amount',
					'title'    => 'Duty',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
	);

	public static $classicJeansAppliedRatesAfterB = array(
		'state sales tax-0.06' => array(
			'percent'     => 6.25,
			'id'          => 'state sales tax-0.06',
			'amount'      => 5.48,
			'base_amount' => 5.48,
			'rates' => array(
				0 => array(
					'code'     => 'state sales tax',
					'title'    => 'state sales tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'shipping tax-0.06' => array(
			'percent'     => 1.33,
			'id'          => 'shipping tax-0.06',
			'amount'      => 0.13,
			'base_amount' => 0.13,
			'rates' => array(
				0 => array(
					'code'     => 'shipping tax',
					'title'    => 'shipping tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'duty tax-0.0262' => array(
			'percent'     => 2.62,
			'id'          => 'duty tax-0.0262',
			'amount'      => 0.51,
			'base_amount' => 0.51,
			'rates' => array(
				0 => array(
					'code'     => 'duty tax',
					'title'    => 'duty tax',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
		'eb2c-duty-amount' => array(
			'percent'     => null,
			'id'          => 'eb2c-duty-amount',
			'amount'      => 8.21,
			'base_amount' => 8.21,
			'rates' => array(
				0 => array(
					'code'     => 'eb2c-duty-amount',
					'title'    => 'Duty',
					'position' => '1',
					'priority' => '1',
				),
			),
		),
	);

	/**
	 * @loadExpectation taxtest.yaml
	 * @dataProvider discountTaxCalculationSequence
	 */
	public function testCalcTaxForItem($scenario)
	{
		$isTaxAppliedAfter = $scenario === 'afterdiscount';
		$helper = $this->getHelperMockBuilder('eb2ctax/data')
			->disableOriginalConstructor()
			->setMethods(array('getApplyTaxAfterDiscount'))
			->getMock();
		$helper->expects($this->atLeastOnce())
			->method('getApplyTaxAfterDiscount')
			->will($this->returnValue($isTaxAppliedAfter));
		$this->replaceByMock('helper', 'eb2ctax', $helper);

		$address = $this->getModelMock('sales/quote_address', array('getId'));
		$address->expects($this->any())
			->method('getId')->will($this->returnValue(15));

		$items = $this->_mockItemsCalcTaxForItem($isTaxAppliedAfter);

		$itemSelectors   = array();
		$itemSelectors[] = $itemSelectorA = new Varien_Object(array('item' => $items[0], 'address' => $address));
		$itemSelectors[] = $itemSelectorB = new Varien_Object(array('item' => $items[1], 'address' => $address));

		// setup the calculator
		$calc = $this->getModelMock('tax/calculation', array('getTax', 'getDiscountTax', 'getAppliedRates'));
		$calc->expects($this->any())->method('getTax')->will($this->returnValueMap(array(
			array($itemSelectorA, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 0.0),
			array($itemSelectorA, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.0),
			array($itemSelectorA, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 0.0),
			array($itemSelectorB, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 8.0),
			array($itemSelectorB, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.0),
			array($itemSelectorB, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 0.0),
		)));
		$calc->expects($this->any())->method('getDiscountTax')->will($this->returnValueMap(array(
			array($itemSelectorA, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 0.0),
			array($itemSelectorA, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.0),
			array($itemSelectorA, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 0.0),
			array($itemSelectorB, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::MERCHANDISE_TYPE, 1.4),
			array($itemSelectorB, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::SHIPPING_TYPE, 0.0),
			array($itemSelectorB, EbayEnterprise_Eb2cTax_Overrides_Model_Calculation::DUTY_TYPE, 0.0),
		)));

		// set up the SUT
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$this->_reflectMethod($taxModel, '_initBeforeCollect')->invoke($taxModel, $address);
		$this->_reflectProperty($taxModel, '_calculator')->setValue($taxModel, $calc);
		$this->_reflectProperty($taxModel, '_store')->setValue($taxModel, Mage::app()->getStore());
		$this->_reflectProperty($taxModel, '_shippingTaxTotals')->setValue($taxModel, array(15 => 0.0));
		$this->_reflectProperty($taxModel, '_shippingTaxSubTotals')->setValue($taxModel, array(15 => 0.0));
		$calcTaxForItemMethod = $this->_reflectMethod($taxModel, '_calcTaxForItem');

		// precondition check
		$this->assertSame(2, count($items), 'number of items (' . count($items) . ') is not 2');
		foreach ($itemSelectors as $itemSelector) {
			$item = $itemSelector->getItem();
			$item->setHiddenTaxAmount(0.0);
			$item->setBaseHiddenTaxAmount(0.0);

			$expectationPath = "{$scenario}-" . $item->getId();
			$e = $this->expected($expectationPath);

			$calcTaxForItemMethod->invoke($taxModel, $itemSelector);

			$this->assertEquals(
				$e->getTaxAmount(),
				$item->getTaxAmount(),
				"$expectationPath: tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getRowTotalInclTax(),
				$item->getRowTotalInclTax(),
				"$expectationPath: row_total_incl_tax didn't match expectation"
			);
			$this->assertEquals(
				$e->getHiddenTaxAmount(),
				$item->getHiddenTaxAmount(),
				"$expectationPath: hidden_tax_amount didn't match expectation"
			);
			# base amounts
			$this->assertEquals(
				$e->getBaseTaxAmount(),
				$item->getBaseTaxAmount(),
				"$expectationPath: base_tax_amount didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseRowTotalInclTax(),
				$item->getBaseRowTotalInclTax(),
				"$expectationPath: base_row_total_incl_tax didn't match expectation"
			);
			$this->assertEquals(
				$e->getBaseHiddenTaxAmount(),
				$item->getBaseHiddenTaxAmount(),
				"$expectationPath: base_hidden_tax_amount didn't match expectation"
			);
		}
		$e = $this->expected("{$scenario}-address");
		$this->assertSame(
			(float) $e->getTotalAmountHiddenTax(),
			$address->getTotalAmount('hidden_tax')
		);
		$this->assertSame(
			(float) $e->getTotalAmountShippingHiddenTax(),
			$address->getTotalAmount('shipping_hidden_tax')
		);
		// base amounts
		$this->assertSame(
			(float) $e->getBaseTotalAmountHiddenTax(),
			$address->getBaseTotalAmount('hidden_tax')
		);
		$this->assertSame(
			(float) $e->getBaseTotalAmountShippingHiddenTax(),
			$address->getBaseTotalAmount('shipping_hidden_tax')
		);
	}

	protected function _mockItemsCalcTaxForItem($after=false)
	{
		$items = array();
		$methods = array(
			'getDiscountAmount',
			'getBaseDiscountAmount',
			'getSku',
			'getTaxableAmount',
			'getBaseTaxableAmount',
			'getIsPriceInclVat',
			'getId',
			'setTaxRates',
		);
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

	/**
	 * @test
	 * @large
	 */
	public function testCalcTaxForAddress()
	{
		// set up the config registry to supply the necessary taxApplyAfterDiscount configuration
		Mage::unregister('_helper/eb2ctax');
		$configRegistry = $this->getModelMock('eb2ccore/config_registry', array('__get', 'setStore'));
		$configRegistry->expects($this->any())
			->method('__get')
			->will($this->returnValueMap(array(array('taxApplyAfterDiscount', false))));
		$configRegistry->expects($this->any())
			->method('setStore')
			->will($this->returnSelf());
		$this->replaceByMock('model', 'eb2ccore/config_registry', $configRegistry);

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
		$calc = $this->_mockCalculator2();

		// precondition check
		$this->assertSame($quote, $address->getQuote());

		// create the tax model after mocking the calculator
		// so that it gets initialized with the mock
		$taxModel = Mage::getModel('tax/sales_total_quote_tax');
		$this->_reflectProperty($taxModel, '_calculator')->setValue($taxModel, $calc);
		$this->_reflectProperty($taxModel, '_store')->setValue($taxModel, Mage::app()->getStore());
		$this->_reflectProperty($taxModel, '_address')->setValue($taxModel, $address);
		$calcTaxForAddressMethod = $this->_reflectMethod($taxModel, '_calcTaxForAddress');
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
		Mage::unregister('_helper/eb2ctax');
		$items = array(
			$this->_mockItem(),
		);

		$quoteMock = $this->getModelMock('sales/quote', array('getStore'));
		$quoteMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));

		$addressMock = $this->_buildModelMock(
			'sales/quote_address',
			array(
				'getId'                 => $this->returnValue(1),
				'getAllNonNominalItems' => $this->returnValue($items),
				'getQuote'              => $this->returnValue($quoteMock),
				'getExtraTaxAmount'     => $this->returnValue(5),
				'getBaseExtraTaxAmount' => $this->returnValue(6),
			)
		);

		$this->_mockCalculator();
		// create the tax model after mocking the calculator so that it gets initialized with the
		// mock
		$tax = $this->getModelMock(
			'tax/sales_total_quote_tax',
			array('_calcTaxForAddress', '_roundTotals', '_addAmount', '_addBaseAmount', '_getAddressItems', '_calcShippingTaxes')
		);
		$tax->expects($this->once())
			->method('_getAddressItems')
			->with($this->identicalTo($addressMock))
			->will($this->returnValue($items));
		$tax->expects($this->once())
			->method('_calcTaxForAddress')
			->with($this->identicalTo($addressMock))
			->will($this->returnSelf());
		$tax->expects($this->once())
			->method('_roundTotals')
			->with($this->identicalTo($addressMock))
			->will($this->returnSelf());
		$tax->expects($this->once())
			->method('_addAmount')
			->with($this->identicalTo(5))
			->will($this->returnSelf());
		$tax->expects($this->once())
			->method('_addBaseAmount')
			->with($this->identicalTo(6))
			->will($this->returnSelf());
		$tax->expects($this->once())
			->method('_calcShippingTaxes')
			->with($this->identicalTo($addressMock))
			->will($this->returnSelf());
		$tax->collect($addressMock);
		// assert the item->getTaxAmount is as expected.
	}

	/**
	 * verify tax calculations are not attempted when there are no items
	 * @test
	 */
	public function testCollectNoItems()
	{
		$quoteMock = $this->getModelMock('sales/quote', array('getStore', 'getId', 'getItemsCount', 'getBillingAddress', 'getAllVisibleItems'));
		$quoteMock->expects($this->any())
			->method('getStore')
			->will($this->returnValue(Mage::app()->getStore()));

		$addressMock = $this->_buildModelMock(
			'sales/quote_address',
			array(
				'getQuote'              => $this->returnValue($quoteMock),
				'getAllNonNominalItems' => $this->returnValue(array()),
				'getId'                 => $this->returnValue(1)
			)
		);

		$this->_mockCalculator();

		$tax = $this->getModelMock('tax/sales_total_quote_tax', array('_getAddressItems', '_calcTaxForAddress'));
		$tax->expects($this->once())
			->method('_getAddressItems')
			->with($addressMock)
			->will($this->returnValue(array()));
		$tax->expects($this->never())
			->method('_calcTaxForAddress');

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
		return $calcMock;
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
			)));
		$this->replaceByMock('singleton', 'tax/calculation', $calcMock);
		$this->replaceByMock('model', 'tax/calculation', $calcMock);
		return $calcMock;
	}

	protected function _mockParentItem()
	{
		$productMock = $this->getModelMock('catalog/product', array('isVirtual'));

		$methods = array(
			'getParentItem',
			'getQty',
			'getCalculationPriceOriginal',
			'getStore',
			'getId',
			'getProduct',
			'getHasChildren',
			'isChildrenCalculated',
			'getChildren',
		);
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

	protected function _mockChildItem()
	{
		$productMock = $this->getModelMock('catalog/product', array('isVirtual'));
		$methods = array(
			'getParentItem',
			'getQty',
			'getCalculationPriceOriginal',
			'getStore',
			'getId',
			'getProduct',
			'getHasChildren',
			'isChildrenCalculated',
			'getChildren',
		);
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