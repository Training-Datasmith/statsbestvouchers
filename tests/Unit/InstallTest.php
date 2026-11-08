<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Support/ModuleCase.php';

class InstallTest extends ModuleCase
{
    public function testRegistersDisplayHook()
    {
        Module::setParentInstallResult(true);
        Module::setRegisterHookResult(true);
        $result = $this->module->install();
        $this->assertTrue(is_bool($result));
        $this->assertTrue($result);
        $this->assertSame(array('displayAdminStatsModules'), Module::getRegisteredHooks());
    }

    public function testParentFailureShortCircuits()
    {
        Module::setParentInstallResult(false);
        Module::setRegisterHookResult(true);
        $result = $this->module->install();
        $this->assertTrue(is_bool($result));
        $this->assertFalse($result);
        $this->assertSame(array(), Module::getRegisteredHooks());
    }

    public function testHookFailureFailsInstall()
    {
        Module::setParentInstallResult(true);
        Module::setRegisterHookResult(false);
        $result = $this->module->install();
        $this->assertTrue(is_bool($result));
        $this->assertFalse($result);
        $this->assertSame(array('displayAdminStatsModules'), Module::getRegisteredHooks());
    }
}
