<?php
return [
    'paths' => [
        'migrations' => [
            'application/migrations',
            'application2/migrations',
        ],
        'seeds' => [
            'application/seeds',
            'application2/seeds',
        ],
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'dev',
        'dev' => [
            'adapter' => 'mysql',
            'wrapper' => 'testwrapper',
            'host' => 'localhost',
            'name' => 'testing',
            'user' => 'root',
            'pass' => '',
            'port' => 3306,
        ],
    ],
];
