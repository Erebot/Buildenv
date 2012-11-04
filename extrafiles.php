<?php

// For phar files to use the proper layout, the PEAR installer
// version must be left at its default value (>= 2.0.0a1).
if (!$options['phar']) {
    // Only change the version requirement
    // if we're not dealing with a phar.
    $package->dependencies['required']->pearinstaller->min('1.9.2')->save();
}

// Adds the "LICENSE" file to the package's documentation if it exists.
// This code tries its best not to pollute the outer scope while being robust.
if (isset($package, $extrafiles)) {
    call_user_func(
        function () use ($package, &$extrafiles) {
            $included = get_included_files();
            $license = dirname($included[count($included) - 2]) .
                        DIRECTORY_SEPARATOR . 'LICENSE';
            if (file_exists($license)) {
                $target = 'doc/' . $package->channel . '/' .
                            $package->name . '/LICENSE';
                $extrafiles[$target] = $license;
            }
        }
    );
}

