<?php

use Phinx\Migration\AbstractMigration;

class RenameBioToBiography extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('users');
        $table->renameColumn('bio', 'biography')->save();
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
