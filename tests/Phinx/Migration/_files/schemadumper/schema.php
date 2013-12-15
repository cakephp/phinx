<?php

use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function up()
    {
        $this->table('one')
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->save();

        $this->table('two')
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->save();

        $this->table('three')
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->save();

    }

    public function down()
    {
        $this->dropTable('one');
        $this->dropTable('two');
        $this->dropTable('three');
    }
}