<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Support/ModuleCase.php';

class GetDataTest extends ModuleCase
{
    public function testSumsConvertedSalesPerVoucher()
    {
        $this->insertCartRule(1, 'SUMMER');
        $this->insertOrder(1, 1, 10, 2, '2020-01-15 12:00:00', 7);
        $this->insertOrderCartRule(1, 1, 'Summer sale');
        $this->insertOrder(2, 1, 10, 4, '2020-01-15 12:00:00', 7);
        $this->insertOrderCartRule(2, 1, 'Summer sale');
        $this->setGridState('total', 'DESC');

        $this->module->getData();

        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(1, $values);
        $this->assertSame('SUMMER', $values[0]['code']);
        $this->assertSame('Summer sale', $values[0]['name']);
        $this->assertTrue(is_numeric($values[0]['total']));
        $this->assertSame(2, (int) $values[0]['total']);
        $this->assertSame('7.50 EUR', $values[0]['ca']);

        $totalCount = $this->getProperty('_totalCount');
        $this->assertTrue(is_int($totalCount));
        $this->assertSame(1, $totalCount);
    }

    public function testExcludesInvalidOutOfRangeAndOtherShopOrders()
    {
        $this->insertCartRule(1, 'SUMMER');
        $this->insertCartRule(2, 'EDGE');
        $this->insertOrder(1, 1, 10, 2, '2020-01-15 12:00:00', 7);
        $this->insertOrderCartRule(1, 1, 'Summer sale');
        $this->insertOrder(2, 1, 10, 4, '2020-01-15 12:00:00', 7);
        $this->insertOrderCartRule(2, 1, 'Summer sale');
        $this->insertOrder(3, 0, 99, 1, '2020-01-15 12:00:00', 7);
        $this->insertOrderCartRule(3, 1, 'Summer sale');
        $this->insertOrder(4, 1, 99, 1, '2020-02-01 00:00:00', 7);
        $this->insertOrderCartRule(4, 1, 'Summer sale');
        $this->insertOrder(5, 1, 99, 1, '2019-12-31 23:59:59', 7);
        $this->insertOrderCartRule(5, 1, 'Summer sale');
        $this->insertOrder(6, 1, 99, 1, '2020-01-15 12:00:00', 8);
        $this->insertOrderCartRule(6, 1, 'Summer sale');
        $this->insertOrder(7, 1, 1, 1, '2020-01-01 00:00:00', 7);
        $this->insertOrderCartRule(7, 2, 'Edge');
        $this->insertOrder(8, 1, 1, 1, '2020-01-31 23:59:59', 7);
        $this->insertOrderCartRule(8, 2, 'Edge');
        $this->setGridState('code', 'ASC');

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(2, $values);
        $this->assertSame('EDGE', $values[0]['code']);
        $this->assertSame(2, (int) $values[0]['total']);
        $this->assertSame('2.00 EUR', $values[0]['ca']);
        $this->assertSame('SUMMER', $values[1]['code']);
        $this->assertSame(2, (int) $values[1]['total']);
        $this->assertSame('7.50 EUR', $values[1]['ca']);
        $totalCount = $this->getProperty('_totalCount');
        $this->assertTrue(is_int($totalCount));
        $this->assertSame(2, $totalCount);
    }

    public function testPageLimitDoesNotChangeFoundRows()
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState('total', 'DESC', 1, 1);

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(1, $values);
        $this->assertTrue(is_numeric($values[0]['total']));
        $this->assertSame(3, (int) $values[0]['total']);
        $totalCount = $this->getProperty('_totalCount');
        $this->assertTrue(is_int($totalCount));
        $this->assertSame(3, $totalCount);
        $this->assertLastSelectContains('LIMIT 1, 1');
        $sqls = Db::getExecutedSql();
        $foundFoundRows = false;
        foreach ($sqls as $sql) {
            if (stripos($sql, 'FOUND_ROWS') !== false) {
                $foundFoundRows = true;
            }
        }
        $this->assertTrue($foundFoundRows);
    }

    public function testStringZeroStartStillLimits()
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState('total', 'DESC', '0', '1');

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(1, $values);
        $this->assertSame(5, (int) $values[0]['total']);
        $totalCount = $this->getProperty('_totalCount');
        $this->assertTrue(is_int($totalCount));
        $this->assertSame(3, $totalCount);
        $this->assertLastSelectContains('LIMIT 0, 1');
    }

    public function lowercaseDescDirections()
    {
        return array(
            array('desc'),
            array('Desc'),
        );
    }

    /**
     * @dataProvider lowercaseDescDirections
     */
    public function testLowercaseDescDirectionSortsDescending($direction)
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState('total', $direction);

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertSame(5, (int) $values[0]['total']);
        $sql = $this->getLastSelectSql();
        $this->assertContains('ORDER BY `total`', $sql);
        $this->assertRegExp('/ORDER BY `total`\\s+desc/i', $sql);
    }

    public function testInvalidDirectionIsDropped()
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState('total', 'sideways');

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(3, $values);
        $this->assertSame(1, (int) $values[0]['total']);
        $sql = $this->getLastSelectSql();
        $this->assertContains('ORDER BY `total`', $sql);
        $this->assertNotContains('DESC', $sql);
        $this->assertNotContains(' ASC', $sql);
        $this->assertNotContains('sideways', $sql);
    }

    public function emptySortValues()
    {
        return array(
            array(null),
            array(''),
        );
    }

    /**
     * @dataProvider emptySortValues
     */
    public function testNullAndEmptySortOmitOrderBy($sort)
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState($sort, null);

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(3, $values);
        $totalCount = $this->getProperty('_totalCount');
        $this->assertTrue(is_int($totalCount));
        $this->assertSame(3, $totalCount);
        $this->assertNoSelectContains('ORDER BY');
    }

    public function disallowedSortTokens()
    {
        return array(
            array('id_order'),
            array('ca1'),
            array('total;delete'),
        );
    }

    /**
     * @dataProvider disallowedSortTokens
     */
    public function testDisallowedSortTokensAreNotInterpolated($sort)
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState($sort, null);

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(3, $values);
        $this->assertNoSelectContains('ORDER BY');
        $sql = $this->getLastSelectSql();
        $this->assertNotContains('ORDER BY `' . $sort . '`', $sql);
    }

    public function testNegativeStartOmitsLimit()
    {
        $this->seedThreeVouchersForSortTests();
        $this->setGridState(null, null, -1, 1);

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(3, $values);
        $this->assertNoSelectContains('LIMIT');
    }

    public function testNoMatchingRowsYieldsEmptyArrayAndZeroCount()
    {
        $this->insertCartRule(1, 'LATE');
        $this->insertOrder(1, 1, 5, 1, '2020-02-02 00:00:00', 7);
        $this->insertOrderCartRule(1, 1, 'Late');

        $this->module->getData();
        $sqls = Db::getExecutedSql();
        $this->assertNotEmpty($sqls);
        $foundDate = false;
        foreach ($sqls as $sql) {
            if (stripos($sql, 'invoice_date') !== false && strpos($sql, '2020-01-01') !== false) {
                $foundDate = true;
            }
        }
        $this->assertTrue($foundDate);
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(0, $values);
        $totalCount = $this->getProperty('_totalCount');
        $this->assertTrue(is_int($totalCount));
        $this->assertSame(0, $totalCount);
    }

    public function testFormatsWithConfiguredCurrency()
    {
        Configuration::set('PS_CURRENCY_DEFAULT', 9);
        $this->insertCartRule(1, 'USDV');
        $this->insertOrder(1, 1, 9, 1, '2020-01-15 12:00:00', 7);
        $this->insertOrderCartRule(1, 1, 'Usd voucher');

        $this->module->getData();
        $values = $this->getProperty('_values');
        $this->assertTrue(is_array($values));
        $this->assertCount(1, $values);
        $this->assertSame('9.00 USD', $values[0]['ca']);
    }
}
