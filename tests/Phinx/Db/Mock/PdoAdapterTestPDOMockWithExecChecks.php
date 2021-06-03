<?php

namespace Test\Phinx\Db\Mock;

/**
 * A mock PDO that stores its last exec()'d SQL that can be retrieved for queries.
 *
 * This exists as $this->getMockForAbstractClass('\PDO') fails under PHP5.4 and
 * an older PHPUnit; a PDO instance cannot be serialised.
 */
class PdoAdapterTestPDOMockWithExecChecks extends PdoAdapterTestPDOMock
{
    private $sql;

    public function exec($sql)
    {
        $this->sql = $sql;
    }

    public function getExecutedSqlForTest()
    {
        return $this->sql;
    }
}
