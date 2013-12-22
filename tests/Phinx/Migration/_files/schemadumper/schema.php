<?php

use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function up()
    {
        $this->table('one', array())
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->save();

        $this->table('two', array('id'=>'two_id'))
            ->addColumn('two_id', 'integer')
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->save();

        $this->table('three', array('id'=>false, 'primary_key'=>array('dummy_int', 'dummy_int_null')))
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