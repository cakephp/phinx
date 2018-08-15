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
use Cake\Database\Driver\Sqlite as SqliteDriver;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Util\Literal;

/**
 * Phinx SQLite Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 * @author Richard McIntyre <richard.mackstars@gmail.com>
 */
class SQLiteAdapter extends PdoAdapter implements AdapterInterface
{
    protected $definitionsWithLimits = [
        'CHARACTER',
        'VARCHAR',
        'VARYING CHARACTER',
        'NCHAR',
        'NATIVE CHARACTER',
        'NVARCHAR'
    ];

    protected $suffix = '.sqlite3';

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if ($this->connection === null) {
            if (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_SQLITE extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }

            $db = null;
            $options = $this->getOptions();

            // if port is specified use it, otherwise use the MySQL default
            if (isset($options['memory'])) {
                $dsn = 'sqlite::memory:';
            } else {
                $dsn = 'sqlite:' . $options['name'] . $this->suffix;
            }

            try {
                $db = new \PDO($dsn);
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
                ));
            }

            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->setConnection($db);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        parent::setOptions($options);

        if (isset($options['suffix'])) {
            $this->suffix = $options['suffix'];
        }
        //don't "fix" the file extension if it is blank, some people
        //might want a SQLITE db file with absolutely no extension.
        if (strlen($this->suffix) && substr($this->suffix, 0, 1) !== '.') {
            $this->suffix = '.' . $this->suffix;
        }

        return $this;
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
        $this->getConnection()->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        $this->getConnection()->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        $this->getConnection()->rollBack();
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
        $tables = [];
        $rows = $this->fetchAll(sprintf('SELECT name FROM sqlite_master WHERE type=\'table\' AND name=\'%s\'', $tableName));
        foreach ($rows as $row) {
            $tables[] = strtolower($row[0]);
        }

        return in_array(strtolower($tableName), $tables);
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        // Add the default primary key
        $options = $table->getOptions();
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $column = new Column();
            $column->setName('id')
                   ->setType('integer')
                   ->setIdentity(true);

            array_unshift($columns, $column);
        } elseif (isset($options['id']) && is_string($options['id'])) {
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                   ->setType('integer')
                   ->setIdentity(true);

            array_unshift($columns, $column);
        }

        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';

            if (isset($options['primary_key']) && $column->getIdentity()) {
                //remove column from the primary key array as it is already defined as an autoincrement
                //primary id
                $identityColumnIndex = array_search($column->getName(), $options['primary_key']);
                if ($identityColumnIndex !== false) {
                    unset($options['primary_key'][$identityColumnIndex]);

                    if (empty($options['primary_key'])) {
                        //The last primary key has been removed
                        unset($options['primary_key']);
                    }
                }
            }
        }

        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            $sql .= ' PRIMARY KEY (';
            if (is_string($options['primary_key'])) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $options['primary_key']));
            }
            $sql .= ')';
        } else {
            $sql = substr(rtrim($sql), 0, -1); // no primary keys
        }

        $sql = rtrim($sql) . ');';
        // execute the sql
        $this->execute($sql);

        foreach ($indexes as $index) {
            $this->addIndex($table, $index);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangePrimaryKeyInstructions(Table $table, $newColumns)
    {
        $instructions = new AlterInstructions();

        // Drop the existing primary key
        $primaryKey = $this->getPrimaryKey($table->getName());
        if (!empty($primaryKey)) {
            $instructions->merge(
                $this->getDropPrimaryKeyInstructions($table, $primaryKey)
            );
        }

        // Add the primary key(s)
        if (!empty($newColumns)) {
            if (!is_string($newColumns)) {
                throw new \InvalidArgumentException(sprintf(
                    "Invalid value for primary key: %s",
                    json_encode($newColumns)
                ));
            }

            $instructions->merge(
                $this->getAddPrimaryKeyInstructions($table, $newColumns)
            );
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
     * {@inheritdoc}
     */
    protected function getRenameTableInstructions($tableName, $newTableName)
    {
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteTableName($tableName),
            $this->quoteTableName($newTableName)
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
            'DELETE FROM %s',
            $this->quoteTableName($tableName)
        );

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $columns = [];
        $rows = $this->fetchAll(sprintf('pragma table_info(%s)', $this->quoteTableName($tableName)));

        foreach ($rows as $columnInfo) {
            $column = new Column();
            $type = strtolower($columnInfo['type']);
            $column->setName($columnInfo['name'])
                   ->setNull($columnInfo['notnull'] !== '1')
                   ->setDefault($columnInfo['dflt_value']);

            $phinxType = $this->getPhinxType($type);
            $column->setType($phinxType['name'])
                   ->setLimit($phinxType['limit']);

            if ($columnInfo['pk'] == 1) {
                $column->setIdentity(true);
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
        $rows = $this->fetchAll(sprintf('pragma table_info(%s)', $this->quoteTableName($tableName)));
        foreach ($rows as $column) {
            if (strcasecmp($column['name'], $columnName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $alter = sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );

        return new AlterInstructions([], [$alter]);
    }

    /**
     * Returns the original CREATE statement for the give table
     *
     * @param string $tableName The table name to get the create statement for
     * @return string
     */
    protected function getDeclaringSql($tableName)
    {
        $rows = $this->fetchAll('select * from sqlite_master where `type` = \'table\'');

        $sql = '';
        foreach ($rows as $table) {
            if ($table['tbl_name'] === $tableName) {
                $sql = $table['sql'];
            }
        }

        return $sql;
    }

    /**
     * Copies all the data from a tmp table to another table
     *
     * @param string $tableName The table name to copy the data to
     * @param string $tmpTableName The tmp table name where the data is stored
     * @param string[] $writeColumns The list of columns in the target table
     * @param string[] $selectColumns The list of columns in the tmp table
     * @return void
     */
    protected function copyDataToNewTable($tableName, $tmpTableName, $writeColumns, $selectColumns)
    {
        $sql = sprintf(
            'INSERT INTO %s(%s) SELECT %s FROM %s',
            $this->quoteTableName($tableName),
            implode(', ', $writeColumns),
            implode(', ', $selectColumns),
            $this->quoteTableName($tmpTableName)
        );
        $this->execute($sql);
    }

    /**
     * Modifies the passed instructions to copy all data from the tmp table into
     * the provided table and then drops the tmp table.
     *
     * @param AlterInstructions $instructions The instructions to modify
     * @param string $tableName The table name to copy the data to
     * @return AlterInstructions
     */
    protected function copyAndDropTmpTable($instructions, $tableName)
    {
        $instructions->addPostStep(function ($state) use ($tableName) {
            $this->copyDataToNewTable(
                $tableName,
                $state['tmpTableName'],
                $state['writeColumns'],
                $state['selectColumns']
            );

            $this->execute(sprintf('DROP TABLE %s', $this->quoteTableName($state['tmpTableName'])));

            return $state;
        });

        return $instructions;
    }

    /**
     * Returns the columns and type to use when copying a table to another in the process
     * of altering a table
     *
     * @param string $tableName The table to modify
     * @param string $columnName The column name that is about to change
     * @param string|false $newColumnName Optionally the new name for the column
     * @return AlterInstructions
     */
    protected function calculateNewTableColumns($tableName, $columnName, $newColumnName)
    {
        $columns = $this->fetchAll(sprintf('pragma table_info(%s)', $this->quoteTableName($tableName)));
        $selectColumns = [];
        $writeColumns = [];
        $columnType = null;
        $found = false;

        foreach ($columns as $column) {
            $selectName = $column['name'];
            $writeName = $selectName;

            if ($selectName == $columnName) {
                $writeName = $newColumnName;
                $found = true;
                $columnType = $column['type'];
                $selectName = $newColumnName === false ? $newColumnName : $selectName;
            }

            $selectColumns[] = $selectName;
            $writeColumns[] = $writeName;
        }

        $selectColumns = array_filter($selectColumns, 'strlen');
        $writeColumns = array_filter($writeColumns, 'strlen');
        $selectColumns = array_map([$this, 'quoteColumnName'], $selectColumns);
        $writeColumns = array_map([$this, 'quoteColumnName'], $writeColumns);

        if (!$found) {
            throw new \InvalidArgumentException(sprintf(
                'The specified column doesn\'t exist: ' . $columnName
            ));
        }

        return compact('writeColumns', 'selectColumns', 'columnType');
    }

    /**
     * Returns the initial instructions to alter a table using the
     * rename-alter-copy strategy
     *
     * @param string $tableName The table to modify
     * @return AlterInstructions
     */
    protected function beginAlterByCopyTable($tableName)
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep(function ($state) use ($tableName) {
            $createSQL = $this->getDeclaringSql($tableName);

            $tmpTableName = 'tmp_' . $tableName;
            $this->execute(
                sprintf(
                    'ALTER TABLE %s RENAME TO %s',
                    $this->quoteTableName($tableName),
                    $this->quoteTableName($tmpTableName)
                )
            );

            return compact('createSQL', 'tmpTableName') + $state;
        });

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName)
    {
        $instructions = $this->beginAlterByCopyTable($tableName);
        $instructions->addPostStep(function ($state) use ($columnName, $newColumnName) {
            $newState = $this->calculateNewTableColumns($state['tmpTableName'], $columnName, $newColumnName);

            return $newState + $state;
        });

        $instructions->addPostStep(function ($state) use ($columnName, $newColumnName) {
            $sql = str_replace(
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumnName),
                $state['createSQL']
            );
            $this->execute($sql);

            return $state;
        });

        return $this->copyAndDropTmpTable($instructions, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $instructions = $this->beginAlterByCopyTable($tableName);

        $newColumnName = $newColumn->getName();
        $instructions->addPostStep(function ($state) use ($columnName, $newColumnName) {
            $newState = $this->calculateNewTableColumns($state['tmpTableName'], $columnName, $newColumnName);

            return $newState + $state;
        });

        $instructions->addPostStep(function ($state) use ($columnName, $newColumn) {
            $sql = preg_replace(
                sprintf("/%s(?:\/\*.*?\*\/|\([^)]+\)|'[^']*?'|[^,])+([,)])/", $this->quoteColumnName($columnName)),
                sprintf('%s %s$1', $this->quoteColumnName($newColumn->getName()), $this->getColumnSqlDefinition($newColumn)),
                $state['createSQL'],
                1
            );
            $this->execute($sql);

            return $state;
        });

        return $this->copyAndDropTmpTable($instructions, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropColumnInstructions($tableName, $columnName)
    {
        $instructions = $this->beginAlterByCopyTable($tableName);

        $instructions->addPostStep(function ($state) use ($columnName) {
            $newState = $this->calculateNewTableColumns($state['tmpTableName'], $columnName, false);

            return $newState + $state;
        });

        $instructions->addPostStep(function ($state) use ($columnName) {
            $sql = preg_replace(
                sprintf("/%s\s%s.*(,\s(?!')|\)$)/U", preg_quote($this->quoteColumnName($columnName)), preg_quote($state['columnType'])),
                "",
                $state['createSQL']
            );

            if (substr($sql, -2) === ', ') {
                $sql = substr($sql, 0, -2) . ')';
            }

            $this->execute($sql);

            return $state;
        });

        return $this->copyAndDropTmpTable($instructions, $tableName);
    }

    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $indexes = [];
        $rows = $this->fetchAll(sprintf('pragma index_list(%s)', $tableName));

        foreach ($rows as $row) {
            $indexData = $this->fetchAll(sprintf('pragma index_info(%s)', $row['name']));
            if (!isset($indexes[$tableName])) {
                $indexes[$tableName] = ['index' => $row['name'], 'columns' => []];
            }
            foreach ($indexData as $indexItem) {
                $indexes[$tableName]['columns'][] = strtolower($indexItem['name']);
            }
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

        foreach ($indexes as $index) {
            if ($indexName === $index['index']) {
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
        $indexColumnArray = [];
        foreach ($index->getColumns() as $column) {
            $indexColumnArray[] = sprintf('`%s` ASC', $column);
        }
        $indexColumns = implode(',', $indexColumnArray);
        $sql = sprintf(
            'CREATE %s ON %s (%s)',
            $this->getIndexSqlDefinition($table, $index),
            $this->quoteTableName($table->getName()),
            $indexColumns
        );

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

        foreach ($indexes as $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s',
                    $this->quoteColumnName($index['index'])
                ));
            }
        }

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByNameInstructions($tableName, $indexName)
    {
        $indexes = $this->getIndexes($tableName);
        $instructions = new AlterInstructions();

        foreach ($indexes as $index) {
            if ($indexName === $index['index']) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s',
                    $this->quoteColumnName($indexName)
                ));
            }
        }

        return $instructions;
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

        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }
        $missingColumns = array_diff($columns, [$primaryKey]);

        return empty($missingColumns);
    }

    /**
     * Get the primary key from a particular table.
     *
     * @param string $tableName Table Name
     * @return string|null
     */
    protected function getPrimaryKey($tableName)
    {
        $rows = $this->fetchAll(
            "SELECT sql, tbl_name
              FROM (
                    SELECT sql sql, type type, tbl_name tbl_name, name name
                      FROM sqlite_master
                     UNION ALL
                    SELECT sql, type, tbl_name, name
                      FROM sqlite_temp_master
                   )
             WHERE type != 'meta'
               AND sql NOTNULL
               AND name NOT LIKE 'sqlite_%'
             ORDER BY substr(type, 2, 1), name"
        );

        foreach ($rows as $row) {
            if ($row['tbl_name'] === $tableName) {
                if (strpos($row['sql'], 'PRIMARY KEY') !== false) {
                    preg_match_all("/PRIMARY KEY\s*\(`([^`]*)`\)/", $row['sql'], $matches);
                    foreach ($matches[1] as $match) {
                        if (!empty($match)) {
                            return $match;
                        }
                    }
                    preg_match_all("/`([^`]+)`[\w\s]+PRIMARY KEY/", $row['sql'], $matches);
                    foreach ($matches[1] as $match) {
                        if (!empty($match)) {
                            return $match;
                        }
                    }
                }
            }
        }

        return null;
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

        return !array_diff($columns, $foreignKeys);
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
        $rows = $this->fetchAll(
            "SELECT sql, tbl_name
              FROM (
                    SELECT sql sql, type type, tbl_name tbl_name, name name
                      FROM sqlite_master
                     UNION ALL
                    SELECT sql, type, tbl_name, name
                      FROM sqlite_temp_master
                   )
             WHERE type != 'meta'
               AND sql NOTNULL
               AND name NOT LIKE 'sqlite_%'
             ORDER BY substr(type, 2, 1), name"
        );

        foreach ($rows as $row) {
            if ($row['tbl_name'] === $tableName) {
                if (strpos($row['sql'], 'REFERENCES') !== false) {
                    preg_match_all("/\(`([^`]*)`\) REFERENCES/", $row['sql'], $matches);
                    foreach ($matches[1] as $match) {
                        $foreignKeys[] = $match;
                    }
                }
            }
        }

        return $foreignKeys;
    }

    /**
     * @param Table $table The Table
     * @param string $column Column Name
     * @return AlterInstructions
     */
    protected function getAddPrimaryKeyInstructions(Table $table, $column)
    {
        $instructions = $this->beginAlterByCopyTable($table->getName());

        $tableName = $table->getName();
        $instructions->addPostStep(function ($state) use ($column) {
            $sql = preg_replace("/(`$column`)\s+\w+\s+((NOT )?NULL)/", '$1 INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $state['createSQL'], 1);
            $this->execute($sql);

            return $state;
        });

        $instructions->addPostStep(function ($state) {
            $columns = $this->fetchAll(sprintf('pragma table_info(%s)', $this->quoteTableName($state['tmpTableName'])));
            $names = array_map([$this, 'quoteColumnName'], array_column($columns, 'name'));
            $selectColumns = $writeColumns = $names;

            return compact('selectColumns', 'writeColumns') + $state;
        });

        return $this->copyAndDropTmpTable($instructions, $tableName);
    }

    /**
     * @param Table $table Table
     * @param string $column Column Name
     * @return AlterInstructions
     */
    protected function getDropPrimaryKeyInstructions($table, $column)
    {
        $instructions = $this->beginAlterByCopyTable($table->getName());

        $instructions->addPostStep(function ($state) use ($column) {
            $newState = $this->calculateNewTableColumns($state['tmpTableName'], $column, $column);

            return $newState + $state;
        });

        $instructions->addPostStep(function ($state) {
            $search = "/(,?\s*PRIMARY KEY\s*\([^\)]*\)|\s+PRIMARY KEY(\s+AUTOINCREMENT)?)/";
            $sql = preg_replace($search, '', $state['createSQL'], 1);

            if ($sql) {
                $this->execute($sql);
            }

            return $state;
        });

        return $this->copyAndDropTmpTable($instructions, $table->getName());
    }

    /**
     * {@inheritdoc}
     */
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey)
    {
        $instructions = $this->beginAlterByCopyTable($table->getName());

        $tableName = $table->getName();
        $instructions->addPostStep(function ($state) use ($foreignKey) {
            $this->execute('pragma foreign_keys = ON');
            $sql = substr($state['createSQL'], 0, -1) . ',' . $this->getForeignKeySqlDefinition($foreignKey) . ')';
            $this->execute($sql);

            return $state;
        });

        $instructions->addPostStep(function ($state) {
            $columns = $this->fetchAll(sprintf('pragma table_info(%s)', $this->quoteTableName($state['tmpTableName'])));
            $names = array_map([$this, 'quoteColumnName'], array_column($columns, 'name'));
            $selectColumns = $writeColumns = $names;

            return compact('selectColumns', 'writeColumns') + $state;
        });

        return $this->copyAndDropTmpTable($instructions, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint)
    {
        throw new \BadMethodCallException('SQLite does not have named foreign keys');
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyByColumnsInstructions($tableName, $columns)
    {
        $instructions = $this->beginAlterByCopyTable($tableName);

        $instructions->addPostStep(function ($state) use ($columns) {
            $newState = $this->calculateNewTableColumns($state['tmpTableName'], $columns[0], $columns[0]);

            $selectColumns = $newState['selectColumns'];
            $columns = array_map([$this, 'quoteColumnName'], $columns);
            $diff = array_diff($columns, $selectColumns);

            if (!empty($diff)) {
                throw new \InvalidArgumentException(sprintf(
                    'The specified columns don\'t exist: ' . implode(', ', $diff)
                ));
            }

            return $newState + $state;
        });

        $instructions->addPostStep(function ($state) use ($columns) {
            $sql = '';

            foreach ($columns as $columnName) {
                $search = sprintf(
                    "/,[^,]*\(%s(?:,`?(.*)`?)?\) REFERENCES[^,]*\([^\)]*\)[^,)]*/",
                    $this->quoteColumnName($columnName)
                );
                $sql = preg_replace($search, '', $state['createSQL'], 1);
            }

            if ($sql) {
                $this->execute($sql);
            }

            return $state;
        });

        return $this->copyAndDropTmpTable($instructions, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        switch ($type) {
            case static::PHINX_TYPE_TEXT:
            case static::PHINX_TYPE_INTEGER:
            case static::PHINX_TYPE_FLOAT:
            case static::PHINX_TYPE_DECIMAL:
            case static::PHINX_TYPE_DATETIME:
            case static::PHINX_TYPE_TIME:
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_BLOB:
            case static::PHINX_TYPE_BOOLEAN:
            case static::PHINX_TYPE_ENUM:
                return ['name' => $type];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'varchar', 'limit' => 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'char', 'limit' => 255];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'bigint'];
            case static::PHINX_TYPE_TIMESTAMP:
                return ['name' => 'datetime'];
            case static::PHINX_TYPE_BINARY:
                return ['name' => 'blob'];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'char', 'limit' => 36];
            case static::PHINX_TYPE_JSON:
            case static::PHINX_TYPE_JSONB:
                return ['name' => 'text'];
            // Geospatial database types
            // No specific data types exist in SQLite, instead all geospatial
            // functionality is handled in the client. See also: SpatiaLite.
            case static::PHINX_TYPE_GEOMETRY:
            case static::PHINX_TYPE_POLYGON:
                return ['name' => 'text'];
            case static::PHINX_TYPE_LINESTRING:
                return ['name' => 'varchar', 'limit' => 255];
            case static::PHINX_TYPE_POINT:
                return ['name' => 'float'];
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlTypeDef SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlTypeDef)
    {
        if (!preg_match('/^([\w]+)(\(([\d]+)*(,([\d]+))*\))*$/', $sqlTypeDef, $matches)) {
            throw new \RuntimeException('Column type ' . $sqlTypeDef . ' is not supported');
        } else {
            $limit = null;
            $precision = null;
            $type = $matches[1];
            if (count($matches) > 2) {
                $limit = $matches[3] ?: null;
            }
            if (count($matches) > 4) {
                $precision = $matches[5];
            }
            switch ($matches[1]) {
                case 'varchar':
                    $type = static::PHINX_TYPE_STRING;
                    if ($limit === 255) {
                        $limit = null;
                    }
                    break;
                case 'char':
                    $type = static::PHINX_TYPE_CHAR;
                    if ($limit === 255) {
                        $limit = null;
                    }
                    if ($limit === 36) {
                        $type = static::PHINX_TYPE_UUID;
                    }
                    break;
                case 'int':
                    $type = static::PHINX_TYPE_INTEGER;
                    if ($limit === 11) {
                        $limit = null;
                    }
                    break;
                case 'bigint':
                    if ($limit === 11) {
                        $limit = null;
                    }
                    $type = static::PHINX_TYPE_BIG_INTEGER;
                    break;
                case 'blob':
                    $type = static::PHINX_TYPE_BINARY;
                    break;
            }
            if ($type === 'tinyint') {
                if ($matches[3] === 1) {
                    $type = static::PHINX_TYPE_BOOLEAN;
                    $limit = null;
                }
            }

            $this->getSqlType($type);

            return [
                'name' => $type,
                'limit' => $limit,
                'precision' => $precision
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = [])
    {
        touch($name . $this->suffix);
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        return is_file($name . $this->suffix);
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        if (file_exists($name . '.sqlite3')) {
            unlink($name . '.sqlite3');
        }
    }

    /**
     * Gets the SQLite Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $isLiteralType = $column->getType() instanceof Literal;
        if ($isLiteralType) {
            $def = (string)$column->getType();
        } else {
            $sqlType = $this->getSqlType($column->getType());
            $def = strtoupper($sqlType['name']);

            $limitable = in_array(strtoupper($sqlType['name']), $this->definitionsWithLimits);
            if (($column->getLimit() || isset($sqlType['limit'])) && $limitable) {
                $def .= '(' . ($column->getLimit() ?: $sqlType['limit']) . ')';
            }
        }
        if ($column->getPrecision() && $column->getScale()) {
            $def .= '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        }
        if (($values = $column->getValues()) && is_array($values)) {
            $def .= " CHECK({$column->getName()} IN ('" . implode("', '", $values) . "'))";
        }

        $default = $column->getDefault();

        $def .= (!$column->isIdentity() && ($column->isNull() || is_null($default))) ? ' NULL' : ' NOT NULL';
        $def .= $this->getDefaultValueDefinition($default, $column->getType());
        $def .= $column->isIdentity() ? ' PRIMARY KEY AUTOINCREMENT' : '';

        if ($column->getUpdate()) {
            $def .= ' ON UPDATE ' . $column->getUpdate();
        }

        $def .= $this->getCommentDefinition($column);

        return $def;
    }

    /**
     * Gets the comment Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @return string
     */
    protected function getCommentDefinition(Column $column)
    {
        if ($column->getComment()) {
            return ' /* ' . $column->getComment() . ' */ ';
        }

        return '';
    }

    /**
     * Gets the SQLite Index Definition for an Index object.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Index $index Index
     * @return string
     */
    protected function getIndexSqlDefinition(Table $table, Index $index)
    {
        if ($index->getType() === Index::UNIQUE) {
            $def = 'UNIQUE INDEX';
        } else {
            $def = 'INDEX';
        }
        if (is_string($index->getName())) {
            $indexName = $index->getName();
        } else {
            $indexName = $table->getName() . '_';
            foreach ($index->getColumns() as $column) {
                $indexName .= $column . '_';
            }
            $indexName .= 'index';
        }
        $def .= ' `' . $indexName . '`';

        return $def;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array_merge(parent::getColumnTypes(), ['enum', 'json', 'jsonb']);
    }

    /**
     * Gets the SQLite Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey)
    {
        $def = '';
        if ($foreignKey->getConstraint()) {
            $def .= ' CONSTRAINT ' . $this->quoteColumnName($foreignKey->getConstraint());
        } else {
            $columnNames = [];
            foreach ($foreignKey->getColumns() as $column) {
                $columnNames[] = $this->quoteColumnName($column);
            }
            $def .= ' FOREIGN KEY (' . implode(',', $columnNames) . ')';
            $refColumnNames = [];
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

    /**
     * {@inheritDoc}
     *
     */
    public function getDecoratedConnection()
    {
        $options = $this->getOptions();
        $options['quoteIdentifiers'] = true;
        $database = ':memory:';

        if (!empty($options['name'])) {
            $options['database'] = $options['name'];

            if (file_exists($options['name'] . $this->suffix)) {
                $options['database'] = $options['name'] . $this->suffix;
            }
        }

        $driver = new SqliteDriver($options);
        if (method_exists($driver, 'setConnection')) {
            $driver->setConnection($this->connection);
        } else {
            $driver->connection($this->connection);
        }

        return new Connection(['driver' => $driver] + $options);
    }
}
