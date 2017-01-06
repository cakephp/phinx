<?php

use Phinx\Seed\AbstractSeed;

class UserSeeder extends AbstractSeed
{
    public function run()
    {
        $data = array(
            array(
                'name'    => 'foo',
                'created' => date('Y-m-d H:i:s'),
            ),
            array(
                'name'    => 'bar',
                'created' => date('Y-m-d H:i:s'),
            )
        );

        $users = $this->table('users');
        $users->insert($data)
              ->save();
    }
}
