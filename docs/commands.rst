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

This command exits with code 0 if the database is up-to-date (ie. all migrations are up) or one of the following codes otherwise:

* 1: There is at least one down migration.
* 2: There is at least one missing migration.

The Seed Create Command
-----------------------

The Seed Create command can be used to create new database seed classes. It
requires one argument and that is the name of the class. The class name should
be specified in CamelCase format.

.. code-block:: bash

        $ phinx seed:create MyNewSeeder

Open the new seed file in your text editor to add your database seed commands.
Phinx creates seed files using the path specified in your ``phinx.yml`` file.
Please see the :doc:`Configuration <configuration>` chapter for more information.

The Seed Run Command
-----------------------

The Seed Run command runs all of the available seed classes or optionally just
one.

.. code-block:: bash

        $ phinx seed:run -e development

To run only one seed class use the ``--seed`` parameter or ``-s`` for short.

.. code-block:: bash

        $ phinx seed:run -e development -s MyNewSeeder

Configuration File Parameter
----------------------------

When running Phinx from the command line, you may specify a configuration file
using the ``--configuration`` or ``-c`` parameter. In addition to YAML, the
configuration file may be the computed output of a PHP file as a PHP array:

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

Phinx auto-detects which language parser to use for files with ``*.yml`` and ``*.php`` extensions. The appropriate
parser may also be specified via the ``--parser`` and ``-p`` parameters. Anything other than ``"php"`` is treated as YAML.

When using a PHP array can you provide a ``connection`` key with an existing PDO instance. It is also important to pass
the database name too as Phinx requires this for certain methods such as ``hasTable()``:

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
                        "name" => "dev_db",
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
