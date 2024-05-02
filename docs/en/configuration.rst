.. index::
   single: Configuration

Configuration
=============

When you initialize your project using the :doc:`Init Command<commands>`, Phinx
creates a default file in the root of your project directory. By default, this
file uses the YAML data serialization format, but you can use the ``--format``
command line option to specify either ``yaml``, ``yml``, ``json``, or ``php``.

If a ``--configuration`` command line option is given, Phinx will load the
specified file. Otherwise, it will attempt to find ``phinx.php``, ``phinx.json``,
``phinx.yml``, or ``phinx.yaml`` and load the first file found. See the
:doc:`Commands <commands>` chapter for more information.

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

    $app = require 'app/phinx.php';
    $pdo = $app->getDatabase()->getPdo();

    return [
        'environments' => [
            'default_environment' => 'development',
            'development' => [
                'name' => 'devdb',
                'connection' => $pdo
            ]
        ]
    ];

Migration Paths
---------------

The first option specifies the path to your migration directory. Phinx uses
``%%PHINX_CONFIG_DIR%%/db/migrations`` by default.

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your phinx configuration file is stored.

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/db/migrations``, you
need to add the following to the configuration.

.. code-block:: yaml

    paths:
        migrations: /your/full/path

You can also provide multiple migration paths by using an array in your configuration:

.. code-block:: yaml

    paths:
        migrations:
            - application/module1/migrations
            - application/module2/migrations


You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/your/relative/path'

Migrations are captured with ``glob``, so you can define a pattern for multiple
directories.

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/module/*/{data,scripts}/migrations'

Custom Migration Base
---------------------

By default all migrations will extend from Phinx's `AbstractMigration` class.
This can be set to a custom class that extends from `AbstractMigration` by
setting ``migration_base_class`` in your config:

.. code-block:: yaml

    migration_base_class: MyMagicalMigration

Seed Paths
----------

The second option specifies the path to your seed directory. Phinx uses
``%%PHINX_CONFIG_DIR%%/db/seeds`` by default.

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your configuration file is stored.

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/db/seeds``, you
need to add the following to the yaml configuration.

.. code-block:: yaml

    paths:
        seeds: /your/full/path

You can also provide multiple seed paths by using an array in your configuration:

.. code-block:: yaml

    paths:
        seeds:
            - /your/full/path1
            - /your/full/path2


You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.

.. code-block:: yaml

    paths:
        seeds: '%%PHINX_CONFIG_DIR%%/your/relative/path'

Custom Seeder Base
---------------------

By default all seeders will extend from Phinx's `AbstractSeed` class.
This can be set to a custom class that extends from `AbstractSeed` by
setting ``seed_base_class`` in your config:

.. code-block:: yaml

    seed_base_class: MyMagicalSeeder

Custom Migration Template
-------------------------

Custom template for Migrations could be used either by defining template file path
in configuration file:

.. code-block:: yaml

    templates:
        file: src/templates/customMigrationTemplate.php


Custom Seeder Template
----------------------

Custom Seeder template could be used either by defining template file path
in configuration file:

.. code-block:: yaml

    templates:
        seedFile: src/templates/customSeederTemplate.php


Environments
------------

One of the key features of Phinx is support for multiple database environments.
You can use Phinx to create migrations on your development environment, then
run the same migrations on your production environment. Environments are
specified under the ``environments`` nested collection. For example:

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            adapter: mysql
            host: localhost
            name: production_db
            user: root
            pass: ''
            port: 3306
            charset: utf8mb4
            collation: utf8mb4_unicode_ci

would define a new environment called ``production``.

In a situation when multiple developers work on the same project and each has
a different environment (e.g. a convention such as ``<environment
type>-<developer name>-<machine name>``), or when you need to have separate
environments for separate purposes (branches, testing, etc) use environment
variable `PHINX_ENVIRONMENT` to override the default environment in the yaml
file:

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`

Migration Table
---------------

To keep track of the migration statuses for an environment, phinx creates
a table to store this information. You can customize where this table
is created by configuring ``default_migration_table`` to be used as default
for all environments:

.. code-block:: yaml

    environment:
        default_migration_table: phinxlog

If this field is omitted, then it will default to ``phinxlog``. For
databases that support it, e.g. Postgres, the schema name can be prefixed
with a period separator (``.``). For example, ``phinx.log`` will create
the table ``log`` in the ``phinx`` schema instead of ``phinxlog`` in the
``public`` (default) schema.

You may also specify the ``migration_table`` on a per environment basis.
Any environment that does not have a ``migration_table`` specified will
fallback to using the ``default_migration_table`` that is defined at the
top level. An example of how you might use this is as follows:

.. code-block:: yaml

    environment:
        default_migration_table: phinxlog
        development:
            migration_table: phinxlog_dev
            # rest of the development settings
        production:
            # rest of the production settings

In the above example, ``development`` will look to the ``phinxlog_dev``
table for migration statues while ``production`` will use ``phinxlog``.

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
        default_environment: development
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
``%%`` symbols on either side. e.g: ``'%%PHINX_DBUSER%%'``. This is especially
useful if you wish to store your secret database credentials directly on the
server and not in a version control system. This feature can be easily
demonstrated by the following example:

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            adapter: mysql
            host: '%%PHINX_DBHOST%%'
            name: '%%PHINX_DBNAME%%'
            user: '%%PHINX_DBUSER%%'
            pass: '%%PHINX_DBPASS%%'
            port: 3306
            charset: utf8

Data Source Names
-----------------

Phinx supports the use of data source names (DSN) to specify the connection
options, which can be useful if you use a single environment variable to hold
the database credentials. PDO has a different DSN formats depending on the
underlying driver, so Phinx uses a database-agnostic DSN format used by other
projects (Doctrine, Rails, AMQP, PaaS, etc).

.. code-block:: text

    <adapter>://[<user>[:<pass>]@]<host>[:<port>]/<name>[?<additionalOptions>]

* A DSN requires at least ``adapter``, ``host`` and ``name``.
* You cannot specify a password without a username.
* ``port`` must be a positive integer.
* ``additionalOptions`` takes the form of a query string, and will be passed to
  the adapter in the options array.

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            # Example data source name
            dsn: mysql://root@localhost:3306/mydb?charset=utf8

Once a DSN is parsed, it's values are merged with the already existing
connection options. Values in specified in a DSN will never override any value
specified directly as connection options.

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        development:
            dsn: '%%DATABASE_URL%%'
        production:
            dsn: '%%DATABASE_URL%%'
            name: production_database

If the supplied DSN is invalid, then it is completely ignored.

Supported Adapters
------------------

Phinx currently supports the following database adapters natively:

* `MySQL <https://www.mysql.com/>`_: specify the ``mysql`` adapter.
* `PostgreSQL <https://www.postgresql.org/>`_: specify the ``pgsql`` adapter.
* `SQLite <https://www.sqlite.org/>`_: specify the ``sqlite`` adapter.
* `SQL Server <https://www.microsoft.com/sqlserver>`_: specify the ``sqlsrv`` adapter.

For each adapter, you may configure the behavior of the underlying PDO object by setting in your
config object the lowercase version of the constant name. This works for both PDO options
(e.g. ``\PDO::ATTR_CASE`` would be ``attr_case``) and adapter specific options (e.g. for MySQL
you may set ``\PDO::MYSQL_ATTR_IGNORE_SPACE`` as ``mysql_attr_ignore_space``). Please consult
the `PDO documentation <https://www.php.net/manual/en/book.pdo.php>`_ for the allowed attributes
and their values.

For example, to set the above example options:

.. code-block:: php

    $config = [
        "environments" => [
            "development" => [
                "adapter" => "mysql",
                # other adapter settings
                "attr_case" => \PDO::ATTR_CASE,
                "mysql_attr_ignore_space" => 1,
            ],
        ],
    ];

By default, the only attribute that Phinx sets is ``\PDO::ATTR_ERRMODE`` to ``PDO::ERRMODE_EXCEPTION``. It is
not recommended to override this.

MySQL
`````````````````

The MySQL adapter has an unfortunate limitation in that it certain actions causes an
`implicit commit <https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html>`_ regardless of transaction
state. Notably this list includes ``CREATE TABLE``, ``ALTER TABLE``, and ``DROP TABLE``, which are the most
common operations that Phinx will run. This means that unlike other adapters which will attempt to gracefully
rollback a transaction on a failed migration, if a migration fails for MySQL, it may leave your DB in a partially
migrated state.

SQLite
`````````````````

Declaring an SQLite database uses a simplified structure:

.. code-block:: yaml

    environments:
        development:
            adapter: sqlite
            name: ./data/derby
            suffix: ".db"    # Defaults to ".sqlite3"
        testing:
            adapter: sqlite
            memory: true     # Setting memory to *any* value overrides name

Starting with PHP 8.1 the SQlite adapter supports ``cache`` and ``mode``
query parameters by using the `URI scheme <https://www.sqlite.org/uri.html>`_ as long as ``open_basedir`` is unset.

.. code-block:: yaml

    environments:
        testing:
            adapter: sqlite
            name: my_app
            mode: memory     # Determines if the new database is opened read-only, read-write, read-write and created if it does not exist, or that the database is a pure in-memory database that never interacts with disk, respectively.
            cache: shared    # Determines if the new database is opened using shared cache mode or with a private cache.

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

Templates
---------

You may override how phinx generates the template used with in a handful of ways:

* file - path to an alternative file to use.
* class - class to use for the template, must implement the ``Phinx\Migration\CreationInterface`` interface.
* style - style to use for template, either ``change`` or ``up_down``, defaults to ``change`` if not set.

You should only use one of these options. These can be overridden by passing command line options to the
:doc:`Create Command <commands`. Example usage within the config file is:

.. code-block:: yaml

    templates:
        style: up_down

Aliases
-------

Template creation class names can be aliased and used with the ``--class`` command line option for the :doc:`Create Command <commands>`.

The aliased classes will still be required to implement the ``Phinx\Migration\CreationInterface`` interface.

.. code-block:: yaml

    aliases:
        permission: \Namespace\Migrations\PermissionMigrationTemplateGenerator
        view: \Namespace\Migrations\ViewMigrationTemplateGenerator

Version Order
-------------

When rolling back or printing the status of migrations, Phinx orders the executed migrations according to the
``version_order`` option, which can have the following values:

* ``creation`` (the default): migrations are ordered by their creation time, which is also part of their filename.
* ``execution``: migrations are ordered by their execution time, also known as start time.

Bootstrap Path
---------------

You can provide a path to a `bootstrap` php file that will be included before any phinx commands are run. Note that
setting External Variables to modify the config will not work because the config has already been parsed by this point.

.. code-block:: yaml

    paths:
        bootstrap: 'phinx-bootstrap.php'

Within the bootstrap script, the following variables will be available:

.. code-block:: php

    /**
     * @var string $filename The file name as provided by the configuration
     * @var string $filePath The absolute, real path to the file
     * @var \Symfony\Component\Console\Input\InputInterface $input The executing command's input object
     * @var \Symfony\Component\Console\Output\OutputInterface $output The executing command's output object
     * @var \Phinx\Console\Command\AbstractCommand $context the executing command object
     */

Feature Flags
-------------

For some breaking changes, Phinx offers a way to opt-out of new behavior. The following flags are available:

* ``unsigned_primary_keys``: Should Phinx create primary keys as unsigned integers? (default: ``true``)
* ``column_null_default``: Should Phinx create columns as null by default? (default: ``true``)

Since MySQL ``TIMESTAMP`` fields do not support dates past 2038-01-19, you have the option to use ``DATETIME`` field
types for fields created by the ``addTimestamps()`` function:

* ``add_timestamps_use_datetime``: Should Phinx create created_at and updated_at fields as datetime? (default: ``false``)

.. code-block:: yaml

    feature_flags:
        unsigned_primary_keys: false

These values can also be set by modifying class fields on the ```Phinx\Config\FeatureFlags``` class, converting
the flag name to ``camelCase``, for example:

.. code-block:: php

    Phinx\Config\FeatureFlags::$unsignedPrimaryKeys = false;
