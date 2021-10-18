<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;

/**
 * Class ConfigReplaceTokensTest
 *
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigReplaceTokensTest extends AbstractConfigTest
{
    /**
     * Data to be saved to $_SERVER and checked later
     *
     * @var array<string, mixed>
     */
    protected static $server = [
        'PHINX_TEST_VAR_1' => 'some-value',
        'NON_PHINX_TEST_VAR_1' => 'some-other-value',
        'PHINX_TEST_VAR_2' => 213456,
    ];

    /**
     * Pass vars to $_SERVER
     */
    protected function setUp(): void
    {
        foreach (static::$server as $name => $value) {
            $_SERVER[$name] = $value;
        }
    }

    /**
     * Clean-up
     */
    protected function tearDown(): void
    {
        foreach (static::$server as $name => $value) {
             unset($_SERVER[$name]);
        }
    }

    /**
     * @covers \Phinx\Config\Config::replaceTokens
     * @covers \Phinx\Config\Config::recurseArrayForTokens
     */
    public function testReplaceTokens()
    {
        $config = new Config([
            'some-var-1' => 'includes/%%PHINX_TEST_VAR_1%%',
            'some-var-2' => 'includes/%%NON_PHINX_TEST_VAR_1%%',
            'some-var-3' => 'includes/%%PHINX_TEST_VAR_2%%',
            'some-var-4' => 123456,
        ]);

        $this->assertStringContainsString(
            static::$server['PHINX_TEST_VAR_1'] . '', // force convert to string
            $config->offsetGet('some-var-1')
        );
        $this->assertStringNotContainsString(
            static::$server['NON_PHINX_TEST_VAR_1'] . '', // force convert to string
            $config->offsetGet('some-var-2')
        );
        $this->assertStringContainsString(
            static::$server['PHINX_TEST_VAR_2'] . '', // force convert to string
            $config->offsetGet('some-var-3')
        );
    }

    /**
     * @covers \Phinx\Config\Config::replaceTokens
     * @covers \Phinx\Config\Config::recurseArrayForTokens
     */
    public function testReplaceTokensRecursive()
    {
        $config = new Config([
            'folding' => [
                'some-var-1' => 'includes/%%PHINX_TEST_VAR_1%%',
                'some-var-2' => 'includes/%%NON_PHINX_TEST_VAR_1%%',
                'some-var-3' => 'includes/%%PHINX_TEST_VAR_2%%',
                'some-var-4' => 123456,
            ],
        ]);

        $folding = $config->offsetGet('folding');

        $this->assertStringContainsString(
            static::$server['PHINX_TEST_VAR_1'] . '', // force convert to string
            $folding['some-var-1']
        );
        $this->assertStringNotContainsString(
            static::$server['NON_PHINX_TEST_VAR_1'] . '', // force convert to string
            $folding['some-var-2']
        );
        $this->assertStringContainsString(
            static::$server['PHINX_TEST_VAR_2'] . '', // force convert to string
            $folding['some-var-3']
        );
    }
}
