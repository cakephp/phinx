<?php

use Phinx\Migration\AbstractMigration;

class SecondDropFkMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('customers')
            ->addColumn('name', 'text')
            ->create();

        $this->table('orders')
            ->addColumn('customer_id', 'integer')
            ->addForeignKey('customer_id', 'customers', 'id')
            ->update();
    }
}
