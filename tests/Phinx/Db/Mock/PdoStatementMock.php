<?php

namespace Test\Phinx\Db\Mock;

class PdoStatementMock
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
