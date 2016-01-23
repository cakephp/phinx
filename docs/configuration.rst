.. index::
   single: Configuration

Configuration
=============

When you initialize your project using the :doc:`Init Command<commands>`, Phinx
creates a default file called ``phinx.yml`` in the root of your project directory.
This file uses the YAML data serialization format.

If a ``--configuration`` command line option is given, Phinx will load the
specified file. Otherwise, it will attempt to find ``phinx.php``, ``phinx.json`` or
``phinx.yml`` and load the first file found. See the :doc:`Commands <commands>`
chapter for more information.

.. warning::

    Remember to store the configuration file outside of a publicly accessible
    directory on your webserver. This file contains your database credentials
    and may be accidentally served as plain text.

Note that while JSON and YAML files are *parsed*, the PHP file is *included*.
This means that:

* It must `return` an array of configuration items.
* The variable scope is local, i.e. you would need to explicitly declare
  any global variables your initialization file reads or modifies.
* Its standard output is suppressed.
* Unlike with JSON and YAML, it is possible to omit environment connection details
  and instead specify ``connection`` which must contain an initialized PDO instance.
  This is useful when you want your migrations to interact with your application
  and/or share the same connection. However remember to also pass the database name
  as Phinx cannot infer this from the PDO connection.

.. code-block:: php

   require 'app/init.php';

   global $app;
   $pdo = $app->getDatabase()->getPdo();

   return array('environments' =>
            array(
              'default_database' => 'development',
              'development' => array(
                'name' => 'devdb',
                'connection' => $pdo
              )
            )
          );

Migration Path
--------------

The first option specifies the path to your migration directory. Phinx uses
``%%PHINX_CONFIG_DIR%%/db/migrations`` by default.

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your ``phinx.yml`` file is stored.

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/db/migrations``, you
need to add the following to the yaml configuration.

.. code-block:: yaml

    paths:
        migrations: /your/full/path

You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.

.. code-block:: yaml

    paths:
        migrations: %%PHINX_CONFIG_DIR%%/your/relative/path

Migrations are captured with ``glob``, so you can define a pattern for multiple
directories.

.. code-block:: yaml

    paths:
        migrations: %%PHINX_CONFIG_DIR%%/module/*/{data,scripts}/migrations

Custom Migration Base
---------------------

By default all migrations will extend from Phinx's `AbstractMigration` class.
This can be set to a custom class that extends from `AbstractMigration` by
setting ``migration_base_class`` in your config:

.. code-block:: yaml

    migration_base_class: MyMagicalMigration

Seed Path
---------

The second option specifies the path to your seed directory. Phinx uses
``%%PHINX_CONFIG_DIR%%/db/seeds`` by default.

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your ``phinx.yml`` file is stored.

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/db/seeds``, you
need to add the following to the yaml configuration.

.. code-block:: yaml

    paths:
        seeds: /your/full/path

You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.

.. code-block:: yaml

    paths:
        seeds: %%PHINX_CONFIG_DIR%%/your/relative/path

Environments
------------

One of the key features of Phinx is support for multiple database environments.
You can use Phinx to create migrations on your development environment, then
run the same migrations on your production environment. Environments are
specified under the ``environments`` nested collection. For example:

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_database: development
        production:
            adapter: mysql
            host: localhost
            name: production_db
            user: root
            pass: ''
            port: 3306
            charset: utf8
            collation: utf8_unicode_ci

would define a new environment called ``production``.

In a situation when multiple developers work on the same project and each has
a different environment (e.g. a convention such as ``<environment
type>-<developer name>-<machine name>``), or when you need to have separate
environments for separate purposes (branches, testing, etc) use environment
variable `PHINX_ENVIRONMENT` to override the default environment in the yaml
file:

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`


Table Prefix and Suffix
-----------------------

You can define a table prefix and table suffix:

.. code-block:: yaml

    environments:
        development:
            ....
            table_prefix: dev_
            table_suffix: _v1
        testing:
            ....
            table_prefix: test_
            table_suffix: _v2


Socket Connections
------------------

When using the MySQL adapter, it is also possible to use sockets instead of
network connections. The socket path is configured with ``unix_socket``:

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_database: development
        production:
            adapter: mysql
            name: production_db
            user: root
            pass: ''
            unix_socket: /var/run/mysql/mysql.sock
            charset: utf8

External Variables
------------------

Phinx will automatically grab any environment variable prefixed with ``PHINX_``
and make it available as a token in the config file. The token will have
exactly the same name as the variable but you must access it by wrapping two
``%%`` symbols on either side. e.g: ``%%PHINX_DBUSER%%``. This is especially
useful if you wish to store your secret database credentials directly on the
server and not in a version control system. This feature can be easily
demonstrated by the following example:

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_database: development
        production:
            adapter: mysql
            host: %%PHINX_DBHOST%%
            name: %%PHINX_DBNAME%%
            user: %%PHINX_DBUSER%%
            pass: %%PHINX_DBPASS%%
            port: 3306
            charset: utf8

Supported Adapters
------------------

Phinx currently supports the following database adapters natively:

* `MySQL <http://www.mysql.com/>`_: specify the ``mysql`` adapter.
* `PostgreSQL <http://www.postgresql.org/>`_: specify the ``pgsql`` adapter.
* `SQLite <http://www.sqlite.org/>`_: specify the ``sqlite`` adapter.
* `SQL Server <http://www.microsoft.com/sqlserver>`_: specify the ``sqlsrv`` adapter.

SQLite
`````````````````

Declaring an SQLite database uses a simplified structure:

.. code-block:: yaml

    environments:
        development:
            adapter: sqlite
            name: ./data/derby
        testing:
            adapter: sqlite
            memory: true     # Setting memory to *any* value overrides name

SQL Server
`````````````````

When using the ``sqlsrv`` adapter and connecting to a named instance you should
omit the ``port`` setting as SQL Server will negotiate the port automatically.
Additionally, omit the ``charset: utf8`` or change to ``charset: 65001`` which
corresponds to UTF8 for SQL Server.

Custom Adapters
`````````````````

You can provide a custom adapter by registering an implementation of the `Phinx\\Db\\Adapter\\AdapterInterface`
with `AdapterFactory`:

.. code-block:: php

    $name  = 'fizz';
    $class = 'Acme\Adapter\FizzAdapter';

    AdapterFactory::instance()->registerAdapter($name, $class);

Adapters can be registered any time before `$app->run()` is called, which normally
called by `bin/phinx`.

Aliases
-------

Template creation class names can be aliased and used with the ``--class`` command line option for the :doc:`Create Command <commands>`.

The aliased classes will still be required to implement the ``Phinx\Migration\CreationInterface`` interface.

.. code-block:: yaml

    aliases:
        permission: \Namespace\Migrations\PermissionMigrationTemplateGenerator
        view: \Namespace\Migrations\ViewMigrationTemplateGenerator
