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

use Phinx\Db\Table,
    Phinx\Db\Table\Column,
    Phinx\Db\Table\Index;

class MysqlAdapter extends PdoAdapter implements AdapterInterface
{
    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if (null === $this->connection) {
            if (!class_exists('PDO') || !in_array('mysql', \PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_Mysql extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }
            
            $dsn = '';
            $db = null;
            $options = $this->getOptions();
            
            // if port is specified use it, otherwise use the MySQL default
            if (isset($options['port'])) {
                $dsn = 'mysql:host=' . $options['host'] . ';port=' . $options['port'] . ';dbname=' . $options['name'];
            } else {
                $dsn = 'mysql:host=' . $options['host'] . ';dbname=' . $options['name'];
            }

            try {
                $db = new \PDO($dsn, $options['user'], $options['pass'], array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
            } catch(\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: '
                    . $exception->getMessage()
                ));
            }

            $this->setConnection($db);
            
            // Create the schema table if it doesn't already exist
            if (!$this->hasSchemaTable()) {
                $this->createSchemaTable();
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->connection = null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return str_replace('.', '`.`', $this->quoteColumnName($tableName));
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '`' . str_replace('`', '``', $columnName) . '`';
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $options = $this->getOptions();
        
        $tables = array();
        $rows = $this->fetchAll(sprintf('SHOW TABLES IN `%s`', $options['name']));
        foreach ($rows as $row) {
            $tables[] = strtolower($row[0]);
        }
        
        return in_array(strtolower($tableName), $tables);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table)
    {
        // This method is based on the MySQL docs here: http://dev.mysql.com/doc/refman/5.1/en/create-index.html
        $defaultOptions = array(
            'engine' => 'InnoDB'
        );
        $options = array_merge($defaultOptions, $table->getOptions());
        
        // Add the default primary key
        $columns = $table->getColumns();
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $column = new \Phinx\Db\Table\Column();
            $column->setName('id')
                   ->setType('integer')
                   ->setIdentity(true);
            
            array_unshift($columns, $column);
            $options['primary_key'] = 'id';
        }
        
        // TODO - process table options like collation etc
        
        // convert options array to sql
        $optionsStr = 'ENGINE = InnoDB';
        
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';
        }
        
        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            $sql .= ' PRIMARY KEY (';
            if (is_string($options['primary_key'])) {       // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } else if (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                // PHP 5.4 will allow access of $this, so we can call quoteColumnName() directly in the anonymous function,
                // but for now just hard-code the adapter quotes
                $sql .= implode(',', array_map(function($v) { return '`' . $v . '`'; }, $options['primary_key']));
            }
            $sql .= ')';
        } else {
            $sql = substr(rtrim($sql), 0, -1);              // no primary keys
        }
        
        // set the indexes
        $indexes = $table->getIndexes();
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql .= ', ' . $this->getIndexSqlDefinition($index);
            }
        }
        
        $sql .= ') ' . $optionsStr;
        $sql = rtrim($sql) . ';';

        // execute the sql
        $this->execute($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $this->execute(sprintf('RENAME TABLE %s TO %s', $this->quoteTableName($tableName), $this->quoteTableName($newTableName)));
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $this->execute(sprintf('DROP TABLE %s', $this->quoteTableName($tableName)));
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName, $options = array())
    {
        // TODO - do we need $options? I think we borrowed the signature from
        // Rails and it's meant to test indexes or something??
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $tableName));
        foreach ($rows as $column) {
            if (strtolower($column['Field']) == strtolower($columnName)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $sql = sprintf('ALTER TABLE %s ADD %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );
        
        if ($column->getAfter()) {
            $sql .= ' AFTER ' . $this->quoteColumnName($column->getAfter());
        }
        
        return $this->execute($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $rows = $this->fetchAll(sprintf('DESCRIBE %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            if (strtolower($row['Field']) == strtolower($columnName)) {
                $null = ($row['Null'] == 'NO') ? 'NOT NULL' : 'NULL';
                $extra = ' ' . strtoupper($row['Extra']);
                $definition = $row['Type'] . ' ' . $null . $extra;
        
                return $this->execute(
                    sprintf('ALTER TABLE %s CHANGE COLUMN %s %s %s',
                            $this->quoteTableName($tableName),
                            $this->quoteColumnName($columnName),
                            $this->quoteColumnName($newColumnName),
                            $definition
                    )
                );
            }
        }
        
        throw new \InvalidArgumentException(sprintf(
            'The specified column doesn\'t exist: '
            . $columnName
        ));
    }
    
    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        return $this->execute(
            sprintf('ALTER TABLE %s CHANGE %s %s %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumn->getName()),
                $this->getColumnSqlDefinition($newColumn)
            )
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $this->execute(
            sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName)
            )
        );
    }
    
    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $indexes = array();
        $rows = $this->fetchAll(sprintf('SHOW INDEXES FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            if (!isset($indexes[$row['Key_name']])) {
                $indexes[$row['Key_name']] = array('columns' => array());
            }
            $indexes[$row['Key_name']]['columns'][] = strtolower($row['Column_name']);
        }
        return $indexes;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        
        $columns = array_map('strtolower', $columns);
        $indexes = $this->getIndexes($tableName);
        
        foreach ($indexes as $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        return $this->execute(
            sprintf('ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getIndexSqlDefinition($index)
            )
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns, $options = array())
    {
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        
        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);
        
        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                return $this->execute(
                    sprintf('ALTER TABLE %s DROP INDEX %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($indexName)
                    )
                );   
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSqlType($type)
    {
        switch ($type) {
            case 'primary_key':
                return self::DEFAULT_PRIMARY_KEY;
            case 'string':
                return array('name' => 'varchar', 'limit' => 255);
                break;
            case 'text':
                return array('name' => 'text');
                break;
            case 'integer':
                return array('name' => 'int', 'limit' => 11);
                break;
            case 'biginteger':
                return array('name' => 'bigint', 'limit' => 11);
                break;
            case 'float':
                return array('name' => 'float');
                break;
            case 'decimal':
                return array('name' => 'decimal');
                break;
            case 'datetime':
                return array('name' => 'datetime');
                break;
            case 'timestamp':
                return array('name' => 'datetime');
                break;
            case 'time':
                return array('name' => 'time');
                break;
            case 'date':
                return array('name' => 'date');
                break;
            case 'binary':
                return array('name' => 'blob');
                break;
            case 'boolean':
                return array('name' => 'tinyint', 'limit' => 1);
                break;
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported.');
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        $charset = isset($options['charset']) ? $options['charset'] : 'utf8';
        
        if (isset($options['collation'])) {
            $this->execute(sprintf(
                'CREATE DATABASE `%s` DEFAULT CHARACTER SET `%s` COLLATE `%s`', $name, $charset, $options['collation']
            ));
        } else {
            $this->execute(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET `%s`', $name, $charset));
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        $rows = $this->fetchAll(
            sprintf(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \'%s\'',
                $name
            )
        );
        
        foreach ($rows as $row) {
            if (!empty($row)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->execute(sprintf('DROP DATABASE IF EXISTS `%s`', $name));
    }
    
    /**
     * Gets the MySQL Column Definition for a Column object.
     *
     * @param Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $sqlType = $this->getSqlType($column->getType());
        $def = '';
        $def = '';
        $def .= strtoupper($sqlType['name']);
        $def .= ($column->getLimit() || isset($sqlType['limit']))
                     ? '(' . ($column->getLimit() ? $column->getLimit() : $sqlType['limit']) . ')' : '';
        $def .= ($column->isNull() == false) ? ' NOT NULL' : ' NULL';
        $def .= ($column->isIdentity()) ? ' AUTO_INCREMENT' : '';
        $def .= ($column->getDefault()) ? ' DEFAULT \'' . $column->getDefault() . '\'' : '';
        // TODO - add precision & scale for decimals
        return $def;
    }
    
    /**
     * Gets the MySQL Index Definition for an Index object.
     *
     * @param Index $index Index
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index)
    {
        $def = '';
        if ($index->getType() == Index::UNIQUE) {
            $def .= ' UNIQUE KEY (' . implode(',', $index->getColumns()) . ')';
        } else {
            $def .= ' KEY (' . implode(',', $index->getColumns()) . ')';
        }
        return $def;
    }
}