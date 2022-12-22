<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Config;

/**
 * Class to hold features flags to toggle breaking changes in Phinx.
 *
 * New flags should be added very sparingly.
 */
class FeatureFlags {
    /**
     * @var bool Should Phinx create unsigned primary keys by default?
     */
    public static $unsignedPks = true;
    /**
     * @var bool Should Phinx create columns NULL by default?
     */
    public static $columnNull = true;

    /**
     * Set the feature flags from a config object
     *
     * @param array $config
     */
    public static function setFlagsFromConfig(array $config): void
    {
        if (isset($config['unsigned_pks'])) {
            self::$unsignedPks = (bool)$config['unsigned_pks'];
        }
        if (isset($config['column_null'])) {
            self::$columnNull = (bool)$config['column_null'];
        }
    }
}
