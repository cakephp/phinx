.. index::
   single: Commands

命令
========

Phinx 使用许多命令运行。

断点命令（breakpoint）
----------------------

breakpoint 命令用于设置断点，允许你限制回滚。不提供任何参数则默认切换至最近一次迁移的断点。

.. code-block:: bash

        $ phinx breakpoint -e development

要在特定版本上切换断点，使用 ``--target`` 参数或 ``-t``。

.. code-block:: bash

        $ phinx breakpoint -e development -t 20120103083322

你可以使用 ``--remove-all`` 参数或 ``-r`` 来删除所有断点。

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

你可以通过提供替代模板文件名来覆盖 Phinx 使用的模板文件。

.. code-block:: bash

        $ phinx create MyNewMigration --template="<file>"

你还可以提供模板生成类。此类必须实现接口 ``Phinx\Migration\CreationInterface``。

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

或者，你可以为 Phinx 的配置文件指定自定义位置：

.. code-block:: bash

        $ phinx init ./custom/location/

还可以指定自定义文件名：

.. code-block:: bash

        $ phinx init custom-config.yml

以及与 php、yml 和 json 不同的格式。例如，要创建 yml 文件：

.. code-block:: bash

        $ phinx init --format yml

在你的文本编辑器中打开此文件以设置你的项目配置。
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

status 命令打印所有迁移的列表及其当前状态。你可以使用此命令来确定已运行了哪些迁移。

.. code-block:: bash

        $ phinx status -e development

如果数据库是最新的（即所有迁移都已完成），则此命令以代码 0 退出，否则以下列代码之一退出：

* 2: 至少有一个缺失的迁移。
* 3: 至少有一次向下迁移。

退出代码 1 表示发生了应用程序错误。

种子创建命令（seed:create）
-----------------------

seed:create 命令用于创建新的数据库种子类。它需要一个参数，即类的名称。类名应以 CamelCase 格式指定。

.. code-block:: bash

        $ phinx seed:create MyNewSeeder

在文本编辑器中打开新的种子文件以添加数据库种子命令。Phinx 使用 Phinx 配置文件中指定的路径创建种子文件。
参阅 :doc:`配置 <configuration>` 章节了解更多信息。

你可以通过提供替代模板文件名来覆盖 Phinx 使用的模板文件。

.. code-block:: bash

        $ phinx seed:create MyNewSeeder --template="<file>"

执行种子命令（seed:run）
--------------------

seed:run 命令将执行所有可用的种子类，或者只执行某一个。

.. code-block:: bash

        $ phinx seed:run -e development

To run only one seed class use the ``--seed`` parameter or ``-s`` for short.
要仅运行一个种子类，请使用 ``--seed`` 参数或简写为 ``-s``。

.. code-block:: bash

        $ phinx seed:run -e development -s MyNewSeeder

配置文件参数
----------------------------

从命令行运行 Phinx 时，你可以使用 ``--configuration`` 或 ``-c`` 参数指定配置文件。
除了 YAML 格式外，配置文件也可以是 PHP 数组形式的文件输出：

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

Phinx 自动检测扩展名为 ``*.yaml``, ``*.yml``, ``*.json`` 和 ``*.php``的文件该使用哪种语言解析器。
也可以通过 ``--parser`` 或 ``-p`` 参数指定适当的解析器。 ``"json"`` 或 ``"php"`` 以外的任何内容都被视为 YAML 格式。

使用 PHP 数组时，你可以为键名 ``connection`` 设置一个已存在的 PDO 实例。
传递数据库名称也很重要，因为 Phinx 的某些方法需要它，例如 ``hasTable()``：

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

在 Web 应用程序中运行 Phinx
--------------------------

通过使用  ``Phinx\Wrapper\TextWrapper`` 类，Phinx 也可以在 Web 应用程序内部运行。
在 ``app/web.php``中提供了一个示例，它可以作为独立服务器运行：

.. code-block:: bash

        $ php -S localhost:8000 vendor/robmorgan/phinx/app/web.php

这将在 `<http://localhost:8000>`__ 创建本地 Web 服务器，默认显示当前迁移状态。
要运行迁移，可使用 `<http://localhost:8000/migrate>`__，而回滚则为 `<http://localhost:8000/rollback>`__。

**包含的网络应用程序只是一个示例，不要在生产环境中使用！**

.. note::

        要在运行时修改配置变量并覆盖 ``%%PHINX_DBNAME%%`` 或其他其他动态选项，要在运行命令之前设置 ``$_SERVER['PHINX_DBNAME']``。
        其它可用选项可在[配置]页面中找到。

在其它 Symfony 控制台应用程序中打包 Phinx
-----------------------------------------------------

Phinx 可以作为单独的 Symfony 控制台应用程序的一部分进行打包和运行。
这样做或许是希望为用户提供应用程序的所有方面的统一界面，或者因为你希望运行多个 Phinx 命令。
虽然可以通过 ``exec`` 命令或上述介绍的 ``Phinx\Wrapper\TextWrapper`` 来执行命令，
但这会使得应用程序以类似的方式处理返回代码和输出将变得困难。

幸运的是，Symfony 可以直接执行这种 "meta" 命令：

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

在这里，你正在实例化 ``PhinxApplication``，告诉它找到 ``migrate`` 命令，
定义要传递给它的参数（与命令行参数和标志匹配），然后最终运行命令，传递与应用程序所使用的相同的 ``OutputInterface``。

有关更多信息，请参阅此 `Symfony 页面 <https://symfony.com/doc/current/console/calling_commands.html>`_ 。

将 Phinx 与 PHPUnit 一起使用
--------------------------

Phinx 可以在你的单元测试中用于准备或播种数据库。你可以以编程的方式使用它：

.. code-block:: php

        public function setUp ()
        {
          $app = new PhinxApplication();
          $app->setAutoExit(false);
          $app->run(new StringInput('migrate'), new NullOutput());
        }

如果你使用内存数据库（memory database），你需要给 Phinx 一个特定的 PDO 实例。
你可以使用 Manager 类直接与 Phinx 交互：

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
