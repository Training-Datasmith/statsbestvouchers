<?php

class UpgradeHookSpy
{
    public $calls = array();

    public function unregisterHook($name)
    {
        $this->calls[] = array('unregister', $name);

        return true;
    }

    public function registerHook($name)
    {
        $this->calls[] = array('register', $name);

        return true;
    }
}

class Upgrade2001Test extends PHPUnit_Framework_TestCase
{
    public function testRewiresAdminStatsHook()
    {
        require_once dirname(dirname(__DIR__)) . '/upgrade/upgrade-2.0.1.php';
        $spy = new UpgradeHookSpy();
        $result = upgrade_module_2_0_1($spy);
        $this->assertTrue(is_bool($result));
        $this->assertTrue($result);
        $this->assertSame(
            array(
                array('unregister', 'AdminStatsModules'),
                array('register', 'displayAdminStatsModules'),
            ),
            $spy->calls
        );
    }
}
