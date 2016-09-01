<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
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
 * @subpackage Phinx\Config
 */
namespace Phinx\Config;

/**
 * Phinx configuration interface.
 *
 * @package Phinx
 * @author  Woody Gilk
 */
interface ConfigInterface extends \ArrayAccess
{
    /**
     * Class Constructor
     *
     * @param array $configArray Config Array
     * @param string $configFilePath Optional File Path
     */
    public function __construct(array $configArray, $configFilePath = null);

    /**
     * Returns the configuration for each environment.
     *
     * This method returns <code>null</code> if no environments exist.
     *
     * @return array|null
     */
    public function getEnvironments();

    /**
     * Returns the configuration for a given environment.
     *
     * This method returns <code>null</code> if the specified environment
     * doesn't exist.
     *
     * @param string $name
     * @return array|null
     */
    public function getEnvironment($name);

    /**
     * Does the specified environment exist in the configuration file?
     *
     * @param string $name Environment Name
     * @return boolean
     */
    public function hasEnvironment($name);

    /**
     * Gets the default environment name.
     *
     * @throws \RuntimeException
     * @return string
     */
    public function getDefaultEnvironment();

    /**
     * Gets the config file path.
     *
     * @return string
     */
    public function getConfigFilePath();

    /**
     * Gets the path of the migration files.
     *
     * @return string
     */
    public function getMigrationPath();

    /**
     * Gets the path of the seed files.
     *
     * @return string
     */
    public function getSeedPath();

     /**
     * Get the template file name.
     *
     * @return string|false
     */
     public function getTemplateFile();

     /**
     * Get the template class name.
     *
     * @return string|false
     */
    public function getTemplateClass();

    /**
     * Gets the base class name for migrations.
     *
     * @param boolean $dropNamespace Return the base migration class name without the namespace.
     * @return string
     */
    public function getMigrationBaseClassName($dropNamespace = true);
}
