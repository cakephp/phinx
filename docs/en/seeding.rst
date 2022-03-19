.. index::
   single: Database Seeding

Database Seeding
================

In version 0.5.0 Phinx introduced support for seeding your database with test
data. Seed classes are a great way to easily fill your database with data after
it's created. By default they are stored in the `seeds` directory; however, this
path can be changed in your configuration file.

.. note::

    Database seeding is entirely optional, and Phinx does not create a `seeds`
    directory by default.

Creating a New Seed Class
-------------------------

Phinx includes a command to easily generate a new seed class:

.. code-block:: bash

        $ php vendor/bin/phinx seed:create UserSeeder

If you have specified multiple seed paths, you will be asked to select which
path to create the new seed class in.

It is based on a skeleton template:

.. code-block:: php

        <?php

        use Phinx\Seed\AbstractSeed;

        class MyNewSeeder extends AbstractSeed
        {
            /**
             * Run Method.
             *
             * Write your database seeder using this method.
             *
             * More information on writing seeders is available here:
             * http://docs.phinx.org/en/latest/seeding.html
             */
            public function run()
            {

            }
        }

The AbstractSeed Class
----------------------

All Phinx seeds extend from the ``AbstractSeed`` class. This class provides the
necessary support to create your seed classes. Seed classes are primarily used
to insert test data.

The Run Method
~~~~~~~~~~~~~~

The run method is automatically invoked by Phinx when you execute the `seed:run`
command. You should use this method to insert your test data.

.. note::

    Unlike with migrations, Phinx does not keep track of which seed classes have
    been run. This means database seeders can be run repeatedly. Keep this in
    mind when developing them.

The Init Method
~~~~~~~~~~~~~~~

The ``init()`` method is run by Phinx before the run method if it exists. This
can be used to initialize properties of the Seed class before using run.

Foreign Key Dependencies
~~~~~~~~~~~~~~~~~~~~~~~~

Often you'll find that seeders need to run in a particular order, so they don't
violate foreign key constraints. To define this order, you can implement the
``getDependencies()`` method that returns an array of seeders to run before the
current seeder:

.. code-block:: php

        <?php

        use Phinx\Seed\AbstractSeed;

        class ShoppingCartSeeder extends AbstractSeed
        {
            public function getDependencies()
            {
                return [
                    'UserSeeder',
                    'ShopItemSeeder'
                ];
            }

            public function run()
            {
                // Seed the shopping cart  after the `UserSeeder` and
                // `ShopItemSeeder` have been run.
            }
        }

.. note::

    Dependencies are only considered when executing all seed classes (default behavior).
    They won't be considered when running specific seed classes.

Inserting Data
--------------

Using The Table Object
~~~~~~~~~~~~~~~~~~~~~~

Seed classes can also use the familiar `Table` object to insert data. You can
retrieve an instance of the Table object by calling the ``table()`` method from
within your seed class and then use the `insert()` method to insert data:

.. code-block:: php

        <?php

        use Phinx\Seed\AbstractSeed;

        class PostsSeeder extends AbstractSeed
        {
            public function run()
            {
                $data = [
                    [
                        'body'    => 'foo',
                        'created' => date('Y-m-d H:i:s'),
                    ],[
                        'body'    => 'bar',
                        'created' => date('Y-m-d H:i:s'),
                    ]
                ];

                $posts = $this->table('posts');
                $posts->insert($data)
                      ->saveData();
            }
        }

.. note::

    You must call the `saveData()` method to commit your data to the table. Phinx
    will buffer data until you do so.

Truncating Tables
-----------------

In addition to inserting data Phinx makes it trivial to empty your tables using the
SQL `TRUNCATE` command:

.. code-block:: php

        <?php

        use Phinx\Seed\AbstractSeed;

        class UserSeeder extends AbstractSeed
        {
            public function run()
            {
                $data = [
                    [
                        'body'    => 'foo',
                        'created' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'body'    => 'bar',
                        'created' => date('Y-m-d H:i:s'),
                    ]
                ];

                $posts = $this->table('posts');
                $posts->insert($data)
                      ->saveData();

                // empty the table
                $posts->truncate();
            }
        }

.. note::

    SQLite doesn't natively support the `TRUNCATE` command so behind the scenes
    `DELETE FROM` is used. It is recommended to call the `VACUUM` command
    after truncating a table. Phinx does not do this automatically.

Executing Seed Classes
----------------------

This is the easy part. To seed your database, simply use the `seed:run` command:

.. code-block:: bash

        $ php vendor/bin/phinx seed:run

By default, Phinx will execute all available seed classes. If you would like to
run a specific class, simply pass in the name of it using the `-s` parameter:

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -s UserSeeder

You can also run multiple seeders:

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -s UserSeeder -s PermissionSeeder -s LogSeeder

You can also use the `-v` parameter for more output verbosity:

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -v

The Phinx seed functionality provides a simple mechanism to easily and repeatably
insert test data into your database.
