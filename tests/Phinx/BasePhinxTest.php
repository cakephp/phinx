<?php namespace Test\Phinx;

use Pimple\Container;

abstract class BaseCommandTest extends \PHPUnit_Framework_TestCase
{
    protected $di;

    protected function setUp()
    {
        parent::setUp();

        $di = new Container();

        $di['util'] = function() {
            return new \Phinx\Migration\Util();
        };
        $di['command.init'] = function() {
            return new \Phinx\Console\Command\Init();
        };
        $di['command.create'] = function() use($di) {
            return new \Phinx\Console\Command\Create($di);
        };
        $di['command.migrate'] = function() use($di) {
            return new \Phinx\Console\Command\Migrate($di);
        };
        $di['command.rollback'] = function() use($di) {
            return new \Phinx\Console\Command\Rollback($di);
        };
        $di['command.status'] = function() use($di) {
            return new \Phinx\Console\Command\Status($di);
        };
        $di['command.test'] = function() use($di) {
            return new \Phinx\Console\Command\Test($di);
        };

        $this->di = $di;
    }
} 