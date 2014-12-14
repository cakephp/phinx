.. index::
   single: Commands
   
Commands
========

Phinx is run using a number of commands.

The Create Command
------------------

The Create command is used to create a new migration file. It requires one
argument and that is the name of the migration. The migration name should be
specified in CamelCase format.

.. code-block:: bash
    
        $ phinx create MyNewMigration

Open the new migration file in your text editor to add your database
transformations. Phinx creates migration files using the path specified in your
``phinx.yml`` file. Please see the :doc:`Configuration <configuration>` chapter 
for more information.

The Init Command
----------------

The Init command (short for initialize) is used to prepare your project for
Phinx. This command generates the ``phinx.yml`` file in the root of your
project directory.

.. code-block:: bash
    
        $ cd yourapp
        $ phinx init .

Open this file in your text editor to setup your project configuration. Please
see the :doc:`Configuration <configuration>` chapter for more information.

The Migrate Command
-------------------

The Migrate command runs all of the available migrations, optionally up to a
specific version.

.. code-block:: bash
    
        $ phinx migrate -e development

To migrate to a specific version then use the ``--target`` parameter or ``-t``
for short.

.. code-block:: bash

        $ phinx migrate -e development -t 20110103081132

The Rollback Command
--------------------

The Rollback command is used to undo previous migrations executed by Phinx. It
is the opposite of the Migrate command.

You can rollback to the previous migration by using the ``rollback`` command
with no arguments.

.. code-block:: bash
    
        $ phinx rollback -e development

To rollback all migrations to a specific version then use the ``--target``
parameter or ``-t`` for short.

.. code-block:: bash
    
        $ phinx rollback -e development -t 20120103083322

Specifying 0 as the target version will revert all migrations.

.. code-block:: bash
    
        $ phinx rollback -e development -t 0

The Status Command
------------------

The Status command prints a list of all migrations, along with their current
status. You can use this command to determine which migrations have been run.

.. code-block:: bash
    
        $ phinx status -e development

Configuration File Parameter
----------------------------

When running Phinx from the command line, you may specify a configuration file using the ``--configuration`` or ``-c`` parameter. In addition to YAML, the configuration file may be the computed output of a PHP file as a PHP array:

.. code-block:: php

        <?php
            return array(
                "paths" => array(
                    "migrations" => "application/migrations"
                ),
                "environments" => array(
                    "default_migration_table" => "phinxlog",
                    "default_database" => "dev",
                    "dev" => array(
                        "adapter" => "mysql",
                        "host" => $_ENV['DB_HOST'],
                        "name" => $_ENV['DB_NAME'],
                        "user" => $_ENV['DB_USER'],
                        "pass" => $_ENV['DB_PASS'],
                        "port" => $_ENV['DB_PORT']
                    )
                )
            );

Phinx auto-detects which language parser to use for files with ``*.yml`` and ``*.php`` extensions. The appropriate parser may also be specified via the ``--parser`` and ``-p`` parameters. Anything other than ``"php"`` is treated as YAML.

In case with PHP array you can provide ``connection`` key with existing PDO instance to use omitting other parameters:

.. code-block:: php

        <?php
            return array(
                "paths" => array(
                    "migrations" => "application/migrations"
                ),
                "environments" => array(
                    "default_migration_table" => "phinxlog",
                    "default_database" => "dev",
                    "dev" => array(
                        "connection" => $pdo_instance
                    )
                )
            );
