<?php

namespace Baz;

use Phinx\Seed\AbstractSeed;

class GSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'body' => 'foo',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'body' => 'bar',
                'created' => date('Y-m-d H:i:s'),
            ],
        ];

        $posts = $this->table('posts');
        $posts->insert($data)
              ->save();
    }
}
