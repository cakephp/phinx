<?php

namespace Foo\Bar;

use Phinx\Migration\AbstractMigration;

class CreateUserLoginsTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // user logins table
        $table = $this->table('user_logins_foo_bar');
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('created', 'datetime')
              ->create();

        // add a foreign key back to the users table
        $table->addForeignKey('user_id', 'users_foo_bar', ['id'])
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
