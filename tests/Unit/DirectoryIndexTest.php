<?php

require_once dirname(__DIR__) . '/Support/ChildProcess.php';

class DirectoryIndexTest extends PHPUnit_Framework_TestCase
{
    public function indexPaths()
    {
        $root = dirname(dirname(__DIR__));

        return array(
            array($root . '/index.php'),
            array($root . '/tests/index.php'),
            array($root . '/tests/phpstan/index.php'),
            array($root . '/translations/index.php'),
            array($root . '/upgrade/index.php'),
        );
    }

    /**
     * @dataProvider indexPaths
     */
    public function testDirectoryIndexExitsBeforeSentinel($path)
    {
        $script = ChildProcess::writeTempScript(
            '<?php' . "\n"
            . 'include ' . var_export($path, true) . ';' . "\n"
            . "echo 'SENTINEL_AFTER_INCLUDE';" . "\n"
        );

        $result = ChildProcess::runScript($script);
        ChildProcess::assertNoDiagnostics($result['stdout'], $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertNotContains('SENTINEL_AFTER_INCLUDE', $result['stdout']);
    }
}
