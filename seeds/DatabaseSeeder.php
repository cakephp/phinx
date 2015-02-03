<?php

use Phinx\Migration\AbstractSeeder;

class DatabaseSeeder extends AbstractSeeder
{
    public function run()
    {
        //$this->call('UsersSeeder');

        $this->table('table')
            ->insert(array('col1' => 'value1'))
            ->insert(array('col2' => 'value2'))
            ->save();
    }
} 