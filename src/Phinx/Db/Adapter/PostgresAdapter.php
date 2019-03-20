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

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres as PostgresDriver;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Util\Literal;

class PostgresAdapter extends PdoAdapter implements AdapterInterface
{
    /**
     * Columns with comments
     *
     * @var array
     */
    protected $columnsWithComments = [];

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if ($this->connection === null) {
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
                $db = new \PDO($dsn, $options['user'], $options['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
                ), $exception->getCode(), $exception);
            }

            try {
                if (isset($options['schema'])) {
                    $db->exec('SET search_path TO ' . $options['schema']);
                }
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(
                    sprintf('Schema does not exists: %s', $options['schema']),
                    $exception->getCode(),
                    $exception
                );
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
        $parts = $this->getSchemaName($tableName);

        return $this->quoteSchemaName($parts['schema']) . '.' . $this->quoteColumnName($parts['table']);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '"' . $columnName . '"';
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $result = $this->getConnection()->query(
            sprintf(
                'SELECT *
                FROM information_schema.tables
                WHERE table_schema = %s
                AND table_name = %s',
                $this->getConnection()->quote($parts['schema']),
                $this->getConnection()->quote($parts['table'])
            )
        );

        return $result->rowCount() === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $options = $table->getOptions();
        $parts = $this->getSchemaName($table->getName());

         // Add the default primary key
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $options['id'] = 'id';
        }

        if (isset($options['id']) && is_string($options['id'])) {
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

        $this->columnsWithComments = [];
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
            $sql .= sprintf(' CONSTRAINT %s PRIMARY KEY (', $this->quoteColumnName($parts['table'] . '_pkey'));
            if (is_string($options['primary_key'])) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $options['primary_key']));
            }
            $sql .= ')';
        } else {
            $sql = rtrim($sql, ', '); // no primary keys
        }

        $sql .= ');';

        // process column comments
        if (!empty($this->columnsWithComments)) {
            foreach ($this->columnsWithComments as $column) {
                $sql .= $this->getColumnCommentSqlDefinition($column, $table->getName());
            }
        }

        // set the indexes
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql .= $this->getIndexSqlDefinition($index, $table->getName());
            }
        }

        // execute the sql
        $this->execute($sql);

        // process table comments
        if (isset($options['comment'])) {
            $sql = sprintf(
                'COMMENT ON TABLE %s IS %s',
                $this->quoteTableName($table->getName()),
                $this->getConnection()->quote($options['comment'])
            );
            $this->execute($sql);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangePrimaryKeyInstructions(Table $table, $newColumns)
    {
        $parts = $this->getSchemaName($table->getName());

        $instructions = new AlterInstructions();

        // Drop the existing primary key
        $primaryKey = $this->getPrimaryKey($table->getName());
        if (!empty($primaryKey['constraint'])) {
            $sql = sprintf(
                'DROP CONSTRAINT %s',
                $this->quoteColumnName($primaryKey['constraint'])
            );
            $instructions->addAlter($sql);
        }

        // Add the new primary key
        if (!empty($newColumns)) {
            $sql = sprintf(
                'ADD CONSTRAINT %s PRIMARY KEY (',
                $this->quoteColumnName($parts['table'] . '_pkey')
            );
            if (is_string($newColumns)) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($newColumns);
            } elseif (is_array($newColumns)) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $newColumns));
            } else {
                throw new \InvalidArgumentException(sprintf(
                    "Invalid value for primary key: %s",
                    json_encode($newColumns)
                ));
            }
            $sql .= ')';
            $instructions->addAlter($sql);
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangeCommentInstructions(Table $table, $newComment)
    {
        $instructions = new AlterInstructions();

        // passing 'null' is to remove table comment
        $newComment = ($newComment !== null)
            ? $this->getConnection()->quote($newComment)
            : 'NULL';
        $sql = sprintf(
            'COMMENT ON TABLE %s IS %s',
            $this->quoteTableName($table->getName()),
            $newComment
        );
        $instructions->addPostStep($sql);

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameTableInstructions($tableName, $newTableName)
    {
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($newTableName)
        );

        return new AlterInstructions([], [$sql]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropTableInstructions($tableName)
    {
        $sql = sprintf('DROP TABLE %s', $this->quoteTableName($tableName));

        return new AlterInstructions([], [$sql]);
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
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $columns = [];
        $sql = sprintf(
            "SELECT column_name, data_type, udt_name, is_identity, is_nullable,
             column_default, character_maximum_length, numeric_precision, numeric_scale,
             datetime_precision
             FROM information_schema.columns
             WHERE table_schema = %s AND table_name = %s",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table'])
        );
        $columnsInfo = $this->fetchAll($sql);

        foreach ($columnsInfo as $columnInfo) {
            $isUserDefined = strtoupper(trim($columnInfo['data_type'])) === 'USER-DEFINED';

            if ($isUserDefined) {
                $columnType = Literal::from($columnInfo['udt_name']);
            } else {
                $columnType = $this->getPhinxType($columnInfo['data_type']);
            }

            // If the default value begins with a ' or looks like a function mark it as literal
            if (isset($columnInfo['column_default'][0]) && $columnInfo['column_default'][0] === "'") {
                if (preg_match('/^\'(.*)\'::[^:]+$/', $columnInfo['column_default'], $match)) {
                    // '' and \' are replaced with a single '
                    $columnDefault = preg_replace('/[\'\\\\]\'/', "'", $match[1]);
                } else {
                    $columnDefault = Literal::from($columnInfo['column_default']);
                }
            } elseif (preg_match('/^\D[a-z_\d]*\(.*\)$/', $columnInfo['column_default'])) {
                $columnDefault = Literal::from($columnInfo['column_default']);
            } else {
                $columnDefault = $columnInfo['column_default'];
            }

            $column = new Column();
            $column->setName($columnInfo['column_name'])
                   ->setType($columnType)
                   ->setNull($columnInfo['is_nullable'] === 'YES')
                   ->setDefault($columnDefault)
                   ->setIdentity($columnInfo['is_identity'] === 'YES')
                   ->setScale($columnInfo['numeric_scale']);

            if (preg_match('/\bwith time zone$/', $columnInfo['data_type'])) {
                $column->setTimezone(true);
            }

            if (isset($columnInfo['character_maximum_length'])) {
                $column->setLimit($columnInfo['character_maximum_length']);
            }

            if (in_array($columnType, [static::PHINX_TYPE_TIME, static::PHINX_TYPE_DATETIME])) {
                $column->setPrecision($columnInfo['datetime_precision']);
            } else {
                $column->setPrecision($columnInfo['numeric_precision']);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName)
    {
        $parts = $this->getSchemaName($tableName);
        $sql = sprintf(
            "SELECT count(*)
            FROM information_schema.columns
            WHERE table_schema = %s AND table_name = %s AND column_name = %s",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table']),
            $this->getConnection()->quote($columnName)
        );

        $result = $this->fetchRow($sql);

        return $result['count'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $instructions = new AlterInstructions();
        $instructions->addAlter(sprintf(
            'ADD %s %s',
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        ));

        if ($column->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($column, $table->getName()));
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName)
    {
        $parts = $this->getSchemaName($tableName);
        $sql = sprintf(
            "SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS column_exists
             FROM information_schema.columns
             WHERE table_schema = %s AND table_name = %s AND column_name = %s",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table']),
            $this->getConnection()->quote($columnName)
        );

        $result = $this->fetchRow($sql);
        if (!(bool)$result['column_exists']) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $instructions = new AlterInstructions();
        $instructions->addPostStep(
            sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $tableName,
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumnName)
            )
        );

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $instructions = new AlterInstructions();

        $sql = sprintf(
            'ALTER COLUMN %s TYPE %s',
            $this->quoteColumnName($columnName),
            $this->getColumnSqlDefinition($newColumn)
        );
        //
        //NULL and DEFAULT cannot be set while changing column type
        $sql = preg_replace('/ NOT NULL/', '', $sql);
        $sql = preg_replace('/ NULL/', '', $sql);
        //If it is set, DEFAULT is the last definition
        $sql = preg_replace('/DEFAULT .*/', '', $sql);

        $instructions->addAlter($sql);

        // process null
        $sql = sprintf(
            'ALTER COLUMN %s',
            $this->quoteColumnName($columnName)
        );

        if ($newColumn->isNull()) {
            $sql .= ' DROP NOT NULL';
        } else {
            $sql .= ' SET NOT NULL';
        }

        $instructions->addAlter($sql);

        if (!is_null($newColumn->getDefault())) {
            $instructions->addAlter(sprintf(
                'ALTER COLUMN %s SET %s',
                $this->quoteColumnName($columnName),
                $this->getDefaultValueDefinition($newColumn->getDefault(), $newColumn->getType())
            ));
        } else {
            //drop default
            $instructions->addAlter(sprintf(
                'ALTER COLUMN %s DROP DEFAULT',
                $this->quoteColumnName($columnName)
            ));
        }

        // rename column
        if ($columnName !== $newColumn->getName()) {
            $instructions->addPostStep(sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumn->getName())
            ));
        }

        // change column comment if needed
        if ($newColumn->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($newColumn, $tableName));
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropColumnInstructions($tableName, $columnName)
    {
        $alter = sprintf(
            'DROP COLUMN %s',
            $this->quoteColumnName($columnName)
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $parts = $this->getSchemaName($tableName);

        $indexes = [];
        $sql = sprintf(
            "SELECT
                i.relname AS index_name,
                a.attname AS column_name
            FROM
                pg_class t,
                pg_class i,
                pg_index ix,
                pg_attribute a,
                pg_namespace nsp
            WHERE
                t.oid = ix.indrelid
                AND i.oid = ix.indexrelid
                AND a.attrelid = t.oid
                AND a.attnum = ANY(ix.indkey)
                AND t.relnamespace = nsp.oid
                AND nsp.nspname = %s
                AND t.relkind = 'r'
                AND t.relname = %s
            ORDER BY
                t.relname,
                i.relname",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table'])
        );
        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            if (!isset($indexes[$row['index_name']])) {
                $indexes[$row['index_name']] = ['columns' => []];
            }
            $indexes[$row['index_name']]['columns'][] = $row['column_name'];
        }

        return $indexes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
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
            if ($name === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAddIndexInstructions(Table $table, Index $index)
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep($this->getIndexSqlDefinition($index, $table->getName()));

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByColumnsInstructions($tableName, $columns)
    {
        $parts = $this->getSchemaName($tableName);

        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                return new AlterInstructions([], [sprintf(
                    'DROP INDEX IF EXISTS %s',
                    '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName))
                )]);
            }
        }

        throw new \InvalidArgumentException(sprintf(
            "The specified index on columns '%s' does not exist",
            implode(',', $columns)
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByNameInstructions($tableName, $indexName)
    {
        $parts = $this->getSchemaName($tableName);

        $sql = sprintf(
            'DROP INDEX IF EXISTS %s',
            '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName))
        );

        return new AlterInstructions([], [$sql]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPrimaryKey($tableName, $columns, $constraint = null)
    {
        $primaryKey = $this->getPrimaryKey($tableName);

        if (empty($primaryKey)) {
            return false;
        }

        if ($constraint) {
            return ($primaryKey['constraint'] === $constraint);
        } else {
            if (is_string($columns)) {
                $columns = [$columns]; // str to array
            }
            $missingColumns = array_diff($columns, $primaryKey['columns']);

            return empty($missingColumns);
        }
    }

    /**
     * Get the primary key from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    public function getPrimaryKey($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $rows = $this->fetchAll(sprintf(
            "SELECT
                    tc.constraint_name,
                    kcu.column_name
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                WHERE constraint_type = 'PRIMARY KEY'
                    AND tc.table_schema = %s
                    AND tc.table_name = %s
                ORDER BY kcu.position_in_unique_constraint",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table'])
        ));

        $primaryKey = [
            'columns' => [],
        ];
        foreach ($rows as $row) {
            $primaryKey['constraint'] = $row['constraint_name'];
            $primaryKey['columns'][] = $row['column_name'];
        }

        return $primaryKey;
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
        $parts = $this->getSchemaName($tableName);
        $foreignKeys = [];
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
                WHERE constraint_type = 'FOREIGN KEY' AND tc.table_schema = %s AND tc.table_name = %s
                ORDER BY kcu.position_in_unique_constraint",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table'])
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
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey)
    {
        $alter = sprintf(
            'ADD %s',
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint)
    {
        $alter = sprintf(
            'DROP CONSTRAINT %s',
            $this->quoteColumnName($constraint)
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyByColumnsInstructions($tableName, $columns)
    {
        $instructions = new AlterInstructions();

        $parts = $this->getSchemaName($tableName);
        $sql = "SELECT c.CONSTRAINT_NAME
                FROM (
                    SELECT CONSTRAINT_NAME, array_agg(COLUMN_NAME::varchar) as columns
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = %s
                    AND TABLE_NAME IS NOT NULL
                    AND TABLE_NAME = %s
                    AND POSITION_IN_UNIQUE_CONSTRAINT IS NOT NULL
                    GROUP BY CONSTRAINT_NAME
                ) c
                WHERE
                    ARRAY[%s]::varchar[] <@ c.columns AND
                    ARRAY[%s]::varchar[] @> c.columns";

        $array = [];
        foreach ($columns as $col) {
            $array[] = "'$col'";
        }

        $rows = $this->fetchAll(sprintf(
            $sql,
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table']),
            implode(',', $array),
            implode(',', $array)
        ));

        foreach ($rows as $row) {
            $newInstr = $this->getDropForeignKeyInstructions($tableName, $row['constraint_name']);
            $instructions->merge($newInstr);
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        switch ($type) {
            case static::PHINX_TYPE_TEXT:
            case static::PHINX_TYPE_TIME:
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_BOOLEAN:
            case static::PHINX_TYPE_JSON:
            case static::PHINX_TYPE_JSONB:
            case static::PHINX_TYPE_UUID:
            case static::PHINX_TYPE_CIDR:
            case static::PHINX_TYPE_INET:
            case static::PHINX_TYPE_MACADDR:
            case static::PHINX_TYPE_TIMESTAMP:
            case static::PHINX_TYPE_INTEGER:
                return ['name' => $type];
            case static::PHINX_TYPE_SMALL_INTEGER:
                return ['name' => 'smallint'];
            case static::PHINX_TYPE_DECIMAL:
                return ['name' => $type, 'precision' => 18, 'scale' => 0];
            case static::PHINX_TYPE_DOUBLE:
                return ['name' => 'double precision'];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'character varying', 'limit' => 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'character', 'limit' => 255];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'bigint'];
            case static::PHINX_TYPE_FLOAT:
                return ['name' => 'real'];
            case static::PHINX_TYPE_DATETIME:
                return ['name' => 'timestamp'];
            case static::PHINX_TYPE_BLOB:
            case static::PHINX_TYPE_BINARY:
                return ['name' => 'bytea'];
            case static::PHINX_TYPE_INTERVAL:
                return ['name' => 'interval'];
            // Geospatial database types
            // Spatial storage in Postgres is done via the PostGIS extension,
            // which enables the use of the "geography" type in combination
            // with SRID 4326.
            case static::PHINX_TYPE_GEOMETRY:
                return ['name' => 'geography', 'type' => 'geometry', 'srid' => 4326];
            case static::PHINX_TYPE_POINT:
                return ['name' => 'geography', 'type' => 'point', 'srid' => 4326];
            case static::PHINX_TYPE_LINESTRING:
                return ['name' => 'geography', 'type' => 'linestring', 'srid' => 4326];
            case static::PHINX_TYPE_POLYGON:
                return ['name' => 'geography', 'type' => 'polygon', 'srid' => 4326];
            default:
                if ($this->isArrayType($type)) {
                    return ['name' => $type];
                }
                // Return array type
                throw new UnsupportedColumnTypeException('Column type "' . $type . '" is not supported by Postgresql.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL type
     * @return string Phinx type
     * @throws UnsupportedColumnTypeException
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
                return static::PHINX_TYPE_SMALL_INTEGER;
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
            case 'double precision':
                return static::PHINX_TYPE_DOUBLE;
            case 'bytea':
                return static::PHINX_TYPE_BINARY;
            case 'interval':
                return static::PHINX_TYPE_INTERVAL;
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
            case 'cidr':
                return static::PHINX_TYPE_CIDR;
            case 'inet':
                return static::PHINX_TYPE_INET;
            case 'macaddr':
                return static::PHINX_TYPE_MACADDR;
            default:
                throw new UnsupportedColumnTypeException('Column type "' . $sqlType . '" is not supported by Postgresql.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = [])
    {
        $charset = isset($options['charset']) ? $options['charset'] : 'utf8';
        $this->execute(sprintf("CREATE DATABASE %s WITH ENCODING = '%s'", $name, $charset));
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        $sql = sprintf("SELECT count(*) FROM pg_database WHERE datname = '%s'", $name);
        $result = $this->fetchRow($sql);

        return $result['count'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->disconnect();
        $this->execute(sprintf('DROP DATABASE IF EXISTS %s', $name));
        $this->connect();
    }

    /**
     * Get the defintion for a `DEFAULT` statement.
     *
     * @param mixed $default default value
     * @param string $columnType column type added
     * @return string
     */
    protected function getDefaultValueDefinition($default, $columnType = null)
    {
        if (is_string($default) && 'CURRENT_TIMESTAMP' !== $default) {
            $default = $this->getConnection()->quote($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        } elseif ($columnType === static::PHINX_TYPE_BOOLEAN) {
            $default = $this->castToBool((bool)$default);
        }

        return isset($default) ? 'DEFAULT ' . $default : '';
    }

    /**
     * Gets the PostgreSQL Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $buffer = [];
        if ($column->isIdentity()) {
            $buffer[] = $column->getType() == 'biginteger' ? 'BIGSERIAL' : 'SERIAL';
        } elseif ($column->getType() instanceof Literal) {
            $buffer[] = (string)$column->getType();
        } else {
            $sqlType = $this->getSqlType($column->getType(), $column->getLimit());
            $buffer[] = strtoupper($sqlType['name']);

            // integers cant have limits in postgres
            if (static::PHINX_TYPE_DECIMAL === $sqlType['name'] && ($column->getPrecision() || $column->getScale())) {
                $buffer[] = sprintf(
                    '(%s, %s)',
                    $column->getPrecision() ?: $sqlType['precision'],
                    $column->getScale() ?: $sqlType['scale']
                );
            } elseif (in_array($sqlType['name'], ['geography'])) {
                // geography type must be written with geometry type and srid, like this: geography(POLYGON,4326)
                $buffer[] = sprintf(
                    '(%s,%s)',
                    strtoupper($sqlType['type']),
                    $sqlType['srid']
                );
            } elseif (in_array($sqlType['name'], ['time', 'timestamp'])) {
                if (is_numeric($column->getPrecision())) {
                    $buffer[] = sprintf('(%s)', $column->getPrecision());
                }

                if ($column->isTimezone()) {
                    $buffer[] = strtoupper('with time zone');
                }
            } elseif (!in_array($sqlType['name'], ['integer', 'smallint', 'bigint', 'boolean'])) {
                if ($column->getLimit() || isset($sqlType['limit'])) {
                    $buffer[] = sprintf('(%s)', $column->getLimit() ?: $sqlType['limit']);
                }
            }
        }

        $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';

        if (!is_null($column->getDefault())) {
            $buffer[] = $this->getDefaultValueDefinition($column->getDefault(), $column->getType());
        }

        return implode(' ', $buffer);
    }

    /**
     * Gets the PostgreSQL Column Comment Definition for a column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
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
            $this->quoteTableName($tableName),
            $this->quoteColumnName($column->getName()),
            $comment
        );
    }

    /**
     * Gets the PostgreSQL Index Definition for an Index object.
     *
     * @param \Phinx\Db\Table\Index  $index Index
     * @param string $tableName Table name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {
        $parts = $this->getSchemaName($tableName);

        if (is_string($index->getName())) {
            $indexName = $index->getName();
        } else {
            $columnNames = $index->getColumns();
            $indexName = sprintf('%s_%s', $parts['table'], implode('_', $columnNames));
        }
        $def = sprintf(
            "CREATE %s INDEX %s ON %s (%s);",
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $this->quoteColumnName($indexName),
            $this->quoteTableName($tableName),
            implode(',', array_map([$this, 'quoteColumnName'], $index->getColumns()))
        );

        return $def;
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey
     * @param string     $tableName  Table name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $parts = $this->getSchemaName($tableName);

        $constraintName = $foreignKey->getConstraint() ?: ($parts['table'] . '_' . implode('_', $foreignKey->getColumns()) . '_fkey');
        $def = ' CONSTRAINT ' . $this->quoteColumnName($constraintName) .
        ' FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")' .
        " REFERENCES {$this->quoteTableName($foreignKey->getReferencedTable()->getName())} (\"" .
        implode('", "', $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete()) {
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
    public function createSchemaTable()
    {
        // Create the public/custom schema if it doesn't already exist
        if ($this->hasSchema($this->getGlobalSchemaName()) === false) {
            $this->createSchema($this->getGlobalSchemaName());
        }

        $this->fetchAll(sprintf('SET search_path TO %s', $this->getGlobalSchemaName()));

        parent::createSchemaTable();
    }

    /**
     * Creates the specified schema.
     *
     * @param  string $schemaName Schema Name
     * @return void
     */
    public function createSchema($schemaName = 'public')
    {
        // from postgres 9.3 we can use "CREATE SCHEMA IF NOT EXISTS schema_name"
        $sql = sprintf('CREATE SCHEMA %s;', $this->quoteSchemaName($schemaName));
        $this->execute($sql);
    }

    /**
     * Checks to see if a schema exists.
     *
     * @param string $schemaName Schema Name
     * @return bool
     */
    public function hasSchema($schemaName)
    {
        $sql = sprintf(
            "SELECT count(*)
             FROM pg_namespace
             WHERE nspname = %s",
            $this->getConnection()->quote($schemaName)
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
        $sql = sprintf("DROP SCHEMA IF EXISTS %s CASCADE;", $this->quoteSchemaName($schemaName));
        $this->execute($sql);
    }

    /**
     * Drops all schemas.
     *
     * @return void
     */
    public function dropAllSchemas()
    {
        foreach ($this->getAllSchemas() as $schema) {
            $this->dropSchema($schema);
        }
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
        $schemaNames = [];
        foreach ($items as $item) {
            $schemaNames[] = $item['schema_name'];
        }

        return $schemaNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array_merge(parent::getColumnTypes(), ['json', 'jsonb', 'cidr', 'inet', 'macaddr', 'interval']);
    }

    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column)
    {
        // If not a standard column type, maybe it is array type?
        return (parent::isValidColumnType($column) || $this->isArrayType($column->getType()));
    }

    /**
     * Check if the given column is an array of a valid type.
     *
     * @param  string $columnType
     * @return bool
     */
    protected function isArrayType($columnType)
    {
        if (!preg_match('/^([a-z]+)(?:\[\]){1,}$/', $columnType, $matches)) {
            return false;
        }

        $baseType = $matches[1];

        return in_array($baseType, $this->getColumnTypes());
    }

    /**
     * @param string $tableName Table name
     * @return array
     */
    private function getSchemaName($tableName)
    {
        $schema = $this->getGlobalSchemaName();
        $table = $tableName;
        if (false !== strpos($tableName, '.')) {
            list($schema, $table) = explode('.', $tableName);
        }

        return [
            'schema' => $schema,
            'table' => $table,
        ];
    }

    /**
     * Gets the schema name.
     *
     * @return string
     */
    private function getGlobalSchemaName()
    {
        $options = $this->getOptions();

        return empty($options['schema']) ? 'public' : $options['schema'];
    }

    /**
     * {@inheritdoc}
     */
    public function castToBool($value)
    {
        return (bool)$value ? 'TRUE' : 'FALSE';
    }

    /**
     * {@inheritDoc}
     *
     */
    public function getDecoratedConnection()
    {
        $options = $this->getOptions();
        $options = [
            'username' => $options['user'],
            'password' => $options['pass'],
            'database' => $options['name'],
            'quoteIdentifiers' => true,
        ] + $options;

        $driver = new PostgresDriver($options);

        if (method_exists($driver, 'setConnection')) {
            $driver->setConnection($this->connection);
        } else {
            $driver->connection($this->connection);
        }

        return new Connection(['driver' => $driver] + $options);
    }
}
