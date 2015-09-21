<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;

/**
 * Class ConfigReplaceTokensTest
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigReplaceTokensTest extends AbstractConfigTest
{
    /**
     * Data to be saved to $_SERVER and checked later
     * @var array
     */
    protected static $server = array(
        'PHINX_TEST_VAR_1' => 'some-value',
        'NON_PHINX_TEST_VAR_1' => 'some-other-value',
        'PHINX_TEST_VAR_2' => 213456,
    );

    /**
     * Pass vars to $_SERVER
     */
    public function setUp()
    {
        foreach (static::$server as $name => $value) {
            $_SERVER[$name] = $value;
        }
    }

    /**
     * Clean-up
     */
    public function tearDown()
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
        $config = new Config(array(
            'some-var-1' => 'includes/%%PHINX_TEST_VAR_1%%',
            'some-var-2' => 'includes/%%NON_PHINX_TEST_VAR_1%%',
            'some-var-3' => 'includes/%%PHINX_TEST_VAR_2%%',
            'some-var-4' => 123456,
        ));

        $this->assertContains(
            static::$server['PHINX_TEST_VAR_1'].'', // force convert to string
            $config->offsetGet('some-var-1')
        );
        $this->assertNotContains(
            static::$server['NON_PHINX_TEST_VAR_1'].'', // force convert to string
            $config->offsetGet('some-var-2')
        );
        $this->assertContains(
            static::$server['PHINX_TEST_VAR_2'].'', // force convert to string
            $config->offsetGet('some-var-3')
        );
    }

    /**
     * @covers \Phinx\Config\Config::replaceTokens
     * @covers \Phinx\Config\Config::recurseArrayForTokens
     */
    public function testReplaceTokensRecursive()
    {
        $config = new Config(array(
            'folding' => array(
                'some-var-1' => 'includes/%%PHINX_TEST_VAR_1%%',
                'some-var-2' => 'includes/%%NON_PHINX_TEST_VAR_1%%',
                'some-var-3' => 'includes/%%PHINX_TEST_VAR_2%%',
                'some-var-4' => 123456,
            )
        ));

        $folding = $config->offsetGet('folding');

        $this->assertContains(
            static::$server['PHINX_TEST_VAR_1'].'', // force convert to string
            $folding['some-var-1']
        );
        $this->assertNotContains(
            static::$server['NON_PHINX_TEST_VAR_1'].'', // force convert to string
            $folding['some-var-2']
        );
        $this->assertContains(
            static::$server['PHINX_TEST_VAR_2'].'', // force convert to string
            $folding['some-var-3']
        );
    }
}