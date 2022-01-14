.. index::
   single: Writing Migrations

编写迁移
==================

Phinx 依靠 迁移(migration) 来转换你的数据库。每次迁移即代表一个唯一的 PHP 类。
建议使用 Phinx 的 PHP API 来编写迁移文件，但同时也支持原始 SQL。

创建新迁移
------------------------
生成迁移示例文件
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

让我们从创建一个新的 Phinx 迁移开始。运行 Phinx 的 ``create`` 命令：

.. code-block:: bash

        $ vendor/bin/phinx create MyNewMigration

这会创建一个新的迁移文件，格式为 ``YYYYMMDDHHMMSS_my_new_migration.php``。
其中前 14 个字符为当前精确到秒的时间戳。

如果你指定了多个迁移文件路径，则会提示选择在哪个路径下创建该迁移。

Phinx 会自动创建一个迁移示例文件，文件内有一个方法(method)。

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
             * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
             *
             * Remember to call "create()" or "update()" and NOT "save()" when working
             * with the Table class.
             *
             */
            public function change()
            {

            }
        }

所有 Phinx 的迁移都继承 ``AbstractMigration`` 类。
该类提供创建数据库迁移行为的必要支持。
数据库迁移可以通过多种方式转换你的数据库，比如创建新的表、插入行、加入索引和修改列。

Change 方法
~~~~~~~~~~~~~~~~~

Phinx 0.2.0 加入了一个名为“可逆性迁移”的新功能。该功能现在已成为默认的迁移方法。
通过“可逆性迁移”，你只需要定义 ``up`` 逻辑（向上迁移），Phinx 就可以自动识别何时为你进行 ``down`` 逻辑（向下迁移，类似回滚）。例如：

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class CreateUserLoginsTable extends AbstractMigration
        {
            public function change()
            {
                // create the table
                $table = $this->table('user_logins');
                $table->addColumn('user_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->create();
            }
        }

当执行这次迁移后，Phinx 会创建 ``user_logins`` 表(up逻辑)，并自动找到删除表的方法(down逻辑)。
请注意当 ``change`` 方法存在时，Phinx将自动忽略 ``up`` 和 ``down`` 方法。
如果你需要使用这两个方法的话，建议单独写入另一个迁移文件。

.. note::

    当在 ``change()`` 方法中创建或修改表时，你必须指定表的 ``create()`` 或 ``update()`` 方法。
    Phinx 无法自动判定 ``save()`` 方法是要创建表还是修改表。


通过 Phinx 中的 Table API 完成以下操作是可逆的，并且会自动反转：

- 创建表
- 重命名表
- 添加列
- 重命名列
- 添加索引
- 添加外键

如果一个动作不能被反转，Phinx 在执行回滚时，会抛出一个 ``IrreversibleMigrationException`` 错误。
如果你希望在 change 方法中执行的命令不被反转，可以使用 if 语句判断 ``$this->isMigratingUp()``，
从而让你的命令只在向上（Up）或向下（Down）的情况下才执行。例如：


.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class CreateUserLoginsTable extends AbstractMigration
        {
            public function change()
            {
                // create the table
                $table = $this->table('user_logins');
                $table->addColumn('user_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->create();
                if ($this->isMigratingUp()) {
                    $table->insert([['user_id' => 1, 'created' => '2020-01-19 03:14:07']])
                          ->save();
                }
            }
        }

Up 方法
~~~~~~~~~~~~~

Phinx 的 ``up()`` 方法将在 向上 迁移时自动执行。它会检查该迁移在之前是否已经执行过。
当你需要更改数据库时，应该使用这个方法。


Down 方法
~~~~~~~~~~~~~~~

Phinx 的 ``down()`` 方法将在 向下 迁移时自动执行。它会检查该迁移在之前是否已经执行过。
当你需要反转/撤销 up 方法中做的改变时，应该使用这个方法。

Init 方法
~~~~~~~~~~~~~~~

Phinx 的 ``init()`` 方法将在迁移方法运行之前执行（如果存在的话）。
它可以用于设置将在迁移方法中使用的通用类的属性。

执行查询
-----------------

查询（Query）可以使用 ``execute()`` 或 ``query()`` 方法。
``execute()`` 方法将返回受影响的行数量； ``query()`` 方法则返回一个`PDOStatement <http://php.net/manual/en/class.pdostatement.php>` 类型的结果。


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

    这些命令使用 PHP 数据对象 (PDO) 扩展运行。
    该扩展为访问数据库定义了一个轻量级、具备一致性的接口。
    在使用 ``execute()`` 之前，请确保你的查询语句是遵循 PDO 的。
    在插入不支持 DELIMITER 的存储过程或触发器期间时，这一点尤其重要。

.. warning::

    当对一批查询使用 ``execute()`` 或 ``query()`` 时，如果批处理中的一个或多个查询出现问题，PDO 并不会抛出异常。

    因此，整个批处理被视为全部顺利通过，并无异常。

    Phinx 无法实现迭代所有潜在的结果集，并去其中查找是否有错误。因为 PDO 中没有工具可以获取以前的结果集，所以Phinx无法访问所有结果。（`nextRowset() <http://php.net/manual/en/pdostatement.nextrowset.php>`_ -
    但没有 ``previousSet()``）

    由于 PDO 的设计决策不会为批处理查询抛出异常，因此在处理批量查询时，Phinx 无法为错误处理提供完整的支持。

    幸运的是，PDO 的所有功能都可用，因此针对批量处理的问题，可以通过在迁移中调用 `nextRowset() <http://php.net/manual/en/pdostatement.nextrowset.php>`_
    和检查 `errorInfo <http://php.net/manual/en/pdostatement.errorinfo.php>`_ 来实现控制。

获取行数据
-------------

There are two methods available to fetch rows. The ``fetchRow()`` method will
fetch a single row, whilst the ``fetchAll()`` method will return multiple rows.
Both methods accept raw SQL as their only parameter.
有两种方法可用于获取行数据。 ``fetchRow()`` 方法将获取单行，而 ``fetchAll()`` 方法将返回多行。
这两种方法都接受原始 SQL 作为其唯一参数。

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

插入数据
--------------

Phinx 可以轻松地将数据插入到你的表中。
虽然此功能主要用于 :doc:`种子功能(seeding) <seeding>`，但你也可以在迁移中自由使用插入方法。

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
                $table = $this->table('status');

                // inserting only one row
                $singleRow = [
                    'id'    => 1,
                    'name'  => 'In Progress'
                ];

                $table->insert($singleRow)->saveData();

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

                $table->insert($rows)->saveData();
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

    插入语句在 `chang()` 方法中不可用。可在 `up()` 或 `down()` 方法中使用它。

使用表
-------------------

表对象
~~~~~~~~~~~~~~~~

Table 对象是 Phinx 提供的最有用的 API 之一。
它可让你使用 PHP 代码轻松操作数据库表。
你可以通过在数据库迁移中调用 ``table()`` 方法来获取 Table 对象实例。

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

随后，你可以使用 Table 对象提供的方法操作该表。

保存更改
~~~~~~~~~~~~~~

使用 Table 对象时，Phinx 会将某些操作存储在“待定更改缓存”中。
对表格进行所需的更改后，必须执行保存操作。
Phinx 提供了三种保存方法 ``create()``, ``update()`` 和 ``save()``。
``create()`` 会先创建表，然后执行待定更改。
``update()`` 将只运行待定的更改，并且前提是表已经存在。
``save()`` 是一个助手函数。它首先检查表是否存在，如果不存在则运行 ``create()``，否则运行 ``update()``。

如上所述，在使用 ``change()`` 迁移方法时，你应该始终使用 ``create()`` 或 ``update() ``。
而不要使用 ``save()`` ，否则可能会导致迁移和回滚过程中出现不同的状态。
因为 ``save()`` 将在迁移时调用 ``create()``，而在回滚时调用 ``update()``。
所以，在使用 ``up()``/``down()`` 方法时，使用 ``save()`` 或其它更明确的方法是安全的。

如果对使用表有任何疑问，通常建议调用适当的函数，并将待定的更改提交到数据库。

创建一个表
~~~~~~~~~~~~~~~~

使用 Table 对象创建表非常容易。让我们创建一个存储用户集合的表。

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

使用 ``addColumn()`` 方法添加列。
使用 ``addIndex()`` 方法为用户名和电子邮件列创建唯一索引。
最后调用 ``create()`` 将更改提交到数据库。

.. note::

    Phinx 会自动为每个表创建一个名为 ``id`` 的自动递增的主键列。

``id`` 选项用于设置自动创建的标识字段的名称，而 ``primary_key`` 用于指定主键字段。
``id`` 将始终覆盖 ``primary_key`` 选项，除非它设置为 false。
如果你不需要主键，则在不指定 ``primary_key`` 的情况下将 ``id`` 设置为 false，则不会创建主键。

要指定备用主键，你可以在访问 Table 对象时指定 ``primary_key`` 选项。
让我们禁用自动 ``id`` 列并使用另外两个列创建主键：

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

只设置 ``primary_key`` 不会启用 ``AUTO_INCREMENT`` 选项。
要更改主键的名称，我们需要覆盖默认的 ``id`` 字段：

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

此外，MySQL 适配器支持以下选项：

========== ===========
选项       描述
========== ===========
comment    为表添加注释文本
row_format 设置表中行的格式
engine     定义表引擎 *(默认为 ``InnoDB``)*
collation  定义表排序规则 *(默认为 ``utf8_general_ci``)*
signed     主键是否是 ``signed``  *(默认为 ``true``)*
limit      设置主键的最大长度
========== ===========

默认情况下，主键是 ``signed``.
要将其设置为无符号，需将 ``signed`` 选项值设置为 ``false``：

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


PostgreSQL 适配器支持以下选项：

========= ===========
选项       描述
========= ===========
comment   为表添加注释文本
========= ===========

要查看可用的列类型和选项，请参阅 `Valid Column Types`_ 了解详细信息。

确定表是否存在
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

你可以使用 ``hasTable()`` 方法确定表是否存在。

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

删除表
~~~~~~~~~~~~~~~~

使用 ``drop()`` 方法可以很方便的删除表。在 ``down()`` 方法中重新创建表也是个好主意。

请注意，与 ``Table`` 类中的其他方法一样， ``drop`` 也需要在最后调用 ``save()`` 才能执行。
这允许 phinx 在涉及多个表时智能地计划迁移。

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

重命名表
~~~~~~~~~~~~~~~~

要重命名表，访问 Table 对象的实例后，调用 ``rename()`` 方法。

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

更改主键
~~~~~~~~~~~~~~~~~~~~~~~~

要更改现有表的主键，请使用 ``changePrimaryKey()`` 方法。
传入列名或列名数组用于设置主键，或传入 ``null`` 用于删除主键。
请注意，被设置为主键的列必须已添加至表中，它们不会被隐式添加。

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

更改表注释
~~~~~~~~~~~~~~~~~~~~~~~~~~

要更改现有表的注释，请使用 ``changeComment()`` 方法。
传入一个字符串用以作为新的表注释，或传入 ``null`` 用以删除现有注释。

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

使用列
--------------------

.. _valid-column-types:

有效的列类型
~~~~~~~~~~~~~~~~~~

列类型必须指定为字符串，可以是以下之一：

-  binary
-  boolean
-  char
-  date
-  datetime
-  decimal
-  float
-  double
-  smallinteger
-  integer
-  biginteger
-  string
-  text
-  time
-  timestamp
-  uuid

此外，MySQL 适配器支持 ``enum``, ``set``, ``blob``, ``tinyblob``, ``mediumblob``, ``longblob``, ``bit`` 和 ``json`` 列类型（ ``json`` 适用于 MySQL 5.7 及更高版本中）。
当提供限制值并使用 ``binary``, ``varbinary`` 或 ``blob`` 及其子类型时，最终保留的列类型将基于所需的长度来决定（有关详细信息，请参阅 `Limit Option and MySQL`_）;

此外，Postgres 适配器支持 ``interval``, ``json``, ``jsonb``, ``uuid``, ``cidr``, ``inet`` 和 ``macaddr`` 列类型（PostgreSQL 9.3 及更高版本）。

有效的列选项
~~~~~~~~~~~~~~~~~~~~

以下是有效的列选项：

对于任何列类型：

======= ===========
选项    描述
======= ===========
limit   设置字符串的最大长度，直接影响适配器中的列类型（请参阅下面的注释）
length  ``limit``的别名
default 设置默认值或操作
null    允许 ``NULL`` 值，默认为 false（不应与主键一起使用！）（请参阅下面的注释）
after   指定新列应该放在后面的列，或使用 ``\Phinx\Db\Adapter\MysqlAdapter::FIRST`` 将列放在表的开头 *（仅适用于 MySQL）*
comment 设置列的注释文本
======= ===========

对于 ``decimal`` 列：

========= ===========
选项      描述
========= ===========
precision 与 ``scale`` 配合使用，用以设置小数精度
scale     与 ``precision`` 配合使用，用以设置小数精度
signed    启用或禁用 ``unsigned`` 选项 *（仅适用于 MySQL）*
========= ===========

对于 ``enum`` 和 ``set`` 列：

========= ===========
选项      描述
========= ===========
values    可以是以逗号分隔的列表，或值的数组
========= ===========

对于 ``integer`` and ``biginteger`` 列：

======== ===========
选项      描述
======== ===========
identity 启用或禁用自动递增
signed   启用或禁用 ``unsigned`` 选项 *（仅适用于 MySQL）*
======== ===========

对于 ``timestamp`` 列：

======== ===========
选项      描述
======== ===========
default  设置默认值（与 ``CURRENT_TIMESTAMP`` 配合使用）
update   设置更新行时触发的操作（与 ``CURRENT_TIMESTAMP`` 配合使用） *（仅适用于MySQL）*
timezone 启用或禁用 ``time`` 和 ``timestamp`` 列的 ``with time zone`` 选项 *（仅适用于 Postgres）*
======== ===========

你可以使用 ``addTimestamps()`` 方法将 ``created_at`` 和 ``updated_at`` 时间戳添加到表中。
此方法接受三个参数，其中前两个是为列设置替代名称，第三个参数是为列启用 ``timezone`` 选项。
这些参数的默认值分别是 ``created_at``, ``updated_at``, and ``true``。
对于第一个和第二个参数，如果你设置为 ``null``，则将使用默认名称；如果你设置为 ``false``，则不会创建该列。
请注意，尝试将两者都设置为 ``false`` 将触发 ``\RuntimeException``。
此外，你可以使用 ``addTimestampsWithTimezone()`` 方法，它是 ``addTimestamps()`` 将第三个参数设置为 ``true`` 的特例（见下面的例子）。
``created_at`` 列的 *默认值* 为 ``CURRENT_TIMESTAMP``。对于 MySQL 而言， ``update_at`` 列的 *更新值* 也是 ``CURRENT_TIMESTAMP``。

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

                // Only add the created_at column to the table
                $table = $this->table('books')->addTimestamps(null, false);
                // Only add the updated_at column to the table
                $table = $this->table('users')->addTimestamps(false);
                // Note, setting both false will throw a \RuntimeError
            }
        }

对于 ``boolean`` 列：

======== ===========
选项      描述
======== ===========
signed   启用或禁用 ``unsigned`` 选项 *（仅适用于 MySQL）*
======== ===========

对于 ``string`` 和 ``text`` 列：

========= ===========
选项      描述
========= ===========
collation 设置不同于表默认值的排序规则 *（仅适用于 MySQL）*
encoding  设置不同于表默认值的字符集 *（仅适用于 MySQL）*
========= ===========

对于外键的定义：

====== ===========
选项    描述
====== ===========
update 设置更新行时触发的操作
delete 设置删除行时触发的操作
====== ===========

所有列均可设置第三个参数（可选），通过数组形式配置一个或多个选项。

限制选项和 MySQL
~~~~~~~~~~~~~~~~~~~~~~

使用 MySQL 适配器时，可以针对一些特定的列添加额外的类型推定。
这些列类型包括： ``integer``, ``text``, ``blob``, ``tinyblob``, ``mediumblob``, ``longblob``。
使用 ``limit`` 关键字，连同以下的选项一起使用，将相应地修改列的类型：

============ ==============
限制选项      列类型
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

对于 ``binary`` 或 ``varbinary`` 类型，最大允许为 255 字节，如果设置的选项超过此限制，则其类型将为“根据指定长度进行最佳匹配的 blob 类型”。

.. code-block:: php

        <?php

        use Phinx\Db\Adapter\MysqlAdapter;

        //...

        $table = $this->table('cart_items');
        $table->addColumn('user_id', 'integer')
              ->addColumn('product_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
              ->addColumn('subtype_id', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
              ->addColumn('quantity', 'integer', ['limit' => MysqlAdapter::INT_TINY])
              ->create();

自定义列类型和默认值
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

一些数据库管理系统（DBMS）提供额外的列类型设置，或它们特有的默认值。
如果你想让你的迁移和 DBMS 关联，你可以通过 ``\Phinx\Util\Literal::from`` 方法在迁移中使用这些自定义类型。
该方法有一个唯一的字符串类型参数，并返回 ``\Phinx\Util\Literal`` 的一个实例。
当 Phinx 发现这个值作为列的类型时，它会不会进行任何验证，并完全按照提供的方式进行使用且不会转义。
这也适用于 ``default`` 值。

你可以在下面看到一个示例，该示例展示了如何在 PostgreSQL 中添加 ``citext`` 列，和一个默认值为函数的列。
所有适配器都支持这种防止内置转义的方法。

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

用户自定义类型（自定义数据域）
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

建立在基本类型和列选项的基础上，你还可以配置自定义类型（user defined type）。
添加用户自定义类型需在根配置中添加 ``data_domain``项目。

.. code-block:: yaml

    data_domain:
        phone_number:
            type: string
            length: 20
        address_line:
            type: string
            length: 150

每个用户自定义类型都可以包含【有效的类型和列选项】，它们只是用作“宏（macros）”并在迁移时被替换。

.. code-block:: php

        <?php

        //...

        $table = $this->table('user_data');
        $table->addColumn('user_phone_number', 'phone_number')
              ->addColumn('user_address_line_1', 'address_line')
              ->addColumn('user_address_line_2', 'address_line', ['null' => true])
              ->create();

在项目开始时指定数据域对于拥有 同质数据模型（homogeneous data model）至关重要。
它会避免一些错误，比如具有许多不同长度的 ``contact_name`` 列、不匹配的整数类型（long 与 bigint）等等。

.. note::

    对于 ``integer``, ``text`` 和 ``blob``列，你可以使用 MySQL 和 Postgres 适配器类中的特殊常量。

    You can even customize some internal types to add your own default options,
    but some column options can't be overriden in the data model (some options
    are fixed like ``limit`` for the ``uuid`` special data type).
    你甚至可以自定义一些内部类型来创造一些独有的默认选项，但是一些列选项不能在数据模型中被覆盖（一些选项是固定的，比如 ``uuid`` 这个特殊数据类型的 ``limit`` 选项）。

.. code-block:: yaml

    # Some examples of custom data types
    data_domain:
        file:
            type: blob
            limit: BLOB_LONG    # For MySQL DB. Uses MysqlAdapter::BLOB_LONG
        boolean:
            type: boolean       # Customization of the boolean to be unsigned
            signed: false
        image_type:
            type: enum          # Enums can use YAML lists or a comma separated string
            values:
                - gif
                - jpg
                - png

获取列的列表
~~~~~~~~~~~~~~~~~

要获取表的所有列，只需创建一个 `table` 对象并调用 `getColumns()` 方法。
此方法将返回一个包含基本信息的 Column 类数组。下面是例子：

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

按名称获取列
~~~~~~~~~~~~~~~~~~~~

要获取表的一个列，只需创建一个 `table` 对象并调用 `getColumn()` 方法。
此方法将返回具有基本信息的 Column 类，当列不存在时返回 NULL。下面是例子：

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

检查列是否存在
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

你可以使用 ``hasColumn()`` 方法检查表是否已经有某个列。

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

重命名列
~~~~~~~~~~~~~~~~~

要重命名列，请访问 Table 对象的实例，然后调用 ``renameColumn()`` 方法。

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

在另一列之后添加一列
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

当使用 MySQL 适配器添加列时，你可以使用 ``after`` 选项指定它的位置，它的值是“要放在 指定列后面 的 指定列的名字”。

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

这将创建新列 ``city`` 并将其放置在 ``email`` 列之后。
你可以使用 `\Phinx\Db\Adapter\MysqlAdapter\FIRST` 常量来指定将新列创建为该表中的第一列。

删除一列
~~~~~~~~~~~~~~~~~

要删除列，请使用 ``removeColumn()`` 方法。

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


指定列限制
~~~~~~~~~~~~~~~~~~~~~~~~~

你可以使用 ``limit`` 选项限制列的最大长度。

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

更改列属性
~~~~~~~~~~~~~~~~~~~~~~~~~~

要更改现有列的列类型或选项，请使用 ``changeColumn()`` 方法。
有关允许的值，请参阅 :ref:`valid-column-types` 和 `Valid Column Options`_。

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

使用索引
--------------------

要将索引添加到表中，你只需在表对象上调用 ``addIndex()`` 方法即可。

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

默认情况下，Phinx 会指示数据库适配器创建一个普通索引。
我们可以将附加参数 ``unique`` 传递给 ``addIndex()`` 方法以指定唯一索引。
我们还可以使用 ``name`` 参数显式指定索引的名称，也可以使用 ``order`` 参数指定索引列的排序顺序。
order 参数接受列名和排序顺序的键/值对数组。

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
                      ->addColumn('username','string')
                      ->addIndex(['email', 'username'], [
                            'unique' => true,
                            'name' => 'idx_users_email',
                            'order' => ['email' => 'DESC', 'username' => 'ASC']]
                            )
                      ->save();
            }

            /**
             * Migrate Down.
             */
            public function down()
            {

            }
        }

MySQL 适配器还支持 ``fulltext`` 索引。如果你使用的是 5.6 之前的版本，你必须确保该表使用 ``MyISAM`` 引擎。

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

此外，MySQL 适配器还支持设置 limit 选项定义的索引长度。
当你使用多列索引（multi-column index）时，你可以定义每列索引长度。
单列索引在 limit 选项中定义其索引长度时，可附加指定列名，也可以不指定。

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

SQL Server 和 PostgreSQL 适配器还支持索引上的 ``include``（非键）列。

.. code-block:: php

        <?php

        use Phinx\Migration\AbstractMigration;

        class MyNewMigration extends AbstractMigration
        {
            public function change()
            {
                $table = $this->table('users');
                $table->addColumn('email', 'string')
                      ->addColumn('firstname','string')
                      ->addColumn('lastname','string')
                      ->addIndex(['email'], ['include' => ['firstname', 'lastname']])
                      ->create();
            }
        }


删除索引直接调用 ``removeIndex()`` 方法即可。 你必须为每个索引调用此方法。

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


使用外键
-------------------------

Phinx 支持在数据库表上创建外键约束。
让我们在示例表中添加一个外键：

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

"On delete" 和 "On update" 操作使用 'delete' 和 'update' 选项数组定义。
可使用的值为 'SET_NULL', 'NO_ACTION', 'CASCADE' 和 'RESTRICT'。
如果使用 'SET_NULL'，则必须使用选项 ``['null' => true]`` 将列创建为可为空的。
约束名称可以使用  'constraint'  选项进行更改。

也可以传递给 ``addForeignKey()`` 一个列的数组。
这使得我们可以与使用组合键（combined key）的表建立外键关系。

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

我们可以使用 ``constraint`` 参数添加命名外键。 从 Phinx 0.6.5 版本起开始支持此功能。

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

我们还可以轻松检查是否存在外键：

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

最后，要删除外键，请使用 ``dropForeignKey`` 方法。

请注意，与 ``Table`` 类中的其他方法一样， ``dropForeignKey`` 也需要在最后调用 ``save()`` 才能执行。
这使得 phinx 在涉及多个表时能智能地计划迁移。

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



使用查询生成器
-----------------------

数据库结构与相应数据配对更改并不少见。
例如，你可能希望将几列中的数据从用户迁移到新创建的表。
对于这种类型的场景，Phinx 提供对查询构建器对象（Query builder object）的访问，你可以使用它来执行复杂的 ``SELECT``, ``UPDATE``, ``INSERT`` 或 ``DELETE``语句。

Query builder 由 `cakephp/database <https://github.com/cakephp/database>`_ 项目提供，他们很容易使用，因为非常类似于普通的 SQL。
通过调用 ``getQueryBuilder()`` 函数来访问查询生成器：


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

选择字段
~~~~~~~~~~~~~~~~

向 SELECT 子句添加字段：


.. code-block:: php

        <?php
        $builder->select(['id', 'title', 'body']);

        // Results in SELECT id AS pk, title AS aliased_title, body ...
        $builder->select(['pk' => 'id', 'aliased_title' => 'title', 'body']);

        // Use a closure
        $builder->select(function ($builder) {
            return ['id', 'title', 'body'];
        });


Where 条件
~~~~~~~~~~~~~~~~

生成条件：

.. code-block:: php

        // WHERE id = 1
        $builder->where(['id' => 1]);

        // WHERE id > 1
        $builder->where(['id >' => 1]);


如你所见，在字段名称后放置一个空格再写运算符即可。添加多个条件也很容易：


.. code-block:: php

        <?php
        $builder->where(['id >' => 1])->andWhere(['title' => 'My Title']);

        // Equivalent to
        $builder->where(['id >' => 1, 'title' => 'My title']);

        // WHERE id > 1 OR title = 'My title'
        $builder->where(['OR' => ['id >' => 1, 'title' => 'My title']]);


对于更复杂的条件，你可以使用闭包和表达式对象：

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


结果是：

.. code-block:: sql

    SELECT * FROM articles
    WHERE
        author_id = 2
        AND published = 1
        AND spam != 1
        AND view_count > 10


组合表达式也是可以的：


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

它将生成：

.. code-block:: sql

    SELECT *
    FROM articles
    WHERE
        NOT (author_id = 2 OR author_id = 5)
        AND view_count <= 10


使用表达式对象时，你可以使用以下方法创建条件：

* ``eq()`` 创建一个相等条件。
* ``notEq()`` 创建一个不等式条件
* ``like()`` 使用 ``LIKE`` 操作符创建一个条件。
* ``notLike()`` 创建一个否定的 ``LIKE`` 条件。
* ``in()`` 使用 ``IN`` 创建一个条件。
* ``notIn()`` 使用 ``IN`` 创建一个否定条件。
* ``gt()`` 创建一个 ``>`` 条件。
* ``gte()`` 创建一个 ``>=`` 条件。
* ``lt()`` 创建一个 ``<`` 条件。
* ``lte()`` 创建一个 ``<=`` 条件。
* ``isNull()`` 创建一个 ``IS NULL`` 条件。
* ``isNotNull()`` 创建一个否定的 ``IS NULL`` 条件。


聚合和 SQL 函数
~~~~~~~~~~~~~~~~~~~~~~~~~~~~


.. code-block:: php

    <?php
    // Results in SELECT COUNT(*) count FROM ...
    $builder->select(['count' => $builder->func()->count('*')]);

使用 func() 方法可以创建许多常用的函数：

* ``sum()`` 计算总和。参数将被视为字面量。
* ``avg()`` 计算平均值。参数将被视为字面量。
* ``min()`` 计算列的最小值。参数将被视为字面量。
* ``max()`` 计算列的最大值。参数将被视为字面量。
* ``count()`` 计算计数。参数将被视为字面量。
* ``concat()`` 将两个值连接在一起。除非标记为字面量，否则参数将被视为绑定参数。
* ``coalesce()`` 合并值。 除非标记为字面量，否则参数将被视为绑定参数。
* ``dateDiff()`` 获取两个日期/时间之间的差异。 除非标记为字面量，否则参数将被视为绑定参数。
* ``now()`` 以 'time' 或 'date' 作为参数，允许你获取当前时间或当前日期。

为 SQL 函数提供参数时，可以使用两种参数，字面量参数和绑定参数。
字面量参数允许你引用列或其他 SQL 文本。
绑定参数可用于安全地将用户数据添加到 SQL 函数。
例如：


.. code-block:: php

    <?php
    // Generates:
    // SELECT CONCAT(title, ' NEW') ...;
    $concat = $builder->func()->concat([
        'title' => 'literal',
        ' NEW'
    ]);
    $query->select(['title' => $concat]);


从查询中获取结果
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

进行查询后，你可能希望从其中获取行数据。有几种方法可以做到这一点：


.. code-block:: php

    <?php
    // Iterate the query
    foreach ($builder as $row) {
        echo $row['title'];
    }

    // Get the statement and fetch all results
    $results = $builder->execute()->fetchAll('assoc');


创建插入查询
~~~~~~~~~~~~~~~~~~~~~~~~

也可以创建插入查询：


.. code-block:: php

    <?php
    $builder = $this->getQueryBuilder();
    $builder
        ->insert(['first_name', 'last_name'])
        ->into('users')
        ->values(['first_name' => 'Steve', 'last_name' => 'Jobs'])
        ->values(['first_name' => 'Jon', 'last_name' => 'Snow'])
        ->execute()


为了提高性能，你可以使用另一个构建器对象作为插入查询的值：

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


上面的代码会生成：

.. code-block:: sql

    INSERT INTO names (first_name, last_name)
        (SELECT fname, lname FROM USERS where is_active = 1)


创建更新查询
~~~~~~~~~~~~~~~~~~~~~~~~

创建更新查询类似于插入和选择：

.. code-block:: php

    <?php
    $builder = $this->getQueryBuilder();
    $builder
        ->update('users')
        ->set('fname', 'Snow')
        ->where(['fname' => 'Jon'])
        ->execute()


创建删除查询
~~~~~~~~~~~~~~~~~~~~~~~

最后，删除查询：

.. code-block:: php

    <?php
    $builder = $this->getQueryBuilder();
    $builder
        ->delete('users')
        ->where(['accepted_gdpr' => false])
        ->execute()
