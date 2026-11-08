<?php

class ChildProcess
{
    private static $diagnosticPattern = '/(Notice:|Warning:|Deprecated:|Strict Standards:|Parse error:|Fatal error:|Catchable fatal error:)/';

    public static function assertNoDiagnostics($stdout, $stderr)
    {
        if (preg_match(self::$diagnosticPattern, $stdout) || preg_match(self::$diagnosticPattern, $stderr)) {
            throw new RuntimeException("Child PHP process emitted a diagnostic:\nSTDOUT:\n" . $stdout . "\nSTDERR:\n" . $stderr);
        }
    }

    public static function runScript($scriptPath, array $env = array())
    {
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $descriptorSpec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $cmd = escapeshellarg($php)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -d log_errors=0 '
            . escapeshellarg($scriptPath);

        $procEnv = array_merge($_ENV, $env);
        $process = proc_open($cmd, $descriptorSpec, $pipes, null, $procEnv);
        if (!is_resource($process)) {
            throw new RuntimeException('proc_open failed');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return array(
            'exitCode' => (int) $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }

    public static function writeTempScript($contents)
    {
        $dir = sys_get_temp_dir() . '/sbv-child-' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = tempnam($dir, 'child-');
        if ($path === false) {
            throw new RuntimeException('tempnam failed');
        }
        file_put_contents($path, $contents);

        return $path;
    }
}
