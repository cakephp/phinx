.. index::
   single: Configuration

配置
=============

当您使用 :doc:`初始化命令<commands>` 初始化项目时，Phinx 会在项目的根目录中创建一个默认文件。
默认情况下，此文件使用 YAML 数据序列化格式，但您可以使用 ``--format`` 命令行选项指定 ``yaml``, ``yml``, ``json`` 或 ``php``。

如果给出了 ``--configuration`` 命令行选项，Phinx 将加载指定的文件。
否则，它将尝试查找 ``phinx.php``, ``phinx.json``, ``phinx.yml``, 或 ``phinx.yaml`` 并加载第一个找到的文件。
有关更多信息，请参阅 :doc:`命令 <commands>` 章节。

.. warning::

    请记住不要将配置文件存储在服务器的可公开访问目录。此文件包含数据库凭据，可能会意外的被视为纯文本。

请注意，虽然 JSON 和 YAML 文件是 *被解析(parsed)* 的，但 PHP 文件是 *包含(included)* 的。这意味着：

* 它必须 `返回` 一个配置项数组。
* 变量范围是本地的(local)，也就是说，初始化文件读取或修改的任何全局变量需要被显式声明。
* 它的标准输出被抑制了。
* 与 JSON 和 YAML 不同，可以省略环境连接的详细信息，而改为指定一个已初始化的 PDO 实例的 ``连接``。
  当您希望迁移与应用程序交互 和/或 共享相同的连接时，这将很有用。
  但是请记住还要传递数据库名称，因为 Phinx 无法从 PDO 连接中推断出这一点。

.. code-block:: php

    $app = require 'app/phinx.php';
    $pdo = $app->getDatabase()->getPdo();

    return [
        'environments' => [
            'default_environment' => 'development',
            'development' => [
                'name' => 'devdb',
                'connection' => $pdo
            ]
        ]
    ];

迁移路径
---------------

第一个选项指定迁移目录的路径。
Phinx 默认使用 ``%%PHINX_CONFIG_DIR%%/db/migrations``。

.. note::

    ``%%PHINX_CONFIG_DIR%%`` 是一个特殊的标记，它会自动替换为存储 phinx 配置文件的根目录。

为了覆盖默认的 ``%%PHINX_CONFIG_DIR%%/db/migrations``，您需要在配置中添加以下内容。

.. code-block:: yaml

    paths:
        migrations: /your/full/path

您还可以通过在配置中使用数组来提供多个迁移路径：

.. code-block:: yaml

    paths:
        migrations:
            - application/module1/migrations
            - application/module2/migrations


您还可以在路径中使用 ``%%PHINX_CONFIG_DIR%%`` 标记。

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/your/relative/path'


迁移是使用 ``glob`` 捕获的，因此您可以为多个目录定义一个模式。

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/module/*/{data,scripts}/migrations'

自定义迁移基类
---------------------

默认情况下，所有迁移都将继承自 Phinx 的 `AbstractMigration` 类。
可以自定义该类，通过继承 `AbstractMigration` 类，并配置 ``migration_base_class`` 选项：

.. code-block:: yaml

    migration_base_class: MyMagicalMigration

种子路径
----------

第二个选项指定种子目录的路径。 Phinx 默认使用 ``%%PHINX_CONFIG_DIR%%/db/seeds``。

.. note::

    ``%%PHINX_CONFIG_DIR%%`` 是一个特殊的标记，它会自动替换为存储配置文件的根目录。

为了覆盖默认的 ``%%PHINX_CONFIG_DIR%%/db/seeds``，你需要在 yaml 配置中添加以下内容。

.. code-block:: yaml

    paths:
        seeds: /your/full/path

您还可以通过在配置中使用数组来提供多个种子路径：

.. code-block:: yaml

    paths:
        seeds:
            - /your/full/path1
            - /your/full/path2


您还可以在路径中使用 ``%%PHINX_CONFIG_DIR%%`` 标记。

.. code-block:: yaml

    paths:
        seeds: '%%PHINX_CONFIG_DIR%%/your/relative/path'

定制播种器基类
---------------------

默认情况下，所有播种器（seeders）都将继承 Phinx 的 `AbstractSeed` 类。
可以自定义该类，通过继承 `AbstractSeed` 类，并配置 ``seeder_base_class`` 选项：

.. code-block:: yaml

    seeder_base_class: MyMagicalSeeder

环境
------------

Phinx 的主要功能之一是支持多种数据库环境。
您可以使用 Phinx 在开发环境中创建迁移，然后在生产环境中运行相同的迁移。
相应的环境在 ``environments`` 嵌套集合下指定。例如：

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            adapter: mysql
            host: localhost
            name: production_db
            user: root
            pass: ''
            port: 3306
            charset: utf8
            collation: utf8_unicode_ci

将定义一个名为 ``production`` 的新环境。

当多个开发人员在同一个项目上工作，并且每个人都有不同的环境的情况下（比如``<环境类型>-<开发人员名称>-<机器名称>``这样的约定），
或者当需要分离不同的开发目的（如分支、测试等）的环境时，可使用 `PHINX_ENVIRONMENT` 来覆盖 yaml 文件中的默认环境：

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`

迁移表
---------------

为了跟踪环境的迁移状态，Phinx 创建了一个表来存储此信息。
您可以通过配置 ``default_migration_table`` 来自定义此表名：

.. code-block:: yaml

    environment:
        default_migration_table: phinxlog

如果省略此字段，则默认为 ``phinxlog``。
对于某些支持 模式（schema） 的数据库，例如 Postgres，模式名可以用句点（ ``.`` ）分隔作为前缀。
例如， ``phinx.log`` 则将在 ``phinx`` 模式中创建 ``log`` 表，而不是在 ``public``（默认）模式中创建 ``phinxlog``。

表前缀和后缀
-----------------------

您可以定义表前缀和表后缀：

.. code-block:: yaml

    environments:
        development:
            ....
            table_prefix: dev_
            table_suffix: _v1
        testing:
            ....
            table_prefix: test_
            table_suffix: _v2


套接字连接
------------------

使用 MySQL 适配器时，也可以使用套接字(socket)代替网络连接。套接字路径配置为 ``unix_socket``：

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            adapter: mysql
            name: production_db
            user: root
            pass: ''
            unix_socket: /var/run/mysql/mysql.sock
            charset: utf8

外部变量
------------------

Phinx 将自动获取以 ``PHINX_`` 为前缀的环境变量，并将其作为配置文件中的标记（token）提供。
标记将与变量具有完全相同的名称，但您必须通过在其两侧包裹两个 ``%%`` 符号来访问它。例如： ``'%%PHINX_DBUSER%%'``。
如果您希望将数据库凭据直接存储在服务器上，而不是版本控制系统中，这将特别有用。
此功能可以通过以下示例轻松演示：

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            adapter: mysql
            host: '%%PHINX_DBHOST%%'
            name: '%%PHINX_DBNAME%%'
            user: '%%PHINX_DBUSER%%'
            pass: '%%PHINX_DBPASS%%'
            port: 3306
            charset: utf8

数据源名称
-----------------

Phinx 支持使用数据源名称 (DSN) 来指定连接选项，如果您使用单个环境变量来保存数据库凭据，这将很有用。
PDO 具有不同的 DSN 格式，具体取决于底层驱动程序，所以 Phinx 和其它项目一样（如Doctrine、Rails、AMQP、PaaS 等），使用一种与数据库无关的 DSN 格式。

.. code-block:: text

    <adapter>://[<user>[:<pass>]@]<host>[:<port>]/<name>[?<additionalOptions>]

* DSN 必须有 ``adapter``, ``host`` 和 ``name``。
* 若要指定密码则必须有用户名。
* ``port`` 必须是一个正整数。
* ``additionalOptions`` 采用查询字符串的形式，并将传递给选项数组中的适配器。

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            # Example data source name
            dsn: mysql://root@localhost:3306/mydb?charset=utf8

解析 DSN 后，它的值将与现有的连接选项合并。
DSN 中配置的值 不会覆盖 连接选项配置的值。

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        development:
            dsn: %%DATABASE_URL%%
        production:
            dsn: %%DATABASE_URL%%
            name: production_database

如果提供的 DSN 无效，则完全忽略它。

支持的适配器
------------------

Phinx 目前原生支持以下数据库适配器：

* `MySQL <http://www.mysql.com/>`_: 指定 ``mysql`` 适配器。
* `PostgreSQL <http://www.postgresql.org/>`_: 指定 ``pgsql`` 适配器。
* `SQLite <http://www.sqlite.org/>`_: 指定 ``sqlite`` 适配器。
* `SQL Server <http://www.microsoft.com/sqlserver>`_: 指定 ``sqlsrv`` 适配器。

对于每个适配器，都可以配置底层 PDO 对象的行为，方法是在配置对象中设置常量名称的小写版本。
这适用于 PDO 选项（例如， ``\PDO::ATTR_CASE`` 则应该是 ``attr_case``）和适配器特定选项
（例如，对于 MySQL，可将 ``\PDO::MYSQL_ATTR_IGNORE_SPACE`` 设置为 ``mysql_attr_ignore_space``）。
请查阅 `PDO 文档 <https://www.php.net/manual/en/book.pdo.php>`_ 了解允许的属性和值。

例如，要设置上述示例选项：

.. code-block:: php

    $config = [
        "environments" => [
            "development" => [
                "adapter" => "mysql",
                # other adapter settings
                "attr_case" => \PDO::ATTR_CASE,
                "mysql_attr_ignore_space" => 1,
            ],
        ],
    ];

默认情况下，Phinx 设置的唯一属性是将 ``\PDO::ATTR_ERRMODE`` 设置成了 ``PDO::ERRMODE_EXCEPTION``。不建议覆盖它。

MySQL
`````````````````

MySQL 适配器有一个不幸的限制，即无论事务状态如何，某些操作都会导致 `隐式提交 <https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html>`_。
值得注意的是，该列表包括 ``CREATE TABLE``, ``ALTER TABLE`` 和 ``DROP TABLE``，它们是 Phinx 将运行的最常见的操作。
这意味着，其他适配器可以在迁移失败时优雅地回滚事务，但 MySQL 适配器不一样。
如果 MySQL 的迁移失败，它可能会使您的数据库处于部分迁移的状态。

SQLite
`````````````````

使用简化的结构声明 SQLite 数据库：

.. code-block:: yaml

    environments:
        development:
            adapter: sqlite
            name: ./data/derby
            suffix: ".db"    # Defaults to ".sqlite3"
        testing:
            adapter: sqlite
            memory: true     # Setting memory to *any* value overrides name

SQL Server
`````````````````

当使用 ``sqlsrv`` 适配器并连接到一个命名实例（named instance）时，你应该省略 ``port`` 设置，因为 SQL Server 将自动协商端口。
此外，对于 SQL Server 的 UTF8 省略 ``charset: utf8`` 或更改为 ``charset: 65001``。

自定义适配器
`````````````````

自定义适配器时，首先实现 `Phinx\\Db\\Adapter\\AdapterInterface` 接口，随后使用 `AdapterFactory` 进行注册：

.. code-block:: php

    $name  = 'fizz';
    $class = 'Acme\Adapter\FizzAdapter';

    AdapterFactory::instance()->registerAdapter($name, $class);

适配器可以在调用 `$app->run()` 之前的任何时间注册，通常由 `bin/phinx` 调用。

别名
-------

模板创建类的名称可以使用别名，并与 :doc:`创建命令 <commands>` 的 ``--class`` 命令行选项一起使用。

别名类仍然需要实现 ``Phinx\Migration\CreationInterface`` 接口。

.. code-block:: yaml

    aliases:
        permission: \Namespace\Migrations\PermissionMigrationTemplateGenerator
        view: \Namespace\Migrations\ViewMigrationTemplateGenerator

版本顺序
-------------

当回滚或打印迁移状态时，Phinx 根据 ``version_order`` 选项对执行的迁移进行排序，该选项可以具有以下值：

* ``creation`` （默认）：迁移按其创建时间排序，这也是其文件名的一部分。
* ``execution``：迁移按执行时间排序(execution time)，也称为开始时间(start time)。

引导路径
---------------

您可以提供一个 `bootstrap` PHP 文件的路径，该文件将在运行任何 Phinx 命令之前被包含。
请注意，设置外部变量来修改配置将不起作用，因为此时配置已经被解析。

.. code-block:: yaml

    paths:
        bootstrap: 'phinx-bootstrap.php'

在引导脚本中，以下变量将可用：

.. code-block:: php

    /**
     * @var string $filename 配置提供的文件名
     * @var string $filePath 文件的真实路径（绝对路径）
     * @var \Symfony\Component\Console\Input\InputInterface $input 正在执行的命令的输入对象
     * @var \Symfony\Component\Console\Output\OutputInterface $output 正在执行的命令的输出对象
     * @var \Phinx\Console\Command\AbstractCommand $context 正在执行的命令对象
     */
