.. index::
   single: Writing Migrations

Writing Migrations
==================

Phinx relies on migrations in order to transform your database. Each migration
is represented by a PHP class in a unique file. It is preferred that you write
your migrations using the Phinx PHP API, but raw SQL is also supported.

Creating a New Migration
------------------------

Let's start by creating a new Phinx migration. Run Phinx using the
``create`` command:

.. code-block:: bash

        $ php vendor/bin/phinx create MyNewMigration

This will create a new migration in the format
``YYYYMMDDHHMMSS_my_new_migration.php`` where the first 14 characters are
replaced with the current timestamp down to the second.

Phinx automatically creates a skeleton migration file with two empty methods
and a commented out one:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Change Method.
             *
             * More information on this method is available here:
             * http://docs.phinx.org/en/latest/migrations.html#the-change-method
             *
             * Uncomment this method if you would like to use it.
             *
            public function change()
            {
            }
            */

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
migration you must uncomment the ``change`` method in your migration file. For
example:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class CreateUserLoginsTable extends AbstractMigration
        {
            /**
             * Change Method.
             *
             * More information on this method is available here:
             * http://docs.phinx.org/en/latest/migrations.html#the-change-method
             *
             * Uncomment this method if you would like to use it.
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
``query()`` method returns the result as a
`PDOStatement <http://php.net/manual/en/class.pdostatement.php>`_

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

    These commands run using the PHP Data Objects (PDO) extension which
    defines a lightweight, consistent interface for accessing databases
    in PHP. Always make sure your queries abide with PDOs before using
    the ``execute()`` command. This is especially important when using
    DELIMITERs during insertion of stored procedures or triggers which
    don't support DELIMITERs.

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
                      ->addColumn('updated', 'datetime', array('null' => true))
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

    Phinx automatically creates an auto-incrementing primary key column called ``id`` for every
    table.

The ``id`` option sets the name of the automatically created identity field, while the ``primary_key``
option selects the field or fields used for primary key. The ``primary_key`` option always defaults to
the value of ``id``. Both can be disabled by setting them to false.

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
To simply change the name of the primary key, we need to override the default ``id`` field name:

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
                $table->addColumn('follower_id', 'integer')
                      ->addColumn('created', 'datetime', array('default' => 'CURRENT_TIMESTAMP'))
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

-  biginteger
-  binary
-  boolean
-  date
-  datetime
-  decimal
-  float
-  integer
-  string
-  text
-  time
-  timestamp
-  uuid

In addition, the MySQL adapter supports ``enum`` and ``set`` column types.

In addition, the Postgres adapter supports ``smallint``, ``json``, ``jsonb`` and ``uuid`` column types
(PostgreSQL 9.3 and above).

For valid options, see the `Valid Column Options`_ below.

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
                      ->addColumn('updated', 'datetime', array('null' => true))
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

Get a column list
~~~~~~~~~~~~~~~~~

To retrieve all table columns, simply create a `table` object and call `getColumns()`
method. This method will return an array of Column classes with basic info. Example below:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class ColumnListMigration extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                $columns = $this->table('users')->getColumns();
                ...
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
                ...
            }
        }

Checking whether a column exists
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can check if a table already has a certain column by using the
``hasColumn()`` method.

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
                $table = $this->table('user');
                $column = $table->hasColumn('username');

                if ($column) {
                    // do something
                }

            }
        }

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

When adding a column you can dictate its position using the ``after`` option.

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

Dropping a Column
~~~~~~~~~~~~~~~~~

To drop a column, use the ``removeColumn()`` method.

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
                $table->removeColumn('short_name')
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
                         ->addForeignKey('tag_id', 'tags', 'id', array('delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'))
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

For any column type:

======= ===========
Option  Description
======= ===========
limit   set maximum length for strings, also hints column types in adapters (see note below)
length  alias for ``limit``
default set default value or action
null    allow ``NULL`` values (should not be used with primary keys!)
after   specify the column that a new column should be placed after
comment set a text comment on the column
======= ===========

For ``decimal`` columns:

========= ===========
Option    Description
========= ===========
precision combine with ``scale`` set to set decimial accuracy
scale     combine with ``precision`` to set decimial accuracy
signed    enable or disable the ``unsigned`` option *(only applies to MySQL)*
========= ===========

For ``enum`` and ``set`` columns:

========= ===========
Option    Description
========= ===========
values    Can be a comma separated list or an array of values
========= ===========

For ``integer`` and ``biginteger`` columns:

======== ===========
Option   Description
======== ===========
identity enable or disable automatic incrementing
signed   enable or disable the ``unsigned`` option *(only applies to MySQL)*
======== ===========

For ``timestamp`` columns:

======== ===========
Option   Description
======== ===========
default  set default value (use with ``CURRENT_TIMESTAMP``)
update   set an action to be triggered when the row is updated (use with ``CURRENT_TIMESTAMP``)
timezone enable or disable the ``with time zone`` option for ``time`` and ``timestamp`` columns *(only applies to Postgres)*
======== ===========

For ``boolean``columns:

======== ===========
Option   Description
======== ===========
signed   enable or disable the ``unsigned`` option *(only applies to MySQL)*
======== ===========

For foreign key definitions:

====== ===========
Option Description
====== ===========
update set an action to be triggered when the row is updated
delete set an action to be triggered when the row is deleted
====== ===========

You can pass one or more of these options to any column with the optional
third argument array.

Limit Option and PostgreSQL
~~~~~~~~~~~~~~~~~~~~~~

When using the PostgreSQL adapter, additional hinting of database column type can be
made for ``integer`` columns. Using ``limit`` with one the following options will
modify the column type accordingly:

============ ==============
Limit        Column Type
============ ==============
INT_SMALL    SMALLINT
============ ==============

.. code-block:: php

         use Phinx\Db\Adapter\PostgresAdapter;

         //...

         $table = $this->table('cart_items');
         $table->addColumn('user_id', 'integer')
               ->addColumn('subtype_id', 'integer', array('limit' => PostgresAdapter::INT_SMALL))
               ->create();

Limit Option and MySQL
~~~~~~~~~~~~~~~~~~~~~~

When using the MySQL adapter, additional hinting of database column type can be
made for ``integer``, ``text`` and ``binary`` columns. Using ``limit`` with
one the following options will modify the column type accordingly:

============ ==============
Limit        Column Type
============ ==============
BLOB_TINY    TINYBLOB
BLOB_REGULAR BLOB
BLOB_MEDIUM  MEDIUMBLOB
BLOB_LONG    LONGBLOB
TEXT_TINY    TINYTEXT
TEXT_REGULAR TEXT
TEXT_MEDIUM  MEDIUMTEXT
TEXT_LONG    LONGTEXT
INT_TINY     TINYINT
INT_SMALL    SMALLINT
INT_MEDIUM   MEDIUMINT
INT_REGULAR  INT
INT_BIG      BIGINT
============ ==============

.. code-block:: php

         use Phinx\Db\Adapter\MysqlAdapter;

         //...

         $table = $this->table('cart_items');
         $table->addColumn('user_id', 'integer')
               ->addColumn('product_id', 'integer', array('limit' => MysqlAdapter::INT_BIG))
               ->addColumn('subtype_id', 'integer', array('limit' => MysqlAdapter::INT_SMALL))
               ->addColumn('quantity', 'integer', array('limit' => MysqlAdapter::INT_TINY))
               ->create();

The Save Method
~~~~~~~~~~~~~~~

When working with the Table object Phinx stores certain operations in a
pending changes cache.

When in doubt it is recommended you call this method. It will commit any
pending changes to the database.
