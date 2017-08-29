<?php
namespace Test\Phinx\Seed;

use Phinx\Seed\AbstractSeed;

class AbstractSeedTest extends \PHPUnit_Framework_TestCase
{
    public function testGetParentSeedWithoutParent()
    {
        /** @var AbstractSeed $sut */
        $sut = $this->getMockForAbstractClass('\Phinx\Seed\AbstractSeed');
        self::assertNull($sut->getParentSeed());
    }

    public function testGetParentSeedWithParent()
    {
        /** @var AbstractSeed $parent */
        $parent = $this->getMockForAbstractClass('\Phinx\Seed\AbstractSeed');

        /** @var AbstractSeed $sut */
        $sut = $this->getMockForAbstractClass('\Phinx\Seed\AbstractSeed');

        $sut->dependsOn($parent);

        self::assertSame($parent, $sut->getParentSeed());
    }
}