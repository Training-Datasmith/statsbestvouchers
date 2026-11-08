<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "run-suite.php must run in CLI\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php run-suite.php /path/to/phpunit-4.8.36.phar\n");
    exit(1);
}

$phpunitPhar = $argv[1];
if (!is_file($phpunitPhar)) {
    fwrite(STDERR, "PHPUnit phar not found: $phpunitPhar\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);

$expectedFiles = array(
    'tests/Unit/ConstructorTest.php',
    'tests/Unit/DirectoryIndexTest.php',
    'tests/Unit/GetDataTest.php',
    'tests/Unit/HookDisplayTest.php',
    'tests/Unit/InstallTest.php',
    'tests/Unit/Upgrade2001Test.php',
    'tests/Unit/VersionGuardTest.php',
);

$expectedTestCount = 31;

$lintTargets = array_merge(
    array('statsbestvouchers.php', 'tests/bootstrap.php', 'tests/run-suite.php'),
    array_map(
        function ($rel) {
            return $rel;
        },
        $expectedFiles
    ),
    array(
        'tests/Support/PrestaShopShim.php',
        'tests/Support/ModuleCase.php',
        'tests/Support/ChildProcess.php',
    )
);

foreach ($lintTargets as $rel) {
    $path = $repoRoot . '/' . $rel;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file for lint: $rel\n");
        exit(1);
    }
    $output = array();
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, implode("\n", $output) . "\n");
        exit(1);
    }
}

$sortedAbs = array();
foreach ($expectedFiles as $rel) {
    $abs = $repoRoot . '/' . $rel;
    if (!is_file($abs)) {
        fwrite(STDERR, "Missing test file: $rel\n");
        exit(1);
    }
    $sortedAbs[] = $abs;
}

$seed = getenv('SBV_SEED');
if ($seed === false || $seed === '') {
    fwrite(STDERR, "SBV_SEED environment variable is required\n");
    exit(1);
}
$seed = (int) $seed;
srand($seed);
$shuffledAbs = $sortedAbs;
shuffle($shuffledAbs);

$tmpDir = sys_get_temp_dir() . '/sbv-phpunit-' . getmypid() . '-' . $seed;
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0700, true);
}

$xmlPath = $tmpDir . '/phpunit.xml';
$fileNodes = '';
foreach ($shuffledAbs as $abs) {
    $fileNodes .= '    <file>' . htmlspecialchars($abs, ENT_XML1) . "</file>\n";
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<phpunit bootstrap="' . htmlspecialchars($repoRoot . '/tests/bootstrap.php', ENT_XML1) . '"'
    . ' backupGlobals="false" backupStaticAttributes="false"'
    . ' colors="true">' . "\n"
    . "  <testsuites>\n"
    . '    <testsuite name="module-tests">' . "\n"
    . $fileNodes
    . "    </testsuite>\n"
    . "  </testsuites>\n"
    . '</phpunit>';

file_put_contents($xmlPath, $xml);

$junitPath = $tmpDir . '/junit.xml';
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$cmd = escapeshellarg($php) . ' -d error_reporting=-1 ' . escapeshellarg($phpunitPhar)
    . ' --log-junit ' . escapeshellarg($junitPath)
    . ' -c ' . escapeshellarg($xmlPath);

$output = array();
$code = 0;
exec($cmd . ' 2>&1', $output, $code);
$joined = implode("\n", $output);
echo $joined . "\n";

$badMarkers = array('FAILURES', 'ERRORS', 'Skipped:', 'Incomplete:', 'Risky:', 'There were warnings');
foreach ($badMarkers as $marker) {
    if (strpos($joined, $marker) !== false) {
        fwrite(STDERR, "Forbidden marker in output: $marker\n");
        exit(1);
    }
}

if (!preg_match('/OK \(' . $expectedTestCount . ' tests, \d+ assertions\)/', $joined)) {
    fwrite(STDERR, "Expected OK ($expectedTestCount tests, N assertions) summary\n");
    exit($code !== 0 ? $code : 1);
}

exit($code !== 0 ? $code : 0);
