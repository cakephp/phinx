<?php

use Phinx\Migration\AbstractMigration;

class FirstDropFkMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('orders')
            ->addColumn('order_date', 'timestamp')
            ->create();
    }
}
