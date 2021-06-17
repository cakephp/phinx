<?php

namespace Test\Phinx\Migration\Manager\Mock;

class PDOMock extends \PDO
{
    /**
     * @var array
     */
    protected $attributes = [];

    public function __construct()
    {
    }

    /**
     * @param int $attribute Attribute
     * @return string
     */
    public function getAttribute($attribute)
    {
        return $this->attributes[$attribute] ?? 'pdomock';
    }

    /**
     * @param int $attribute Attribute
     * @param mixed $value Value
     * @return bool
     */
    public function setAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;

        return true;
    }
}
