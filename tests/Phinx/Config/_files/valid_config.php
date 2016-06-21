<?php
return array(
    'paths' => array(
        'migrations' => array(
            'application/migrations',
            'application2/migrations'
        ),
        'seeds' => array(
            'application/seeds',
            'application2/seeds'
        )
    ),
    'environments' => array(
        'default_migration_table' => 'phinxlog',
        'default_database' => 'dev',
        'dev' => array(
            'adapter' => 'mysql',
            'wrapper' => 'testwrapper',
            'host' => 'localhost',
            'name' => 'testing',
            'user' => 'root',
            'pass' => '',
            'port' => 3306
        )
    )
);
