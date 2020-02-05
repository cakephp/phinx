.. index::
   single: Configuration

構成設定
=============

:doc:`init コマンド <commands>` を使ってプロジェクトを初期化すると、
Phinx はプロジェクトディレクトリーのルートに ``phinx.yml`` というデフォルトファイルを作成します。
このファイルは、YAML データのシリアル化形式を使用します。

``--configuration`` コマンドラインオプションが与えられた場合、Phinx は指定されたファイルを
ロードします。それ以外の場合は、 ``phinx.php`` 、 ``phinx.json`` , ``phinx.yml``,
または ``phinx.yaml`` を見つけて、最初に見つかったファイルを読み込みます。詳しくは、
:doc:`コマンド <commands>` の章をご覧下さい。

.. warning::

    設定ファイルは、ウェブサーバー上の一般公開されているディレクトリーの外に保存してください。
    このファイルにはデータベースの信用情報が含まれており、
    誤ってプレーンテキストとして提供される可能性があります。

JSON ファイルと YAML ファイルは *パース* されますが、PHP ファイルは *インクルード* されています。
つまり、こういうことです。

* 設定項目の配列を `return` する必要があります。
* 変数スコープはローカルです。つまり、初期化ファイルが読み取ったり変更したりするグローバル変数を
  明示的に宣言する必要があります。
* その標準出力は抑制されます。
* JSON や YAML とは異なり、環境接続の詳細を省略し、代わりに初期化された PDO インスタンスを含む
  ``connection`` を指定することができます。
  これは、マイグレーションがアプリケーションとやり取りしたり、同じ接続を共有したりする場合に便利です。
  ただし、Phinx は PDO 接続からデータベース名を推測できないため、データベース名を渡すことを
  忘れないでください。

::

    <?php
    require 'app/init.php';

    global $app;
    $pdo = $app->getDatabase()->getPdo();

    return ['environments' =>
             [
               'default_database' => 'development',
               'development' => [
                 'name' => 'devdb',
                 'connection' => $pdo,
               ]
             ]
           ];

マイグレーションのパス
----------------------

最初のオプションは、マイグレーションのディレクトリーへのパスを指定します。
デフォルトでは Phinx は ``%%PHINX_CONFIG_DIR%%/db/migrations`` を使用します。

.. note::

    ``%%PHINX_CONFIG_DIR%%`` は特別なトークンで、
    ``phinx.yml`` ファイルが保存されているルートディレクトリーに自動的に置き換えられます。

デフォルトの ``%%PHINX_CONFIG_DIR%%/db/migrations`` を上書きするには、
yaml 設定に次の行を追加する必要があります。

.. code-block:: yaml

    paths:
        migrations: /your/full/path

また、設定内の配列を使用して複数のマイグレーションパスを提供することもできます。

.. code-block:: yaml

    paths:
        migrations:
            - application/module1/migrations
            - application/module2/migrations

パスに ``%%PHINX_CONFIG_DIR%%`` トークンを使うこともできます。

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/your/relative/path'

マイグレーションは ``glob`` で取り込まれるので、複数のディレクトリーのパターンを定義することができます。

.. code-block:: yaml

    paths:
        migrations: '%%PHINX_CONFIG_DIR%%/module/*/{data,scripts}/migrations'

カスタムベースクラス
---------------------

デフォルトでは、すべてのマイグレーションは Phinx の ``AbstractMigration`` クラスを継承します。
これは、設定の中で ``migration_base_class`` を設定することによって、 ``AbstractMigration``
を継承したカスタムクラスに設定することができます。

.. code-block:: yaml

    migration_base_class: MyMagicalMigration

シードのパス
------------

2番目のオプションは、シードディレクトリーへのパスを指定します。
デフォルトでは Phinx は ``%%PHINX_CONFIG_DIR%%/db/seeds`` を使用します。

.. note::

    ``%%PHINX_CONFIG_DIR%%`` は特別なトークンで、
    ``phinx.yml`` ファイルが保存されているルートディレクトリーに自動的に置き換えられます。

デフォルトの ``%%PHINX_CONFIG_DIR%%/db/seeds`` を上書きするには、
yaml 設定に以下を追加する必要があります。

.. code-block:: yaml

    paths:
        seeds: /your/full/path

また、設定内で配列を使用して複数のシードパスを指定することもできます。

.. code-block:: yaml

    paths:
        seeds:
            - /your/full/path1
            - /your/full/path2

パスに ``%%PHINX_CONFIG_DIR%%`` トークンを使うこともできます。

.. code-block:: yaml

    paths:
        seeds: '%%PHINX_CONFIG_DIR%%/your/relative/path'

環境
----

Phinx の主な機能の1つは、複数のデータベース環境をサポートすることです。Phinx を使用して、
開発環境でマイグレーションを作成した後、本番環境で同じマイグレーションを実行することができます。
環境は ``environments`` 以下にネストされたコレクションで指定されます。例:

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_database: development
        production:
            adapter: mysql
            host: localhost
            name: production_db
            user: root
            pass: ''
            port: 3306
            charset: utf8
            collation: utf8_unicode_ci

上記は ``production`` と呼ばれる新しい環境を定義します。

複数の開発者が同じプロジェクトで作業し、それぞれが異なる環境を持つ状況
（例えば、 ``<environment type>-<developer name>-<machine name>`` のような規約）、
または、別々の目的（ブランチ、テストなど）のために別々の環境を持つ必要がある場合には、
環境変数 `PHINX_ENVIRONMENT` を使用して yaml ファイルのデフォルト環境を上書きします。

.. code-block:: bash

    export PHINX_ENVIRONMENT=dev-`whoami`-`hostname`

テーブルのプレフィクスとサフィックス
------------------------------------

テーブルのプレフィックスとサフィックスを定義することができます。

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

ソケット接続
------------

MySQL アダプターを使用する場合、ネットワーク接続の代わりにソケットを使用することもできます。
ソケットのパスは ``unix_socket`` で設定されます。

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_database: development
        production:
            adapter: mysql
            name: production_db
            user: root
            pass: ''
            unix_socket: /var/run/mysql/mysql.sock
            charset: utf8

外部変数
--------

Phinx は ``PHINX_`` というプレフィックスが付いた環境変数を自動的に取得し、
設定ファイルのトークンとして利用できるようにします。
トークンは変数とまったく同じ名前になりますが、どちらの側にも2つの
``%%`` のシンボルをラップすることによってアクセスする必要があります。
例: ``%%PHINX_DBUSER%%`` 。これは、秘密のデータベース資格情報をバージョン管理システムではなく
サーバーに直接格納する場合に特に便利です。この機能は、次の例で簡単に実証できます。

.. code-block:: yaml

    environments:
        default_migration_table: phinxlog
        default_database: development
        production:
            adapter: mysql
            host: '%%PHINX_DBHOST%%'
            name: '%%PHINX_DBNAME%%'
            user: '%%PHINX_DBUSER%%'
            pass: '%%PHINX_DBPASS%%'
            port: 3306
            charset: utf8

サポートするアダプター
----------------------

Phinx は現在、次のデータベースアダプターをネイティブにサポートしています。

* `MySQL <http://www.mysql.com/>`_: ``mysql`` アダプターを指定。
* `PostgreSQL <http://www.postgresql.org/>`_: ``pgsql`` アダプターを指定。
* `SQLite <http://www.sqlite.org/>`_: ``sqlite`` アダプターを指定。
* `SQL Server <http://www.microsoft.com/sqlserver>`_: ``sqlsrv`` アダプターを指定。

SQLite
~~~~~~

SQLite データベースを宣言すると、単純化された構造が使用されます。

.. code-block:: yaml

    environments:
        development:
            adapter: sqlite
            name: ./data/derby
        testing:
            adapter: sqlite
            memory: true     # *任意* の値で memory を設定すると、 name が上書きされます

SQL Server
~~~~~~~~~~

``sqlsrv`` アダプターを使用して名前付きインスタンスに接続するときは、
SQL Server が自動的にポートをネゴシエートするので、 ``port`` 設定を省略してください。
さらに、 ``charset: utf8`` を省略するか、SQL Server の UTF8 に対応する
``charset: 65001`` に変更してください。

カスタムアダプター
~~~~~~~~~~~~~~~~~~

``Phinx\\Db\\Adapter\\AdapterInterface`` の実装を ``AdapterFactory`` で登録することで
カスタムアダプターを提供できます。

.. code-block:: php

    $name  = 'fizz';
    $class = 'Acme\Adapter\FizzAdapter';

    AdapterFactory::instance()->registerAdapter($name, $class);

アダプターは `$app->run()` が呼び出される前にいつでも登録することができます。
通常は `bin/phinx` によって呼び出されます。

エイリアス
----------

テンプレート作成クラス名は、別名をつけて :doc:`create コマンド<commands>` の
``--class`` コマンドラインオプションで使うことができます。

エイリアス化されたクラスは ``Phinx\Migration\CreationInterface`` インタフェースを実装する
必要があります。

.. code-block:: yaml

    aliases:
        permission: \Namespace\Migrations\PermissionMigrationTemplateGenerator
        view: \Namespace\Migrations\ViewMigrationTemplateGenerator

バージョンの順序
----------------

マイグレーションの状態をロールバックまたは表示するとき、Phinx は ``version_order``
オプションに従って実行されたマイグレーションを処理します。これは次の値をとります。

* ``creation`` (デフォルト): マイグレーションはファイル名の一部でもある作成時間順に並べ替えられます。
* ``execution``: マイグレーションは実行時間（開始時間とも呼ばれます）によって順序付けられます。
