#!/usr/bin/env php
<?php
/**
 */

function usage($script)
{
    fprintf(
        STDERR,
        "%s",
        "Usage: $script current_component [component1,component2,...]" .
        PHP_EOL
    );
    exit(1);
}

function main()
{
    $args = $_SERVER['argv'];
    $script = array_shift($args);

    if (!count($args))
        usage($script);

    $current = array_shift($args);

    if (!count($args))
        echo PHP_EOL;

    $components = explode(',', array_shift($args));
    $paths      = array();
    $base       = dirname(dirname(dirname(__FILE__)));

    foreach ($components as $component) {
        if ($current == '-')
            $parts = array('', 'tmp', 'tagfiles');
        else
            $parts = array(
                $base,
                $component,
            );

        $parts[]        = $component . '.tagfile.xml';
        $path           = implode(DIRECTORY_SEPARATOR, $parts);
        $paths[$path]   =
            ($current == '-')
            ? '../../' . $component . '/html'
            : dirname($path) .
                DIRECTORY_SEPARATOR . 'docs' .
                DIRECTORY_SEPARATOR . 'api' .
                DIRECTORY_SEPARATOR . 'html';
    }

    foreach ($paths as $tagfile => $relative)
        echo "\"$tagfile=$relative\" ";

    echo PHP_EOL;
    exit(0);
}

main();

