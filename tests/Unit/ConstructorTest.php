<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Support/ModuleCase.php';

class ConstructorTest extends ModuleCase
{
    public function testIdentityAndCompliancy()
    {
        $this->assertSame('statsbestvouchers', $this->module->name);
        $this->assertSame('administration', $this->module->tab);
        $this->assertSame('2.1.0', $this->module->version);
        $this->assertSame('PrestaShop', $this->module->author);
        $this->assertSame(0, $this->module->need_instance);
        $this->assertTrue(is_array($this->module->ps_versions_compliancy));
        $this->assertSame('1.7.6.0', $this->module->ps_versions_compliancy['min']);
        $this->assertSame('1.7.8.11', $this->module->ps_versions_compliancy['max']);
    }

    public function testPublicStrings()
    {
        $this->assertSame('Best vouchers', $this->module->displayName);
        $this->assertSame(
            'Enrich your stats, add a list of the most used vouchers to the dashboard.',
            $this->module->description
        );
    }

    public function testColumnsAndSortDefaultsReachTheGridEngine()
    {
        $_SERVER['REQUEST_URI'] = '/admin-dev/index.php?controller=AdminStats&module=statsbestvouchers';
        $html = $this->module->hookDisplayAdminStatsModules(array());
        $this->assertContains(self::ENGINE_LITERAL, $html);
    }

    public function testConfigXmlMatchesClassVersion()
    {
        $root = dirname(dirname(__DIR__));
        $xml = simplexml_load_file($root . '/config.xml');
        $this->assertNotFalse($xml);
        $this->assertSame('statsbestvouchers', (string) $xml->name);
        $this->assertSame('2.1.0', (string) $xml->version);
        $this->assertSame('2.1.0', $this->module->version);
        $this->assertSame('PrestaShop', (string) $xml->author);
        $this->assertSame('0', (string) $xml->need_instance);
        $this->assertSame('0', (string) $xml->is_configurable);
    }
}
