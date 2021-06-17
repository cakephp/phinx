<?php

namespace Test\Phinx\Db\Mock;

class MockPdoStatement
{
    public function execute()
    {
        return true;
    }

    public function rowCount()
    {
        return 1;
    }
}
