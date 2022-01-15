.. index::
   single: Database Seeding

数据库种子设定
================

In version 0.5.0 Phinx introduced support for seeding your database with test
data. Seed classes are a great way to easily fill your database with data after
it's created. By default they are stored in the `seeds` directory; however, this
path can be changed in your configuration file.
在 0.5.0 版本中，Phinx 引入了对使用测试数据播种数据库的支持。 种子类是在创建数据库后轻松使用数据填充数据库的好方法。 默认情况下，它们存储在“种子”目录中； 但是，可以在您的配置文件中更改此路径。

.. note::

    Database seeding is entirely optional, and Phinx does not create a `seeds`
    directory by default.
    数据库播种完全是可选的，Phinx 默认不创建“种子”目录。

Creating a New Seed Class 创建一个新的种子类
-------------------------

Phinx includes a command to easily generate a new seed class:
Phinx 包含一个可以轻松生成新种子类的命令：

.. code-block:: bash

        $ php vendor/bin/phinx seed:create UserSeeder

If you have specified multiple seed paths, you will be asked to select which
path to create the new seed class in.
如果您指定了多个种子路径，您将被要求选择在哪个路径中创建新的种子类。

It is based on a skeleton template:
它基于一个骨架模板：

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

The AbstractSeed Class AbstractSeed 类
----------------------

All Phinx seeds extend from the ``AbstractSeed`` class. This class provides the
necessary support to create your seed classes. Seed classes are primarily used
to insert test data.
所有 Phinx 种子都扩展自“AbstractSeed”类。 此类为创建种子类提供了必要的支持。 种子类主要用于插入测试数据。

The Run Method 运行方法
~~~~~~~~~~~~~~

The run method is automatically invoked by Phinx when you execute the `seed:run`
command. You should use this method to insert your test data.
当你执行 `seed:run` 命令时，Phinx 会自动调用 run 方法。 您应该使用此方法插入您的测试数据。

.. note::

    Unlike with migrations, Phinx does not keep track of which seed classes have
    been run. This means database seeders can be run repeatedly. Keep this in
    mind when developing them.
    与迁移不同，Phinx 不跟踪已运行的种子类。 这意味着数据库播种机可以重复运行。 在开发它们时请记住这一点。

The Init Method
~~~~~~~~~~~~~~~

The ``init()`` method is run by Phinx before the run method if it exists. This
can be used to initialize properties of the Seed class before using run.
``init()`` 方法由 Phinx 在 run 方法之前运行（如果存在）。 这可用于在使用 run 之前初始化 Seed 类的属性。

Foreign Key Dependencies
~~~~~~~~~~~~~~~~~~~~~~~~

Often you'll find that seeders need to run in a particular order, so they don't
violate foreign key constraints. To define this order, you can implement the
``getDependencies()`` method that returns an array of seeders to run before the
current seeder:
通常您会发现播种机需要以特定顺序运行，因此它们不会违反外键约束。 要定义此顺序，您可以实现 getDependencies() 方法，该方法返回要在当前播种器之前运行的播种器数组：

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
    仅在执行所有种子类时才考虑依赖项（默认行为）。 在运行特定的种子类时不会考虑它们。

Inserting Data 插入数据
--------------

Using The Table Object 使用表格对象
~~~~~~~~~~~~~~~~~~~~~~

Seed classes can also use the familiar `Table` object to insert data. You can
retrieve an instance of the Table object by calling the ``table()`` method from
within your seed class and then use the `insert()` method to insert data:
种子类也可以使用熟悉的 `Table` 对象来插入数据。 您可以通过从种子类中调用 ``table()`` 方法来检索 Table 对象的实例，然后使用 `insert()` 方法插入数据：

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
    您必须调用 `saveData()` 方法将数据提交到表中。 在您这样做之前，Phinx 将缓冲数据。

Integrating with the Faker library 与 Faker 库集成
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

It's trivial to use the awesome
`Faker library <https://github.com/fzaninotto/Faker>`_ in your seed classes.
Simply install it using Composer:
使用真棒是微不足道的
`Faker library <https://github.com/fzaninotto/Faker>`_ 在你的种子类中。
只需使用 Composer 安装它：

.. code-block:: bash

        $ composer require fzaninotto/faker

Then use it in your seed classes:
然后在你的种子类中使用它：

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

                $this->table('users')->insert($data)->saveData();
            }
        }

Truncating Tables 截断表
-----------------

In addition to inserting data Phinx makes it trivial to empty your tables using the
SQL `TRUNCATE` command:
除了插入数据之外，Phinx 还可以使用
SQL `TRUNCATE` 命令：

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
    SQLite 本身不支持 `TRUNCATE` 命令，因此在幕后使用了 `DELETE FROM`。 建议在截断表后调用“VACUUM”命令。 Phinx 不会自动执行此操作。

Executing Seed Classes 执行种子类
----------------------

This is the easy part. To seed your database, simply use the `seed:run` command:
这是简单的部分。 要为您的数据库播种，只需使用 `seed:run` 命令：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run

By default, Phinx will execute all available seed classes. If you would like to
run a specific class, simply pass in the name of it using the `-s` parameter:
默认情况下，Phinx 将执行所有可用的种子类。 如果你想运行一个特定的类，只需使用 `-s` 参数传入它的名称：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -s UserSeeder

You can also run multiple seeders:
您还可以运行多个播种机：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -s UserSeeder -s PermissionSeeder -s LogSeeder

You can also use the `-v` parameter for more output verbosity:
您还可以使用 `-v` 参数来获得更详细的输出：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -v

The Phinx seed functionality provides a simple mechanism to easily and repeatably
insert test data into your database.
Phinx 种子功能提供了一种简单的机制，可以轻松且可重复地将测试数据插入到您的数据库中。
