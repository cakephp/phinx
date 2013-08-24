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
