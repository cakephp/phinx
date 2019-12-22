.. index::
   single: Database Seeding

データベースの初期データ投入
============================

バージョン0.5.0 では、Phinx はデータベースにテストデータをシードするためのサポートを導入しました。
シードクラスは、作成済みのデータをデータベースに簡単に投入するための優れた方法です。
デフォルトでは ``seeds`` ディレクトリーに保存されます。 ただし、このパスは設定ファイルで変更できます。

.. note::

    データベースのシードは完全にオプションで、
    Phinx はデフォルトで ``seeds`` ディレクトリーを作成しません。

シードクラスの新規作成
----------------------

Phinx には、新しいシードクラスを簡単に生成するコマンドが含まれています。

.. code-block:: bash

    $ php vendor/bin/phinx seed:create UserSeeder

複数のシードのパスを指定した場合は、新しいシードクラスを作成するパスを選択するように求められます。

以下は、スケルトンテンプレートを元にしています。 ::

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

AbstractSeed クラス
-------------------

すべての Phinx のシードは ``AbstractSeed`` クラスを継承します。
このクラスは、シードクラスを作成するために必要なサポートを提供します。
シードクラスは主にテストデータの挿入に使用されます。

run メソッド
~~~~~~~~~~~~

run メソッドは、 `seed:run` コマンドを実行すると、Phinx によって自動的に呼び出されます。
このメソッドを使用してテストデータを挿入してください。

.. note::

    マイグレーションとは異なり、Phinx はどのシードクラスが実行されたかを把握していません。
    これは、データベースシーダーを繰り返し実行できることを意味します。
    開発時にはこのことに留意してください。

データの挿入
------------

Table オブジェクトの使用
~~~~~~~~~~~~~~~~~~~~~~~~

シードクラスは、使い慣れた ``Table`` オブジェクトを使ってデータを挿入することもできます。
シードクラス内から ``table()`` メソッドを呼び出し、 ``insert()`` メソッドを使用して
データを挿入することで、Table オブジェクトのインスタンスを取得できます。 ::

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
                ],
                [
                    'body'    => 'bar',
                    'created' => date('Y-m-d H:i:s'),
                ]
            ];

            $posts = $this->table('posts');
            $posts->insert($data)
                  ->save();
        }
    }

.. note::

    ``save()`` メソッドを呼び出して、データをテーブルにコミットする必要があります。
    Phinx はデータをバッファリングします。

Faker ライブラリーとの統合
~~~~~~~~~~~~~~~~~~~~~~~~~~

シードクラスですばらしい `Faker ライブラリー <https://github.com/fzaninotto/Faker>`_
を使うのは簡単です。Composer を使用してインストールするだけです。

.. code-block:: bash

    $ composer require fzaninotto/faker

そして、シードクラスの中で、それを使用してください。 ::

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

            $this->insert('users', $data);
        }
    }

テーブルのデータ消去
--------------------

データを挿入することに加えて、Phinx は SQL の ``TRUNCATE`` コマンドを使って
テーブルを空にすることを容易にします。 ::

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
                  ->save();

            // テーブルを空にします
            $posts->truncate();
        }
    }

.. note::

    SQLite は ``TRUNCATE`` コマンドをネイティブにサポートしていないので、 ``DELETE FROM``
    が使用されています。テーブルのデータ消去後、 ``VACUUM`` コマンドを呼び出すことをお勧めします。
    Phinx はこれを自動的には行いません。

シードクラスの実行
------------------

これは簡単な部分です。データベースをシードするには、 ``seed:run`` コマンドを使います。

.. code-block:: bash

    $ php vendor/bin/phinx seed:run

デフォルトでは、Phinx は利用可能なすべてのシードクラスを実行します。
特定のクラスを実行したい場合は、 ``-s`` パラメーターを使ってそのクラスの名前を渡します。

.. code-block:: bash

    $ php vendor/bin/phinx seed:run -s UserSeeder

複数のシーダーを実行することもできます。

.. code-block:: bash

    $ php vendor/bin/phinx seed:run -s UserSeeder -s PermissionSeeder -s LogSeeder

``-v`` パラメーターを使用して、より詳細な出力を表示することもできます。

.. code-block:: bash

    $ php vendor/bin/phinx seed:run -v

Phinx のシード機能は、テストデータをデータベースに簡単かつ繰り返し挿入するための
簡単なメカニズムを提供します。
