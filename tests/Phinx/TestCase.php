<?php
declare(strict_types=1);

namespace Test\Phinx;

use PHPUnit\Framework\Constraint\RegularExpression;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Asserts that a string matches a given regular expression.
     *
     * @param string $pattern Regex pattern
     * @param string $string String to test
     * @param string $message Message
     * @return void
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @codeCoverageIgnore
     */
    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        static::assertThat($string, new RegularExpression($pattern), $message);
    }

    /**
     * Asserts that a string does not match a given regular expression.
     *
     * @param string $pattern Regex pattern
     * @param string $string String to test
     * @param string $message Message
     * @return void
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDoesNotMatchRegularExpression(
        string $pattern,
        string $string,
        string $message = ''
    ): void {
        static::assertThat(
            $string,
            new LogicalNot(
                new RegularExpression($pattern)
            ),
            $message
        );
    }
}
