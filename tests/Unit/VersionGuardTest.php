<?php

require_once dirname(__DIR__) . '/Support/ChildProcess.php';

class VersionGuardTest extends PHPUnit_Framework_TestCase
{
    public function testExitsCleanlyWhenPsVersionIsUndefined()
    {
        $modulePath = dirname(dirname(__DIR__)) . '/statsbestvouchers.php';
        $script = ChildProcess::writeTempScript(
            '<?php' . "\n"
            . '$module = ' . var_export($modulePath, true) . ';' . "\n"
            . 'include $module;' . "\n"
            . "echo 'SENTINEL_AFTER_INCLUDE';" . "\n"
        );

        $result = ChildProcess::runScript($script);
        ChildProcess::assertNoDiagnostics($result['stdout'], $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertNotContains('SENTINEL_AFTER_INCLUDE', $result['stdout']);
    }
}
