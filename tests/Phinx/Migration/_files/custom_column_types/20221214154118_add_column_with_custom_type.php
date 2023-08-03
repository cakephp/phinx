<?php
use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class AddColumnWithCustomType extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        $this
            ->table('users')
            ->addColumn('first_name', Column::STRING, ['limit' => 30])
            ->addColumn('last_name', Column::STRING, ['limit' => 30])
            ->addColumn('phone_number', 'phone_number')
            ->addColumn('phone_number_ext', 'phone_number', [
                'null' => false,
                'limit' => 30,
            ])
            ->create();
    }
}
