<?php

// For phar files to use the proper layout, the PEAR installer
// version must be left at its default value (>= 2.0.0a1).
if (!$options['phar']) {
    // Only change the version requirement
    // if we're not dealing with a phar.
    $package->dependencies['required']->pearinstaller->min('1.9.2')->save();
}

// Adds some files to the package if they exist:
// "LICENSE" -> package's doc/
// "composer.json" -> package's data/
// This code tries its best not to pollute the outer scope
// while still being robust.
if (isset($package, $extrafiles)) {
    call_user_func(
        function () use ($package, &$extrafiles) {
            $included = get_included_files();
            $rootDir = dirname($included[count($included) - 2]);

            $mapping = array(
                'LICENSE' => 'doc/@PKG_SPECIFIC@/LICENSE',
                'composer.json' => 'data/@PKG_SPECIFIC@/composer.json',
            );

            foreach ($mapping as $source => $target) {
                $source = $rootDir . DIRECTORY_SEPARATOR . $source;
                if (!file_exists($source)) {
                    continue;
                }

                $target = str_replace(
                    '@PKG_SPECIFIC@',
                    $package->channel . '/' . $package->name,
                    $target
                );
                $extrafiles[$target] = $source;
            }
        }
    );
}

