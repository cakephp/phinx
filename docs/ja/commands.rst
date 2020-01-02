.. index::
   single: Commands

コマンド
########

Phinx は多くのコマンドを使用して実行されます。

マイグレーションコマンド
========================

init コマンド
----------------

init コマンド (initialize の略) は、Phinx のプロジェクトを準備するために使用されます。
このコマンドはプロジェクトディレクトリーのルートに ``phinx.yml`` ファイルを生成します。

.. code-block:: bash

        $ cd yourapp
        $ phinx init .

このファイルをテキストエディターで開き、プロジェクトの設定を行います。
詳しくは :doc:`設定 <configuration>` の章をご覧下さい。

create コマンド
---------------

create コマンドは、新しいマイグレーションファイルを作成するために使用されます。
1つの引数、つまりマイグレーション名が必要です。
マイグレーション名は、キャメルケース形式で指定する必要があります。

.. code-block:: bash

        $ phinx create MyNewMigration

新しいマイグレーションファイルをテキストエディターで開き、データベースの変更を追加します。
Phinx は ``phinx.yml`` ファイルで指定されたパスを使ってマイグレーションファイルを作成します。
詳しくは :doc:`設定 <configuration>` の章をご覧下さい。

代替のテンプレートファイル名を指定することで、
Phinx が使用するテンプレートファイルを上書きすることができます。

.. code-block:: bash

        $ phinx create MyNewMigration --template="<file>"

テンプレート生成クラスを提供することもできます。このクラスはインタフェース
``Phinx\Migration\CreationInterface`` を実装しなければなりません。

.. code-block:: bash

        $ phinx create MyNewMigration --class="<class>"

マイグレーションのテンプレートを提供するだけでなく、クラスはマイグレーションファイルが
テンプレートから生成されると呼び出されるコールバックを定義することもできます。

``--template`` と ``--class`` の両方を使用することはできません。

migrate コマンド
----------------

migrate コマンドは、すべての使用可能なマイグレーションを実行します。
オプションで特定のバージョンまで実行できます。

.. code-block:: bash

        $ phinx migrate -e development

特定のバージョンにマイグレーションするには、 ``--target`` パラメーターまたは省略して
``-t`` を使用します。

.. code-block:: bash

        $ phinx migrate -e development -t 20110103081132

``--dry-run`` を使って、クエリーを実行せずに標準出力に出力します。

.. code-block:: bash

        $ phinx migrate --dry-run

rollback コマンド
-----------------

rollback コマンドは、Phinx によって実行された以前のマイグレーションを取り消すために使用されます。
これは、migrate コマンドの反対です。

引数を指定せずに ``rollback`` コマンドを使用すると、以前の移行にロールバックすることができます。

.. code-block:: bash

        $ phinx rollback -e development

すべてのマイグレーションを特定のバージョンにロールバックするには、 ``--target`` パラメーターまたは
省略して ``-t`` を使用します。

.. code-block:: bash

        $ phinx rollback -e development -t 20120103083322

ターゲットバージョンとして 0 を指定すると、すべてのマイグレーションが元に戻ります。

.. code-block:: bash

        $ phinx rollback -e development -t 0

すべてのマイグレーションを特定の日付にロールバックするには、 ``--date`` パラメーターまたは省略して
``-d`` を省略して使用します。

.. code-block:: bash

        $ phinx rollback -e development -d 2012
        $ phinx rollback -e development -d 201201
        $ phinx rollback -e development -d 20120103
        $ phinx rollback -e development -d 2012010312
        $ phinx rollback -e development -d 201201031205
        $ phinx rollback -e development -d 20120103120530

ブレークポイントが設定され、さらにロールバックをブロックしている場合は、 ``--force`` パラメーターまたは
``-f`` を使ってブレークポイントをオーバーライドすることができます。

.. code-block:: bash

        $ phinx rollback -e development -t 0 -f

``--dry-run`` を使って、クエリーを実行せずに標準出力に出力します。

.. code-block:: bash

        $ phinx rollback --dry-run

.. note::

	ロールバックすると、Phinx は ``phinx.yml`` ファイルの ``version_order`` オプションで
	指定された順序で実行されたマイグレーションを処理します。
        詳しくは :doc:`設定 <configuration>` の章をご覧下さい。

status コマンド
---------------

status コマンドは、すべてのマイグレーションのリストを現在のステータスとともに表示します。
このコマンドを使用して、実行されたマイグレーションを確認できます。

.. code-block:: bash

        $ phinx status -e development

このコマンドは、データベースが最新の場合（つまり、すべてのマイグレーションが稼働している場合）
コード0で終了します。またはそれ以外の場合は、次のコードのいずれかで終了します。

* 1: 実行されるマイグレーションが少なくとも1つ残っています。
* 2: マイグレーションが実行され、データベースに記録されましたが、マイグレーションファイルが有りません。

breakpoint コマンド
-------------------

breakpoint コマンドは、ブレークポイントを設定するために使用され、ロールバックを制限することができます。
最新のマイグレーションのブレークポイントは、パラメーターを指定しないで切り替えることができます。

.. code-block:: bash

        $ phinx breakpoint -e development

特定のバージョンでブレークポイントを切り替えるには、 ``--target`` パラメーターまたは省略して
``-t`` を使用します。

.. code-block:: bash

        $ phinx breakpoint -e development -t 20120103083322

全てのブレークポイントを削除するには、 ``--remove-all`` パラメーターまたは省略して
``-r`` を使用します。

.. code-block:: bash

        $ phinx breakpoint -e development -r

ブレークポイントは、 ``status`` コマンドを実行すると表示されます。

データベースの初期データ投入
============================

seed:create コマンド
--------------------

seed:create コマンドを使用して、新しいデータベースシードクラスを作成できます。
1つの引数、クラスの名前が必要です。クラス名はキャメルケース形式で指定する必要があります。

.. code-block:: bash

        $ phinx seed:create MyNewSeeder

テキストエディターで新しいシードファイルを開き、データベースシードコマンドを追加します。
Phinx は ``phinx.yml`` ファイルで指定されたパスを使ってシードファイルを作成します。
詳しくは :doc:`設定 <configuration>` の章をご覧下さい。

seed:run コマンド
-----------------

seed:run コマンドは、使用可能なすべてのシードクラスを実行するか、オプションで1つだけを実行します。

.. code-block:: bash

        $ phinx seed:run -e development

シードクラスを1つだけ実行するには、 ``--seed`` パラメーターまたは省略して ``-s`` を使用します。

.. code-block:: bash

        $ phinx seed:run -e development -s MyNewSeeder

設定ファイルパラメーター
------------------------

コマンドラインから Phinx を実行するときは、 ``--configuration`` または
``-c`` パラメーターを使って設定ファイルを指定することができます。
YAML に加えて、設定ファイルは PHP 配列として PHP ファイルの計算された出力でもよいです。

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
                        "port" => $_ENV['DB_PORT'],
                    ]
                ]
            ];

Phinx は ``*.yml`` と ``*.php`` 拡張子を持つファイルにどの言語パーサーを使うかを自動的に検出します。
適切なパーサーは、 ``--parser`` と ``-p`` パラメーターで指定することもできます。
``"php"`` 以外は YAML として扱われます。

PHP 配列を使用する場合、既存の PDO インスタンスに ``connection`` キーを提供することができます。
Phinx は ``hasTable()`` のような特定のメソッドに対してデータベース名を必要とするため、
データベース名も渡すことも重要です。

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
                        "name" => "dev_db",
                        "connection" => $pdo_instance
                    ]
                ]
            ];

ウェブアプリ内で Phinx を実行
-----------------------------

Phinx は ``Phinx\Wrapper\TextWrapper`` クラスを使ってウェブアプリケーションの内部で
実行することもできます。この例は ``app/web.php`` で提供されています。
これはスタンドアロンサーバーとして実行できます。

.. code-block:: bash

        $ php -S localhost:8000 vendor/robmorgan/phinx/app/web.php

これはデフォルトで現在のマイグレーションの状態を表示する `<http://localhost:8000>`__
にローカルウェブサーバーを作成します。マイグレーションを実行するには、
`<http://localhost:8000/migrate>`__ を使用し、ロールバックには
`<http://localhost:8000/rollback>`__ を使用します。

**付属のウェブアプリは一例に過ぎません、本番環境では使用しないでください！**

.. note::

	実行時に設定変数を変更し、 ``%%PHINX_DBNAME%%`` やその他の動的オプションを変更するには、
	コマンドを実行する前に ``$_SERVER['PHINX_DBNAME']`` を設定します。
	使用可能なオプションは、設定ページに記載されています。

PHPUnit で Phinx を使用
-----------------------

Phinx は、ユニットテスト内でデータベースを準備またはシードするために使用できます。
プログラムによって使用することができます。

.. code-block:: php

        public function setUp ()
        {
          $app = new PhinxApplication();
          $app->setAutoExit(false);
          $app->run(new StringInput('migrate'), new NullOutput());
        }

メモリーデータベースを使用する場合は、Phinx に特定の PDO インスタンスを提供する必要があります。
Manager クラスを使用して Phinx と直接対話することができます。

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
                // シード後にデフォルトのフェッチモードを変更することができます
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
                $this->pdo = $pdo;
            }

        }
