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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Db\Table,
    Phinx\Db\Table\Column,
    Phinx\Db\Table\Index,
    Phinx\Db\Table\ForeignKey,
    Phinx\Migration\MigrationInterface;

class PostgresAdapter extends PdoAdapter implements AdapterInterface
{
    /**
     * {@inheritdoc}
     */
    public function connect()
    {        
        if (null === $this->connection) {
            if (!class_exists('PDO') || !in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_Pgsql extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }
            
            $dsn = '';
            $db = null;
            $options = $this->getOptions();
            
            // if port is specified use it, otherwise use the PostgreSQL default
            if (isset($options['port'])) {
                $dsn = 'pgsql:host=' . $options['host'] . ';port=' . $options['port'] . ';dbname=' . $options['name'];
            } else {
                $dsn = 'pgsql:host=' . $options['host'] . ';dbname=' . $options['name'];
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
            
            // Create the public schema  if it doesn't already exist
            if(!$this->hasSchema('public')) {
                $this->createSchema('public');
            } 
            
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
    public function hasTransactions()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->execute('BEGIN');
    }
    
    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        $this->execute('COMMIT');
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        $this->execute('ROLLBACK');
    }
    
    /**
     * Quotes a schema name for use in a query.
     * 
     * @param string $schemaName Schema Name
     * @return string
     */
    public function quoteSchemaName($schemaName)
    {        
        return $this->quoteColumnName($schemaName);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {        
        return $this->quoteColumnName($tableName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '"'. $columnName . '"';
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $options = $this->getOptions();
        
        $tables = array();
        $rows = $this->fetchAll(sprintf('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\';'));
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
        $this->startCommandTimer();
        $options = $table->getOptions();
        
         // Add the default primary key
        $columns = $table->getPendingColumns();
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $column = new Column();
            $column->setName('id')
                   ->setType('integer')
                   ->setIdentity(true);
            
            array_unshift($columns, $column);
            $options['primary_key'] = 'id';

        } elseif (isset($options['id']) && is_string($options['id'])) {
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                   ->setType('integer')
                   ->setIdentity(true);

            array_unshift($columns, $column);
            $options['primary_key'] = $options['id'];
        }
        
        // TODO - process table options like collation etc        
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';
        }
        
         // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            $sql .= sprintf(' CONSTRAINT %s_pkey PRIMARY KEY (', $table->getName());
            if (is_string($options['primary_key'])) {       // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } else if (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                // PHP 5.4 will allow access of $this, so we can call quoteColumnName() directly in the anonymous function,
                // but for now just hard-code the adapter quotes
                $sql .= implode(',', array_map(function($v) { return '"' . $v . '"'; }, $options['primary_key']));
            }
            $sql .= ')';
        } else {
            $sql = substr(rtrim($sql), 0, -1);              // no primary keys
        }                      

        // set the foreign keys
        $foreignKeys = $table->getForeignKeys();
        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $foreignKey) {
                $sql .= ', ' . $this->getForeignKeySqlDefinition($foreignKey, $table->getName());
            }
        }

        $sql .= ') ';
        $sql = rtrim($sql) . ';';

        // set the indexes
        $indexes = $table->getIndexes();
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql .= $this->getIndexSqlDefinition($index, $table->getName());
            }
        }

        // execute the sql        
        $this->writeCommand('createTable', array($table->getName()));        
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {        
        $this->startCommandTimer();
        $this->writeCommand('renameTable', array($tableName, $newTableName));
        $sql = sprintf('ALTER TABLE %s RENAME TO %s',
            $this->quoteTableName($tableName), $this->quoteTableName($newTableName));        
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {        
        $this->startCommandTimer();
        $this->writeCommand('dropTable', array($tableName));
        $this->execute(sprintf('DROP TABLE %s', $this->quoteTableName($tableName)));
        $this->endCommandTimer();        
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $columns = array();
        $sql = sprintf("SELECT column_name, data_type, is_identity, is_nullable,
                column_default, character_maximum_length, numeric_precision, numeric_scale 
            FROM information_schema.columns 
            WHERE table_name ='%s'", $tableName);          
        $columnsInfo = $this->fetchAll($sql);
        
        foreach ($columnsInfo as $columnInfo) {            
            $column = new Column();
            $column->setName($columnInfo['column_name'])
                   ->setType($this->getPhinxType($columnInfo['data_type']))
                   ->setNull($columnInfo['is_nullable'] == 'YES')
                   ->setDefault($columnInfo['column_default'])
                   ->setIdentity($columnInfo['is_identity'] == 'YES');                   
            
            if (isset($columnInfo['character_maximum_length'])) {
                $column->setLimit($columnInfo['character_maximum_length']);
            }
            $columns[] = $column;
        }
        return $columns;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName, $options = array())
    {        
        $sql = sprintf("SELECT count(*) 
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = '%s' AND column_name = '%s'", $tableName, $columnName);                
        $result = $this->fetchRow($sql);
        return  $result['count'] > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $this->startCommandTimer();
        $this->writeCommand('addColumn', array($table->getName(), $column->getName(), $column->getType()));
        $sql = sprintf('ALTER TABLE %s ADD %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );
        
        $this->execute($sql);
        $this->endCommandTimer();        
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $this->startCommandTimer();
        $sql = sprintf("SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS column_exists 
            FROM information_schema.columns 
            WHERE table_name ='%s' AND column_name = '%s'", $tableName,$columnName);                 
        $result = $this->fetchRow($sql);                
        if(!(bool)$result['column_exists']) {
            throw new \InvalidArgumentException(sprintf('The specified column doesn\'t exist: '. $columnName));
        }
        $this->writeCommand('renameColumn', array($tableName, $columnName, $newColumnName));
        $this->execute(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', 
            $this->quoteTableName($tableName), $this->quoteColumnName($columnName), $newColumnName));         
        $this->endCommandTimer();        
    }
    
    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        // TODO - is it possible to merge these 3 queries into less?
        $this->startCommandTimer();
        $this->writeCommand('changeColumn', array($tableName, $columnName, $newColumn->getType()));
        // change data type
        $sql = sprintf('ALTER TABLE %s ALTER COLUMN %s TYPE %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName),
            $this->getColumnSqlDefinition($newColumn));
        $sql = preg_replace('/ NOT NULL$/', '', $sql);
        $sql = preg_replace('/ NULL$/', '', $sql);
        $this->execute($sql);
        // process null
        $sql = sprintf('ALTER TABLE %s ALTER COLUMN %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName));
        if ($newColumn->isNull()) {
            $sql .= ' DROP NOT NULL';
        } else {
            $sql .= ' SET NOT NULL';
        }
        $this->execute($sql);
        // rename column
        if ($columnName !== $newColumn->getName()) {
        $this->execute(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName),
            $this->quoteColumnName($newColumn->getName())));
        }
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropColumn', array($tableName, $columnName));
        $this->execute(
            sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName)
            )
        );
        $this->endCommandTimer();
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
        $sql = "SELECT    
            i.relname AS index_name,
            a.attname AS column_name
        FROM
            pg_class t,
            pg_class i,
            pg_index ix,
            pg_attribute a
        WHERE
            t.oid = ix.indrelid
            AND i.oid = ix.indexrelid
            AND a.attrelid = t.oid
            AND a.attnum = ANY(ix.indkey)
            AND t.relkind = 'r'
            AND t.relname = '$tableName'            
        ORDER BY
            t.relname,
            i.relname;";
        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            if (!isset($indexes[$row['index_name']])) {
                $indexes[$row['index_name']] = array('columns' => array());
            }
            $indexes[$row['index_name']]['columns'][] = strtolower($row['column_name']);
        }
        return $indexes;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {   
        if (is_string($columns)) {
            $columns = array($columns);
        }
        $columns = array_map('strtolower', $columns);        
        $indexes = $this->getIndexes($tableName);                                        
        foreach ($indexes as $index) {
            if(array_diff($index['columns'], $columns) === array_diff($columns, $index['columns'])) {
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
        $this->startCommandTimer();
        $this->writeCommand('addIndex', array($table->getName(), $index->getColumns()));
        $sql = $this->getIndexSqlDefinition($index, $table->getName());
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {        
        $this->startCommandTimer();
        $this->writeCommand('dropIndex', array($tableName, $columns));
        $sql = sprintf('DROP INDEX IF EXISTS %s',
            $this->getIndexName($tableName, $columns));
        $this->execute($sql);  
        $this->endCommandTimer();                  
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        $foreignKeys = $this->getForeignKeys($tableName);
        if ($constraint) {
            if (isset($foreignKeys[$constraint])) {
                return !empty($foreignKeys[$constraint]);
            }
            return false;
        } else {
            foreach ($foreignKeys as $key) {
                $a = array_diff($columns, $key['columns']);
                if (empty($a)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $foreignKeys = array();
        $rows = $this->fetchAll(sprintf(
            "SELECT                
                    tc.constraint_name,
                    tc.table_name, kcu.column_name,
                    ccu.table_name AS referenced_table_name,
                    ccu.column_name AS referenced_column_name
                FROM
                    information_schema.table_constraints AS tc
                    JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
                    JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
                WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name = '%s'
                ORDER BY kcu.position_in_unique_constraint",
            $tableName
        ));
        foreach ($rows as $row) {
            $foreignKeys[$row['constraint_name']]['table'] = $row['table_name'];
            $foreignKeys[$row['constraint_name']]['columns'][] = $row['column_name'];
            $foreignKeys[$row['constraint_name']]['referenced_table'] = $row['referenced_table_name'];
            $foreignKeys[$row['constraint_name']]['referenced_columns'][] = $row['referenced_column_name'];
        }
        return $foreignKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $this->startCommandTimer();
        $this->writeCommand('addForeignKey', array($table->getName(), $foreignKey->getColumns()));
        $sql = sprintf('ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
            );        
        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        $this->startCommandTimer();
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }        
        $this->writeCommand('dropForeignKey', array($tableName, $columns));        

        if ($constraint) {
            $this->execute(
                sprintf('ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quoteTableName($tableName),
                    $constraint
                )
            );
        } else {
            foreach ($columns as $column) {
                $rows = $this->fetchAll(sprintf(
                        "SELECT CONSTRAINT_NAME
                          FROM information_schema.KEY_COLUMN_USAGE
                          WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                            AND TABLE_NAME = '%s'
                            AND COLUMN_NAME = '%s'
                          ORDER BY POSITION_IN_UNIQUE_CONSTRAINT",
                        $column,
                        $this->quoteTableName($tableName)

                ));
                foreach ($rows as $row) {
                    $this->dropForeignKey($tableName, $columns, $row['CONSTRAINT_NAME']);
                }
            }
        }
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSqlType($type)
    {
        switch ($type) {            
            case 'string':
                return array('name' => 'character varying', 'limit' => 255);
                break;
            case 'text':
                return array('name' => 'text');
                break;
            case 'integer':
                return array('name' => 'integer');
                break;
            case 'biginteger':
                return array('name' => 'bigint');
                break;
            case 'float':
                return array('name' => 'real');
                break;
            case 'decimal':
                return array('name' => 'decimal');
                break;           
             case 'datetime':
                return array('name' => 'timestamp');
                break;
            case 'timestamp':
                return array('name' => 'timestamp');
                break;
            case 'time':
                return array('name' => 'time');
                break;
            case 'date':
                return array('name' => 'date');
                break;
            case 'binary':
                return array('name' => 'bytea');
                break;
            case 'boolean':
                return array('name' => 'boolean');
                break;
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlType)
    {           
        switch ($sqlType) {
            case 'character varying':
            case 'varchar':
                return 'string';                
            case 'text':
                return 'text';
            case 'int':
            case 'int4':
            case 'integer':
                return 'integer';                
            case 'decimal':
            case 'numeric':
                return 'decimal';                
            case 'bigint':
            case 'int8':                
                return 'biginteger';        
            case 'real':
            case 'float4':
                return 'float';
            case 'bytea':
                return 'binary';
                break;
            case 'time':
            case 'timetz':
            case 'time with time zone':
            case 'time without time zone':
                return 'time';
            case 'date':
                return 'date';                
            case 'timestamp':
            case 'timestamptz':
            case 'timestamp with time zone':
            case 'timestamp without time zone':
                return 'datetime';           
            case 'bool':
            case 'boolean':
                return 'boolean';
            default:
                throw new \RuntimeException('The PostgreSQL type: "' . $sqlType . '" is not supported');
        }                   
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        $this->startCommandTimer();
        $this->writeCommand('createDatabase', array($name));
        $charset = isset($options['charset']) ? $options['charset'] : 'utf8';
        $this->execute(sprintf("CREATE DATABASE %s WITH ENCODING = '%s'", $name, $charset));
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasDatabase($databaseName)
    {
        $sql = sprintf("SELECT count(*) FROM pg_database WHERE datname = '%s'", $databaseName);        
        $result = $this->fetchRow($sql);
        return  $result['count'] > 0;        
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {        
        $this->startCommandTimer();
        $this->writeCommand('dropDatabase', array($name));
        $this->disconnect();        
        $this->execute(sprintf('DROP DATABASE IF EXISTS %s', $name));
        $this->connect();
        $this->endCommandTimer();
    }
    
    /**
     * Gets the PostgreSQL Column Definition for a Column object.
     *
     * @param Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $sqlType = $this->getSqlType($column->getType());
        $def = strtoupper($sqlType['name']);
        // integers cant have limits in postgres
        if ('integer' !== $sqlType['name']) {
            $def .= ($column->getLimit() || isset($sqlType['limit']))
                 ? '(' . ($column->getLimit() ? $column->getLimit() : $sqlType['limit']) . ')' : '';
        }
        $def .= ($column->isNull() == false) ? ' NOT NULL' : ' NULL';
        $def .= ($column->isIdentity()) ? '' : '';
        $default = $column->getDefault();
        if (is_numeric($default)) {
            $def .= ' DEFAULT ' . $column->getDefault();
        } else {
            $def .= is_null($column->getDefault()) ? '' : ' DEFAULT \'' . $column->getDefault() . '\'';
        }        
        // TODO - add precision & scale for decimals
        return $def;
    }
    
    /**
     * Gets the PostgreSQL Index Definition for an Index object.
     *
     * @param Index  $index Index
     * @param string $tableName Table name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {                
        $def = sprintf("CREATE %s INDEX %s ON %s (%s);",
            ($index->getType() == Index::UNIQUE ? 'UNIQUE' : ''),
            $this->getIndexName($tableName, $index->getColumns()),
            $this->quoteTableName($tableName),
            implode(',', $index->getColumns()));
        return $def;
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param ForeignKey $foreignKey
     * @param string     $tableName  Table name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $def = ' CONSTRAINT ';       
        $columnNames = array();
        foreach ($foreignKey->getColumns() as $column) {
            $columnNames[] = $column;
        }
        $def .= $tableName.'_'.implode('_', $columnNames);
        $def .= ' FOREIGN KEY (' . implode(',', $columnNames) . ')';
        $refColumnNames = array();
        foreach ($foreignKey->getReferencedColumns() as $column) {
            $refColumnNames[] = $column;
        }
        $def .= ' REFERENCES ' . $foreignKey->getReferencedTable()->getName() . ' (' . implode(',', $refColumnNames) . ')';
        if ($foreignKey->getOnDelete()) {
            $def .= ' ON DELETE ' . $foreignKey->getOnDelete();
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= ' ON UPDATE ' . $foreignKey->getOnUpdate();
        }        
        return $def;
    }

    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {        
        try {
            $options = array(
                'id' => false
            );
            
            $table = new \Phinx\Db\Table($this->getSchemaTableName(), $options, $this);
            $table->addColumn('version', 'biginteger')
                  ->addColumn('start_time', 'timestamp')
                  ->addColumn('end_time', 'timestamp')
                  ->save();
        } catch(\Exception $exception) {            
            throw new \InvalidArgumentException('There was a problem creating the schema table');
        }
    }

     /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strtolower($direction) == 'up') {
            // up
            $sql = sprintf(
                "INSERT INTO %s (version, start_time, end_time) VALUES ('%s', '%s', '%s');",
                $this->getSchemaTableName(),
                $migration->getVersion(),
                $startTime,
                $endTime
            );

            $this->query($sql);
        } else {
            // down
            $sql = sprintf("DELETE FROM %s WHERE version = '%s'",
                $this->getSchemaTableName(),
                $migration->getVersion()
            );
            
            $this->query($sql);
        }
        return $this;
    }

    /**
     * Creates the specified database table.
     *
     * @param  string $schemaName Schema Name
     * @return void
     */
    public function createSchema($schemaName = 'public')
    {
        $this->startCommandTimer();
        $this->writeCommand('addSchema', array($schemaName));
        $sql = sprintf('CREATE SCHEMA %s;', $this->quoteSchemaName($schemaName)); // from postgres 9.3 we can use "CREATE SCHEMA IF NOT EXISTS schema_name"
        $this->execute($sql);
        return $this->endCommandTimer(); 
    }

    /**
     * Checks to see if a schema exists.
     *
     * @param string $schemaName  Schema Name     
     * @return boolean
     */
    public function hasSchema($schemaName)
    {        
        $sql = sprintf("SELECT count(*) 
            FROM information_schema.schemata 
            WHERE schema_name = '%s'",
            $schemaName);
        $result = $this->fetchRow($sql);
        return  $result['count'] > 0;
    }

    /**
     * Drops the specified schema table.
     * 
     * @param string $tableName Table Name
     * @return void
     */
    public function dropSchema($schemaName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropSchema', array($schemaName));
        $sql = sprintf("DROP SCHEMA IF EXISTS %s CASCADE;", $this->quoteSchemaName($schemaName));
        $this->execute($sql);
        $this->endCommandTimer();     
    }    

    /**
     * Drops all schemas.
     *
     * @return void     
     */
    public function dropAllSchemas()
    {
        $this->startCommandTimer();
        $this->writeCommand('dropAllSchemas');
        foreach ($this->getAllSchemas() as $schema) {
            $this->dropSchema($schema);
        }
        $this->endCommandTimer();
    }

    /**
     * Returns schemas.
     *
     * @return array
     */
    public function getAllSchemas()
    {
        $sql = "SELECT schema_name
                FROM information_schema.schemata
                WHERE schema_name <> 'information_schema' AND schema_name !~ '^pg_'";
        $items = $this->fetchAll($sql);
        $schemaNames = array();
        foreach ($items as $item) {
            $schemaNames[] = $item['schema_name'];
        }        
        return $schemaNames;
    }

    /**
     * Returns index name.
     *
     * @param string       $tableName   Table name.
     * @param string|array $columnNames Column names.
     *
     * @return string
     */
    private function getIndexName($tableName, $columnNames)
    {
        if (is_string($columnNames)) {
            $columnNames = array($columnNames);
        }
        $indexName = sprintf('%s_%s', $tableName, implode('_', $columnNames));
        return $indexName;
    }
}
