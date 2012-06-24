<?php

/**
 * Extra package.xml settings such as dependencies.
 * More information: http://pear.php.net/manual/en/pyrus.commands.make.php#pyrus.commands.make.packagexmlsetup
 *
 * The defaults presented here take dependency information
 * and licensing information from data/package.php expressed
 * using Composer's terminology and convert them into PEAR
 * metadata.
 */

require(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'buildenv' .
    DIRECTORY_SEPARATOR . 'dependency.php'
);

$packageName = $package->name;
$packageVersion = $package->getReleaseVersion();
$metadata = array($package->getChannel(). '/' . $packageName => array());
require(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'data' .
    DIRECTORY_SEPARATOR . 'package.php'
);

$pearTypes = array(
    'required'  => 'requires',
    'optional'  => 'suggests',
    'conflicts' => 'conflicts',
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

$parser     = new Erebot_Package_Dependency();
$fullName   = $package->getChannel(). '/' . $packageName;
foreach (array($package, $compatible) as $obj) {
    // requirements, suggestions and conflicts.
    foreach ($pearTypes as $pearType => $composerType) {
        if (!isset($metadata[$fullName][$composerType]))
            continue;

        foreach ($metadata[$fullName][$composerType] as $dependency => $constraints) {
            if (is_int($dependency)) {
                $dependency     = $constraints;
                $constraints    = '*';
            }

            // Skip virtual packages.
            if (!strncasecmp('virt-', $dependency, 5))
                continue;
            // Determine minimal/maximal/excluded versions.
            list($min, $max, $excluded) = $parser->getBounds($constraints);

            // Grab a reference to the object the constraints apply to.
            if ($dependency == 'php')
                $objDep = $obj->dependencies[$pearType]->php;
            else if ($dependency == 'pear2.php.net/pyrus') {
                // This constraint only applies to Pyrus (PEAR2).
                if ($obj === $compatible)
                    continue;
                $objDep = $obj->dependencies[$pearType]->pearinstaller;
            }
            else
                $objDep = $obj->dependencies[$pearType]->package[$dependency];

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

    // license.
    if (isset($metadata[$fullName]['license'])) {
        $licenses = (array) $metadata[$fullName]['license'];
        if (count($licenses) != 1)
            throw new Exception('Multiple licenses are not implemented yet');
        foreach ($licenses as $name => $uri) {
            if (is_int($name)) {
                $name   = $uri;
                $uri    = NULL;
            }
            $obj->license['name'] = $name;
            if ($uri !== NULL)
                $obj->license['uri'] = $uri;
        }
    }

    // Fix API stability.
    $obj->stability['api'] = 'stable';
}

