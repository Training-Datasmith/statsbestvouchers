<?php

require_once dirname(__FILE__) . '/ChildProcess.php';

abstract class ModuleCase extends PHPUnit_Framework_TestCase
{
    const ENGINE_LITERAL = 'ENGINE|Best vouchers|code:code:left:Code,name:name:left:Name,ca:ca:right:Sales,total:total:center:Total used|ca|DESC|Empty recordset returned.|Displaying {0} - {1} of {2}';

    /** @var statsbestvouchers */
    protected $module;

    public static function setUpBeforeClass()
    {
        if (!getenv('STATSBESTVOUCHERS_DB_HOST')) {
            throw new RuntimeException('STATSBESTVOUCHERS_DB_* environment variables are required');
        }
    }

    protected function setUp()
    {
        Db::truncateTables();
        Db::resetState();
        Configuration::reset();
        Currency::reset();
        Context::reset();
        Module::resetSpies();
        $_GET = array();
        $_POST = array();
        unset($_SERVER['REQUEST_URI']);

        Configuration::set('PS_CURRENCY_DEFAULT', 1);
        Currency::register(1, 'EUR');
        Currency::register(9, 'USD');

        $this->module = new statsbestvouchers();
    }

    protected function assertStringNotContainsString($needle, $haystack, $message = '')
    {
        $this->assertNotContains($needle, $haystack, $message);
    }

    protected function setGridState($sort = null, $direction = null, $start = null, $limit = null)
    {
        $this->setProperty('_sort', $sort);
        $this->setProperty('_direction', $direction);
        $this->setProperty('_start', $start);
        $this->setProperty('_limit', $limit);
    }

    protected function setProperty($name, $value)
    {
        $ref = new ReflectionProperty('statsbestvouchers', $name);
        $ref->setAccessible(true);
        $ref->setValue($this->module, $value);
    }

    protected function getProperty($name)
    {
        $ref = new ReflectionProperty('statsbestvouchers', $name);
        $ref->setAccessible(true);

        return $ref->getValue($this->module);
    }

    protected function isFoundRowsFollowUpQuery($sql)
    {
        return (bool) preg_match('/^\s*SELECT\s+FOUND_ROWS\s*\(\s*\)/i', $sql);
    }

    protected function getLastSelectSql()
    {
        $foundSelect = null;
        foreach (Db::getExecutedSql() as $sql) {
            if (preg_match('/^\s*SELECT\b/i', $sql) && !$this->isFoundRowsFollowUpQuery($sql)) {
                $foundSelect = $sql;
            }
        }
        $this->assertTrue(is_string($foundSelect));

        return $foundSelect;
    }

    protected function assertLastSelectContains($fragment)
    {
        $this->assertContains($fragment, $this->getLastSelectSql());
    }

    protected function assertNoSelectContains($fragment)
    {
        foreach (Db::getExecutedSql() as $sql) {
            if (preg_match('/^\s*SELECT\b/i', $sql) && !$this->isFoundRowsFollowUpQuery($sql)) {
                $this->assertNotContains($fragment, $sql);
            }
        }
    }

    protected function insertCartRule($idCartRule, $code)
    {
        $db = Db::getInstance(false);
        $prefix = _DB_PREFIX_;
        $sql = 'INSERT INTO `' . $prefix . 'cart_rule` (`id_cart_rule`, `code`) VALUES ('
            . (int) $idCartRule . ', ' . "'" . pSQL($code) . "'" . ')';
        $db->queryRaw($sql);
    }

    protected function insertOrder($idOrder, $valid, $totalPaid, $conversionRate, $invoiceDate, $idShop)
    {
        $db = Db::getInstance(false);
        $prefix = _DB_PREFIX_;
        $sql = 'INSERT INTO `' . $prefix . 'orders` (`id_order`, `valid`, `total_paid_real`, `conversion_rate`, `invoice_date`, `id_shop`) VALUES ('
            . (int) $idOrder . ', '
            . (int) $valid . ', '
            . (float) $totalPaid . ', '
            . (float) $conversionRate . ', '
            . "'" . pSQL($invoiceDate) . "', "
            . (int) $idShop
            . ')';
        $db->queryRaw($sql);
    }

    protected function insertOrderCartRule($idOrder, $idCartRule, $name)
    {
        $db = Db::getInstance(false);
        $prefix = _DB_PREFIX_;
        $sql = 'INSERT INTO `' . $prefix . 'order_cart_rule` (`id_order`, `id_cart_rule`, `name`) VALUES ('
            . (int) $idOrder . ', '
            . (int) $idCartRule . ', '
            . "'" . pSQL($name) . "'"
            . ')';
        $db->queryRaw($sql);
    }

    protected function seedThreeVouchersForSortTests()
    {
        $this->insertCartRule(1, 'V5');
        $this->insertCartRule(2, 'V3');
        $this->insertCartRule(3, 'V1');
        $orderId = 100;
        $counts = array(1 => 5, 2 => 3, 3 => 1);
        $names = array(1 => 'Five', 2 => 'Three', 3 => 'One');
        foreach ($counts as $cartRuleId => $usageCount) {
            for ($i = 0; $i < $usageCount; $i++) {
                ++$orderId;
                $this->insertOrder($orderId, 1, 1, 1, '2020-01-15 12:00:00', 7);
                $this->insertOrderCartRule($orderId, $cartRuleId, $names[$cartRuleId]);
            }
        }
    }
}
