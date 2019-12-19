<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Adapter;

use RuntimeException;

/**
 * Adapter factory and registry.
 *
 * Used for registering adapters and creating instances of adapters.
 *
 * @author Woody Gilk <woody.gilk@gmail.com>
 */
class AdapterFactory
{
    /**
     * @var \Phinx\Db\Adapter\AdapterFactory
     */
    protected static $instance;

    /**
     * Get the factory singleton instance.
     *
     * @return \Phinx\Db\Adapter\AdapterFactory
     */
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Class map of database adapters, indexed by PDO::ATTR_DRIVER_NAME.
     *
     * @var array
     */
    protected $adapters = [
        'mysql' => 'Phinx\Db\Adapter\MysqlAdapter',
        'pgsql' => 'Phinx\Db\Adapter\PostgresAdapter',
        'sqlite' => 'Phinx\Db\Adapter\SQLiteAdapter',
        'sqlsrv' => 'Phinx\Db\Adapter\SqlServerAdapter',
    ];

    /**
     * Class map of adapters wrappers, indexed by name.
     *
     * @var array
     */
    protected $wrappers = [
        'prefix' => 'Phinx\Db\Adapter\TablePrefixAdapter',
        'proxy' => 'Phinx\Db\Adapter\ProxyAdapter',
        'timed' => 'Phinx\Db\Adapter\TimedOutputAdapter',
    ];

    /**
     * Add or replace an adapter with a fully qualified class name.
     *
     * @param string $name
     * @param string $class
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function registerAdapter($name, $class)
    {
        if (!is_subclass_of($class, 'Phinx\Db\Adapter\AdapterInterface')) {
            throw new RuntimeException(sprintf(
                'Adapter class "%s" must implement Phinx\\Db\\Adapter\\AdapterInterface',
                $class
            ));
        }
        $this->adapters[$name] = $class;

        return $this;
    }

    /**
     * Get an adapter class by name.
     *
     * @param string $name
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function getClass($name)
    {
        if (empty($this->adapters[$name])) {
            throw new RuntimeException(sprintf(
                'Adapter "%s" has not been registered',
                $name
            ));
        }

        return $this->adapters[$name];
    }

    /**
     * Get an adapter instance by name.
     *
     * @param string $name
     * @param array $options
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getAdapter($name, array $options)
    {
        $class = $this->getClass($name);

        return new $class($options);
    }

    /**
     * Add or replace a wrapper with a fully qualified class name.
     *
     * @param string $name
     * @param string $class
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function registerWrapper($name, $class)
    {
        if (!is_subclass_of($class, 'Phinx\Db\Adapter\WrapperInterface')) {
            throw new RuntimeException(sprintf(
                'Wrapper class "%s" must be implement Phinx\\Db\\Adapter\\WrapperInterface',
                $class
            ));
        }
        $this->wrappers[$name] = $class;

        return $this;
    }

    /**
     * Get a wrapper class by name.
     *
     * @param string $name
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function getWrapperClass($name)
    {
        if (empty($this->wrappers[$name])) {
            throw new RuntimeException(sprintf(
                'Wrapper "%s" has not been registered',
                $name
            ));
        }

        return $this->wrappers[$name];
    }

    /**
     * Get a wrapper instance by name.
     *
     * @param string $name
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getWrapper($name, AdapterInterface $adapter)
    {
        $class = $this->getWrapperClass($name);

        return new $class($adapter);
    }
}
