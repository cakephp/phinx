<?php

namespace Phinx\Util\Traits;

use Phinx\Util\Util;

trait SeedUtilTrait
{
    /**
     * Gets an array of all the existing seed class names.
     *
     * @return string[]
     */
    public static function getExistingSeedClassNames($path)
    {
        $classNames = array();

        if (!is_dir($path)) {
            return $classNames;
        }

        // filter the files to only get the ones that match our naming scheme
        $phpFiles = glob($path . DIRECTORY_SEPARATOR . '*.php');

        foreach ($phpFiles as $filePath) {
            if (preg_match(Util::SEED_FILE_NAME_PATTERN, basename($filePath))) {
                $classNames[] = static::mapSeedFileNameToClassName(basename($filePath));
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
    public static function mapClassNameToSeedFileName($className)
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
    public static function mapSeedFileNameToClassName($fileName)
    {
        $matches = array();
        if (preg_match(Util::SEED_FILE_NAME_PATTERN, $fileName, $matches)) {
            $fileName = $matches[1];
        }

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName)));
    }

    /**
     * Check if a seed class name is unique.
     *
     * This method takes a class name and a path to a seeds directory.
     *
     * Seed class names must be in CamelCase format.
     * e.g: PopulateUsersTable.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     * @param string $path Path
     * @return boolean
     */
    public static function isUniqueSeedClassName($className, $path)
    {
        $existingClassNames = static::getExistingSeedClassNames($path);
        return !(in_array($className, $existingClassNames));
    }

    /**
     * Check if a seed file name is valid.
     *
     * @param string $fileName File Name
     * @return boolean
     */
    public static function isValidSeedFileName($fileName)
    {
        $matches = array();
        return preg_match(Util::SEED_FILE_NAME_PATTERN, $fileName, $matches);
    }

}
