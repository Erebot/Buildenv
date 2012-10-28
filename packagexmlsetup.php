<?php

/**
 * Extra package.xml settings such as dependencies.
 * More information: http://pear.php.net/manual/en/pyrus.commands.make.php#pyrus.commands.make.packagexmlsetup
 *
 * The defaults presented here take dependency information
 * and licensing information from data/composer.json and
 * convert them into PEAR metadata.
 */

require(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'dependency.php'
);

$licenses = include(
    dirname(dirname(__FILE__)) .
    DIRECTORY_SEPARATOR . 'buildenv' .
    DIRECTORY_SEPARATOR . 'licenses.php'
);
$metadata = json_decode(
    file_get_contents(
        dirname(dirname(__FILE__)) .
        DIRECTORY_SEPARATOR . 'data' .
        DIRECTORY_SEPARATOR . 'composer.json'
    ),
    TRUE
);

if ($metadata === NULL) {
    throw new Exception('Could not parse composer.json');
}

$pearTypes = array(
    'required'  => 'require',
    'optional'  => 'suggest',
    'conflicts' => 'conflict',
);

function normalizePearVersion($package, $version)
{
    if ($package == "pear2.php.net/pyrus")
        $substs = array('-alpha' => 'a', '-beta' => 'b');
    else
        $substs = array('-alpha' => 'alpha', '-beta' => 'beta');
    $end        = strspn($version, '1234567890.');
    $parts      = explode('.', (string) substr($version, 0, $end));
    $modifiers  = (string) substr($version, $end);
    array_pop($parts);
    $modifiers  = strtr($modifiers, $substs);
    $version = implode('.', $parts) . $modifiers;
    return $version;
}

$parser = new Erebot_Package_Dependency();

// Requirements, suggestions and conflicts.
foreach ($pearTypes as $pearType => $composerType) {
    if (!isset($metadata[$composerType]))
        continue;

    foreach ($metadata[$composerType] as $dependency => $constraints) {
        // Skip virtual packages.
        if (!strncasecmp('virt-', $dependency, 5))
            continue;

        // Determine minimal/maximal/excluded versions.
        list($min, $max, $excluded) = $parser->getBounds($constraints);

        // Grab a reference to the object the constraints apply to.
        if ($dependency == 'php') {
            $objDep = $package->dependencies[$pearType]->php;
        }
        else if (substr($dependency, 0, 13) == 'pecl.php.net/') {
            $objDep = $package->dependencies[$pearType]
                              ->extension[substr($dependency, 13)];
        }
        else {
            $objDep = $package->dependencies[$pearType]->package[$dependency];
        }

        // Apply the constraints.
        if ($min !== NULL)
            $objDep->min(normalizePearVersion($dependency, $min));
        if ($max !== NULL)
            $objDep->max(normalizePearVersion($dependency, $max));
        foreach ($excluded as $version)
            $objDep->exclude(normalizePearVersion($dependency, $version));
        if ($pearType == 'conflicts')
            $objDep->conflicts();
        $objDep->save();
    }
}

// PEAR installer.
$package->dependencies['required']->pearinstaller->min('1.9.2')->save();

// license.
if (isset($metadata['license'])) {
    $pkgLicenses = (array) $metadata['license'];
    if (count($pkgLicenses) != 1)
        throw new Exception('Multiple licenses are not implemented yet');
    foreach ($pkgLicenses as $name) {
        $package->license['name'] = $name;
        if (isset($licenses[$name])) {
            $package->license['uri'] = sprintf(
                'http://www.spdx.org/licenses/%s#licenseText',
                rawurlencode($name)
            );
        }
    }
}
