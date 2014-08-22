.. index::
   single: Configuration
   
Configuration
=============

Phinx uses the YAML data serialization format to store it's configuration data.
When you initialize your project using the :doc:`Init Command<commands>`, Phinx
creates a file called ``phinx.yml`` in the root of your project directory.

.. warning::

    Remember to store the ``phinx.yml`` file outside of a publicly accessible
    directory on your webserver. This file contains your database credentials
    and may be accidentally served as plain text.

If you do not wish to use the default configuration file, you may specify a configuration file (or a file that generates a PHP array) on the command line. See the :doc:`Commands <commands>` chapter for more information.

Migration Path
--------------

The first option specifies the path to your migration directory. Phinx uses 
``%%PHINX_CONFIG_DIR%%/migrations`` by default.

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your ``phinx.yml`` file is stored.

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/migrations``, you need
to add the following to the yaml configuration.

.. code-block:: yaml

    paths:
        migrations: /your/full/path

You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.

.. code-block:: yaml

    paths:
        migrations: %%PHINX_CONFIG_DIR%%/your/relative/path

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

would define a new environment called ``production``.

In a situation when multiple developers work on the same project and each has
a different environment (e.g. a convention such as ``<environment
type>-<developer name>-<machine name>``), or when you need to have separate
environments for separate purposes (branches, testing, etc) use environment
variable `PHINX_ENVIRONMENT` to override the default environment in the yaml
file:

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`

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

Declaring an SQLite database uses a simplified structure:

.. code-block:: yaml

    environments:
        development:
            adapter: sqlite
            name: ./data/derby
        testing:
            adapter: sqlite
            memory: true     # Setting memory to *any* value overrides name

You can provide a custom adapter by registering an implementation of the `Phinx\Db\Adapter\AdapterInterface` with the Environment:

.. code-block:: php

    $customAdapterFactory = function(Phinx\Migration\Manager\Environment $env) {
        // Configure my adapter with the env and return.
        $adapter = new \My\Adapter(...);
        return $adapter;
    }
    $environment->registerAdapter('my-adapter', $customAdapterFactory);
