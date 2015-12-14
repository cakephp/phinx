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

use \FilesystemIterator;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;

/**
 * Class MigrationIterator
 * @package Phinx\Util
 */
class MigrationIterator implements \Iterator, \Countable
{
    /**
     * @var MigrationFilterIterator
     */
    protected $it;

    /**
     * MigrationIterator constructor.
     * @param string $path
     * @param int $flags
     * @throws \InvalidArgumentException
     */
    public function __construct($path, $flags = FilesystemIterator::SKIP_DOTS)
    {
        if ('' === $path || !is_dir($path) || !file_exists($path)) {
            throw new \InvalidArgumentException("Seems like '{$path}' doesn't exist.");
        }

        // create the iterator
        $this->it = new RecursiveDirectoryIterator($path, $flags);
        $this->it = new RecursiveIteratorIterator($this->it);
        $this->it = new MigrationFilterIterator($this->it);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->it->current()->getRealPath();
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->it->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->it->key();
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->it->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->it->rewind();
    }

    /**
     * @return int
     */
    public function count()
    {
        return iterator_count($this->it);
    }
}
