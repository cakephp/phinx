<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Util;

class Literal
{
    /**
     * @var string The literal's value
     */
    protected $value;

    /**
     * @param string $value The literal's value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string Returns the literal's value
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * @param string $value The literal's value
     *
     * @return self
     */
    public static function from($value)
    {
        return new self($value);
    }
}
