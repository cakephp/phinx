<?php
declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class UserSeederNotExecuted extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'foo',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'bar',
                'created' => date('Y-m-d H:i:s'),
            ],
        ];

        $users = $this->table('users');
        $users->insert($data)
              ->save();
    }

    public function shouldExecute(): bool
    {
        return false;
    }
}
