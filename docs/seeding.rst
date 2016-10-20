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

        $ php bin/phinx seed:create UserSeeder

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
                $data = array(
                  array(
                      'body'    => 'foo',
                      'created' => date('Y-m-d H:i:s'),
                  ),
                  array(
                      'body'    => 'bar',
                      'created' => date('Y-m-d H:i:s'),
                  )
                );

                $posts = $this->table('posts');
                $posts->insert($data)
                      ->save();
            }
        }

.. note::

    You must call the `save()` method to commit your data to the table. Phinx
    will buffer data until you do so.

Integrating with the Faker library
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

It's trivial to use the awesome
`Faker library <https://github.com/fzaninotto/Faker>`_ in your seed classes.
Simply install it using Composer:

.. code-block:: bash

        $ composer require fzaninotto/faker

Then use it in your seed classes:

.. code-block:: php

        <?php

        use Phinx\Seed\AbstractSeed;

        class UserSeeder extends AbstractSeed
        {
            public function run()
            {
              $faker = Faker\Factory::create();
              $data = [];
              for ($i = 0; $i < 100; $i++) {
                  $data[] = [
                      'username'      => $faker->userName,
                      'password'      => sha1($faker->password),
                      'password_salt' => sha1('foo'),
                      'email'         => $faker->email,
                      'first_name'    => $faker->firstName,
                      'last_name'     => $faker->lastName,
                      'created'       => date('Y-m-d H:i:s'),
                  ];
              }

              $this->insert('users', $data);
            }
        }

Executing Seed Classes
----------------------

This is the easy part. To seed your database, simply use the `seed:run` command:

.. code-block:: bash

        $ php bin/phinx seed:run

By default, Phinx will execute all available seed classes. If you would like to
run a specific class, simply pass in the name of it using the `-s` parameter:

.. code-block:: bash

        $ php bin/phinx seed:run -s UserSeeder

You can also run multiple seeders:

.. code-block:: bash

        $ php bin/phinx seed:run -s UserSeeder -s PermissionSeeder -s LogSeeder

You can also use the `-v` parameter for more output verbosity:

.. code-block:: bash

        $ php bin/phinx seed:run -v

The Phinx seed functionality provides a simple mechanism to easily and repeatably
insert test data into your database.
