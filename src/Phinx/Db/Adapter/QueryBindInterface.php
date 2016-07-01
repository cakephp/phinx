<?php
/**
 * Created by PhpStorm.
 * User: courtney
 * Date: 31/03/15
 * Time: 7:26 AM
 */

namespace Phinx\Db\Adapter;

/**
 * Interface QueryBindInterface
 * @package Phinx\Db\Adapter
 */
interface QueryBindInterface {
    /**
     * Represents a boolean data type.
     */
    const TYPE_BOOL = \PDO::PARAM_BOOL;

    /**
     * Represents the SQL NULL data type.
     */
    const TYPE_NULL = \PDO::PARAM_NULL;

    /**
     * Represents the SQL INTEGER data type.
     */
    const TYPE_INT = \PDO::PARAM_INT;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     */
    const TYPE_STR = \PDO::PARAM_STR;

    /**
     * Represents the SQL large object data type.
     */
    const TYPE_LOB = \PDO::PARAM_LOB;

    /**
     * Get the bind value.
     * @return bool|float|int|string
     */
    public function getValue();

    /**
     * Set the SQL type for the value to be bound to the query as.
     * @param int $bindType
     */
    public function setBindType($bindType);

    /**
     * Get the SQL type the value will be bound as.
     * @return int
     */
    public function getBindType();
}