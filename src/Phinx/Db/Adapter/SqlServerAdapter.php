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
use Cake\Database\Driver\Sqlserver as SqlServerDriver;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Util\Literal;

/**
 * Phinx SqlServer Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
class SqlServerAdapter extends PdoAdapter implements AdapterInterface
{
    protected $schema = 'dbo';

    protected $signedColumnTypes = ['integer' => true, 'biginteger' => true, 'float' => true, 'decimal' => true];

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if ($this->connection === null) {
            if (!class_exists('PDO') || !in_array('sqlsrv', \PDO::getAvailableDrivers(), true)) {
                // try our connection via freetds (Mac/Linux)
                $this->connectDblib();

                return;
            }

            $db = null;
            $options = $this->getOptions();

            // if port is specified use it, otherwise use the SqlServer default
            if (empty($options['port'])) {
                $dsn = 'sqlsrv:server=' . $options['host'] . ';database=' . $options['name'];
            } else {
                $dsn = 'sqlsrv:server=' . $options['host'] . ',' . $options['port'] . ';database=' . $options['name'];
            }
            $dsn .= ';MultipleActiveResultSets=false';

            $driverOptions = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

            // charset support
            if (isset($options['charset'])) {
                $driverOptions[\PDO::SQLSRV_ATTR_ENCODING] = $options['charset'];
            }

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
        }
    }

    /**
     * Connect to MSSQL using dblib/freetds.
     *
     * The "sqlsrv" driver is not available on Unix machines.
     *
     * @throws \InvalidArgumentException
     */
    protected function connectDblib()
    {
        if (!class_exists('PDO') || !in_array('dblib', \PDO::getAvailableDrivers(), true)) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('You need to enable the PDO_Dblib extension for Phinx to run properly.');
            // @codeCoverageIgnoreEnd
        }

        $options = $this->getOptions();

        // if port is specified use it, otherwise use the SqlServer default
        if (empty($options['port'])) {
            $dsn = 'dblib:host=' . $options['host'] . ';dbname=' . $options['name'];
        } else {
            $dsn = 'dblib:host=' . $options['host'] . ':' . $options['port'] . ';dbname=' . $options['name'];
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
        $this->execute('COMMIT TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        $this->execute('ROLLBACK TRANSACTION');
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
    public function hasTable($tableName)
    {
        $result = $this->fetchRow(sprintf('SELECT count(*) as [count] FROM information_schema.tables WHERE table_name = \'%s\';', $tableName));

        return $result['count'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $options = $table->getOptions();

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
            $pkSql = sprintf('CONSTRAINT PK_%s PRIMARY KEY (', $table->getName());
            if (is_string($options['primary_key'])) { // handle primary_key => 'id'
                $pkSql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                $pkSql .= implode(',', array_map([$this, 'quoteColumnName'], $options['primary_key']));
            }
            $pkSql .= ')';
            $sqlBuffer[] = $pkSql;
        }

        $sql .= implode(', ', $sqlBuffer);
        $sql .= ');';

        // process column comments
        foreach ($columnsWithComments as $column) {
            $sql .= $this->getColumnCommentSqlDefinition($column, $table->getName());
        }

        // set the indexes
        foreach ($indexes as $index) {
            $sql .= $this->getIndexSqlDefinition($index, $table->getName());
        }

        // execute the sql
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangePrimaryKeyInstructions(Table $table, $newColumns)
    {
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

        // Add the primary key(s)
        if (!empty($newColumns)) {
            $sql = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (',
                $this->quoteTableName($table->getName()),
                $this->quoteColumnName('PK_' . $table->getName())
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
            $instructions->addPostStep($sql);
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangeCommentInstructions(Table $table, $newComment)
    {
        throw new \BadMethodCallException('SQLite does not have table comments');
    }

    /**
     * Gets the SqlServer Column Comment Defininition for a column object.
     *
     * @param \Phinx\Db\Table\Column $column    Column
     * @param string $tableName Table name
     *
     * @return string
     */
    protected function getColumnCommentSqlDefinition(Column $column, $tableName)
    {
        // passing 'null' is to remove column comment
        $currentComment = $this->getColumnComment($tableName, $column->getName());

        $comment = (strcasecmp($column->getComment(), 'NULL') !== 0) ? $this->getConnection()->quote($column->getComment()) : '\'\'';
        $command = $currentComment === false ? 'sp_addextendedproperty' : 'sp_updateextendedproperty';

        return sprintf(
            "EXECUTE %s N'MS_Description', N%s, N'SCHEMA', N'%s', N'TABLE', N'%s', N'COLUMN', N'%s';",
            $command,
            $comment,
            $this->schema,
            $tableName,
            $column->getName()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameTableInstructions($tableName, $newTableName)
    {
        $sql = sprintf(
            'EXEC sp_rename \'%s\', \'%s\'',
            $tableName,
            $newTableName
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

    public function getColumnComment($tableName, $columnName)
    {
        $sql = sprintf("SELECT cast(extended_properties.[value] as nvarchar(4000)) comment
  FROM sys.schemas
 INNER JOIN sys.tables
    ON schemas.schema_id = tables.schema_id
 INNER JOIN sys.columns
    ON tables.object_id = columns.object_id
 INNER JOIN sys.extended_properties
    ON tables.object_id = extended_properties.major_id
   AND columns.column_id = extended_properties.minor_id
   AND extended_properties.name = 'MS_Description'
   WHERE schemas.[name] = '%s' AND tables.[name] = '%s' AND columns.[name] = '%s'", $this->schema, $tableName, $columnName);
        $row = $this->fetchRow($sql);

        if ($row) {
            return trim($row['comment']);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $columns = [];
        $sql = sprintf(
            "SELECT DISTINCT TABLE_SCHEMA AS [schema], TABLE_NAME as [table_name], COLUMN_NAME AS [name], DATA_TYPE AS [type],
            IS_NULLABLE AS [null], COLUMN_DEFAULT AS [default],
            CHARACTER_MAXIMUM_LENGTH AS [char_length],
            NUMERIC_PRECISION AS [precision],
            NUMERIC_SCALE AS [scale], ORDINAL_POSITION AS [ordinal_position],
            COLUMNPROPERTY(object_id(TABLE_NAME), COLUMN_NAME, 'IsIdentity') as [identity]
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = '%s'
        ORDER BY ordinal_position",
            $tableName
        );
        $rows = $this->fetchAll($sql);
        foreach ($rows as $columnInfo) {
            try {
                $type = $this->getPhinxType($columnInfo['type']);
            } catch (UnsupportedColumnTypeException $e) {
                $type = Literal::from($columnInfo['type']);
            }

            $column = new Column();
            $column->setName($columnInfo['name'])
                   ->setType($type)
                   ->setNull($columnInfo['null'] !== 'NO')
                   ->setDefault($this->parseDefault($columnInfo['default']))
                   ->setIdentity($columnInfo['identity'] === '1')
                   ->setComment($this->getColumnComment($columnInfo['table_name'], $columnInfo['name']));

            if (!empty($columnInfo['char_length'])) {
                $column->setLimit($columnInfo['char_length']);
            }

            $columns[$columnInfo['name']] = $column;
        }

        return $columns;
    }

    protected function parseDefault($default)
    {
        $default = preg_replace(["/\('(.*)'\)/", "/\(\((.*)\)\)/", "/\((.*)\)/"], '$1', $default);

        if (strtoupper($default) === 'NULL') {
            $default = null;
        } elseif (is_numeric($default)) {
            $default = (int)$default;
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName)
    {
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
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $alter = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $table->getName(),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );

        return new AlterInstructions([], [$alter]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName)
    {
        if (!$this->hasColumn($tableName, $columnName)) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $instructions = new AlterInstructions();

        $oldConstraintName = "DF_{$tableName}_{$columnName}";
        $newConstraintName = "DF_{$tableName}_{$newColumnName}";
        $sql = <<<SQL
IF (OBJECT_ID('$oldConstraintName', 'D') IS NOT NULL)
BEGIN
     EXECUTE sp_rename N'%s', N'%s', N'OBJECT'
END
SQL;
        $instructions->addPostStep(sprintf(
            $sql,
            $oldConstraintName,
            $newConstraintName
        ));

        $instructions->addPostStep(sprintf(
            "EXECUTE sp_rename N'%s.%s', N'%s', 'COLUMN' ",
            $tableName,
            $columnName,
            $newColumnName
        ));

        return $instructions;
    }

    /**
     * Returns the instructions to change a column default value
     *
     * @param string $tableName The table where the column is
     * @param Column $newColumn The column to alter
     * @return AlterInstructions
     */
    protected function getChangeDefault($tableName, Column $newColumn)
    {
        $constraintName = "DF_{$tableName}_{$newColumn->getName()}";
        $default = $newColumn->getDefault();
        $instructions = new AlterInstructions();

        if ($default === null) {
            $default = 'DEFAULT NULL';
        } else {
            $default = ltrim($this->getDefaultValueDefinition($default));
        }

        if (empty($default)) {
            return $instructions;
        }

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s %s FOR %s',
            $this->quoteTableName($tableName),
            $constraintName,
            $default,
            $this->quoteColumnName($newColumn->getName())
        ));

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $columns = $this->getColumns($tableName);
        $changeDefault =
            $newColumn->getDefault() !== $columns[$columnName]->getDefault() ||
            $newColumn->getType() !== $columns[$columnName]->getType();

        $instructions = new AlterInstructions();

        if ($columnName !== $newColumn->getName()) {
            $instructions->merge(
                $this->getRenameColumnInstructions($tableName, $columnName, $newColumn->getName())
            );
        }

        if ($changeDefault) {
            $instructions->merge($this->getDropDefaultConstraint($tableName, $newColumn->getName()));
        }

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($newColumn->getName()),
            $this->getColumnSqlDefinition($newColumn, false)
        ));
        // change column comment if needed
        if ($newColumn->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($newColumn, $tableName));
        }

        if ($changeDefault) {
            $instructions->merge($this->getChangeDefault($tableName, $newColumn));
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropColumnInstructions($tableName, $columnName)
    {
        $instructions = $this->getDropDefaultConstraint($tableName, $columnName);

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName)
        ));

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropDefaultConstraint($tableName, $columnName)
    {
        $defaultConstraint = $this->getDefaultConstraint($tableName, $columnName);

        if (!$defaultConstraint) {
            return new AlterInstructions();
        }

        return $this->getDropForeignKeyInstructions($tableName, $defaultConstraint);
    }

    protected function getDefaultConstraint($tableName, $columnName)
    {
        $sql = "SELECT
    default_constraints.name
FROM
    sys.all_columns

        INNER JOIN
    sys.tables
        ON all_columns.object_id = tables.object_id

        INNER JOIN
    sys.schemas
        ON tables.schema_id = schemas.schema_id

        INNER JOIN
    sys.default_constraints
        ON all_columns.default_object_id = default_constraints.object_id

WHERE
        schemas.name = 'dbo'
    AND tables.name = '{$tableName}'
    AND all_columns.name = '{$columnName}'";

        $rows = $this->fetchAll($sql);

        return empty($rows) ? false : $rows[0]['name'];
    }

    protected function getIndexColums($tableId, $indexId)
    {
        $sql = "SELECT AC.[name] AS [column_name]
FROM sys.[index_columns] IC
  INNER JOIN sys.[all_columns] AC ON IC.[column_id] = AC.[column_id]
WHERE AC.[object_id] = {$tableId} AND IC.[index_id] = {$indexId}  AND IC.[object_id] = {$tableId}
ORDER BY IC.[key_ordinal];";

        $rows = $this->fetchAll($sql);
        $columns = [];
        foreach ($rows as $row) {
            $columns[] = strtolower($row['column_name']);
        }

        return $columns;
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
        $sql = "SELECT I.[name] AS [index_name], I.[index_id] as [index_id], T.[object_id] as [table_id]
FROM sys.[tables] AS T
  INNER JOIN sys.[indexes] I ON T.[object_id] = I.[object_id]
WHERE T.[is_ms_shipped] = 0 AND I.[type_desc] <> 'HEAP'  AND T.[name] = '{$tableName}'
ORDER BY T.[name], I.[index_id];";

        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            $columns = $this->getIndexColums($row['table_id'], $row['index_id']);
            $indexes[$row['index_name']] = ['columns' => $columns];
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
        $sql = $this->getIndexSqlDefinition($index, $table->getName());

        return new AlterInstructions([], [$sql]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByColumnsInstructions($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);
        $instructions = new AlterInstructions();

        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteColumnName($indexName),
                    $this->quoteTableName($tableName)
                ));

                return $instructions;
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
        $indexes = $this->getIndexes($tableName);
        $instructions = new AlterInstructions();

        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteColumnName($indexName),
                    $this->quoteTableName($tableName)
                ));

                return $instructions;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            "The specified index name '%s' does not exist",
            $indexName
        ));
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
        $rows = $this->fetchAll(sprintf(
            "SELECT
                    tc.constraint_name,
                    kcu.column_name
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                WHERE constraint_type = 'PRIMARY KEY'
                    AND tc.table_name = '%s'
                ORDER BY kcu.ordinal_position",
            $tableName
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
                WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name = '%s'
                ORDER BY kcu.ordinal_position",
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
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey)
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ADD %s',
            $this->quoteTableName($table->getName()),
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
        ));

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint)
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->quoteTableName($tableName),
            $constraint
        ));

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyByColumnsInstructions($tableName, $columns)
    {
        $instructions = new AlterInstructions();

        foreach ($columns as $column) {
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
            WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name = '%s' and ccu.column_name='%s'
            ORDER BY kcu.ordinal_position",
                $tableName,
                $column
            ));
            foreach ($rows as $row) {
                $instructions->merge(
                    $this->getDropForeignKeyInstructions($tableName, $row['constraint_name'])
                );
            }
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        switch ($type) {
            case static::PHINX_TYPE_FLOAT:
            case static::PHINX_TYPE_DECIMAL:
            case static::PHINX_TYPE_DATETIME:
            case static::PHINX_TYPE_TIME:
            case static::PHINX_TYPE_DATE:
                return ['name' => $type];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'nvarchar', 'limit' => 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'nchar', 'limit' => 255];
            case static::PHINX_TYPE_TEXT:
                return ['name' => 'ntext'];
            case static::PHINX_TYPE_INTEGER:
                return ['name' => 'int'];
            case static::PHINX_TYPE_SMALL_INTEGER:
                return ['name' => 'smallint'];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'bigint'];
            case static::PHINX_TYPE_TIMESTAMP:
                return ['name' => 'datetime'];
            case static::PHINX_TYPE_BLOB:
            case static::PHINX_TYPE_BINARY:
                return ['name' => 'varbinary'];
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'bit'];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'uniqueidentifier'];
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
                throw new UnsupportedColumnTypeException('Column type "' . $type . '" is not supported by SqlServer.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL Type definition
     * @throws UnsupportedColumnTypeException
     * @internal param string $sqlType SQL type
     * @return string Phinx type
     */
    public function getPhinxType($sqlType)
    {
        switch ($sqlType) {
            case 'nvarchar':
            case 'varchar':
                return static::PHINX_TYPE_STRING;
            case 'char':
            case 'nchar':
                return static::PHINX_TYPE_CHAR;
            case 'text':
            case 'ntext':
                return static::PHINX_TYPE_TEXT;
            case 'int':
            case 'integer':
                return static::PHINX_TYPE_INTEGER;
            case 'decimal':
            case 'numeric':
            case 'money':
                return static::PHINX_TYPE_DECIMAL;
            case 'smallint':
                return static::PHINX_TYPE_SMALL_INTEGER;
            case 'bigint':
                return static::PHINX_TYPE_BIG_INTEGER;
            case 'real':
            case 'float':
                return static::PHINX_TYPE_FLOAT;
            case 'binary':
            case 'image':
            case 'varbinary':
                return static::PHINX_TYPE_BINARY;
            case 'time':
                return static::PHINX_TYPE_TIME;
            case 'date':
                return static::PHINX_TYPE_DATE;
            case 'datetime':
            case 'timestamp':
                return static::PHINX_TYPE_DATETIME;
            case 'bit':
                return static::PHINX_TYPE_BOOLEAN;
            case 'uniqueidentifier':
                return static::PHINX_TYPE_UUID;
            case 'filestream':
                return static::PHINX_TYPE_FILESTREAM;
            default:
                throw new UnsupportedColumnTypeException('Column type "' . $sqlType . '" is not supported by SqlServer.');
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
     * Gets the SqlServer Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column, $create = true)
    {
        $buffer = [];
        if ($column->getType() instanceof Literal) {
            $buffer[] = (string)$column->getType();
        } else {
            $sqlType = $this->getSqlType($column->getType());
            $buffer[] = strtoupper($sqlType['name']);
            // integers cant have limits in SQlServer
            $noLimits = [
                'bigint',
                'int',
                'tinyint'
            ];
            if (static::PHINX_TYPE_DECIMAL === $sqlType['name'] && $column->getPrecision() && $column->getScale()) {
                $buffer[] = sprintf(
                    '(%s, %s)',
                    $column->getPrecision() ?: $sqlType['precision'],
                    $column->getScale() ?: $sqlType['scale']
                );
            } elseif (!in_array($sqlType['name'], $noLimits) && ($column->getLimit() || isset($sqlType['limit']))) {
                $buffer[] = sprintf('(%s)', $column->getLimit() ? $column->getLimit() : $sqlType['limit']);
            }
        }

        $properties = $column->getProperties();
        $buffer[] = $column->getType() === 'filestream' ? 'FILESTREAM' : '';
        $buffer[] = isset($properties['rowguidcol']) ? 'ROWGUIDCOL' : '';

        $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';

        if ($create === true) {
            if ($column->getDefault() === null && $column->isNull()) {
                $buffer[] = ' DEFAULT NULL';
            } else {
                $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
            }
        }

        if ($column->isIdentity()) {
            $buffer[] = 'IDENTITY(1, 1)';
        }

        return implode(' ', $buffer);
    }

    /**
     * Gets the SqlServer Index Definition for an Index object.
     *
     * @param \Phinx\Db\Table\Index $index Index
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {
        if (is_string($index->getName())) {
            $indexName = $index->getName();
        } else {
            $columnNames = $index->getColumns();
            $indexName = sprintf('%s_%s', $tableName, implode('_', $columnNames));
        }
        $def = sprintf(
            "CREATE %s INDEX %s ON %s (%s);",
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $indexName,
            $this->quoteTableName($tableName),
            '[' . implode('],[', $index->getColumns()) . ']'
        );

        return $def;
    }

    /**
     * Gets the SqlServer Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $constraintName = $foreignKey->getConstraint() ?: $tableName . '_' . implode('_', $foreignKey->getColumns());
        $def = ' CONSTRAINT ' . $this->quoteColumnName($constraintName);
        $def .= ' FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")';
        $def .= " REFERENCES {$this->quoteTableName($foreignKey->getReferencedTable()->getName())} (\"" . implode('", "', $foreignKey->getReferencedColumns()) . '")';
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
        $startTime = str_replace(' ', 'T', $startTime);
        $endTime = str_replace(' ', 'T', $endTime);

        return parent::migrated($migration, $direction, $startTime, $endTime);
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

        $driver = new SqlServerDriver($options);

        if (method_exists($driver, 'setConnection')) {
            $driver->setConnection($this->connection);
        } else {
            $driver->connection($this->connection);
        }

        return new Connection(['driver' => $driver] + $options);
    }
}
