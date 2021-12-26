.. index::
   single: Installation

安装
============

Phinx 使用 PHP 依赖管理工具 Composer 进行安装。请访问 `Composer <https://getcomposer.org/>` 获取其更多信息。

.. note::

    Phinx 至少需要 PHP 7.2 版本（或更高）。

安装 Phinx，只需使用 Composer 安装命令：

.. code-block:: bash

    php composer.phar require robmorgan/phinx

在你的项目中创建如下的文件夹结构： ``db/migrations``，并赋予适当权限。
这里将成为迁移文件所在目录，所以它应该是可写的。

Phinx 现在可以在你的项目中执行：

.. code-block:: bash

    vendor/bin/phinx init
