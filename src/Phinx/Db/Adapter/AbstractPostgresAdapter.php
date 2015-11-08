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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\ForeignKey;
use Phinx\Migration\MigrationInterface;

abstract class AbstractPostgresAdapter extends PdoAdapter implements AdapterInterface
{

    const INT_SMALL   = 65535;
    /**
     * Columns with comments
     *
     * @var array
     */
    protected $columnsWithComments = array();

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
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: '
                    . $exception->getMessage()
                ));
            }

            $this->setConnection($db);
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
        return $this->quoteSchemaName($this->getSchemaName()) . '.' . $this->quoteColumnName($tableName);
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
        $result = $this->getConnection()->query(
            sprintf(
                'SELECT *
                FROM information_schema.tables
                WHERE table_schema = %s
                AND lower(table_name) = lower(%s)',
                $this->getConnection()->quote($this->getSchemaName()),
                $this->getConnection()->quote($tableName)
            )
        );

        return $result->rowCount() === 1;
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

        $this->columnsWithComments = array();
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';

            // set column comments, if needed
            if ($column->getComment()) {
                $this->columnsWithComments[] = $column;
            }
        }

         // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            $sql .= sprintf(' CONSTRAINT %s_pkey PRIMARY KEY (', $table->getName());
            if (is_string($options['primary_key'])) {       // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                // PHP 5.4 will allow access of $this, so we can call quoteColumnName() directly in the anonymous function,
                // but for now just hard-code the adapter quotes
                $sql .= implode(
                    ',',
                    array_map(
                        function ($v) {
                            return '"' . $v . '"';
                        },
                        $options['primary_key']
                    )
                );
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

        $sql .= ') ' . $this->getTableOptions($options) . ';';

        // process column comments
        if (!empty($this->columnsWithComments)) {
            foreach ($this->columnsWithComments as $column) {
                $sql .= $this->getColumnCommentSqlDefinition($column, $table->getName());
            }
        }


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
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($newTableName)
        );
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
        $sql = sprintf(
            $this->getColumnsQuery(),
            $tableName
        );
        $columnsInfo = $this->fetchAll($sql);

        foreach ($columnsInfo as $columnInfo) {
            $column = new Column();
            $column->setName($columnInfo['column_name'])
                   ->setType($this->getPhinxType($columnInfo['data_type']))
                   ->setNull($columnInfo['is_nullable'] == 'YES')
                   ->setDefault($columnInfo['column_default'])
                   ->setIdentity($columnInfo['is_identity'] == 'YES')
                   ->setPrecision($columnInfo['numeric_precision'])
                   ->setScale($columnInfo['numeric_scale']);

            if (preg_match('/\bwith time zone$/', $columnInfo['data_type'])) {
                $column->setTimezone(true);
            }

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
            WHERE table_schema = '%s' AND table_name = '%s' AND column_name = '%s'",
            $this->getSchemaName(),
            $tableName,
            $columnName
        );

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
        $sql = sprintf(
            'ALTER TABLE %s ADD %s %s',
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
        $sql = sprintf(
            "SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS column_exists
             FROM information_schema.columns
             WHERE table_name ='%s' AND column_name = '%s'",
            $tableName,
            $columnName
        );
        $result = $this->fetchRow($sql);
        if (!(bool) $result['column_exists']) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }
        $this->writeCommand('renameColumn', array($tableName, $columnName, $newColumnName));
        $this->execute(
            sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $newColumnName
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
            if (array_diff($index['columns'], $columns) === array_diff($columns, $index['columns'])) {
                return true;
            }
        }
        return false;
    }

     /**
      * {@inheritdoc}
      */
     public function hasIndexByName($tableName, $indexName)
     {
         $indexes = $this->getIndexes($tableName);
         foreach ($indexes as $name => $index) {
             if ($name == $indexName) {
                 return true;
             }
         }
         return false;
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
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $this->startCommandTimer();
        $this->writeCommand('addForeignKey', array($table->getName(), $foreignKey->getColumns()));
        $sql = sprintf(
            'ALTER TABLE %s ADD %s',
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
                sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $this->quoteTableName($tableName),
                    $constraint
                )
            );
        } else {
            foreach ($columns as $column) {
                $rows = $this->getForeignKeys($tableName, $column);

                foreach ($rows as $constraint => $row) {
                    $this->dropForeignKey($tableName, $columns, $constraint);
                }
            }
        }
        $this->endCommandTimer();
    }

    /**
     * Utility method to get types common to both Postgres & Redshift
     *
     * @param  string $type
     * @param  mixed $limit
     * @return array|bool
     */
    protected function getCommonSqlTypes($type, $limit = null)
    {
        switch ($type) {
            case static::PHINX_TYPE_INTEGER:
                if ($limit && $limit == static::INT_SMALL) {
                    return array(
                        'name' => 'smallint',
                        'limit' => static::INT_SMALL
                    );
                }
                return array('name' => $type);
            case static::PHINX_TYPE_TEXT:
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_BOOLEAN:
                return array('name' => $type);
            case static::PHINX_TYPE_DECIMAL:
                return array('name' => $type, 'precision' => 18, 'scale' => 0);
            case static::PHINX_TYPE_STRING:
                return array('name' => 'character varying', 'limit' => 255);
            case static::PHINX_TYPE_CHAR:
                return array('name' => 'character', 'limit' => 255);
            case static::PHINX_TYPE_BIG_INTEGER:
                return array('name' => 'bigint');
            case static::PHINX_TYPE_FLOAT:
                return array('name' => 'real');
            case static::PHINX_TYPE_DATETIME:
            case static::PHINX_TYPE_TIMESTAMP:
                return array('name' => 'timestamp');
        }

        return false;
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
                return static::PHINX_TYPE_STRING;
            case 'character':
            case 'char':
                return static::PHINX_TYPE_CHAR;
            case 'text':
                return static::PHINX_TYPE_TEXT;
            case 'json':
                return static::PHINX_TYPE_JSON;
            case 'jsonb':
                return static::PHINX_TYPE_JSONB;
            case 'smallint':
                return array(
                    'name' => 'smallint',
                    'limit' => static::INT_SMALL
                );
            case 'int':
            case 'int4':
            case 'integer':
                return static::PHINX_TYPE_INTEGER;
            case 'decimal':
            case 'numeric':
                return static::PHINX_TYPE_DECIMAL;
            case 'bigint':
            case 'int8':
                return static::PHINX_TYPE_BIG_INTEGER;
            case 'real':
            case 'float4':
                return static::PHINX_TYPE_FLOAT;
            case 'bytea':
                return static::PHINX_TYPE_BINARY;
                break;
            case 'time':
            case 'timetz':
            case 'time with time zone':
            case 'time without time zone':
                return static::PHINX_TYPE_TIME;
            case 'date':
                return static::PHINX_TYPE_DATE;
            case 'timestamp':
            case 'timestamptz':
            case 'timestamp with time zone':
            case 'timestamp without time zone':
                return static::PHINX_TYPE_DATETIME;
            case 'bool':
            case 'boolean':
                return static::PHINX_TYPE_BOOLEAN;
            case 'uuid':
                return static::PHINX_TYPE_UUID;
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
    public function createSchemaTable()
    {
        // Create the public/custom schema if it doesn't already exist
        if (false === $this->hasSchema($this->getSchemaName())) {
            $this->createSchema($this->getSchemaName());
        }

        $this->fetchAll(sprintf('SET search_path TO %s', $this->getSchemaName()));

        return parent::createSchemaTable();
    }

    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
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
            $sql = sprintf(
                "DELETE FROM %s WHERE version = '%s'",
                $this->getSchemaTableName(),
                $migration->getVersion()
            );

            $this->query($sql);
        }
        return $this;
    }

    /**
     * Creates the specified schema.
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
        $this->endCommandTimer();
    }

    /**
     * Checks to see if a schema exists.
     *
     * @param string $schemaName Schema Name
     * @return boolean
     */
    public function hasSchema($schemaName)
    {
        $sql = sprintf(
            "SELECT count(*)
             FROM pg_namespace
             WHERE nspname = '%s'",
            $schemaName
        );
        $result = $this->fetchRow($sql);
        return $result['count'] > 0;
    }

    /**
     * Drops the specified schema table.
     *
     * @param string $schemaName Schema name
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
     * Extra table options after the closing parenthesis in the table/columns definition
     *
     * @return string
     */
    protected function getTableOptions(array $options)
    {
        // NOOP
    }

    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table Name
     * @param string $columnName [optional] column name for which to fetch foreign keys
     * @return array
     */
    protected function getForeignKeys($tableName, $columnName=null)
    {
        $foreignKeys = array();
        $rows = $this->fetchAll(sprintf(
            "SELECT
              c.conname as constraint_name,
              cl.relname AS table_name,
              a.attname AS column_name,
              cl2.relname AS referenced_table_name,
              a2.attname AS referenced_column_name
            FROM pg_constraint c
            INNER JOIN pg_namespace n ON c.connamespace = n.oid
            INNER JOIN pg_class cl ON c.conrelid = cl.oid
            INNER JOIN pg_attribute a ON a.attnum = ANY(c.conkey) AND a.attrelid = c.conrelid
            INNER JOIN pg_attribute a2 ON a2.attnum = ANY(c.confkey) AND a2.attrelid = c.confrelid
            INNER JOIN pg_class cl2 ON cl2.oid = c.confrelid
            WHERE
              c.contype = 'f'
              and n.nspname in ('public')
              AND c.conrelid = '%s'::regclass"
            . ($columnName ? " AND a.attname = '%s'" : '')
            ." ORDER BY
              n.nspname ASC,
              cl.relname ASC,
              c.conname ASC",
            $tableName,
            $columnName
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
     * Get the defintion for a `DEFAULT` statement.
     *
     * @param  mixed $default
     * @return string
     */
    protected function getDefaultValueDefinition($default)
    {
        if (is_string($default) && 'CURRENT_TIMESTAMP' !== $default) {
            $default = $this->getConnection()->quote($default);
        } elseif (is_bool($default)) {
            $default = $default ? 'TRUE' : 'FALSE';
        }
        return isset($default) ? 'DEFAULT ' . $default : '';
    }

    /**
     * Gets the PostgreSQL Column Definition for a Column object.
     *
     * @param Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $buffer = array();
        if ($column->isIdentity()) {
            $buffer[] = $this->getIdentityType();
        } else {
            $sqlType = $this->getSqlType($column->getType(), $column->getLimit());
            $buffer[] = strtoupper($sqlType['name']);
            // integers cant have limits in postgres
            if (static::PHINX_TYPE_DECIMAL == $sqlType['name'] && ($column->getPrecision() || $column->getScale())) {
                $buffer[] = sprintf(
                    '(%s, %s)',
                    $column->getPrecision() ? $column->getPrecision() : $sqlType['precision'],
                    $column->getScale() ? $column->getScale() : $sqlType['scale']
                );
            } elseif (!in_array($sqlType['name'], array('integer', 'smallint'))) {
                if ($column->getLimit() || isset($sqlType['limit'])) {
                    $buffer[] = sprintf('(%s)', $column->getLimit() ? $column->getLimit() : $sqlType['limit']);
                }
            }

            $timeTypes = array(
                'time',
                'timestamp',
            );
            if (in_array($sqlType['name'], $timeTypes) && $column->isTimezone()) {
                $buffer[] = $this->getTimezoneDefinition();
            }
        }

        $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';

        if (!is_null($column->getDefault())) {
            $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
        }

        return implode(' ', $buffer);
    }

    /**
     * Gets the PostgreSQL Column Comment Defininition for a column object.
     *
     * @param Column $column Column
     * @param string $tableName Table name
     * @return string
     */
    protected function getColumnCommentSqlDefinition(Column $column, $tableName)
    {
        // passing 'null' is to remove column comment
        $comment = (strcasecmp($column->getComment(), 'NULL') !== 0)
                 ? $this->getConnection()->quote($column->getComment())
                 : 'NULL';

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s;',
            $tableName,
            $column->getName(),
            $comment
        );
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
        $constraintName = $foreignKey->getConstraint() ?: $tableName . '_' . implode('_', $foreignKey->getColumns());
        $def = ' CONSTRAINT "' . $constraintName . '" FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")';
        $def .= " REFERENCES {$foreignKey->getReferencedTable()->getName()} (\"" . implode('", "', $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }
        return $def;
    }

    /**
     * Gets the schema name.
     *
     * @return string
     */
    private function getSchemaName()
    {
        $options = $this->getOptions();
        return empty($options['schema']) ? 'public' : $options['schema'];
    }

    /**
     * Get the type definition for the identity field
     *
     * @return string
     */
    abstract protected function getIdentityType();

    /**
     * Get the definition for 'with timezone' fields
     *
     * @return string
     */
    abstract protected function getTimezoneDefinition();

    /**
     * The sql query used to fetch information about columns in the table
     *
     * @return string
     */
    abstract protected function getColumnsQuery();
}
