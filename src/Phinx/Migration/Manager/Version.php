<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2013 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Migration\Manager
 * @author Jordi Llonch <llonch.jordi@gmail.com>
 */

namespace Phinx\Migration\Manager;

use Phinx\Config\Config;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\Util;

class Version implements VersionInterface {
    /**
     * @var Config
     */
    protected $config;

    /**
     * @inheritdoc
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function create($className, $contents)
    {
        // get the migration path from the config
        $path = $this->config->getMigrationPath();

        if (!is_writeable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" is not writeable',
                $path
            ));
        }

        if (!Util::isValidMigrationClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $path = realpath($this->config->getMigrationPath());
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $filePath
            ));
        }

        return 'File created at ' . $filePath;
    }

    /**
     * @inheritdoc
     */
    public function getMigrations()
    {
        $migrations = array();

        $phpFiles = glob($this->config->getMigrationPath() . DIRECTORY_SEPARATOR . '*.php');

        // filter the files to only get the ones that match our naming scheme
        $fileNames = array();
        $versions = array();

        foreach ($phpFiles as $filePath) {
            if (preg_match('/([0-9]+)_([_a-z0-9]*).php/', basename($filePath))) {
                $matches = array();
                preg_match('/^[0-9]+/', basename($filePath), $matches); // get the version from the start of the filename
                $version = $matches[0];

                if (isset($versions[$version])) {
                    throw new \InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $version));
                }

                // convert the filename to a class name
                $class = preg_replace('/^[0-9]+_/', '', basename($filePath));
                $class = str_replace('_', ' ', $class);
                $class = ucwords($class);
                $class = str_replace(' ', '', $class);
                if (false !== strpos($class, '.')) {
                    $class = substr($class, 0, strpos($class, '.'));
                }

                if (isset($fileNames[$class])) {
                    throw new \InvalidArgumentException(sprintf(
                        'Migration "%s" has the same name as "%s"',
                        basename($filePath),
                        $fileNames[$class]
                    ));
                }

                $fileNames[$class] = basename($filePath);

                // load the migration file
                require_once $filePath;
                if (!class_exists($class)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Could not find class "%s" in file "%s"',
                        $class,
                        $filePath
                    ));
                }

                // instantiate it
                $migration = new $class($version);

                if (!($migration instanceof AbstractMigration)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" in file "%s" must extend \Phinx\Migration\AbstractMigration',
                        $class,
                        $filePath
                    ));
                }

                $versions[$version] = $migration;
            }
        }

        return $versions;
    }
}