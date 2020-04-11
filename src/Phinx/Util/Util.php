<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Util;

use DateTime;
use DateTimeZone;
use Exception;

class Util
{
    /**
     * @var string
     */
    public const DATE_FORMAT = 'YmdHis';

    /**
     * @var string
     */
    protected const MIGRATION_FILE_NAME_PATTERN = '/^\d+_([\w_]+).php$/i';

    /**
     * @var string
     */
    protected const SEED_FILE_NAME_PATTERN = '/^([A-Z][a-z0-9]+).php$/i';

    /**
     * Gets the current timestamp string, in UTC.
     *
     * @return string
     */
    public static function getCurrentTimestamp()
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));

        return $dt->format(static::DATE_FORMAT);
    }

    /**
     * Gets an array of all the existing migration class names.
     *
     * @param string $path Path
     *
     * @return string[]
     */
    public static function getExistingMigrationClassNames($path)
    {
        $classNames = [];

        if (!is_dir($path)) {
            return $classNames;
        }

        // filter the files to only get the ones that match our naming scheme
        $phpFiles = glob($path . DIRECTORY_SEPARATOR . '*.php');

        foreach ($phpFiles as $filePath) {
            if (preg_match('/([0-9]+)_([_a-z0-9]*).php/', basename($filePath))) {
                $classNames[] = static::mapFileNameToClassName(basename($filePath));
            }
        }

        return $classNames;
    }

    /**
     * Get the version from the beginning of a file name.
     *
     * @param string $fileName File Name
     *
     * @return string
     */
    public static function getVersionFromFileName($fileName)
    {
        $matches = [];
        preg_match('/^[0-9]+/', basename($fileName), $matches);

        return $matches[0];
    }

    /**
     * Turn migration names like 'CreateUserTable' into file names like
     * '12345678901234_create_user_table.php' or 'LimitResourceNamesTo30Chars' into
     * '12345678901234_limit_resource_names_to_30_chars.php'.
     *
     * @param string $className Class Name
     *
     * @return string
     */
    public static function mapClassNameToFileName($className)
    {
        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = static::getCurrentTimestamp() . '_' . strtolower(implode('_', $arr)) . '.php';

        return $fileName;
    }

    /**
     * Turn file names like '12345678901234_create_user_table.php' into class
     * names like 'CreateUserTable'.
     *
     * @param string $fileName File Name
     *
     * @return string
     */
    public static function mapFileNameToClassName($fileName)
    {
        $matches = [];
        if (preg_match(static::MIGRATION_FILE_NAME_PATTERN, $fileName, $matches)) {
            $fileName = $matches[1];
        }

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName)));
    }

    /**
     * Check if a migration class name is unique regardless of the
     * timestamp.
     *
     * This method takes a class name and a path to a migrations directory.
     *
     * Migration class names must be in CamelCase format.
     * e.g: CreateUserTable or AddIndexToPostsTable.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     * @param string $path Path
     *
     * @return bool
     */
    public static function isUniqueMigrationClassName($className, $path)
    {
        $existingClassNames = static::getExistingMigrationClassNames($path);

        return !(in_array($className, $existingClassNames, true));
    }

    /**
     * Check if a migration/seed class name is valid.
     *
     * Migration & Seed class names must be in CamelCase format.
     * e.g: CreateUserTable, AddIndexToPostsTable or UserSeeder.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     *
     * @return bool
     */
    public static function isValidPhinxClassName($className)
    {
        return (bool)preg_match('/^([A-Z][a-z0-9]+)+$/', $className);
    }

    /**
     * Check if a migration file name is valid.
     *
     * @param string $fileName File Name
     *
     * @return bool
     */
    public static function isValidMigrationFileName($fileName)
    {
        $matches = [];

        return preg_match(static::MIGRATION_FILE_NAME_PATTERN, $fileName, $matches);
    }

    /**
     * Check if a seed file name is valid.
     *
     * @param string $fileName File Name
     *
     * @return bool
     */
    public static function isValidSeedFileName($fileName)
    {
        $matches = [];

        return preg_match(static::SEED_FILE_NAME_PATTERN, $fileName, $matches);
    }

    /**
     * Expands a set of paths with curly braces (if supported by the OS).
     *
     * @param string[] $paths Paths
     *
     * @return string[]
     */
    public static function globAll(array $paths)
    {
        $result = [];

        foreach ($paths as $path) {
            $result = array_merge($result, static::glob($path));
        }

        return $result;
    }

    /**
     * Expands a path with curly braces (if supported by the OS).
     *
     * @param string $path Path
     *
     * @return string[]
     */
    public static function glob($path)
    {
        return glob($path, defined('GLOB_BRACE') ? GLOB_BRACE : 0);
    }

    /**
     * Takes the path to a php file and attempts to include it if readable
     *
     * @param string $filename Filename
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function loadPhpFile($filename)
    {
        $filePath = realpath($filename);
        if (!file_exists($filePath)) {
            throw new Exception(sprintf("File does not exist: %s \n", $filename));
        }

        /**
         * I lifed this from phpunits FileLoader class
         *
         * @see https://github.com/sebastianbergmann/phpunit/pull/2751
         */
        $isReadable = @fopen($filePath, 'r') !== false;

        if (!$isReadable) {
            throw new Exception(sprintf("Cannot open file %s \n", $filename));
        }

        include_once $filePath;

        return $filePath;
    }

    /**
     * Given an array of paths, return all unique PHP files that are in them
     *
     * @param string[] $paths array of paths to get php files
     *
     * @return string[]
     */
    public static function getFiles($paths)
    {
        $files = static::globAll(array_map(function ($path) {
            return $path . DIRECTORY_SEPARATOR . "*.php";
        }, $paths));
        // glob() can return the same file multiple times
        // This will cause the migration to fail with a
        // false assumption of duplicate migrations
        // http://php.net/manual/en/function.glob.php#110340
        $files = array_unique($files);

        return $files;
    }
}
