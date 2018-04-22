<?php

namespace Foo\Bar;

use Phinx\Migration\AbstractMigration;

class RenameInfoTableToStatusesTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('info_foo_bar');
        $table->rename('statuses_foo_bar')->save();
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
