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
     * Should Phinx create unsigned primary keys by default?
     */
    public static bool $unsignedPks = true;
    /**
     * Should Phinx create columns NULL by default?
     */
    public static bool $columnNull = true;

    public static function setFlagsFromConfig(array $config): void {
        if (isset($config['unsigned_pks'])) {
            self::$unsignedPks = (bool)$config['unsigned_pks'];
        }
        if (isset($config['column_null'])) {
            self::$columnNull = (bool)$config['column_null'];
        }
    }
}
