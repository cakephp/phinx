<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Util;

class Expression
{
    /**
     * @var string The expression
     */
    private $value;

    /**
     * @param string $value The expression
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string Returns the expression
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * @param string $value The expression
     *
     * @return self
     */
    public static function from($value)
    {
        return new self($value);
    }
}
