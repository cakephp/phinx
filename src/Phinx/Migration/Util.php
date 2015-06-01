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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

class Util
{
    /**
     * @var string
     */
    const DATE_FORMAT = 'YmdHis';

    /**
     * Gets the current timestamp string, in UTC.
     *
     * @return string
     */
    public static function getCurrentTimestamp()
    {
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        return $dt->format(static::DATE_FORMAT);
    }

    /**
     * Turn migration names like 'CreateUserTable' into file names like
     * '12345678901234_create_user_table.php' or 'LimitResourceNamesTo30Chars' into
     * '12345678901234_limit_resource_names_to_30_chars.php'.
     *
     * @param string $className Class Name
     * @return string
     */
    public static function mapClassNameToFileName($className)
    {
        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = static::getCurrentTimestamp() . '_' . strtolower(implode($arr, '_')) . '.php';
        return $fileName;
    }

    /**
     * Check if the given path to a migration file is valid.
     *
     * Migration file names must be in a format similar to following:
     * $timestamp_$lowerCaseMigrationName
     *
     * @see Util::mapClassNameToFileName()
     * @param string $filePath Path to the migration file to be checked
     * @return boolean true if and only if $filePath is a valid path to a migration file
     */
    public static function isValidMigrationFilePath($filePath){
        return preg_match('/([0-9]+)_([_a-z0-9]*).php/', basename($filePath));
    }

    /**
     * Returns the version of a migration based on the path to the file of the migration
     *
     * @param string $filePath Path to the migration file the version number is to be acquired
     * @return string version of the migration corresponding to the given file path
     */
    public static function getVersionFromMigrationFilePath($filePath){
        $matches = array();
        preg_match('/^[0-9]+/', basename($filePath), $matches); // get the version from the start of the filename
        return $matches[0];
    }

    /**
     * Check if a migration class name is valid.
     *
     * Migration class names must be in CamelCase format.
     * e.g: CreateUserTable or AddIndexToPostsTable.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     * @return boolean
     */
    public static function isValidMigrationClassName($className)
    {
        return (bool) preg_match('/^([A-Z][a-z0-9]+)+$/', $className);
    }
}
