.. index::
   single: Supporting namespaces

PSR-4 compliance
==================

Phinx allows the use of namespaces in Migrations and Seeders.
Migrations require a timestamp in the filename, and therefore won't be fully PSR-4 compliant. Seeders do not need a timestamp and will be fully PSR-4 compliant.

Using namespaces
------------------------
1) locate your Phinx config file, the config file may be in one of following three formats: PHP, YAML or JSON.
2) Locate the "paths" key inside the config file, it should look something like one of the below examples.
    - (NB. the "migrations" and "seeds" keys may be both an array or a string, so don't be alarmed if yours looks different)

PHP:

.. code-block:: php

        'paths' => [
            'migrations' => 'database/migrations',
            'seeds' => 'database/seeds',
        ],


YAML:

.. code-block:: yaml

        paths:
            migrations: ./database/migrations
            seeds: ./database/seeds

JSON:

.. code-block:: json

        {
            "paths": {
                "migrations": "database/migrations",
                "seeds": "database/seeds"
            }
        }

3) Enabling namespaces is a fairly simple task, we're going to turn the "migrations" and "seeds" keys into arrays.
    - Any value without a key is a global-non-namespaced path
    - Any keyed value will use the key as namespace

.. code-block:: php

        'paths' => [
            'migrations' => [
                '/path/to/migration/without/namespace', // Non-namespaced migrations
                'Foo' => '/path/to/migration/Foo', // Migrations in the Foo namespace
            ],
            'seeds' => [
                '/path/to/seeds/without/namespace', // Non-namespaced seeders
                'Baz' => '/path/to/seeds/Baz', // Seeders in the Baz namespace
            ]
        ],

PHP is a bit special in this case, as it allows keyless and keyed values in the same array. To make this configuration work in YAML and JSON, we have to key the non-namespaced path with "0".

.. code-block:: json

        {
            "paths": {
                "migrations": {
                    "0": "./db/migrations",
                    "Foo\\Bar": "./src/FooBar/db/migrations"
                }
            }
        }

.. code-block:: yaml

        paths:
            migrations:
                0: ./db/migrations
                Foo\\Bar: ./src/FooBar/db/migrations

Path resolving
^^^^^^^^^^^^^^

Let's take a closer look on how the paths are resolved, let's start with the non-namespaced path.

"./" refers to the project-root, therefore "./db/migrations" would resolve to <project-root>/db/migrations.
This is the directory where Phinx will look for migrations when migrating.
NB. these migrations must not have a namespace.

.. image:: http://i.imgur.com/l84308Q.jpg

This image shows the path for "./db/migrations" where "Phinx" is the project root.

And the namespaced path would be resolved as shown below.

"./src/FooBar/db/migrations" would resolve to <project-root>/src/FooBar/db/migrations, which is where Phinx will look for migrations in the Foo\\Bar namespace.

.. image:: http://i.imgur.com/2mg0V8V.jpg

The file path would look like this, if the project-root was "Phinx"

File examples
^^^^^^^^^^^^^

The non-namespaced file in <project-root>/db/migrations may look like the following example.

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class CreateUserTable extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('users');
                $table->addColumn('name', 'string')->create();
            }
        }

Whereas the namespaced file will be found in <project-root>/src/FoorBar/db/migrations and can look like this:
(Notice the namespace is the same as defined in the paths config).

.. code-block:: php

        <?php

        namespace Foo\Bar;

        use Phinx\Migration\AbstractMigration;

        class CreateUserTable extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('users');
                $table->addColumn('name', 'string')->create();
            }
        }


4) That's it, you're ready to go, to create a migration simply run: *$ phinx create CreateUsersTable [--path ./src/FoorBar/db/migrations]*

    - If multiple paths are configured, but none provided with the --path flag, you will be prompted for which path to use.


Did you run into an issue?
--------------------------

- Due to the way the migrations are created, it is impossible to generate a migration in the *global* namespace with a class-name that is the same as a migration in a user-defined namespace.
