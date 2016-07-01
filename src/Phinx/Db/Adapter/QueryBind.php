<?php

namespace Phinx\Db\Adapter;

/**
 * Defines a value that can be bound to an SQL query as a specific SQL type.
 * @package Phinx\Db\Adapter
 */

class QueryBind implements QueryBindInterface
{
    /**
     * @var bool|float|int|string
     */
    protected $value;

    /**
     * @var int
     */
    protected $bindType;

    /**
     * @param bool|float|int|string $value The value to bind to the SQL query.
     * @param int $bindType The SQL type for the value to be bound to the query as.
     */
    public function __construct($value, $bindType = self::TYPE_STR)
    {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('Invalid bind value type.  Expected a scalar type but received ' . gettype($value));
        }

        $this->value = $value;

        $this->bindType = $bindType;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setBindType($bindType)
    {
        $this->bindType = $bindType;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindType()
    {
        return $this->bindType;
    }
}