<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2013 Rob Morgan
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
 * @subpackage Phinx\Migration\Manager
 * @author Jordi Llonch <llonch.jordi@gmail.com>
 */

namespace Phinx\Migration\Manager;

use Phinx\Config\Config;

interface VersionInterface {
    /**
     * @param Config $config
     */
    public function __construct(Config $config);

    /**
     * Create a new version class.
     *
     * @param $className
     * @param $contents
     * @return string
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function create($className, $contents);

    /**
     * Get an array of versions with their classes instances.
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getMigrations();
} 