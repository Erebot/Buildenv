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

    ob_start();
    $output = system('git describe --tags 2>' . $NUL, $exitcode);
    ob_end_clean();
    if ($exitcode != 0) {
        ob_start();
        $output = system('git describe --all 2>' . $NUL, $exitcode);
        ob_end_clean();
        $version = 'dev-' . substr(strstr(trim($output), '/'), 1);
    }
    else {
        $version = trim($output);
    }

    echo $version . PHP_EOL;
    exit($exitcode);
}

main();

