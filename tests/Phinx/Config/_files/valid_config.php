<?php
return array(
    'version_manager' => array(
        'paths' => array(
            'migrations' => 'application/migrations'
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