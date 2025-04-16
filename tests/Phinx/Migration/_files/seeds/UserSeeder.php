<?php

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Phinx\Seed\AbstractSeed;

class UserSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'foo',
                'created' => new Date(),
            ],
            [
                'name' => 'bar',
                'created' => new DateTime(),
            ],
        ];

        $users = $this->table('users');
        $users->insert($data)
              ->save();
    }
}
