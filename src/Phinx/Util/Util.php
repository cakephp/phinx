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
 * @subpackage Phinx\Util
 */
namespace Phinx\Util;

use Phinx\Util\Traits\MigrationUtilTrait;
use Phinx\Util\Traits\SeedUtilTrait;

class Util
{
    use MigrationUtilTrait;
    use SeedUtilTrait;

    /**
     * @var string
     */
    const DATE_FORMAT = 'YmdHis';

    /**
     * @var string
     */
    const MIGRATION_FILE_NAME_PATTERN = '/^\d+_([\w_]+).php$/i';

    /**
     * @var string
     */
    const REPEATABLE_MIGRATION_FILE_NAME_PATTERN = '/^([\w_]+).php$/i';

    /**
     * @var string
     */
    const SEED_FILE_NAME_PATTERN = '/^([A-Z][a-z0-9]+).php$/i';

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
     * Check if a migration/seed class name is valid.
     *
     * Migration & Seed class names must be in CamelCase format.
     * e.g: CreateUserTable, AddIndexToPostsTable or UserSeeder.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     * @return boolean
     */
    public static function isValidPhinxClassName($className)
    {
        return (bool) preg_match('/^([A-Z][a-z0-9]+)+$/', $className);
    }

    /**
     * Expands a set of paths with curly braces (if supported by the OS).
     *
     * @param array $paths
     * @return array
     */
    public static function globAll(array $paths)
    {
        $result = array();

        foreach ($paths as $path) {
            $result = array_merge($result, static::glob($path));
        }

        return $result;
    }

    /**
     * Expands a path with curly braces (if supported by the OS).
     *
     * @param $path
     * @return array
     */
    public static function glob($path)
    {
        return glob($path, defined('GLOB_BRACE') ? GLOB_BRACE : 0);
    }
}
