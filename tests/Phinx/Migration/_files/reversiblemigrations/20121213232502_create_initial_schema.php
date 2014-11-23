<?php

use Phinx\Migration\AbstractMigration;

class CreateInitialSchema extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $users = $this->table('users');
        $users->addColumn('username', 'string', array('limit' => 20))
              ->addColumn('password', 'string', array('limit' => 40))
              ->addColumn('password_salt', 'string', array('limit' => 40))
              ->addColumn('email', 'string', array('limit' => 100))
              ->addColumn('first_name', 'string', array('limit' => 30))
              ->addColumn('last_name', 'string', array('limit' => 30))
              ->addColumn('bio', 'string', array('limit' => 160, 'null' => true, 'default' => null))
              ->addColumn('profile_image_url', 'string', array('limit' => 120, 'null' => true, 'default' => null))
              ->addColumn('twitter', 'string', array('limit' => 30, 'null' => true, 'default' => null))
              ->addColumn('role', 'string', array('limit' => 20))
              ->addColumn('confirmed', 'boolean', array('null' => true, 'default' => null))
              ->addColumn('confirmation_key', 'string', array('limit' => 40))
              ->addColumn('created', 'datetime')
              ->addColumn('updated', 'datetime', array('default' => null))
              ->addIndex(array('username', 'email'), array('unique' => true))
              ->create();

        // info table
        $info = $this->table('info');
        $info->addColumn('username', 'string', array('limit' => 20))
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
