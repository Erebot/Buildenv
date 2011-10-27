<?php

// Adds the "LICENSE" file to the package's documentation if it exists.
// This code tries its best not to pollute the outer scope.

call_user_func(
    function () use ($package, $extrafiles) {
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

