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

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;

/**
 * Migration interface
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
interface MigrationInterface
{
    /**
     * @var string
     */
    const CHANGE = 'change';
    
    /**
     * @var string
     */
    const UP = 'up';
    
    /**
     * @var string
     */
    const DOWN = 'down';
    
    /**
     * Migrate Up
     *
     * @return void
     */
    public function up();

    /**
     * Migrate Down
     *
     * @return void
     */
    public function down();
    
    /**
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter Database Adapter
     * @return MigrationInterface
     */
    public function setAdapter(AdapterInterface $adapter);
    
    /**
     * Gets the database adapter.
     *
     * @return AdapterInterface
     */
    public function getAdapter();
    
    /**
     * Gets the name.
     *
     * @return string
     */
    public function getName();
    
    /**
     * Sets the migration version number.
     *
     * @param float $version Version
     * @return MigrationInterface
     */
    public function setVersion($version);
    
    /**
     * Gets the migration version number.
     *
     * @return float
     */
    public function getVersion();
    
    /**
     * Executes a SQL statement and returns the number of affected rows.
     * 
     * @param string $sql SQL
     * @return int
     */
    public function execute($sql);
    
    /**
     * Executes a SQL statement and returns the result as an array. 
     *
     * @param string $sql SQL
     * @return array
     */
    public function query($sql);
    
    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchRow($sql);
    
    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll($sql);
    
    /**
     * Create a new database.
     *
     * @param string $name Database Name
     * @param array $options Options
     * @return void
     */
    public function createDatabase($name, $options);
    
    /**
     * Drop a database.
     *
     * @param string $name Database Name
     * @return void
     */
    public function dropDatabase($name);
    
    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table Name
     * @return boolean
     */
    public function hasTable($tableName);
    
    /**
     * Returns an instance of the <code>\Table</code> class.
     *
     * You can use this class to create and manipulate tables.
     *
     * @param string $tableName Table Name
     * @param array $options Options
     * @return Table
     */
    public function table($tableName, $options);
}
