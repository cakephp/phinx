<?php

namespace Foo\Bar;

use Phinx\Migration\AbstractMigration;

class RenameBioToBiography extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('users_foo_bar');
        $table->renameColumn('bio', 'biography');
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
