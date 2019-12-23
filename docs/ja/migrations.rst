.. index::
   single: Writing Migrations

マイグレーションを書く
======================

Phinx は、データベースを変換するためにマイグレーションに依存しています。
各マイグレーションは、一意のファイル内の PHP クラスによって表されます。
Phinx の PHP API を使用してマイグレーションを記述することをお勧めしますが、
生の SQL もサポートされています。

新しいマイグレーションの作成
----------------------------

マイグレーションファイルのスケルトンを生成
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

新しい Phinx マイグレーションを作成することから始めましょう。
``create`` コマンドを使って Phinx を実行してください。

.. code-block:: bash

    $ php vendor/bin/phinx create MyNewMigration

これにより、 ``YYYYMMDDHHMMSS_my_new_migration.php`` という形式で
新しいマイグレーションが作成されます。最初の14文字は現在のタイムスタンプで置き換えられます。

複数のマイグレーションのパスを指定した場合は、新しいマイグレーションを作成するパスを
選択するよう求められます。

Phinx は、単一の方法でスケルトンのマイグレーションファイルを自動的に作成します。 ::

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
         * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
         *
         * The following commands can be used in this method and Phinx will
         * automatically reverse them when rolling back:
         *
         *    createTable
         *    renameTable
         *    addColumn
         *    renameColumn
         *    addIndex
         *    addForeignKey
         *
         * Remember to call "create()" or "update()" and NOT "save()" when working
         * with the Table class.
         */
        public function change()
        {

        }
    }

全ての Phinx マイグレーションは、 ``AbstractMigration`` クラスを継承します。
このクラスは、データベースのマイグレーションを作成するために必要なサポートを提供します。
データベースのマイグレーションでは、新しいテーブルの作成、行の挿入、インデックスの追加、
カラムの変更など、さまざまな方法でデータベースを更新できます。

change メソッド
~~~~~~~~~~~~~~~

Phinx 0.2.0 は、可逆マイグレーションと呼ばれる新機能を導入しました。
この機能がデフォルトのマイグレーション方法になりました。
可逆マイグレーションでは、 ``change`` のロジックを定義するだけでよく、
Phinx は自動的にマイグレーションする方法を理解することができます。
例::

    <?php

    use Phinx\Migration\AbstractMigration;

    class CreateUserLoginsTable extends AbstractMigration
    {
        /**
         * Change Method.
         *
         * More information on this method is available here:
         * http://docs.phinx.org/en/latest/migrations.html#the-change-method
         *
         * Uncomment this method if you would like to use it.
         */
        public function change()
        {
            // テーブルの作成
            $table = $this->table('user_logins');
            $table->addColumn('user_id', 'integer')
                  ->addColumn('created', 'datetime')
                  ->create();
        }

        /**
         * Migrate Up.
         */
        public function up()
        {

        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

このマイグレーションを実行すると、Phinx は up すると ``user_logins`` テーブルを作成し、
down するとテーブルを削除する方法を自動的に見つけ出します。 ``change`` メソッドが存在する場合、
Phinx は自動的に ``up`` メソッドと ``down`` メソッドを無視することに注意してください。
これらのメソッドを使う必要がある場合、別のマイグレーションファイルを作成することをお勧めします。

.. note::

    ``change()`` メソッドの中でテーブルを作成または更新する場合は、Table の ``create()``
    と ``update()`` メソッドを使用する必要があります。Phinx は、 ``save()`` の呼び出しが
    新しいテーブルを作成しているのか、既存のテーブルを変更しているのかを自動的に判断することはできません。

Phinx は、次のコマンドでのみ、逆にすることができます。

-  createTable
-  renameTable
-  addColumn
-  renameColumn
-  addIndex
-  addForeignKey

コマンドを元に戻せない場合、Phinx はマイグレーション中に ``IrreversibleMigrationException``
例外をスローします。

up メソッド
~~~~~~~~~~~

up メソッドは、マイグレーション中に Phinx によって自動的に実行され、
指定されたマイグレーションが以前に実行されていないことを検出します。
データベースを目的の変更に変換するには、up メソッドを使用する必要があります。

down メソッド
~~~~~~~~~~~~~

down メソッドは、マイグレーション中に Phinx によって自動的に実行され、
指定されたマイグレーションが過去に実行されたことを検出します。
up メソッドで記述された変換を元に戻すには、down メソッドを使用する必要があります。

クエリーの実行
--------------

クエリーは、 ``execute()`` と ``query()`` メソッドで実行できます。
``execute()`` メソッドは影響を受ける行の数を返しますが、 ``query()`` メソッドは結果を
`PDOStatement <http://php.net/manual/ja/class.pdostatement.php>`_
として返します。 ::

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
            $count = $this->execute('DELETE FROM users'); // 影響を受ける行の数を返します

            // query()
            $stmt = $this->query('SELECT * FROM users'); // PDOStatement を返します
            $rows = $stmt->fetchAll(); // 配列として結果を返します
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

.. note::

    これらのコマンドは、PHP のデータベースにアクセスするための軽量で一貫した
    インターフェースを定義する PHP Data Objects（PDO）拡張を使用して実行されます。
    ``execute()`` コマンドを使う前に、必ずクエリーが PDO に従っていることを確認してください。
    これは、DELIMITER をサポートしていないストアードプロシージャーまたはトリガーの挿入時に
    DELIMITER を使用する場合に特に重要です。

.. warning::

    クエリーのバッチで ``execute()`` や ``query()`` を使用すると、
    PDO はバッチに1つ以上のクエリーに問題があったとしても、例外をスローしません。

    したがって、バッチ全体が問題なく通過したものとみなされます。

    Phinx が潜在的な結果セットを反復して、１つのエラーがあることを発見した場合、
    以前の結果セットを得る機能が PDO にはないため、Phinx はすべての結果へのアクセスを拒否します。
    (``previousSet()`` ではなく
    `nextRowset() <http://php.net/manual/ja/pdostatement.nextrowset.php>`_) 。

    その結果、バッチ処理されたクエリーの例外を投げないようにする PDO の設計上の決定により、
    Phinx はクエリーのバッチが提供されたときにエラー処理を最大限にサポートすることができません。

    幸いにも、PDO のすべての機能が利用可能であるため、
    `nextRowset()  <http://php.net/manual/ja/pdostatement.nextrowset.php>`_
    を呼び出して `errorInfo <http://php.net/manual/ja/pdostatement.errorinfo.php>`_
    を調べることで、複数のバッチをマイグレーション中に制御することができます。

行の取得
--------

行を取得するには2つのメソッドがあります。 ``fetchRow()`` メソッドは単一の行を取得し、
``fetchAll()`` メソッドは複数の行を返します。どちらのメソッドも、唯一のパラメーターとして
生の SQL を受け取ります。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class MyNewMigration extends AbstractMigration
    {
        /**
         * Migrate Up.
         */
        public function up()
        {
            // ユーザーを１件取得
            $row = $this->fetchRow('SELECT * FROM users');

            // メッセージの配列を取得
            $rows = $this->fetchAll('SELECT * FROM messages');
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

データの挿入
------------

Phinx ではテーブルにデータを簡単に挿入できます。この機能は :doc:`シード機能 <seeding>`
を対象としていますが、マイグレーションでも insert メソッドを自由に使うこともできます。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class NewStatus extends AbstractMigration
    {
        /**
         * Migrate Up.
         */
        public function up()
        {
            // １行のみ追加
            $singleRow = [
                'id'    => 1,
                'name'  => 'In Progress'
            ];

            $table = $this->table('status');
            $table->insert($singleRow);
            $table->saveData();

            // 複数行の追加
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

            // これは便利なショートカットです
            $this->insert('status', $rows);
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

    insert メソッドは、 ``change()`` メソッドの中で使うことはできません。
    ``up()`` と ``down()`` メソッドを使用してください。

テーブルの操作
-------------------

Table オブジェクト
~~~~~~~~~~~~~~~~~~

Table オブジェクトは、 Phinx が提供する最も便利な API の一つです。
これにより、PHP コードを使用して簡単にデータベーステーブルを操作できます。
データベースのマイグレーションの中から ``table()`` メソッドを呼び出すことによって、
Table オブジェクトのインスタンスを取得することができます。 ::

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

次に、Table オブジェクトによって提供されるメソッドを使用して、このテーブルを操作できます。

save メソッド
~~~~~~~~~~~~~

Table オブジェクトを操作する場合、Phinx は特定の操作を保留中の変更キャッシュに保存します。

疑わしいときは、このメソッドを呼び出すことをお勧めします。
これは、データベースに対する保留中の変更をコミットします。

テーブルの作成
~~~~~~~~~~~~~~

テーブルの作成は、Table オブジェクトを使用するととても簡単です。
ユーザーのコレクションを格納するテーブルを作成しましょう。 ::

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

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

カラムは ``addColumn()`` メソッドを使って追加されます。 ``addIndex()`` メソッドを使用して、
username と email カラムの両方に一意なインデックスを作成します。
最後に ``save()`` を呼び出すと、データベースへの変更がコミットされます。

.. note::

    Phinx は、すべてのテーブルに対して ``id`` という名前のオートインクリメントの主キーを
    自動的に作成します。

``id`` オプションは自動的に作成された識別フィールドの名前を設定し、
``primary_key`` オプションは主キーに使われるフィールドを選択します。
``id`` は、 false に設定されていない限り、 ``primary_key`` オプションを上書きします。
主キーが必要ない場合は、 ``primary_key`` を指定せずに ``id`` を false に設定してください。
主キーは作成されません。

別の主キーを指定するには、Table オブジェクトにアクセスする際に ``primary_key``
オプションを指定します。自動的な ``id`` カラムを無効にし、
代わりに2つのカラムを使って主キーを作成しましょう。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class MyNewMigration extends AbstractMigration
    {
        /**
         * Migrate Up.
         */
        public function up()
        {
            $table = $this->table('followers', ['id' => false, 'primary_key' => ['user_id', 'follower_id']]);
            $table->addColumn('user_id', 'integer')
                  ->addColumn('follower_id', 'integer')
                  ->addColumn('created', 'datetime')
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

1つの ``primary_key`` を設定しても、 ``AUTO_INCREMENT`` オプションは有効になりません。
単純に主キーの名前を変更するには、デフォルトの ``id`` フィールド名を上書きする必要があります。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class MyNewMigration extends AbstractMigration
    {
        /**
         * Migrate Up.
         */
        public function up()
        {
            $table = $this->table('followers', ['id' => 'user_id']);
            $table->addColumn('follower_id', 'integer')
                  ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

さらに、MySQL アダプターは次のオプションをサポートしています。

========== ===========
オプション  説明
========== ===========
comment    テーブルにテキストコメントを設定
engine     テーブルエンジンの定義 *(デフォルトは `InnoDB`)*
collation  テーブル照合順序の定義 *(デフォルトは `utf8_general_ci`)*
signed     主キーが ``符号付き`` かどうか
========== ===========

デフォルトでは、主キーは ``符号付き`` です。
単純に unsigned に設定するには ``signed`` オプションに ``false`` の値を渡します。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class MyNewMigration extends AbstractMigration
    {
        /**
         * Migrate Up.
         */
        public function up()
        {
            $table = $this->table('followers', ['signed' => false]);
            $table->addColumn('follower_id', 'integer')
                  ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

有効なカラムの型
~~~~~~~~~~~~~~~~~~

カラム型は文字列として指定され、次のいずれかになります。

-  biginteger
-  binary
-  boolean
-  date
-  datetime
-  decimal
-  float
-  integer
-  string
-  text
-  time
-  timestamp
-  uuid

さらに、MySQL アダプターは、 ``enum`` 、 ``set`` 、 ``blob`` 、 ``json``
カラム型をサポートしています。 (``json`` は MySQL 5.7 以降)

さらに、Postgres アダプターは、 ``smallint`` 、 ``json`` 、 ``jsonb`` 、 ``uuid`` 、
``cidr`` 、 ``inet`` 、 ``macaddr`` カラム型をサポートしています。(PostgreSQL 9.3 以降)

有効なオプションについては、以下の `有効なカラムのオプション`_ を参照してください。

テーブルが存在するかどうかの判断
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``hasTable()`` メソッドを使うことによって、テーブルが存在するかどうかを判断することができます。 ::

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
                // 何かします
            }
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

テーブルの削除
~~~~~~~~~~~~~~~~

Table は ``dropTable()`` メソッドを使って非常に簡単に削除することができます。
テーブルを ``down()`` メソッドで再作成することは良い考えです。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class MyNewMigration extends AbstractMigration
    {
        /**
         * Migrate Up.
         */
        public function up()
        {
            $this->dropTable('users');
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

テーブル名の変更
~~~~~~~~~~~~~~~~

テーブルの名前を変更するには、Table オブジェクトのインスタンスにアクセスし、
``rename()`` メソッドを呼び出します。 ::

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
            $table->rename('legacy_users')
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {
            $table = $this->table('legacy_users');
            $table->rename('users')
                  ->save();
        }
    }

カラムの操作
--------------------

.. _valid-column-types:

有効なカラムの型
~~~~~~~~~~~~~~~~~~

カラムの型は文字列として指定され、次のいずれかになります。

-  biginteger
-  binary
-  boolean
-  char
-  date
-  datetime
-  decimal
-  float
-  integer
-  string
-  text
-  time
-  timestamp
-  uuid

さらに、MySQL アダプターは、 ``enum`` 、 ``set`` 、 ``blob`` 、 ``json``
カラム型をサポートしています。 (``json`` は MySQL 5.7 以降)

さらに、Postgres アダプターは、 ``smallint`` 、 ``json`` 、 ``jsonb`` 、 ``uuid`` 、
``cidr`` 、 ``inet`` 、 ``macaddr`` カラム型をサポートしています。(PostgreSQL 9.3 以降)


有効なカラムのオプション
~~~~~~~~~~~~~~~~~~~~~~~~

有効なカラムのオプションは次のとおりです。

全てのカラム型:

========== ===========
オプション  説明
========== ===========
limit      文字列の最大長を設定します。また、アダプターのカラムの種類を示します（下記の注を参照）
length     ``limit`` の別名
default    デフォルトの値やアクションを設定
null       ``NULL`` 値の許可 (主キーで使用してはいけません！）
after      新しいカラムの前に配置するカラムを指定
comment    カラムのテキストコメントを設定
========== ===========

``decimal`` カラム:

========== ===========
オプション  説明
========== ===========
precision  ``scale`` と組み合わせ、数値全体の桁数を設定
scale      ``precision`` と組み合わせ、少数点以下の桁数を設定
signed     ``unsigned`` オプションを有効または無効にする *(MySQL のみ適用)*
========== ===========

``enum`` と ``set`` カラム:

========== ===========
オプション  説明
========== ===========
values     カンマ区切りリストまたは値の配列
========== ===========

``integer`` と ``biginteger`` カラム:

========== ===========
オプション  説明
========== ===========
identity   自動インクリメントを有効または無効にする
signed     ``unsigned`` オプションを有効または無効にする *(MySQL のみ適用)*
========== ===========

``timestamp`` カラム:

========== ===========
オプション  説明
========== ===========
default    デフォルト値を設定 (``CURRENT_TIMESTAMP`` を使用)
update     行が更新されたときにトリガーされるアクションを設定 (``CURRENT_TIMESTAMP`` を使用)
timezone   ``time`` と ``timestamp`` カラムの ``with time zone`` オプションを有効または無効にする *(Postgres のみ適用)*
========== ===========

``addTimestamps()`` を使うことで、テーブルに ``created_at`` と ``updated_at``
タイムスタンプを追加できます。このメソッドでは、代わりの名を指定することもできます。 ::

    <?php

    use Phinx\Migration\AbstractMigration;

    class MyNewMigration extends AbstractMigration
    {
        /**
         * Migrate Change.
         */
        public function change()
        {
            // 'updated_at' カラム名を 'amended_at' で上書きします
            $table = $this->table('users')->addTimestamps(null, 'amended_at')->create();
        }
    }

``boolean`` カラム:

========== ===========
オプション  説明
========== ===========
signed     ``unsigned`` オプションを有効または無効にする *(MySQL のみ適用)*
========== ===========

``string`` と ``text`` カラム:

========== ===========
オプション  説明
========== ===========
collation  テーブルのデフォルトとは異なる照合順序を設定 *(MySQL のみ適用)*
encoding   テーブルのデフォルトとは異なる文字セットを設定 *(MySQL のみ適用)*
========== ===========

外部キーの定義:

========== ===========
オプション  説明
========== ===========
update     行が更新されたときにトリガーされるアクションを設定
delete     行が削除されたときにトリガーされるアクションを設定
========== ===========

オプションの第3引数配列を使用して、これらのオプションの1つ以上を任意のカラムに渡すことができます。

PostgreSQL の Limit オプション
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

PostgreSQL アダプターを使用する場合、 ``integer`` カラムに対してデータベースのカラム型のヒントを
追加することができます。次のいずれかのオプションで ``limit`` を使うと、
それに応じてカラムの型が変更されます。

============ ==============
Limit        カラム型
============ ==============
INT_SMALL    SMALLINT
============ ==============

.. code-block:: php

     use Phinx\Db\Adapter\PostgresAdapter;

     //...

     $table = $this->table('cart_items');
     $table->addColumn('user_id', 'integer')
           ->addColumn('subtype_id', 'integer', ['limit' => PostgresAdapter::INT_SMALL])
           ->create();

MySQL の Limit オプション
~~~~~~~~~~~~~~~~~~~~~~~~~~

MySQL アダプターを使用する場合、 ``integer`` 、 ``text`` 、および ``blob`` カラムに対して、
データベースのカラム型のヒントを追加することができます。次のいずれかのオプションで ``limit`` を使うと、
それに応じてカラムの型が変更されます。

============ ==============
Limit        カラム型
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

.. code-block:: php

     use Phinx\Db\Adapter\MysqlAdapter;

     //...

     $table = $this->table('cart_items');
     $table->addColumn('user_id', 'integer')
           ->addColumn('product_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
           ->addColumn('subtype_id', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
           ->addColumn('quantity', 'integer', ['limit' => MysqlAdapter::INT_TINY])
           ->create();

カラム一覧の取得
~~~~~~~~~~~~~~~~~

すべてのテーブルのカラムを取得するには、 `table` オブジェクトを作成し、
`getColumns()` メソッドを呼び出します。
このメソッドは、基本情報を持つ Column クラスの配列を返します。 例::

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

カラムが存在するかどうかの確認
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``hasColumn()`` メソッドを使ってテーブルに特定のカラムがすでにあるかどうかを調べることができます。 ::

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
                // 何かします
            }

        }
    }

カラム名の変更
~~~~~~~~~~~~~~~~~

カラムの名前を変更するには、Table オブジェクトのインスタンスにアクセスし、
``renameColumn()`` メソッドを呼び出します。 ::

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
            $table->renameColumn('bio', 'biography')
               ->update();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {
            $table = $this->table('users');
            $table->renameColumn('biography', 'bio')
               ->update();
        }
    }

別のカラムの後にカラムの追加
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

列を追加するときに、 ``after`` オプションを使用してその位置を指定することができます。 ::

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

カラムの削除
~~~~~~~~~~~~~~~~~

カラムを削除するには、 ``removeColumn()`` メソッドを使用してください。 ::

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

カラムの制限を指定
~~~~~~~~~~~~~~~~~~~~~~~~~

``limit`` オプションを使ってカラムの最大長を制限できます。 ::

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

カラムの属性を変更
~~~~~~~~~~~~~~~~~~~~~~~~~~

既存のカラムのカラム型またはオプションを変更するには、 ``changeColumn()`` メソッドを使用します。
使用可能な値に関しては、 :ref:`valid-column-types` や `有効なカラムのオプション`_ をご覧ください。 ::

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

インデックスの操作
--------------------

テーブルにインデックスを追加するには、テーブルオブジェクトに対して
``addIndex()`` メソッドを呼び出すことができます。 ::

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

デフォルトでは、Phinx はデータベースアダプターに通常のインデックスを作成するよう指示します。
一意のインデックスを指定するために、 ``unique`` を ``addIndex()`` メソッドに渡すことができます。
また、 ``name`` パラメーターを使ってインデックスの名前を明示的に指定することもできます。 ::

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
                  ->addIndex(['email'], ['unique' => true, 'name' => 'idx_users_email'])
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

MySQL アダプターは、 ``fulltext`` インデックスもサポートしています。
5.6 より前のバージョンを使用している場合は、
テーブルが ``MyISAM`` エンジンを使用していることを確認する必要があります。 ::

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

``removeIndex()`` メソッドを呼び出すと簡単にインデックスが削除できます。
各インデックスに対してこのメソッドを呼び出す必要があります。 ::

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

            // あるいは、インデックスの名前で削除することもできます。例:
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

外部キーの操作
--------------

Phinx は、データベーステーブルに外部キー制約を作成する機能をサポートしています。
例のテーブルに外部キーを追加しましょう。 ::

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
            $refTable->addColumn('tag_id', 'integer')
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

「削除時」および「更新時」アクションは、 'delete' および 'update' オプション配列で定義されます。
使用可能な値は、 'SET_NULL'、 'NO_ACTION'、 'CASCADE' および 'RESTRICT' です。
制約名は 'constraint' オプションで変更できます。

``addForeignKey()`` にカラムの配列を渡すこともできます。
これにより、複合キーを使用するテーブルとの外部キー関係を確立することができます。 ::

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

``constraint`` パラメーターを使って名前付きの外部キーを追加することができます。
この機能は Phinx バージョン 0.6.5 でサポートされます。 ::

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
                                ['constraint'=>'your_foreign_key_name']);
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

外部キーが存在するかどうかも簡単に確認できます。 ::

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
                // 何かします
            }
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }

最後に、外部キーを削除するには、 ``dropForeignKey`` メソッドを使用します。 ::

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
            $table->dropForeignKey('tag_id');
        }

        /**
         * Migrate Down.
         */
        public function down()
        {

        }
    }
