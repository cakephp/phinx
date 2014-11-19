<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
     * Last generated timestamp
     *
     * @var string
     */
    protected static $lastTimestamp;

    /**
     * Gets the current timestamp string, in UTC.
     *
     * @return string
     */
    public static function getCurrentTimestamp()
    {
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        static::$lastTimestamp = $dt->format(static::DATE_FORMAT);
        return static::$lastTimestamp;
    }

    /**
     * Get the last generated timestamp
     * @return type
     */
    protected static function getLastTimestamp()
    {
        if (!static::$lastTimestamp) {
            return static::getCurrentTimestamp();
        }
        return static::$lastTimestamp;
    }

    /**
     * Generate class name from string
     *
     * @param string  $name
     * @param boolean $autoTimestamp
     *
     * @throws \InvalidArgumentException
     */
    public static function generateClassName($name, $autoTimestamp = false)
    {
        $className = ucfirst($name);

        if (!static::isValidMigrationClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        if ($autoTimestamp) {
            $className = $className . '_' . static::getLastTimestamp();
        }

        return $className;
    }

    /**
     * Turn migration names like 'CreateUserTable' into file names like
     * '12345678901234_create_user_table.php' or 'LimitResourceNamesTo30Chars' into
     * '12345678901234_limit_resource_names_to_30_chars.php'.
     *
     * @param string  $className     Class Name
     * @param boolean $autoTimestamp Append timestamp to the class name?
     * @return string
     */
    public static function mapClassNameToFileName($className, $autoTimestamp = false)
    {
        // remove timestamp from the tail of the class name
        $matches = array();
        if ($autoTimestamp && preg_match('/^(.*)_[0-9]+$/', $className, $matches)) {
            $className = $matches[1];
        }

        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = static::getLastTimestamp() . '_' . strtolower(implode($arr, '_')) . '.php';
        return $fileName;
    }

    /**
     * Get the classname from the migration file name
     * 
     * @param string  $filename      Migration file name
     * @param boolean $autoTimestamp Append timestamp to the class name?
     */
    public static function mapFileNameToClassName($filename, $autoTimestamp = false)
    {
        // convert the filename to a class name
        $class = preg_replace('/^[0-9]+_/', '', $filename);
        $class = str_replace('_', ' ', $class);
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);
        if (false !== strpos($class, '.')) {
            $class = substr($class, 0, strpos($class, '.'));
        }

        if ($autoTimestamp) {
            $timestamp = strstr($filename, '_', true);
            $class     = $class . '_' . $timestamp;
        }
        return $class;
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
