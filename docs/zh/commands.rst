.. index::
   single: Commands

命令
========

Phinx 使用许多命令运行。

断点命令（breakpoint）
----------------------

breakpoint 命令用于设置断点，允许您限制回滚。不提供任何参数则默认切换至最近一次迁移的断点。

.. code-block:: bash

        $ phinx breakpoint -e development

要在特定版本上切换断点，使用 ``--target`` 参数或 ``-t``。

.. code-block:: bash

        $ phinx breakpoint -e development -t 20120103083322

您可以使用 ``--remove-all`` 参数或 ``-r`` 来删除所有断点。

.. code-block:: bash

        $ phinx breakpoint -e development -r

使用 ``-set`` 和 ``--unset`` 可在最近迁移中设置或取消断点（而不仅仅是切换）。
（或与 ``--target`` 或 ``-t`` 参数联合使用来指定迁移对象）

运行 ``status`` 命令时，断点可见。

创建命令（create）
------------------

Create 命令用于创建新的迁移文件。它需要一个参数：迁移的名称。迁移名称应以 CamelCase 格式指定。

.. code-block:: bash

        $ phinx create MyNewMigration

在文本编辑器中打开新的迁移文件以添加数据库转换逻辑。Phinx 使用 Phinx 配置文件中指定的路径创建迁移文件。
参阅 :doc:`配置 <configuration>` 章节了解更多信息。

您可以通过提供替代模板文件名来覆盖 Phinx 使用的模板文件。

.. code-block:: bash

        $ phinx create MyNewMigration --template="<file>"

您还可以提供模板生成类。此类必须实现接口 ``Phinx\Migration\CreationInterface``。

.. code-block:: bash

        $ phinx create MyNewMigration --class="<class>"

除了为迁移提供模板外，该类还可以定义一个回调，一旦从模板生成迁移文件就会调用该回调。

你不能同时使用 ``--template`` 和 ``--class``。

初始化命令（init）
----------------

init 命令（initialize 的缩写）用于为 Phinx 准备项目。
此命令在项目目录的根目录中生成 phinx 配置文件。
默认情况下，此文件将命名为 ``phinx.php``。

.. code-block:: bash

        $ phinx init

或者，您可以为 Phinx 的配置文件指定自定义位置：

.. code-block:: bash

        $ phinx init ./custom/location/

还可以指定自定义文件名：

.. code-block:: bash

        $ phinx init custom-config.yml

以及与 php、yml 和 json 不同的格式。例如，要创建 yml 文件：

.. code-block:: bash

        $ phinx init --format yml

在您的文本编辑器中打开此文件以设置您的项目配置。
参阅 :doc:`配置 <configuration>` 章节了解更多信息。

迁移命令（migrate）
-------------------

migrate 命令运行所有可用的迁移，可以选择直到特定版本。

.. code-block:: bash

        $ phinx migrate -e development

要迁移到特定版本，请使用 ``--target`` 参数或简写为 ``-t``。

.. code-block:: bash

        $ phinx migrate -e development -t 20110103081132

用于 ``--dry-run`` 将查询打印到标准输出而不执行它们。

.. code-block:: bash

        $ phinx migrate --dry-run

回滚命令（rollback）
--------------------

rollback 命令用于撤销 Phinx 之前执行的迁移。它与 migrate 命令相反。

rollback 不提供任何参数则默认回滚最近一次迁移。

.. code-block:: bash

        $ phinx rollback -e development

要回滚到特定版本，请使用 ``--target`` 参数或简写为 ``-t``。

.. code-block:: bash

        $ phinx rollback -e development -t 20120103083322

将 0 指定为目标版本则将回滚所有迁移。

.. code-block:: bash

        $ phinx rollback -e development -t 0

要回滚所有迁移到特定日期，请使用 ``--date`` 参数或简写为 ``-d``。

.. code-block:: bash

        $ phinx rollback -e development -d 2012
        $ phinx rollback -e development -d 201201
        $ phinx rollback -e development -d 20120103
        $ phinx rollback -e development -d 2012010312
        $ phinx rollback -e development -d 201201031205
        $ phinx rollback -e development -d 20120103120530

如果设置了断点（breakpoint），阻止了进一步的回滚，可以使用 ``--force`` 参数或简写为 ``-f`` 来覆盖断点。

.. code-block:: bash

        $ phinx rollback -e development -t 0 -f

用于 ``--dry-run`` 将查询打印到标准输出而不执行它们。

.. code-block:: bash

        $ phinx rollback --dry-run

.. note::

        回滚时，Phinx 使用 Phinx 配置文件中 ``version_order`` 指定的顺序对执行的迁移进行排序。
        参阅 :doc:`配置 <configuration>` 章节了解更多信息。

状态命令（status）
------------------

status 命令打印所有迁移的列表及其当前状态。您可以使用此命令来确定已运行了哪些迁移。

.. code-block:: bash

        $ phinx status -e development

如果数据库是最新的（即所有迁移都已完成），则此命令以代码 0 退出，否则以下列代码之一退出：

* 2: 至少有一个缺失的迁移。
* 3: 至少有一次向下迁移。

An exit code of 1 means an application error has occurred.
退出代码 1 表示发生了应用程序错误。

种子创建命令（seed:create）
-----------------------

The Seed Create command can be used to create new database seed classes. It
requires one argument, the name of the class. The class name should be specified
in CamelCase format.

.. code-block:: bash

        $ phinx seed:create MyNewSeeder

Open the new seed file in your text editor to add your database seed commands.
Phinx creates seed files using the path specified in your configuration file.
Please see the :doc:`Configuration <configuration>` chapter for more information.

You are able to override the template file used by Phinx by supplying an
alternative template filename.

.. code-block:: bash

        $ phinx seed:create MyNewSeeder --template="<file>"

The Seed Run Command
--------------------

The Seed Run command runs all of the available seed classes or optionally just
one.

.. code-block:: bash

        $ phinx seed:run -e development

To run only one seed class use the ``--seed`` parameter or ``-s`` for short.

.. code-block:: bash

        $ phinx seed:run -e development -s MyNewSeeder

Configuration File Parameter
----------------------------

When running Phinx from the command line, you may specify a configuration file
using the ``--configuration`` or ``-c`` parameter. In addition to YAML, the
configuration file may be the computed output of a PHP file as a PHP array:

.. code-block:: php

        <?php
            return [
                "paths" => [
                    "migrations" => "application/migrations"
                ],
                "environments" => [
                    "default_migration_table" => "phinxlog",
                    "default_environment" => "dev",
                    "dev" => [
                        "adapter" => "mysql",
                        "host" => $_ENV['DB_HOST'],
                        "name" => $_ENV['DB_NAME'],
                        "user" => $_ENV['DB_USER'],
                        "pass" => $_ENV['DB_PASS'],
                        "port" => $_ENV['DB_PORT']
                    ]
                ]
            ];

Phinx auto-detects which language parser to use for files with ``*.yaml``, ``*.yml``, ``*.json``, and ``*.php`` extensions.
The appropriate parser may also be specified via the ``--parser`` and ``-p`` parameters. Anything other than  ``"json"`` or
``"php"`` is treated as YAML.

When using a PHP array, you can provide a ``connection`` key with an existing PDO instance. It is also important to pass
the database name too, as Phinx requires this for certain methods such as ``hasTable()``:

.. code-block:: php

        <?php
            return [
                "paths" => [
                    "migrations" => "application/migrations"
                ),
                "environments" => [
                    "default_migration_table" => "phinxlog",
                    "default_environment" => "dev",
                    "dev" => [
                        "name" => "dev_db",
                        "connection" => $pdo_instance
                    ]
                ]
            ];

Running Phinx in a Web App
--------------------------

Phinx can also be run inside of a web application by using the ``Phinx\Wrapper\TextWrapper``
class. An example of this is provided in ``app/web.php``, which can be run as a
standalone server:

.. code-block:: bash

        $ php -S localhost:8000 vendor/robmorgan/phinx/app/web.php

This will create local web server at `<http://localhost:8000>`__ which will show current
migration status by default. To run migrations up, use `<http://localhost:8000/migrate>`__
and to rollback use `<http://localhost:8000/rollback>`__.

**The included web app is only an example and should not be used in production!**

.. note::

        To modify configuration variables at runtime and override ``%%PHINX_DBNAME%%``
        or other another dynamic option, set ``$_SERVER['PHINX_DBNAME']`` before
        running commands. Available options are documented in the Configuration page.

Wrapping Phinx in another Symfony Console Application
-----------------------------------------------------

Phinx can be wrapped and run as part of a separate Symfony console application. This
may be desirable to present a unified interface to the user for all aspects of your
application, or because you wish to run multiple Phinx commands. While you could
run the commands through ``exec`` or use the above ``Phinx\Wrapper\TextWrapper``,
though this makes it hard to deal with the return code and output in a similar fashion
as your application.

Luckily, Symfony makes doing this sort of "meta" command straight-forward:

.. code-block:: php

    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Phinx\Console\PhinxApplication;

    // ...

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $phinx = new PhinxApplication();
        $command = $phinx->find('migrate');

        $arguments = [
            'command'         => 'migrate',
            '--environment'   => 'production',
            '--configuration' => '/path/to/phinx/config/file'
        ];

        $input = new ArrayInput($arguments);
        $returnCode = $command->run(new ArrayInput($arguments), $output);
        // ...
    }

Here, you are instantianting the ``PhinxApplication``, telling it to find the ``migrate``
command, defining the arguments to pass to it (which match the commandline arguments and flags),
and then finally running the command, passing it the same ``OutputInterface`` that your
application uses.

See this `Symfony page <https://symfony.com/doc/current/console/calling_commands.html>`_ for more information.

Using Phinx with PHPUnit
--------------------------

Phinx can be used within your unit tests to prepare or seed the database. You can use it programatically :

.. code-block:: php

        public function setUp ()
        {
          $app = new PhinxApplication();
          $app->setAutoExit(false);
          $app->run(new StringInput('migrate'), new NullOutput());
        }

If you use a memory database, you'll need to give Phinx a specific PDO instance. You can interact with Phinx directly
using the Manager class :

.. code-block:: php

        use PDO;
        use Phinx\Config\Config;
        use Phinx\Migration\Manager;
        use PHPUnit\Framework\TestCase;
        use Symfony\Component\Console\Input\StringInput;
        use Symfony\Component\Console\Output\NullOutput;

        class DatabaseTestCase extends TestCase {

            public function setUp ()
            {
                $pdo = new PDO('sqlite::memory:', null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $configArray = require('phinx.php');
                $configArray['environments']['test'] = [
                    'adapter'    => 'sqlite',
                    'connection' => $pdo
                ];
                $config = new Config($configArray);
                $manager = new Manager($config, new StringInput(' '), new NullOutput());
                $manager->migrate('test');
                $manager->seed('test');
                // You can change default fetch mode after the seeding
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
                $this->pdo = $pdo;
            }

        }
