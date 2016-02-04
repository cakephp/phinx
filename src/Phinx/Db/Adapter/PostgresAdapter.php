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

class PostgresAdapter extends AbstractPostgresAdapter implements AdapterInterface
{
    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        // TODO - is it possible to merge these 3 queries into less?
        $this->startCommandTimer();
        $this->writeCommand('changeColumn', array($tableName, $columnName, $newColumn->getType()));
        // change data type
        $sql = sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName),
            $this->getColumnSqlDefinition($newColumn)
        );
        //NULL and DEFAULT cannot be set while changing column type
        $sql = preg_replace('/ NOT NULL/', '', $sql);
        $sql = preg_replace('/ NULL/', '', $sql);
        //If it is set, DEFAULT is the last definition
        $sql = preg_replace('/DEFAULT .*/', '', $sql);
        $this->execute($sql);
        // process null
        $sql = sprintf(
            'ALTER TABLE %s ALTER COLUMN %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName)
        );
        if ($newColumn->isNull()) {
            $sql .= ' DROP NOT NULL';
        } else {
            $sql .= ' SET NOT NULL';
        }
        $this->execute($sql);
        if (!is_null($newColumn->getDefault())) {
            //change default
            $this->execute(
                sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s SET %s',
                    $this->quoteTableName($tableName),
                    $this->quoteColumnName($columnName),
                    $this->getDefaultValueDefinition($newColumn->getDefault())
                )
            );
        }
        else {
            //drop default
            $this->execute(
                sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT',
                    $this->quoteTableName($tableName),
                    $this->quoteColumnName($columnName)
                )
            );
        }
        // rename column
        if ($columnName !== $newColumn->getName()) {
            $this->execute(
                sprintf(
                    'ALTER TABLE %s RENAME COLUMN %s TO %s',
                    $this->quoteTableName($tableName),
                    $this->quoteColumnName($columnName),
                    $this->quoteColumnName($newColumn->getName())
                )
            );
        }

        // change column comment if needed
        if ($newColumn->getComment()) {
            $sql = $this->getColumnCommentSqlDefinition($newColumn, $tableName);
            $this->execute($sql);
        }

        $this->endCommandTimer();
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
                        'DROP INDEX IF EXISTS %s',
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
        $sql = sprintf(
            'DROP INDEX IF EXISTS %s',
            $indexName
        );
        $this->execute($sql);
        $this->endCommandTimer();
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
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array_merge(parent::getColumnTypes(), array('json', 'jsonb'));
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
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        if ($result = $this->getCommonSqlTypes($type, $limit)) {
            return $result;
        }

        switch ($type) {
            case static::PHINX_TYPE_TIME:
            case static::PHINX_TYPE_JSON:
            case static::PHINX_TYPE_JSONB:
            case static::PHINX_TYPE_UUID:
                return array('name' => $type);
            case static::PHINX_TYPE_BLOB:
            case static::PHINX_TYPE_BINARY:
                return array('name' => 'bytea');
            // Geospatial database types
            // Spatial storage in Postgres is done via the PostGIS extension,
            // which enables the use of the "geography" type in combination
            // with SRID 4326.
            case static::PHINX_TYPE_GEOMETRY:
                return array('name' => 'geography', 'geometry', 4326);
                break;
            case static::PHINX_TYPE_POINT:
                return array('name' => 'geography', 'point', 4326);
                break;
            case static::PHINX_TYPE_LINESTRING:
                return array('name' => 'geography', 'linestring', 4326);
                break;
            case static::PHINX_TYPE_POLYGON:
                return array('name' => 'geography', 'polygon', 4326);
                break;
            default:
                if ($this->isArrayType($type)) {
                    return array('name' => $type);
                }
                // Return array type
                throw new \RuntimeException('The type: "' . $type . '" is not supported');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getTimezoneDefinition()
    {
        return strtoupper('with time zone');
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
     * {@inheritdoc}
     */
    protected function getIdentityType()
    {
        return 'SERIAL';
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
     * Gets the PostgreSQL Index Definition for an Index object.
     *
     * @param Index  $index Index
     * @param string $tableName Table name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {
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
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $indexName,
            $this->quoteTableName($tableName),
            implode(',', $index->getColumns())
        );
        return $def;
    }

    /**
     * {@inheritdoc}
     */
    protected function getColumnsQuery()
    {
        return "SELECT column_name, data_type, is_identity, is_nullable,
             column_default, character_maximum_length, numeric_precision, numeric_scale
             FROM information_schema.columns
             WHERE table_name ='%s'";
    }
}
