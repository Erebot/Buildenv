#!/usr/bin/env php
<?php
/**
 * This program displays supported locales for i18n,
 * as a comma-separated list.
 */

function usage($script)
{
    echo "Usage: $script [directory]\n";
    exit(1);
}

function main()
{
    $args = $_SERVER['argv'];
    $script = array_shift($args);

    $dir = getcwd();
    if (count($args))
        $dir = array_shift($args);

    $locales = array();

    try {
        $iterator = new DirectoryIterator(
            $dir.
            DIRECTORY_SEPARATOR.'data'.
            DIRECTORY_SEPARATOR.'i18n'
        );
    }
    catch (UnexpectedValueException $e) {
        echo "\n";
        exit(0);
    }

    foreach ($iterator as $fileinfo) {
        if (!$fileinfo->isDir() || $fileinfo->isDot())
            continue;
        $dirname = $fileinfo->getFilename();
        if ($dirname[0] == ".")
            continue;
        $locales[] = $dirname;
    }

    echo implode(',', $locales)."\n";
    exit(0);
}

main();

