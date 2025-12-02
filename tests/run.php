#!/usr/bin/env php
<?php
/**
 * Standalone test runner for PHPUnit tests
 *
 * This script allows running PHPUnit tests without requiring a global
 * PHPUnit installation. It uses the locally downloaded phar file.
 *
 * Usage:
 *   php tests/run.php [test-file] [options]
 *
 * Examples:
 *   php tests/run.php tests/microservices/ServiceRegistryTest.php
 *   php tests/run.php tests/microservices/
 *   php tests/run.php --coverage-html=coverage-html
 */

// Check if PHPUnit phar exists
$phpunit_phar = __DIR__ . '/phpunit.phar';
if (!file_exists($phpunit_phar)) {
    echo "Error: PHPUnit phar file not found at {$phpunit_phar}\n";
    echo "Please download PHPUnit first.\n";
    exit(1);
}

// Check if bootstrap file exists
$bootstrap_file = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_file)) {
    echo "Error: Bootstrap file not found at {$bootstrap_file}\n";
    exit(1);
}

// Check if configuration file exists
$config_file = __DIR__ . '/phpunit.xml';
if (!file_exists($config_file)) {
    echo "Error: Configuration file not found at {$config_file}\n";
    exit(1);
}

// Display header
echo "============================================\n";
echo "AI Auto News Poster - PHPUnit Test Runner\n";
echo "============================================\n";
echo "PHPUnit: {$phpunit_phar}\n";
echo "Bootstrap: {$bootstrap_file}\n";
echo "Configuration: {$config_file}\n";

// Build command
$command = "php {$phpunit_phar} --bootstrap {$bootstrap_file} --configuration {$config_file}";

// Add arguments passed to this script
$args = $_SERVER['argv'];
array_shift($args); // Remove script name

// Add coverage options if requested
$coverage_options = array('--coverage-html', '--coverage-text', '--coverage-clover');
foreach ($args as $arg) {
    foreach ($coverage_options as $option) {
        if (strpos($arg, $option) === 0) {
            $command .= " --coverage-process-uncovered";
            break;
        }
    }
}

if (!empty($args)) {
    $command .= " " . implode(" ", array_map('escapeshellarg', $args));
}

echo "Command: {$command}\n";
echo "============================================\n\n";

// Execute command
passthru($command, $return_code);

exit($return_code);