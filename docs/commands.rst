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

You are able to override the template file used by Phinx by supplying an
alternative template filename.

.. code-block:: bash

        $ phinx create MyNewMigration --template="<file>"

You can also supply a template generating class. This class must implement the
interface ``Phinx\Migration\CreationInterface``.

.. code-block:: bash

        $ phinx create MyNewMigration --class="<class>"

In addition to providing the template for the migration, the class can also define
a callback that will be called once the migration file has been generated from the
template.

You cannot use ``--template`` and ``--class`` together.

The Dump Command
----------------

The Dump command is used to manually create a schema.sql file of the current
status of the target database. This is mainly used to test the seed table 
configuration.

.. code-block:: bash

        $ phinx dump

The output file is configured by the schema path set up in phinx.yml. If you'd
like to use a different path, use the ``--outfile`` option:

.. code-block:: bash

        $ phinx dump --outfile /tmp/my-schema.sql

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

Similar to Ruby on Rails migrations, after a migration is run, the latest state of the
target database is dumped to a schema.sql file. This is a simple sql file containing
create statements for all the current tables, as well as any seed data that has been
configured. See the :doc:`Configuration <configuration>` chapter for more information
about seed data.

If you don't want to run a schema dump after a migration, (if you're migrating a
production instance, for example) use the ``--no-schema-dump`` option to avoid
unnecessarily dumping a schema file.

The Reset Command
-----------------

This command is primarily used for developers. It's an easy way to get the db
back to a fresh, trusted state after making changes. Be aware that this will drop
the target schema completely and recreate it from the structure and seed data stored
in the schema.sql file (which is produced by the ``Dump`` or ``Migrate`` commands.)

The reason for this command is that for larger projects you may have hundreds of 
migrations that add up over time. It's usually more efficient and less error prone to
deploy a fresh database from a single schema file rather than re-executing all of the
migrations.

Since this is an invasive command, many attempts have been made to make sure that you
_really_ want to reset the database. In addition, Phinx will by default refuse to reset an
environment that looks like it might be a production environment (matches this regex 
``/pro?d(uction)?/i``).

You can get around this by using the ``--force`` and ``--no-interaaction`` options if
you know what you're doing.

.. code-block:: bash

        $ phinx reset
        Phinx by Rob Morgan - https://phinx.org. version 0.4.4

        using config file ./phinx.yml
        using config parser yaml
        using migration path /home/cru/phinx/migrations
        warning no environment specified, defaulting to: development
        Are you sure you want to drop and recreate the development database: 'mysql://localhost/development'? yes
        Resetting database at mysql://localhost/development
        $ 


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

Similar to the Migrate command , a rollback also regenerates a schema dump to keep it in
sync with the current version of the database. If you don't want to do this for some
reason, use the ``--no-schema-dump`` option.

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
                    "migrations" => "application/migrations",
                    "schema" => "application/schema.sql"
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
                    "migrations" => "application/migrations",
                    "schema" => "application/schema.sql"
                ),
                "environments" => array(
                    "default_migration_table" => "phinxlog",
                    "default_database" => "dev",
                    "dev" => array(
                        "connection" => $pdo_instance
                    )
                )
            );

Running Phinx in a Web App
--------------------------

Phinx can also be run inside of a web application by using the ``Phinx\Wrapper\TextWrapper``
class. An example of this is provided in ``app/web.php``, which can be run as a
standalone server:

.. code-block:: bash

        $ php -S localhost:8000 vendor/robmorgan/phinx/app/web.php

This will create local web server at `<http://localhost:8000>`__ which will show current
migration status by default. To run migrations up, use `<http://localhost:8000/migrate>`__
and to rollback use `<http://localhost:8000/rollback>`__.

**The included web app is only an example and should not be used in production!**

.. note::

        To modify configuration variables at runtime and overrid ``%%PHINX_DBNAME%%``
        or other another dynamic option, set ``$_SERVER['PHINX_DBNAME']`` before
        running commands. Available options are documented in the Configuration page.

