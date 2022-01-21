.. index::
   single: Configuration

配置
=============

When you initialize your project using the :doc:`Init Command<commands>`, Phinx
creates a default file in the root of your project directory. By default, this
file uses the YAML data serialization format, but you can use the ``--format``
command line option to specify either ``yaml``, ``yml``, ``json``, or ``php``.
当您使用 Init Command<commands>` 初始化项目时，Phinx 会在项目目录的根目录中创建一个默认文件。 默认情况下，此文件使用 YAML 数据序列化格式，但您可以使用 ``--format`` 命令行选项指定 ``yaml``、``yml``、``json`` 或 ` `php`。

If a ``--configuration`` command line option is given, Phinx will load the
specified file. Otherwise, it will attempt to find ``phinx.php``, ``phinx.json``,
``phinx.yml``, or ``phinx.yaml`` and load the first file found. See the
:doc:`Commands <commands>` chapter for more information.
如果给出了 --configuration 命令行选项，Phinx 将加载指定的文件。 否则，它将尝试查找“phinx.php”、“phinx.json”、“phinx.yml”或“phinx.yaml”并加载找到的第一个文件。 有关更多信息，请参阅:doc:`Commands <commands>` 章节。

.. warning::

    Remember to store the configuration file outside of a publicly accessible
    directory on your webserver. This file contains your database credentials
    and may be accidentally served as plain text.
    请记住将配置文件存储在网络服务器上可公开访问的目录之外。 此文件包含您的数据库凭据，可能会意外地以纯文本形式提供。

Note that while JSON and YAML files are *parsed*, the PHP file is *included*.
This means that:
请注意，虽然 JSON 和 YAML 文件是 *parsed* 的，但 PHP 文件是 *included* 的。
这意味着：

* It must `return` an array of configuration items.
* The variable scope is local, i.e. you would need to explicitly declare
  any global variables your initialization file reads or modifies.
* Its standard output is suppressed.
* Unlike with JSON and YAML, it is possible to omit environment connection details
  and instead specify ``connection`` which must contain an initialized PDO instance.
  This is useful when you want your migrations to interact with your application
  and/or share the same connection. However remember to also pass the database name
  as Phinx cannot infer this from the PDO connection.
  * 它必须“返回”一个配置项数组。
* 变量范围是本地的，即您需要显式声明初始化文件读取或修改的任何全局变量。
* 其标准输出被抑制。
* 与 JSON 和 YAML 不同，可以省略环境连接详细信息，而是指定必须包含已初始化 PDO 实例的“连接”。
   当您希望迁移与应用程序交互和/或共享相同的连接时，这很有用。 但是请记住还要传递数据库名称，因为 Phinx 无法从 PDO 连接中推断出这一点。

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

Migration Paths 迁移路径
---------------

The first option specifies the path to your migration directory. Phinx uses
``%%PHINX_CONFIG_DIR%%/db/migrations`` by default.
第一个选项指定迁移目录的路径。 Phinx 默认使用 ``%%PHINX_CONFIG_DIR%%/db/migrations``。

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your phinx configuration file is stored.
    ``%%PHINX_CONFIG_DIR%%`` 是一个特殊的标记，它会自动替换为存储 phinx 配置文件的根目录。

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/db/migrations``, you
need to add the following to the configuration.
为了覆盖默认的“%%PHINX_CONFIG_DIR%%/db/migrations”，您需要在配置中添加以下内容。

.. code-block:: yaml

    paths:
        migrations: /your/full/path

You can also provide multiple migration paths by using an array in your configuration:
您还可以通过在配置中使用数组来提供多个迁移路径：

.. code-block:: yaml

    paths:
        migrations:
            - application/module1/migrations
            - application/module2/migrations


You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.
您还可以在路径中使用 ``%%PHINX_CONFIG_DIR%%`` 标记。

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/your/relative/path'

Migrations are captured with ``glob``, so you can define a pattern for multiple
directories.
迁移是使用“glob”捕获的，因此您可以为多个目录定义一个模式。

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/module/*/{data,scripts}/migrations'

Custom Migration Base 自定义迁移基地
---------------------

By default all migrations will extend from Phinx's `AbstractMigration` class.
This can be set to a custom class that extends from `AbstractMigration` by
setting ``migration_base_class`` in your config:
默认情况下，所有迁移都将从 Phinx 的 `AbstractMigration` 类扩展。
这可以通过在配置中设置 ``migration_base_class`` 设置为从 `AbstractMigration` 扩展的自定义类：

.. code-block:: yaml

    migration_base_class: MyMagicalMigration

Seed Paths 种子路径
----------

The second option specifies the path to your seed directory. Phinx uses
``%%PHINX_CONFIG_DIR%%/db/seeds`` by default.
第二个选项指定种子目录的路径。 菲尼克斯使用
``%%PHINX_CONFIG_DIR%%/db/seeds`` 默认情况下。

.. note::

    ``%%PHINX_CONFIG_DIR%%`` is a special token and is automatically replaced
    with the root directory where your configuration file is stored.
    ``%%PHINX_CONFIG_DIR%%`` 是一个特殊的标记，它会自动替换为存储配置文件的根目录。

In order to overwrite the default ``%%PHINX_CONFIG_DIR%%/db/seeds``, you
need to add the following to the yaml configuration.
为了覆盖默认的 ``%%PHINX_CONFIG_DIR%%/db/seeds``，你需要在 yaml 配置中添加以下内容。

.. code-block:: yaml

    paths:
        seeds: /your/full/path

You can also provide multiple seed paths by using an array in your configuration:
您还可以通过在配置中使用数组来提供多个种子路径：

.. code-block:: yaml

    paths:
        seeds:
            - /your/full/path1
            - /your/full/path2


You can also use the ``%%PHINX_CONFIG_DIR%%`` token in your path.
您还可以在路径中使用 ``%%PHINX_CONFIG_DIR%%`` 标记。

.. code-block:: yaml

    paths:
        seeds: '%%PHINX_CONFIG_DIR%%/your/relative/path'

Custom Seeder Base 定制播种机基地
---------------------

By default all seeders will extend from Phinx's `AbstractSeed` class.
This can be set to a custom class that extends from `AbstractSeed` by
setting ``seeder_base_class`` in your config:
默认情况下，所有播种机都将从 Phinx 的“AbstractSeed”类扩展。
这可以通过在配置中设置 ``seeder_base_class`` 设置为从 `AbstractSeed` 扩展的自定义类：

.. code-block:: yaml

    seeder_base_class: MyMagicalSeeder

Environments 环境
------------

One of the key features of Phinx is support for multiple database environments.
You can use Phinx to create migrations on your development environment, then
run the same migrations on your production environment. Environments are
specified under the ``environments`` nested collection. For example:
Phinx 的主要功能之一是支持多种数据库环境。 您可以使用 Phinx 在开发环境中创建迁移，然后在生产环境中运行相同的迁移。 环境在 ``environments`` 嵌套集合下指定。 例如：

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

would define a new environment called ``production``.
将定义一个名为“生产”的新环境。

In a situation when multiple developers work on the same project and each has
a different environment (e.g. a convention such as ``<environment
type>-<developer name>-<machine name>``), or when you need to have separate
environments for separate purposes (branches, testing, etc) use environment
variable `PHINX_ENVIRONMENT` to override the default environment in the yaml
file:
在多个开发人员在同一个项目上工作并且每个人都有不同的环境（例如，诸如``<环境类型>-<开发人员名称>-<机器名称>``这样的约定）的情况下，或者当您需要单独的环境时 用于单独目的（分支、测试等）的环境使用环境变量 PHINX_ENVIRONMENT` 来覆盖 yaml 文件中的默认环境：

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`

Migration Table 迁移表
---------------

To keep track of the migration statuses for an environment, phinx creates
a table to store this information. You can customize where this table
is created by configuring ``default_migration_table``:
为了跟踪环境的迁移状态，phinx 创建了一个表来存储此信息。 您可以通过配置“default_migration_table”来自定义创建此表的位置：

.. code-block:: yaml

    environment:
        default_migration_table: phinxlog

If this field is omitted, then it will default to ``phinxlog``. For
databases that support it, e.g. Postgres, the schema name can be prefixed
with a period separator (``.``). For example, ``phinx.log`` will create
the table ``log`` in the ``phinx`` schema instead of ``phinxlog`` in the
``public`` (default) schema.
如果省略此字段，则默认为 ``phinxlog``。 对于支持它的数据库，例如 Postgres，模式名称可以以句点分隔符（``.``）作为前缀。 例如，``phinx.log`` 将在 ``phinx`` 架构中创建 ``log`` 表，而不是在 ``public`` （默认）架构中创建 ``phinxlog``。

Table Prefix and Suffix 表前缀和后缀
-----------------------

You can define a table prefix and table suffix:
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


Socket Connections 插座连接
------------------

When using the MySQL adapter, it is also possible to use sockets instead of
network connections. The socket path is configured with ``unix_socket``:
使用 MySQL 适配器时，也可以使用套接字代替
网络连接。 套接字路径配置为“unix_socket”：

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

External Variables 外部变量
------------------

Phinx will automatically grab any environment variable prefixed with ``PHINX_``
and make it available as a token in the config file. The token will have
exactly the same name as the variable but you must access it by wrapping two
``%%`` symbols on either side. e.g: ``'%%PHINX_DBUSER%%'``. This is especially
useful if you wish to store your secret database credentials directly on the
server and not in a version control system. This feature can be easily
demonstrated by the following example:
Phinx 将自动获取任何以“PHINX_”为前缀的环境变量，并将其作为配置文件中的标记提供。 标记将与变量具有完全相同的名称，但您必须通过在两侧包装两个 ``%%`` 符号来访问它。 例如：``'%%PHINX_DBUSER%%'``。 如果您希望将秘密数据库凭据直接存储在服务器上而不是版本控制系统中，这将特别有用。 此功能可以通过以下示例轻松演示：

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

Data Source Names 数据源名称
-----------------

Phinx supports the use of data source names (DSN) to specify the connection
options, which can be useful if you use a single environment variable to hold
the database credentials. PDO has a different DSN formats depending on the
underlying driver, so Phinx uses a database-agnostic DSN format used by other
projects (Doctrine, Rails, AMQP, PaaS, etc).
Phinx 支持使用数据源名称 (DSN) 来指定连接选项，如果您使用单个环境变量来保存数据库凭据，这将很有用。 PDO 具有不同的 DSN 格式，具体取决于底层驱动程序，因此 Phinx 使用其他项目（Doctrine、Rails、AMQP、PaaS 等）使用的与数据库无关的 DSN 格式。

.. code-block:: text

    <adapter>://[<user>[:<pass>]@]<host>[:<port>]/<name>[?<additionalOptions>]

* A DSN requires at least ``adapter``, ``host`` and ``name``.
* You cannot specify a password without a username.
* ``port`` must be a positive integer.
* ``additionalOptions`` takes the form of a query string, and will be passed to
  the adapter in the options array.

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        production:
            # Example data source name
            dsn: mysql://root@localhost:3306/mydb?charset=utf8

Once a DSN is parsed, it's values are merged with the already existing
connection options. Values in specified in a DSN will never override any value
specified directly as connection options.
解析 DSN 后，它的值将与现有的连接选项合并。 DSN 中指定的值永远不会覆盖直接指定为连接选项的任何值。

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_environment: development
        development:
            dsn: %%DATABASE_URL%%
        production:
            dsn: %%DATABASE_URL%%
            name: production_database

If the supplied DSN is invalid, then it is completely ignored.
如果提供的 DSN 无效，则完全忽略它。

Supported Adapters 支持的适配器
------------------

Phinx currently supports the following database adapters natively:
Phinx 目前原生支持以下数据库适配器：

* `MySQL <http://www.mysql.com/>`_: specify the ``mysql`` adapter. 指定 ``mysql`` 适配器。
* `PostgreSQL <http://www.postgresql.org/>`_: specify the ``pgsql`` adapter. 指定 pgsql 适配器。
* `SQLite <http://www.sqlite.org/>`_: specify the ``sqlite`` adapter. 指定“sqlite”适配器。
* `SQL Server <http://www.microsoft.com/sqlserver>`_: specify the ``sqlsrv`` adapter. 指定 ``sqlsrv`` 适配器。

For each adapter, you may configure the behavior of the underlying PDO object by setting in your
config object the lowercase version of the constant name. This works for both PDO options
(e.g. ``\PDO::ATTR_CASE`` would be ``attr_case``) and adapter specific options (e.g. for MySQL
you may set ``\PDO::MYSQL_ATTR_IGNORE_SPACE`` as ``mysql_attr_ignore_space``). Please consult
the `PDO documentation <https://www.php.net/manual/en/book.pdo.php>`_ for the allowed attributes
and their values.
对于每个适配器，您可以通过在配置对象中设置常量名称的小写版本来配置底层 PDO 对象的行为。 这适用于 PDO 选项（例如，``\PDO::ATTR_CASE`` 将是 ``attr_case``）和适配器特定选项（例如，对于 MySQL，您可以将 ``\PDO::MYSQL_ATTR_IGNORE_SPACE`` 设置为 ``mysql_attr_ignore_space` `）。 请查阅 `PDO 文档 <https://www.php.net/manual/en/book.pdo.php>`_ 了解允许的属性及其值。

For example, to set the above example options:
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

By default, the only attribute that Phinx sets is ``\PDO::ATTR_ERRMODE`` to ``PDO::ERRMODE_EXCEPTION``. It is
not recommended to override this.
默认情况下，Phinx 设置的唯一属性是 ``\PDO::ATTR_ERRMODE`` 到 ``PDO::ERRMODE_EXCEPTION``。 不建议覆盖它。

MySQL
`````````````````

The MySQL adapter has an unfortunate limitation in that it certain actions causes an
`implicit commit <https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html>`_ regardless of transaction
state. Notably this list includes ``CREATE TABLE``, ``ALTER TABLE``, and ``DROP TABLE``, which are the most
common operations that Phinx will run. This means that unlike other adapters which will attempt to gracefully
rollback a transaction on a failed migration, if a migration fails for MySQL, it may leave your DB in a partially
migrated state.
MySQL 适配器有一个不幸的限制，即无论事务状态如何，某些操作都会导致`隐式提交 <https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html>`_。 值得注意的是，该列表包括“CREATE TABLE”、“ALTER TABLE”和“DROP TABLE”，它们是 Phinx 将运行的最常见的操作。 这意味着，与其他将尝试在失败的迁移时优雅地回滚事务的适配器不同，如果 MySQL 的迁移失败，它可能会使您的数据库处于部分迁移的状态。

SQLite
`````````````````

Declaring an SQLite database uses a simplified structure:
声明 SQLite 数据库使用简化的结构：

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

When using the ``sqlsrv`` adapter and connecting to a named instance you should
omit the ``port`` setting as SQL Server will negotiate the port automatically.
Additionally, omit the ``charset: utf8`` or change to ``charset: 65001`` which
corresponds to UTF8 for SQL Server.
当使用``sqlsrv`` 适配器并连接到一个命名实例时，你应该省略``port`` 设置，因为 SQL Server 将自动协商端口。 此外，省略 ``charset: utf8`` 或更改为 ``charset: 65001`` 对应于 SQL Server 的 UTF8。

Custom Adapters 自定义适配器
`````````````````

You can provide a custom adapter by registering an implementation of the `Phinx\\Db\\Adapter\\AdapterInterface`
with `AdapterFactory`:
您可以通过使用 `AdapterFactory` 注册 `Phinx\\Db\\Adapter\\AdapterInterface` 的实现来提供自定义适配器：

.. code-block:: php

    $name  = 'fizz';
    $class = 'Acme\Adapter\FizzAdapter';

    AdapterFactory::instance()->registerAdapter($name, $class);

Adapters can be registered any time before `$app->run()` is called, which normally
called by `bin/phinx`.
适配器可以在调用 `$app->run()` 之前的任何时间注册，通常由 `bin/phinx` 调用。

Aliases 别名
-------

Template creation class names can be aliased and used with the ``--class`` command line option for the :doc:`Create Command <commands>`.
模板创建类名称可以使用别名，并与 :doc:`Create Command <commands>` 的 ``--class`` 命令行选项一起使用。

The aliased classes will still be required to implement the ``Phinx\Migration\CreationInterface`` interface.
别名类仍然需要实现“Phinx\Migration\CreationInterface”接口。

.. code-block:: yaml

    aliases:
        permission: \Namespace\Migrations\PermissionMigrationTemplateGenerator
        view: \Namespace\Migrations\ViewMigrationTemplateGenerator

Version Order 版本顺序
-------------

When rolling back or printing the status of migrations, Phinx orders the executed migrations according to the
``version_order`` option, which can have the following values:

* ``creation`` (the default): migrations are ordered by their creation time, which is also part of their filename.
* ``execution``: migrations are ordered by their execution time, also known as start time.
当回滚或打印迁移状态时，Phinx 根据 ``version_order`` 选项对执行的迁移进行排序，该选项可以具有以下值：

* ``creation`` （默认）：迁移按其创建时间排序，这也是其文件名的一部分。
* ``执行``：迁移按执行时间排序，也称为开始时间。

Bootstrap Path 引导路径
---------------

You can provide a path to a `bootstrap` php file that will included before any commands phinx commands are run. Note that
setting External Variables to modify the config will not work because the config has already been parsed by this point.
您可以提供 `bootstrap` php 文件的路径，该文件将在运行任何命令 phinx 命令之前包含。 请注意，设置外部变量来修改配置将不起作用，因为此时配置已经被解析。

.. code-block:: yaml

    paths:
        bootstrap: 'phinx-bootstrap.php'

Within the bootstrap script, the following variables will be available:
在引导脚本中，以下变量将可用：

.. code-block:: php

    /**
     * @var string $filename The file name as provided by the configuration
     * @var string $filePath The absolute, real path to the file
     * @var \Symfony\Component\Console\Input\InputInterface $input The executing command's input object
     * @var \Symfony\Component\Console\Output\OutputInterface $output The executing command's output object
     * @var \Phinx\Console\Command\AbstractCommand $context the executing command object
     */
