<?php

namespace Test\Phinx\Seed;

use PDOStatement;
use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Seed\AbstractSeed;
use PHPUnit\Framework\TestCase;

class SeedTest extends TestCase
{
    /**
     * @return void
     */
    public function testHasData(): void
    {
        $queryStub = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $queryStub->expects($this->once())
            ->method('fetchAll')
            ->will($this->returnValue([0 => ['count' => 0]]));

        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('query')
            ->will($this->returnValue($queryStub));

        $stub = $this->getMockForAbstractClass(AbstractSeed::class);
        $stub->setAdapter($adapterStub);
        $result = $stub->hasData('foo');

        $this->assertFalse($result);
    }

    /**
     * @return void
     */
    public function testHasDataTrue(): void
    {
        $queryStub = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $queryStub->expects($this->once())
            ->method('fetchAll')
            ->will($this->returnValue([0 => ['count' => 1]]));

        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('query')
            ->will($this->returnValue($queryStub));

        $stub = $this->getMockForAbstractClass(AbstractSeed::class);
        $stub->setAdapter($adapterStub);
        $result = $stub->hasData('foo');

        $this->assertTrue($result);
    }
}
