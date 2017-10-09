<?php

namespace Foo\Bar;

use Phinx\Migration\AbstractMigration;

class UpdateInfoTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // info table
        $info = $this->table('info_foo_bar');
        $info->addColumn('password', 'string', ['limit' => 40])
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
