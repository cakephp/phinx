<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\PdoAdapter,
    Phinx\Db\Adapter\ProxyAdapter,
    Phinx\Db\Table;

class ProxyAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\ProxyAdapter
     */
    private $adapter;
    
    public function setUp()
    {
        $this->adapter = new ProxyAdapter();
    }
    
    public function tearDown()
    {
        unset($this->adapter);
    }
    
    public function testProxyAdapterCanInvertCreateTable()
    {
        $stub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $stub->expects($this->any())
             ->method('getVersions')
             ->will($this->returnValue(array('20110301080000')));
        
        $this->adapter->setAdapter($stub);
        
        $table = new \Phinx\Db\Table('atable');
        $this->adapter->createTable($table);
        
        $commands = $this->adapter->getInvertedCommands();
        $this->assertEquals('dropTable', $commands[0]['name']);
        $this->assertEquals('atable', $commands[0]['arguments'][0]);
    }
    
    public function testProxyAdapterCanInvertRenameTable()
    {
        $stub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $this->adapter->setAdapter($stub);
        
        $this->adapter->renameTable('oldname', 'newname');
        
        $commands = $this->adapter->getInvertedCommands();
        $this->assertEquals('renameTable' , $commands[0]['name']);
        $this->assertEquals('newname', $commands[0]['arguments'][0]);
        $this->assertEquals('oldname', $commands[0]['arguments'][1]);
    }
}