.. index::
   single: Supporting namespaces

PSR-4 compliance
==================

Phinx allows the use of namespaces in Migrations and Seeders.
Migrations require a timestamp in the filename, and therefore won't be fully PSR-4 compliant. Seeders does not need a timestamp, and will be fully PSR-4 compliant.

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
    - Any value without a key, is a global-non-namespaced path
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
                    "Foo\\Bar": "./db/FooBar"
                }
            }
        }

4) That's it, you're ready to go, to create a migration simply run: `$ phinx create Foo\\MyNewMigration`


Did you run into an issue?
------------------------

- Due to the way the migrations are created, it is imposible to generate a migration in the *global* namespace with a classname that is the same, as a migration in a user-defined namespace.
