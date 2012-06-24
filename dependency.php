<?php
# composer/composer#cc7632489d3d2728862b3d3d5053aafbd543bdf3
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
Copyright (c) 2011 Nils Adermann, Jordi Boggiano

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

/**
 * Version parser
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Erebot_Package_Dependency
{
    const STABILITY_STABLE  = 0;
    const STABILITY_RC      = 5;
    const STABILITY_BETA    = 10;
    const STABILITY_ALPHA   = 15;
    const STABILITY_DEV     = 20;

    public static $stabilities = array(
        'stable' => self::STABILITY_STABLE,
        'RC'     => self::STABILITY_RC,
        'beta'   => self::STABILITY_BETA,
        'alpha'  => self::STABILITY_ALPHA,
        'dev'    => self::STABILITY_DEV,
    );

    private static $modifierRegex = '[.-]?(?:(beta|RC|alpha|patch|pl|p)(?:[.-]?(\d+))?)?([.-]?dev)?';

    /**
     * Returns the stability of a version
     *
     * @param  string $version
     * @return string
     */
    public static function parseStability($version)
    {
        $version = preg_replace('{#[a-f0-9]+$}i', '', $version);

        if ('dev-' === substr($version, 0, 4) || '-dev' === substr($version, -4)) {
            return 'dev';
        }

        preg_match('{'.self::$modifierRegex.'$}', $version, $match);
        if (!empty($match[3])) {
            return 'dev';
        }

        if (!empty($match[1]) && ($match[1] === 'beta' || $match[1] === 'alpha' || $match[1] === 'RC')) {
            return $match[1];
        }

        return 'stable';
    }

    public static function normalizeStability($stability)
    {
        $stability = strtolower($stability);

        return $stability === 'rc' ? 'RC' : $stability;
    }

    /**
     * Normalizes a version string to be able to perform comparisons on it
     *
     * @param  string $version
     * @return array
     */
    public function normalize($version)
    {
        $version = trim($version);

        // ignore aliases and just assume the alias is required instead of the source
        if (preg_match('{^([^,\s]+) +as +([^,\s]+)$}', $version, $match)) {
            $version = $match[1];
        }

        // match master-like branches
        if (preg_match('{^(?:dev-)?(?:master|trunk|default)$}i', $version)) {
            return '9999999-dev';
        }

        if ('dev-' === strtolower(substr($version, 0, 4))) {
            return strtolower($version);
        }

        // match classical versioning
        if (preg_match('{^v?(\d{1,3})(\.\d+)?(\.\d+)?(\.\d+)?'.self::$modifierRegex.'$}i', $version, $matches)) {
            $version = $matches[1]
                .(!empty($matches[2]) ? $matches[2] : '.0')
                .(!empty($matches[3]) ? $matches[3] : '.0')
                .(!empty($matches[4]) ? $matches[4] : '.0');
            $index = 5;
        } elseif (preg_match('{^v?(\d{4}(?:[.:-]?\d{2}){1,6}(?:[.:-]?\d{1,3})?)'.self::$modifierRegex.'$}i', $version, $matches)) { // match date-based versioning
            $version = preg_replace('{\D}', '-', $matches[1]);
            $index = 2;
        }

        // add version modifiers if a version was matched
        if (isset($index)) {
            if (!empty($matches[$index])) {
                $mod = array('{^pl?$}i', '{^rc$}i');
                $modNormalized = array('patch', 'RC');
                $version .= '-'.preg_replace($mod, $modNormalized, strtolower($matches[$index]))
                    . (!empty($matches[$index+1]) ? $matches[$index+1] : '');
            }

            if (!empty($matches[$index+2])) {
                $version .= '-dev';
            }

            return $version;
        }

        // match dev branches
        if (preg_match('{(.*?)[.-]?dev$}i', $version, $match)) {
            try {
                return $this->normalizeBranch($match[1]);
            } catch (Exception $e) {}
        }

        throw new UnexpectedValueException('Invalid version string '.$version);
    }

    /**
     * Normalizes a branch name to be able to perform comparisons on it
     *
     * @param  string $version
     * @return array
     */
    public function normalizeBranch($name)
    {
        $name = trim($name);

        if (in_array($name, array('master', 'trunk', 'default'))) {
            return $this->normalize($name);
        }

        if (preg_match('#^v?(\d+)(\.(?:\d+|[x*]))?(\.(?:\d+|[x*]))?(\.(?:\d+|[x*]))?$#i', $name, $matches)) {
            $version = '';
            for ($i = 1; $i < 5; $i++) {
                $version .= isset($matches[$i]) ? str_replace('*', 'x', $matches[$i]) : '.x';
            }

            return str_replace('x', '9999999', $version).'-dev';
        }

        return 'dev-'.$name;
    }

    /**
     * Parses as constraint string into LinkConstraint objects
     *
     * @param string $constraints
     */
    public function parseConstraints($constraints)
    {
        $prettyConstraint = $constraints;

        if (preg_match('{^([^,\s]*?)@('.implode('|', array_keys(self::$stabilities)).')$}i', $constraints, $match)) {
            $constraints = empty($match[1]) ? '*' : $match[1];
        }

        if (preg_match('{^(dev-[^,\s@]+?|[^,\s@]+?\.x-dev)#[a-f0-9]+$}i', $constraints, $match)) {
            $constraints = $match[1];
        }

        $constraints = preg_split('{\s*,\s*}', trim($constraints));
        $constraintObjects = array();
        foreach ($constraints as $constraint) {
            $constraintObjects = array_merge($constraintObjects, $this->parseConstraint($constraint));
        }

        $constraint = array($prettyConstraint, $constraintObjects);
        return $constraint;
    }

    private function parseConstraint($constraint)
    {
        if (preg_match('{^[x*](\.[x*])*$}i', $constraint)) {
            return array();
        }

        // match wildcard constraints
        if (preg_match('{^(\d+)(?:\.(\d+))?(?:\.(\d+))?\.[x*]$}', $constraint, $matches)) {
            if (isset($matches[3])) {
                $highVersion = $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '.9999999';
                if ($matches[3] === '0') {
                    $lowVersion = $matches[1] . '.' . ($matches[2] - 1) . '.9999999.9999999';
                } else {
                    $lowVersion = $matches[1] . '.' . $matches[2] . '.' . ($matches[3] - 1). '.9999999';
                }
            } elseif (isset($matches[2])) {
                $highVersion = $matches[1] . '.' . $matches[2] . '.9999999.9999999';
                if ($matches[2] === '0') {
                    $lowVersion = ($matches[1] - 1) . '.9999999.9999999.9999999';
                } else {
                    $lowVersion = $matches[1] . '.' . ($matches[2] - 1) . '.9999999.9999999';
                }
            } else {
                $highVersion = $matches[1] . '.9999999.9999999.9999999';
                if ($matches[1] === '0') {
                    return array(array('<', $highVersion));
                } else {
                    $lowVersion = ($matches[1] - 1) . '.9999999.9999999.9999999';
                }
            }

            return array(
                array('>', $lowVersion),
                array('<', $highVersion),
            );
        }

        // match operators constraints
        if (preg_match('{^(>=?|<=?|==?)?\s*(.*)}', $constraint, $matches)) {
            try {
                $version = $this->normalize($matches[2]);

                return array(array($matches[1] ?: '=', $version));
            } catch (Exception $e) {}
        }

        throw new UnexpectedValueException('Could not parse version constraint '.$constraint);
    }

    public function getBounds($constraints)
    {
        $constraints = str_replace(' ', '', $constraints);
        list($pretty, $constraints) = $this->parseConstraints($constraints);
        if (!count($constraints))
            return array(NULL, NULL, array());

        $min = NULL;
        $max = NULL;
        $exclude = array();
        foreach ($constraints as $constraint) {
            if (!count($constraint))
                continue;

            list($op, $version) = $constraint;
            switch ($op) {
                case '==':
                case '=':
                    $min = $max = $version;
                    break;

                case '>':
                    $exclude[] = $version;
                case '>=':
                    if ($min === NULL || version_compare($version, $min, '>'))
                        $min = $version;
                    break;

                case '<':
                    $exclude[] = $version;
                case '<=':
                    if ($max === NULL || version_compare($version, $max, '<'))
                        $max = $version;
                    break;
            }
        }

        if ($min !== NULL) {
            foreach ($exclude as &$version)
                if (version_compare($version, $min, '<'))
                    $version = NULL;
            unset($version);
        }
        if ($max !== NULL) {
            foreach ($exclude as &$version)
                if (version_compare($version, $max, '>'))
                    $version = NULL;
            unset($version);
        }
        $exclude = array_filter($exclude);
        return array($min, $max, $exclude);
    }
}
