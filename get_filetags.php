#!/usr/bin/env php
<?php
/**
 */

function usage($script)
{
    echo "Usage: $script current_component [component1,component2,...]\n";
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
        echo "\n";

    $components = explode(',', array_shift($args));
    $paths      = array();
    $base       = dirname(dirname(dirname(dirname(__FILE__))));
    if (!strncasecmp($current, 'Erebot_Module_', 14))
        $base = dirname($base);

    foreach ($components as $component) {
        if ($current == '-')
            $parts = array('', 'tmp', 'tagfiles');
        else if ($component == 'Erebot' || $component == 'Plop')
            $parts = array(
                $base,
                ($component == 'Erebot' ? 'core' : 'logging'),
                'trunk',
            );
        else
            $parts = array(
                $base,
                'modules',
                substr($component, 14),
                'trunk',
            );

        $parts[]        = $component . '.tagfile.xml';
        $path           = implode(DIRECTORY_SEPARATOR, $parts);
        $paths[$path]   =
            ($current == '-')
            ? '../' . $component
            : dirname($path) .
                DIRECTORY_SEPARATOR . 'docs' .
                DIRECTORY_SEPARATOR . 'html';
    }

    foreach ($paths as $tagfile => $relative)
        echo "\"$tagfile=$relative\" ";

    echo "\n";
    exit(0);
}

main();

