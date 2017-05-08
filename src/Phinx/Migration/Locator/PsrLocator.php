<?php
/**
 * Phinx.
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
 * @author Cas Leentfaar
 */
class PsrLocator implements LocatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function generate($targetDir, $name = null)
    {
        $version = Util::getCurrentTimestamp();
        $className = sprintf('v%d%s', $version, $name ? '_' . $name : '');
        $filePath = sprintf('%s/%s.php', $targetDir, $className);
        $definition = new MigrationDefinition($version, $className, $filePath, $name);

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function locate($filePath)
    {
        // convert the filename to a class name
        $class = basename($filePath, '.php');

        if (false !== $underscorePos = strpos($class, '_')) {
            $name = substr($class, strpos($class, "_") + 1);
        } else {
            $name = null;
        }

        // extract version from class name
        $version = preg_replace('/[^\d]/', '', $class);

        // try to find namespace in class
        $content = file_get_contents($filePath);
        preg_match('#^namespace\s+(.+?);$#sm', $content, $matches);

        if (!isset($matches[1])) {
            // no namespace detected, the FQCN is expected to be the same as the filename without extension
            $fqcn = $class;
        } else {
            // namespace detected, the FQCN is expected to be the same as the namespace + filename
            $fqcn = sprintf('%s\%s', $matches[1], $class);
        }

        return new MigrationDefinition($version, $fqcn, $filePath, $name);
    }
}
