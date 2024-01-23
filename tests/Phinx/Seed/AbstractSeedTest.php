<?php
declare(strict_types=1);

namespace Test\Phinx\Seed;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractSeedTest extends TestCase
{
    public function testAdapterMethods()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Seed\AbstractSeed', ['mockenv', 20230102030405]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        // test methods
        $this->expectException(RuntimeException::class);
        $migrationStub->getAdapter();
        $migrationStub->setAdapter($adapterStub);
        $this->assertInstanceOf(
            'Phinx\Db\Adapter\AdapterInterface',
            $migrationStub->getAdapter()
        );
    }
}
