#!/usr/bin/env php
<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

if (version_compare(phpversion(), '5.3.1', '<')) {
    if (substr(phpversion(), 0, 5) !== '5.3.1') {
        // this small hack is because of running RCs of 5.3.1
        echo basename(__FILE__) . " requires PHP 5.3.1 or newer." . PHP_EOL;
        exit(1);
    }
}
foreach (array('phar', 'reflection', 'json', 'pcre') as $ext) {
    if (!extension_loaded($ext)) {
        echo "Extension $ext is required." . PHP_EOL;
        exit(1);
    }
}
try {
    Phar::mapPhar();
    if (realpath($_SERVER['SCRIPT_FILENAME']) !== realpath(__FILE__)) {
        $metadata = json_decode(
            file_get_contents(
                "phar://" . __FILE__ . DIRECTORY_SEPARATOR . "composer.lock"
            ),
            TRUE
        );
        $metadata['packages'][] = json_decode(
            file_get_contents(
                "phar://" . __FILE__ . DIRECTORY_SEPARATOR . "composer.json"
            ),
            TRUE
        );
        return $metadata;
    }
} catch (Exception $e) {
    echo "Cannot process " . basename(__FILE__) . ":" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

if (realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    class Erebot_Phar_App
    {
        private $_args;

        public function __construct($args)
        {
            if (count($args)) {
                array_shift($args);
            }
            $this->_args = $args;
        }

        public function run()
        {
            if (!count($this->_args)) {
                return $this->usage();
            }

            $command = $this->_args[0];
            if (method_exists(__CLASS__, 'run_' . $command)) {
                return call_user_func(array(__CLASS__, 'run_' . $command));
            }
            return $this->usage();
        }

        public function usage()
        {
            echo "Usage: " . basename(__FILE__) . " [command]" . PHP_EOL;
            echo PHP_EOL;
            echo "Available commands:" . PHP_EOL;
            $refl = new ReflectionClass(__CLASS__);
            $commands = array();
            $maxlen = 0;
            foreach ($refl->getMethods() as $method) {
                $name = $method->getName();
                if (!strncmp($name, 'run_', 4)) {
                    $name = substr($name, 4);
                    if ($name === FALSE) {
                        continue;
                    }

                    $doc = $method->getDocComment();
                    if ($doc !== FALSE) {
                        // Remove /** and */.
                        $doc = substr($doc, 3, -2);
                    }
                    if ($doc === FALSE) {
                        $doc = "No documentation available.";
                    }
                    else {
                        // Remove '*' at the beginning of each line.
                        $doc = preg_replace('/^\\s+\\*/m', ' ', $doc);
                        // Replace successive whitespaces by a single single.
                        $doc = preg_replace('/\\s+/', ' ', $doc);
                    }

                    $commands[$name] = $doc;
                    if (strlen($name) > $maxlen) {
                        $maxlen = strlen($name);
                    }
                }
            }

            $maxlen += 4;
            foreach ($commands as $name => $doc) {
                $start = str_pad("  " . $name, $maxlen);
                $lines = explode("\n", wordwrap($doc, 78 - $maxlen));
                foreach ($lines as $line) {
                    echo $start . $line . PHP_EOL;
                    $start = str_repeat(' ', $maxlen);
                }
            }
        }

        /**
         * Displays information about this module's version.
         */
        public function run_version()
        {
            $phar = new Phar(__FILE__);
            $md = $phar->getMetadata();
            echo $md['realname'] . ' version ' . $md['version'] . PHP_EOL;
        }

        /**
         * Displays this module's metadata (composer.json).
         */
        public function run_metadata()
        {
            echo file_get_contents(
                "phar://" . __FILE__ . DIRECTORY_SEPARATOR . "composer.json"
            );
        }
    }

    $app = new Erebot_Phar_App($_SERVER['argv']);
    $app->run();
}

__HALT_COMPILER();
