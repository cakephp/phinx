<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class ShouldExecuteMigration extends AbstractMigration
{
    public function change()
    {
        // info table
        $this->table('info')->create();
    }

    public function shouldExecute(): bool
    {
        return true;
    }
}
