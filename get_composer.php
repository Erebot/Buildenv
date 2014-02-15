#!/usr/bin/env php
<?php
/**
 * This program displays various information
 * about the current component.
 */

function usage($prog)
{
    echo "Usage: $prog composer path" . PHP_EOL;
}

function get_composer($dir)
{
    $composer = @file_get_contents(
        $dir .
        DIRECTORY_SEPARATOR . 'composer.json'
    );
    if ($composer === FALSE) {
        fprintf(STDERR, "Could not find 'composer.json'.%s", PHP_EOL);
        exit(1);
    }

    $composer = @json_decode(
        $composer,
        TRUE
    );
    if ($composer === NULL) {
        fprintf(STDERR, "Could not parse 'composer.json'.%s", PHP_EOL);
        exit(1);
    }
    return $composer;
}

function main()
{
    $args       = $_SERVER['argv'];
    $script     = array_shift($args);
    $rem_args   = array();
    $got_args   = FALSE;

    for ($i = 0, $m = count($args); $i < $m; $i++) {
        $arg = $args[$i];

        if (!$got_args && !strncmp($arg, '-', 1)) {
            usage($script);
            exit(1);
        }
        else {
            $got_args   = TRUE;
            $rem_args[] = $arg;
        }
    }

    // Retrieve the path to the repository's toplevel directory.
    $NUL = strncasecmp(PHP_OS, 'Win', 3) ? '/dev/null' : 'NUL';
    exec('git rev-parse --show-toplevel 2>' . $NUL, $output, $exitcode);
    if ($exitcode != 0) {
        fprintf(STDERR, "Could not determine path to .git folder.%s", PHP_EOL);
        exit(1);
    }
    $dir = trim($output[0]);

    $composer = get_composer($dir);
    $type = NULL;
    foreach ($rem_args as $arg) {
        if ($type === NULL && !strncmp($arg, '-', 1)) {
            $type = (string) substr($arg, 1);
            continue;
        } else {
            $type = 'string';
        }

        @settype($arg, $type);
        $composer   = $composer[$arg];
        $type       = NULL;
    }
    echo $composer . PHP_EOL;
    exit(0);
}

main();

