<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\AdapterFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class AdapterFactoryTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\AdapterFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->factory = AdapterFactory::instance();
    }

    protected function tearDown(): void
    {
        unset($this->factory);
    }

    public function testInstanceIsFactory()
    {
        $this->assertInstanceOf('Phinx\Db\Adapter\AdapterFactory', $this->factory);
    }

    public function testRegisterAdapter()
    {
        // AdapterFactory::getClass is protected, work around it to avoid
        // creating unnecessary instances and making the test more complex.
        $method = new ReflectionMethod(get_class($this->factory), 'getClass');
        $method->setAccessible(true);

        $adapter = $method->invoke($this->factory, 'mysql');
        $this->factory->registerAdapter('test', $adapter);

        $this->assertEquals($adapter, $method->invoke($this->factory, 'test'));
    }

    public function testRegisterAdapterFailure()
    {
        $adapter = static::class;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Adapter class "Test\Phinx\Db\Adapter\AdapterFactoryTest" must implement Phinx\Db\Adapter\AdapterInterface');

        $this->factory->registerAdapter('test', $adapter);
    }

    public function testGetAdapter()
    {
        $adapter = $this->factory->getAdapter('mysql', []);

        $this->assertInstanceOf('Phinx\Db\Adapter\MysqlAdapter', $adapter);
    }

    public function testGetAdapterFailure()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Adapter "bad" has not been registered');

        $this->factory->getAdapter('bad', []);
    }

    public function testRegisterWrapper()
    {
        // WrapperFactory::getClass is protected, work around it to avoid
        // creating unnecessary instances and making the test more complex.
        $method = new ReflectionMethod(get_class($this->factory), 'getWrapperClass');
        $method->setAccessible(true);

        $wrapper = $method->invoke($this->factory, 'proxy');
        $this->factory->registerWrapper('test', $wrapper);

        $this->assertEquals($wrapper, $method->invoke($this->factory, 'test'));
    }

    public function testRegisterWrapperFailure()
    {
        $wrapper = static::class;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrapper class "Test\Phinx\Db\Adapter\AdapterFactoryTest" must be implement Phinx\Db\Adapter\WrapperInterface');

        $this->factory->registerWrapper('test', $wrapper);
    }

    private function getAdapterMock()
    {
        return $this->getMockBuilder('Phinx\Db\Adapter\AdapterInterface')->getMock();
    }

    public function testGetWrapper()
    {
        $wrapper = $this->factory->getWrapper('prefix', $this->getAdapterMock());

        $this->assertInstanceOf('Phinx\Db\Adapter\TablePrefixAdapter', $wrapper);
    }

    public function testGetWrapperFailure()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrapper "nope" has not been registered');

        $this->factory->getWrapper('nope', $this->getAdapterMock());
    }
}
