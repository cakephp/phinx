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
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Migration\MigrationInterface;

/**
 * Phinx Oracle Adapter.
 *
 * @author Felipe Maia <felipepqm@gmail.com>
 */
class OracleAdapter extends PdoAdapter implements AdapterInterface
{
    protected $schema = 'dbo';

    protected $signedColumnTypes = ['integer' => true, 'biginteger' => true, 'float' => true, 'decimal' => true];

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if ($this->connection === null) {
            if (!extension_loaded('pdo_oci')) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_OCI extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }

            $options = $this->getOptions();

            // if port is specified use it, otherwise use the Oracle default
            if (empty($options['port'])) {
                $dsn = "oci:dbname=//" . $options['host'] . "/" . $options['sid'] . "";
            } else {
                $dsn = "oci:dbname=//" . $options['host'] . ":" . $options['port'] . "/" . $options['sid'] . "";
            }

            $driverOptions = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

            try {
                $db = new \PDO($dsn, $options['user'], $options['pass'], $driverOptions);
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
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
     * @codeCoverageIgnore
     * {@inheritdoc}
     */
    public function hasTransactions()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function beginTransaction()
    {
//        $this->execute('BEGIN TRANSACTION');
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function commitTransaction()
    {
//        $this->execute('COMMIT TRANSACTION');
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function rollbackTransaction()
    {
//        $this->execute('ROLLBACK TRANSACTION');
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
        return '"' . str_replace(']', '"', $columnName) . '"';
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $result = $this->fetchRow(
            sprintf(
                'SELECT count(*) as count FROM ALL_TABLES WHERE table_name = \'%s\'',
                $tableName
            )
        );

        return $result['COUNT'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table)
    {
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
        $sqlBuffer = [];
        $columnsWithComments = [];

        foreach ($columns as $column) {
            $sqlBuffer[] = $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column);

            // set column comments, if needed
            if ($column->getComment()) {
                $columnsWithComments[] = $column;
            }
        }

        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $pkSql = sprintf('CONSTRAINT PK_%s PRIMARY KEY (', substr($table->getName(), 0, 28));
            if (is_string($options['primary_key'])) { // handle primary_key => 'id'
                $pkSql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                $pkSql .= implode(',', array_map([$this, 'quoteColumnName'], $options['primary_key']));
            }
            $pkSql .= ')';
            $sqlBuffer[] = $pkSql;
        }

        // set the foreign keys
        $foreignKeys = $table->getForeignKeys();
        foreach ($foreignKeys as $key => $foreignKey) {
            $sqlBuffer[] = $this->getForeignKeySqlDefinition($foreignKey, $table->getName());
        }

        $sql .= implode(', ', $sqlBuffer);
        $sql .= ')';

        $this->execute($sql);
        // process column comments
        foreach ($columnsWithComments as $key => $column) {
            $sql = $this->getColumnCommentSqlDefinition($column, $table->getName());
            $this->execute($sql);
        }
        // set the indexes
        $indexes = $table->getIndexes();

        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql = $this->getIndexSqlDefinition($index, $table->getName());
                $this->execute($sql);
            }
        }

        if (!$this->hasSequence($table->getName())) {
            $sql = "CREATE SEQUENCE SQ_" . $table->getName() . " MINVALUE 1 MAXVALUE 99999999999999999 INCREMENT BY 1";
            $this->execute($sql);
        }
    }

    /**
     * Verify if the table has a Sequence for primary Key
     *
     * @param string $tableName Table name
     *
     * @return bool
     */
    public function hasSequence($tableName)
    {
        $sql = sprintf(
            "SELECT COUNT(*) as COUNT FROM user_sequences WHERE sequence_name = '%s'",
            strtoupper("SQ_" . $tableName)
        );
        $result = $this->fetchRow($sql);

        return $result['COUNT'] > 0;
    }

    /**
     * Gets the Oracle Column Comment Defininition for a column object.
     *
     * @param \Phinx\Db\Table\Column $column    Column
     * @param string $tableName Table name
     *
     * @return string
     */
    protected function getColumnCommentSqlDefinition(Column $column, $tableName)
    {
        $comment = (strcasecmp($column->getComment(), 'NULL') !== 0) ? $column->getComment() : '';

        return sprintf(
            "COMMENT ON COLUMN \"%s\".\"%s\" IS '%s'",
            $tableName,
            $column->getName(),
            str_replace("'", "", $comment)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $this->execute(sprintf('alter table "%s" rename to "%s"', $tableName, $newTableName));

        if (!$this->hasSequence("SQ_" . strtoupper($newTableName))) {
            $this->renameSequence("SQ_" . strtoupper($tableName), "SQ_" . strtoupper($newTableName));
        }
    }

    /**
     * Rename an Oracle Sequence Object.
     *
     * @param string $sequenceName Old Sequence Name
     * @param string $newSequenceName New Sequence Name
     *
     * @return void
     */
    public function renameSequence($sequenceName, $newSequenceName)
    {
        $this->execute(sprintf('rename "%s" to "%s"', $sequenceName, $newSequenceName));
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $this->execute(sprintf('DROP TABLE %s', $this->quoteTableName($tableName)));
        $this->execute(sprintf('DROP SEQUENCE %s', $this->quoteTableName(strtoupper("SQ_" . $tableName))));
    }

    /**
     * {@inheritdoc}
     */
    public function truncateTable($tableName)
    {
        $sql = sprintf(
            'TRUNCATE TABLE %s',
            $this->quoteTableName($tableName)
        );

        $this->execute($sql);
    }

    /**
     * Get the comment for a column
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     *
     * @return string
     */
    public function getColumnComment($tableName, $columnName)
    {
        $sql = sprintf(
            "select COMMENTS from ALL_COL_COMMENTS WHERE COLUMN_NAME = '%s' and TABLE_NAME = '%s'",
            $columnName,
            $tableName
        );
        $row = $this->fetchRow($sql);

        return $row['COMMENTS'];
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $columns = [];

        $sql = sprintf(
            "select TABLE_NAME \"TABLE_NAME\", COLUMN_NAME \"NAME\", DATA_TYPE \"TYPE\", NULLABLE \"NULL\", 
            DATA_DEFAULT \"DEFAULT\", DATA_LENGTH \"CHAR_LENGTH\", DATA_PRECISION \"PRECISION\", DATA_SCALE \"SCALE\", 
            COLUMN_ID \"ORDINAL_POSITION\" FROM ALL_TAB_COLUMNS WHERE table_name = '%s'",
            $tableName
        );

        $rows = $this->fetchAll($sql);

        foreach ($rows as $columnInfo) {
            $default = null;
            if (trim($columnInfo['DEFAULT']) != 'NULL') {
                $default = trim($columnInfo['DEFAULT']);
            }

            $column = new Column();
            $column->setName($columnInfo['NAME'])
                ->setType($this->getPhinxType($columnInfo['TYPE'], $columnInfo['PRECISION']))
                ->setNull($columnInfo['NULL'] !== 'N')
                ->setDefault($default)
                ->setComment($this->getColumnComment($columnInfo['TABLE_NAME'], $columnInfo['NAME']));

            if (!empty($columnInfo['CHAR_LENGTH'])) {
                $column->setLimit($columnInfo['CHAR_LENGTH']);
            }

            $columns[$columnInfo['NAME']] = $column;
        }

        return $columns;
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName)
    {
        $sql = sprintf(
            "select count(*) as count from ALL_TAB_COLUMNS 
            where table_name = '%s' and column_name = '%s'",
            $tableName,
            $columnName
        );

        $result = $this->fetchRow($sql);

        return $result['COUNT'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $sql = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        if (!$this->hasColumn($tableName, $columnName)) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $this->execute(
            sprintf(
                "alter table \"%s\" rename column \"%s\" TO \"%s\"",
                $tableName,
                $columnName,
                $newColumnName
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $columns = $this->getColumns($tableName);

        if ($columnName !== $newColumn->getName()) {
            $this->renameColumn($tableName, $columnName, $newColumn->getName());
        }

        $setNullSql = ($newColumn->isNull() == $columns[$columnName]->isNull() ? false : true);

        $this->execute(
            sprintf(
                'ALTER TABLE %s MODIFY(%s %s)',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($newColumn->getName()),
                $this->getColumnSqlDefinition($newColumn, $setNullSql)
            )
        );
        // change column comment if needed
        if ($newColumn->getComment()) {
            $sql = $this->getColumnCommentSqlDefinition($newColumn, $tableName);
            $this->execute($sql);
        }
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
    public function getIndexes($tableName)
    {
        $indexes = [];
        $sql = "SELECT index_owner as owner,index_name,column_name FROM ALL_IND_COLUMNS 
                WHERE TABLE_NAME = '$tableName'";

        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            if (!isset($indexes[$row['INDEX_NAME']])) {
                $indexes[$row['INDEX_NAME']] = ['columns' => []];
            }
            $indexes[$row['INDEX_NAME']]['columns'][] = strtoupper($row['COLUMN_NAME']);
        }

        return $indexes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

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
    public function hasIndexByName($tableName, $indexName)
    {
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
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
        $sql = $this->getIndexSqlDefinition($index, $table->getName());
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtoupper', $columns);

        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $this->execute(
                    sprintf(
                        'DROP INDEX %s',
                        $this->quoteColumnName($indexName)
                    )
                );

                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                $this->execute(
                    sprintf(
                        'DROP INDEX %s',
                        $this->quoteColumnName($indexName)
                    )
                );

                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }
        $foreignKeys = $this->getForeignKeys($tableName);

        if ($constraint) {
            if (isset($foreignKeys[$constraint])) {
                return !empty($foreignKeys[$constraint]);
            }

            return false;
        } else {
            foreach ($foreignKeys as $key) {
                $a = array_diff($columns, $key['COLUMNS']);
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
     * @param string $type Type of Constraint Type (R, P)
     * @return array
     */
    protected function getForeignKeys($tableName, $type = 'R')
    {
        $foreignKeys = [];
        $rows = $this->fetchAll(sprintf(
            "SELECT a.CONSTRAINT_NAME, a.TABLE_NAME, b.COLUMN_NAME, 
                    (SELECT c.TABLE_NAME from ALL_CONS_COLUMNS c 
                    WHERE c.CONSTRAINT_NAME = a.R_CONSTRAINT_NAME) referenced_table_name,
                    (SELECT c.COLUMN_NAME from ALL_CONS_COLUMNS c 
                    WHERE c.CONSTRAINT_NAME = a.R_CONSTRAINT_NAME) referenced_column_name
                    FROM all_constraints a JOIN ALL_CONS_COLUMNS b ON a.CONSTRAINT_NAME = b.CONSTRAINT_NAME
                    WHERE a.table_name = '%s'
                    AND CONSTRAINT_TYPE = '%s'",
            $tableName,
            $type
        ));

        foreach ($rows as $row) {
            $foreignKeys[$row['CONSTRAINT_NAME']]['TABLE'] = $row['TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['COLUMNS'][] = $row['COLUMN_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['REFERENCED_TABLE'] = $row['REFERENCED_TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['REFERENCED_COLUMNS'][] = $row['REFERENCED_COLUMN_NAME'];
        }

        return $foreignKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $this->execute(
            sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        if ($constraint) {
            $this->execute(
                sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $this->quoteTableName($tableName),
                    $constraint
                )
            );

            return;
        } else {
            foreach ($columns as $column) {
                $rows = $this->fetchAll(sprintf(
                    "SELECT a.CONSTRAINT_NAME, a.TABLE_NAME, b.COLUMN_NAME, 
                    (SELECT c.TABLE_NAME from ALL_CONS_COLUMNS c 
                    WHERE c.CONSTRAINT_NAME = a.R_CONSTRAINT_NAME) referenced_table_name,
                    (SELECT c.COLUMN_NAME from ALL_CONS_COLUMNS c 
                    WHERE c.CONSTRAINT_NAME = a.R_CONSTRAINT_NAME) referenced_column_name
                    FROM all_constraints a JOIN ALL_CONS_COLUMNS b ON a.CONSTRAINT_NAME = b.CONSTRAINT_NAME
                    WHERE a.table_name = '%s'
                    AND CONSTRAINT_TYPE = 'R'
                    AND COLUMN_NAME = '%s'",
                    $tableName,
                    $column
                ));
                foreach ($rows as $row) {
                    $this->dropForeignKey($tableName, $columns, $row['CONSTRAINT_NAME']);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
//      reference: https://docs.oracle.com/cd/B19306_01/gateways.102/b14270/apa.htm
        switch ($type) {
            case static::PHINX_TYPE_STRING:
                return ['name' => 'VARCHAR2', 'limit' => 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'CHAR', 'limit' => 255];
            case static::PHINX_TYPE_TEXT:
                return ['name' => 'LONG'];
            case static::PHINX_TYPE_INTEGER:
                return ['name' => 'NUMBER', 'precision' => 10];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'NUMBER', 'precision' => 19];
            case static::PHINX_TYPE_FLOAT:
                return ['name' => 'FLOAT', 'precision' => 49];
            case static::PHINX_TYPE_DECIMAL:
                return ['name' => 'NUMBER'];
            case static::PHINX_TYPE_DATETIME:
                return ['name' => 'DATE'];
            case static::PHINX_TYPE_TIMESTAMP:
                return ['name' => 'TIMESTAMP'];
            case static::PHINX_TYPE_TIME:
                return ['name' => 'time'];
            case static::PHINX_TYPE_DATE:
                return ['name' => 'DATE'];
            case static::PHINX_TYPE_BLOB:
                return ['name' => 'BLOB'];
            case 'CLOB':
                return ['name' => 'CLOB'];
            case static::PHINX_TYPE_BINARY:
                return ['name' => 'RAW', 'limit' => 2000];
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'NUMBER', 'precision' => 1];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'RAW', 'precision' => 16, 'default' => 'SYS_GUID()', 'limit' => 2000];
            case static::PHINX_TYPE_FILESTREAM:
                return ['name' => 'varbinary', 'limit' => 'max'];
            // Geospatial database types
            case static::PHINX_TYPE_GEOMETRY:
            case static::PHINX_TYPE_POINT:
            case static::PHINX_TYPE_LINESTRING:
            case static::PHINX_TYPE_POLYGON:
                // SQL Server stores all spatial data using a single data type.
                // Specific types (point, polygon, etc) are set at insert time.
                return ['name' => 'geography'];
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL Type definition
     * @param int $precision Precision of NUMBER type to define Phinx Type.
     * @throws \RuntimeException
     * @internal param string $sqlType SQL type
     * @return string Phinx type
     */
    public function getPhinxType($sqlType, $precision = null)
    {
        if ($sqlType === 'VARCHAR2') {
            return static::PHINX_TYPE_STRING;
        } elseif ($sqlType === 'CHAR') {
            return static::PHINX_TYPE_CHAR;
        } elseif ($sqlType == 'LONG') {
            return static::PHINX_TYPE_TEXT;
        } elseif ($sqlType === 'NUMBER' && $precision === 10) {
            return static::PHINX_TYPE_INTEGER;
        } elseif ($sqlType === 'NUMBER' && $precision === 19) {
            return static::PHINX_TYPE_BIG_INTEGER;
        } elseif ($sqlType === 'FLOAT') {
            return static::PHINX_TYPE_FLOAT;
        } elseif ($sqlType === 'TIMESTAMP(6)') {
            return static::PHINX_TYPE_TIMESTAMP;
        } elseif ($sqlType === 'TIME') {
            return static::PHINX_TYPE_TIME;
        } elseif ($sqlType === 'DATE') {
            return static::PHINX_TYPE_DATE;
        } elseif ($sqlType === 'BLOB') {
            return static::PHINX_TYPE_BLOB;
        } elseif ($sqlType === 'CLOB') {
            return 'CLOB';
        } elseif ($sqlType === 'RAW' && $precision === 16) {
            return static::PHINX_TYPE_UUID;
        } elseif ($sqlType === 'RAW') {
            return static::PHINX_TYPE_BLOB;
        } elseif ($sqlType === 'NUMBER' && $precision === 1) {
            return static::PHINX_TYPE_BOOLEAN;
        } elseif ($sqlType === 'NUMBER') {
            return static::PHINX_TYPE_DECIMAL;
        } else {
            throw new \RuntimeException('The Oracle type: "' . $sqlType . '" is not supported');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = [])
    {
        if (isset($options['collation'])) {
            $this->execute(sprintf('CREATE DATABASE [%s] COLLATE [%s]', $name, $options['collation']));
        } else {
            $this->execute(sprintf('CREATE DATABASE [%s]', $name));
        }
        $this->execute(sprintf('USE [%s]', $name));
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        $result = $this->fetchRow(
            sprintf(
                'SELECT count(*) as [count] FROM master.dbo.sysdatabases WHERE [name] = \'%s\'',
                $name
            )
        );

        return $result['count'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $sql = <<<SQL
USE master;
IF EXISTS(select * from sys.databases where name=N'$name')
ALTER DATABASE [$name] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
DROP DATABASE [$name];
SQL;
        $this->execute($sql);
    }

    /**
     * Get the defintion for a `DEFAULT` statement.
     *
     * @param  mixed $default Default value for column
     * @return string
     */
    protected function getDefaultValueDefinition($default)
    {
        if (is_string($default) && 'CURRENT_TIMESTAMP' !== $default && 'SYSDATE' !== $default) {
            $default = $this->getConnection()->quote($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        }

        return isset($default) ? ' DEFAULT ' . $default : 'DEFAULT NULL';
    }

    /**
     * Gets the Oracle Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @param bool $setNullSql Set column nullable
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column, $setNullSql = true)
    {
        $buffer = [];

        $sqlType = $this->getSqlType($column->getType());

        $buffer[] = strtoupper($sqlType['name']);
        // integers cant have limits in Oracle
        $noLimits = [
            static::PHINX_TYPE_INTEGER,
            static::PHINX_TYPE_BIG_INTEGER,
            static::PHINX_TYPE_FLOAT,
            static::PHINX_TYPE_UUID,
            static::PHINX_TYPE_BOOLEAN
        ];
        if (!in_array($column->getType(), $noLimits) && ($column->getLimit() || isset($sqlType['limit']))) {
            $buffer[] = sprintf('(%s)', $column->getLimit() ?: $sqlType['limit']);
        }
        if ($column->getPrecision() && $column->getScale()) {
            $buffer[] = '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        }

        if ($column->getDefault() === null && $column->isNull()) {
            $buffer[] = ' DEFAULT NULL';
        } else {
            $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
        }

        if ($setNullSql) {
            $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';
        }

        return implode(' ', $buffer);
    }

    /**
     * Gets the Oracle Index Definition for an Index object.
     *
     * @param \Phinx\Db\Table\Index $index Index
     * @param string $tableName Table Name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {
        if (is_string($index->getName())) {
            $indexName = $index->getName();
        } else {
            $columnNames = $index->getColumns();
            if (is_string($columnNames)) {
                $columnNames = [$columnNames];
            }
            $indexName = sprintf('%s_%s', $tableName, implode('_', $columnNames));
        }
        $def = sprintf(
            "CREATE %s INDEX %s ON %s (%s)",
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $indexName,
            $this->quoteTableName($tableName),
            '"' . implode('","', $index->getColumns()) . '"'
        );

        return $def;
    }

    /**
     * Gets the Oracle Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey Foreign Key Object
     * @param string $tableName Table Name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $constraintName = $foreignKey->getConstraint() ?: $tableName . '_' . implode('_', $foreignKey->getColumns());
        $def = ' CONSTRAINT ' . $this->quoteColumnName(substr($constraintName, 0, 27));
        $def .= ' FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")';
        $def .= " REFERENCES {$this->quoteTableName($foreignKey->getReferencedTable()->getName())} 
        (\"" . implode('", "', $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete() && $foreignKey->getOnDelete() != "NO ACTION") {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        return $def;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array_merge(parent::getColumnTypes(), ['filestream']);
    }

    /**
     * Records a migration being run.
     *
     * @param \Phinx\Migration\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param int $startTime Start Time
     * @param int $endTime End Time
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function migrated(\Phinx\Migration\MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        $startTime = "TO_TIMESTAMP('$startTime', 'YYYY-MM-DD HH24:MI:SS')";
        $endTime = "TO_TIMESTAMP('$endTime', 'YYYY-MM-DD HH24:MI:SS')";

        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                "INSERT INTO \"%s\" (%s, %s, %s, %s, %s) VALUES ('%s', '%s', %s, %s, %s)",
                $this->getSchemaTableName(),
                $this->quoteColumnName('version'),
                $this->quoteColumnName('migration_name'),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('end_time'),
                $this->quoteColumnName('breakpoint'),
                $migration->getVersion(),
                substr($migration->getName(), 0, 100),
                $startTime,
                $endTime,
                $this->castToBool(false)
            );

            $this->execute($sql);
        } else {
            // down
            $sql = sprintf(
                "DELETE FROM \"%s\" WHERE %s = '%s'",
                $this->getSchemaTableName(),
                $this->quoteColumnName('version'),
                $migration->getVersion()
            );

            $this->execute($sql);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function bulkinsert(Table $table, $rows)
    {
        $sql = "INSERT ALL ";

        $vals = [];
        $tableName = $table->getName();
        $primaryKeyColumn = current($this->getForeignKeys($tableName, 'P'));
        $sequenceNextVal = $this->getNextValSequence('SQ_' . $tableName);
//        buscar sequence e primary key padr�o para incrementar PK com a SEQUENCE.NEXTVAL

        foreach ($rows as $key => $row) {
            $pk = ($sequenceNextVal + $key);
            $row[$primaryKeyColumn['COLUMNS'][0]] = (int)$pk;

            $sql .= sprintf(
                "INTO %s ",
                $this->quoteTableName($tableName)
            );

            $keys = array_keys($row);
            $sql .= "(" . implode(', ', array_map([$this, 'quoteColumnName'], $keys)) . ") VALUES";

            foreach ($row as $v) {
                $vals[] = $v;
            }

            $count_keys = count($keys);
            $query = " (" . implode(', ', array_fill(0, $count_keys, '?')) . ") ";

            $queries = array_fill(0, 1, $query);
            $sql .= implode(',', $queries);
        }
        $sql .= "SELECT 1 FROM DUAL";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($vals);
    }

    /**
     * Get Next Auto Increment Value Sequence
     *
     *
     * @param string $sequence Sequence Name
     * @return int
     */
    protected function getNextValSequence($sequence)
    {
        $sql = "SELECT %s.NEXTVAL FROM DUAL";
        $rows = $this->fetchAll(sprintf($sql, $sequence));

        return $rows[0]['NEXTVAL'];
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionLog()
    {
        $result = [];

        switch ($this->options['version_order']) {
            case \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME:
                $orderBy = '"version" ASC';
                break;
            case \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME:
                $orderBy = '"start_time" ASC, "version" ASC';
                break;
            default:
                throw new \RuntimeException('Invalid version_order configuration option');
        }

        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->quoteColumnName(
            $this->getSchemaTableName()
        ), $orderBy));
        foreach ($rows as $version) {
            $result[$version['version']] = $version;
        }

        return $result;
    }
}
