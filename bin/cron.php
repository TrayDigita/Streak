#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Bin {
    global $argv;

    if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
        die(sprintf('%s only allowed on cli mode', basename(__FILE__)));
    }
    if (!$argv) {
        $argv = [__FILE__];
    }
    $_SERVER['argv'] =& $argv;
    $script = array_shift($argv);
    array_unshift($argv, $script, 'run:scheduler');

    return require __DIR__ .'/console.php';
}
