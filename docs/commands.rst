.. index::
   single: Commands
   
Commands
========

Phinx is run using a number of commands.

The Create Command
------------------

The Init Command
----------------

The Migrate Command
-------------------

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