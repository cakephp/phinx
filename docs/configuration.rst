.. index::
   single: Configuration
   
Configuration
=============

Phinx uses the YAML data serialization format to store it's configuration data.
When you initialize your project using the :doc:`Init Command<commands>`, Phinx
creates a file called ``phinx.yml`` in the root of your project directory.

.. note::

    Remember to store the ``phinx.yml`` file outside of a publicly accessible
    directory on your webserver. This file contains your database credentials
    and may be accidentally served as plain text.

Migration Path
--------------

The first option specifies the path to your migration directory. Phinx uses 
``%%PHINX_CONFIG_DIR%%/migrations`` by default.

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your ``phinx.yml`` file is stored.

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

would define a new environment called ``production``.

In a situation when multiple developers work on the same project and each has
a different environment (e.g. a convention such as <environment
type>-<developer name>-<machine name>), or when you need to have separate
environments for separate purposes (branches, testing, etc) use environment
variable `PHINX_ENVIRONMENT` to override the default environment in the yaml
file:

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`

.. warning:: It is usually not a good idea to commit files that contain passwords, such
             as Phinx configuration files, into your version control. If you insist on doing so,
             make sure the configuration file is outside of the reach of your webserver. In any case,
             never save your *production* settings in version control. The best practice is to keep a
             template (`.dist`) in the repository and have every installation keep its own configuration
             file that is ignored by your version control system.