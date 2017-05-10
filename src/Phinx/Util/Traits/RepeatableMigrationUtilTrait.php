<?php

namespace Phinx\Util\Traits;

use Phinx\Util\Util;

trait RepeatableMigrationUtilTrait
{
    /**
     * Gets an array of all the existing seed class names.
     *
     * @return string[]
     */
    public static function getExistingRepeatableMigrationClassNames($path)
    {
        $classNames = array();

        if (!is_dir($path)) {
            return $classNames;
        }

        // filter the files to only get the ones that match our naming scheme
        $phpFiles = glob($path . DIRECTORY_SEPARATOR . '*.php');

        foreach ($phpFiles as $filePath) {
            if (preg_match(Util::REPEATABLE_MIGRATION_FILE_NAME_PATTERN, basename($filePath))) {
                $classNames[] = static::mapRepeatableMigrationFileNameToClassName(basename($filePath));
            }
        }

        return $classNames;
    }

    /**
     * Turn seed names like 'PopulateUsersTable' into file names like 'populate_users_table.php'.
     *
     * @param string $className Class Name
     * @return string
     */
    public static function mapClassNameToRepeatableMigrationFileName($className)
    {
        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = Util::getCurrentTimestamp() . '_' . strtolower(implode($arr, '_')) . '.php';
        return $fileName;
    }

    /**
     * Turn file names like 'populate_users_table.php' into class names like 'PopulateUsersTable'.
     *
     * @param string $fileName File Name
     * @return string
     */
    public static function mapRepeatableMigrationFileNameToClassName($fileName)
    {
        $matches = array();
        if (preg_match(Util::REPEATABLE_MIGRATION_FILE_NAME_PATTERN, $fileName, $matches)) {
            $fileName = $matches[1];
        }

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName)));
    }

    /**
     * Check if a seed class name is unique.
     *
     * This method takes a class name and a path to a seeds directory.
     *
     * RepeatableMigration class names must be in CamelCase format.
     * e.g: PopulateUsersTable.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     * @param string $path Path
     * @return boolean
     */
    public static function isUniqueRepeatableMigrationClassName($className, $path)
    {
        $existingClassNames = static::getExistingRepeatableMigrationClassNames($path);
        return !(in_array($className, $existingClassNames));
    }

    /**
     * Check if a seed file name is valid.
     *
     * @param string $fileName File Name
     * @return boolean
     */
    public static function isValidRepeatableMigrationFileName($fileName)
    {
        $matches = array();
        return preg_match(Util::REPEATABLE_MIGRATION_FILE_NAME_PATTERN, $fileName, $matches);
    }

}
