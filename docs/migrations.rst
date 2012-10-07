.. index::
   single: Writing Migrations

Writing Migrations
==================

Phinx relies on migrations in order to transform your database. Each migration
is represented by a PHP class in a unique file. It is preferred that you write
your migrations using the Phinx PHP API, but a raw SQL is also supported.

Creating a New Migration
------------------------

Let's start by creating a new Phinx migration. Run Phinx using the
``create`` command.

.. code-block:: bash
    
        phinx create MyNewMigration
        
This will create a new migration in the format
``YYYYMMDDHHMMSS_my_new_migration.php`` where the first 14 characters are
replaced with the current timestamp down to the second.

The AbstractMigration Class
---------------------------

All Phinx migrations extend from the ``AbstractMigration`` class. This class
provides the necessary support to create your database migrations. Database
migrations can transform your database in ways such as creating new tables,
inserting rows, adding indexes and modifying columns.

The Up Method
~~~~~~~~~~~~~

The up method is automatically run by Phinx when you are migrating up and it
detects the given migration hasn't been executed previously. You should use the
up method to transform the database with your intended changes.

The Down Method
~~~~~~~~~~~~~~~

The down method is automatically run by Phinx when you are migrating down and
it detects the given migration has been executed in the past. You should use
the down method to reverse/undo the transformations described in the up method.

Executing Queries
-----------------

Fetching Rows
-------------

Working With Tables
-------------------

The Table Object
~~~~~~~~~~~~~~~~

The Table object is one of the most useful APIs provided by Phinx. It allows
you to easily manipulate database tables using PHP code. You can retrieve an
instance of the Table object by calling the ``table()`` method from within
your database migration.

.. code-block:: bash
    
        $table = $this->table('tableName');

You can then manipulate this table using the methods provided by the Table
object.

Creating a Table
~~~~~~~~~~~~~~~~

Creating a table is really easy using the Table object. Let's create a table to
store a collection of users.

.. code-block:: php

        $users = $this->table('users');
        $users->addColumn('username', 'string', array('limit' => 20))
              ->addColumn('password', 'string', array('limit' => 40))
              ->addColumn('password_salt', 'string', array('limit' => 40))
              ->addColumn('email', 'string', array('limit' => 100))
              ->addColumn('first_name', 'string', array('limit' => 30))
              ->addColumn('last_name', 'string', array('limit' => 30))
              ->addColumn('created', 'datetime')
              ->addColumn('updated', 'datetime', array('default' => null))
              ->addIndex(array('username', 'email'), array('unique' => true))
              ->save();
			  
Columns are added using the ``addColumn()`` method. We create a unique index for
both the username and email columns using the ``addIndex()`` method. Finally
calling ``save()`` commits the changes to the database.

Determining Whether a Table Exists
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Dropping a Table
~~~~~~~~~~~~~~~~

Tables can be dropped quite easily using the ``dropTable`` method of the
``AbstractMigration`` class.

.. code-block:: php
        
        $this->dropTable('tablename');
        
Renaming a Table
~~~~~~~~~~~~~~~~

Working With Columns
~~~~~~~~~~~~~~~~~~~~

The Save Method
~~~~~~~~~~~~~~~

When working with the ``table`` object Phinx stores certain operations in a
pending changes cache.

When in doubt it is recommended you call this method. It will commit any
pending changes to the database.