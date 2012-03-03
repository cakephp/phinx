<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2012 Rob Morgan
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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Migration\MigrationInterface;

/**
 * Adapter interface
 *
 * @author      Rob Morgan <rob@robmorgan.id.au>
 */
interface AdapterInterface
{
    /**
     * Get all migrated version numbers.
     *
     * @return array
     */
    public function getVersions();
    
    /**
     * Records a migration being run.
     *
     * @param MigrationInterface $migration Migration
     * @param string $direction Direction
     * @return AdapterInterface
     */
    public function migrated(MigrationInterface $migration, $direction);

    /**
     * Does the schema table exist?
     *
     * @return boolean
     */
    public function hasSchemaTable();

    /**
     * Creates the schema table.
     *
     * @return void
     */
    public function createSchemaTable();
    
    /**
     * Returns the adapter type.
     *
     * @return string
     */
    public function getAdapterType();
    
    /**
     * Initializes the database connection.
     *
     * @throws \RuntimeException When the requested database driver is not installed.
     * @return void
     */
    public function connect();
    
    /**
     * Closes the database connection.
     *
     * @return void
     */
    public function disconnect();
    
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
     * Quotes a table name for use in a query.
     * 
     * @param string $tableName Table Name
     * @return string
     */
    public function quoteTableName($tableName);
    
    /**
     * Quotes a column name for use in a query.
     * 
     * @param string $columnName Table Name
     * @return string
     */
    public function quoteColumnName($columnName);
    
    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table Name
     * @return boolean
     */
    public function hasTable($tableName);
    
    /**
     * Creates the specified database table.
     *
     * @param string $tableName Table Name
     * @param array  $columns   Columns
     * @param array  $indexes   Indexes
     * @param string $options   Options
     * @return void
     */
    public function createTable($tableName, $columns, $indexes, $options);
    
    /**
     * Renames the specified database table.
     *
     * @param string $tableName Table Name
     * @param string $newName   New Name
     * @return void
     */
    public function renameTable($tableName, $newName);
    
    /**
     * Drops the specified database table.
     * 
     * @param string $tableName Table Name
     * @return void
     */
    public function dropTable($tableName);
    
    /**
     * Checks to see if a column exists.
     *
     * @param string $tableName  Table Name
     * @param string $columnName Column Name
     * @param array  $options    Options
     * @return boolean
     */
    public function hasColumn($tableName, $columnName, $options = array());
    
    /**
     * Adds the specified column to a database table.
     * 
     * @param string $tableName   Table Name
     * @param string $columnName  Column Name
     * @param string $type Column Type
     * @param array  $options     Options
     * @return void
     */
    public function addColumn($tableName, $columnName, $type, $options);
    
    /**
     * Renames the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return void
     */
    public function renameColumn($tableName, $columnName, $newColumnName);
    
    /**
     * Drops the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @return void
     */
    public function dropColumn($tableName, $columnName);
    
    /**
     * Checks to see if an index exists.
     *
     * @param string $tableName Table Name
     * @param mixed $columns Column(s)
     * @param array $options Options
     * @return boolean
     */
    public function hasIndex($tableName, $columns, $options);
    
    /**
     * Adds the specified index to a database table.
     * 
     * @param string $tableName Table Name
     * @param mixed $columns Column(s)
     * @param array $options Options
     * @return void
     */
    public function addIndex($tableName, $columns, $options);
    
    /**
     * Drops the specified index from a database table.
     * 
     * @param string $tableName
     * @param mixed $columns Column(s)
     * @param array $options
     * @return void
     */
    public function dropIndex($tableName, $columns, $options);
    
    /**
     * Returns an array of the supported Phinx column types.
     * 
     * @return array
     */
    public function getColumnTypes();
    
    /**
     * Converts the Phinx logical type to the adapter's SQL type.
     * 
     * @param string $type Type
     * @return string
     */
    public function getSqlType($type);
    
    /**
     * Creates a new database.
     *
     * @param string $name Database Name
     * @param array $options Options
     * @return void
     */
    public function createDatabase($name, $options);
    
    /**
     * Drops the specified database.
     *
     * @param string $name Database Name
     * @return void
     */
    public function dropDatabase($name);
}