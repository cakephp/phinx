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

/**
 * TODO: Add column compression encoding support
 */
class RedshiftAdapter extends AbstractPostgresAdapter implements AdapterInterface
{
    const SORTKEY_COMPOUND    = 'compound';
    const SORTKEY_INTERLEAVED = 'interleaved';

    const DISTSTYLE_ALL       = 'all';
    const DISTSTYLE_EVEN      = 'even';
    const DISTSTYLE_KEY       = 'key';

    const REDSHIFT_PORT       = 5439;

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        parent::setOptions($options);

        $options = $this->getOptions();
        if (!isset($options['port'])) {
            $this->options['port'] = self::REDSHIFT_PORT;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        throw new \RuntimeException("Redshift does not support 'CHANGE COLUMN'. Please DROP and recreate the column.");
    }

    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        throw new \RuntimeException('CREATE [UNIQUE] INDEX is not supported on Redshift.');
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        throw new \RuntimeException('DROP INDEX is not supported on Redshift.');
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        throw new \RuntimeException('DROP INDEX is not supported on Redshift.');
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropDatabase', array($name));
        $this->disconnect();
        $this->execute(sprintf('DROP DATABASE %s', $name));
        $this->connect();
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array(
            'string',
            'char',
            'text',
            'integer',
            'biginteger',
            'float',
            'decimal',
            'datetime',
            'timestamp',
            'time',
            'date',
            'boolean',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        if ($result = $this->getCommonSqlTypes($type, $limit)) {
            return $result;
        }

        throw new \RuntimeException('The type: "' . $type . '" is not supported');
    }

    /**
     * Get the table's `diststyle`
     *
     * @param  string $tableName
     * @return string
     */
    public function getDistStyle($tableName)
    {
        $styles = [
            0 => self::DISTSTYLE_EVEN,
            1 => self::DISTSTYLE_KEY,
            8 => self::DISTSTYLE_ALL,
        ];

        $query = "SELECT reldiststyle FROM pg_class WHERE relname = '$tableName'";

        $result = $this->fetchRow($query);
        if ($result) {
            return $styles[$result['reldiststyle']];
        }
    }

    /**
     * Get the table's `distkey`
     *
     * @param  string $tableName
     * @return string
     */
    public function getDistKey($tableName)
    {
        $query = "SELECT \"column\" FROM pg_table_def WHERE tablename = '$tableName' AND distkey = 1";

        $result = $this->fetchRow($query);
        if ($result) {
            return $result['column'];
        }
    }

    /**
     * Check if a column is part of the table's `sortkey`
     *
     * @param  string $tableName
     * @param  string $columnName
     * @return bool
     */
    public function hasSortKey($tableName, $columnName)
    {
        $query = "SELECT sortkey
            FROM pg_table_def
            WHERE tablename = '$tableName'
            AND \"column\" = '$columnName'";

        $result = $this->fetchRow($query);
        if ($result) {
            return $result['sortkey'] != 0;
        }
        return false;
    }

    /**
     * Get the table's `sortkey` columns with positions
     *
     * @param  string $tableName
     * @return bool
     */
    public function getSortKey($tableName)
    {
        $sortkey = array();

        $query = "SELECT \"column\", sortkey
            FROM pg_table_def
            WHERE tablename = '$tableName'
            AND sortkey != 0
            ORDER BY ABS(sortkey) ASC";

        $result = $this->fetchAll($query);
        if ($result) {
            $first = true;
            foreach ($result as $row) {
                if ($first) {
                    $sortkey = array(
                        'type'    => $row['sortkey'] < 0 ? self::SORTKEY_INTERLEAVED : self::SORTKEY_COMPOUND,
                        'columns' => array()
                    );
                    $first = false;
                }
                $sortkey['columns'][] = $row['column'];
            }
        }

        return $sortkey;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTimezoneDefinition()
    {
        throw new \RuntimeException("Redshift does not support 'TIMESTAMP WITH TIME ZONE'");
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdentityType()
    {
        return 'INT IDENTITY(1, 1)';
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
            t.relname as index_name,
            c.attname AS column_name
        FROM pg_attribute c
        LEFT JOIN pg_index i
          ON c.attrelid = i.indrelid
          AND i.indisprimary
          AND c.attnum = ANY(string_to_array(textin(int2vectorout(i.indkey)), ' '))
        LEFT JOIN pg_class t ON t.oid = i.indrelid
        WHERE c.attnum > 0 AND NOT c.attisdropped AND t.relname = '$tableName'
        ORDER BY attnum";

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
        throw new \RuntimeException('CREATE [UNIQUE] INDEX is not supported on Redshift.');
    }

    /**
     * {@inheritdoc}
     */
    protected function getColumnsQuery()
    {
        return "SELECT column_name, data_type, is_nullable,
             column_default, character_maximum_length, numeric_precision, numeric_scale,
             column_default IS NOT NULL AND column_default LIKE '\"identity\"%%' AS is_identity
             FROM information_schema.columns
             WHERE table_name ='%s'";
    }

    /**
     * {@includedoc}
     */
    protected function getTableOptions(array $options)
    {
        $sql = '';

        if (isset($options['diststyle'])) {
            $sql = $this->buildDistStyle($options);
        }

        if (isset($options['distkey'])) {
            if (!is_string($options['distkey'])) {
                throw new \RuntimeException('Invalid DISTKEY value. Must be a string.');
            }

            $sql .= sprintf(
                "DISTKEY(%s) ",
                $this->quoteColumnName($options['distkey'])
            );
        }

        if (isset($options['sortkey'])) {
            $sql .= $this->buildSortKey($options['sortkey']);
        }

        return $sql;
    }

    /**
     * Build the DISTSTYLE definition
     *
     * @param  array $options
     * @return string
     */
    protected function buildDistStyle(array $options)
    {
        $validStyles = array(self::DISTSTYLE_KEY, self::DISTSTYLE_ALL, self::DISTSTYLE_EVEN);

        if (!is_string($options['diststyle']) || !in_array(strtolower($options['diststyle']), $validStyles)) {
            throw new \RuntimeException(sprintf(
                "Invalid DISTSTYLE '%s'. Must be one of: %s",
                $options['diststyle'],
                implode(', ', $valid)
            ));
        }
        $distStyle = strtolower($options['diststyle']);

        if ($distStyle == 'key' && !isset($options['distkey'])) {
            throw new \RuntimeException('Must set a DISTKEY when using DISTSTYLE KEY');
        }

        return "DISTSTYLE $distStyle ";
    }

    /**
     * Build the SORTKEY definition
     *
     * @param  array $sortkey
     * @return string
     */
    protected function buildSortKey(array $sortkey)
    {
        if (!isset($sortkey['columns'])) {
            $sortkey = array('type' => self::SORTKEY_COMPOUND, 'columns' => $sortkey);
        }

        if (!isset($sortkey['type'])) {
            $sortkey['type'] = self::SORTKEY_COMPOUND;
        }

        $valid = array(self::SORTKEY_COMPOUND, self::SORTKEY_INTERLEAVED);

        if (!in_array(strtolower($sortkey['type']), $valid)) {
            throw new \RuntimeException("Invalid SORTKEY type. Must be one of: " . implode (', ', $valid));
        }

        $columns = $sortkey['columns'];
        array_walk($columns, function (&$value, $key) {
            if (!is_string($value)) {
                throw new \RuntimeException("'sortkey' must be an array of columns or ['type' => '...', 'columns' => ['field1', 'field2', ...]]");
            }

            $value = $this->quoteColumnName($value);
        });

        return sprintf(
            "%s SORTKEY (%s)",
            strtoupper($sortkey['type']),
            implode(',', $columns)
        );
    }
}
