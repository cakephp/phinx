<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Adapter;

class PdoAdapterTestPDOMock extends \PDO
{
    public function __construct()
    {
    }
}
