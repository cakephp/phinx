<?php

use Phinx\Seed\AbstractSeed;

class PostSeeder extends AbstractSeed
{
    public function run()
    {
        $data = array(
            array(
                'body'    => 'foo',
                'created' => date('Y-m-d H:i:s'),
            ),
            array(
                'body'    => 'bar',
                'created' => date('Y-m-d H:i:s'),
            )
        );

        $posts = $this->table('posts');
        $posts->insert($data)
              ->save();
    }
}
