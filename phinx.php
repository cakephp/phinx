<?php
return array(
    'paths' => array(
        'migrations' => __DIR__.'/migrations'
    ),
  'environments' =>  array(
      'default_migration_table' => 'phinxlog',
      'default_database' => 'development',
      'production' => array(
          'adapter' => 'mysql',
          'host' => 'localhost',
          'name' => 'production_db',
          'user' => 'root',
          'pass' => '',
          'port' => '3306',
          'charset' => 'utf8',
      ),
      'development' => array(
          'adapter' => 'mysql',
          'host' => 'localhost',
          'name' => 'testing_db',
          'user' => 'root',
          'pass' => '',
          'port' => '3306',
          'charset' => 'utf8',
      ),
      'testing' => array(
          'adapter' => 'mysql',
          'host' => 'localhost',
          'name' => 'testing_db',
          'user' => 'root',
          'pass' => '',
          'port' => '3306',
          'charset' => 'utf8',
      )
  )
);