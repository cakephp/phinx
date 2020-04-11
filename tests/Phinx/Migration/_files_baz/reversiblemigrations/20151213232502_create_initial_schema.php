<?php

namespace Baz;

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class CreateInitialSchema extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $users = $this->table('users_baz');
        $users->addColumn('username', Column::STRING, ['limit' => 20])
              ->addColumn('password', Column::STRING, ['limit' => 40])
              ->addColumn('password_salt', Column::STRING, ['limit' => 40])
              ->addColumn('email', Column::STRING, ['limit' => 100])
              ->addColumn('first_name', Column::STRING, ['limit' => 30])
              ->addColumn('last_name', Column::STRING, ['limit' => 30])
              ->addColumn('bio', Column::STRING, ['limit' => 160, 'null' => true, 'default' => null])
              ->addColumn('profile_image_url', Column::STRING, ['limit' => 120, 'null' => true, 'default' => null])
              ->addColumn('twitter', Column::STRING, ['limit' => 30, 'null' => true, 'default' => null])
              ->addColumn('role', Column::STRING, ['limit' => 20])
              ->addColumn('confirmed', Column::BOOLEAN, ['null' => true, 'default' => null])
              ->addColumn('confirmation_key', Column::STRING, ['limit' => 40])
              ->addColumn('created', Column::DATETIME)
              ->addColumn('updated', Column::DATETIME, ['default' => null])
              ->addIndex(['username', 'email'], ['unique' => true])
              ->create();

        // info table
        $info = $this->table('info_baz');
        $info->addColumn('username', Column::STRING, ['limit' => 20])
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
