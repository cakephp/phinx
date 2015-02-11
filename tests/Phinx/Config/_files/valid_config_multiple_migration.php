<?php
return array(
    'paths' => array(
        'migrations' => array(
            __DIR__ . '/_migrations',
            __DIR__ . '/_migrations_1'
        ),
    ),
    'environments' => array(
        'default_migration_table' => 'phinxlog',
        'default_database' => 'dev',
        'dev' => array(
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'testing',
            'user' => 'root',
            'pass' => '',
            'port' => 3306
        )
    )
);
