<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Adapter;

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql as MysqlDriver;
use InvalidArgumentException;
use PDO;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Util\Literal;
use RuntimeException;

/**
 * Phinx MySQL Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
class MysqlAdapter extends PdoAdapter
{
    protected $signedColumnTypes = [
        'integer' => true,
        'smallinteger' => true,
        'biginteger' => true,
        'float' => true,
        'decimal' => true,
        'double' => true,
        'boolean' => true,
    ];

    const TEXT_TINY = 255;
    const TEXT_SMALL = 255; /* deprecated, alias of TEXT_TINY */
    const TEXT_REGULAR = 65535;
    const TEXT_MEDIUM = 16777215;
    const TEXT_LONG = 4294967295;

    // According to https://dev.mysql.com/doc/refman/5.0/en/blob.html BLOB sizes are the same as TEXT
    const BLOB_TINY = 255;
    const BLOB_SMALL = 255; /* deprecated, alias of BLOB_TINY */
    const BLOB_REGULAR = 65535;
    const BLOB_MEDIUM = 16777215;
    const BLOB_LONG = 4294967295;

    const INT_TINY = 255;
    const INT_SMALL = 65535;
    const INT_MEDIUM = 16777215;
    const INT_REGULAR = 4294967295;
    const INT_BIG = 18446744073709551615;

    const BIT = 64;

    const TYPE_YEAR = 'year';

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function connect()
    {
        if ($this->connection === null) {
            if (!class_exists('PDO') || !in_array('mysql', PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException('You need to enable the PDO_Mysql extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }

            $options = $this->getOptions();

            $dsn = 'mysql:';

            if (!empty($options['unix_socket'])) {
                // use socket connection
                $dsn .= 'unix_socket=' . $options['unix_socket'];
            } else {
                // use network connection
                $dsn .= 'host=' . $options['host'];
                if (!empty($options['port'])) {
                    $dsn .= ';port=' . $options['port'];
                }
            }

            $dsn .= ';dbname=' . $options['name'];

            // charset support
            if (!empty($options['charset'])) {
                $dsn .= ';charset=' . $options['charset'];
            }

            $driverOptions = [];

            // use custom data fetch mode
            if (!empty($options['fetch_mode'])) {
                $driverOptions[PDO::ATTR_DEFAULT_FETCH_MODE] = constant('\PDO::FETCH_' . strtoupper($options['fetch_mode']));
            }

            // support arbitrary \PDO::MYSQL_ATTR_* driver options and pass them to PDO
            // http://php.net/manual/en/ref.pdo-mysql.php#pdo-mysql.constants
            foreach ($options as $key => $option) {
                if (strpos($key, 'mysql_attr_') === 0) {
                    $driverOptions[constant('\PDO::' . strtoupper($key))] = $option;
                }
            }

            $db = $this->createPdoConnection($dsn, $options['user'], $options['pass'], $driverOptions);

            $this->setConnection($db);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function disconnect()
    {
        $this->connection = null;
    }

    /**
     * @inheritDoc
     */
    public function hasTransactions()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->execute('START TRANSACTION');
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function commitTransaction()
    {
        $this->execute('COMMIT');
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function rollbackTransaction()
    {
        $this->execute('ROLLBACK');
    }

    /**
     * @inheritDoc
     */
    public function quoteTableName($tableName)
    {
        return str_replace('.', '`.`', $this->quoteColumnName($tableName));
    }

    /**
     * @inheritDoc
     */
    public function quoteColumnName($columnName)
    {
        return '`' . str_replace('`', '``', $columnName) . '`';
    }

    /**
     * @inheritDoc
     */
    public function hasTable($tableName)
    {
        if ($this->hasCreatedTable($tableName)) {
            return true;
        }

        $options = $this->getOptions();

        $exists = $this->fetchRow(sprintf(
            "SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'",
            $options['name'],
            $tableName
        ));

        return !empty($exists);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        // This method is based on the MySQL docs here: http://dev.mysql.com/doc/refman/5.1/en/create-index.html
        $defaultOptions = [
            'engine' => 'InnoDB',
            'collation' => 'utf8_general_ci',
        ];

        $options = array_merge(
            $defaultOptions,
            array_intersect_key($this->getOptions(), $defaultOptions),
            $table->getOptions()
        );

        // Add the default primary key
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $options['id'] = 'id';
        }

        if (isset($options['id']) && is_string($options['id'])) {
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                   ->setType('integer')
                   ->setSigned(isset($options['signed']) ? $options['signed'] : true)
                   ->setIdentity(true);

            array_unshift($columns, $column);
            if (isset($options['primary_key']) && (array)$options['id'] !== (array)$options['primary_key']) {
                throw new InvalidArgumentException('You cannot enable an auto incrementing ID field and a primary key');
            }
            $options['primary_key'] = $options['id'];
        }

        // TODO - process table options like collation etc

        // process table engine (default to InnoDB)
        $optionsStr = 'ENGINE = InnoDB';
        if (isset($options['engine'])) {
            $optionsStr = sprintf('ENGINE = %s', $options['engine']);
        }

        // process table collation
        if (isset($options['collation'])) {
            $charset = explode('_', $options['collation']);
            $optionsStr .= sprintf(' CHARACTER SET %s', $charset[0]);
            $optionsStr .= sprintf(' COLLATE %s', $options['collation']);
        }

        // set the table comment
        if (isset($options['comment'])) {
            $optionsStr .= sprintf(' COMMENT=%s ', $this->getConnection()->quote($options['comment']));
        }

        // set the table row format
        if (isset($options['row_format'])) {
            $optionsStr .= sprintf(' ROW_FORMAT=%s ', $options['row_format']);
        }

        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';
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

        // set the indexes
        foreach ($indexes as $index) {
            $sql .= ', ' . $this->getIndexSqlDefinition($index);
        }

        $sql .= ') ' . $optionsStr;
        $sql = rtrim($sql);

        // execute the sql
        $this->execute($sql);

        $this->addCreatedTable($table->getName());
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getChangePrimaryKeyInstructions(Table $table, $newColumns)
    {
        $instructions = new AlterInstructions();

        // Drop the existing primary key
        $primaryKey = $this->getPrimaryKey($table->getName());
        if (!empty($primaryKey['columns'])) {
            $instructions->addAlter('DROP PRIMARY KEY');
        }

        // Add the primary key(s)
        if (!empty($newColumns)) {
            $sql = 'ADD PRIMARY KEY (';
            if (is_string($newColumns)) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($newColumns);
            } elseif (is_array($newColumns)) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $newColumns));
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Invalid value for primary key: %s',
                    json_encode($newColumns)
                ));
            }
            $sql .= ')';
            $instructions->addAlter($sql);
        }

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getChangeCommentInstructions(Table $table, $newComment)
    {
        $instructions = new AlterInstructions();

        // passing 'null' is to remove table comment
        $newComment = ($newComment !== null)
            ? $newComment
            : '';
        $sql = sprintf(' COMMENT=%s ', $this->getConnection()->quote($newComment));
        $instructions->addAlter($sql);

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getRenameTableInstructions($tableName, $newTableName)
    {
        $this->updateCreatedTableName($tableName, $newTableName);
        $sql = sprintf(
            'RENAME TABLE %s TO %s',
            $this->quoteTableName($tableName),
            $this->quoteTableName($newTableName)
        );

        return new AlterInstructions([], [$sql]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropTableInstructions($tableName)
    {
        $this->removeCreatedTable($tableName);
        $sql = sprintf('DROP TABLE %s', $this->quoteTableName($tableName));

        return new AlterInstructions([], [$sql]);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
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
     * @inheritDoc
     */
    public function getColumns($tableName)
    {
        $columns = [];
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $columnInfo) {
            $phinxType = $this->getPhinxType($columnInfo['Type']);

            $column = new Column();
            $column->setName($columnInfo['Field'])
                   ->setNull($columnInfo['Null'] !== 'NO')
                   ->setDefault($columnInfo['Default'])
                   ->setType($phinxType['name'])
                   ->setSigned(strpos($columnInfo['Type'], 'unsigned') === false)
                   ->setLimit($phinxType['limit'])
                   ->setScale($phinxType['scale']);

            if ($columnInfo['Extra'] === 'auto_increment') {
                $column->setIdentity(true);
            }

            if (isset($phinxType['values'])) {
                $column->setValues($phinxType['values']);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @inheritDoc
     */
    public function hasColumn($tableName, $columnName)
    {
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $column) {
            if (strcasecmp($column['Field'], $columnName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $alter = sprintf(
            'ADD %s %s',
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );

        if ($column->getAfter()) {
            $alter .= ' AFTER ' . $this->quoteColumnName($column->getAfter());
        }

        return new AlterInstructions([$alter]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName)
    {
        $rows = $this->fetchAll(sprintf('SHOW FULL COLUMNS FROM %s', $this->quoteTableName($tableName)));

        foreach ($rows as $row) {
            if (strcasecmp($row['Field'], $columnName) === 0) {
                $null = ($row['Null'] == 'NO') ? 'NOT NULL' : 'NULL';
                $comment = isset($row['Comment']) ? ' COMMENT ' . '\'' . addslashes($row['Comment']) . '\'' : '';
                $extra = ' ' . strtoupper($row['Extra']);
                if (($row['Default'] !== null)) {
                    $extra .= $this->getDefaultValueDefinition($row['Default']);
                }
                $definition = $row['Type'] . ' ' . $null . $extra . $comment;

                $alter = sprintf(
                    'CHANGE COLUMN %s %s %s',
                    $this->quoteColumnName($columnName),
                    $this->quoteColumnName($newColumnName),
                    $definition
                );

                return new AlterInstructions([$alter]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            "The specified column doesn't exist: " .
            $columnName
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $after = $newColumn->getAfter() ? ' AFTER ' . $this->quoteColumnName($newColumn->getAfter()) : '';
        $alter = sprintf(
            'CHANGE %s %s %s%s',
            $this->quoteColumnName($columnName),
            $this->quoteColumnName($newColumn->getName()),
            $this->getColumnSqlDefinition($newColumn),
            $after
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropColumnInstructions($tableName, $columnName)
    {
        $alter = sprintf('DROP COLUMN %s', $this->quoteColumnName($columnName));

        return new AlterInstructions([$alter]);
    }

    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     *
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $indexes = [];
        $rows = $this->fetchAll(sprintf('SHOW INDEXES FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            if (!isset($indexes[$row['Key_name']])) {
                $indexes[$row['Key_name']] = ['columns' => []];
            }
            $indexes[$row['Key_name']]['columns'][] = strtolower($row['Column_name']);
        }

        return $indexes;
    }

    /**
     * @inheritDoc
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $columns = array_map('strtolower', $columns);
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $index) {
            if ($columns == $index['columns']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    protected function getAddIndexInstructions(Table $table, Index $index)
    {
        $instructions = new AlterInstructions();

        if ($index->getType() == Index::FULLTEXT) {
            // Must be executed separately
            // SQLSTATE[HY000]: General error: 1795 InnoDB presently supports one FULLTEXT index creation at a time
            $alter = sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getIndexSqlDefinition($index)
            );

            $instructions->addPostStep($alter);
        } else {
            $alter = sprintf(
                'ADD %s',
                $this->getIndexSqlDefinition($index)
            );

            $instructions->addAlter($alter);
        }

        return $instructions;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropIndexByColumnsInstructions($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);

        foreach ($indexes as $indexName => $index) {
            if ($columns == $index['columns']) {
                return new AlterInstructions([sprintf(
                    'DROP INDEX %s',
                    $this->quoteColumnName($indexName)
                )]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            "The specified index on columns '%s' does not exist",
            implode(',', $columns)
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropIndexByNameInstructions($tableName, $indexName)
    {
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                return new AlterInstructions([sprintf(
                    'DROP INDEX %s',
                    $this->quoteColumnName($indexName)
                )]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            "The specified index name '%s' does not exist",
            $indexName
        ));
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey($tableName, $columns, $constraint = null)
    {
        $primaryKey = $this->getPrimaryKey($tableName);

        if (empty($primaryKey['constraint'])) {
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
     *
     * @return array
     */
    public function getPrimaryKey($tableName)
    {
        $rows = $this->fetchAll(sprintf(
            "SELECT
                k.constraint_name,
                k.column_name
            FROM information_schema.table_constraints t
            JOIN information_schema.key_column_usage k
                USING(constraint_name,table_name)
            WHERE t.constraint_type='PRIMARY KEY'
                AND t.table_name='%s'",
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
     * @inheritDoc
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
                if ($columns == $key['columns']) {
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
     *
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $foreignKeys = [];
        $rows = $this->fetchAll(sprintf(
            "SELECT
              CONSTRAINT_NAME,
              TABLE_NAME,
              COLUMN_NAME,
              REFERENCED_TABLE_NAME,
              REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_NAME = '%s'
            ORDER BY POSITION_IN_UNIQUE_CONSTRAINT",
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
     * @inheritDoc
     */
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey)
    {
        $alter = sprintf(
            'ADD %s',
            $this->getForeignKeySqlDefinition($foreignKey)
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint)
    {
        $alter = sprintf(
            'DROP FOREIGN KEY %s',
            $constraint
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropForeignKeyByColumnsInstructions($tableName, $columns)
    {
        $instructions = new AlterInstructions();

        foreach ($columns as $column) {
            $rows = $this->fetchAll(sprintf(
                "SELECT
                    CONSTRAINT_NAME
                  FROM information_schema.KEY_COLUMN_USAGE
                  WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    AND TABLE_NAME = '%s'
                    AND COLUMN_NAME = '%s'
                  ORDER BY POSITION_IN_UNIQUE_CONSTRAINT",
                $tableName,
                $column
            ));

            foreach ($rows as $row) {
                $instructions->merge($this->getDropForeignKeyInstructions($tableName, $row['CONSTRAINT_NAME']));
            }
        }

        if (empty($instructions->getAlterParts())) {
            throw new InvalidArgumentException(sprintf(
                "Not foreign key on columns '%s' exist",
                implode(',', $columns)
            ));
        }

        return $instructions;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Phinx\Db\Adapter\UnsupportedColumnTypeException
     */
    public function getSqlType($type, $limit = null)
    {
        switch ($type) {
            case static::PHINX_TYPE_FLOAT:
            case static::PHINX_TYPE_DOUBLE:
            case static::PHINX_TYPE_DECIMAL:
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_ENUM:
            case static::PHINX_TYPE_SET:
            case static::PHINX_TYPE_JSON:
            // Geospatial database types
            case static::PHINX_TYPE_GEOMETRY:
            case static::PHINX_TYPE_POINT:
            case static::PHINX_TYPE_LINESTRING:
            case static::PHINX_TYPE_POLYGON:
                return ['name' => $type];
            case static::PHINX_TYPE_DATETIME:
            case static::PHINX_TYPE_TIMESTAMP:
            case static::PHINX_TYPE_TIME:
                return ['name' => $type, 'limit' => $limit];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'varchar', 'limit' => $limit ?: 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'char', 'limit' => $limit ?: 255];
            case static::PHINX_TYPE_TEXT:
                if ($limit) {
                    $sizes = [
                        // Order matters! Size must always be tested from longest to shortest!
                        'longtext' => static::TEXT_LONG,
                        'mediumtext' => static::TEXT_MEDIUM,
                        'text' => static::TEXT_REGULAR,
                        'tinytext' => static::TEXT_SMALL,
                    ];
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            return ['name' => $name];
                        }
                    }
                }

                return ['name' => 'text'];
            case static::PHINX_TYPE_BINARY:
                return ['name' => 'binary', 'limit' => $limit ?: 255];
            case static::PHINX_TYPE_VARBINARY:
                return ['name' => 'varbinary', 'limit' => $limit ?: 255];
            case static::PHINX_TYPE_BLOB:
                if ($limit) {
                    $sizes = [
                        // Order matters! Size must always be tested from longest to shortest!
                        'longblob' => static::BLOB_LONG,
                        'mediumblob' => static::BLOB_MEDIUM,
                        'blob' => static::BLOB_REGULAR,
                        'tinyblob' => static::BLOB_SMALL,
                    ];
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            return ['name' => $name];
                        }
                    }
                }

                return ['name' => 'blob'];
            case static::PHINX_TYPE_BIT:
                return ['name' => 'bit', 'limit' => $limit ?: 64];
            case static::PHINX_TYPE_SMALL_INTEGER:
                return ['name' => 'smallint', 'limit' => $limit ?: 6];
            case static::PHINX_TYPE_INTEGER:
                if ($limit && $limit >= static::INT_TINY) {
                    $sizes = [
                        // Order matters! Size must always be tested from longest to shortest!
                        'bigint' => static::INT_BIG,
                        'int' => static::INT_REGULAR,
                        'mediumint' => static::INT_MEDIUM,
                        'smallint' => static::INT_SMALL,
                        'tinyint' => static::INT_TINY,
                    ];
                    $limits = [
                        'smallint' => 6,
                        'int' => 11,
                        'bigint' => 20,
                    ];
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            $def = ['name' => $name];
                            if (isset($limits[$name])) {
                                $def['limit'] = $limits[$name];
                            }

                            return $def;
                        }
                    }
                } elseif (!$limit) {
                    $limit = 11;
                }

                return ['name' => 'int', 'limit' => $limit];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'bigint', 'limit' => 20];
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'tinyint', 'limit' => 1];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'char', 'limit' => 36];
            case static::TYPE_YEAR:
                if (!$limit || in_array($limit, [2, 4])) {
                    $limit = 4;
                }

                return ['name' => 'year', 'limit' => $limit];
            default:
                throw new UnsupportedColumnTypeException('Column type "' . $type . '" is not supported by MySQL.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @internal param string $sqlType SQL type
     *
     * @param string $sqlTypeDef
     *
     * @throws \Phinx\Db\Adapter\UnsupportedColumnTypeException
     *
     * @return array Phinx type
     */
    public function getPhinxType($sqlTypeDef)
    {
        $matches = [];
        if (!preg_match('/^([\w]+)(\(([\d]+)*(,([\d]+))*\))*(.+)*$/', $sqlTypeDef, $matches)) {
            throw new UnsupportedColumnTypeException('Column type "' . $sqlTypeDef . '" is not supported by MySQL.');
        } else {
            $limit = null;
            $scale = null;
            $type = $matches[1];
            if (count($matches) > 2) {
                $limit = $matches[3] ? (int)$matches[3] : null;
            }
            if (count($matches) > 4) {
                $scale = (int)$matches[5];
            }
            if ($type === 'tinyint' && $limit === 1) {
                $type = static::PHINX_TYPE_BOOLEAN;
                $limit = null;
            }
            switch ($type) {
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
                case 'tinyint':
                    $type = static::PHINX_TYPE_INTEGER;
                    $limit = static::INT_TINY;
                    break;
                case 'smallint':
                    $type = static::PHINX_TYPE_SMALL_INTEGER;
                    $limit = static::INT_SMALL;
                    break;
                case 'mediumint':
                    $type = static::PHINX_TYPE_INTEGER;
                    $limit = static::INT_MEDIUM;
                    break;
                case 'int':
                    $type = static::PHINX_TYPE_INTEGER;
                    if ($limit === 11) {
                        $limit = null;
                    }
                    break;
                case 'bigint':
                    if ($limit === 20) {
                        $limit = null;
                    }
                    $type = static::PHINX_TYPE_BIG_INTEGER;
                    break;
                case 'bit':
                    $type = static::PHINX_TYPE_BIT;
                    if ($limit === 64) {
                        $limit = null;
                    }
                    break;
                case 'blob':
                    $type = static::PHINX_TYPE_BINARY;
                    break;
                case 'tinyblob':
                    $type = static::PHINX_TYPE_BINARY;
                    $limit = static::BLOB_TINY;
                    break;
                case 'mediumblob':
                    $type = static::PHINX_TYPE_BINARY;
                    $limit = static::BLOB_MEDIUM;
                    break;
                case 'longblob':
                    $type = static::PHINX_TYPE_BINARY;
                    $limit = static::BLOB_LONG;
                    break;
                case 'tinytext':
                    $type = static::PHINX_TYPE_TEXT;
                    $limit = static::TEXT_TINY;
                    break;
                case 'mediumtext':
                    $type = static::PHINX_TYPE_TEXT;
                    $limit = static::TEXT_MEDIUM;
                    break;
                case 'longtext':
                    $type = static::PHINX_TYPE_TEXT;
                    $limit = static::TEXT_LONG;
                    break;
            }

            try {
                // Call this to check if parsed type is supported.
                $this->getSqlType($type, $limit);
            } catch (UnsupportedColumnTypeException $e) {
                $type = Literal::from($type);
            }

            $phinxType = [
                'name' => $type,
                'limit' => $limit,
                'scale' => $scale,
            ];

            if ($type == static::PHINX_TYPE_ENUM || $type == static::PHINX_TYPE_SET) {
                $phinxType['values'] = explode("','", trim($matches[6], "()'"));
            }

            return $phinxType;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function createDatabase($name, $options = [])
    {
        $charset = isset($options['charset']) ? $options['charset'] : 'utf8';

        if (isset($options['collation'])) {
            $this->execute(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET `%s` COLLATE `%s`', $name, $charset, $options['collation']));
        } else {
            $this->execute(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET `%s`', $name, $charset));
        }
    }

    /**
     * @inheritDoc
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
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropDatabase($name)
    {
        $this->execute(sprintf('DROP DATABASE IF EXISTS `%s`', $name));
    }

    /**
     * Gets the MySQL Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     *
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        if ($column->getType() instanceof Literal) {
            $def = (string)$column->getType();
        } else {
            $sqlType = $this->getSqlType($column->getType(), $column->getLimit());
            $def = strtoupper($sqlType['name']);
        }
        if ($column->getPrecision() && $column->getScale()) {
            $def .= '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        } elseif (isset($sqlType['limit'])) {
            $def .= '(' . $sqlType['limit'] . ')';
        }
        if (($values = $column->getValues()) && is_array($values)) {
            $def .= "('" . implode("', '", $values) . "')";
        }
        $def .= $column->getEncoding() ? ' CHARACTER SET ' . $column->getEncoding() : '';
        $def .= $column->getCollation() ? ' COLLATE ' . $column->getCollation() : '';
        $def .= (!$column->isSigned() && isset($this->signedColumnTypes[$column->getType()])) ? ' unsigned' : '';
        $def .= ($column->isNull() == false) ? ' NOT NULL' : ' NULL';
        $def .= $column->isIdentity() ? ' AUTO_INCREMENT' : '';
        $def .= $this->getDefaultValueDefinition($column->getDefault(), $column->getType());

        if ($column->getComment()) {
            $def .= ' COMMENT ' . $this->getConnection()->quote($column->getComment());
        }

        if ($column->getUpdate()) {
            $def .= ' ON UPDATE ' . $column->getUpdate();
        }

        return $def;
    }

    /**
     * Gets the MySQL Index Definition for an Index object.
     *
     * @param \Phinx\Db\Table\Index $index Index
     *
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index)
    {
        $def = '';
        $limit = '';

        if ($index->getType() == Index::UNIQUE) {
            $def .= ' UNIQUE';
        }

        if ($index->getType() == Index::FULLTEXT) {
            $def .= ' FULLTEXT';
        }

        $def .= ' KEY';

        if (is_string($index->getName())) {
            $def .= ' `' . $index->getName() . '`';
        }

        if (!is_array($index->getLimit())) {
            if ($index->getLimit()) {
                $limit = '(' . $index->getLimit() . ')';
            }
            $def .= ' (`' . implode('`,`', $index->getColumns()) . '`' . $limit . ')';
        } else {
            $columns = $index->getColumns();
            $limits = $index->getLimit();
            $def .= ' (';
            foreach ($columns as $column) {
                $limit = !isset($limits[$column]) || $limits[$column] <= 0 ? '' : '(' . $limits[$column] . ')';
                $def .= '`' . $column . '`' . $limit . ', ';
            }
            $def = rtrim($def, ', ');
            $def .= ' )';
        }

        return $def;
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey
     *
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey)
    {
        $def = '';
        if ($foreignKey->getConstraint()) {
            $def .= ' CONSTRAINT ' . $this->quoteColumnName($foreignKey->getConstraint());
        }
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

        return $def;
    }

    /**
     * Describes a database table. This is a MySQL adapter specific method.
     *
     * @param string $tableName Table name
     *
     * @return array
     */
    public function describeTable($tableName)
    {
        $options = $this->getOptions();

        // mysql specific
        $sql = sprintf(
            "SELECT *
             FROM information_schema.tables
             WHERE table_schema = '%s'
             AND table_name = '%s'",
            $options['name'],
            $tableName
        );

        return $this->fetchRow($sql);
    }

    /**
     * Returns MySQL column types (inherited and MySQL specified).
     *
     * @return array
     */
    public function getColumnTypes()
    {
        return array_merge(parent::getColumnTypes(), ['enum', 'set', 'year', 'json']);
    }

    /**
     * @inheritDoc
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

        $driver = new MysqlDriver($options);
        if (method_exists($driver, 'setConnection')) {
            $driver->setConnection($this->connection);
        } else {
            $driver->connection($this->connection);
        }

        return new Connection(['driver' => $driver] + $options);
    }
}
