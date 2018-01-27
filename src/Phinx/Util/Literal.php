<?php


namespace Phinx\Util;


class Literal
{
    /**
     * @var string
     */
    private $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public static function from($value)
    {
        return new self($value);
    }
}
