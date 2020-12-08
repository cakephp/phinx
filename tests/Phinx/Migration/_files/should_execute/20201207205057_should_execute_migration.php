<?php

use Phinx\Migration\AbstractMigration;

class ShouldExecuteMigration extends AbstractMigration
{
    public function change()
    {
        // info table
        $this->table('info')->create();
    }

    public function shouldExecute()
    {
        return true;
    }
}
