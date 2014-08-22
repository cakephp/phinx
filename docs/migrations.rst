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
    
        $ phinx create MyNewMigration
        
This will create a new migration in the format
``YYYYMMDDHHMMSS_my_new_migration.php`` where the first 14 characters are
replaced with the current timestamp down to the second.

Phinx automatically creates a skeleton migration file with two empty methods.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
            
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

The AbstractMigration Class
---------------------------

All Phinx migrations extend from the ``AbstractMigration`` class. This class
provides the necessary support to create your database migrations. Database
migrations can transform your database in many ways such as creating new
tables, inserting rows, adding indexes and modifying columns.

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

The Change Method
~~~~~~~~~~~~~~~~~

Phinx 0.2.0 introduced a new feature called reversible migrations. With
reversible migrations you only need to define the ``up`` logic and Phinx can
figure out how to migrate down automatically for you. To define a reversible
migration you must declare a ``change`` method in your migration file. For
example:

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class CreateUserLoginsTable extends AbstractMigration
        {
            /**
             * Change.
             */
            public function change()
            {
                // create the table
                $table = $this->table('user_logins');
                $table->addColumn('user_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->create();
            }
    
            /**
             * Migrate Up.
             */
            public function up()
            {
    
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

When executing this migration Phinx will create the ``user_logins`` table on
the way up and automatically figure out how to drop the table on the way down.
Please be aware that when a ``change`` method exists Phinx will automatically
ignore the ``up`` and ``down`` methods. If you need to use these methods it is
recommended to create a separate migration file.

.. note::

    When creating or updating tables inside a ``change()`` method you must use
    the Table ``create()`` and ``update()`` methods. Phinx cannot automatically
    determine whether a ``save()`` call is creating a new table or modifying an
    existing one.

Phinx can only reverse the following commands:

-  createTable
-  renameTable
-  addColumn
-  renameColumn
-  addIndex
-  addForeignKey

If a command cannot be reversed then Phinx will throw a 
``IrreversibleMigrationException`` exception when it's migrating down.

Executing Queries
-----------------

Queries can be executed with the ``execute()`` and ``query()`` methods. The
``execute()`` method returns the number of affected rows whereas the
``query()`` method returns the result as an array.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                // execute()
                $count = $this->execute('DELETE FROM users'); // returns the number of affected rows

                // query()
                $rows = $this->query('SELECT * FROM users'); // returns the result as an array
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

.. note::

    These commands run using the ``PHP Data Objects (PDO) extension which  defines a lightweight, consistent interface for accessing databases in PHP``. Always make sure your queries abide with PDOs before using the ``execute()`` command; this is especially important when using DELIMITERs during insertion of stored procedures or triggers which don't support DELIMITERs.
        
Fetching Rows
-------------

There are two methods available to fetch rows. The ``fetchRow()`` method will
fetch a single row, whilst the ``fetchAll()`` method will return multiple rows.
Both methods accept raw SQL as their only parameter.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                // fetch a user
                $row = $this->fetchRow('SELECT * FROM users');

                // fetch an array of messages
                $rows = $this->fetchAll('SELECT * FROM messages');
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Working With Tables
-------------------

The Table Object
~~~~~~~~~~~~~~~~

The Table object is one of the most useful APIs provided by Phinx. It allows
you to easily manipulate database tables using PHP code. You can retrieve an
instance of the Table object by calling the ``table()`` method from within
your database migration.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('tableName');
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }
        
You can then manipulate this table using the methods provided by the Table
object.

Creating a Table
~~~~~~~~~~~~~~~~

Creating a table is really easy using the Table object. Let's create a table to
store a collection of users.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
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
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }
        
Columns are added using the ``addColumn()`` method. We create a unique index
for both the username and email columns using the ``addIndex()`` method.
Finally calling ``save()`` commits the changes to the database.

.. note::

    Phinx automatically creates an auto-incrementing primary key for every
    table called ``id``.

To specify an alternate primary key you can specify the ``primary_key`` option
when accessing the Table object. Let's disable the automatic ``id`` column and
create a primary key using two columns instead:

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('followers', array('id' => false, 'primary_key' => array('user_id', 'follower_id')));
                $table->addColumn('user_id', 'integer')
                      ->addColumn('follower_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Setting a single ``primary_key`` doesn't enable the ``AUTO_INCREMENT`` option.
To do this, we need to override the default ``id`` field name:

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('followers', array('id' => 'user_id'));
                $table->addColumn('user_id', 'integer')
                      ->addColumn('follower_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }
        
Valid Column Types
~~~~~~~~~~~~~~~~~~

Column types are specified as strings and can be one of: 

-  string
-  text
-  integer
-  biginteger
-  float
-  decimal
-  datetime
-  timestamp
-  time
-  date
-  binary
-  boolean

In addition, the Postgres adapter supports a ``json`` column type
(PostgreSQL 9.3 and above).

Determining Whether a Table Exists
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can determine whether or not a table exists by using the ``hasTable()``
method.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $exists = $this->hasTable('users');
                if ($exists) {
                    // do something
                }
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Dropping a Table
~~~~~~~~~~~~~~~~

Tables can be dropped quite easily using the ``dropTable()`` method. It is a
good idea to recreate the table again in the ``down()`` method.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $this->dropTable('users');
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
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
            }
        }
        
Renaming a Table
~~~~~~~~~~~~~~~~

To rename a table access an instance of the Table object then call the
``rename()`` method.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('users');
                $table->rename('legacy_users');
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
                $table = $this->table('legacy_users');
                $table->rename('users');
            }
        }

Working With Columns
~~~~~~~~~~~~~~~~~~~~

Renaming a Column
~~~~~~~~~~~~~~~~~

To rename a column access an instance of the Table object then call the
``renameColumn()`` method.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('users');
                $table->renameColumn('bio', 'biography');
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
                $table = $this->table('users');
                $table->renameColumn('biography', 'bio');
            }
        }

Adding a Column After Another Column
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When adding a column you can dictate it's position using the ``after`` option.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Change Method.
             */
            public function change()
            {
                $table = $this->table('users');
                $table->addColumn('city', 'string', array('after' => 'email'))
                      ->update();
            }
        }

Specifying a Column Limit
~~~~~~~~~~~~~~~~~~~~~~~~~

You can limit the maximum length of a column by using the ``limit`` option.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Change Method.
             */
            public function change()
            {
                $table = $this->table('tags');
                $table->addColumn('short_name', 'string', array('limit' => 30))
                      ->update();
            }
        }

Working with Indexes
~~~~~~~~~~~~~~~~~~~~

To add an index to a table you can simply call the ``addIndex()`` method on the
table object.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('users');
                $table->addColumn('city', 'string')
                      ->addIndex(array('city'))
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

By default Phinx instructs the database adapter to create a normal index. We
can pass an additional parameter to the ``addIndex()`` method to specify a
unique index.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('users');
                $table->addColumn('email', 'string')
                      ->addIndex(array('email'), array('unique' => true))
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }
        
Removing indexes is as easy as calling the ``removeIndex()`` method. You must
call this method for each index.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('users');
                $table->removeIndex(array('email'));
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

.. note::

    There is no need to call the ``save()`` method when using 
    ``removeIndex()``. The index will be removed immediately.

Working With Foreign Keys
~~~~~~~~~~~~~~~~~~~~~~~~~

Phinx has support for creating foreign key constraints on your database tables.
Let's add a foreign key to an example table:

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('tags');
                $table->addColumn('tag_name', 'string')
                      ->save();
        
                $refTable = $this->table('tag_relationships');
                $refTable->addColumn('tag_id', 'integer')
                         ->addForeignKey('tag_id', 'tags', 'id', array('delete'=> 'SET_NULL', update=> 'NO_ACTION'))
                         ->save();
                
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

"On delete" and "On update" actions are defined with a 'delete' and 'update' options array. Possibles values are 'SET_NULL', 'NO_ACTION', 'CASCADE' and 'RESTRICT'.

We can also easily check if a foreign key exists:

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('tag_relationships');
                $exists = $table->hasForeignKey('tag_id');
                if ($exists) {
                    // do something
                }
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Finally to delete a foreign key use the ``dropForeignKey`` method.

.. code-block:: php
        
        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $table = $this->table('tag_relationships');
                $table->dropForeignKey('tag_id');
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Valid Column Options
~~~~~~~~~~~~~~~~~~~~

The following are valid column options:

-  limit
-  length
-  default
-  null
-  precision
-  scale
-  after
-  update
-  comment

You can pass one or more of these options to any column with the optional
third argument array.

The default and update column options can accept 'CURRENT_TIMESTAMP' as a value.

The Save Method
~~~~~~~~~~~~~~~

When working with the Table object Phinx stores certain operations in a
pending changes cache.

When in doubt it is recommended you call this method. It will commit any
pending changes to the database.
