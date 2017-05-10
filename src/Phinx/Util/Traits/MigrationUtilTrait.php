<?php

namespace Phinx\Util\Traits;

use Phinx\Util\Util;

trait MigrationUtilTrait
{
    /**
     * Gets an array of all the existing migration class names.
     *
     * @return string[]
     */
    public static function getExistingMigrationClassNames($path)
    {
        $classNames = array();

        if (!is_dir($path)) {
            return $classNames;
        }

        // filter the files to only get the ones that match our naming scheme
        $phpFiles = glob($path . DIRECTORY_SEPARATOR . '*.php');

        foreach ($phpFiles as $filePath) {
            if (preg_match(Util::MIGRATION_FILE_NAME_PATTERN, basename($filePath))) {
                $classNames[] = static::mapMigrationFileNameToClassName(basename($filePath));
            }
        }

        return $classNames;
    }

    /**
     * Turn migration names like 'CreateUserTable' into file names like
     * '12345678901234_create_user_table.php' or 'LimitResourceNamesTo30Chars' into
     * '12345678901234_limit_resource_names_to_30_chars.php'.
     *
     * @param string $className Class Name
     * @return string
     */
    public static function mapClassNameToMigrationFileName($className)
    {
        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = Util::getCurrentTimestamp() . '_' . strtolower(implode($arr, '_')) . '.php';
        return $fileName;
    }

    /**
     * Turn file names like '12345678901234_create_user_table.php' into class names like 'CreateUserTable'.
     *
     * @param string $fileName File Name
     * @return string
     */
    public static function mapMigrationFileNameToClassName($fileName)
    {
        $matches = array();
        if (preg_match(Util::MIGRATION_FILE_NAME_PATTERN, $fileName, $matches)) {
            $fileName = $matches[1];
        }

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName)));
    }

    /**
     * Check if a migration class name is unique regardless of the timestamp.
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
     * @return boolean
     */
    public static function isUniqueMigrationClassName($className, $path)
    {
        $existingClassNames = static::getExistingMigrationClassNames($path);
        return !(in_array($className, $existingClassNames));
    }

    /**
     * Check if a migration file name is valid.
     *
     * @param string $fileName File Name
     * @return boolean
     */
    public static function isValidMigrationFileName($fileName)
    {
        $matches = array();
        return preg_match(Util::MIGRATION_FILE_NAME_PATTERN, $fileName, $matches);
    }

    /**
     * Get the version from the beginning of a file name.
     *
     * @param string $fileName File Name
     * @return string
     */
    public static function getVersionFromFileName($fileName)
    {
        $matches = array();
        preg_match('/^\d+/', basename($fileName), $matches);
        return $matches[0];
    }
}
