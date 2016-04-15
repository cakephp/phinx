<?php

namespace Phinx\Migration\Schema;

use Phinx\Db\Adapter\AdapterInterface;

class Dumper
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return string PHP code to re-create schema.
     *
     * @throws \Exception
     */
    public function dump()
    {
        $tables = $this->getAdapter()->getTables();

        if (!$tables) {
            return "";
        }

        ob_start();
        try {
            require_once dirname(__FILE__) . '/Schema.template.php';
            $dump = ob_get_contents();
        } catch (\Exception $e) {
            ob_end_clean();

            throw $e;
        }
        ob_end_clean();

        return $dump;
    }
}
