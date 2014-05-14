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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\ForeignKey;

/**
 * Phinx MySQL Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
class SqlServerAdapter extends PdoAdapter implements AdapterInterface
{

    protected $signedColumnTypes = array('integer' => true, 'biginteger' => true, 'float' => true, 'decimal' => true);

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if (null === $this->connection) {
            if (!class_exists('PDO') || !in_array('sqlsrv', \PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_SqlSrv extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }
            
            $db = null;
            $options = $this->getOptions();
            
            // if port is specified use it, otherwise use the SqlServer default
            if (empty($options['port'])) {
                $dsn = 'sqlsrv:server=' . $options['host'] . ';database=' . $options['name'];
            } else {
                $dsn = 'sqlsrv:server=' . $options['host'] . ',' . $options['port'] . ';database=' . $options['name'];
            }
            
            // charset support
            if (isset($options['charset'])) {
                $dsn .= ';charset=' . $options['charset'];
            }

            $driverOptions = array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION);

            // support arbitrary \PDO::SQLSRV_ATTR_* driver options and pass them to PDO
            // http://php.net/manual/en/ref.pdo-sqlsrv.php#pdo-sqlsrv.constants
            foreach ($options as $key => $option) {
                if (strpos($key, 'sqlsrv_attr_') === 0) {
                    $driverOptions[constant('\PDO::' . strtoupper($key))] = $option;
                }
            }

            try {
                $db = new \PDO($dsn, $options['user'], $options['pass'], $driverOptions);
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
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
    public function hasTransactions()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->execute('BEGIN TRANSACTION');
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
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return str_replace('.', '].[', $this->quoteColumnName($tableName));
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '[' . str_replace(']', '\]', $columnName) . ']';
    }

	/**
	 * {@inheritdoc}
	 */
	public function hasTable($tableName) {

		$result = $this->fetchRow(sprintf('SELECT count(*) as [count] FROM information_schema.tables WHERE table_name = \'%s\';', $tableName));

		return $result['count'] > 0;
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

        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
	    $sqlBuffer = array();
        foreach ($columns as $column) {
	        $sqlBuffer[] = $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column);

			// set column comments, if needed
	        if ($column->getComment()) {
		        $this->columnsWithComments[] = $column;
	        }
        }

	    // set the primary key(s)
	    if (isset($options['primary_key'])) {
		    $pkSql = sprintf('CONSTRAINT PK_%s PRIMARY KEY (', $table->getName());
		    if (is_string($options['primary_key'])) { // handle primary_key => 'id'
			    $pkSql .= $this->quoteColumnName($options['primary_key']);
		    } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
			    // PHP 5.4 will allow access of $this, so we can call quoteColumnName() directly in the anonymous function,
			    // but for now just hard-code the adapter quotes
			    $pkSql .= implode(
				    ',',
				    array_map(
					    function ($v) {
						    return '[' . $v . ']';
					    },
					    $options['primary_key']
				    )
			    );
		    }
		    $pkSql .= ')';
		    $sqlBuffer[] = $pkSql;
	    }

	    // set the foreign keys
	    $foreignKeys = $table->getForeignKeys();
	    if (!empty($foreignKeys)) {
		    foreach ($foreignKeys as $foreignKey) {
			    $sqlBuffer[] = $this->getForeignKeySqlDefinition($foreignKey, $table->getName());
		    }
	    }

	    $sql .= implode(', ', $sqlBuffer);
	    $sql .= ');';


	    // set the indexes
	    $indexes = $table->getIndexes();
	    if (!empty($indexes)) {
		    foreach ($indexes as $index) {
			    $sql .= $this->getIndexSqlDefinition($index, $table->getName());
		    }
	    }
var_dump($sql);
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
        $this->execute(sprintf('RENAME TABLE %s TO %s', $this->quoteTableName($tableName), $this->quoteTableName($newTableName)));
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
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $tableName));
        foreach ($rows as $columnInfo) {
            $column = new Column();
            $column->setName($columnInfo['Field'])
                   ->setType($columnInfo['Type'])
                   ->setNull($columnInfo['Null'] != 'NO')
                   ->setDefault($columnInfo['Default']);

            $phinxType = $this->getPhinxType($columnInfo['Type']);
            $column->setType($phinxType['name'])
                   ->setLimit($phinxType['limit']);

            if ($columnInfo['Extra'] == 'auto_increment') {
                $column->setIdentity(true);
            }

            $columns[] = $column;
        }

        return $columns;
    }
    
    /**
     * {@inheritdoc}
     */
	public function hasColumn($tableName, $columnName, $options = array()) {
		$sql = sprintf(
			"SELECT count(*) as [count]
			 FROM information_schema.columns
			 WHERE table_name = '%s' AND column_name = '%s'",
			$tableName,
			$columnName
		);
		$result = $this->fetchRow($sql);

		return $result['count'] > 0;
	}
    
    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $this->startCommandTimer();
        $sql = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );
        
        if ($column->getAfter()) {
            $sql .= ' AFTER ' . $this->quoteColumnName($column->getAfter());
        }
        
        $this->writeCommand('addColumn', array($table->getName(), $column->getName(), $column->getType()));
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $this->startCommandTimer();
        $rows = $this->fetchAll(sprintf('DESCRIBE %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            if (strtolower($row['Field']) == strtolower($columnName)) {
                $null = ($row['Null'] == 'NO') ? 'NOT NULL' : 'NULL';
                $extra = ' ' . strtoupper($row['Extra']);
                $definition = $row['Type'] . ' ' . $null . $extra;
        
                $this->writeCommand('renameColumn', array($tableName, $columnName, $newColumnName));
                $this->execute(
                    sprintf(
                        'ALTER TABLE %s CHANGE COLUMN %s %s %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($columnName),
                        $this->quoteColumnName($newColumnName),
                        $definition
                    )
                );
                $this->endCommandTimer();
                return;
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
        $this->startCommandTimer();
        $this->writeCommand('changeColumn', array($tableName, $columnName, $newColumn->getType()));
        $this->execute(
            sprintf(
                'ALTER TABLE %s CHANGE %s %s %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumn->getName()),
                $this->getColumnSqlDefinition($newColumn)
            )
        );
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
	protected function getIndexes($tableName) {
		$indexes = array();
		$sql = "SELECT OBJECT_SCHEMA_NAME(T.[object_id],DB_ID()) AS [Schema],
  T.[name] AS [table_name], I.[name] AS [index_name], AC.[name] AS [column_name]
FROM sys.[tables] AS T
  INNER JOIN sys.[indexes] I ON T.[object_id] = I.[object_id]
  INNER JOIN sys.[index_columns] IC ON I.[object_id] = IC.[object_id]
  INNER JOIN sys.[all_columns] AC ON T.[object_id] = AC.[object_id] AND IC.[column_id] = AC.[column_id]
WHERE T.[is_ms_shipped] = 0 AND I.[type_desc] <> 'HEAP'  AND T.[name] = N'{$tableName}'
ORDER BY T.[name], I.[index_id], IC.[key_ordinal];";

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
        $this->startCommandTimer();
        $this->writeCommand('addIndex', array($table->getName(), $index->getColumns()));
        $this->execute(
            sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getIndexSqlDefinition($index)
            )
        );
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        $this->startCommandTimer();
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        
        $this->writeCommand('dropIndex', array($tableName, $columns));
        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);
        
        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $this->execute(
                    sprintf(
                        'ALTER TABLE %s DROP INDEX %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($indexName)
                    )
                );
                $this->endCommandTimer();
                return;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $this->startCommandTimer();
        
        $this->writeCommand('dropIndexByName', array($tableName, $indexName));
        $indexes = $this->getIndexes($tableName);
        
        foreach ($indexes as $name => $index) {
            //$a = array_diff($columns, $index['columns']);
            if ($name === $indexName) {
                $this->execute(
                    sprintf(
                        'ALTER TABLE %s DROP INDEX %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($indexName)
                    )
                );
                $this->endCommandTimer();
                return;
            }
        }
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
            'SELECT
              CONSTRAINT_NAME,
              TABLE_NAME,
              COLUMN_NAME,
              REFERENCED_TABLE_NAME,
              REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_NAME = "%s"
            ORDER BY POSITION_IN_UNIQUE_CONSTRAINT',
            $tableName
        ));
        foreach ($rows as $row) {
            $foreignKeys[$row['CONSTRAINT_NAME']]['table'] = $row['TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['columns'][] = $row['COLUMN_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['referenced_table'] = $row['REFERENCED_TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['referenced_columns'][] = $row['REFERENCED_COLUMN_NAME'];
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
        $this->execute(
            sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getForeignKeySqlDefinition($foreignKey)
            )
        );
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
                sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quoteTableName($tableName),
                    $constraint
                )
            );
            $this->endCommandTimer();
            return;
        } else {
            foreach ($columns as $column) {
                $rows = $this->fetchAll(sprintf(
                    'SELECT
                        CONSTRAINT_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                        AND TABLE_NAME = "%s"
                        AND COLUMN_NAME = "%s"
                      ORDER BY POSITION_IN_UNIQUE_CONSTRAINT',
                    $tableName,
                    $column
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
            case static::PHINX_TYPE_STRING:
                return array('name' => 'nvarchar', 'limit' => 255);
                break;
            case static::PHINX_TYPE_TEXT:
                return array('name' => 'text');
                break;
            case static::PHINX_TYPE_INTEGER:
                return array('name' => 'int', 'limit' => 11);
                break;
            case static::PHINX_TYPE_BIG_INTEGER:
                return array('name' => 'bigint');
                break;
            case static::PHINX_TYPE_FLOAT:
                return array('name' => 'float');
                break;
            case static::PHINX_TYPE_DECIMAL:
                return array('name' => 'decimal');
                break;
            case static::PHINX_TYPE_DATETIME:
                return array('name' => 'datetime');
                break;
            case static::PHINX_TYPE_TIMESTAMP:
                return array('name' => 'datetime');
                break;
            case static::PHINX_TYPE_TIME:
                return array('name' => 'time');
                break;
            case static::PHINX_TYPE_DATE:
                return array('name' => 'date');
                break;
            case static::PHINX_TYPE_BINARY:
                return array('name' => 'blob');
                break;
            case static::PHINX_TYPE_BOOLEAN:
                return array('name' => 'tinyint', 'limit' => 1);
                break;
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param $sqlTypeDef
     * @throws \RuntimeException
     * @internal param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlTypeDef)
    {
        if (preg_match('/^([\w]+)(\(([\d]+)*(,([\d]+))*\))*$/', $sqlTypeDef, $matches) === false) {
            throw new \RuntimeException('Column type ' . $sqlTypeDef . ' is not supported');
        } else {
            $limit = null;
            $precision = null;
            $type = $matches[1];
            if (count($matches) > 2) {
                $limit = $matches[3] ? $matches[3] : null;
            }
            if (count($matches) > 4) {
                $precision = $matches[5];
            }
            switch ($matches[1]) {
                case 'varchar':
                    $type = static::PHINX_TYPE_STRING;
                    if ($limit == 255) {
                        $limit = null;
                    }
                    break;
                case 'int':
                    $type = static::PHINX_TYPE_INTEGER;
                    if ($limit == 11) {
                        $limit = null;
                    }
                    break;
                case 'bigint':
                    if ($limit == 20) {
                        $limit = null;
                    }
                    $type = static::PHINX_TYPE_BIG_INTEGER;
                    break;
                case 'blob':
                    $type = static::PHINX_TYPE_BINARY;
                    break;
            }
            if ($type == 'tinyint') {
                if ($matches[3] == 1) {
                    $type = static::PHINX_TYPE_BOOLEAN;
                    $limit = null;
                }
            }

            $this->getSqlType($type);

            return array(
                'name' => $type,
                'limit' => $limit,
                'precision' => $precision
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        $this->startCommandTimer();
        $this->writeCommand('createDatabase', array($name));

        if (isset($options['collation'])) {
            $this->execute(sprintf('CREATE DATABASE [%s] COLLATE [%s]', $name, $options['collation']));
        } else {
            $this->execute(sprintf('CREATE DATABASE [%s]', $name));
        }
	    $this->execute(sprintf('USE [%s]', $name));
        $this->endCommandTimer();
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
        $this->startCommandTimer();
        $this->writeCommand('dropDatabase', array($name));
	    $sql = <<<SQL
USE master;
IF EXISTS(select * from sys.databases where name=N'$name')
ALTER DATABASE [$name] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
DROP DATABASE [$name];
SQL;
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * Gets the SqlServer Column Definition for a Column object.
     *
     * @param Column $column Column
     * @return string
     */
	protected function getColumnSqlDefinition(Column $column) {
		$buffer = array();

		$sqlType = $this->getSqlType($column->getType());
		$buffer[] = strtoupper($sqlType['name']);
		// integers cant have limits in SQlServer
		if ('bigint' !== $sqlType['name'] && 'int' !== $sqlType['name'] && ($column->getLimit() || isset($sqlType['limit']))) {
			$buffer[] = sprintf('(%s)', $column->getLimit() ? $column->getLimit() : $sqlType['limit']);
		}

		$buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';
		$default = $column->getDefault();
		if (is_numeric($default) || 'CURRENT_TIMESTAMP' === $default) {
			$buffer[] = 'DEFAULT';
			$buffer[] = $default;
		} elseif ($default) {
			$buffer[] = "DEFAULT '{$default}'";
		}

		if ($column->isIdentity()) {
			$buffer[] = 'IDENTITY(1, 1)';
		}

		// TODO - add precision & scale for decimals
		return implode(' ', $buffer);
	}
    
    /**
     * Gets the SqlServer Index Definition for an Index object.
     *
     * @param Index $index Index
     * @return string
     */
	protected function getIndexSqlDefinition(Index $index, $tableName) {
		if (is_string($index->getName())) {
			$indexName = $index->getName();
		} else {
			$columnNames = $index->getColumns();
			if (is_string($columnNames)) {
				$columnNames = array($columnNames);
			}
			$indexName = sprintf('%s_%s', $tableName, implode('_', $columnNames));
		}
		$def = sprintf(
			"CREATE %s INDEX %s ON %s (%s);",
			($index->getType() == Index::UNIQUE ? 'UNIQUE' : ''),
			$indexName,
			$this->quoteTableName($tableName),
			'[' . implode('],[', $index->getColumns()) . ']'
		);

		return $def;
	}

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param ForeignKey $foreignKey
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey)
    {
        $def = '';
        if ($foreignKey->getConstraint()) {
            $def .= ' CONSTRAINT ' . $this->quoteColumnName($foreignKey->getConstraint());
        } else {
            $columnNames = array();
            foreach ($foreignKey->getColumns() as $column) {
                $columnNames[] = $this->quoteColumnName($column);
            }
            $def .= ' FOREIGN KEY (' . implode(',', $columnNames) . ')';
            $refColumnNames = array();
            foreach ($foreignKey->getReferencedColumns() as $column) {
                $refColumnNames[] = $this->quoteColumnName($column);
            }
            $def .= ' REFERENCES ' . $this->quoteTableName($foreignKey->getReferencedTable()->getName()) . ' (' . implode(',', $refColumnNames) . ')';
            if ($foreignKey->getOnDelete()) {
                $def .= ' ON DELETE ' . $foreignKey->getOnDelete();
            }
            if ($foreignKey->getOnUpdate()) {
                $def .= ' ON UPDATE ' . $foreignKey->getOnUpdate();
            }
        }
        return $def;
    }
}
