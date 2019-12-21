.. index::
   single: Commands

Commands
========

Phinx is run using a number of commands.

The Breakpoint Command
----------------------

The Breakpoint command is used to set breakpoints, allowing you to limit
rollbacks. You can toggle the breakpoint of the most recent migration by
not supplying any parameters.

.. code-block:: bash

        $ phinx breakpoint -e development

To toggle a breakpoint on a specific version then use the ``--target``
parameter or ``-t`` for short.

.. code-block:: bash

        $ phinx breakpoint -e development -t 20120103083322

You can remove all the breakpoints by using the ``--remove-all`` parameter
or ``-r`` for short.

.. code-block:: bash

        $ phinx breakpoint -e development -r

You can set or unset (rather than just toggle) the breakpoint on the most
recent migration (or on a specific migration when combined with the
``--target`` or ``-t`` parameter) by using ``-set`` or ``--unset``.

Breakpoints are visible when you run the ``status`` command.

The Create Command
------------------

The Create command is used to create a new migration file. It requires one
argument: the name of the migration. The migration name should be specified in
CamelCase format.

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
        $ phinx init

Optionally you can specify a custom location for Phinx's config file:

.. code-block:: bash

        $ cd yourapp
        $ phinx init ./custom/location/

You can also specify a custom file name:

.. code-block:: bash

        $ cd yourapp
        $ phinx init custom-config.yml

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

Use ``--dry-run`` to print the queries to standard output without executing them

.. code-block:: bash

        $ phinx migrate --dry-run

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

To rollback all migrations to a specific date then use the ``--date``
parameter or ``-d`` for short.

.. code-block:: bash

        $ phinx rollback -e development -d 2012
        $ phinx rollback -e development -d 201201
        $ phinx rollback -e development -d 20120103
        $ phinx rollback -e development -d 2012010312
        $ phinx rollback -e development -d 201201031205
        $ phinx rollback -e development -d 20120103120530

If a breakpoint is set, blocking further rollbacks, you can override the
breakpoint using the ``--force`` parameter or ``-f`` for short.

.. code-block:: bash

        $ phinx rollback -e development -t 0 -f

Use ``--dry-run`` to print the queries to standard output without executing them

.. code-block:: bash

        $ phinx rollback --dry-run

.. note::

        When rolling back, Phinx orders the executed migrations using
        the order specified in the ``version_order`` option of your
        ``phinx.yml`` file.
        Please see the :doc:`Configuration <configuration>` chapter for more information.

The Status Command
------------------

The Status command prints a list of all migrations, along with their current
status. You can use this command to determine which migrations have been run.

.. code-block:: bash

        $ phinx status -e development

This command exits with code 0 if the database is up-to-date (ie. all migrations are up) or one of the following codes otherwise:

* 2: There is at least one missing migration.
* 3: There is at least one down migration.

An exit code of 1 means an application error has occurred.

The Seed Create Command
-----------------------

The Seed Create command can be used to create new database seed classes. It
requires one argument, the name of the class. The class name should be specified
in CamelCase format.

.. code-block:: bash

        $ phinx seed:create MyNewSeeder

Open the new seed file in your text editor to add your database seed commands.
Phinx creates seed files using the path specified in your ``phinx.yml`` file.
Please see the :doc:`Configuration <configuration>` chapter for more information.

The Seed Run Command
--------------------

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
            return [
                "paths" => [
                    "migrations" => "application/migrations"
                ],
                "environments" => [
                    "default_migration_table" => "phinxlog",
                    "default_database" => "dev",
                    "dev" => [
                        "adapter" => "mysql",
                        "host" => $_ENV['DB_HOST'],
                        "name" => $_ENV['DB_NAME'],
                        "user" => $_ENV['DB_USER'],
                        "pass" => $_ENV['DB_PASS'],
                        "port" => $_ENV['DB_PORT']
                    ]
                ]
            ];

Phinx auto-detects which language parser to use for files with ``*.yml``, ``*.json``, and ``*.php`` extensions. The appropriate
parser may also be specified via the ``--parser`` and ``-p`` parameters. Anything other than  ``"json"`` or ``"php"`` is
treated as YAML.

When using a PHP array, you can provide a ``connection`` key with an existing PDO instance. It is also important to pass
the database name too, as Phinx requires this for certain methods such as ``hasTable()``:

.. code-block:: php

        <?php
            return [
                "paths" => [
                    "migrations" => "application/migrations"
                ),
                "environments" => [
                    "default_migration_table" => "phinxlog",
                    "default_database" => "dev",
                    "dev" => [
                        "name" => "dev_db",
                        "connection" => $pdo_instance
                    ]
                ]
            ];

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

        To modify configuration variables at runtime and override ``%%PHINX_DBNAME%%``
        or other another dynamic option, set ``$_SERVER['PHINX_DBNAME']`` before
        running commands. Available options are documented in the Configuration page.

Wrapping Phinx in another Symfony Console Application
-----------------------------------------------------

Phinx can be wrapped and run as part of a separate Symfony console application. This
may be desirable to present a unified interface to the user for all aspects of your
application, or because you wish to run multiple Phinx commands. While you could
run the commands through ``exec`` or use the above ``Phinx\Wrapper\TextWrapper``,
though this makes it hard to deal with the return code and output in a similar fashion
as your application.

Luckily, Symfony makes doing this sort of "meta" command straight-forward:

.. code-block:: php

    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Phinx\Console\PhinxApplication;

    // ...

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $phinx = new PhinxApplication();        
        $command = $phinx->find('migrate');

        $arguments = [
            'command'         => 'migrate',
            '--environment'   => 'production',
            '--configuration' => '/path/to/config/phinx.yml'
        ];
        
        $input = new ArrayInput($arguments);
        $returnCode = $command->run(new ArrayInput($arguments), $output);
        // ...
    }
    
Here, you are instantianting the ``PhinxApplication``, telling it to find the ``migrate``
command, defining the arguments to pass to it (which match the commandline arguments and flags),
and then finally running the command, passing it the same ``OutputInterface`` that your
application uses.

See this `Symfony page <https://symfony.com/doc/current/console/calling_commands.html>`_ for more information.

Using Phinx with PHPUnit
--------------------------

Phinx can be used within your unit tests to prepare or seed the database. You can use it programatically :

.. code-block:: php

        public function setUp ()
        {
          $app = new PhinxApplication();
          $app->setAutoExit(false);
          $app->run(new StringInput('migrate'), new NullOutput());
        }

If you use a memory database, you'll need to give Phinx a specific PDO instance. You can interact with Phinx directly
using the Manager class :

.. code-block:: php

        use PDO;
        use Phinx\Config\Config;
        use Phinx\Migration\Manager;
        use PHPUnit\Framework\TestCase;
        use Symfony\Component\Console\Input\StringInput;
        use Symfony\Component\Console\Output\NullOutput;

        class DatabaseTestCase extends TestCase {

            public function setUp ()
            {
                $pdo = new PDO('sqlite::memory:', null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $configArray = require('phinx.php');
                $configArray['environments']['test'] = [
                    'adapter'    => 'sqlite',
                    'connection' => $pdo
                ];
                $config = new Config($configArray);
                $manager = new Manager($config, new StringInput(' '), new NullOutput());
                $manager->migrate('test');
                $manager->seed('test');
                // You can change default fetch mode after the seeding
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
                $this->pdo = $pdo;
            }

        }
