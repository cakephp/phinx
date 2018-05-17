<?php

namespace Foo\Bar;

use Phinx\Migration\AbstractMigration;

class CreateInitialSchema extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $users = $this->table('users_foo_bar');
        $users->addColumn('username', 'string', ['limit' => 20])
              ->addColumn('password', 'string', ['limit' => 40])
              ->addColumn('password_salt', 'string', ['limit' => 40])
              ->addColumn('email', 'string', ['limit' => 100])
              ->addColumn('first_name', 'string', ['limit' => 30])
              ->addColumn('last_name', 'string', ['limit' => 30])
              ->addColumn('bio', 'string', ['limit' => 160, 'null' => true, 'default' => null])
              ->addColumn('profile_image_url', 'string', ['limit' => 120, 'null' => true, 'default' => null])
              ->addColumn('twitter', 'string', ['limit' => 30, 'null' => true, 'default' => null])
              ->addColumn('role', 'string', ['limit' => 20])
              ->addColumn('confirmed', 'boolean', ['null' => true, 'default' => null])
              ->addColumn('confirmation_key', 'string', ['limit' => 40])
              ->addColumn('created', 'datetime')
              ->addColumn('updated', 'datetime', ['default' => null])
              ->addIndex(['username', 'email'], ['unique' => true])
              ->create();

        // info table
        $info = $this->table('info_foo_bar');
        $info->addColumn('username', 'string', ['limit' => 20])
             ->create();
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
