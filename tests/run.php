<?php

require __DIR__ . '/bootstrap.php';

$test_files = glob(__DIR__ . '/unit/*Test.php');
$tests = array();

foreach ($test_files as $test_file) {
    $registered = require $test_file;
    if (is_array($registered)) {
        $tests = array_merge($tests, $registered);
    }
}

$failures = array();

foreach ($tests as $name => $callback) {
    try {
        $callback();
        echo "[PASS] {$name}\n";
    } catch (Throwable $throwable) {
        $failures[] = array($name, $throwable->getMessage());
        echo "[FAIL] {$name}: {$throwable->getMessage()}\n";
    }
}

if (! empty($failures)) {
    exit(1);
}

echo sprintf("Executed %d tests.\n", count($tests));
