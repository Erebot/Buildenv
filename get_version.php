#!/usr/bin/env php
<?php
/**
 * This program displays the current version.
 */

function main()
{
    $args = $_SERVER['argv'];
    $script = array_shift($args);

    $dir = getcwd();
    if (count($args))
        $dir = array_shift($args);

    putenv('GIT_DIR=' . $dir . DIRECTORY_SEPARATOR . '.git');
    $NUL = strncasecmp(PHP_OS, 'Win', 3) ? '/dev/null' : 'NUL';

    exec('git describe --tags 2>' . $NUL, $output, $exitcode);
    if ($exitcode != 0) {
        unset($output);
        exec('git describe --all 2>' . $NUL, $output, $exitcode);
        $version = 'dev-' . substr(strstr(trim($output[0]), '/'), 1);
    }
    else {
        $version = trim($output[0]);
    }

    echo $version . PHP_EOL;
    exit($exitcode);
}

main();

