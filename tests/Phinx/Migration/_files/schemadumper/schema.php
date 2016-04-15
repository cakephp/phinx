<?php

use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function change()
    {
        $this->table('one', array())
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->create();

        $this->table('two', array('id'=>'two_id'))
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->create();

        $this->table('three', array('id'=>false, 'primary_key'=>array('dummy_int', 'dummy_int_null')))
            ->addColumn('name', 'string', array('length'=>50))
            ->addColumn('dummy_int', 'integer')
            ->addColumn('dummy_int_null', 'integer', array('null'=>true))
            ->create();

        $this->table('three', array('id'=>false, 'primary_key'=>array('dummy_int', 'dummy_int_null')))
            ->addForeignKey('dummy_int', 'two', 'dummy_int_null')
            ->update();

    }
}