<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Support/ModuleCase.php';
require_once dirname(__DIR__) . '/Support/ChildProcess.php';

class HookDisplayTest extends ModuleCase
{
    public function testRendersHeadingEngineAndEscapedExportHref()
    {
        $_SERVER['REQUEST_URI'] = '/admin-dev/index.php?controller=AdminStats&module=statsbestvouchers&token=abc';
        $html = $this->module->hookDisplayAdminStatsModules(array());
        $this->assertContains('class="panel-heading"', $html);
        $this->assertContains('Best vouchers', $html);
        $this->assertContains(self::ENGINE_LITERAL, $html);
        $this->assertContains('class="btn btn-default export-csv"', $html);
        $this->assertContains('icon-cloud-upload', $html);
        $this->assertContains('CSV Export', $html);
        $this->assertContains(
            '/admin-dev/index.php?controller=AdminStats&amp;module=statsbestvouchers&amp;token=abc&amp;export=1',
            $html
        );
    }

    public function testExportWritesCsvAndDoesNotReturnHtml()
    {
        $bootstrap = dirname(__DIR__) . '/bootstrap.php';
        $script = ChildProcess::writeTempScript($this->buildExportChildScript($bootstrap));
        $result = ChildProcess::runScript($script, array(
            'STATSBESTVOUCHERS_DB_HOST' => getenv('STATSBESTVOUCHERS_DB_HOST'),
            'STATSBESTVOUCHERS_DB_PORT' => getenv('STATSBESTVOUCHERS_DB_PORT'),
            'STATSBESTVOUCHERS_DB_USER' => getenv('STATSBESTVOUCHERS_DB_USER'),
            'STATSBESTVOUCHERS_DB_PASSWORD' => getenv('STATSBESTVOUCHERS_DB_PASSWORD'),
            'STATSBESTVOUCHERS_DB_NAME' => getenv('STATSBESTVOUCHERS_DB_NAME'),
        ));

        ChildProcess::assertNoDiagnostics($result['stdout'], $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertNotContains('panel-heading', $result['stdout']);
        $expected = "Code;Name;Sales;Total used\n"
            . "SUMMER;Summer sale;5.00 EUR;1\n"
            . "WINTER;Winter sale;9.00 EUR;1\n";
        $this->assertSame($expected, $result['stdout']);
    }

    private function buildExportChildScript($bootstrapPath)
    {
        return '<?php' . "\n"
            . '$_GET["export"] = "1";' . "\n"
            . '$_SERVER["REQUEST_URI"] = "/admin-dev/index.php?controller=AdminStats&module=statsbestvouchers&token=abc";' . "\n"
            . 'require ' . var_export($bootstrapPath, true) . ';' . "\n"
            . 'Db::truncateTables();' . "\n"
            . 'Configuration::set("PS_CURRENCY_DEFAULT", 1);' . "\n"
            . 'Currency::register(1, "EUR");' . "\n"
            . '$db = Db::getInstance(false);' . "\n"
            . '$p = _DB_PREFIX_;' . "\n"
            . '$db->queryRaw("INSERT INTO `{$p}cart_rule` (`id_cart_rule`, `code`) VALUES (10, \'SUMMER\'), (11, \'WINTER\')");' . "\n"
            . '$db->queryRaw("INSERT INTO `{$p}orders` (`id_order`, `valid`, `total_paid_real`, `conversion_rate`, `invoice_date`, `id_shop`) VALUES (1, 1, 5, 1, \'2020-01-15 12:00:00\', 7), (2, 1, 9, 1, \'2020-01-15 12:00:00\', 7)");' . "\n"
            . '$db->queryRaw("INSERT INTO `{$p}order_cart_rule` (`id_order`, `id_cart_rule`, `name`) VALUES (1, 10, \'Summer sale\'), (2, 11, \'Winter sale\')");' . "\n"
            . '$m = new statsbestvouchers();' . "\n"
            . '$m->hookDisplayAdminStatsModules(array());' . "\n";
    }
}
