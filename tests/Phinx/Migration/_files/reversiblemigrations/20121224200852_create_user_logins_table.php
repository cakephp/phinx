<?php

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class CreateUserLoginsTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // user logins table
        $table = $this->table('user_logins');
        $table->addColumn('user_id', Column::INTEGER, ['signed' => false])
              ->addColumn('created', Column::DATETIME)
              ->create();

        // add a foreign key back to the users table
        $table->addForeignKey('user_id', 'users')
              ->update();
    }

    /**
     * Migrate Up.
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
