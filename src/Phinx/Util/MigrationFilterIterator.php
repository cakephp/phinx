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
 * @author t1gor <igor.timoshenkov@gmail.com>
 */
namespace Phinx\Util;

/**
 * Class MigrationFilterIterator
 * @package Phinx\Util
 */
class MigrationFilterIterator extends \FilterIterator
{
    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function accept()
    {
        /** @var \SplFileInfo $file */
        $file = $this->current();
        $filename = $file->getFilename();

        // check only for php files
        $isPhp = strtolower($file->getExtension()) === 'php';

        // following the naming convention
        $validFileName = Util::isValidMigrationFileName($filename);

        // accept if matches
        return $isPhp && $validFileName;
    }
}