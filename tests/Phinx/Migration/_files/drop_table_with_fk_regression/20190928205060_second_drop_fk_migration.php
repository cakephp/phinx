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
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addForeignKey('customer_id', 'customers', 'id')
            ->update();
    }
}
