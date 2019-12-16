<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Config;

use ArrayAccess;

/**
 * Phinx configuration interface.
 *
 * @package Phinx
 * @author Woody Gilk
 */
interface ConfigInterface extends ArrayAccess
{
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
     *
     * @return array|null
     */
    public function getEnvironment($name);

    /**
     * Does the specified environment exist in the configuration file?
     *
     * @param string $name Environment Name
     *
     * @return bool
     */
    public function hasEnvironment($name);

    /**
     * Gets the default environment name.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function getDefaultEnvironment();

    /**
     * Get the aliased value from a supplied alias.
     *
     * @param string $alias
     *
     * @return string|null
     */
    public function getAlias($alias);

    /**
     * Get all the aliased values.
     *
     * @return string[]
     */
    public function getAliases();

    /**
     * Gets the config file path.
     *
     * @return string
     */
    public function getConfigFilePath();

    /**
     * Gets the paths to search for migration files.
     *
     * @return string[]
     */
    public function getMigrationPaths();

    /**
     * Gets the paths to search for seed files.
     *
     * @return string[]
     */
    public function getSeedPaths();

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
     * Get the version order.
     *
     * @return string
     */
    public function getVersionOrder();

    /**
     * Is version order creation time?
     *
     * @return bool
     */
    public function isVersionOrderCreationTime();

    /**
     * Get the bootstrap file path
     *
     * @return string|false
     */
    public function getBootstrapFile();

    /**
     * Gets the base class name for migrations.
     *
     * @param bool $dropNamespace Return the base migration class name without the namespace.
     *
     * @return string
     */
    public function getMigrationBaseClassName($dropNamespace = true);
}
