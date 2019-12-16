<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Table;

use RuntimeException;

class Index
{
    /**
     * @var string
     */
    const UNIQUE = 'unique';

    /**
     * @var string
     */
    const INDEX = 'index';

    /**
     * @var string
     */
    const FULLTEXT = 'fulltext';

    /**
     * @var array
     */
    protected $columns;

    /**
     * @var string
     */
    protected $type = self::INDEX;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int|array|null
     */
    protected $limit;

    /**
     * Sets the index columns.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Gets the index columns.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Sets the index type.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the index type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the index name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the index name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the index limit.
     *
     * @param int|array $limit limit value or array of limit value
     *
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Gets the index limit.
     *
     * @return int|array
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Utility method that maps an array of index options to this objects methods.
     *
     * @param array $options Options
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function setOptions($options)
    {
        // Valid Options
        $validOptions = ['type', 'unique', 'name', 'limit'];
        foreach ($options as $option => $value) {
            if (!in_array($option, $validOptions, true)) {
                throw new RuntimeException(sprintf('"%s" is not a valid index option.', $option));
            }

            // handle $options['unique']
            if (strcasecmp($option, self::UNIQUE) === 0) {
                if ((bool)$value) {
                    $this->setType(self::UNIQUE);
                }
                continue;
            }

            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }

        return $this;
    }
}
