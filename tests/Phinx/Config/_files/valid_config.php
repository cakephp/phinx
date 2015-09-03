<?php
return array(
    'paths' => array(
        'migrations' => 'application/migrations'
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
