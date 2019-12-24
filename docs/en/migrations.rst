.. index::
   single: Writing Migrations

Writing Migrations
==================

Phinx relies on migrations in order to transform your database. Each migration
is represented by a PHP class in a unique file. It is preferred that you write
your migrations using the Phinx PHP API, but raw SQL is also supported.

Creating a New Migration
------------------------
Generating a skeleton migration file
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Let's start by creating a new Phinx migration. Run Phinx using the ``create``
command:

.. code-block:: bash

        $ vendor/bin/phinx create MyNewMigration

This will create a new migration in the format
``YYYYMMDDHHMMSS_my_new_migration.php``, where the first 14 characters are
replaced with the current timestamp down to the second.

If you have specified multiple migration paths, you will be asked to select
which path to create the new migration in.

Phinx automatically creates a skeleton migration file with a single method:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Change Method.
             *
             * Write your reversible migrations using this method.
             *
             * More information on writing migrations is available here:
             * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
             *
             * The following commands can be used in this method and Phinx will
             * automatically reverse them when rolling back:
             *
             *    createTable
             *    renameTable
             *    addColumn
             *    renameColumn
             *    addIndex
             *    addForeignKey
             *
             */
            public function change()
            {

            }
        }

All Phinx migrations extend from the ``AbstractMigration`` class. This class
provides the necessary support to create your database migrations. Database
migrations can transform your database in many ways, such as creating new
tables, inserting rows, adding indexes and modifying columns.

The Change Method
~~~~~~~~~~~~~~~~~

Phinx 0.2.0 introduced a new feature called reversible migrations. This feature
has now become the default migration method. With reversible migrations, you
only need to define the ``up`` logic, and Phinx can figure out how to migrate
down automatically for you. For example:

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

When executing this migration, Phinx will create the ``user_logins`` table on
the way up and automatically figure out how to drop the table on the way down.
Please be aware that when a ``change`` method exists, Phinx will automatically
ignore the ``up`` and ``down`` methods. If you need to use these methods it is
recommended to create a separate migration file.

..note
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

If a command cannot be reversed then Phinx will throw an
``IrreversibleMigrationException`` when it's migrating down.

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
                $stmt = $this->query('SELECT * FROM users'); // returns PDOStatement
                $rows = $stmt->fetchAll(); // returns the result as an array
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

.. warning::

    When using ``execute()`` or ``query()`` with a batch of queries, PDO doesn't
    throw an exception if there is an issue with one or more of the queries
    in the batch.

    As such, the entire batch is assumed to have passed without issue.

    If Phinx was to iterate any potential result sets, looking to see if one
    had an error, then Phinx would be denying access to all the results as there
    is no facility in PDO to get a previous result set
    `nextRowset() <http://php.net/manual/en/pdostatement.nextrowset.php>`_ -
    but no ``previousSet()``).

    So, as a consequence, due to the design decision in PDO to not throw
    an exception for batched queries, Phinx is unable to provide the fullest
    support for error handling when batches of queries are supplied.

    Fortunately though, all the features of PDO are available, so multiple batches
    can be controlled within the migration by calling upon
    `nextRowset() <http://php.net/manual/en/pdostatement.nextrowset.php>`_
    and examining `errorInfo <http://php.net/manual/en/pdostatement.errorinfo.php>`_.

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

Inserting Data
--------------

Phinx makes it easy to insert data into your tables. Whilst this feature is
intended for the :doc:`seed feature <seeding>`, you are also free to use the
insert methods in your migrations.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class NewStatus extends AbstractMigration
        {
            /**
             * Migrate Up.
             */
            public function up()
            {
                // inserting only one row
                $singleRow = [
                    'id'    => 1,
                    'name'  => 'In Progress'
                ];

                $table = $this->table('status');
                $table->insert($singleRow);
                $table->saveData();

                // inserting multiple rows
                $rows = [
                    [
                      'id'    => 2,
                      'name'  => 'Stopped'
                    ],
                    [
                      'id'    => 3,
                      'name'  => 'Queued'
                    ]
                ];

                $this->table('status')->insert($rows)->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
                $this->execute('DELETE FROM status');
            }
        }

.. note::

    You cannot use the insert methods inside a `change()` method. Please use the
    `up()` and `down()` methods.

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

Saving Changes
~~~~~~~~~~~~~~

When working with the Table object, Phinx stores certain operations in a
pending changes cache. Once you have made the changes you want to the table,
you must save them. To perform this operation, Phinx provides three methods,
``create()``, ``update()``, and ``save()``. ``create()`` will first create
the table and then run the pending changes. ``update()`` will just run the
pending changes, and should be used when the table already exists. ``save()``
is a helper function that checks first if the table exists and if it does not
will run ``create()``, else it will run ``update()``.

As stated above, when using the ``change()`` migration method, you should always
use ``create()`` or ``update()``, and never ``save()`` as otherwise migrating
and rolling back may result in different states, due to ``save()`` calling
``create()`` when running migrate and then ``update()`` on rollback. When
using the ``up()``/``down()`` methods, it is safe to use either ``save()`` or
the more explicit methods.

When in doubt with working with tables, it is always recommended to call
the appropriate function and commit any pending changes to the database.

Creating a Table
~~~~~~~~~~~~~~~~

Creating a table is really easy using the Table object. Let's create a table to
store a collection of users.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function change()
            {
                $users = $this->table('users');
                $users->addColumn('username', 'string', ['limit' => 20])
                      ->addColumn('password', 'string', ['limit' => 40])
                      ->addColumn('password_salt', 'string', ['limit' => 40])
                      ->addColumn('email', 'string', ['limit' => 100])
                      ->addColumn('first_name', 'string', ['limit' => 30])
                      ->addColumn('last_name', 'string', ['limit' => 30])
                      ->addColumn('created', 'datetime')
                      ->addColumn('updated', 'datetime', ['null' => true])
                      ->addIndex(['username', 'email'], ['unique' => true])
                      ->create();
            }
        }

Columns are added using the ``addColumn()`` method. We create a unique index
for both the username and email columns using the ``addIndex()`` method.
Finally calling ``create()`` commits the changes to the database.

.. note::

    Phinx automatically creates an auto-incrementing primary key column called ``id`` for every
    table.

The ``id`` option sets the name of the automatically created identity field, while the ``primary_key``
option selects the field or fields used for primary key. ``id`` will always override the ``primary_key``
option unless it's set to false. If you don't need a primary key set ``id`` to false without
specifying a ``primary_key``, and no primary key will be created.

To specify an alternate primary key, you can specify the ``primary_key`` option
when accessing the Table object. Let's disable the automatic ``id`` column and
create a primary key using two columns instead:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('followers', ['id' => false, 'primary_key' => ['user_id', 'follower_id']]);
                $table->addColumn('user_id', 'integer')
                      ->addColumn('follower_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->create();
            }
        }

Setting a single ``primary_key`` doesn't enable the ``AUTO_INCREMENT`` option.
To simply change the name of the primary key, we need to override the default ``id`` field name:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function up()
            {
                $table = $this->table('followers', ['id' => 'user_id']);
                $table->addColumn('follower_id', 'integer')
                      ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                      ->create();
            }
        }

In addition, the MySQL adapter supports following options:

========== ===========
Option     Description
========== ===========
comment    set a text comment on the table
row_format set the table row format
engine     define table engine *(defaults to ``InnoDB``)*
collation  define table collation *(defaults to ``utf8_general_ci``)*
signed     whether the primary key is ``signed``  *(defaults to ``true``)*
========== ===========

By default the primary key is ``signed``.
To simply set it to unsigned just pass ``signed`` option with a ``false`` value:

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('followers', ['signed' => false]);
                $table->addColumn('follower_id', 'integer')
                      ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                      ->create();
            }
        }


The PostgreSQL adapter supports the following options:

========= ===========
Option    Description
========= ===========
comment   set a text comment on the table
========= ===========

.. _valid-column-types:

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
-  double
-  integer
-  smallinteger
-  string
-  text
-  time
-  timestamp
-  uuid

In addition, the MySQL adapter supports ``enum``, ``set``, ``blob``, ``bit`` and ``json`` column types
(``json`` in MySQL 5.7 and above).

In addition, the Postgres adapter supports ``interval``, ``json``, ``jsonb``, ``uuid``, ``cidr``, ``inet`` and ``macaddr`` column types
(PostgreSQL 9.3 and above).

For valid options, see the `Valid Column Options`_ below.

Custom Column Types & Default Values
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Some DBMS systems provide additional column types and default values that are specific to them.
If you don't want to keep your migrations DBMS-agnostic you can use those custom types in your migrations
through the ``\Phinx\Util\Literal::from`` method, which takes a string as its only argument, and returns an
instance of ``\Phinx\Util\Literal``. When Phinx encounters this value as a column's type it knows not to
run any validation on it and to use it exactly as supplied without escaping. This also works for ``default``
values.

You can see an example below showing how to add a ``citext`` column as well as a column whose default value
is a function, in PostgreSQL. This method of preventing the built-in escaping is supported in all adapters.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;
        use Phinx\Util\Literal;

        class AddSomeColumns extends AbstractMigration
        {
            public function change()
            {
                $this->table('users')
                      ->addColumn('username', Literal::from('citext'))
                      ->addColumn('uniqid', 'uuid', [
                          'default' => Literal::from('uuid_generate_v4()')
                      ])
                      ->addColumn('creation', 'timestamp', [
                          'timezone' => true,
                          'default' => Literal::from('now()')
                      ])
                      ->create();
            }
        }

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

Tables can be dropped quite easily using the ``drop()`` method. It is a
good idea to recreate the table again in the ``down()`` method.

Note that like other methods in the ``Table`` class, ``drop`` also needs ``save()``
to be called at the end in order to be executed. This allows phinx to intelligently
plan migrations when more than one table is involved.

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
                $this->table('users')->drop()->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
                $users = $this->table('users');
                $users->addColumn('username', 'string', ['limit' => 20])
                      ->addColumn('password', 'string', ['limit' => 40])
                      ->addColumn('password_salt', 'string', ['limit' => 40])
                      ->addColumn('email', 'string', ['limit' => 100])
                      ->addColumn('first_name', 'string', ['limit' => 30])
                      ->addColumn('last_name', 'string', ['limit' => 30])
                      ->addColumn('created', 'datetime')
                      ->addColumn('updated', 'datetime', ['null' => true])
                      ->addIndex(['username', 'email'], ['unique' => true])
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
                $table
                    ->rename('legacy_users')
                    ->update();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {
                $table = $this->table('legacy_users');
                $table
                    ->rename('users')
                    ->update();
            }
        }

Changing the Primary Key
~~~~~~~~~~~~~~~~~~~~~~~~

To change the primary key on an existing table, use the ``changePrimaryKey()`` method.
Pass in a column name or array of columns names to include in the primary key, or ``null`` to drop the primary key.
Note that the mentioned columns must be added to the table, they will not be added implicitly.

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
                $users
                    ->addColumn('username', 'string', ['limit' => 20, 'null' => false])
                    ->addColumn('password', 'string', ['limit' => 40])
                    ->save();

                $users
                    ->addColumn('new_id', 'integer', ['null' => false])
                    ->changePrimaryKey(['new_id', 'username'])
                    ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Changing the Table Comment
~~~~~~~~~~~~~~~~~~~~~~~~~~

To change the comment on an existing table, use the ``changeComment()`` method.
Pass in a string to set as the new table comment, or ``null`` to drop the existing comment.

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
                $users
                    ->addColumn('username', 'string', ['limit' => 20])
                    ->addColumn('password', 'string', ['limit' => 40])
                    ->save();

                $users
                    ->changeComment('This is the table with users auth information, password should be encrypted')
                    ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Working With Columns
--------------------

Valid Column Types
~~~~~~~~~~~~~~~~~~

Column types are specified as strings and can be one of:

-  biginteger
-  binary
-  boolean
-  char
-  date
-  datetime
-  decimal
-  float
-  integer
-  smallinteger
-  string
-  text
-  time
-  timestamp
-  uuid

In addition, the MySQL adapter supports ``enum``, ``set``, ``blob``, ``bit`` and ``json`` column types
(``json`` in MySQL 5.7 and above).

In addition, the Postgres adapter supports ``interval``, ``json``, ``jsonb``, ``uuid``, ``cidr``, ``inet`` and ``macaddr`` column types
(PostgreSQL 9.3 and above).

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
after   specify the column that a new column should be placed after *(only applies to MySQL)*
comment set a text comment on the column
======= ===========

For ``decimal`` columns:

========= ===========
Option    Description
========= ===========
precision combine with ``scale`` set to set decimal accuracy
scale     combine with ``precision`` to set decimal accuracy
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

You can add ``created_at`` and ``updated_at`` timestamps to a table using the ``addTimestamps()`` method. This method also
allows you to supply alternative names. The optional third argument allows you to change the ``timezone`` option for the
columns being added. Additionally, you can use the ``addTimestampsWithTimezone()`` method, which is an alias to
``addTimestamps()`` that will always set the third argument to ``true`` (see examples below).

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            /**
             * Migrate Change.
             */
            public function change()
            {
                // Use defaults (without timezones)
                $table = $this->table('users')->addTimestamps()->create();
                // Use defaults (with timezones)
                $table = $this->table('users')->addTimestampsWithTimezone()->create();

                // Override the 'created_at' column name with 'recorded_at'.
                $table = $this->table('books')->addTimestamps('recorded_at')->create();

                // Override the 'updated_at' column name with 'amended_at', preserving timezones.
                // The two lines below do the same, the second one is simply cleaner.
                $table = $this->table('books')->addTimestamps(null, 'amended_at', true)->create();
                $table = $this->table('users')->addTimestampsWithTimezone(null, 'amended_at')->create();
            }
        }

For ``boolean`` columns:

======== ===========
Option   Description
======== ===========
signed   enable or disable the ``unsigned`` option *(only applies to MySQL)*
======== ===========

For ``string`` and ``text`` columns:

========= ===========
Option    Description
========= ===========
collation set collation that differs from table defaults *(only applies to MySQL)*
encoding  set character set that differs from table defaults *(only applies to MySQL)*
========= ===========

For foreign key definitions:

====== ===========
Option Description
====== ===========
update set an action to be triggered when the row is updated
delete set an action to be triggered when the row is deleted
====== ===========

You can pass one or more of these options to any column with the optional
third argument array.

Limit Option and MySQL
~~~~~~~~~~~~~~~~~~~~~~

When using the MySQL adapter, additional hinting of database column type can be
made for ``integer``, ``text`` and ``blob`` columns. Using ``limit`` with
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
               ->addColumn('product_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
               ->addColumn('subtype_id', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
               ->addColumn('quantity', 'integer', ['limit' => MysqlAdapter::INT_TINY])
               ->create();


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

Get a column by name
~~~~~~~~~~~~~~~~~~~~

To retrieve one table column, simply create a `table` object and call the `getColumn()`
method. This method will return a Column class with basic info or NULL when the column doesn't exist. Example below:

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
                $column = $this->table('users')->getColumn('email');
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

To rename a column, access an instance of the Table object then call the
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
                $table->addColumn('city', 'string', ['after' => 'email'])
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
             * Migrate up.
             */
            public function up()
            {
                $table = $this->table('users');
                $table->removeColumn('short_name')
                      ->save();
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
                $table->addColumn('short_name', 'string', ['limit' => 30])
                      ->update();
            }
        }

Changing Column Attributes
~~~~~~~~~~~~~~~~~~~~~~~~~~

To change column type or options on an existing column, use the ``changeColumn()`` method.
See :ref:`valid-column-types` and `Valid Column Options`_ for allowed values.

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
                $users->changeColumn('email', 'string', ['limit' => 255])
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

Working With Indexes
--------------------

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
                      ->addIndex(['city'])
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
can pass an additional parameter ``unique`` to the ``addIndex()`` method to
specify a unique index. We can also explicitly specify a name for the index
using the ``name`` parameter.

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
                      ->addIndex(['email'], [
                            'unique' => true,
                            'name' => 'idx_users_email'])
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

The MySQL adapter also supports ``fulltext`` indexes. If you are using a version before 5.6 you must
ensure the table uses the ``MyISAM`` engine.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('users', ['engine' => 'MyISAM']);
                $table->addColumn('email', 'string')
                      ->addIndex('email', ['type' => 'fulltext'])
                      ->create();
            }
        }

In addition, MySQL adapter also supports setting the index length defined by limit option.
When you are using a multi-column index, you are able to define each column index length.
The single column index can define its index length with or without defining column name in limit option.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('users');
                $table->addColumn('email', 'string')
                      ->addColumn('username','string')
                      ->addColumn('user_guid', 'string', ['limit' => 36])
                      ->addIndex(['email','username'], ['limit' => ['email' => 5, 'username' => 2]])
                      ->addIndex('user_guid', ['limit' => 6])
                      ->create();
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
                $table->removeIndex(['email'])
                    ->save();

                // alternatively, you can delete an index by its name, ie:
                $table->removeIndexByName('idx_users_email')
                    ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }


Working With Foreign Keys
-------------------------

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
                $refTable->addColumn('tag_id', 'integer', ['null' => true])
                         ->addForeignKey('tag_id', 'tags', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                         ->save();

            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

"On delete" and "On update" actions are defined with a 'delete' and 'update' options array. Possibles values are 'SET_NULL', 'NO_ACTION', 'CASCADE' and 'RESTRICT'.  If 'SET_NULL' is used then the column must be created as nullable with the option ``['null' => true]``.
Constraint name can be changed with the 'constraint' option.

It is also possible to pass ``addForeignKey()`` an array of columns.
This allows us to establish a foreign key relationship to a table which uses a combined key.

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
                $table = $this->table('follower_events');
                $table->addColumn('user_id', 'integer')
                      ->addColumn('follower_id', 'integer')
                      ->addColumn('event_id', 'integer')
                      ->addForeignKey(['user_id', 'follower_id'],
                                      'followers',
                                      ['user_id', 'follower_id'],
                                      ['delete'=> 'NO_ACTION', 'update'=> 'NO_ACTION', 'constraint' => 'user_follower_id'])
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

We can add named foreign keys using the ``constraint`` parameter. This feature is supported as of Phinx version 0.6.5

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
                $table = $this->table('your_table');
                $table->addForeignKey('foreign_id', 'reference_table', ['id'],
                                    ['constraint' => 'your_foreign_key_name']);
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

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

Finally, to delete a foreign key, use the ``dropForeignKey`` method.

Note that like other methods in the ``Table`` class, ``dropForeignKey`` also needs ``save()``
to be called at the end in order to be executed. This allows phinx to intelligently
plan migrations when more than one table is involved.

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
                $table->dropForeignKey('tag_id')->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }



Using the Query Builder
-----------------------

It is not uncommon to pair database structure changes with data changes. For example, you may want to
migrate the data in a couple columns from the users to a newly created table. For this type of scenarios,
Phinx provides access to a Query builder object, that you may use to execute complex ``SELECT``, ``UPDATE``,
``INSERT`` or ``DELETE`` statements.

The Query builder is provided by the `cakephp/database <https://github.com/cakephp/database>`_ project, and should
be easy to work with as it resembles very closely plain SQL. Accesing the query builder is done by calling the
``getQueryBuilder()`` function:


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
                $builder = $this->getQueryBuilder();
                $statement = $builder->select('*')->from('users')->execute();
                var_dump($statement->fetchAll());
            }
        }

Selecting Fields
~~~~~~~~~~~~~~~~

Adding fields to the SELECT clause:


.. code-block:: php

        <?php
        $builder->select(['id', 'title', 'body']);

        // Results in SELECT id AS pk, title AS aliased_title, body ...
        $builder->select(['pk' => 'id', 'aliased_title' => 'title', 'body']);

        // Use a closure
        $builder->select(function ($builder) {
            return ['id', 'title', 'body'];
        });


Where Conditions
~~~~~~~~~~~~~~~~

Generating conditions:

.. code-block:: php

        // WHERE id = 1
        $builder->where(['id' => 1]);

        // WHERE id > 1
        $builder->where(['id >' => 1]);


As you can see you can use any operator by placing it with a space after the field name. Adding multiple conditions is easy as well:


.. code-block:: php

        <?php
        $builder->where(['id >' => 1])->andWhere(['title' => 'My Title']);

        // Equivalent to
        $builder->where(['id >' => 1, 'title' => 'My title']);

        // WHERE id > 1 OR title = 'My title'
        $builder->where(['OR' => ['id >' => 1, 'title' => 'My title']]);


For even more complex conditions you can use closures and expression objects:

.. code-block:: php

        <?php
        // Coditions are tied together with AND by default
        $builder
            ->select('*')
            ->from('articles')
            ->where(function ($exp) {
                return $exp
                    ->eq('author_id', 2)
                    ->eq('published', true)
                    ->notEq('spam', true)
                    ->gt('view_count', 10);
            });


Which results in:

.. code-block:: sql

    SELECT * FROM articles
    WHERE
        author_id = 2
        AND published = 1
        AND spam != 1
        AND view_count > 10


Combining expressions is also possible:


.. code-block:: php

        <?php
        $builder
            ->select('*')
            ->from('articles')
            ->where(function ($exp) {
                $orConditions = $exp->or_(['author_id' => 2])
                    ->eq('author_id', 5);
                return $exp
                    ->not($orConditions)
                    ->lte('view_count', 10);
            });

It generates:

.. code-block:: sql

    SELECT *
    FROM articles
    WHERE
        NOT (author_id = 2 OR author_id = 5)
        AND view_count <= 10


When using the expression objects you can use the following methods to create conditions:

* ``eq()`` Creates an equality condition.
* ``notEq()`` Create an inequality condition
* ``like()`` Create a condition using the ``LIKE`` operator.
* ``notLike()`` Create a negated ``LIKE`` condition.
* ``in()`` Create a condition using ``IN``.
* ``notIn()`` Create a negated condition using ``IN``.
* ``gt()`` Create a ``>`` condition.
* ``gte()`` Create a ``>=`` condition.
* ``lt()`` Create a ``<`` condition.
* ``lte()`` Create a ``<=`` condition.
* ``isNull()`` Create an ``IS NULL`` condition.
* ``isNotNull()`` Create a negated ``IS NULL`` condition.


Aggregates and SQL Functions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~


.. code-block:: php

    <?php
    // Results in SELECT COUNT(*) count FROM ...
    $builder->select(['count' => $builder->func()->count('*')]);

A number of commonly used functions can be created with the func() method:

* ``sum()`` Calculate a sum. The arguments will be treated as literal values.
* ``avg()`` Calculate an average. The arguments will be treated as literal values.
* ``min()`` Calculate the min of a column. The arguments will be treated as literal values.
* ``max()`` Calculate the max of a column. The arguments will be treated as literal values.
* ``count()`` Calculate the count. The arguments will be treated as literal values.
* ``concat()`` Concatenate two values together. The arguments are treated as bound parameters unless marked as literal.
* ``coalesce()`` Coalesce values. The arguments are treated as bound parameters unless marked as literal.
* ``dateDiff()`` Get the difference between two dates/times. The arguments are treated as bound parameters unless marked as literal.
* ``now()`` Take either 'time' or 'date' as an argument allowing you to get either the current time, or current date.

When providing arguments for SQL functions, there are two kinds of parameters you can use,
literal arguments and bound parameters. Literal parameters allow you to reference columns or
other SQL literals. Bound parameters can be used to safely add user data to SQL functions. For example:


.. code-block:: php

    <?php
    // Generates:
    // SELECT CONCAT(title, ' NEW') ...;
    $concat = $builder->func()->concat([
        'title' => 'literal',
        ' NEW'
    ]);
    $query->select(['title' => $concat]);


Getting Results out of a Query
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Once you’ve made your query, you’ll want to retrieve rows from it. There are a few ways of doing this:


.. code-block:: php

    <?php
    // Iterate the query
    foreach ($builder as $row) {
        echo $row['title'];
    }

    // Get the statement and fetch all results
    $results = $builder->execute()->fetchAll('assoc');


Creating an Insert Query
~~~~~~~~~~~~~~~~~~~~~~~~

Creating insert queries is also possible:


.. code-block:: php

    <?php
    $builder = $this->getQueryBuilder();
    $builder
        ->insert(['first_name', 'last_name'])
        ->into('users')
        ->values(['first_name' => 'Steve', 'last_name' => 'Jobs'])
        ->values(['first_name' => 'Jon', 'last_name' => 'Snow'])
        ->execute()


For increased performance, you can use another builder object as the values for an insert query:

.. code-block:: php

    <?php

    $namesQuery = $this->getQueryBuilder();
    $namesQuery
        ->select(['fname', 'lname'])
        ->from('users')
        ->where(['is_active' => true])

    $builder = $this->getQueryBuilder();
    $st = $builder
        ->insert(['first_name', 'last_name'])
        ->into('names')
        ->values($namesQuery)
        ->execute()

    var_dump($st->lastInsertId('names', 'id'));


The above code will generate:

.. code-block:: sql

    INSERT INTO names (first_name, last_name)
        (SELECT fname, lname FROM USERS where is_active = 1)


Creating an update Query
~~~~~~~~~~~~~~~~~~~~~~~~

Creating update queries is similar to both inserting and selecting:

.. code-block:: php

    <?php
    $builder = $this->getQueryBuilder();
    $builder
        ->update('users')
        ->set('fname', 'Snow')
        ->where(['fname' => 'Jon'])
        ->execute()


Creating a Delete Query
~~~~~~~~~~~~~~~~~~~~~~~

Finally, delete queries:

.. code-block:: php

    <?php
    $builder = $this->getQueryBuilder();
    $builder
        ->delete('users')
        ->where(['accepted_gdpr' => false])
        ->execute()
