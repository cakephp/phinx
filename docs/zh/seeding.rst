.. index::
   single: Database Seeding

数据库种子（数据填充）
================

在 0.5.0 版本中，Phinx 加入了使用测试数据播种数据库（Database Seeding）的支持。
种子类提供了一种在创建数据库后填充数据的一种便捷方法。
默认情况下，它们存储在 `seeds` 目录中，但也可以在配置文件中更改此路径。

.. note::

    数据库播种完全是可选的，Phinx 默认不创建 `seeds` 目录。

创建一个新的种子类
-------------------------

Phinx 包含一个可以轻松生成新种子类的命令：

.. code-block:: bash

        $ php vendor/bin/phinx seed:create UserSeeder

如果您指定了多个种子路径，您将被要求选择在哪个路径中创建新的种子类。

它基于一个骨架模板（skeleton template）：

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

AbstractSeed 类
----------------------

所有 Phinx 种子都扩展自 ``AbstractSeed`` 类。此类为创建种子类提供了必要的支持。种子类主要用于插入测试数据。

Run 方法
~~~~~~~~~~~~~~

当你执行 `seed:run` 命令时，Phinx 会自动调用 run 方法。可使用此方法插入测试数据。

.. note::

    与迁移不同，Phinx 不跟踪已执行过的种子类。这意味着数据库播种器（Seeder）可以重复运行。在开发它们时请记住这一点。

init 方法
~~~~~~~~~~~~~~~

``init()`` 方法由 Phinx 在 run 方法之前运行（如果存在）。这可用于在使用 run 之前初始化 Seed 类的属性。

外键依赖
~~~~~~~~~~~~~~~~~~~~~~~~

通常您会发现播种器需要以特定顺序运行，因此它们不会违反外键约束。
要定义此顺序，您可以实现 ``getDependencies()`` 方法，该方法返回要在当前播种器之前运行的播种器数组：

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

    仅在执行所有种子类时才考虑依赖项（默认行为）。在运行特定的种子类时不会考虑它们。

插入数据
--------------

使用 Table 对象
~~~~~~~~~~~~~~~~~~~~~~

种子类也可以使用熟悉的 `Table` 对象来插入数据。
您可以通过从种子类中调用 ``table()`` 方法来获取 Table 对象实例，然后使用 `insert()` 方法插入数据：

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

    您必须调用 `saveData()` 方法将数据提交到表中。 在您这样做之前，Phinx 将缓存数据。

与 Faker 库集成
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

非常简单就可以将优秀的
`Faker library <https://github.com/fzaninotto/Faker>`_ 加入种子类中。
使用 Composer 安装它：

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

TRUNCATE 清空表
-----------------

In addition to inserting data Phinx makes it trivial to empty your tables using the
SQL `TRUNCATE` command:
除了插入数据之外，Phinx 还支持使用SQL `TRUNCATE` 命令对表进行清空：

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

    SQLite 本身不支持 `TRUNCATE` 命令，因此实际在幕后使用了 `DELETE FROM`。建议在 TRUNCATE 表后调用 `VACUUM` 命令。Phinx 不会自动执行此操作。

执行种子类
----------------------

这是简单的部分。要为您的数据库执行播种，只需使用 `seed:run` 命令：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run

默认情况下，Phinx 将执行所有可用的种子类。 如果你想运行一个特定的类，只需使用 `-s` 参数传入它的名称：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -s UserSeeder

您还可以运行多个播种器：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -s UserSeeder -s PermissionSeeder -s LogSeeder

您还可以使用 `-v` 参数来获得更详细的输出：

.. code-block:: bash

        $ php vendor/bin/phinx seed:run -v

Phinx 种子功能提供了一种简单的机制，可以轻松且可重复地将测试数据插入到您的数据库中。
