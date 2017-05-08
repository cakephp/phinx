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
 * @subpackage Phinx\Migration\Locator
 */
namespace Phinx\Migration\Locator;

use Phinx\Migration\MigrationDefinition;
use Phinx\Util\Util;

/**
 * BC locator to support existing migrations
 *
 * @author Cas Leentfaar
 */
class DefaultLocator implements LocatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function generate($targetDir, $name = null)
    {
        $version = Util::getCurrentTimestamp();
        $className = $name;

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        if (!Util::isUniqueMigrationClassName($className, $targetDir)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" already exists',
                $className
            ));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        return new MigrationDefinition($version, $className, $filePath, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function locate($filePath)
    {
        $fileName = basename($filePath);

        if (Util::isValidMigrationFileName($fileName)) {
            $version = Util::getVersionFromFileName($fileName);
            $name = substr($fileName, strpos($fileName, '_') + 1);

            // convert the filename to a class name
            $class = Util::mapFileNameToClassName($fileName);

            return new MigrationDefinition($version, $class, $filePath, $name);
        }
    }
}
